<?php
/**
 * Handles the custom leads database table.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_DB {

	/**
	 * Get the fully-qualified table name.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . WCW_TABLE_NAME;
	}

	/**
	 * Create the leads table on activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			phone VARCHAR(32) NOT NULL,
			email VARCHAR(191) NOT NULL,
			department VARCHAR(191) NULL,
			intent TEXT NULL,
			message TEXT NULL,
			page_url VARCHAR(500) NULL,
			visited_pages TEXT NULL,
			crm_synced TINYINT(1) NOT NULL DEFAULT 0,
			crm_sync_error TEXT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create or update a lead in one call, so partial progress can be saved
	 * as the visitor answers each question — not just at the very end.
	 *
	 * @param int   $id   Existing lead ID to update, or 0 to insert a new row.
	 * @param array $data Any of: name, phone, email, department, intent, message,
	 *                    page_url, visited_pages, ip_address, user_agent. Only
	 *                    keys present in the array are written.
	 * @return int|false The lead ID on success, false on failure.
	 */
	public static function upsert_lead( $id, $data ) {
		global $wpdb;

		$allowed_fields = array( 'name', 'phone', 'email', 'department', 'intent', 'message', 'page_url', 'visited_pages', 'crm_synced', 'crm_sync_error', 'ip_address', 'user_agent' );

		$fields = array();

		foreach ( $allowed_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$fields[ $field ] = $data[ $field ];
			}
		}

		$id = (int) $id;

		if ( $id > 0 ) {
			$fields['updated_at'] = current_time( 'mysql' );

			// Every column written here is textual, so a flat '%s' per
			// field is always correct regardless of which fields are present.
			$updated = $wpdb->update(
				self::table_name(),
				$fields,
				array( 'id' => $id ),
				array_fill( 0, count( $fields ), '%s' ),
				array( '%d' )
			);

			return ( false === $updated ) ? false : $id;
		}

		// New row: fields not yet answered default to an empty string so
		// the NOT NULL name/phone/email columns are still satisfied.
		$fields = wp_parse_args(
			$fields,
			array(
				'name'  => '',
				'phone' => '',
				'email' => '',
			)
		);
		$fields['created_at'] = current_time( 'mysql' );

		$inserted = $wpdb->insert( self::table_name(), $fields, array_fill( 0, count( $fields ), '%s' ) );

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get total lead count.
	 */
	public static function get_total_count() {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}

	/**
	 * Fetch a page of leads, newest first.
	 *
	 * @param int $per_page Rows per page.
	 * @param int $offset   Row offset.
	 */
	public static function get_leads( $per_page = 20, $offset = 0 ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Fetch all leads for CSV export, newest first.
	 */
	public static function get_all_leads() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
	}

	/**
	 * Delete a single lead by ID. Returns number of rows deleted (0 or 1).
	 *
	 * @param int $id Lead row ID.
	 */
	public static function delete_lead( $id ) {
		global $wpdb;
		return $wpdb->delete(
			self::table_name(),
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	// ---------------------------------------------------------------
	// Transfers: a separate, lightweight log for visitors routed to a
	// non-Sales department. Deliberately not part of the leads table —
	// no phone/email/CRM concept applies to these records.
	// ---------------------------------------------------------------

	/**
	 * Get the fully-qualified transfers table name.
	 */
	public static function transfers_table_name() {
		global $wpdb;
		return $wpdb->prefix . WCW_TRANSFERS_TABLE_NAME;
	}

	/**
	 * Create the transfers table on activation.
	 */
	public static function create_transfers_table() {
		global $wpdb;

		$table_name      = self::transfers_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			department VARCHAR(191) NOT NULL,
			phone VARCHAR(32) NOT NULL,
			page_url VARCHAR(500) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a transfer record. Returns the inserted row ID or false on failure.
	 *
	 * @param array $data Associative array: name, department, phone, page_url.
	 */
	public static function insert_transfer( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::transfers_table_name(),
			array(
				'name'       => $data['name'],
				'department' => $data['department'],
				'phone'      => isset( $data['phone'] ) ? $data['phone'] : '',
				'page_url'   => isset( $data['page_url'] ) ? $data['page_url'] : '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get total transfer count.
	 */
	public static function get_total_transfers_count() {
		global $wpdb;
		$table = self::transfers_table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}

	/**
	 * Fetch a page of transfers, newest first.
	 *
	 * @param int $per_page Rows per page.
	 * @param int $offset   Row offset.
	 */
	public static function get_transfers( $per_page = 20, $offset = 0 ) {
		global $wpdb;
		$table = self::transfers_table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Fetch all transfers for CSV export, newest first.
	 */
	public static function get_all_transfers() {
		global $wpdb;
		$table = self::transfers_table_name();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
	}

	/**
	 * Delete a single transfer by ID. Returns number of rows deleted (0 or 1).
	 *
	 * @param int $id Transfer row ID.
	 */
	public static function delete_transfer( $id ) {
		global $wpdb;
		return $wpdb->delete(
			self::transfers_table_name(),
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	// ---------------------------------------------------------------
	// Submissions: the generic, route-driven table that replaces both
	// wcw_leads and wcw_transfers. Any route — Sales, Careers, Support,
	// or anything an admin defines later — writes here. What used to be
	// fixed columns (phone, email, intent...) now lives in the `answers`
	// JSON blob, since different routes collect different fields.
	// ---------------------------------------------------------------

	/**
	 * Get the fully-qualified submissions table name.
	 */
	public static function submissions_table_name() {
		global $wpdb;
		return $wpdb->prefix . WCW_SUBMISSIONS_TABLE_NAME;
	}

	/**
	 * Create the submissions table on activation.
	 */
	public static function create_submissions_table() {
		global $wpdb;

		$table_name      = self::submissions_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			route_id VARCHAR(191) NOT NULL,
			route_label VARCHAR(191) NOT NULL,
			name VARCHAR(191) NOT NULL,
			answers TEXT NULL,
			final_action_type VARCHAR(32) NULL,
			outcome_message TEXT NULL,
			crm_synced TINYINT(1) NOT NULL DEFAULT 0,
			crm_sync_error TEXT NULL,
			page_url VARCHAR(500) NULL,
			visited_pages TEXT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY route_id (route_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create or update a submission in one call, so partial progress can
	 * be saved as the visitor answers each question — not just at the end.
	 *
	 * @param int   $id   Existing submission ID to update, or 0 to insert.
	 * @param array $data Any of: route_id, route_label, name, answers (array,
	 *                    will be JSON-encoded), final_action_type,
	 *                    outcome_message, crm_synced, crm_sync_error,
	 *                    page_url, visited_pages, ip_address, user_agent,
	 *                    created_at, updated_at (the latter two are only
	 *                    meant for migrating legacy rows with their
	 *                    original timestamps intact — normal inserts/updates
	 *                    ignore them and use the current time instead).
	 * @return int|false The submission ID on success, false on failure.
	 */
	public static function upsert_submission( $id, $data ) {
		global $wpdb;

		$allowed_fields = array( 'route_id', 'route_label', 'name', 'answers', 'final_action_type', 'outcome_message', 'crm_synced', 'crm_sync_error', 'page_url', 'visited_pages', 'ip_address', 'user_agent' );

		$fields = array();

		foreach ( $allowed_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$value = $data[ $field ];
				if ( 'answers' === $field && is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$fields[ $field ] = $value;
			}
		}

		$id = (int) $id;

		if ( $id > 0 ) {
			$fields['updated_at'] = isset( $data['updated_at'] ) ? $data['updated_at'] : current_time( 'mysql' );

			$updated = $wpdb->update(
				self::submissions_table_name(),
				$fields,
				array( 'id' => $id ),
				array_fill( 0, count( $fields ), '%s' ),
				array( '%d' )
			);

			return ( false === $updated ) ? false : $id;
		}

		$fields = wp_parse_args(
			$fields,
			array(
				'route_id'    => '',
				'route_label' => '',
				'name'        => '',
			)
		);
		$fields['created_at'] = isset( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql' );

		$inserted = $wpdb->insert( self::submissions_table_name(), $fields, array_fill( 0, count( $fields ), '%s' ) );

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get total submission count, optionally filtered by route.
	 *
	 * @param string $route_id Optional route slug to filter by; '' for all.
	 */
	public static function get_total_submissions_count( $route_id = '' ) {
		global $wpdb;
		$table = self::submissions_table_name();

		if ( '' !== $route_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE route_id = %s", $route_id ) );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}

	/**
	 * Fetch a page of submissions, newest first, optionally filtered by route.
	 *
	 * @param int    $per_page Rows per page.
	 * @param int    $offset   Row offset.
	 * @param string $route_id Optional route slug to filter by; '' for all.
	 */
	public static function get_submissions( $per_page = 20, $offset = 0, $route_id = '' ) {
		global $wpdb;
		$table = self::submissions_table_name();

		if ( '' !== $route_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE route_id = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$route_id,
					$per_page,
					$offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Fetch all submissions for CSV export, newest first, optionally
	 * filtered by route.
	 *
	 * @param string $route_id Optional route slug to filter by; '' for all.
	 */
	public static function get_all_submissions( $route_id = '' ) {
		global $wpdb;
		$table = self::submissions_table_name();

		if ( '' !== $route_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE route_id = %s ORDER BY created_at DESC", $route_id ) );
		}

		return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
	}

	/**
	 * Delete a single submission by ID. Returns number of rows deleted (0 or 1).
	 *
	 * @param int $id Submission row ID.
	 */
	public static function delete_submission( $id ) {
		global $wpdb;
		return $wpdb->delete(
			self::submissions_table_name(),
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	/**
	 * One-time copy of legacy wcw_leads and wcw_transfers rows into the
	 * new unified wcw_submissions table, so existing history is visible in
	 * the new Submissions page rather than only in the (now-hidden) old
	 * tables. Original timestamps are preserved. Guarded by an option flag
	 * so it only ever runs once, even across multiple page loads.
	 */
	public static function migrate_legacy_rows_to_submissions() {
		if ( get_option( 'wcw_legacy_rows_migrated' ) ) {
			return;
		}

		global $wpdb;

		$routes = WCW_Routes::get_routes();

		$find_route = function ( $label ) use ( $routes ) {
			foreach ( $routes as $route ) {
				if ( 0 === strcasecmp( $route['label'], (string) $label ) ) {
					return $route;
				}
			}
			return null;
		};

		// Legacy leads → always ended in a WhatsApp handoff, with an
		// intent/description answered as "requirement" (matching the
		// question key the migrated Sales route now uses).
		$leads_table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads_table ) ) ) {
			$leads = $wpdb->get_results( "SELECT * FROM $leads_table ORDER BY id ASC" );
			foreach ( $leads as $lead ) {
				$route_label = ! empty( $lead->department ) ? $lead->department : 'Sales';
				$route       = $find_route( $route_label );

				$answers = array_filter(
					array(
						'requirement' => isset( $lead->intent ) ? $lead->intent : '',
						'phone'       => $lead->phone,
						'email'       => $lead->email,
					)
				);

				self::upsert_submission(
					0,
					array(
						'route_id'          => $route ? $route['id'] : sanitize_title( $route_label ),
						'route_label'       => $route_label,
						'name'              => $lead->name,
						'answers'           => $answers,
						'final_action_type' => 'whatsapp',
						'outcome_message'   => isset( $lead->message ) ? $lead->message : '',
						'crm_synced'        => isset( $lead->crm_synced ) ? $lead->crm_synced : 0,
						'crm_sync_error'    => isset( $lead->crm_sync_error ) ? $lead->crm_sync_error : '',
						'page_url'          => isset( $lead->page_url ) ? $lead->page_url : '',
						'visited_pages'     => isset( $lead->visited_pages ) ? $lead->visited_pages : '',
						'ip_address'        => isset( $lead->ip_address ) ? $lead->ip_address : '',
						'user_agent'        => isset( $lead->user_agent ) ? $lead->user_agent : '',
						'created_at'        => $lead->created_at,
						'updated_at'        => isset( $lead->updated_at ) ? $lead->updated_at : null,
					)
				);
			}
		}

		// Legacy transfers → ended either in a WhatsApp handoff or a direct
		// contact message, depending on how that department was configured
		// at the time — carried over via whichever mode the migrated route
		// now has.
		$transfers_table = self::transfers_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $transfers_table ) ) ) {
			$transfers = $wpdb->get_results( "SELECT * FROM $transfers_table ORDER BY id ASC" );
			foreach ( $transfers as $transfer ) {
				$route = $find_route( $transfer->department );

				self::upsert_submission(
					0,
					array(
						'route_id'          => $route ? $route['id'] : sanitize_title( $transfer->department ),
						'route_label'       => $transfer->department,
						'name'              => $transfer->name,
						'answers'           => array_filter( array( 'phone' => $transfer->phone ) ),
						'final_action_type' => $route ? $route['final_action']['type'] : 'whatsapp',
						'outcome_message'   => '',
						'crm_synced'        => 0,
						'crm_sync_error'    => '',
						'page_url'          => isset( $transfer->page_url ) ? $transfer->page_url : '',
						'created_at'        => $transfer->created_at,
					)
				);
			}
		}

		update_option( 'wcw_legacy_rows_migrated', 1 );
	}
}
