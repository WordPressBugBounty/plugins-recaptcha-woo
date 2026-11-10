<?php
/**
* Plugin Name: reCAPTCHA for WooCommerce
* Description: Add Google reCAPTCHA to your WooCommerce Checkout, Login, and Registration Forms.
* Version: 1.5.0
* Author: Elliot Sowersby, RelyWP
* Author URI: https://www.relywp.com
* License: GPLv3 or later
* Text Domain: recaptcha-woo
*
* WC requires at least: 3.4
* WC tested up to: 11.2
**/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include( plugin_dir_path( __FILE__ ) . 'admin-options.php');
include_once( plugin_dir_path( __FILE__ ) . 'config-keys.php');
include_once( plugin_dir_path( __FILE__ ) . 'verification.php');
include_once( plugin_dir_path( __FILE__ ) . 'whitelist.php');

/**
 * On activate redirect to settings page
 */
register_activation_hook(__FILE__, function () {
  add_option('rcfwc_do_activation_redirect', true);
	add_option('rcfwc_tested', 'no');
});
add_action('admin_init', function () {
  if (get_option('rcfwc_do_activation_redirect', false)) {
    delete_option('rcfwc_do_activation_redirect');
    exit( wp_redirect("options-general.php?page=recaptcha-woo%2Fadmin-options.php") );
  }
});

