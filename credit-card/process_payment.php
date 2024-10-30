<?php


 function external_process_payment($order_id, $gateway_instance)
	{
        global $woocommerce;
        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order  = new WC_Order($order_id);
        $order           = wc_get_order($order_id);
        $parentOrderId   = $customer_order->get_parent_id();
        $parentOrder     = new WC_Order($parentOrderId);
        $orderTotal      = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->order_total : $customer_order->get_total();
        $orderTotal      = bcmul($orderTotal, 100);
        $orderTotal      = $orderTotal;
        $PhoneNum        = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->billing_phone : $customer_order->get_billing_phone();
        $customerAccode  = preg_replace('/[^0-9]/', '', $PhoneNum);
        $customer_account_number = str_replace(array(
                    ' ',
                    '-'
                ), '', preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['spyr_capitalepay-card-number']));
        $customerCSC     = $_POST['spyr_capitalepay-card-cvc'];
        $saveCard     	 = isset($_POST['cepay_save_card']);
        $cc_expiry       = $gateway_instance->clean_date($_POST['spyr_capitalepay-card-expiry']);
        $token     	 = $_POST['spyr_capitalepay_saved_card'];
	
        // Are we testing right now or is it a real transaction
        $environment     = ($gateway_instance->environment == "yes") ? 'TRUE' : 'FALSE';
        // Decide which URL to post to
        $environment_url = ("FALSE" == $environment) ? 'https://secure.zift.io/gates/xurl?' : 'https://sandbox-secure.zift.io/gates/xurl?';
        
	
		
		$orderItems      = '';
        foreach ($order->get_items() as $item_id => $item_data) {
            $product = $item_data->get_product();
            //$orderItems .= '(code=' . $item_data->get_product_id() . ';quantity=' . $item_data->get_quantity() . ';description=' . substr($gateway_instance->clean_string($product->get_name()), 0, 33) . ';totalAmount=' . bcmul($item_data->get_total(), 100) . ')';
        }

            	
			
	

			
						
			//Change Payment method
			if (isset($_GET['change_payment_method']) && class_exists('WC_Subscriptions_Order')) {
				
				if($customer_account_number && $cc_expiry && $customerCSC && $saveCard){
					
					$data      = array(
						'requestType' => 'tokenization',
						'userName' => $gateway_instance->userName,
						'password' => $gateway_instance->password,
						'accountId' => $gateway_instance->merchantAccountCode,
						'accountType' => $gateway_instance->AccountType,
						'accountNumber' => $customer_account_number,
						'accountAccessory' => $gateway_instance->clean_date($_POST['spyr_capitalepay-card-expiry']),
						'transactionCode' => '',
						'holderName' => $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name()
					);
					$options   = array(
						'http' => array(
							'header' => "Content-type: application/x-www-form-urlencoded",
							'method' => 'POST',
							'content' => http_build_query($data)
						)
					);
					$context   = stream_context_create($options);
					$result    = file_get_contents($environment_url, false, $context);
					$responses = explode('&', $result);
					$response  = array();
					foreach ($responses as $match) { // Check through each match.
						$results               = explode('=', $match); // Separate the string into key and value by '=' as delimiter.
						$response[$results[0]] = trim($results[1], '"'); // Load key and value into array.
						
					}
					$responseCode    = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
					$responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
					$transactionId   = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
					$token           = (!empty($response['token']) ? $response['token'] : '');					
				
					//Save card Details
					$gateway_instance->save_card_details($token, $customer_account_number, $cc_expiry);					
					
				}

				$parentOrder->add_order_note(__('Capital ePay Credit Card Changed: ', 'spyr-capitalepay'));
				$parentOrder->update_meta_data('cepay_customer_token_id', $token);
				$parentOrder->update_meta_data('cepay_cc_expiry', $cc_expiry);
				$parentOrder->save();

				$customer_order->add_order_note(__('Capital ePay Credit Card Changed: ', 'spyr-capitalepay'));				
				$customer_order->update_meta_data('cepay_customer_token_id', $token);
				$customer_order->update_meta_data('cepay_cc_expiry', $cc_expiry);
				$customer_order->save();					
				
				// Redirect to thank you page
				return array(
					'result' => 'success',
					'redirect' => $gateway_instance->get_return_url($customer_order)
				);
				
				
			}
			
			
				
        
		
	


	
	
        // This is where the fun stuff begins
        $payload   = array(
            //CapitalePay Credentials and API Info
            'requestType' => (($gateway_instance->authorizeTransaction == 'yes') ? 'sale-auth' : 'sale'),
            'userName' => $gateway_instance->userName,
            'password' => $gateway_instance->password,
            'accountId' => $gateway_instance->merchantAccountCode,
            'transactionIndustryType' => $gateway_instance->TransactionIndustryType,
            'transactionCode' => '',
            'amount' => $orderTotal,
            'holderType' => $gateway_instance->holderType,
			'holderName' => $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name(),
			'street' => $customer_order->get_shipping_address_1(),
			'city' => $customer_order->get_billing_city(),
			'state' => $customer_order->get_billing_state(),
			'zipCode' => $customer_order->get_billing_postcode(),
            'accountType' => $gateway_instance->AccountType,
            'accountNumber' => $customer_account_number,
            'accountAccessory' => $gateway_instance->clean_date($_POST['spyr_capitalepay-card-expiry']),
            'customerAccountCode' => $PhoneNum,
			'token'=>$token
        );
        // Send this payload to CapitalePay for processing
        $options   = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded",
                'method' => 'POST',
                'content' => http_build_query($payload)
            )
        );
        $context   = stream_context_create($options);
        $result    = file_get_contents($environment_url, false, $context);

        $responses = explode('&', $result);
        $response  = array();
        foreach ($responses as $match) { // Check through each match.
            $results               = explode('=', $match); // Separate the string into key and value by '=' as delimiter.
            $response[$results[0]] = trim($results[1], '"'); // Load key and value into array.
            
        }
        $responseCode    = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
        $transactionId   = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
        $token           = (!empty($response['token']) ? $response['token'] : '');
		
		if($saveCard){
		//Save card Details
		$gateway_instance->save_card_details($token, $customer_account_number, $cc_expiry);		
		}
		
        if (is_wp_error($response))
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-capitalepay'));
        if (empty($response))
            throw new Exception(__('CapitalePay\'s Response was empty.', 'spyr-capitalepay'));
        // Test the code to know if the transaction went through or not.
        // 1 or 4 means the transaction was a success
        if (($responseCode == 'A01')) {
            if (class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($customer_order->id)) {
                WC_Subscriptions_Manager::activate_subscriptions_for_order($customer_order);
                WC_Subscriptions_Manager::process_subscription_payments_on_order($customer_order);
                $subscriptions = wcs_get_subscriptions_for_order(wcs_get_objects_property($customer_order, 'id'), array(
                    'order_type' => 'parent'
                ));
                foreach ($subscriptions as $subscription) {

                    if (version_compare(WC_VERSION, '3.0.0', '<')) {
                        update_post_meta($customer_order->id, 'cepay_customer_token_id', $token);
                        update_post_meta($customer_order->id, 'cepay_cc_expiry', $cc_expiry);
                    } else {
                        $customer_order->update_meta_data('cepay_customer_token_id', $token);
                        $customer_order->update_meta_data('cepay_cc_expiry', $cc_expiry);
                        $customer_order->save();
                    }
                }
            }

            // Mark order as Paid
            $customer_order->payment_complete($transactionId);
            if(class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($customer_order->id)){
	

				// Payment has been successful
				$customer_order->add_order_note(__('Capital ePay scheduled subscription payment completed. Transaction ID: ', 'spyr-capitalepay') . $transactionId);
				$customer_order->update_meta_data('cepay_customer_token_id', $token);
				$customer_order->update_meta_data('cepay_cc_expiry', $cc_expiry);
				$customer_order->save();					
                $customer_order->update_status('completed');
				
			}else{
				
				
				if($gateway_instance->authorizeTransaction=='yes'){
					// Payment has been successful
					$customer_order->add_order_note(__('Capital ePay payment ready for capture. Transaction ID: ', 'spyr-capitalepay') . $transactionId);				
					$customer_order->update_status('processing');
					if (version_compare(WC_VERSION, '3.0.0', '<')) {				
						update_post_meta($customer_order->id, 'cepay_order_authTransec', 1);
						update_post_meta($customer_order->id, 'cepay_order_capture_status', 0);
					}else{
						$customer_order->update_meta_data('cepay_order_authTransec', 1);
						$customer_order->update_meta_data('cepay_order_capture_status', 0);
						$customer_order->save();
					}
				}else{
					// Payment has been successful
					$customer_order->add_order_note(__('Capital ePay payment completed. Transaction ID: ', 'spyr-capitalepay') . $transactionId);
			
					
					$customer_order->update_meta_data('cepay_customer_token_id', $token);
					$customer_order->update_meta_data('cepay_cc_expiry', $cc_expiry);
					$customer_order->save();					
					$customer_order->update_status('completed');					
				}				
				

			}
            // Empty the cart (Very important step)
            $woocommerce->cart->empty_cart();
            // Redirect to thank you page
            return array(
                'result' => 'success',
                'redirect' => $gateway_instance->get_return_url($customer_order)
            );
        } else {
            // Transaction was not succesful
            // Add notice to the cart
            if ($responseCode == 'D05') {
                wc_add_notice('Error : Invalid card number (Invalid Account Number).', 'error');
            } elseif ($responseCode == 'D10') {
                wc_add_notice('Error : Card reported lost/stolen (Lost/Stolen Card).', 'error');
            } elseif ($responseCode == 'D30') {
                wc_add_notice('Error : Call for Authorization (Referral)*.', 'error');
            } elseif ($responseCode == 'D04') {
                wc_add_notice('Error : Hold - Pick up card (Pick Up Card).', 'error');
            } elseif ($responseCode == 'D08') {
                wc_add_notice('Error : CSC is invalid (Decline CSC/CID Fail).', 'error');
            } elseif ($responseCode == 'D03') {
                wc_add_notice('Error : Insufficient Funds.', 'error');
            } elseif ($responseCode == 'E02') {
                wc_add_notice('Error : Processing Network Unavailable.', 'error');
            } elseif ($responseCode == 'E09') {
                wc_add_notice('Error : Processing Network Error.', 'error');
            } elseif ($responseCode == 'A05') {
                wc_add_notice('Error : Partially Approved**.', 'error');
            } elseif ($responseCode == 'D24') {
                wc_add_notice('Error : Chargeback received***.', 'error');
            } elseif ($responseCode == 'D01') {
                wc_add_notice('Error : Denied by customer\'s bank (Do Not Honor)', 'error');
            } elseif ($responseCode == 'D08') {
                wc_add_notice('Error : CSC is invalid (Decline CSC/CID Fail)', 'error');
            } else {
                $customer_order->add_order_note(__('Error', 'spyr-capitalepay') . ($response && $response[0]?$response[0]:'unknown: Cepay Auth Failed ') . '<br>2:' .  ($response && $response[1]?$response[1]:'unknown') . '<br>3:' . ($response && $response[2]?urldecode($response[2]):'unknown'));
                wc_add_notice($responseCode, 'error');
            }
            // Add note to the order for your reference
            /* $customer_order->add_order_note('Error: '); */
        }
}
?>
