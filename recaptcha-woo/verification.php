<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token-based verification helpers (mirrors the Simple Cloudflare Turnstile plugin).
 *
 * These replace fragile PHP $_SESSION usage with short-lived transients keyed
 * to the reCAPTCHA token, so each token can only be used once and verified
 * users don't get re-checked across multiple validation hooks in the same
 * request. This is also page-cache friendly (no PHP sessions required).
 */

/**
 * Build a transient key from the reCAPTCHA token in the current POST.
 *
 * @param string $key   Verification key, e.g. 'rcfwc_checkout_checked'.
 * @param string $token Optional. Explicit token value (e.g. from block checkout
 *                      extensions data). Falls back to $_POST['g-recaptcha-response'].
 * @return string|false Transient key, or false if no token is present.
 */
function rcfwc_transient_key( $key, $token = '' ) {
	if ( empty( $token ) ) {
		$token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( $_POST['g-recaptcha-response'] ) : '';
	}
	if ( $token ) {
		return 'rcfwc_' . substr( md5( $key . '_t' . $token ), 0, 20 );
	}
	return false;
}

/**
 * Store a verification flag tied to the current reCAPTCHA token.
 *
 * @param string $key    Verification key.
 * @param string $token  Optional. Explicit token value.
 * @param int    $expire Optional. Transient TTL in seconds. Default 10.
 */
function rcfwc_set_verified( $key, $token = '', $expire = 10 ) {
	$transient_key = rcfwc_transient_key( $key, $token );
	if ( $transient_key ) {
		set_transient( $transient_key, 1, $expire );
	}
}

/**
 * Check whether a verification flag is set for the current reCAPTCHA token.
 *
 * @param string $key   Verification key.
 * @param string $token Optional. Explicit token value.
 * @return bool
 */
function rcfwc_get_verified( $key, $token = '' ) {
	$transient_key = rcfwc_transient_key( $key, $token );
	if ( $transient_key ) {
		return (bool) get_transient( $transient_key );
	}
	return false;
}

/**
 * Clear a verification flag.
 *
 * @param string $key   Verification key.
 * @param string $token Optional. Explicit token value.
 */
function rcfwc_clear_verified( $key, $token = '' ) {
	$transient_key = rcfwc_transient_key( $key, $token );
	if ( $transient_key ) {
		delete_transient( $transient_key );
	}
}
