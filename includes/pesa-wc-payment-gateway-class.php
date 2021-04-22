<?php

require plugin_dir_path( __FILE__ ) . '/../vendor/autoload.php';
use Carbon\Carbon;

class WC_Gateway_Pesa extends WC_Payment_Gateway {

	/*
	 * Gateway constructor.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title               = $this->get_option( 'title' );
		$this->description         = $this->get_option( 'description' );
		$this->paybill_no          = $this->get_option( 'paybill_no' );
		$this->consumer_key        = $this->get_option( 'consumer_key' );
		$this->consumer_secret     = $this->get_option( 'consumer_secret' );
		$this->pass_key            = $this->get_option( 'pass_key' );
		$this->instructions        = $this->get_option( 'instructions' );
		$this->enable_for_methods  = $this->get_option( 'enable_for_methods', array() );
		$this->enable_for_virtual  = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                  = 'pesa_payment';
		$this->icon                = apply_filters( 'woocommerce_pesa_icon', plugins_url('../assets/img/mpesa_logo.png', __FILE__ ) );
		$this->method_title        = __( 'Mobile Money Payments', 'pesa-pay-woo' );
		$this->paybill_no          = __( 'Paybill Number', 'pesa-pay-woo' );
		$this->consumer_key        = __( 'Add Consumer Key', 'pesa-pay-woo' );
		$this->consumer_secret     = __( 'Add Consumer Secret', 'pesa-pay-woo' );
		$this->pass_key            = __( 'Add PassKey', 'pesa-pay-woo' );
		$this->method_description  = __( 'Customers to pay using Mpesa.', 'pesa-pay-woo' );
		$this->has_fields          = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'pesa-pay-woo' ),
				'label'       => __( 'Enable Mobile Money Payments', 'pesa-pay-woo' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'              => array(
				'title'       => __( 'Title', 'pesa-pay-woo' ),
				'type'        => 'text',
				'description' => __( 'Mobile Payment method description that the customer will see on your checkout.', 'pesa-pay-woo' ),
				'default'     => __( 'Mobile Payments', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'paybill_no'             => array(
				'title'       => __( 'Paybill Number', 'pesa-pay-woo' ),
				'type'        => 'text',
				'description' => __( 'Add Paybill Number', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'consumer_key'             => array(
				'title'       => __( 'Consumer Key', 'pesa-pay-woo' ),
				'type'        => 'text',
				'description' => __( 'Add Consumer key', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'consumer_secret'           => array(
				'title'       => __( 'Consumer Secret', 'pesa-pay-woo' ),
				'type'        => 'text',
				'description' => __( 'Add Consumer Secret', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'pass_key'           => array(
				'title'       => __( 'PassKey', 'pesa-pay-woo' ),
				'type'        => 'text',
				'description' => __( 'Add PassKey', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'pesa-pay-woo' ),
				'type'        => 'textarea',
				'description' => __( 'Mobile Payment method description that the customer will see on your website.', 'pesa-pay-woo' ),
				'default'     => __( 'Mobile Payments before delivery.', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'instructions'       => array(
				'title'       => __( 'Instructions', 'pesa-pay-woo' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'pesa-pay-woo' ),
				'default'     => __( 'Mobile Payments before delivery.', 'pesa-pay-woo' ),
				'desc_tip'    => true,
			),
			'enable_for_methods' => array(
				'title'             => __( 'Enable for shipping methods', 'pesa-pay-woo' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __( 'If pesa is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'pesa-pay-woo' ),
				'options'           => $this->load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __( 'Select shipping methods', 'pesa-pay-woo' ),
				),
			),
			'enable_for_virtual' => array(
				'title'   => __( 'Accept for virtual orders', 'pesa-pay-woo' ),
				'label'   => __( 'Accept mobile money if the order is virtual', 'pesa-pay-woo' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'pesa_payment' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'pesa-pay-woo' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'pesa-pay-woo' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'pesa-pay-woo' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'pesa-pay-woo' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			$this->pesa_payment_processing( $order );
		}
	}

	
	private function pesa_payment_processing($order) {

		$total = intval( $order->get_total() );

		$get_phone = esc_attr( $_POST['payment_number'] );
		$formated_phone = substr($get_phone, 1); //Formated like 72000000
		$country_code = "254";
		$phone_number = $country_code.$formated_phone; //Displayed like 25472000000
		
		//Start of code from mpesa

		//timestamp
		$timestamp = Carbon::rawParse('now')->format('YmdHms');
		//passkey
		$pass_key = $this->pass_key;
		$business_short_code = $this->paybill_no;
		//generate password
		$mpesaPassword = base64_encode($business_short_code.$pass_key.$timestamp);
	

		$consumer_key=$this->consumer_key ;
		$consumer_secret=$this->consumer_secret;
		$credentials = base64_encode($consumer_key.":".$consumer_secret);
		$credential_url = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";


		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $credential_url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$credentials,"Content-Type:application/json"));
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);
		$access_token=json_decode($curl_response);
		curl_close($curl);
		
		$new_token = $access_token->access_token;	


		$curl_post_data = [
			'BusinessShortCode' =>$business_short_code,
			'Password' => $mpesaPassword,
			'Timestamp' => $timestamp,
			'TransactionType' => 'CustomerPayBillOnline',
			'Amount' => $total,
			'PartyA' => $phone_number,
			'PartyB' => $business_short_code,
			'PhoneNumber' => $phone_number,
			'CallBackURL' => 'https://boxy.test/checkout/',
			'AccountReference' => "Product Payment",
			'TransactionDesc' => "Lipa Na M-PESA"
		];


		$data_string = json_encode($curl_post_data);

		$url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';	

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$new_token));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
		$curl_response = curl_exec($curl);
		var_dump($curl_response);
		// //End of code from mpesa

		$response = wp_remote_post( $url, array( 'timeout' => 45 ) );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return "Something went wrong: $error_message";
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$order->update_status( apply_filters( 'woocommerce_pesa_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order ), __( 'Payments pending.', 'pesa-pay-woo' ) );
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			var_dump($response_body['message']);
			if ( 'Thank you! Your payment was successful' === $response_body['message'] ) {
				$order->payment_complete();

				// Remove cart.
				WC()->cart->empty_cart();

				// Return thankyou redirect.
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		}
		
	}



	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
	}

	/**
	 * Change payment complete order status to completed for pesa orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'pesa_payment' === $order->get_payment_method() ) {
			$status = 'completed';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}
}