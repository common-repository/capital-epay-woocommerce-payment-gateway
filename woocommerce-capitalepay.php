<?php
/**
 * Plugin Name: Capital ePay WooCommerce Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/capital-epay-woocommerce-payment-gateway/
 * Description: Extends WooCommerce by Adding the Capital ePay Gateway.
 * Version: 3.3.6
 * Author: Capital District Digital
 * Author URI: https://capitaldistrictdigital.com/
 * WC requires at least: 3.0.9
 * WC tested up to: 6.6.2
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Update URI: https://wordpress.org/plugins/capital-epay-woocommerce-payment-gateway/
 */

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'spyr_capitalepay_init', 0 );
function spyr_capitalepay_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	require_once 'credit-card/connection.php';
	require_once 'credit-card/capitalepay_payment_gateway_settings.php';
	require_once 'credit-card/add_payment_method.php';
	require_once 'credit-card/process_payment.php';
	
	include_once( 'credit-card/class-cepay-payment-gateway-cc.php' );
	include_once( 'e-check/class-cepay-payment-gateway-echeck.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'spyr_add_capitalepay_gateway' );
	
	function spyr_add_capitalepay_gateway( $methods ) {
		$methods[] = 'SPYR_CapitalePay';
		$methods[] = 'CEPAY_WC_Gateway_Cheque';
		return $methods;
	}
}add_action('wp_ajax_cepay_capture_charge', [ 'SPYR_CapitalePay','cepay_capture_charge']);		
add_action( 'admin_enqueue_scripts', 'my_plugin_scripts' );function my_plugin_scripts(){ wp_enqueue_script( 'cepay_capture_script', plugins_url('assets/js/capture.js' , __FILE__ ));	wp_localize_script( 'cepay_capture_script', 'ajax_object', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );}
add_action('wp_enqueue_scripts','register_cepay_style');

function register_cepay_style(){
    wp_enqueue_style( 'cepay-style', plugins_url( 'assets/css/style.css' , __FILE__ ) );
	
	wp_enqueue_script( 'cepay-normal-script', plugins_url('assets/js/cepay_normal_script.js' , __FILE__ ),'','',true);
	}
	
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spyr_capitalepay_action_links' );
function spyr_capitalepay_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'spyr-capitalepay' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}