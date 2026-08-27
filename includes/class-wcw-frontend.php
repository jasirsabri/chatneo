<?php
/**
 * Renders the front-end chat bubble + docked conversational panel, and enqueues assets.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wcw-' ) ) {
			return;
		}

		// Shared responsive layout fixes across all ChatNeo admin pages
		// (Settings, Route Builder, Submissions).
		wp_enqueue_style( 'wcw-admin', WCW_PLUGIN_URL . 'assets/css/admin.css', array(), WCW_VERSION );

		if ( strpos( $hook, 'wcw-settings' ) === false ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			"jQuery(document).ready(function(\$){ \$('.wcw-color-field').wpColorPicker(); });"
		);
	}

	public function enqueue_assets() {
		$settings = WCW_Settings::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// intl-tel-input: gives a country-code dropdown + format validation.
		wp_enqueue_style( 'intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/css/intlTelInput.min.css', array(), '23.8.0' );
		wp_enqueue_script( 'intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/js/intlTelInputWithUtils.min.js', array(), '23.8.0', true );

		wp_enqueue_style( 'wcw-widget', WCW_PLUGIN_URL . 'assets/css/widget.css', array(), WCW_VERSION );
		wp_enqueue_script( 'wcw-widget', WCW_PLUGIN_URL . 'assets/js/widget.js', array( 'jquery', 'intl-tel-input' ), WCW_VERSION, true );

		// Build the opening line(s). If both a site name and agent name are
		// configured, the bot introduces itself, thanks the visitor, then
		// asks for their name — as three separate chat messages; otherwise
		// it falls back to a single generic greeting.
		$site_name  = trim( $settings['site_name'] );
		$agent_name = trim( $settings['agent_name'] );

		// A direct request rather than a yes/no-shaped question — visitors
		// sometimes reply "yes" or "sure" to a question here instead of
		// actually typing their name.
		$name_prompt = __( 'Please share your name to get started.', 'whatomate-chat-widget' );

		if ( '' !== $site_name && '' !== $agent_name ) {
			$greeting_lines = array(
				/* translators: 1: agent name, 2: site/company name */
				sprintf( __( "Hey! I'm %1\$s from %2\$s.", 'whatomate-chat-widget' ), $agent_name, $site_name ),
				__( 'Thanks for reaching out!', 'whatomate-chat-widget' ),
				$name_prompt,
			);
		} else {
			$greeting_lines = array(
				__( 'Hi there! Thanks for stopping by.', 'whatomate-chat-widget' ),
				$name_prompt,
			);
		}

		wp_localize_script(
			'wcw-widget',
			'wcwData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wcw_frontend_nonce' ),
				'greetingLines' => $greeting_lines,
				'routes'        => WCW_Routes::get_routes_for_frontend(),
				'i18n'          => array(
					'invalidName'     => __( "I didn't quite catch that — please share your name.", 'whatomate-chat-widget' ),
					'askRoute'        => __( 'Nice to meet you, {name}. Which department would you like to reach out to?', 'whatomate-chat-widget' ),
					'invalidRequired' => __( "Sorry, I didn't quite get that — could you try again?", 'whatomate-chat-widget' ),
					'invalidPhone'    => __( "That doesn't look like a valid number. Could you enter it again with the country code?", 'whatomate-chat-widget' ),
					'invalidEmail'    => __( "That email address doesn't look quite right — mind trying again?", 'whatomate-chat-widget' ),
					'whatsappTransition' => __( 'Thanks, {name}! Tap below to continue on WhatsApp.', 'whatomate-chat-widget' ),
					'whatsappButton'  => __( 'Continue on WhatsApp', 'whatomate-chat-widget' ),
					'genericError'    => __( 'Something went wrong on our end. Please tap below to try again.', 'whatomate-chat-widget' ),
					'retryButton'     => __( 'Try again', 'whatomate-chat-widget' ),
					'placeholderText' => __( 'Type your answer…', 'whatomate-chat-widget' ),
				),
			)
		);
	}

	public function render_widget() {
		$settings = WCW_Settings::get_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['whatsapp_number'] ) ) {
			return;
		}

		$position_class = ( 'bottom-left' === $settings['position'] ) ? 'wcw-pos-left' : 'wcw-pos-right';
		?>
		<div id="wcw-widget-root" class="<?php echo esc_attr( $position_class ); ?>" style="--wcw-background-color: <?php echo esc_attr( $settings['bubble_color'] ); ?>; --wcw-foreground-color: <?php echo esc_attr( $settings['foreground_color'] ); ?>;">

			<div id="wcw-chat-panel" class="wcw-hidden" role="dialog" aria-modal="true" aria-labelledby="wcw-chat-title">
				<div id="wcw-chat-header">
					<span class="wcw-chat-header-icon" aria-hidden="true">
						<svg viewBox="0 0 32 32" width="18" height="18" fill="currentColor">
							<path d="M16 3C9.4 3 4 8.1 4 14.4c0 2.5.9 4.8 2.4 6.7L5 29l8.2-2.1c.9.2 1.8.3 2.8.3 6.6 0 12-5.1 12-11.4S22.6 3 16 3z"/>
						</svg>
					</span>
					<span id="wcw-chat-title"><?php echo esc_html( $settings['greeting_text'] ); ?></span>
					<button type="button" id="wcw-chat-close" aria-label="<?php esc_attr_e( 'Close chat', 'whatomate-chat-widget' ); ?>">&times;</button>
				</div>

				<div id="wcw-chat-messages" aria-live="polite"></div>

				<form id="wcw-chat-form" novalidate>
					<div class="wcw-chat-input-wrap">
						<div class="wcw-step-input" data-step="active">
							<input type="text" id="wcw-input-active" autocomplete="off" disabled />
						</div>
					</div>
					<button type="submit" id="wcw-chat-send" aria-label="<?php esc_attr_e( 'Send', 'whatomate-chat-widget' ); ?>" disabled>
						<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M3 20l18-8L3 4v6l12 2-12 2z"/></svg>
					</button>
				</form>
			</div>

			<?php if ( ! empty( $settings['show_online_sticker'] ) ) : ?>
				<div class="wcw-online-sticker" aria-hidden="true">
					<span class="wcw-online-dot"></span>
					<span class="wcw-online-text"><?php echo esc_html( $settings['online_sticker_text'] ); ?></span>
				</div>
			<?php endif; ?>

			<button type="button" id="wcw-bubble-btn" aria-label="<?php esc_attr_e( 'Open chat', 'whatomate-chat-widget' ); ?>">
				<span class="wcw-bubble-icon wcw-icon-chat" aria-hidden="true">
					<svg viewBox="0 0 32 32" width="26" height="26" fill="currentColor">
						<path d="M16 3C9.4 3 4 8.1 4 14.4c0 2.5.9 4.8 2.4 6.7L5 29l8.2-2.1c.9.2 1.8.3 2.8.3 6.6 0 12-5.1 12-11.4S22.6 3 16 3z"/>
					</svg>
				</span>
				<span class="wcw-bubble-icon wcw-icon-close" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
				</span>
				<span class="wcw-bubble-label"><?php echo esc_html( $settings['greeting_text'] ); ?></span>
				<?php if ( ! empty( $settings['show_online_sticker'] ) ) : ?>
					<span class="wcw-bubble-online-dot" aria-hidden="true"></span>
				<?php endif; ?>
			</button>
		</div>
		<?php
	}
}
