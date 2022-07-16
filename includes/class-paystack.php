<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Paystack_Payment_Method extends WC_Payment_Gateway {

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Paystack test public key.
	 *
	 * @var string
	 */
	public $test_public_key;

	/**
	 * Paystack test secret key.
	 *
	 * @var string
	 */
	public $test_secret_key;

	/**
	 * Paystack live public key.
	 *
	 * @var string
	 */
	public $live_public_key;

	/**
	 * Paystack live secret key.
	 *
	 * @var string
	 */
	public $live_secret_key;

	/**
	 * API public key
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key
	 *
	 * @var string
	 */
	public $secret_key;

    /**
     * Class constructor
     */
    public function __construct() {
        
		$this->id                 = 'sdq_paystack';
		
	    $this->icon               = WC_HTTPS::force_https_url( SDQ_PAYMENT_M_URL . 'assets/images/paystack-wc.png' );
		
		$this->has_fields         = false;
		
		$this->method_title       = 'Paystack';
		
		$this->method_description = 'Paystack provide merchants with the tools and services needed to accept online payments from local and international customers using Mastercard, Visa, Verve Cards and Bank Accounts. <a href="https://paystack.com" target="_blank">Sign up</a> for a Paystack account, and <a href="https://dashboard.paystack.com/#/settings/developer" target="_blank">get your API keys</a>.';

		$this->supports = array(
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Get setting values
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->testmode    = $this->get_option( 'testmode' ) === 'yes' ? true : false;

		$this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );
        
		$this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
		
		$this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;
		
		// Hooks
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		
		// Create order via ajax
        add_action( 'wp_ajax_paystack_create_order', [ $this, 'paystack_create_order' ] );

		// Verify payment via ajax
        add_action( 'wp_ajax_verify_paystack_payment', [ $this, 'verify_paystack_payment' ] );
		
		// Webhook listener/API hook.
		add_action( 'woocommerce_api_sdq_wc_paystack_webhook', array( $this, 'process_webhooks' ) );
		
		// Scheduled subscription payment
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
		    
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			
		}

    }

	/**
	 * Check if Paystack merchant details is filled.
	 */
	public function admin_notices() {

		if ( $this->enabled == 'no' ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>Please enter your Paystack merchant details <a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=sdq_paystack' ) . '">here</a> to be able to use the Paystack WooCommerce plugin.</p></div>';
			return;
		}

	}

	/**
	 * Check if Paystack gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( 'yes' == $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options() {

		?>

		<h2><?php _e( 'Paystack' ); ?>
		<?php
		if ( function_exists( 'wc_back_link' ) ) {
			wc_back_link( __( 'Return to payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		}
		?>
		</h2>

		<h4>
			<strong><?php printf( __( 'Optional: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="%1$s" target="_blank" rel="noopener noreferrer">here</a> to the URL below<span style="color: red"><pre><code>%2$s</code></pre></span>' ), 'https://dashboard.paystack.co/#/settings/developer', WC()->api_request_url( 'sdq_wc_paystack_webhook' ) ); ?></strong>
		</h4>

		<?php

		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';

	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$form_fields = array(
			'enabled'         => array(
				'title'       => __( 'Enable/Disable' ),
				'label'       => __( 'Enable Paystack' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Paystack as a payment option on the checkout page.' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'           => array(
				'title'       => __( 'Title' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.' ),
				'default'     => __( 'Debit/Credit Cards' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.' ),
				'default'     => __( 'Make payment using your debit and credit cards' ),
				'desc_tip'    => true,
			),
			'testmode'        => array(
				'title'       => __( 'Test mode' ),
				'label'       => __( 'Enable Test Mode' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once the LIVE MODE is enabled on your Paystack account uncheck this.' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_secret_key' => array(
				'title'       => __( 'Test Secret Key' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Secret Key here' ),
				'default'     => '',
			),
			'test_public_key' => array(
				'title'       => __( 'Test Public Key' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Public Key here.' ),
				'default'     => '',
			),
			'live_secret_key' => array(
				'title'       => __( 'Live Secret Key' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Secret Key here.' ),
				'default'     => '',
			),
			'live_public_key' => array(
				'title'       => __( 'Live Public Key' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Public Key here.' ),
				'default'     => '',
			),
		);

		$this->form_fields = $form_fields;

	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}
		
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		
        wp_enqueue_script( 'paystack-admin-js', SDQ_PAYMENT_M_URL . 'assets/js/paystack-admin' .$suffix. '.js', ['jquery'], SDQ_PAYSTACK_VERSION, true );

	}
    
	/**
	 * Create an order.
	 * 
	 * @return json JSON response back to an Ajax request, indicating success or failure.
	 */    
    public function paystack_create_order() {
    
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['security'] ), 'paystack-security' ) ) {
            wp_send_json_error('An error occured. Please refresh and try again.');
        }
    
        $product_id = absint( $_POST['product_id'] );
        if ( ! $product_id ) {
            wp_send_json_error('Product ID is required.');
        }
		
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error('Specified product is invalid.');
		}

        $user    = wp_get_current_user();
        $user_id = $user->ID;

        // Payment processor data
        $settings = get_option( 'woocommerce_sdq_paystack_settings' );
		$enabled  = isset( $settings['enabled'] ) ? $settings['enabled'] : '';

		if ( $enabled === 'no' ) {
			wp_send_json_error('Paystack payment method is not enabled.');
		}
		
        $test_public_key = isset( $settings['test_public_key'] ) ? $settings['test_public_key'] : '';
        $live_public_key = isset( $settings['live_public_key'] ) ? $settings['live_public_key'] : '';
        $testmode 		 = isset( $settings['testmode'] ) ? $settings['testmode'] : '';
        $public_key      = ( $testmode === 'yes' ) ? $test_public_key : $live_public_key;
        $currency        = 'NGN'; // Accepts only naira

		// Only one product can be purchased at a time
        $quantity = 1;

        // User data
        $address = [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'company'    => $user->display_name,
            'email'      => $user->user_email
        ];

        // Get payment method
        $payment_method = WC()->payment_gateways->payment_gateways();
        $payment_method = $payment_method['sdq_paystack'];

		// Check if it is a simple subscription or product
        if ( $product->is_type( 'subscription' ) || $product->is_type( 'simple' ) ) {

            // Create simple subscription order
            $order = wc_create_order( [ 'customer_id' => $user_id ] );
            $order->add_product( $product, $quantity );
            $order->set_payment_method( $payment_method );
            $order->set_address( $address, 'billing' );
            $order->calculate_totals();
            $order_id = $order->get_id();

			if ( function_exists( 'wcs_create_subscription' ) && $product->is_type( 'subscription' ) ) {
				// Create simple subscription
				$period = WC_Subscriptions_Product::get_period( $product );
				$interval = WC_Subscriptions_Product::get_interval( $product );
				$sub = wcs_create_subscription(
					array(
						'order_id' => $order_id,
						'billing_period' => $period,
						'billing_interval' => $interval
					)
				);

				$sub->add_product( $product, $quantity );
				$sub->set_payment_method( $payment_method );
				$sub->set_address( $address, 'billing' );
				$sub->calculate_totals();
			}

            // Reference
            $txnref = $order_id . '_' . time();
            // Save reference to order for verification
            update_post_meta( $order_id, '_paystack_txn_ref', $txnref );

            $data = [
                'meta_order_id' => $order_id,
                'amount'        => $order->get_total() * 100,
                'txnref'        => $txnref,
                'product'       => $product->get_name() . ' (Qty: 1)',
                'currency'      => $currency,
                'public_key'    => $public_key,
                'email'    		=> $user->user_email
            ];

			wp_send_json_success($data);

        }

		wp_send_json_error('Only simple products can be purchased.');

	}
    
	/**
	 * Verify paystack payment.
	 * 
	 * @return json JSON response back to an Ajax request, indicating success or failure.
	 */    
    public function verify_paystack_payment() {
    
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['security'] ), 'paystack-security' ) ) {
            wp_send_json_error('Unable to verify payment. Please contact us!');
        }
    
        $reference = sanitize_text_field( $_POST['reference'] );
        if ( ! $reference ) {
            wp_send_json_error('Unable to verify payment. Please contact us!');
        }
        
		$paystack_url = 'https://api.paystack.co/transaction/verify/' . $reference;

		$headers = array(
			'Authorization' => 'Bearer ' . $this->secret_key,
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
		); 

		$request = wp_remote_get( $paystack_url, $args );
		
		if ( is_wp_error( $request ) ) {
            wp_send_json_error('An error occured. Please contact us!');
		}
		
		if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
            wp_send_json_error('An error occured. Please contact us!');
		}
		
		$response = json_decode( wp_remote_retrieve_body( $request ) );
		
		if ( 'success' !== $response->data->status ) {
		    
		    $order_details = explode( '_', $reference );
		    $order_id = (int) $order_details[0];
		    $order = wc_get_order( $order_id );
		    
			$order->update_status( 'failed', __( 'Payment was declined by Paystack.' ) );
			
			wp_send_json_error('Oops! Your payment was declined by Paystack.');
			
		}
		    
		$order_details = explode( '_', $response->data->reference );
		$order_id = (int) $order_details[0];
		$order = wc_get_order( $order_id );
		
		if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
			
			wp_send_json_success( 'Transaction successful.' );
		
		}
		
		$order_total     = $order->get_total();
		$order_currency  = $order->get_currency();
		$currency_symbol = get_woocommerce_currency_symbol($order_currency);
		$amount_paid     = $response->data->amount / 100;
		$paystack_ref    = $response->data->reference;
		$payment_currency = strtoupper( $response->data->currency );
		$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );
		
		// Save payment token for subscription renewal
		$subscription_id = $this->save_subscription_payment_token( $order_id, $response );

		// check if the amount paid is equal to the order amount.
		if ( $amount_paid < $order_total ) {

			$order->update_status( 'on-hold' );
			
			add_post_meta( $order_id, '_transaction_id', $paystack_ref, true );
			
			// Add Customer Order Note
			$note = sprintf( __( 'Thank you.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.' ), '<br />', '<br />', '<br />' );

			$order->add_order_note( $note, 1 );
			
			// Add Admin Order Note
			$admin_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Paystack Transaction Reference:</strong> %9$s' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $paystack_ref );
			
			$order->add_order_note( $admin_note );
			
			$message = 'Your payment transaction was successful, but the amount paid is not the same as the order amount. Kindly contact us for more information.';
			
			wp_send_json_error( $message );

		}

		if ( $payment_currency !== $order_currency ) {

			$order->update_status( 'on-hold' );

			update_post_meta( $order_id, '_transaction_id', $paystack_ref );
			
			// Add Customer Order Note
			$note = sprintf( __( 'Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.' ), '<br />', '<br />', '<br />' );

			$order->add_order_note( $note, 1 );

			// Add Admin Order Note
			$admin_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Paystack Transaction Reference:</strong> %9$s' ), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $paystack_ref );
					
			$order->add_order_note( $admin_note );
						
			$message = 'Your payment transaction was successful, but the payment currency is different from the order currency. Kindly contact us for more information.';
			
			wp_send_json_error( $message );

		}

		$order->payment_complete( $paystack_ref );
		$order->update_status( 'completed' );
		$order->add_order_note( sprintf( __( 'Payment via Paystack successful (Transaction Reference: %s)' ), $paystack_ref ) );
			
		wp_send_json_success( 'Payment successful.' );
		    
	}

	/**
	 * Process Webhook.
	 */
	public function process_webhooks() {

		if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) || ! array_key_exists( 'HTTP_X_PAYSTACK_SIGNATURE', $_SERVER ) ) {
			exit;
		}

		$json = file_get_contents( 'php://input' );

		// Validate event do all at once to avoid timing attack.
		if ( $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac( 'sha512', $json, $this->secret_key ) ) {
			exit;
		}

		$event = json_decode( $json );
		
		if ( 'charge.success' !== $event->event ) {
		    exit;
		}

		sleep( 10 );
		
		$order_details = explode( '_', $event->data->reference );
		$order_id = (int) $order_details[0];
		$order = wc_get_order( $order_id );
		
		$paystack_txn_ref = get_post_meta( $order_id, '_paystack_txn_ref', true );

		if ( $event->data->reference != $paystack_txn_ref ) {
			exit;
		}

		http_response_code( 200 );

		if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
			exit;
		}
		
		$order_total     = $order->get_total();
		$order_currency  = $order->get_currency(); // NGN | Symbol is ₦
		$currency_symbol = get_woocommerce_currency_symbol($order_currency);
		$amount_paid     = $event->data->amount / 100;
		$paystack_ref    = $event->data->reference;
		$payment_currency = strtoupper( $event->data->currency );
		$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );
		
		// Save payment token for subscription renewal
		$this->save_subscription_payment_token( $order_id, $event );
		
		// check if the amount paid is equal to the order amount.
		if ( $amount_paid < $order_total ) {

			$order->update_status( 'on-hold' );
			
			add_post_meta( $order_id, '_transaction_id', $paystack_ref, true );
			
			// Add Customer Order Note
			$note = sprintf( __( 'Thank you.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.' ), '<br />', '<br />', '<br />' );

			$order->add_order_note( $note, 1 );
			
			// Add Admin Order Note
			$admin_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Paystack Transaction Reference:</strong> %9$s' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $paystack_ref );
			
			$order->add_order_note( $admin_note );
			
			exit;

		}
		
		if ( $payment_currency !== $order_currency ) {

			$order->update_status( 'on-hold' );

			update_post_meta( $order_id, '_transaction_id', $paystack_ref );
			
			// Add Customer Order Note
			$note = sprintf( __( 'Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.' ), '<br />', '<br />', '<br />' );

			$order->add_order_note( $note, 1 );

			// Add Admin Order Note
			$admin_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Paystack Transaction Reference:</strong> %9$s' ), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $paystack_ref );
					
			$order->add_order_note( $admin_note );
						
			exit;

		}
		
		$order->payment_complete( $paystack_ref );
		$order->update_status( 'completed' );
		$order->add_order_note( sprintf( __( 'Payment via Paystack successful (Transaction Reference: %s)' ), $paystack_ref ) );
		
		exit;

	}

	/**
	 * Save payment token to the order for automatic renewal for further subscription payment.
	 *
	 * @param $order_id
	 * @param $response
	 */
	public function save_subscription_payment_token( $order_id, $response ) {

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

			$subscriptions = wcs_get_subscriptions_for_order( $order_id );

		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

		} else {

			$subscriptions = array();

		}

		if( $subscriptions ){

			foreach ( $subscriptions as $subscription ) {

				$subscription_id = $subscription->get_id();

				if ( $response->data->authorization->reusable && 'card' == $response->data->authorization->channel ) {

					$auth_code = $response->data->authorization->authorization_code;

					update_post_meta( $subscription_id, '_paystack_token', $auth_code );

				}

			}

		}

	}

	/**
	 * Check if an order contains a subscription.
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return bool
	 */
	public function order_contains_subscription( $order_id ) {

		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) );

	}

	/**
	 * Process a refund request from the Order details screen.
	 *
	 * @param int    $order_id WC Order ID.
	 * @param null   $amount   WC Order Amount.
	 * @param string $reason   Refund Reason
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		if ( ! ( $this->public_key && $this->secret_key ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}
		
		$order_currency = $order->get_currency();
		$transaction_id = $order->get_transaction_id();

		$verify_url = 'https://api.paystack.co/transaction/verify/' . $transaction_id;

		$headers = array(
			'Authorization' => 'Bearer ' . $this->secret_key,
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
		);

		$request = wp_remote_get( $verify_url, $args );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

			$paystack_response = json_decode( wp_remote_retrieve_body( $request ) );

			if ( 'success' == $paystack_response->data->status ) {

				$merchant_note = sprintf( __( 'Refund for Order ID: #%1$s on %2$s' ), $order_id, get_site_url() );

				$body = array(
					'transaction'   => $transaction_id,
					'amount'        => $amount * 100,
					'currency'      => $order_currency,
					'customer_note' => $reason,
					'merchant_note' => $merchant_note,
				);

				$args['body'] = $body;
				$refund_url   = 'https://api.paystack.co/refund';

				$refund_request = wp_remote_post( $refund_url, $args );

				if ( ! is_wp_error( $refund_request ) && 200 === wp_remote_retrieve_response_code( $refund_request ) ) {

					$refund_response = json_decode( wp_remote_retrieve_body( $refund_request ) );

					if ( $refund_response->status ) {
						$amount         = wc_price( $amount, array( 'currency' => $order_currency ) );
						$refund_id      = $refund_response->data->id;
						$refund_message = sprintf( __( 'Refunded %1$s. Refund ID: %2$s. Reason: %3$s' ), $amount, $refund_id, $reason );
						$order->add_order_note( $refund_message );

						return true;
					}

				} else {

					$refund_response = json_decode( wp_remote_retrieve_body( $refund_request ) );

					if ( isset( $refund_response->message ) ) {
						return new WP_Error( 'error', $refund_response->message );
					} else {
						return new WP_Error( 'error', __( 'Can&#39;t process refund at the moment. Try again later.' ) );
					}
				}

			}

		}

	}

	/**
	 * Process a subscription renewal.
	 *
	 * @param float    $amount_to_charge Subscription payment amount.
	 * @param WC_Order $renewal_order Renewal Order.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
	    
	    if( get_current_subscription_id( $renewal_order->get_user_id() ) ) {
	        
	        $renewal_order->update_status( 'failed', __( 'Renewal aborted. User has an active subscription.' ) );
	        
	    } else {
        
		    $response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		    if ( is_wp_error( $response ) ) {

			    $renewal_order->update_status( 'failed', sprintf( __( 'Paystack Transaction Failed (%s)' ), $response->get_error_message() ) );

		    }
		
	    }

	}

	/**
	 * Process a subscription renewal payment.
	 *
	 * @param WC_Order $order  Subscription renewal order.
	 * @param float    $amount Subscription payment amount.
	 *
	 * @return bool|WP_Error
	 */
	public function process_subscription_payment( $order, $amount ) {

		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

		$auth_code = get_post_meta( $order_id, '_paystack_token', true );

		if ( $auth_code ) {

			$email = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

			$order_amount = $amount * 100;

			$paystack_url = 'https://api.paystack.co/transaction/charge_authorization';

			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_key,
			);

			$metadata['custom_fields'] = $this->get_custom_fields( $order_id );

			$body = array(
				'email'              => $email,
				'amount'             => $order_amount,
				'metadata'           => $metadata,
				'authorization_code' => $auth_code,
			);

			$args = array(
				'body'    => json_encode( $body ),
				'headers' => $headers,
				'timeout' => 60,
			);

			$request = wp_remote_post( $paystack_url, $args );

			if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

				$paystack_response = json_decode( wp_remote_retrieve_body( $request ) );

				if ( 'success' === $paystack_response->data->status ) {

					$paystack_ref = $paystack_response->data->reference;

					$order->payment_complete( $paystack_ref );
					
			        $order->update_status( 'completed' );

					$message = sprintf( __( 'Payment via Paystack successful (Transaction Reference: %s)' ), $paystack_ref );

					$order->add_order_note( $message );

					return true;

				} else {

					$gateway_response = __( 'Paystack payment failed.' );

					if ( isset( $paystack_response->data->gateway_response ) && ! empty( $paystack_response->data->gateway_response ) ) {
						$gateway_response = sprintf( __( 'Paystack payment failed. Reason: %s' ), $paystack_response->data->gateway_response );
					}

					return new WP_Error( 'paystack_error', $gateway_response );

				}
			}
		}

		return new WP_Error( 'paystack_error', __( 'This subscription can&#39;t be renewed automatically. The customer will have to login to their account to renew their subscription' ) );

	}

	/**
	 * Get custom fields to pass to Paystack.
	 *
	 * @param int $order_id WC Order ID
	 *
	 * @return array
	 */
	public function get_custom_fields( $order_id ) {

		$order = wc_get_order( $order_id );

		$custom_fields = array();

		$custom_fields[] = array(
			'display_name'  => 'Plugin',
			'variable_name' => 'plugin',
			'value'         => 'sdq_paystack',
		);

		$custom_fields[] = array(
			'display_name'  => 'Order ID',
			'variable_name' => 'order_id',
			'value'         => $order_id,
		);

		$line_items = $order->get_items();

		$products = '';

		foreach ( $line_items as $item_id => $item ) {
			$name     = $item['name'];
			$quantity = $item['qty'];
			$products .= $name . ' (Qty: ' . $quantity . ')';
			$products .= ' | ';
		}

		$products = rtrim( $products, ' | ' );

		$custom_fields[] = array(
			'display_name'  => 'Product',
			'variable_name' => 'product',
			'value'         => $products,
		);

		return $custom_fields;
	}
    
}