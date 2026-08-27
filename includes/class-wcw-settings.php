<?php
/**
 * Admin settings page: WhatsApp number, message template, widget appearance.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_Settings {

	const OPTION_KEY = 'wcw_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get settings with defaults merged in.
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'          => 1,
			'whatsapp_number'  => '',
			'site_name'        => get_bloginfo( 'name' ),
			'agent_name'       => '',
			'message_template' => "Hi, I'm a customer and I'd like to chat.\nName: {name}\nPhone: {phone}\nEmail: {email}\nDepartment: {department}\nLooking for: {intent}\nSite: {site_name}",
			'greeting_text'    => 'Chat with us',
			'bubble_color'     => '#25D366',
			'foreground_color' => '#ffffff',
			'position'         => 'bottom-right',
			'show_online_sticker' => 1,
			'online_sticker_text' => 'We are online',

			// CRM (ERPNext) integration.
			'crm_enabled'         => 0,
			'crm_base_url'        => '',
			'crm_api_key'         => '',
			'crm_api_secret'      => '',
			'crm_doctype'         => 'Lead',
			'crm_field_name'      => 'lead_name',
			'crm_field_phone'     => 'mobile_no',
			'crm_field_email'     => 'email_id',
			'crm_field_department' => '',
			'crm_field_intent'    => '',
			'crm_field_site'      => '',
			'crm_field_page_url'  => '',

			// Department routing.
			'departments'               => "Sales\nSupport | direct | +910000000000 | support@example.com\nCareers\nProcurement | direct | +910000000000 | procurement@example.com\nFinance | direct | +910000000000 | finance@example.com",
			'sales_department'          => 'Sales',
			'transfer_message_template' => "Hi, I need help with {department}.\nName: {name}\nPhone: {phone}",
			'direct_contact_template'   => "{department} Team is not available on Chat. You can reach them on phone {phone} or {email}",
		);

		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Parse the departments setting into structured data — one entry per
	 * line, each either a plain department name (defaults to routing
	 * through WhatsApp, same as before), or the extended format:
	 * "Name | direct | phone | email" for a department that should show
	 * its contact details directly instead of going to WhatsApp.
	 *
	 * @param array $settings Result of get_settings().
	 * @return array[] Each: array( name, mode, phone, email ). mode is
	 *                 'whatsapp' or 'direct'.
	 */
	public static function parse_departments( $settings ) {
		$lines = preg_split( '/[\r\n]+/', (string) $settings['departments'] );
		$parsed = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( count( $parts ) >= 4 && '' !== $parts[0] && 0 === strcasecmp( $parts[1], 'direct' ) ) {
				$parsed[] = array(
					'name'  => $parts[0],
					'mode'  => 'direct',
					'phone' => $parts[2],
					'email' => $parts[3],
				);
			} elseif ( '' !== $parts[0] ) {
				$parsed[] = array(
					'name'  => $parts[0],
					'mode'  => 'whatsapp',
					'phone' => '',
					'email' => '',
				);
			}
		}

		return $parsed;
	}

	/**
	 * Just the department names, in order — used for rendering buttons and
	 * validating a picked department, same interface as before this
	 * setting supported per-department contact modes.
	 *
	 * @param array $settings Result of get_settings().
	 * @return string[]
	 */
	public static function get_departments_list( $settings ) {
		return array_map(
			function ( $dept ) {
				return $dept['name'];
			},
			self::parse_departments( $settings )
		);
	}

	/**
	 * Look up one department's configuration by name (case-insensitive).
	 * Falls back to 'whatsapp' mode if the department isn't found, so an
	 * unrecognized name never silently breaks the flow.
	 *
	 * @param array  $settings   Result of get_settings().
	 * @param string $department Department name to look up.
	 * @return array { name, mode, phone, email }
	 */
	public static function get_department_config( $settings, $department ) {
		foreach ( self::parse_departments( $settings ) as $dept ) {
			if ( 0 === strcasecmp( $dept['name'], $department ) ) {
				return $dept;
			}
		}
		return array(
			'name'  => $department,
			'mode'  => 'whatsapp',
			'phone' => '',
			'email' => '',
		);
	}

	public function add_menu() {
		add_menu_page(
			__( 'ChatNeo', 'whatomate-chat-widget' ),
			__( 'ChatNeo', 'whatomate-chat-widget' ),
			'manage_options',
			'wcw-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'wcw-settings',
			__( 'Settings', 'whatomate-chat-widget' ),
			__( 'Settings', 'whatomate-chat-widget' ),
			'manage_options',
			'wcw-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wcw_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings on save.
	 */
	public function sanitize_settings( $input ) {
		$clean = array();

		$clean['enabled'] = isset( $input['enabled'] ) ? 1 : 0;

		// Strip everything except digits and a leading + from the number.
		$number                    = isset( $input['whatsapp_number'] ) ? trim( $input['whatsapp_number'] ) : '';
		$number                    = preg_replace( '/[^\d+]/', '', $number );
		$clean['whatsapp_number']  = $number;

		$clean['message_template'] = isset( $input['message_template'] )
			? sanitize_textarea_field( $input['message_template'] )
			: '';

		$clean['site_name'] = isset( $input['site_name'] )
			? sanitize_text_field( $input['site_name'] )
			: '';

		$clean['agent_name'] = isset( $input['agent_name'] )
			? sanitize_text_field( $input['agent_name'] )
			: '';

		$clean['greeting_text'] = isset( $input['greeting_text'] )
			? sanitize_text_field( $input['greeting_text'] )
			: 'Chat with us';

		$clean['bubble_color'] = isset( $input['bubble_color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $input['bubble_color'] )
			? $input['bubble_color']
			: '#25D366';

		$clean['foreground_color'] = isset( $input['foreground_color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $input['foreground_color'] )
			? $input['foreground_color']
			: '#ffffff';

		$clean['position'] = ( isset( $input['position'] ) && 'bottom-left' === $input['position'] )
			? 'bottom-left'
			: 'bottom-right';

		$clean['show_online_sticker'] = isset( $input['show_online_sticker'] ) ? 1 : 0;

		$clean['online_sticker_text'] = isset( $input['online_sticker_text'] )
			? sanitize_text_field( $input['online_sticker_text'] )
			: 'We are online';

		// ERPNext connection (shared across all routes — which DocType and
		// fields each route uses is configured per-route via WCW_Routes).
		$clean['crm_base_url'] = isset( $input['crm_base_url'] )
			? untrailingslashit( esc_url_raw( trim( $input['crm_base_url'] ) ) )
			: '';

		$clean['crm_api_key'] = isset( $input['crm_api_key'] )
			? sanitize_text_field( trim( $input['crm_api_key'] ) )
			: '';

		// Only overwrite the stored secret if a new value was actually
		// entered — the field is rendered blank on screen (like a
		// password field) so re-saving the form without touching it
		// shouldn't wipe out the existing secret.
		if ( isset( $input['crm_api_secret'] ) && '' !== trim( $input['crm_api_secret'] ) ) {
			$clean['crm_api_secret'] = sanitize_text_field( trim( $input['crm_api_secret'] ) );
		} else {
			$existing               = self::get_settings();
			$clean['crm_api_secret'] = $existing['crm_api_secret'];
		}

		// The following legacy keys no longer have Settings UI — routes,
		// their questions, CRM doctype, and field mapping are configured
		// per-route in the Route Builder now (see WCW_Routes). These are
		// preserved at whatever value they last held purely so the
		// one-time migration (WCW_Routes::migrate_from_legacy_settings)
		// can still read them on an install upgrading from before routes
		// existed; they're never written to again after that runs.
		$existing_for_legacy = self::get_settings();
		foreach ( array( 'message_template', 'departments', 'sales_department', 'transfer_message_template', 'direct_contact_template', 'crm_enabled', 'crm_doctype', 'crm_field_name', 'crm_field_phone', 'crm_field_email', 'crm_field_department', 'crm_field_intent', 'crm_field_site', 'crm_field_page_url' ) as $legacy_key ) {
			$clean[ $legacy_key ] = $existing_for_legacy[ $legacy_key ];
		}

		if ( '' === $clean['whatsapp_number'] ) {
			add_settings_error(
				self::OPTION_KEY,
				'wcw_missing_number',
				__( 'Please enter a WhatsApp number (with country code) for the widget to work.', 'whatomate-chat-widget' ),
				'error'
			);
		}

		return $clean;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap wcw-settings-wrap">
			<h1><?php esc_html_e( 'ChatNeo Settings', 'whatomate-chat-widget' ); ?></h1>

			<?php settings_errors( self::OPTION_KEY ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcw_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wcw_enabled"><?php esc_html_e( 'Enable widget', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="wcw_enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Show the chat bubble on the site', 'whatomate-chat-widget' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_whatsapp_number"><?php esc_html_e( 'WhatsApp number', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_whatsapp_number" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[whatsapp_number]" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" placeholder="+919876543210" />
							<p class="description"><?php esc_html_e( 'Include the country code, e.g. +919876543210. This is the Whatomate-connected business number visitors will be sent to.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_site_name"><?php esc_html_e( 'Site / company name', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_site_name" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[site_name]" value="<?php echo esc_attr( $settings['site_name'] ); ?>" placeholder="Acme Corp" />
							<p class="description"><?php esc_html_e( 'Used in the chat greeting (e.g. "Hi, I\'m Sarah from Acme Corp") and can be included in the WhatsApp message via {site_name} — helpful if one WhatsApp number receives leads from more than one site.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_agent_name"><?php esc_html_e( 'Agent name', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_agent_name" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[agent_name]" value="<?php echo esc_attr( $settings['agent_name'] ); ?>" placeholder="Sarah" />
							<p class="description"><?php esc_html_e( 'The name the chat widget introduces itself with. Leave both this and the site name blank to use a generic greeting instead.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_message_template"><?php esc_html_e( 'Pre-filled message', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<textarea id="wcw_message_template" class="large-text" rows="6" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[message_template]"><?php echo esc_textarea( $settings['message_template'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Placeholders: {name}, {phone}, {email}, {department}, {intent}, {site_name}, {agent_name}. {department} is the destination department the visitor picked (Sales, for this path); {intent} is now their brief description of what they need.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_greeting_text"><?php esc_html_e( 'Bubble label text', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_greeting_text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[greeting_text]" value="<?php echo esc_attr( $settings['greeting_text'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Shown next to the chat icon on desktop, and as the panel\'s header title. Hidden on mobile and while the panel is open, where the icon speaks for itself.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_show_online_sticker"><?php esc_html_e( '"We are online" sticker', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="wcw_show_online_sticker" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_online_sticker]" value="1" <?php checked( 1, $settings['show_online_sticker'] ); ?> />
								<?php esc_html_e( 'Show a small "we\'re online" badge above the bubble on desktop', 'whatomate-chat-widget' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'On mobile this collapses to a small dot on the bubble itself to save space, rather than the full badge.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_online_sticker_text"><?php esc_html_e( 'Sticker text', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_online_sticker_text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[online_sticker_text]" value="<?php echo esc_attr( $settings['online_sticker_text'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_bubble_color"><?php esc_html_e( 'Background Colour', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_bubble_color" class="wcw-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bubble_color]" value="<?php echo esc_attr( $settings['bubble_color'] ); ?>" />
							<p class="description"><?php esc_html_e( 'The main brand colour — the bubble, header, and your visitor\'s own chat messages all use this as their background.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_foreground_color"><?php esc_html_e( 'Foreground Colour', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_foreground_color" class="wcw-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[foreground_color]" value="<?php echo esc_attr( $settings['foreground_color'] ); ?>" />
							<p class="description"><?php esc_html_e( 'The text and icon colour shown on top of the Background Colour above — e.g. the bubble icon, header text, and your visitor\'s message text. Defaults to white.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_position"><?php esc_html_e( 'Widget position', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<select id="wcw_position" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[position]">
								<option value="bottom-right" <?php selected( 'bottom-right', $settings['position'] ); ?>><?php esc_html_e( 'Bottom right', 'whatomate-chat-widget' ); ?></option>
								<option value="bottom-left" <?php selected( 'bottom-left', $settings['position'] ); ?>><?php esc_html_e( 'Bottom left', 'whatomate-chat-widget' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Routes', 'whatomate-chat-widget' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Departments, their questions, what happens when a visitor finishes (WhatsApp handoff or a direct contact message), and CRM push are all configured per-route now.', 'whatomate-chat-widget' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcw-routes' ) ); ?>"><?php esc_html_e( 'Open the Route Builder →', 'whatomate-chat-widget' ); ?></a>
				</p>

				<h2><?php esc_html_e( 'ERPNext Connection', 'whatomate-chat-widget' ); ?></h2>
				<p class="description"><?php esc_html_e( 'One shared connection to your ERPNext instance, using token authentication (Authorization: token api_key:api_secret). Which DocType and fields each route pushes to is configured per-route in the Route Builder — a Sales route might create a "Lead" while a Careers route creates a "Job Applicant", using the same connection.', 'whatomate-chat-widget' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wcw_crm_base_url"><?php esc_html_e( 'ERPNext base URL', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_crm_base_url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[crm_base_url]" value="<?php echo esc_attr( $settings['crm_base_url'] ); ?>" placeholder="https://crm.example.com" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_crm_api_key"><?php esc_html_e( 'API Key', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="wcw_crm_api_key" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[crm_api_key]" value="<?php echo esc_attr( $settings['crm_api_key'] ); ?>" autocomplete="off" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wcw_crm_api_secret"><?php esc_html_e( 'API Secret', 'whatomate-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="password" id="wcw_crm_api_secret" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[crm_api_secret]" value="" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $settings['crm_api_secret'] ? __( '•••••••• (saved — leave blank to keep it)', 'whatomate-chat-widget' ) : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Left blank on this screen for security. Only fill this in when setting the secret for the first time or rotating it — leaving it blank on save keeps the existing value.', 'whatomate-chat-widget' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
