<?php
/* CapitalePay Payment Gateway Class */

class SPYR_CapitalePay extends WC_Payment_Gateway_CC
{
    // Setup our Gateway's id, description and other values
    function __construct()
    {
        // The global ID for this Payment method
        $this->id                 = "spyr_capitalepay";
        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title       = __("CapitalePay", 'spyr-capitalepay');
        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("CapitalePay Payment Gateway Plug-in for WooCommerce", 'spyr-capitalepay');
        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title              = __("CapitalePay", 'spyr-capitalepay');
        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon               = null;
        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields         = true;
        // Supports the default credit card form
        $this->supports           = array(					'add_payment_method',
            'default_credit_card_form',
            'products',
            'multiple_subscriptions',
			'subscription_payment_method_change', // Subscriptions 1.n compatibility 
			'subscription_payment_method_change_customer',  
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
        
		$this->form_fields = init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }
        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array(
                $this,
                'scheduled_subscription_payment'
            ), 10, 2);
            add_filter('woocommerce_subscription_payment_meta', array(
                $this,
                'add_subscription_payment_meta'
            ), 10, 2);
        }
        // Add hooks
        add_action('admin_notices', array(
            $this,
            'cepay_commerce_ssl_check'
        ));
        
		// Capture charge for woocommerce        
		add_action('woocommerce_order_item_add_action_buttons', array(
			$this,
			'wc_order_item_add_action_buttons_callback'
		));		
		
		// Capture charge for woocommerce        
		add_action('woocommerce_credit_card_form_start', array(
			$this,
			'woocommerce_credit_card_form_start_ui'
		));
		// Capture charge for woocommerce        
		add_action('woocommerce_credit_card_form_end', array(
			$this,
			'woocommerce_credit_card_form_last_ui'
		));		     
        
    } // End __construct()
	
		
	  
	  
	public function save_card_details($token_cepay, $card_number, $card_expiry){
		
		$token = new WC_Payment_Token_CC(); 
		$token->set_token( $token_cepay );
		$token->set_gateway_id( $this->id );
		$token->set_card_type( strtolower( $this->detectCardType($card_number) ) );
		$token->set_last4( substr($card_number ,-4) );
		$token->set_expiry_month(substr($card_expiry, 0 ,-2) );
		$token->set_expiry_year( '20' . substr($card_expiry ,-2) );

		if ( is_user_logged_in() ) {
			$token->set_user_id( get_current_user_id() );
		}

		$token->save();	
	}		
	  

	 public function woocommerce_credit_card_form_start_ui() { 
		$customer_token = null; 
		$html = '';
		$items = array();
		
			if ( is_user_logged_in() && !is_account_page()) { 
			$tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );
			if($tokens){
				$html .= '<div class="cepay-saved-card-container">';		  
				$html .= '<div class="form-row-first">';		  
				$html .= '<div class="cepay-manage-payment-methods-title">Saved Cards</div>';		  
				$html .= '</div>';		  
				$html .= '<div class="form-row-last">';	
				$html .= '<a class="cepay-manage-payment-methods-btn" href="'.site_url('my-account/payment-methods/').'">Manage Payment Methods</a>';		  
				$html .= '</div>';		  


				foreach ( $tokens as $token ) { 
					$token = $token->get_data();
					$html .= '<div class="form-row-wide cepay-saved-card">';
					$html .= '<input class="cepay-card-radio" type="radio" name="spyr_capitalepay_saved_card" value="'.$token['token'].'">';
					$html .= '<img src="' . plugins_url('woocommerce/assets/images/icons/credit-cards/'.$token['card_type'].'.svg') . '" >';
					$html .= '<span class="cepay-saved-card-text"> •••'.$token['last4'].' (expires '.$token['expiry_month'].'/'.$token['expiry_year'].')</span>';		  
					$html .= '</div>';
					$items[] = $html;
				} 
				   

				$html .= '</div>';		  
				echo $html;
				}
			}
		  
	  } 
	  
	  
	 public function woocommerce_credit_card_form_last_ui() { 
		$html = '';
		
			if ( is_user_logged_in() && !is_account_page()) { 

				$html .= '<p class="form-row-wide" onclick="save_card()">';
				$html .= '<input type="checkbox" name="cepay_save_card" id="cepay-saved-option"/>';
				$html .= '<label class="cepay_save_card_label noselect">Save card for future use</label>';
				$html .= '</p>';
				echo $html;

			}
		  
	  } 	  
	  

		// define the woocommerce_order_item_add_action_buttons callback    
		function wc_order_item_add_action_buttons_callback($order)
		{
			$cepay_order_authTransec_meta = get_post_meta(esc_attr($order->get_id()) , 'cepay_order_authTransec', true);
			$cepay_order_capture_status_meta = get_post_meta(esc_attr($order->get_id()) , 'cepay_order_capture_status', true);
			
			if($cepay_order_authTransec_meta==1){
				echo '<button id="cepay_capture_charge_btn" type="button" class="button add-capture-charge-item" '.($cepay_order_capture_status_meta==1?'disabled':'').' data-order_id="' . esc_attr($order->get_id()) . '">Capture amount</button>';
			}
		}   
		
		
		function cepay_capture_charge(){
		$obj = new SPYR_CapitalePay;
			
	    // the data from the ajax call
	    $order_id = intval($_POST['order_id']);
		//getting order Object
		$order = wc_get_order($order_id); 
		$transaction_id = $order->get_transaction_id();
		

		 
        // Send this payload to CapitalePay for processing
		zift_connect_cepay_capture_charge($this, $order, $obj, $transaction_id);

	
			  
		}	
		
	
	/**
	 * Check if SSL is enabled and notify the user if not.
	 *
	 * This function verifies if the WooCommerce 'force SSL' option is enabled,
	 * and if the payment gateway is also enabled. If both conditions are met, it
	 * displays a warning message to the user, informing them that their checkout
	 * process is not secure and suggesting to enable SSL with a valid certificate.
	 */
		function cepay_commerce_ssl_check() {
			if (get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
				$admin_url = admin_url('admin.php?page=wc-settings&tab=checkout');
				echo '<div class="error"><p>' . 
					 sprintf(
						 __('Capital ePay payment gateway is enabled and the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woocommerce-gateway-inspire'),
						 $admin_url
					 ) .
					 '</p></div>';
			}
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


        if ($amount == 0)

        {

            $order->add_order_note(__('Capital ePay: The amount is already refunded.', 'woocommerce-gateway-inspire'));

            return true;

        }

        // This is where the fun stuff begins
		return zift_connect_process_refund($this, $transaction_id, $amount, $order);		
			

    }



    /**
     * Add payment method
     *
     * @param  string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */	
	public function add_payment_method()
    {
        return external_add_payment_method($this);
    }

    /**
     * Process payment
     *
     * @param  int $order_id
     * @param  string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */	
	public function process_payment($order_id)
    {
        return external_process_payment($order_id, $this);
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
        $parent_order_id = wcs_get_subscriptions_for_renewal_order( $order->get_id() );
		
        $result = $this->process_subscription_payment($order,$parent_order_id,$amount_to_charge);
        if (is_wp_error($result)) {
            $order->update_status('failed', sprintf(__('Simplify Transaction Failed (%s)', 'woocommerce'), $result->get_error_message()));
        } else {
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
		public function process_subscription_payment($customer_order, $parent_order, $amount = 0) {
			global $woocommerce;

			if ( wcs_order_contains_renewal( $customer_order->id ) ) {
				$parent_id = WC_Subscriptions_Renewal_Order::get_parent_order_id( $customer_order->id );
			}


			// Get this Order's information so that we know
			// who to charge and how much
			$orderTotal      = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->order_total : $customer_order->get_total();
			$orderTotal      = bcmul($orderTotal, 100);
			$orderTotal      = $orderTotal;
			$PhoneNum        = version_compare(WC_VERSION, '3.0.0', '<') ? $customer_order->billing_phone : $customer_order->get_billing_phone();
			$customerAccode  = preg_replace('/[^0-9]/', '', $PhoneNum);
			$cc_token        = str_replace("transactionId=", "", get_post_meta($parent_id, 'cepay_customer_token_id', true));
			$cc_expiry       = get_post_meta($parent_id, 'cepay_cc_expiry', true);
			// Are we testing right now or is it a real transaction

			// This is where the fun stuff begins
			return zift_connect_process_subscription_payment($this, $customer_order, $parent_id, $orderTotal, $PhoneNum, $cc_expiry, $cc_token);
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
                    'value' => str_replace("transactionId=", "", get_post_meta($subscription->get_id(), 'cepay_customer_token_id', true)),
                    'label' => 'Credit Card Token'
                ),
                'cepay_cc_expiry' => array(
                    'value' => get_post_meta($subscription->get_id(), 'cepay_cc_expiry', true),
                    'label' => 'Credit Card Expiry'
                )
            )
        );
        return $payment_details;
    }
    /**
     * Remove spaces
     *
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */	
    public function clean_string($string)
    {
        $string = str_replace(' ', '-', $string);
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        return preg_replace('/-+/', '-', $string);
    }
    /**
     * Credit Card Date Format
     *
     * @param  string $expiry
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */	
	public function clean_date($expiry)
	{
		$expiry = preg_replace('/\s+/', '', $expiry);
		$cc_expiry = DateTime::createFromFormat("m/Y", $expiry);

		if ($cc_expiry instanceof DateTime) {
			$cc_expiry = str_replace(array('/', ' '), '', preg_replace('/[^A-Za-z0-9\-]/', '', date_format($cc_expiry, "m/y")));
		} else {
			// Handle the case where the date string could not be parsed
			// You can either throw an exception, set a default value, or log an error
			$cc_expiry = null; // Example: set the value to null if the date is invalid
		}

    return $cc_expiry;
	}

    /**
     * Convert Zift Response to array
     *
     * @param  string $responses
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */		
    public function to_array($responses)
    {
        $response = array();
        foreach ($responses as $match) { // Check through each match.
            $results               = explode('=', $match); // Separate the string into key and value by '=' as delimiter.
            $response[$results[0]] = trim($results[1], '"'); // Load key and value into array.
            
        }
        return $response;
    }
    /**
     * Detect Card Type By Number
     *
     * @param  string $responses
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */		
	public function detectCardType($num)
	{
		if($num === null){
			// Handle null case, for example return false or throw an exception
			return false;
		}

		$re = array(
			"visa"       => "/^4[0-9]{12}(?:[0-9]{3})?$/",
			"mastercard" => "/^5[1-5][0-9]{14}$/",
			"amex"       => "/^3[47][0-9]{13}$/",
			"discover"   => "/^6(?:011|5[0-9]{2})[0-9]{12}$/",
			"diners"     => "/^3(?:0[0-5]|[68][0-9])[0-9]{4,}$/",
			"jcb"        => "/^(?:2131|1800|35[0-9]{3})[0-9]{3,}$/",
			"maestro"    => "/^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|0604|6390)\d+$/"
		);

		foreach ($re as $cardType => $pattern) {
			if (preg_match($pattern, $num)) {
				return $cardType;
			}
		}

		return false;
	}


}