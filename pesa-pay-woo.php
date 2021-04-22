<?php
/**
 * Plugin Name: Mpesa Payment Gateway
 * Plugin URI: https://vinny.com
 * Author: Vinny
 * Author URI: https://vinny.com
 * Description: Mobile mpesa payment woocommerce.
 * Version: 1.0.0
 * License: GPL3
 * License URL: 
 * text-domain: pesa-pay-woo
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;



add_action( 'plugins_loaded', 'pesa_payment_init', 11 );

function pesa_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/pesa-wc-payment-gateway-class.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/pesa-order-statuses.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/pesa-checkout-fields.php';
	}
}

add_filter( 'woocommerce_payment_gateways', 'add_to_woo_pesa_payment_gateway');

function add_to_woo_pesa_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Pesa';
    return $gateways;
}


add_filter( 'woocommerce_currencies', 'add_pesa_ksh_currencies' );

function add_pesa_ksh_currencies( $currencies ) {
	$currencies['KSh'] = __( 'Kenya Shillings', 'pesa-pay-woo' );
	return $currencies;
}

add_filter( 'woocommerce_currency_symbol', 'add_pesa_ksh_currencies_symbol', 10, 2 );
function add_pesa_ksh_currencies_symbol( $currency_symbol, $currency ) {
	switch ( $currency ) {
		case 'KSh': 
			$currency_symbol = 'KSh'; 
		break;
	}
	return $currency_symbol;
}