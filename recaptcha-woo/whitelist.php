<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whitelist + IP helpers (mirrors the Simple Cloudflare Turnstile plugin).
 *
 * Allows skipping the reCAPTCHA check for logged in users, specific IP
 * addresses, or specific User-Agent substrings.
 */

/**
 * Determine whether the current request is whitelisted.
 *
 * @return bool
 */
function rcfwc_whitelisted() {
	// Never whitelist the plugin's settings page (so the admin test always runs).
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'recaptcha-woo/admin-options.php' ) {
		return false;
	}

	$whitelisted = apply_filters( 'rcfwc_whitelisted', false );

	// Logged in users.
	if ( get_option( 'rcfwc_whitelist_users' ) && is_user_logged_in() ) {
		$whitelisted = true;
	}

	// IP address whitelist.
	if ( get_option( 'rcfwc_whitelist_ips' ) ) {
		$whitelist     = get_option( 'rcfwc_whitelist_ips' );
		$whitelist_ips = explode( "\n", str_replace( "\r", "", $whitelist ) );
		$current_ip    = rcfwc_get_ip();
		foreach ( $whitelist_ips as $whitelist_ip ) {
			$whitelist_ip = sanitize_text_field( trim( $whitelist_ip ) );
			if ( ! filter_var( $whitelist_ip, FILTER_VALIDATE_IP ) ) {
				continue;
			}
			if ( $current_ip && $current_ip === $whitelist_ip ) {
				$whitelisted = true;
				break;
			}
		}
	}

	// User-Agent whitelist.
	if ( get_option( 'rcfwc_whitelist_agents' ) ) {
		$whitelist        = get_option( 'rcfwc_whitelist_agents' );
		$whitelist_agents = explode( "\n", str_replace( "\r", "", $whitelist ) );

		$current_agent = '';
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$current_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		foreach ( $whitelist_agents as $whitelist_agent ) {
			$whitelist_agent = sanitize_text_field( trim( $whitelist_agent ) );
			if ( $current_agent && $whitelist_agent && strpos( $current_agent, $whitelist_agent ) !== false ) {
				$whitelisted = true;
				break;
			}
		}
	}

	return $whitelisted;
}

/**
 * Get the visitor IP address, taking common proxy headers into account.
 *
 * @return string|false
 */
function rcfwc_get_ip() {
	$is_public_ip = function ( $ip ) {
		return filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	};

	// 1. Cloudflare header.
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$ip = sanitize_text_field( trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}

	// 2. Common proxy headers.
	$proxy_headers = array(
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_X_CLIENT_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_CLUSTER_CLIENT_IP',
	);

	foreach ( $proxy_headers as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}
		$ips = explode( ',', wp_unslash( $_SERVER[ $header ] ) );
		foreach ( $ips as $ip ) {
			$ip = sanitize_text_field( trim( $ip ) );
			if ( $is_public_ip( $ip ) ) {
				return $ip;
			}
		}
		foreach ( $ips as $ip ) {
			$ip = sanitize_text_field( trim( $ip ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}

	// 3. REMOTE_ADDR fallback.
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}

	return false;
}
