<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allow defining keys in wp-config.php and override the saved options
 *
 * Define the following in wp-config.php to use them:
 *   define('RECAPTCHA_WOO_SITE_KEY',   'your-site-key');
 *   define('RECAPTCHA_WOO_SECRET_KEY', 'your-secret-key');
 */
add_filter( 'option_rcfwc_key', function ( $value ) {
	if ( defined( 'RECAPTCHA_WOO_SITE_KEY' ) && RECAPTCHA_WOO_SITE_KEY ) {
		return RECAPTCHA_WOO_SITE_KEY;
	}
	return $value;
} );
add_filter( 'option_rcfwc_secret', function ( $value ) {
	if ( defined( 'RECAPTCHA_WOO_SECRET_KEY' ) && RECAPTCHA_WOO_SECRET_KEY ) {
		return RECAPTCHA_WOO_SECRET_KEY;
	}
	return $value;
} );
add_filter( 'pre_option_rcfwc_key', function ( $default ) {
	if ( defined( 'RECAPTCHA_WOO_SITE_KEY' ) && RECAPTCHA_WOO_SITE_KEY ) {
		return RECAPTCHA_WOO_SITE_KEY;
	}
	return $default;
} );
add_filter( 'pre_option_rcfwc_secret', function ( $default ) {
	if ( defined( 'RECAPTCHA_WOO_SECRET_KEY' ) && RECAPTCHA_WOO_SECRET_KEY ) {
		return RECAPTCHA_WOO_SECRET_KEY;
	}
	return $default;
} );
// Prevent updating stored options when constants are defined.
add_filter( 'pre_update_option_rcfwc_key', function ( $value, $old_value ) {
	if ( defined( 'RECAPTCHA_WOO_SITE_KEY' ) && RECAPTCHA_WOO_SITE_KEY ) {
		return $old_value;
	}
	return $value;
}, 10, 2 );
add_filter( 'pre_update_option_rcfwc_secret', function ( $value, $old_value ) {
	if ( defined( 'RECAPTCHA_WOO_SECRET_KEY' ) && RECAPTCHA_WOO_SECRET_KEY ) {
		return $old_value;
	}
	return $value;
}, 10, 2 );
