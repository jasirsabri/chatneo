<?php
/**
 * Pushes a completed submission to an ERPNext (Frappe) instance via its
 * REST API, using token-based authentication
 * (Authorization: token api_key:api_secret).
 *
 * The ERPNext connection itself (base URL, API key/secret) is one shared
 * setting — it's the same instance either way. What's generic per-route is
 * *which DocType* a given route creates and *which fields* its answers map
 * to, since e.g. a Sales route might create a "Lead" while a Careers route
 * creates a "Job Applicant" with an entirely different field shape.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_CRM {

	/**
	 * Whether the shared ERPNext connection is configured at all.
	 *
	 * @param array $settings Result of WCW_Settings::get_settings().
	 */
	public static function is_connected( $settings ) {
		return '' !== $settings['crm_base_url']
			&& '' !== $settings['crm_api_key']
			&& '' !== $settings['crm_api_secret'];
	}

	/**
	 * Whether a specific route is set up to push to the CRM: the shared
	 * connection must be configured, and the route itself must have CRM
	 * enabled with a DocType set.
	 *
	 * @param array $settings Result of WCW_Settings::get_settings().
	 * @param array $route    A route from WCW_Routes::get_routes().
	 */
	public static function is_configured_for_route( $settings, $route ) {
		return self::is_connected( $settings )
			&& ! empty( $route['crm']['enabled'] )
			&& '' !== trim( (string) $route['crm']['doctype'] );
	}

	/**
	 * Push one submission's data to ERPNext, using the given route's own
	 * DocType and field mapping.
	 *
	 * @param array $settings Result of WCW_Settings::get_settings().
	 * @param array $route    A route from WCW_Routes::get_routes() — provides
	 *                        crm.doctype and crm.field_map.
	 * @param array $values   Associative array keyed by field name — always
	 *                        includes 'name' and 'label' (the route's own
	 *                        label), plus whatever question keys that route
	 *                        defines (e.g. 'phone', 'email', 'requirement').
	 * @return array {
	 *     @type bool   $success Whether the push succeeded (2xx response).
	 *     @type string $error   Human-readable error, empty on success.
	 * }
	 */
	public static function push_submission( $settings, $route, $values ) {
		$payload = self::build_payload( $route, $values );

		$endpoint = $settings['crm_base_url'] . '/api/resource/' . rawurlencode( $route['crm']['doctype'] );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'token ' . $settings['crm_api_key'] . ':' . $settings['crm_api_secret'],
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'error'   => '',
			);
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		// ERPNext typically returns a descriptive message in `exception` or
		// `_server_messages` on error — fall back to a raw body snippet if
		// the shape doesn't match what's expected.
		$error_detail = '';
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['exception'] ) ) {
				$error_detail = $decoded['exception'];
			} elseif ( ! empty( $decoded['message'] ) ) {
				$error_detail = is_string( $decoded['message'] ) ? $decoded['message'] : wp_json_encode( $decoded['message'] );
			}
		}
		if ( '' === $error_detail ) {
			$error_detail = substr( $body, 0, 300 );
		}

		return array(
			'success' => false,
			/* translators: 1: HTTP status code, 2: error detail from the CRM */
			'error'   => sprintf( __( 'HTTP %1$d: %2$s', 'whatomate-chat-widget' ), $code, $error_detail ),
		);
	}

	/**
	 * Build the JSON payload using this route's own field mapping. Only
	 * mapped (non-blank) fields are included, so an unmapped field is
	 * simply omitted rather than sent under a guessed name.
	 */
	private static function build_payload( $route, $values ) {
		$field_map = isset( $route['crm']['field_map'] ) && is_array( $route['crm']['field_map'] ) ? $route['crm']['field_map'] : array();

		$payload = array();

		// Fixed values first (e.g. a mandatory "source"), so a mapped visitor
		// answer for the same field still wins below.
		if ( isset( $route['crm']['static_fields'] ) && is_array( $route['crm']['static_fields'] ) ) {
			foreach ( $route['crm']['static_fields'] as $target_field => $fixed_value ) {
				$target_field = trim( (string) $target_field );
				if ( '' !== $target_field && '' !== (string) $fixed_value ) {
					$payload[ $target_field ] = $fixed_value;
				}
			}
		}

		// The route's dedicated Lead owner setting — sent as lead_owner, and
		// takes precedence over a same-named fixed value.
		if ( ! empty( $route['crm']['lead_owner'] ) ) {
			$payload['lead_owner'] = $route['crm']['lead_owner'];
		}

		foreach ( $field_map as $our_key => $target_field ) {
			$target_field = trim( (string) $target_field );
			if ( '' !== $target_field && isset( $values[ $our_key ] ) && '' !== $values[ $our_key ] ) {
				$payload[ $target_field ] = $values[ $our_key ];
			}
		}

		return $payload;
	}
}
