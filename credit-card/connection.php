<?php

	/**
	 * Get the environment URL based on the gateway instance configuration.
	 *
	 * This function checks if the environment is set to 'yes' in the gateway
	 * instance. If it is, it means the environment is in sandbox mode and the
	 * sandbox URL is returned. If the environment is not set to 'yes', it means
	 * the environment is in production mode and the production URL is returned.
	 *
	 * @param object $gateway_instance An instance of the gateway class, containing
	 *                                 the environment configuration.
	 *
	 * @return string Returns the environment-specific URL to use for API requests.
	 */

	function get_environment_url($gateway_instance) {
		$environment = ($gateway_instance->environment == "yes") ? 'TRUE' : 'FALSE';
		return ("FALSE" == $environment) ? 'https://secure.zift.io/gates/xurl?' : 'https://sandbox-secure.zift.io/gates/xurl?';
	}


	/**
	 * Sends a payload to the specified API endpoint and processes the response.
	 *
	 * This function takes a payload, the API endpoint URL, and a gateway instance.
	 * It creates an HTTP POST request with the payload, sends it to the API endpoint,
	 * and processes the response by converting it into an array.
	 *
	 * @param array $payload The payload to be sent as the request body.
	 * @param string $environment_url The API endpoint URL to send the request to.
	 * @param object $gateway_instance The gateway instance to use for processing the response.
	 *
	 * @return array The processed response as an associative array.
	 */
	function send_payload_to_api($payload, $environment_url, $gateway_instance) {
		$options = array(
			'http' => array(
				'header' => "Content-type: application/x-www-form-urlencoded",
				'method' => 'POST',
				'content' => http_build_query($payload)
			)
		);

		$context = stream_context_create($options);
		$result = file_get_contents($environment_url, false, $context);
		$responses = explode('&', $result);

		return $responses;
	}


	 function zift_connect_cepay_capture_charge($gateway_instance, $order, $obj, $transaction_id) {
		
		// Are we testing right now or is it a real transaction
		$environment_url = get_environment_url($gateway_instance);
		
		$payload = array(
				'requestType'=>'capture',
				'userName'=>$obj->userName,
				'password'=>$obj->password,
				'accountId'=>$obj->merchantAccountCode,
				'transactionId'=> $transaction_id,

			);

		$responses = send_payload_to_api($payload, $environment_url, $gateway_instance);

        $response = $obj->to_array($responses);

        $responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');

        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'null');

        $transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'null');


		$msg=array('error'=>0, 'msg'=>$responseMessage);
		
		if($responseMessage=="Approved"){
            // Payment has been successful
            $order->add_order_note(__('Capital ePay amount captured successfully. Transaction ID: ', 'spyr-capitalepay') . $transactionId);
            // Mark order as Paid
            $order->payment_complete($transactionId);
			
			if (version_compare(WC_VERSION, '3.0.0', '<')) {				
				update_post_meta($order_id, 'cepay_order_capture_status', 1);
			}else{
				$order->update_meta_data('cepay_order_capture_status', 1);
				$order->save();
			}			
			
            $order->update_status('completed');		
			
			
		}else{
            $order->add_order_note(__('Capital ePay: Capture Error.', 'woocommerce-gateway-inspire'));
			
		}
		
		
		wp_send_json( $msg );
			
	 }


	/**
	 * Sends a tokenization request to Zift (CapitalePay) to add a new payment method for the user.
	 * 
	 * This function takes the card details provided by the user through a form and sends a tokenization
	 * request to the Zift (CapitalePay) API. The API then returns a token representing the card, which
	 * can be used for future transactions.
	 *
	 * @param object $gateway_instance An instance of the payment gateway class, containing configuration settings.
	 * 
	 * @return array $response An associative array containing the API response, which includes the token and other details.
	 * 
	 * @throws Exception If there is an error during the API request.
	 */

	 function zift_connect_add_payment_method($gateway_instance) {
		
		// Are we testing right now or is it a real transaction
		$environment_url = get_environment_url($gateway_instance);

		$card_number = str_replace(array(' ', '-'), '', preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['spyr_capitalepay-card-number']));
		$card_expiry = $gateway_instance->clean_date($_POST['spyr_capitalepay-card-expiry']);

		// Send this payload to CapitalePay for processing
		$payload = array(
			'requestType' => 'tokenization',
			'userName' => $gateway_instance->userName,
			'password' => $gateway_instance->password,
			'accountId' => $gateway_instance->merchantAccountCode,
			'accountType' => $gateway_instance->AccountType,
			'accountNumber' => $card_number,
			'accountAccessory' => $card_expiry
		);

		// Call the send_payload_to_api function
		$responses = send_payload_to_api($payload, $environment_url, $gateway_instance);

		$response = array();

		foreach ($responses as $match) {
			$results = explode('=', $match);
			$response[$results[0]] = trim($results[1], '"');
		}
		
		$responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
		$responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
		$transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
		$token_cepay = (!empty($response['token']) ? $response['token'] : '');

		$user_id = get_current_user_id();

		// Get customer tokens
		$tokens = WC_Payment_Tokens::get_customer_tokens($user_id);

		// Check if the card token already exists in the user's account
		$token_exists = false;
		foreach ($tokens as $existing_token) {
			if ($existing_token->get_token() === $token_cepay) {
				$token_exists = true;
				break;
			}
		}

		if ($token_exists) {
			 wc_add_notice(__('This payment method has already been added to your account.', 'woocommerce'), 'error');
			 return array(
				'result' => 'failure',
				'redirect' => wc_get_endpoint_url('payment-methods'),
			);

		}else{ 


			// Save card Details
			$gateway_instance->save_card_details($token_cepay, $card_number, $card_expiry);

			return array(
				'result' => 'success',
				'redirect' => wc_get_endpoint_url('payment-methods'),
			);
		}
		 
	 }


	/**
	 * Processes a subscription payment through the Zift (CapitalePay) API.
	 * 
	 * This function sends a sale request to the Zift (CapitalePay) API to process a subscription payment.
	 * It takes the necessary payment and customer details and sends them to the API for processing.
	 * Based on the API response, it updates the customer order status and adds appropriate order notes.
	 *
	 * @param object $gateway_instance An instance of the payment gateway class, containing configuration settings.
	 * @param WC_Order $customer_order The WooCommerce order object for the subscription payment.
	 * @param float $orderTotal The total amount of the order.
	 * @param string $PhoneNum The customer's phone number.
	 * @param string $cc_expiry The expiry date of the credit card.
	 * @param string $cc_token The token representing the credit card.
	 * 
	 * @throws Exception If there is an error during the API request or the response is empty.
	 */

	 function zift_connect_process_subscription_payment($gateway_instance, $customer_order, $parent_order, $orderTotal,$PhoneNum,$cc_expiry, $cc_token) {
		 
		$environment_url = get_environment_url($gateway_instance);

        // This is where the fun stuff begins
        $payload   = array(
            //CapitalePay Credentials and API Info
            'requestType' => 'sale',
            'userName' => $gateway_instance->userName,
            'password' => $gateway_instance->password,
            'accountId' => $gateway_instance->merchantAccountCode,
            'transactionIndustryType' => $gateway_instance->TransactionIndustryType,
            'amount' => $orderTotal,
            'holderType' => $gateway_instance->holderType,
			'holderName' => $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name(),
			'street' => $customer_order->get_shipping_address_1(),
			'city' => $customer_order->get_billing_city(),
			'state' => $customer_order->get_billing_state(),
			'zipCode' => $customer_order->get_billing_postcode(),
            'accountType' => $gateway_instance->AccountType,
            'customerAccountCode' => $PhoneNum,
            'accountAccessory' => $cc_expiry,
            'token' => $cc_token
        );
        // Send this payload to CapitalePay for processing
		// Call the send_payload_to_api function
		$responses = send_payload_to_api($payload, $environment_url, $gateway_instance);
        $response  = $gateway_instance->to_array($responses);
        
        $responseCode    = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
        $transactionId   = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
        $token           = (!empty($response['token']) ? $response['token'] : '');	 
	 
        if (is_wp_error($response)) {
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-capitalepay'));
        }
        if (empty($response)) {
            throw new Exception(__('CapitalePay\'s Response was empty.', 'spyr-capitalepay'));
        }	 
	 
        // Test the code to know if the transaction went through or not.
        // 1 or 4 means the transaction was a success
        if ($responseCode == 'A01') {
            // Payment has been successful
            $customer_order->add_order_note(__('Capital ePay scheduled subscription payment completed. Transaction ID: ', 'spyr-capitalepay') . $transactionId);

			$customer_order->update_meta_data('cepay_customer_token_id', $token);
			$customer_order->update_meta_data('cepay_cc_expiry', $cc_expiry);
			$customer_order->save();		

            // Mark order as Paid
            $customer_order->payment_complete($transactionId);
            $customer_order->update_status('completed');
        } else {
            // Transaction was not succesful
            // Add notice to the cart
            if ($responseCode == 'D05') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Payment declined.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D10') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. The cardholder’s bank has declined the transaction and requested the cardholder’s credit card to be retained because the card was reported lost or stolen.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D30') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. To proceed with the transaction, call for authorization is needed to confirm the validity of the card.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D04') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. The cardholder’s bank has declined the transaction and requested cardholder’s credit card to be retained.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D08') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. CSC value is invalid.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D03') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Specified credit card does not have sufficient funds.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'E02') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Processing network is temporarily unavailable.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'E09') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Error ocurred during the connection process after the transaction was submitted.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'A05') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription: Transaction has been partially approved as a result of split payment.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D24') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription: Chargeback has been received.', 'spyr-capitalepay'));
            } elseif ($responseCode == 'D01') {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Transaction has been denied by cardholder\'s bank.', 'spyr-capitalepay'));
            } else {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed', 'spyr-capitalepay') . ($response && $response[0]?$response[0]:'unknown: Cepay Auth Failed ') . '<br>2:' .  ($response && $response[1]?$response[1]:'unknown') . '<br>3:' . ($response && $response[2]?urldecode($response[2]):'unknown'));
            }
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($customer_order);
        }
	 
	 }

	/**
	 * Processes a refund through the Zift (CapitalePay) API.
	 *
	 * This function sends a refund request to the Zift (CapitalePay) API for a given transaction.
	 * It takes the necessary payment details, transaction ID, and refund amount, and sends them
	 * to the API for processing. Based on the API response, it updates the order status and adds
	 * appropriate order notes.
	 *
	 * @param object $gateway_instance An instance of the payment gateway class, containing configuration settings.
	 * @param string $transaction_id The ID of the transaction to be refunded.
	 * @param float $amount The amount to be refunded.
	 * @param WC_Order $order The WooCommerce order object related to the refund.
	 *
	 * @return bool Returns true if the refund was successful, and false if it failed.
	 */
	
	 function zift_connect_process_refund($gateway_instance, $transaction_id, $amount, $order) {
		 
		$environment_url = get_environment_url($gateway_instance);

        $payload = array(

            'requestType' => 'refund',

            'userName' => $gateway_instance->userName,

            'password' => $gateway_instance->password,

            'amount' => bcmul($amount, 100) ,

            'accountId' => $gateway_instance->merchantAccountCode,

            'transactionId' => $transaction_id

        );

        // Send this payload to CapitalePay for processing
		// Call the send_payload_to_api function
		$responses = send_payload_to_api($payload, $environment_url, $gateway_instance);

        $response = $gateway_instance->to_array($responses);



        $responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');

        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'null');

        $transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'null');
		
        if ($responseCode == 'A01')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed.Transaction has been approved.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A02')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Credit has been posted on a cardholder’s account.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A03')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Void has been posted on a cardholder’s account (with authorization reversal).', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A04')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. No updates have been made as a result of account updater operation.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A05')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Transaction has been partially approved as a result of split payment.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A06')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Void has been posted on a cardholder’s account (without authorization reversal).', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A07')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Partial void has been posted on a cardholder’s account.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A08')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Partial refund has been posted on a cardholder’s account.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A09')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Increment has been posted on a cardholder’s account.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'A10')

        {

            // Success

            $order->add_order_note(__('Capital ePay refund completed. Request has been accepted.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return true;

        }

        elseif ($responseCode == 'D01')

        {

            // Failure

            $order->add_order_note(__('Capital ePay refund error. Response data: Transaction has been denied by cardholder\'s bank.', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return false;

        }

        else

        {

            // Failure

            $order->add_order_note(__('Capital ePay refund error. Response data: ', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $responseMessage);

            return false;

        }		
	 
	 }
	
	 
?>	 