/**
 * Compatible with HPOS
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
	}
} );

// Plugin List - Settings Link
add_filter( 'plugin_action_links', 'rcfwc_settings_link_plugin', 10, 5 );
function rcfwc_settings_link_plugin( $actions, $plugin_file )
{
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {
		$settings = array('settings' => '<a href="options-general.php?page=recaptcha-woo%2Fadmin-options.php">' . __('Settings', 'General') . '</a>');
    	$actions = array_merge($settings, $actions);
	}

	return $actions;
}

// Enqueue recaptcha script only on account or checkout page
if(get_option('rcfwc_scripts_all', true)) {
	add_action("wp_enqueue_scripts", "rcfwc_script_enqueue");
}
function rcfwc_script_enqueue() {
	wp_enqueue_script( 'rcfwc-js', plugins_url( '/js/rcfwc.js', __FILE__ ), array('jquery'), '1.5.0', array('strategy' => 'defer'));
	wp_enqueue_script( 'recaptcha', 'https://www.google.com/recaptcha/api.js?hl=' . get_locale(), array(), null, array('strategy' => 'defer'));
}
add_action("wp_enqueue_scripts", "rcfwc_script");
function rcfwc_script() {
  if( rcfwc_is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
  	if ( is_checkout() || is_account_page() ) {
  		 rcfwc_script_enqueue();
  	}
  }
}
// Enqueue recaptcha script on login
add_action("login_enqueue_scripts", "rcfwc_script_login");
function rcfwc_script_login() {
	rcfwc_script_enqueue();
}

// Field
function rcfwc_field() {
	$key = esc_attr( get_option('rcfwc_key') );
	$secret = esc_attr( get_option('rcfwc_secret') );
	$theme = esc_attr( get_option('rcfwc_theme') );
	if($key && $secret) {
		?>
		<div class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo $key; ?>"></div>
		<br/>
		<?php
	}
}

// Field WP Admin
function rcfwc_field_admin() {
	$key = esc_attr( get_option('rcfwc_key') );
	$secret = esc_attr( get_option('rcfwc_secret') );
	$theme = esc_attr( get_option('rcfwc_theme') );
	if($key && $secret) {
		?>
		<div style="margin-left: -15px;" class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo $key; ?>"></div>
		<br/>
		<?php
	}
}

// Field Checkout
function rcfwc_field_checkout($checkout = null) {
	$key = esc_attr( get_option('rcfwc_key') );
	$secret = esc_attr( get_option('rcfwc_secret') );
	$theme = esc_attr( get_option('rcfwc_theme') );
	$guest = esc_attr( get_option('rcfwc_guest_only') );
	if(get_option('rcfwc_woo_checkout_pos') == "afterpay") {
		echo "<br/>";
	}
	if( !$guest || ( $guest && !is_user_logged_in() ) ) {
		if($key && $secret) {
		?>
		<div class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo $key; ?>"></div>
		<br/>
		<?php
		}
	}
}

// Field Checkout block
function rcfwc_field_checkout_block() {
	$key = esc_attr( get_option('rcfwc_key') );
	$secret = esc_attr( get_option('rcfwc_secret') );
	$theme = esc_attr( get_option('rcfwc_theme') );
	$guest = esc_attr( get_option('rcfwc_guest_only') );
	?>
	<?php if ( !$guest || ( $guest && !is_user_logged_in() ) ) {
		if($key && $secret) {
			?>
			<div
				class="g-recaptcha"
				id="g-recaptcha-woo-checkout"
				<?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>
				data-sitekey="<?php echo $key; ?>"
				data-callback="rcfwcRecaptchaCallback"
				data-expired-callback="rcfwcRecaptchaExpired"
			></div>
			<br/>
			<?php
		}
	} ?>
	<?php
	return;
}

function rcfwc_render_post_block($block_content) {
	ob_start();
	echo $block_content;
	rcfwc_field_checkout_block();
	$block_content = ob_get_contents();
	ob_end_clean();
	return $block_content;
}

function rcfwc_render_pre_block($block_content) {
	ob_start();
	rcfwc_field_checkout_block();
	echo $block_content;
	$block_content = ob_get_contents();
	ob_end_clean();
	return $block_content;
}

// Check the reCAPTCHA on submit.
function rcfwc_recaptcha_check($token = null) {

	// Whitelist short-circuit
	if ( function_exists( 'rcfwc_whitelisted' ) && rcfwc_whitelisted() ) {
		return array( 'success' => true );
	}

	if ( isset( $token ) ) {
		$postdata = sanitize_text_field( $token );
	} else {
		$postdata = "";
		if(isset($_POST['g-recaptcha-response'])) {
			$postdata = sanitize_text_field( $_POST['g-recaptcha-response'] );
		}
	}

	$key = esc_attr( get_option('rcfwc_key') );
	$secret = esc_attr( get_option('rcfwc_secret') );
	$guest = esc_attr( get_option('rcfwc_guest_only') );

	if($key && $secret) {

		$body = array(
			'secret'   => $secret,
			'response' => $postdata,
		);
		$remoteip = function_exists( 'rcfwc_get_ip' ) ? rcfwc_get_ip() : '';
		if ( $remoteip ) {
			$body['remoteip'] = $remoteip;
		}
		$verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'body' => $body,
		) );
		if ( is_wp_error( $verify ) ) {
			return array( 'success' => false, 'error_code' => 'bad-request' );
		}
		$verify = wp_remote_retrieve_body( $verify );
		$response = json_decode($verify);

		if ( ! is_object( $response ) ) {
			return array( 'success' => false );
		}


		$results = array(
			'success' => ! empty( $response->success ),
		);

		foreach($response as $key => $val){
			if($key == 'error-codes')
			foreach((array) $val as $key => $error_val){
				$results['error_code'] = sanitize_text_field( $error_val );
			}
		}

		return $results;

	} else {

		return false;

	}

}

/**
 * Custom "is_plugin_active" wrapper that doesn't require pluggable.php on the frontend.
 *
 * @param string $plugin
 * @return bool
 */
