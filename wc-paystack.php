<?php

/**
 * Plugin Name: WooCommerce Paystack Payment Gateway
 * Description: Paystack payment gateway for WooCommerce
 * Author: Sadiq Odunsi
 * Version: 1.3.1
 * WC requires at least: 3.0.0
 * WC tested up to: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'SDQ_PAYMENT_M_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDQ_PAYMENT_M_URL', plugin_dir_url( __FILE__ ) );
define( 'SDQ_PAYSTACK_VERSION', '1.3.1' );

/**
 * Initialize Our Paystack WooCommerce payment gateway.
 */
function sdq_wc_paystack_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once SDQ_PAYMENT_M_DIR . 'includes/class-paystack.php';

}
add_action( 'plugins_loaded', 'sdq_wc_paystack_init', 99 );

/**
 * Add Paystack Gateway to WooCommerce.
 *
 * @param array $methods WooCommerce payment gateways methods.
 *
 * @return array
 */
function sdq_wc_add_paystack_gateway( $methods ) {
    
	$methods[] = 'Paystack_Payment_Method';
	return $methods;
	
}
add_filter('woocommerce_payment_gateways', 'sdq_wc_add_paystack_gateway');

/**
 * Display the test mode notice.
 */
function sdq_paystack_testmode_notice() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$paystack_settings = get_option( 'woocommerce_v_paystack_settings' );
	$test_mode         = isset( $paystack_settings['testmode'] ) ? $paystack_settings['testmode'] : '';

	if ( 'yes' === $test_mode ) {
		echo '<div class="error"><p>' . sprintf( __( 'Paystack test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=v_paystack' ) ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'sdq_paystack_testmode_notice' );

/**
 * Enqueue Paystack scripts to enable ajax payment
 */
function sdq_enqueue_paystack_frontend_scripts() {
	
	$settings = get_option( 'woocommerce_v_paystack_settings' );
	$enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : '';

	if ( $enabled === 'no' ) {
		return;
	}

    $paystack_params['ajax_url']    = esc_url( admin_url( 'admin-ajax.php' ) );
    $paystack_params['security']    = wp_create_nonce('paystack-security');
        
    $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
    
    wp_enqueue_script( 'paystack', 'https://js.paystack.co/v1/inline.js', [], SDQ_PAYSTACK_VERSION, true );

    wp_enqueue_script( 'paystack-js', SDQ_PAYMENT_M_URL . 'assets/js/paystack' .$suffix. '.js', ['jquery'], SDQ_PAYSTACK_VERSION, true );
        
    wp_localize_script('paystack-js', 'paystack_params', $paystack_params);
        
}
add_action( 'wp_enqueue_scripts', 'sdq_enqueue_paystack_frontend_scripts' );