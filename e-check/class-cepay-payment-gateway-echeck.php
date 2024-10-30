<?php
/* CapitalePay Payment Gateway Class */
class CEPAY_WC_Gateway_Cheque extends WC_Payment_Gateway
{
    // Setup our Gateway's id, description and other values
    function __construct()
    {
        // The global ID for this Payment method
        $this->id = "cepay_cheque";
        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("CapitalePay - Check payments", 'Check payment method', 'cepay_cheque');
        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("Take payments in person via checks.", 'cepay_cheque');
        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("CapitalePay", 'cepay_cheque');
        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = apply_filters('woocommerce_cheque_icon', '');
        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;
        // Supports the default credit card form
        $this->supports = array(
            'products',
            'multiple_subscriptions',
            'subscription_payment_method_change_admin',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'refunds'
        );
        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value)
        {
            $this->$setting_key = $value;
        }
        // Save settings
        if (is_admin())
        {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
        if (class_exists('WC_Subscriptions_Order'))
        {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array(
                $this,
                'scheduled_subscription_payment'
            ) , 10, 2);
            add_filter('woocommerce_subscription_payment_meta', array(
                $this,
                'add_subscription_payment_meta'
            ) , 10, 2);
        }
        // Add hooks
        add_action('admin_notices', array(
            $this,
            'cepay_commerce_ssl_check'
        ));
    } // End __construct()
    
    /**
     * Check if SSL is enabled and notify the user.
     */
    function cepay_commerce_ssl_check()
    {
        if (get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes')
        {
            $admin_url = admin_url('admin.php?page=wc-settings&tab=checkout');
            echo '<div class="error"><p>' . sprintf(__('Capital ePay payment gateway is enabled and the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woocommerce-gateway-inspire') , $admin_url) . '</p></div>';
        }
    }
    public function payment_fields()
    {
        // ok, let's display some description before the payment form
        if ($this->description)
        {
            // you can instructions for test mode, I mean test card numbers etc.
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }
        // I will echo() the form, but you can close PHP tags and print it directly in HTML
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        // Add this action hook if you want your custom payment gateway to support it
        do_action('woocommerce_credit_card_form_start', $this->id);
        // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
        echo '<p class="form-row form-row-wide"><label>Bank account number <span class="required">*</span></label>
				<input id="cepay_account_number" class="input-text cepay-input-field" type="text" autocomplete="off" name="cepay-account-number">
			</p>
			<p class="form-row form-row-wide">
				<label>Bank routing number <span class="required">*</span></label>
				<input id="cepay_routing_number" class="input-text cepay-input-field"  type="text" autocomplete="off" name="cepay-routing-number">
			</p>
			<div class="clear"></div>';
        do_action('woocommerce_credit_card_form_end', $this->id);
        echo '<div class="clear"></div></fieldset>';
    }
    /**
     * Process a refund if supported
     *
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        $transaction_id = str_replace("providerTransactionCode=", "", $order->get_transaction_id());
        // Are we testing right now or is it a real transaction
        $environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
        // Decide which URL to post to
        $environment_url = ("FALSE" == $environment) ? 'https://secure.zift.io/gates/xurl?' : 'https://sandbox-secure.zift.io/gates/xurl?';
        if ($amount == 0)
        {
            $order->add_order_note(__('Capital ePay: The amount is already refunded.', 'woocommerce-gateway-inspire'));
            return true;
        }
        // This is where the fun stuff begins
        $payload = array(
            'requestType' => 'refund',
            'userName' => $this->userName,
            'password' => $this->password,
            'amount' => bcmul($amount, 100) ,
            'accountId' => $this->merchantAccountID,
            'transactionId' => $transaction_id
        );
        // Send this payload to CapitalePay for processing
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
        $response = $this->to_array($responses);
		
        $responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
        $transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
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
			print($responses);
            $order->add_order_note(__('Capital ePay refund error. Response data: ', 'woocommerce-gateway-inspire') . '<br>Reason:' . $reason . '<br>Refund Transaction ID: ' . $transactionId . '<br>' . $result);
            return false;
        }
    }
    // Build the administration fields for this specific Gateway
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'cepay_cheque') ,
                'label' => __('Enable this payment gateway', 'cepay_cheque') ,
                'type' => 'checkbox',
                'default' => 'no'
            ) ,
            'title' => array(
                'title' => __('Title', 'cepay_cheque') ,
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'cepay_cheque') ,
                'default' => __('Check Payments', 'cepay_cheque')
            ) ,
            'description' => array(
                'title' => __('Description', 'cepay_cheque') ,
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'cepay_cheque') ,
                'default' => __('Pay securely using your credit card.', 'cepay_cheque') ,
                'css' => 'max-width:350px;'
            ) ,
            'merchantAccountID' => array(
                'title' => __('Merchant Account ID', 'cepay_cheque') ,
                'type' => 'text',
                'desc_tip' => __('This is the Merchant Code provided by CapitalePay when you signed up for an account.', 'cepay_cheque')
            ) ,
            'userName' => array(
                'title' => __('Username', 'cepay_cheque') ,
                'type' => 'text',
                'desc_tip' => __('This is the Username provided by CapitalePay when you signed up for an account.', 'cepay_cheque')
            ) ,
            'password' => array(
                'title' => __('Password', 'cepay_cheque') ,
                'type' => 'password',
                'desc_tip' => __('This is the Password provided by CapitalePay when you signed up for an account.', 'cepay_cheque')
            ) ,
            'holderType' => array(
                'title' => __('Holder Type', 'cepay_cheque') ,
                'type' => 'select',
                'desc_tip' => __('Type of a payment card or bank account holder.', 'cepay_cheque') ,
                'options' => array(
                    'P' => 'Personal',
                    'O' => 'Level II and Level III',
                )
            ) ,
            'TransactionIndustryType' => array(
                'title' => __('Transaction Industry Type', 'cepay_cheque') ,
                'type' => 'select',
                'desc_tip' => __('Indicates the industry related to this merchant and specific transaction.', 'cepay_cheque') ,
                'options' => array(
                    'DB' => 'Direct Marketing',
                    'EC' => 'Ecommerce',
                    'RE' => 'Retail',
                    'RS' => 'Restaurant',
                    'LD' => 'Lodging',
                    'PT' => 'Petroleum',
                    'CCD' => 'Corporate Credit or Debit is used when charging a corporate bank account.',
                    'C21' => 'Check 21',
                    'PPD' => 'Prearranged Payment and Deposit is used when charging an individual consumer bank account.',
                    'POP' => 'Point of Purchase entry.',
                    'TEL' => 'Telephone initatied transactions, used when the ACH transaction is initiated over the phone with the account holder.',
                    'WEB' => 'Internet initiated entry.'
                )
            ) ,
            'environment' => array(
                'title' => __('Test Mode', 'cepay_cheque') ,
                'label' => __('Enable Test Mode', 'cepay_cheque') ,
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'cepay_cheque') ,
                'default' => 'no'
            )
        );
    }
    // Submit payment and handle response
    public function process_payment($order_id)
    {
        global $woocommerce;
        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order($order_id);
        $order = wc_get_order($order_id);
        $orderTotal      = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->order_total : $customer_order->get_total();
        $orderTotal = bcmul($orderTotal, 100);
        $orderTotal = $orderTotal;
        $PhoneNum        = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->billing_phone : $customer_order->get_billing_phone();
        $customerAccode = preg_replace('/[^0-9]/', '', $PhoneNum);
        $cepay_account_number = $_POST['cepay-account-number'];
        $cepay_routing_number = $_POST['cepay-routing-number'];
        // Are we testing right now or is it a real transaction
        $environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
        // Decide which URL to post to
        $environment_url = ("FALSE" == $environment) ? 'https://secure.zift.io/gates/xurl?' : 'https://sandbox-secure.zift.io/gates/xurl?';
        $orderItems = '';
        foreach ($order->get_items() as $item_id => $item_data)
        {
            $product = $item_data->get_product();
            //$orderItems .= '(code=' . $item_data->get_product_id() . ';quantity=' . $item_data->get_quantity() . ';description=' . substr($this->clean_string($product->get_name()) , 0, 33) . ';totalAmount=' . bcmul($item_data->get_total() , 100) . ')';
        }
        if (class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($customer_order->id))
        {
            $data = array(
                'requestType' => 'sale',
                'userName' => $this->userName,
                'password' => $this->password,
                'accountId' => $this->merchantAccountID,
                'accountType' => 'C',
                'accountNumber' => str_replace(array(
                    ' ',
                    '-'
                ) , '', preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['cepay-account-number'])) ,
                'accountAccessory' => $_POST['cepay-routing-number'],
                'transactionCode' => '',
                'holderName' => $customer_order->billing_first_name . $customer_order->billing_last_name,
            );
            $options = array(
                'http' => array(
                    'header' => "Content-type: application/x-www-form-urlencoded",
                    'method' => 'POST',
                    'content' => http_build_query($data) ,
                ) ,
            );
            $context = stream_context_create($options);
            $result = file_get_contents($environment_url, false, $context);
            $responses = explode('&', $result);
            $matches = array(); // This will be the array of matched strings.
            $response = array();
            foreach ($responses as $match)
            { // Check through each match.
                $results = explode('=', $match); // Separate the string into key and value by '=' as delimiter.
                $response[$results[0]] = trim($results[1], '"'); // Load key and value into array.
                
            }
            $responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
            $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
            $transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
            $token = (!empty($response['token']) ? $response['token'] : '');
            $subscription = wcs_get_subscriptions_for_order(wcs_get_objects_property($customer_order, 'id') , array(
                'order_type' => 'parent'
            ));
            foreach ($subscription as $subscription)
            {
                update_post_meta($subscription->get_id() , 'cepay_customer_token_id', $token);
                update_post_meta($subscription->get_id() , 'cepay_routing_number', $cepay_routing_number);
                $subscription = wcs_get_subscription($subscription->get_id());
                $subscription_trial_length = wcs_estimate_periods_between($subscription->get_time('start') , $subscription->get_time('trial_end') , $subscription->get_trial_period());
                if ($subscription_trial_length > 0 && $orderTotal == 0)
                {
                    $customer_order->update_status('completed');
                    // Empty the cart (Very important step)
                    $woocommerce
                        ->cart
                        ->empty_cart();
                    // Redirect to thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($customer_order)
                    );
                }
            }
        }
        // This is where the fun stuff begins
        $payload = array(
            //CapitalePay Credentials and API Info
            'requestType' => 'sale',
            'userName' => $this->userName,
            'password' => $this->password,
            'accountId' => $this->merchantAccountID,
            'transactionIndustryType' => $this->TransactionIndustryType,
            'transactionCode' => '',
            'amount' => $orderTotal,
            'holderType' => $this->holderType,
            'holderName' => $customer_order->billing_first_name . $customer_order->billing_last_name,
            'street' => $customer_order->shipping_address_1,
            'city' => $customer_order->billing_city,
            'state' => $customer_order->billing_state,
            'zipCode' => $customer_order->billing_postcode,
            'accountType' => 'C',
            'accountNumber' => str_replace(array(
                ' ',
                '-'
            ) , '', preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['cepay-account-number'])) ,
            'accountAccessory' => $_POST['cepay-routing-number'],
            'customerAccountCode' => $PhoneNum
        );
        // Send this payload to CapitalePay for processing
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
        $response = $this->to_array($responses); // This will be the array of matched strings.
        $responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
        $transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
        $token = (!empty($response['token']) ? $response['token'] : '');
        if (is_wp_error($response)) throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'cepay_cheque'));
        if (empty($response['transactionId'])) throw new Exception(__('CapitalePay\'s Response was empty.' . print_r($response) , 'cepay_cheque'));
        // Test the code to know if the transaction went through or not.
        // 1 or 4 means the transaction was a success
        if (($responseCode == 'A01'))
        {
            if (class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($customer_order->id))
            {
                WC_Subscriptions_Manager::activate_subscriptions_for_order($customer_order);
                WC_Subscriptions_Manager::process_subscription_payments_on_order($customer_order);
                $subscriptions = wcs_get_subscriptions_for_order(wcs_get_objects_property($customer_order, 'id') , array(
                    'order_type' => 'parent'
                ));
                foreach ($subscriptions as $subscription)
                {
                    update_post_meta($subscription->get_id() , 'cepay_customer_token_id', $token);
                    update_post_meta($subscription->get_id() , 'cepay_routing_number', $cepay_routing_number);
                }
            }
            // Payment has been successful
            $customer_order->add_order_note(__('Capital ePay scheduled subscription payment completed. Transaction ID: ', 'cepay_cheque') . $transactionId);
            // Mark order as Paid
            $customer_order->payment_complete($transactionId);
            $customer_order->update_status('completed');
            // Empty the cart (Very important step)
            $woocommerce
                ->cart
                ->empty_cart();
            // Redirect to thank you page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($customer_order)
            );
        }
        else
        {
            // Transaction was not succesful
            // Add notice to the cart
            if ($responseCode == 'D05')
            {
                wc_add_notice('Error : Invalid card number (Invalid Account Number).', 'error');
            }
            elseif ($responseCode == 'D10')
            {
                wc_add_notice('Error : Card reported lost/stolen (Lost/Stolen Card).', 'error');
            }
            elseif ($responseCode == 'D30')
            {
                wc_add_notice('Error : Call for Authorization (Referral)*.', 'error');
            }
            elseif ($responseCode == 'D04')
            {
                wc_add_notice('Error : Hold - Pick up card (Pick Up Card).', 'error');
            }
            elseif ($responseCode == 'D08')
            {
                wc_add_notice('Error : CSC is invalid (Decline CSC/CID Fail).', 'error');
            }
            elseif ($responseCode == 'D03')
            {
                wc_add_notice('Error : Insufficient Funds.', 'error');
            }
            elseif ($responseCode == 'E02')
            {
                wc_add_notice('Error : Processing Network Unavailable.', 'error');
            }
            elseif ($responseCode == 'E09')
            {
                wc_add_notice('Error : Processing Network Error.', 'error');
            }
            elseif ($responseCode == 'A05')
            {
                wc_add_notice('Error : Partially Approved**.', 'error');
            }
            elseif ($responseCode == 'D24')
            {
                wc_add_notice('Error : Chargeback received***.', 'error');
            }
            elseif ($responseCode == 'D01')
            {
                wc_add_notice('Error : Denied by customer\'s bank (Do Not Honor)', 'error');
            }
            elseif ($responseCode == 'D08')
            {
                wc_add_notice('Error : CSC is invalid (Decline CSC/CID Fail)', 'error');
            }
            else
            {
                $customer_order->add_order_note(__('Error', 'spyr-capitalepay') . $response[0] . '<br>2:' . $response[1] . '<br>3d:' . $response);
                wc_add_notice($responseCode, 'error');
            }
            // Add note to the order for your reference
            /* $customer_order->add_order_note('Error: '); */
        }
    }
    /**
     * scheduled_subscription_payment function.
     *
     * @param float $amount_to_charge  The amount to charge.
     * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
     * @access public
     * @return void
     */
    function scheduled_subscription_payment($amount_to_charge, $order)
    {
        $result = $this->process_subscription_payment($order, $amount_to_charge);
        if (is_wp_error($result))
        {
            $order->update_status('failed', sprintf(__('Simplify Transaction Failed (%s)', 'woocommerce') , $result->get_error_message()));
        }
        else
        {
            WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
        }
    }
    /**
     * process_subscription_payment function.
     *
     * @access public
     * @param WC_Order $order
     * @param int $amount (default: 0)
     * @return string|WP_Error
     */
    public function process_subscription_payment($customer_order, $amount = 0)
    {
        global $woocommerce;
        $order = wc_get_order($customer_order->id);
        // Get this Order's information so that we know
        // who to charge and how much
        $orderTotal = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->order_total : $customer_order->get_total();
        $orderTotal = bcmul($orderTotal, 100);
        $orderTotal = $orderTotal;
        $PhoneNum        = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->billing_phone : $customer_order->get_billing_phone();
        $customerAccode = preg_replace('/[^0-9]/', '', $PhoneNum);
        $cc_token = str_replace("token=", "", get_post_meta($customer_order->id, 'cepay_customer_token_id', true));
        $cepay_routing_number = get_post_meta($customer_order->id, 'cepay_routing_number', true);
        // Are we testing right now or is it a real transaction
        $environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
        // Decide which URL to post to
        $environment_url = ("FALSE" == $environment) ? 'https://secure.zift.io/gates/xurl?' : 'https://sandbox-secure.zift.io/gates/xurl?';
        $orderItems = '';
        foreach ($order->get_items() as $item_id => $item_data)
        {
            $product = $item_data->get_product();
            $orderItems .= '(code=' . $item_data->get_product_id() . ';quantity=' . $item_data->get_quantity() . ';description=' . substr($product->get_name() , 0, 33) . ';totalAmount=' . bcmul($item_data->get_total() , 100) . ')';
        }
        // This is where the fun stuff begins
        $payload = array(
            //CapitalePay Credentials and API Info
            'requestType' => 'sale',
            'userName' => $this->userName,
            'password' => $this->password,
            'accountId' => $this->merchantAccountID,
            'transactionIndustryType' => $this->TransactionIndustryType,
            'amount' => $orderTotal,
            'holderType' => $this->holderType,
            'holderName' => $customer_order->billing_first_name . $customer_order->billing_last_name,
            'street' => $customer_order->shipping_address_1,
            'city' => $customer_order->billing_city,
            'state' => $customer_order->billing_state,
            'zipCode' => $customer_order->billing_postcode,
            'accountType' => 'C',
            'items' => $orderItems,
            'customerAccountCode' => $PhoneNum,
            'accountAccessory' => $cepay_routing_number,
            'token' => $cc_token
        );
        // Send this payload to CapitalePay for processing
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
        $response = $this->to_array($responses); // This will be the array of matched strings.
        $responseCode = (!empty($response["responseCode"]) ? $response["responseCode"] : '');
        $responseMessage = (!empty($response["responseMessage"]) ? urldecode($response["responseMessage"]) : 'error');
        $transactionId = (!empty($response['transactionId']) ? $response['transactionId'] : 'error');
        $token = (!empty($response['token']) ? $response['token'] : '');
        if (is_wp_error($response))
        {
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'cepay_cheque'));
        }
        if (empty($response))
        {
            throw new Exception(__('CapitalePay\'s Response was empty.', 'cepay_cheque'));
        }
        // Test the code to know if the transaction went through or not.
        // 1 or 4 means the transaction was a success
        if (($responseCode == 'A01'))
        {
            // Payment has been successful
            $customer_order->add_order_note(__('Capital ePay scheduled subscription payment completed. Transaction ID: ', 'cepay_cheque') . $transactionId);
            // Mark order as Paid
            $customer_order->payment_complete($transactionId);
            $customer_order->update_status('completed');
        }
        else
        {
            // Transaction was not succesful
            // Add notice to the cart
            if ($responseCode == 'D05')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Payment declined.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D10')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. The cardholder’s bank has declined the transaction and requested the cardholder’s credit card to be retained because the card was reported lost or stolen.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D30')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. To proceed with the transaction, call for authorization is needed to confirm the validity of the card.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D04')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. The cardholder’s bank has declined the transaction and requested cardholder’s credit card to be retained.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D08')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. CSC value is invalid.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D03')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Specified credit card does not have sufficient funds.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'E02')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Processing network is temporarily unavailable.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'E09')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Error ocurred during the connection process after the transaction was submitted.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'A05')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription: Transaction has been partially approved as a result of split payment.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D24')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription: Chargeback has been received.', 'cepay_cheque'));
            }
            elseif ($responseCode == 'D01')
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed. Transaction has been denied by cardholder\'s bank.', 'cepay_cheque'));
            }
            else
            {
                $customer_order->add_order_note(__('Capital ePay scheduled subscription payment failed.' . $response[1], 'cepay_cheque'));
            }
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($customer_order);
        }
    }
    // Validate fields
    public function validate_fields()
    {
        return true;
    }
    /**
     * Include the payment meta data required to process automatic recurring payments so that store managers can
     * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
     *
     * @since 2.5
     * @param array $payment_meta associative array of meta data required for automatic payments
     * @param WC_Subscription $subscription An instance of a subscription object
     * @return array
     */
    public function add_subscription_payment_meta($payment_details, $subscription)
    {
        $payment_details[$this->id] = array(
            'post_meta' => array(
                'cepay_customer_token_id' => array(
                    'value' => str_replace("token=", "", get_post_meta($subscription->get_id(), 'cepay_customer_token_id', true)) ,
                    'label' => 'Check Token',
                ) ,
                'cepay_routing_number' => array(
                    'value' => get_post_meta($subscription->get_id(), 'cepay_routing_number', true) ,
                    'label' => 'Routing Number',
                )
            )
        );
        return $payment_details;
    }
    public function clean_string($string)
    {
        $string = str_replace(' ', '-', $string);
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        return preg_replace('/-+/', '-', $string);
    }
    public function to_array($responses)
    {
        $response = array();
        foreach ($responses as $match)
        { // Check through each match.
            $results = explode('=', $match); // Separate the string into key and value by '=' as delimiter.
            $response[$results[0]] = trim($results[1], '"'); // Load key and value into array.
            
        }
        return $response;
    }
}