if ( ! function_exists( 'rcfwc_is_plugin_active' ) ) {
	function rcfwc_is_plugin_active( $plugin ) {
		$active = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( in_array( $plugin, $active, true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			if ( isset( $network[ $plugin ] ) ) {
				return true;
			}
		}
		return false;
	}
}

/**
 * Get the (optionally customized) failed reCAPTCHA message.
 *
 * @return string
 */
function rcfwc_failed_message() {
	$custom = get_option( 'rcfwc_error_message' );
	if ( ! empty( $custom ) ) {
		return wp_kses_post( $custom );
	}
	return esc_html__( 'Please complete the reCAPTCHA to verify that you are not a robot.', 'recaptcha-woo' );
}

/**
 * Whether the current request is a WooCommerce login form submission.
 *
 * WooCommerce accepts the login nonce as either "woocommerce-login-nonce" or "_wpnonce",
 * so the request is detected from the fields WC_Form_Handler::process_login() requires
 * rather than from the name of the nonce field.
 *
 * @return bool
 */
function rcfwc_is_woo_login_request() {
	return isset( $_POST['woocommerce-login-nonce'] ) || isset( $_POST['login'], $_POST['username'], $_POST['password'] );
}

/**
 * Whether the current request is a WooCommerce lost password form submission.
 *
 * WooCommerce accepts the lost password nonce as either "woocommerce-lost-password-nonce" or "_wpnonce",
 * so the request is detected from the field WC_Form_Handler::process_lost_password() requires
 * rather than from the name of the nonce field.
 *
 * @return bool
 */
function rcfwc_is_woo_lost_password_request() {
	return isset( $_POST['woocommerce-lost-password-nonce'] ) || isset( $_POST['wc_reset_password'] );
}

/**
 * Whether the current request is being handled by wp-login.php (including custom login URLs that load it).
 *
 * @return bool
 */
function rcfwc_is_wp_login_script() {
	global $pagenow;
	return isset( $pagenow ) && 'wp-login.php' === $pagenow;
}

/**
 * Whether this request is on the WordPress login page, for the "Only on wp-login.php" options.
 *
 * wp-login.php is detected by it actually running, never only by the request URL: /wp-login.ph%70
 * and //wp-login.php still run it but don't match the wp_login_url() path. A page that
 * wp_login_url() has been pointed at instead still counts when the request path matches it exactly.
 *
 * @return bool
 */
function rcfwc_is_wp_login_page() {
	if ( rcfwc_is_wp_login_script() || did_action( 'login_init' ) ) {
		return true;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}
	$login_url_path   = wp_parse_url( wp_login_url(), PHP_URL_PATH );
	$current_url_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	return $login_url_path && $current_url_path === $login_url_path;
}

/**
 * Whether the WP login / lost password checks should run for this request.
 *
 * Keeps the original loose request URI match against wp_login_url(), and also always
 * matches when wp-login.php is actually running (so an encoded URL can't skip the check).
 *
 * @return bool
 */
function rcfwc_is_login_request_uri() {
	if ( rcfwc_is_wp_login_page() ) {
		return true;
	}
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	return $request_uri && stripos( $request_uri, strrchr( wp_login_url(), '/' ) ) !== false;
}

// Field: WP login form (respects "Only on wp-login.php")
function rcfwc_field_login() {
	if ( get_option( 'rcfwc_login_only', 0 ) && ! rcfwc_is_wp_login_page() ) {
		return;
	}
	rcfwc_field_admin();
}

// Field: WP register form (respects "Only on wp-login.php")
function rcfwc_field_register() {
	if ( get_option( 'rcfwc_register_only', 0 ) && ! rcfwc_is_wp_login_page() ) {
		return;
	}
	rcfwc_field_admin();
}

/**
 * Whether the current request carries a valid WooCommerce login nonce under either field name.
 *
 * @return bool
 */
function rcfwc_woo_login_nonce_valid() {
	$nonce = '';
	if ( isset( $_POST['woocommerce-login-nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-login-nonce'] ) );
	} elseif ( isset( $_POST['_wpnonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
	}
	return $nonce && wp_verify_nonce( $nonce, 'woocommerce-login' );
}

if(!empty(get_option('rcfwc_key')) && !empty(get_option('rcfwc_secret'))) {

	// WP Login Check
	if(get_option('rcfwc_login')) {
		if(get_option('rcfwc_tested') == 'yes') {
			add_action('login_form','rcfwc_field_login');
			add_action('authenticate', 'rcfwc_wp_login_check', 21, 1);
			function rcfwc_wp_login_check($user){

				// Only run if $user exists
				if(!isset($user->ID)) { return $user; }

				// Check skip
				if(defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST) { return $user; } // Skip XMLRPC
				if(defined( 'REST_REQUEST' ) && REST_REQUEST) { return $user; } // Skip REST API
				if(rcfwc_is_woo_login_request() && !rcfwc_is_wp_login_script() && rcfwc_woo_login_nonce_valid()) { return $user; } // Skip Woo (never on wp-login.php itself)
				if(is_wp_error($user) && isset($user->errors['empty_username']) && isset($user->errors['empty_password']) ) {return $user; } // Skip Errors

				// Login-only mode (only run on wp-login.php).
				if ( get_option( 'rcfwc_login_only', 0 ) && ! rcfwc_is_wp_login_page() ) {
					return $user;
				}

				// Custom skip filter (integrations can return true to bypass global WP login check).
				if ( apply_filters( 'rcfwc_wp_login_checks', false ) === true ) {
					return $user;
				}

				// Check if already validated for this token (transient based, cache-friendly).
				if ( rcfwc_get_verified( 'rcfwc_login_checked' ) ) {
					return $user;
				}

				if(rcfwc_is_login_request_uri()) { // Check if WP login page
					$check = rcfwc_recaptcha_check();
					$success = $check['success'];
					if($success != true) {
						$user = new WP_Error( 'authentication_failed', rcfwc_failed_message() );
					} else {
						rcfwc_set_verified( 'rcfwc_login_checked' );
					}
				}

				return $user;

			}
		}
	}
	// Clear verification flag on login
	add_action('wp_login', 'rcfwc_wp_login_clear', 10, 2);
	function rcfwc_wp_login_clear($user_login, $user) {
		rcfwc_clear_verified( 'rcfwc_login_checked' );
	}

	// Skip global WP login reCAPTCHA check when a Woo login form is submitted but Woo login integration is disabled.
	add_filter( 'rcfwc_wp_login_checks', 'rcfwc_woo_skip_wp_login_check', 10, 1 );
	function rcfwc_woo_skip_wp_login_check( $skip ) {
		if ( ! get_option( 'rcfwc_woo_login' ) && rcfwc_is_woo_login_request() && ! rcfwc_is_wp_login_script() ) {
			return true;
		}
		return $skip;
	}

	// WP Register Check
	if(get_option('rcfwc_register')) {
		add_action('register_form','rcfwc_field_register');
		add_action('registration_errors', 'rcfwc_wp_register_check', 10, 3);
		function rcfwc_wp_register_check($errors, $sanitized_user_login, $user_email) {
			if(defined( 'XMLRPC_REQUEST')) { return $errors; } // Skip XMLRPC
			if(defined( 'REST_REQUEST')) { return $errors; } // Skip REST API

			// Register-only mode (only run on wp-login.php).
			if ( get_option( 'rcfwc_register_only', 0 ) && ! rcfwc_is_wp_login_page() ) {
				return $errors;
			}

			$check = rcfwc_recaptcha_check();
			$success = $check['success'];
			if($success != true) {
				$errors->add( 'rcfwc_error', sprintf('<strong>%s</strong>: %s',__( 'ERROR', 'recaptcha-woo' ), rcfwc_failed_message() ) );
			}
			return $errors;
		}
	}

	// WP Reset Check
	if(get_option('rcfwc_woo_reset')) {
		if(!is_admin()) {
			add_action('lostpassword_form','rcfwc_field_admin');
			add_action('lostpassword_post','rcfwc_wp_reset_check', 10, 1);
			function rcfwc_wp_reset_check($validation_errors) {
			if(rcfwc_is_login_request_uri()) { // Check if WP login page
				$check = rcfwc_recaptcha_check();
				$success = $check['success'];
				if($success != true) {
					$validation_errors->add( 'rcfwc_error', rcfwc_failed_message() );
				}
			}
			}
		}
	}

	if( rcfwc_is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

  	// Woo Checkout
  	if( get_option('rcfwc_key') && get_option('rcfwc_woo_checkout') ) {
		if(empty(get_option('rcfwc_woo_checkout_pos')) || get_option('rcfwc_woo_checkout_pos') == "beforepay") {
			add_action('woocommerce_review_order_before_payment', 'rcfwc_field_checkout', 10);
			add_filter('render_block_woocommerce/checkout-payment-block', 'rcfwc_render_pre_block', 999, 1); // Before Payment block.
		} elseif(get_option('rcfwc_woo_checkout_pos') == "afterpay") {
			add_action('woocommerce_review_order_after_payment', 'rcfwc_field_checkout', 10);
			add_filter('render_block_woocommerce/checkout-payment-block', 'rcfwc_render_post_block', 999, 1); // After Payment block.
		} elseif(get_option('rcfwc_woo_checkout_pos') == "beforebilling") {
			add_action('woocommerce_before_checkout_billing_form', 'rcfwc_field_checkout', 10);
			add_filter('render_block_woocommerce/checkout-contact-information-block', 'rcfwc_render_pre_block', 999, 1); // Before Contact Information block.
		} elseif(get_option('rcfwc_woo_checkout_pos') == "afterbilling") {
			add_action('woocommerce_after_checkout_billing_form', 'rcfwc_field_checkout', 10);
			add_filter('render_block_woocommerce/checkout-shipping-methods-block', 'rcfwc_render_pre_block', 999, 1); // Before Shipping Methods block.
		} elseif(get_option('rcfwc_woo_checkout_pos') == "beforesubmit") {
			add_action('woocommerce_review_order_before_submit', 'rcfwc_field_checkout', 10);
			add_filter('render_block_woocommerce/checkout-actions-block', 'rcfwc_render_pre_block', 999, 1); // Before Actions block, not sure if this option is still supported.
		}
  		add_action('woocommerce_checkout_process', 'rcfwc_checkout_check');
		add_action('woocommerce_after_checkout_validation', 'rcfwc_checkout_check');
		add_action('woocommerce_store_api_checkout_update_order_from_request', 'rcfwc_checkout_block_check', 10, 2);
		add_action('woocommerce_loaded', 'rcfwc_register_endpoint_data', 20);
		function rcfwc_register_endpoint_data() {
			if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
				return;
			}
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'checkout',
					'namespace'       => 'rcfwc',
					'schema_callback' => function() {
						return array(
							'token' => array(
								'description'       => __( 'reCaptcha token.', 'recaptcha-woo' ),
								'type'              => 'string',
								'context'           => array( 'view', 'edit' ),
								'sanitize_callback' => 'sanitize_text_field',
								'readonly'          => false,
							),
						);
					},
				)
			);
		}

		/**
		 * Helper: detect non-checkout wc-ajax requests so we don't burn the reCAPTCHA
		 * token on payment-method pre-validation calls etc.
		 */
		function rcfwc_is_non_checkout_ajax() {
			// Never skip once an order is being placed (the classic checkout form handler runs
			// on any URL, so a "wc-ajax" query string alone must not skip the check).
			if ( did_action( 'woocommerce_before_checkout_process' ) ) {
				return false;
			}
			$wc_ajax = isset( $_GET['wc-ajax'] ) ? sanitize_text_field( wp_unslash( $_GET['wc-ajax'] ) ) : '';
			if ( $wc_ajax && $wc_ajax !== 'checkout' ) {
				return true;
			}
			return false;
		}

  		function rcfwc_checkout_check() {
			// Prevent duplicate execution within a single request.
			static $rcfwc_wc_checkout_ran = false;
			if ( $rcfwc_wc_checkout_ran ) {
				return;
			}

			// Skip non-checkout wc-ajax requests (e.g. payment gateway pre-validation) to preserve the token.
			if ( rcfwc_is_non_checkout_ajax() ) {
				return;
			}

			// Skip if reCAPTCHA disabled for payment method
			$skip = 0;
			if ( isset( $_POST['payment_method'] ) ) {
				$chosen_payment_method = sanitize_text_field( $_POST['payment_method'] );
				$selected_payment_methods = get_option('rcfwc_selected_payment_methods', array());
				if(is_array($selected_payment_methods)) {
					if ( in_array( $chosen_payment_method, $selected_payment_methods, true ) ) {
						$skip = 1;
					}
				}
			}
			// Check if guest only enabled
			$guest = esc_attr( get_option('rcfwc_guest_only') );
			// Check
			if( !$skip && (!$guest || ( $guest && !is_user_logged_in() )) ) {
				// If this token already passed verification, skip re-check.
				if ( rcfwc_get_verified( 'rcfwc_checkout_checked' ) ) {
					$rcfwc_wc_checkout_ran = true;
					return;
				}
				$check = rcfwc_recaptcha_check();
				$success = $check['success'];
				if($success != true) {
					wc_add_notice( rcfwc_failed_message(), 'error');
				} else {
					rcfwc_set_verified( 'rcfwc_checkout_checked', '', 120 );
				}
				$rcfwc_wc_checkout_ran = true;
			}
  		}

		// Clear classic checkout verification transient after all validation hooks have run.
		add_action('woocommerce_after_checkout_validation', 'rcfwc_checkout_clear_transient', 9999);
		function rcfwc_checkout_clear_transient() {
			rcfwc_clear_verified( 'rcfwc_checkout_checked' );
		}

		function rcfwc_checkout_block_check($order, $request) {

			// Prevent duplicate execution within a single request.
			static $rcfwc_wc_block_checkout_ran = false;
			if ( $rcfwc_wc_block_checkout_ran ) {
				return $order;
			}

			// No wc-ajax skip on this REST route: a "wc-ajax" query string can be added to any Store API request.

			// Whitelisted requests don't need a token.
			if ( rcfwc_whitelisted() ) {
				return $order;
			}

			if ( $request->get_method() === 'POST' ) {

				if ( $request->get_param( 'payment_method' ) !== null ) {
					$chosen_payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) );
					$selected_payment_methods = get_option('rcfwc_selected_payment_methods', array());
					if(is_array($selected_payment_methods)) {
						if ( in_array( $chosen_payment_method, $selected_payment_methods, true ) ) {
							return $order;
						}
					}
				}

				// Additional skip: WooPayments Express or Stripe Express (Apple Pay / Google Pay / Link) on block checkout.
				$payment_method   = $request->get_param( 'payment_method' );
				$payment_data     = $request->get_param( 'payment_data' );
				$express_detected = false;
				if ( is_array( $payment_data ) ) {
					foreach ( $payment_data as $pd_item ) {
						if ( is_array( $pd_item ) && isset( $pd_item['key'] ) ) {
							$pd_key   = $pd_item['key'];
							$pd_value = isset( $pd_item['value'] ) ? $pd_item['value'] : '';
							if ( in_array( $pd_key, array( 'express_payment_type', 'payment_request_type' ), true ) && ! empty( $pd_value ) ) {
								$express_detected = true;
								break;
							}
						}
					}
				}
				// Allow customization via filter, defaults to skip when WooPayments or Stripe express is detected.
				$skip_on_express = apply_filters( 'recaptcha_skip_on_express_pay', ( ($payment_method === 'woocommerce_payments' || $payment_method === 'stripe') && $express_detected ), $payment_method, $payment_data, $request );
				if ( $skip_on_express ) {
					return $order;
				}

				// Check if guest only enabled
				$guest = esc_attr( get_option('rcfwc_guest_only') );
				if ( !$guest || ( $guest && !is_user_logged_in() ) ) {
					$extensions = $request->get_param( 'extensions' );
					if ( empty( $extensions ) ) {
						throw new \Exception( rcfwc_failed_message() );
					}
					$value = isset( $extensions['rcfwc'] ) ? $extensions['rcfwc'] : array();
					if ( empty( $value ) || empty( $value['token'] ) ) {
						throw new \Exception( rcfwc_failed_message() );
					}
					$token = sanitize_text_field( $value['token'] );

					// Store token so the cleanup callback can access it.
					global $rcfwc_block_checkout_token;
					$rcfwc_block_checkout_token = $token;

					// If this token already passed verification, skip re-check.
					if ( rcfwc_get_verified( 'rcfwc_block_checkout_checked', $token ) ) {
						$rcfwc_wc_block_checkout_ran = true;
						return $order;
					}

					$check = rcfwc_recaptcha_check($token);
					$success = $check['success'];
					$rcfwc_wc_block_checkout_ran = true;
					if ( !$success ) {
						throw new \Exception( rcfwc_failed_message() );
					} else {
						rcfwc_set_verified( 'rcfwc_block_checkout_checked', $token, 120 );
					}
				}

			}

			return $order;

		}

		// Block checkout: clear the transient after the order is processed.
		add_action('woocommerce_store_api_checkout_order_processed', 'rcfwc_block_checkout_clear_transient', 9999);
		function rcfwc_block_checkout_clear_transient() {
			global $rcfwc_block_checkout_token;
			if ( ! empty( $rcfwc_block_checkout_token ) ) {
				rcfwc_clear_verified( 'rcfwc_block_checkout_checked', $rcfwc_block_checkout_token );
			}
		}
	}

	// Woo Checkout Pay-for-Order page
	if( get_option('rcfwc_woo_checkout_pay') ) {
		add_action('woocommerce_pay_order_before_submit', 'rcfwc_field_checkout', 10);
		add_action('woocommerce_before_pay_action', 'rcfwc_woo_checkout_pay_check', 10, 1);
		function rcfwc_woo_checkout_pay_check( $order ) {
			// Skip if reCAPTCHA disabled for payment method
			if ( isset( $_POST['payment_method'] ) ) {
				$chosen_payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
				$selected_payment_methods = get_option('rcfwc_selected_payment_methods', array());
				if ( is_array( $selected_payment_methods ) && in_array( $chosen_payment_method, $selected_payment_methods, true ) ) {
					return;
				}
			}
			// Skip logged in users when guest only is enabled (the field isn't shown to them either).
			$guest = esc_attr( get_option('rcfwc_guest_only') );
			if ( $guest && is_user_logged_in() ) {
				return;
			}
			$check = rcfwc_recaptcha_check();
			$success = $check['success'];
			if($success != true) {
				wc_add_notice( rcfwc_failed_message(), 'error');
			}
		}
	}

	// Woo Login
  	if(get_option('rcfwc_woo_login')) {
  		add_action('woocommerce_login_form','rcfwc_field');
  		add_action('authenticate', 'rcfwc_woo_login_check', 21, 1);
  		function rcfwc_woo_login_check($user){

			// Check skip
			if(!isset($user->ID)) { return $user; }
			if(!rcfwc_is_woo_login_request()) { return $user; } // Skip if not WooCommerce login
			// No XMLRPC skip here: real XML-RPC calls never carry the login form fields, but WooCommerce
			// still processes a login form that is posted to xmlrpc.php.
			if(defined( 'REST_REQUEST' ) && REST_REQUEST) { return $user; } // Skip REST API

			// Check if already validated for this token (cache-friendly).
			if ( rcfwc_get_verified( 'rcfwc_login_checked' ) ) {
				return $user;
			}

			// Check
			$check = rcfwc_recaptcha_check();
			$success = $check['success'];
			if($success != true) {
				$user = new WP_Error( 'authentication_failed', rcfwc_failed_message() );
			} else {
				rcfwc_set_verified( 'rcfwc_login_checked' );
			}
			return $user;
		}
	}

  	// Woo Register
  	if(get_option('rcfwc_woo_register')) {
  		add_action('woocommerce_register_form','rcfwc_field');
  		add_action('woocommerce_register_post', 'rcfwc_woo_register_check', 10, 3);
  		function rcfwc_woo_register_check($username, $email, $validation_errors) {
			// Skip XMLRPC, but not a WooCommerce register form that is posted to xmlrpc.php (WooCommerce still processes it).
			if(defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && !isset($_POST['register'])) { return; }
			if(defined( 'REST_REQUEST' ) && REST_REQUEST) { return; } // Skip REST API
			if(!is_checkout()) {
				$check = rcfwc_recaptcha_check();
				$success = $check['success'];
				if($success != true) {
					$validation_errors->add( 'rcfwc_error', rcfwc_failed_message() );
				}
			}
		}
	}

  	// Woo Reset
  	if(get_option('rcfwc_woo_reset')) {
  		add_action('woocommerce_lostpassword_form','rcfwc_field');
  		add_action('lostpassword_post','rcfwc_woo_reset_check', 10, 1);
  		function rcfwc_woo_reset_check($validation_errors) {
			// On the WP login page the WP reset check above handles it. Only skip when that check is actually
			// hooked: it isn't on admin-ajax.php, where "/wp-login.php" in the query string would skip both checks.
			if(rcfwc_is_login_request_uri() && has_action('lostpassword_post', 'rcfwc_wp_reset_check')) {
				return $validation_errors;
			}
			if(rcfwc_is_woo_lost_password_request()) {
				$check = rcfwc_recaptcha_check();
				$success = $check['success'];
				if($success != true) {
					$validation_errors->add( 'rcfwc_error', rcfwc_failed_message() );
				}
			}
			return $validation_errors;
		}
	}

  }

}
