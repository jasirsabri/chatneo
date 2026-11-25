<?php
/**
 * Admin page: the Route Builder. Each route (Sales, Careers, Support, or
 * anything an admin defines) is edited here — its questions, what happens
 * when a visitor finishes, and an optional ERPNext CRM mapping.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_Routes_Page {

	const NONCE_ACTION = 'wcw_save_routes';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_wcw_save_routes', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'wcw-settings',
			__( 'Route Builder', 'whatomate-chat-widget' ),
			__( 'Route Builder', 'whatomate-chat-widget' ),
			'manage_options',
			'wcw-routes',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wcw-routes' ) ) {
			return;
		}
		wp_enqueue_script(
			'wcw-routes-admin',
			WCW_PLUGIN_URL . 'assets/js/routes-admin.js',
			array(),
			WCW_VERSION,
			true
		);
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'whatomate-chat-widget' ) );
		}

		// phpcs-safe: raw route data is fully sanitized field-by-field
		// inside WCW_Routes::sanitize_routes() before anything is saved.
		$raw_routes = isset( $_POST['wcw_routes'] ) ? wp_unslash( $_POST['wcw_routes'] ) : array();
		$clean      = WCW_Routes::sanitize_routes( $raw_routes );

		WCW_Routes::save_routes( $clean );

		wp_safe_redirect( add_query_arg( array( 'page' => 'wcw-routes', 'wcw_saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$routes = WCW_Routes::get_routes();
		?>
		<div class="wrap wcw-routes-wrap">
			<h1><?php esc_html_e( 'Route Builder', 'whatomate-chat-widget' ); ?></h1>

			<?php if ( isset( $_GET['wcw_saved'] ) && '1' === $_GET['wcw_saved'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Routes saved.', 'whatomate-chat-widget' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Each route is a destination department the visitor can pick after sharing their name. Define what it asks, what happens when the visitor finishes, and — optionally — which ERPNext DocType and fields it pushes to. No two routes need to look alike.', 'whatomate-chat-widget' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wcw_save_routes" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<div id="wcw-routes-list" data-next-route-index="<?php echo (int) count( $routes ); ?>">
					<?php foreach ( $routes as $r_index => $route ) : ?>
						<?php $this->render_route_row( $r_index, $route ); ?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" id="wcw-add-route" class="button"><?php esc_html_e( '+ Add Route', 'whatomate-chat-widget' ); ?></button>
				</p>

				<?php submit_button( __( 'Save Routes', 'whatomate-chat-widget' ) ); ?>
			</form>

			<!-- Templates cloned by JS when adding a new route / question. Never submitted directly (outside the <form>). -->
			<template id="wcw-route-template">
				<?php $this->render_route_row( '__ROUTE_INDEX__', $this->blank_route(), true ); ?>
			</template>
			<template id="wcw-question-template">
				<?php $this->render_question_row( '__ROUTE_INDEX__', '__Q_INDEX__', $this->blank_question() ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * A blank route/question, used only to render the JS clone templates.
	 */
	private function blank_route() {
		return array(
			'id'           => '',
			'label'        => '',
			'questions'    => array(),
			'final_action' => array( 'type' => 'whatsapp', 'message_template' => '', 'contact_phone' => '', 'contact_email' => '' ),
			'crm'          => array( 'enabled' => false, 'doctype' => '', 'lead_owner' => '', 'field_map' => array(), 'static_fields' => array() ),
		);
	}

	private function blank_question() {
		return array( 'key' => '', 'type' => 'text', 'prompt' => '', 'required' => true );
	}

	/**
	 * Render one route's editable block.
	 *
	 * @param int|string $index      Numeric position in the array, or the
	 *                               literal placeholder string when this is
	 *                               being rendered inside the JS <template>.
	 * @param array      $route      The route data.
	 * @param bool       $is_template Whether this is the hidden template copy.
	 */
	private function render_route_row( $index, $route, $is_template = false ) {
		$name_prefix = 'wcw_routes[' . $index . ']';
		?>
		<div class="wcw-route-row" style="border:1px solid #dcdcde;background:#fff;padding:16px 20px;margin-bottom:16px;">
			<div class="wcw-route-header-row" style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
				<input type="hidden" class="wcw-route-id" name="<?php echo esc_attr( $name_prefix ); ?>[id]" value="<?php echo esc_attr( $route['id'] ); ?>" />
				<label style="font-weight:600;">
					<?php esc_html_e( 'Route label', 'whatomate-chat-widget' ); ?><br />
					<input type="text" class="regular-text wcw-route-label" name="<?php echo esc_attr( $name_prefix ); ?>[label]" value="<?php echo esc_attr( $route['label'] ); ?>" placeholder="e.g. Sales" />
				</label>
				<button type="button" class="button wcw-remove-route" style="margin-top:22px;"><?php esc_html_e( 'Remove Route', 'whatomate-chat-widget' ); ?></button>
				<button type="button" class="button wcw-move-route-up" style="margin-top:22px;">&uarr;</button>
				<button type="button" class="button wcw-move-route-down" style="margin-top:22px;">&darr;</button>
			</div>

			<h4><?php esc_html_e( 'Questions (asked in order, after Name)', 'whatomate-chat-widget' ); ?></h4>
			<div class="wcw-questions-list" data-next-q-index="<?php echo (int) count( $route['questions'] ); ?>">
				<?php foreach ( $route['questions'] as $q_index => $question ) : ?>
					<?php $this->render_question_row( $index, $q_index, $question ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button wcw-add-question"><?php esc_html_e( '+ Add Question', 'whatomate-chat-widget' ); ?></button></p>

			<h4><?php esc_html_e( 'When the visitor finishes', 'whatomate-chat-widget' ); ?></h4>
			<p>
				<label style="margin-right:16px;">
					<input type="radio" class="wcw-final-type" name="<?php echo esc_attr( $name_prefix ); ?>[final_action][type]" value="whatsapp" <?php checked( 'whatsapp', $route['final_action']['type'] ); ?> />
					<?php esc_html_e( 'Continue on WhatsApp', 'whatomate-chat-widget' ); ?>
				</label>
				<label>
					<input type="radio" class="wcw-final-type" name="<?php echo esc_attr( $name_prefix ); ?>[final_action][type]" value="direct_message" <?php checked( 'direct_message', $route['final_action']['type'] ); ?> />
					<?php esc_html_e( 'Show a direct contact message (no WhatsApp)', 'whatomate-chat-widget' ); ?>
				</label>
			</p>
			<p>
				<label style="display:block;">
					<?php esc_html_e( 'Message template', 'whatomate-chat-widget' ); ?>
					<textarea class="large-text" rows="3" name="<?php echo esc_attr( $name_prefix ); ?>[final_action][message_template]"><?php echo esc_textarea( $route['final_action']['message_template'] ); ?></textarea>
				</label>
				<span class="description"><?php esc_html_e( 'Placeholders: {name}, {label}, plus each question\'s key above (e.g. {phone}, {email}, {requirement}).', 'whatomate-chat-widget' ); ?></span>
			</p>
			<p class="wcw-direct-contact-fields" <?php echo 'direct_message' !== $route['final_action']['type'] ? 'style="display:none;"' : ''; ?>>
				<label style="margin-right:16px;">
					<?php esc_html_e( 'Contact phone', 'whatomate-chat-widget' ); ?><br />
					<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[final_action][contact_phone]" value="<?php echo esc_attr( $route['final_action']['contact_phone'] ); ?>" placeholder="+910000000000" />
				</label>
				<label>
					<?php esc_html_e( 'Contact email', 'whatomate-chat-widget' ); ?><br />
					<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[final_action][contact_email]" value="<?php echo esc_attr( $route['final_action']['contact_email'] ); ?>" placeholder="team@example.com" />
				</label>
			</p>

			<h4><?php esc_html_e( 'CRM (ERPNext)', 'whatomate-chat-widget' ); ?></h4>
			<p>
				<label>
					<input type="checkbox" class="wcw-crm-enabled" name="<?php echo esc_attr( $name_prefix ); ?>[crm][enabled]" value="1" <?php checked( true, ! empty( $route['crm']['enabled'] ) ); ?> />
					<?php esc_html_e( 'Push this route\'s submissions to ERPNext (uses the shared connection configured in Settings)', 'whatomate-chat-widget' ); ?>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'DocType', 'whatomate-chat-widget' ); ?><br />
					<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[crm][doctype]" value="<?php echo esc_attr( $route['crm']['doctype'] ); ?>" placeholder="e.g. Lead, Job Applicant" />
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Lead owner', 'whatomate-chat-widget' ); ?><br />
					<input type="text" class="regular-text" name="<?php echo esc_attr( $name_prefix ); ?>[crm][lead_owner]" value="<?php echo esc_attr( isset( $route['crm']['lead_owner'] ) ? $route['crm']['lead_owner'] : '' ); ?>" placeholder="e.g. sales@example.com" />
				</label>
				<span class="description" style="display:block;"><?php esc_html_e( 'Optional. The ERPNext user (their email) each new lead is assigned to, sent as lead_owner. Must match an existing ERPNext user. Leave blank to let ERPNext decide (usually the API user).', 'whatomate-chat-widget' ); ?></span>
			</p>
			<div class="wcw-crm-field-map">
				<p class="description"><?php esc_html_e( 'Field mapping (rebuilds automatically as you add/rename questions above):', 'whatomate-chat-widget' ); ?></p>
				<table class="widefat" style="max-width:520px;">
					<thead><tr><th><?php esc_html_e( 'Our field', 'whatomate-chat-widget' ); ?></th><th><?php esc_html_e( 'Your DocType field', 'whatomate-chat-widget' ); ?></th></tr></thead>
					<tbody class="wcw-crm-field-map-body">
						<?php
						$map_fields = array_merge(
							array( 'name' => __( 'Name', 'whatomate-chat-widget' ), 'label' => __( 'Route label', 'whatomate-chat-widget' ) ),
							array_combine( wp_list_pluck( $route['questions'], 'key' ), wp_list_pluck( $route['questions'], 'key' ) )
						);
						foreach ( $map_fields as $map_key => $map_label ) :
							$mapped_value = isset( $route['crm']['field_map'][ $map_key ] ) ? $route['crm']['field_map'][ $map_key ] : '';
							?>
							<tr data-map-key="<?php echo esc_attr( $map_key ); ?>">
								<td><?php echo esc_html( $map_label ); ?></td>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr( $name_prefix ); ?>[crm][field_map][<?php echo esc_attr( $map_key ); ?>]" value="<?php echo esc_attr( $mapped_value ); ?>" /></td>
							</tr>
							<?php
						endforeach;
						?>
					</tbody>
				</table>
			</div>
			<?php
			$static_lines = '';
			if ( ! empty( $route['crm']['static_fields'] ) && is_array( $route['crm']['static_fields'] ) ) {
				foreach ( $route['crm']['static_fields'] as $static_field => $static_value ) {
					$static_lines .= $static_field . ' = ' . $static_value . "\n";
				}
			}
			?>
			<p>
				<label style="display:block;">
					<?php esc_html_e( 'Fixed values (sent with every submission)', 'whatomate-chat-widget' ); ?>
					<textarea class="large-text" rows="3" style="max-width:520px;" name="<?php echo esc_attr( $name_prefix ); ?>[crm][static_fields]" placeholder="source = Website"><?php echo esc_textarea( trim( $static_lines ) ); ?></textarea>
				</label>
				<span class="description"><?php esc_html_e( 'One per line, as fieldname = value. Use this for fields your CRM requires but visitors are never asked — for example, a mandatory Source field: source = Website', 'whatomate-chat-widget' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render one question row inside a route.
	 */
	private function render_question_row( $route_index, $q_index, $question ) {
		$name_prefix = 'wcw_routes[' . $route_index . '][questions][' . $q_index . ']';
		?>
		<div class="wcw-question-row" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:8px;">
			<label>
				<?php esc_html_e( 'Field key', 'whatomate-chat-widget' ); ?><br />
				<input type="text" class="wcw-question-key" name="<?php echo esc_attr( $name_prefix ); ?>[key]" value="<?php echo esc_attr( $question['key'] ); ?>" placeholder="e.g. phone" style="width:120px;" />
			</label>
			<label style="flex:1;">
				<?php esc_html_e( 'Prompt shown to the visitor', 'whatomate-chat-widget' ); ?><br />
				<input type="text" class="wcw-question-prompt" name="<?php echo esc_attr( $name_prefix ); ?>[prompt]" value="<?php echo esc_attr( $question['prompt'] ); ?>" style="width:100%;" placeholder="e.g. What's the best number to reach you on?" />
			</label>
			<label>
				<?php esc_html_e( 'Type', 'whatomate-chat-widget' ); ?><br />
				<select name="<?php echo esc_attr( $name_prefix ); ?>[type]">
					<option value="text" <?php selected( 'text', $question['type'] ); ?>><?php esc_html_e( 'Text', 'whatomate-chat-widget' ); ?></option>
					<option value="phone" <?php selected( 'phone', $question['type'] ); ?>><?php esc_html_e( 'Phone', 'whatomate-chat-widget' ); ?></option>
					<option value="email" <?php selected( 'email', $question['type'] ); ?>><?php esc_html_e( 'Email', 'whatomate-chat-widget' ); ?></option>
				</select>
			</label>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[required]" value="1" <?php checked( true, ! empty( $question['required'] ) ); ?> />
				<?php esc_html_e( 'Required', 'whatomate-chat-widget' ); ?>
			</label>
			<button type="button" class="button wcw-move-question-up" title="<?php esc_attr_e( 'Move up', 'whatomate-chat-widget' ); ?>">&uarr;</button>
			<button type="button" class="button wcw-move-question-down" title="<?php esc_attr_e( 'Move down', 'whatomate-chat-widget' ); ?>">&darr;</button>
			<button type="button" class="button wcw-remove-question"><?php esc_html_e( 'Remove', 'whatomate-chat-widget' ); ?></button>
		</div>
		<?php
	}
}
