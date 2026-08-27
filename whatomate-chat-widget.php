<?php
/**
 * Plugin Name:       ChatNeo
 * Description:       A floating, conversational WhatsApp chat widget with a fully generic Route engine — each destination department (Sales, Careers, Support, or anything you define) has its own questions, its own ending (WhatsApp handoff or a direct contact message), and its own optional ERPNext CRM mapping, all configured in the Route Builder without touching code.
 * Version:           1.10.1
 * Author:            Consyst Group
 * Text Domain:       whatomate-chat-widget
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WCW_VERSION', '1.10.1' );
define( 'WCW_DB_VERSION', '1.9' );
define( 'WCW_PLUGIN_FILE', __FILE__ );
define( 'WCW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCW_TABLE_NAME', 'wcw_leads' );
define( 'WCW_TRANSFERS_TABLE_NAME', 'wcw_transfers' );
define( 'WCW_SUBMISSIONS_TABLE_NAME', 'wcw_submissions' );

require_once WCW_PLUGIN_DIR . 'includes/class-wcw-db.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-settings.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-routes.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-crm.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-routes-page.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-submissions-page.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-ajax.php';
require_once WCW_PLUGIN_DIR . 'includes/class-wcw-frontend.php';

/**
 * Runs on plugin activation: creates all tables and seeds default settings.
 * Legacy leads/transfers tables are still created here too, purely so a
 * fresh activation and an upgrade-in-place behave identically — neither
 * table is written to going forward, but keeping the create call is
 * harmless and avoids a special case.
 */
function wcw_activate() {
	WCW_DB::create_table();
	WCW_DB::create_transfers_table();
	WCW_DB::create_submissions_table();
	update_option( 'wcw_db_version', WCW_DB_VERSION );

	if ( false === get_option( 'wcw_settings' ) ) {
		add_option( 'wcw_settings', WCW_Settings::get_settings() );
	}

	// Eagerly generate (and save) routes now, rather than waiting for the
	// first time something calls WCW_Routes::get_routes(). This matters on
	// upgrade: it guarantees the one-time migration reads the legacy
	// Settings values before anything else has a chance to touch them.
	WCW_Routes::get_routes();

	// Copy any existing leads/transfers history into the new unified
	// table so it's visible in Submissions — guarded internally to only
	// ever run once.
	WCW_DB::migrate_legacy_rows_to_submissions();
}
register_activation_hook( WCW_PLUGIN_FILE, 'wcw_activate' );

/**
 * Runs on every load: if the plugin was updated by overwriting files
 * (rather than deactivate/reactivate), this re-runs dbDelta so existing
 * installs pick up new tables/columns without losing any previously
 * collected data, and eagerly runs the routes migration for the same
 * reason described in wcw_activate() above.
 */
function wcw_maybe_upgrade_db() {
	if ( get_option( 'wcw_db_version' ) !== WCW_DB_VERSION ) {
		WCW_DB::create_table();
		WCW_DB::create_transfers_table();
		WCW_DB::create_submissions_table();
		update_option( 'wcw_db_version', WCW_DB_VERSION );
		WCW_Routes::get_routes();
		WCW_DB::migrate_legacy_rows_to_submissions();
	}
}
add_action( 'plugins_loaded', 'wcw_maybe_upgrade_db', 5 );

/**
 * Initialize plugin components. The Leads and Transfers admin pages have
 * been retired in favor of one generic Submissions page — their
 * underlying tables are preserved (and migrated into wcw_submissions on
 * upgrade) but no longer written to or displayed separately.
 */
function wcw_init() {
	new WCW_Settings();
	new WCW_Routes_Page();
	new WCW_Submissions_Page();
	new WCW_Ajax();
	new WCW_Frontend();
}
add_action( 'plugins_loaded', 'wcw_init' );
