<?php
/**
 * Generic Route configuration: each route (Sales, Careers, Support, or
 * anything an admin defines) is a data object — its questions, its final
 * action (WhatsApp handoff or a direct contact message), and an optional
 * ERPNext CRM mapping — executed by one generic engine rather than
 * hardcoded per-route logic.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_Routes {

	const OPTION_KEY = 'wcw_routes';

	/**
	 * Get all configured routes, in display order. Runs the one-time
	 * migration from the legacy Sales/Departments settings shape if no
	 * routes have been saved yet.
	 *
	 * @return array[] Each: { id, label, questions[], final_action{}, crm{} }
	 */
	public static function get_routes() {
		$routes = get_option( self::OPTION_KEY, null );

		if ( null === $routes ) {
			$routes = self::migrate_from_legacy_settings();
			update_option( self::OPTION_KEY, $routes );
		}

		return is_array( $routes ) ? $routes : array();
	}

	/**
	 * Save the full routes array (already sanitized by the caller).
	 */
	public static function save_routes( $routes ) {
		update_option( self::OPTION_KEY, $routes );
	}

	/**
	 * Look up one route by its id (case-insensitive on label, exact on id).
	 *
	 * @param string $route_id The route's id (slug).
	 * @return array|null
	 */
	public static function get_route( $route_id ) {
		foreach ( self::get_routes() as $route ) {
			if ( $route['id'] === $route_id ) {
				return $route;
			}
		}
		return null;
	}

	/**
	 * A minimal, front-end-safe projection of the routes list: just enough
	 * for the widget to render department buttons and walk through each
	 * route's questions. Deliberately excludes CRM credentials/mapping and
	 * message templates, which stay server-side and are only resolved at
	 * submission time.
	 *
	 * @return array[] Each: { id, label, questions: [{ key, type, prompt, required }] }
	 */
	public static function get_routes_for_frontend() {
		$out = array();

		foreach ( self::get_routes() as $route ) {
			$questions = array();
			foreach ( $route['questions'] as $q ) {
				$questions[] = array(
					'key'      => $q['key'],
					'type'     => $q['type'],
					'prompt'   => $q['prompt'],
					'required' => ! empty( $q['required'] ),
				);
			}

			$out[] = array(
				'id'        => $route['id'],
				'label'     => $route['label'],
				'questions' => $questions,
			);
		}

		return $out;
	}

	/**
	 * Turn a label into a stable, URL/array-key-safe id. Not re-derived on
	 * every save — an id is assigned once when a route is first created —
	 * so renaming a route's label later doesn't orphan its history.
	 */
	public static function slugify( $label, $existing_ids = array() ) {
		$base = sanitize_title( $label );
		if ( '' === $base ) {
			$base = 'route';
		}

		$id  = $base;
		$i   = 2;
		while ( in_array( $id, $existing_ids, true ) ) {
			$id = $base . '-' . $i;
			++$i;
		}

		return $id;
	}

	/**
	 * Sanitize a full routes array submitted from the admin Route Builder.
	 * Unknown keys are dropped; missing keys get safe defaults, so a
	 * malformed submission can't corrupt the option or break the frontend.
	 */
	public static function sanitize_routes( $input ) {
		$clean       = array();
		$used_ids    = array();

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		foreach ( $input as $raw_route ) {
			if ( ! is_array( $raw_route ) || '' === trim( (string) ( $raw_route['label'] ?? '' ) ) ) {
				continue; // A route with no label was likely a removed/blank repeater row — skip it.
			}

			$label = sanitize_text_field( $raw_route['label'] );

			$id = isset( $raw_route['id'] ) ? sanitize_key( $raw_route['id'] ) : '';
			if ( '' === $id || in_array( $id, $used_ids, true ) ) {
				$id = self::slugify( $label, $used_ids );
			}
			$used_ids[] = $id;

			$questions   = array();
			$used_qkeys  = array();
			$raw_questions = isset( $raw_route['questions'] ) && is_array( $raw_route['questions'] ) ? $raw_route['questions'] : array();

			foreach ( $raw_questions as $raw_q ) {
				if ( ! is_array( $raw_q ) || '' === trim( (string) ( $raw_q['prompt'] ?? '' ) ) ) {
					continue;
				}

				$qkey = isset( $raw_q['key'] ) ? sanitize_key( $raw_q['key'] ) : '';
				if ( '' === $qkey ) {
					$qkey = 'field_' . ( count( $questions ) + 1 );
				}
				if ( in_array( $qkey, $used_qkeys, true ) ) {
					$qkey .= '_' . ( count( $questions ) + 1 );
				}
				$used_qkeys[] = $qkey;

				$type = isset( $raw_q['type'] ) ? sanitize_key( $raw_q['type'] ) : 'text';
				if ( ! in_array( $type, array( 'text', 'phone', 'email' ), true ) ) {
					$type = 'text';
				}

				$questions[] = array(
					'key'      => $qkey,
					'type'     => $type,
					'prompt'   => sanitize_text_field( $raw_q['prompt'] ),
					'required' => ! empty( $raw_q['required'] ),
				);
			}

			$final_type = isset( $raw_route['final_action']['type'] ) && 'direct_message' === $raw_route['final_action']['type']
				? 'direct_message'
				: 'whatsapp';

			$final_action = array(
				'type'             => $final_type,
				'message_template' => isset( $raw_route['final_action']['message_template'] )
					? sanitize_textarea_field( $raw_route['final_action']['message_template'] )
					: '',
				'contact_phone'    => isset( $raw_route['final_action']['contact_phone'] )
					? sanitize_text_field( $raw_route['final_action']['contact_phone'] )
					: '',
				'contact_email'    => isset( $raw_route['final_action']['contact_email'] )
					? sanitize_text_field( $raw_route['final_action']['contact_email'] )
					: '',
			);

			$crm_enabled  = ! empty( $raw_route['crm']['enabled'] );
			$crm_doctype  = isset( $raw_route['crm']['doctype'] ) ? sanitize_text_field( trim( $raw_route['crm']['doctype'] ) ) : '';
			$crm_owner    = isset( $raw_route['crm']['lead_owner'] ) ? sanitize_text_field( trim( $raw_route['crm']['lead_owner'] ) ) : '';
			$field_map    = array();
			$raw_field_map = isset( $raw_route['crm']['field_map'] ) && is_array( $raw_route['crm']['field_map'] ) ? $raw_route['crm']['field_map'] : array();

			// Only keep mappings for fields that actually still exist on
			// this route (name, label, and this route's current question
			// keys) — prevents stale mappings from lingering after a
			// question is renamed or removed.
			$valid_map_keys = array_merge( array( 'name', 'label' ), $used_qkeys );
			foreach ( $valid_map_keys as $map_key ) {
				if ( isset( $raw_field_map[ $map_key ] ) && '' !== trim( $raw_field_map[ $map_key ] ) ) {
					$field_map[ $map_key ] = sanitize_text_field( trim( $raw_field_map[ $map_key ] ) );
				}
			}

			// Fixed values sent with every push regardless of what the
			// visitor answered — e.g. a CRM that makes "source" mandatory.
			// Submitted as a textarea, one "fieldname = value" per line.
			$static_fields = array();
			$raw_static    = isset( $raw_route['crm']['static_fields'] ) && is_string( $raw_route['crm']['static_fields'] )
				? $raw_route['crm']['static_fields']
				: '';
			foreach ( preg_split( '/\r\n|\r|\n/', $raw_static ) as $line ) {
				$parts = explode( '=', $line, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$field_name  = sanitize_text_field( trim( $parts[0] ) );
				$field_value = sanitize_text_field( trim( $parts[1] ) );
				if ( '' !== $field_name && '' !== $field_value ) {
					$static_fields[ $field_name ] = $field_value;
				}
			}

			$clean[] = array(
				'id'           => $id,
				'label'        => $label,
				'questions'    => $questions,
				'final_action' => $final_action,
				'crm'          => array(
					'enabled'       => $crm_enabled,
					'doctype'       => $crm_doctype,
					'lead_owner'    => $crm_owner,
					'field_map'     => $field_map,
					'static_fields' => $static_fields,
				),
			);
		}

		return $clean;
	}

	/**
	 * One-time migration: build route definitions from the pre-generic
	 * Settings shape (message_template, departments textarea, transfer/
	 * direct-contact templates, single global CRM mapping) so upgrading
	 * changes nothing about how the widget behaves until the admin
	 * actively edits routes in the new builder.
	 */
	private static function migrate_from_legacy_settings() {
		$settings = WCW_Settings::get_settings();
		$routes   = array();
		$used_ids = array();

		// The legacy "Sales" path becomes the first route, keeping its
		// existing message template and CRM mapping intact.
		$sales_label = ! empty( $settings['sales_department'] ) ? $settings['sales_department'] : 'Sales';
		$sales_id    = self::slugify( $sales_label, $used_ids );
		$used_ids[]  = $sales_id;

		$routes[] = array(
			'id'        => $sales_id,
			'label'     => $sales_label,
			'questions' => array(
				array(
					'key'      => 'requirement',
					'type'     => 'text',
					'prompt'   => 'Could you briefly describe what you need?',
					'required' => true,
				),
				array(
					'key'      => 'phone',
					'type'     => 'phone',
					'prompt'   => "What's the best contact number to reach you on? Please include your country code.",
					'required' => true,
				),
				array(
					'key'      => 'email',
					'type'     => 'email',
					'prompt'   => 'Could I also get your email address?',
					'required' => true,
				),
			),
			'final_action' => array(
				'type'             => 'whatsapp',
				'message_template' => ! empty( $settings['message_template'] )
					? str_replace( array( '{intent}', '{department}' ), array( '{requirement}', '{label}' ), $settings['message_template'] )
					: "Hi, I'm a customer and I'd like to chat.\nName: {name}\nPhone: {phone}\nEmail: {email}\nRequirement: {requirement}",
				'contact_phone'    => '',
				'contact_email'    => '',
			),
			'crm' => array(
				'enabled' => ! empty( $settings['crm_enabled'] ),
				'doctype' => ! empty( $settings['crm_doctype'] ) ? $settings['crm_doctype'] : 'Lead',
				'field_map' => array_filter(
					array(
						'name'        => $settings['crm_field_name'] ?? '',
						'phone'       => $settings['crm_field_phone'] ?? '',
						'email'       => $settings['crm_field_email'] ?? '',
						'requirement' => $settings['crm_field_intent'] ?? '',
						'label'       => $settings['crm_field_department'] ?? '',
					)
				),
			),
		);

		// Every other legacy department becomes its own route with a
		// single Phone question, preserving its WhatsApp-vs-direct-message
		// behavior exactly.
		foreach ( WCW_Settings::parse_departments( $settings ) as $dept ) {
			if ( 0 === strcasecmp( $dept['name'], $sales_label ) ) {
				continue; // Already handled above as the Sales route.
			}

			$dept_id    = self::slugify( $dept['name'], $used_ids );
			$used_ids[] = $dept_id;

			if ( 'direct' === $dept['mode'] ) {
				$final_action = array(
					'type'             => 'direct_message',
					'message_template' => ! empty( $settings['direct_contact_template'] )
						? str_replace(
							array( '{department}', '{phone}', '{email}' ),
							array( '{label}', '{contact_phone}', '{contact_email}' ),
							$settings['direct_contact_template']
						)
						: '{label} Team is not available on Chat. You can reach them on phone {contact_phone} or {contact_email}',
					'contact_phone'    => $dept['phone'],
					'contact_email'    => $dept['email'],
				);
			} else {
				$final_action = array(
					'type'             => 'whatsapp',
					'message_template' => ! empty( $settings['transfer_message_template'] )
						? str_replace( '{department}', '{label}', $settings['transfer_message_template'] )
						: "Hi, I need help with {label}.\nName: {name}\nPhone: {phone}",
					'contact_phone'    => '',
					'contact_email'    => '',
				);
			}

			$routes[] = array(
				'id'        => $dept_id,
				'label'     => $dept['name'],
				'questions' => array(
					array(
						'key'      => 'phone',
						'type'     => 'phone',
						'prompt'   => "What's the best number to reach you on? Please include your country code.",
						'required' => true,
					),
				),
				'final_action' => $final_action,
				'crm'          => array(
					'enabled'   => false,
					'doctype'   => '',
					'field_map' => array(),
				),
			);
		}

		return $routes;
	}
}
