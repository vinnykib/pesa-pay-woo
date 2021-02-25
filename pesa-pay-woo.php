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
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;