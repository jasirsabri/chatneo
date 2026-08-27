<?php
/**
 * One generic AJAX endpoint handling every route's submissions — partial
 * saves as each question is answered, and the final action (WhatsApp
 * handoff or a direct contact message, with an optional CRM push) once
 * every required question has been answered. What used to be two
 * hardcoded handlers (Sales leads, department transfers) is now one
 * handler driven entirely by the submitted route's own configuration.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_wcw_submit_route', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_wcw_submit_route', array( $this, 'handle_submission' ) );
	}

	public function handle_submission() {
		check_ajax_referer( 'wcw_frontend_nonce', 'nonce' );

		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$route_id      = isset( $_POST['route_id'] ) ? sanitize_key( wp_unslash( $_POST['route_id'] ) ) : '';
		$name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$is_final      = ! empty( $_POST['is_final'] );
		$page_url      = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$visited_pages = isset( $_POST['visited_pages'] ) ? wp_unslash( $_POST['visited_pages'] ) : '';

		// Raw answers arrive as a JSON object of { question_key: value },
		// cumulative — every call after the first resends everything
		// answered so far, same partial-save pattern used throughout.
		$raw_answers = array();
		if ( isset( $_POST['answers'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['answers'] ), true );
			if ( is_array( $decoded ) ) {
				$raw_answers = $decoded;
			}
		}

		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'errors' => array( 'name' => __( 'Please enter your name.', 'whatomate-chat-widget' ) ) ), 400 );
		}

		$route = WCW_Routes::get_route( $route_id );
		if ( null === $route ) {
			wp_send_json_error( array( 'errors' => array( 'general' => __( 'That option is no longer available. Please try again.', 'whatomate-chat-widget' ) ) ), 400 );
		}

		// Validate and sanitize each answer according to that question's
		// own declared type — a generic version of the same per-field
		// validation every earlier version of this plugin hardcoded.
		$clean_answers = array();
		$errors        = array();

		foreach ( $route['questions'] as $question ) {
			$key = $question['key'];
			if ( ! array_key_exists( $key, $raw_answers ) ) {
				continue; // Not answered yet — fine on a partial-save call.
			}

			$raw_value = (string) $raw_answers[ $key ];

			if ( 'phone' === $question['type'] ) {
				$digits = preg_replace( '/[^\d+]/', '', $raw_value );
				if ( '' !== $raw_value && ! preg_match( '/^\+?[1-9]\d{7,14}$/', $digits ) ) {
					$errors[ $key ] = __( 'Please enter a valid phone number with country code.', 'whatomate-chat-widget' );
					continue;
				}
				$clean_answers[ $key ] = $digits;
			} elseif ( 'email' === $question['type'] ) {
				$email = sanitize_email( $raw_value );
				if ( '' !== $raw_value && ! is_email( $email ) ) {
					$errors[ $key ] = __( 'Please enter a valid email address.', 'whatomate-chat-widget' );
					continue;
				}
				$clean_answers[ $key ] = $email;
			} else {
				$clean_answers[ $key ] = sanitize_text_field( $raw_value );
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ), 400 );
		}

		$settings = WCW_Settings::get_settings();

		$submission_data = array(
			'route_id'      => $route['id'],
			'route_label'   => $route['label'],
			'name'          => $name,
			'answers'       => $clean_answers,
			'page_url'      => $page_url,
			'visited_pages' => $visited_pages,
			'ip_address'    => $this->get_client_ip(),
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		$response = array();

		if ( $is_final ) {
			// Build the placeholder set: {name}, {label}, plus every
			// question key this route defines. Direct-message routes also
			// get {contact_phone}/{contact_email} from the route's own
			// static contact details — kept separate from a visitor-
			// answered {phone}/{email} question, if that route happens to
			// also ask one, so the two never collide.
			$placeholders = array(
				'{name}'  => $name,
				'{label}' => $route['label'],
			);
			foreach ( $clean_answers as $key => $value ) {
				$placeholders[ '{' . $key . '}' ] = $value;
			}
			if ( 'direct_message' === $route['final_action']['type'] ) {
				$placeholders['{contact_phone}'] = $route['final_action']['contact_phone'];
				$placeholders['{contact_email}'] = $route['final_action']['contact_email'];
			}

			$message = strtr( $route['final_action']['message_template'], $placeholders );
			$submission_data['outcome_message']    = $message;
			$submission_data['final_action_type']  = $route['final_action']['type'];

			if ( 'direct_message' === $route['final_action']['type'] ) {
				$response['direct_message'] = $message;
			} else {
				if ( empty( $settings['whatsapp_number'] ) ) {
					wp_send_json_error( array( 'errors' => array( 'general' => __( 'Chat is temporarily unavailable. Please try again later.', 'whatomate-chat-widget' ) ) ), 500 );
				}
				$wa_number             = ltrim( $settings['whatsapp_number'], '+' );
				$response['redirect_url'] = 'https://wa.me/' . rawurlencode( $wa_number ) . '?text=' . rawurlencode( $message );
			}

			// CRM push — only for routes that opt in, only on the final
			// call, and never blocks the visitor's outcome either way.
			if ( WCW_CRM::is_configured_for_route( $settings, $route ) ) {
				$crm_values = array_merge(
					array( 'name' => $name, 'label' => $route['label'] ),
					$clean_answers
				);
				$crm_result = WCW_CRM::push_submission( $settings, $route, $crm_values );

				$submission_data['crm_synced']     = $crm_result['success'] ? 1 : 0;
				$submission_data['crm_sync_error'] = $crm_result['success'] ? '' : $crm_result['error'];
			}
		}

		$saved_id = WCW_DB::upsert_submission( $submission_id, $submission_data );

		if ( false === $saved_id ) {
			wp_send_json_error( array( 'errors' => array( 'general' => __( 'Could not save your details. Please try again.', 'whatomate-chat-widget' ) ) ), 500 );
		}

		$response['submission_id'] = $saved_id;

		wp_send_json_success( $response );
	}

	/**
	 * Best-effort client IP lookup.
	 */
	private function get_client_ip() {
		foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
