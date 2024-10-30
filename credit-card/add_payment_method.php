<?php

	/**
	 * Add a new payment method to the user's account.
	 * 
	 * This function sends the user's card details to CapitalePay for tokenization
	 * and stores the resulting token in the user's account. Upon success, it
	 * redirects the user to the payment methods page.
	 * 
	 * @return array An associative array containing the result ('success' or 'failure') and the URL to redirect the user to.
	 */

	 function external_add_payment_method($gateway_instance) {
		global $woocommerce;
		$current_user = wp_get_current_user();
		
	

		return zift_connect_add_payment_method($gateway_instance);
		


	}


?>