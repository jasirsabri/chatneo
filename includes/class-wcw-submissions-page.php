<?php
/**
 * Admin page: Submissions. One generic list for every route's submissions —
 * replaces the old separate Leads and Transfers pages, since a submission's
 * shape now depends entirely on which route it came through.
 *
 * @package Whatomate_Chat_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCW_Submissions_Page {

	const PER_PAGE = 20;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_wcw_export_submissions_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_wcw_delete_submission', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_wcw_bulk_delete_submissions', array( $this, 'handle_bulk_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wcw-submissions' ) ) {
			return;
		}
		wp_enqueue_script(
			'wcw-submissions-admin',
			WCW_PLUGIN_URL . 'assets/js/submissions-admin.js',
			array(),
			WCW_VERSION,
			true
		);
	}

	public function add_menu() {
		add_submenu_page(
			'wcw-settings',
			__( 'Submissions', 'whatomate-chat-widget' ),
			__( 'Submissions', 'whatomate-chat-widget' ),
			'manage_options',
			'wcw-submissions',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$route_filter = isset( $_GET['route'] ) ? sanitize_key( wp_unslash( $_GET['route'] ) ) : '';

		$total_items  = WCW_DB::get_total_submissions_count( $route_filter );
		$total_pages  = max( 1, (int) ceil( $total_items / self::PER_PAGE ) );
		$current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$current_page = min( $current_page, $total_pages );
		$offset       = ( $current_page - 1 ) * self::PER_PAGE;

		$submissions = WCW_DB::get_submissions( self::PER_PAGE, $offset, $route_filter );
		$routes      = WCW_Routes::get_routes();

		$export_url = wp_nonce_url(
			add_query_arg( 'route', $route_filter, admin_url( 'admin-post.php?action=wcw_export_submissions_csv' ) ),
			'wcw_export_submissions_csv'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Submissions', 'whatomate-chat-widget' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'whatomate-chat-widget' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['wcw_deleted'] ) && '1' === $_GET['wcw_deleted'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'whatomate-chat-widget' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['wcw_bulk_deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					printf(
						/* translators: %d: number of submissions deleted */
						esc_html__( '%d submission(s) deleted.', 'whatomate-chat-widget' ),
						(int) $_GET['wcw_bulk_deleted']
					);
					?>
				</p></div>
			<?php endif; ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( remove_query_arg( 'route' ) ); ?>" <?php echo '' === $route_filter ? 'class="current"' : ''; ?>><?php esc_html_e( 'All', 'whatomate-chat-widget' ); ?></a></li>
				<?php foreach ( $routes as $route ) : ?>
					| <li><a href="<?php echo esc_url( add_query_arg( 'route', $route['id'] ) ); ?>" <?php echo $route_filter === $route['id'] ? 'class="current"' : ''; ?>><?php echo esc_html( $route['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>

			<p><?php
				/* translators: %d: total number of submissions */
				printf( esc_html__( 'Total: %d', 'whatomate-chat-widget' ), (int) $total_items );
			?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wcw-submissions-form">
				<input type="hidden" name="action" value="wcw_bulk_delete_submissions" />
				<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>" />
				<input type="hidden" name="route" value="<?php echo esc_attr( $route_filter ); ?>" />
				<?php wp_nonce_field( 'wcw_bulk_delete_submissions' ); ?>

				<div class="tablenav top wcw-bulk-actions-bar">
					<button type="submit" class="button" id="wcw-bulk-delete-btn" disabled onclick="return confirm('<?php echo esc_js( __( 'Delete the selected submissions? This cannot be undone.', 'whatomate-chat-widget' ) ); ?>');">
						<?php esc_html_e( 'Delete Selected', 'whatomate-chat-widget' ); ?>
					</button>
				</div>

				<table class="wp-list-table widefat fixed striped wcw-responsive-table">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="wcw-select-all" /></th>
							<th><?php esc_html_e( 'Name', 'whatomate-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Route', 'whatomate-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Answers', 'whatomate-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Ending', 'whatomate-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'CRM', 'whatomate-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Date', 'whatomate-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'whatomate-chat-widget' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $submissions ) ) : ?>
							<tr><td colspan="8"><?php esc_html_e( 'No submissions yet.', 'whatomate-chat-widget' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $submissions as $sub ) : ?>
								<?php
								$answers    = self::decode_answers( $sub->answers );
								$delete_url = wp_nonce_url(
									admin_url( 'admin-post.php?action=wcw_delete_submission&id=' . absint( $sub->id ) . '&paged=' . $current_page ),
									'wcw_delete_submission_' . $sub->id
								);
								?>
								<tr>
									<th class="check-column" scope="row"><input type="checkbox" class="wcw-row-checkbox" name="submission_ids[]" value="<?php echo esc_attr( $sub->id ); ?>" /></th>
									<td data-label="<?php esc_attr_e( 'Name', 'whatomate-chat-widget' ); ?>"><?php echo esc_html( $sub->name ); ?></td>
									<td data-label="<?php esc_attr_e( 'Route', 'whatomate-chat-widget' ); ?>"><?php echo esc_html( $sub->route_label ); ?></td>
									<td data-label="<?php esc_attr_e( 'Answers', 'whatomate-chat-widget' ); ?>">
										<?php
										$parts = array();
										foreach ( $answers as $k => $v ) {
											if ( '' !== $v ) {
												$parts[] = esc_html( $k ) . ': ' . esc_html( $v );
											}
										}
										echo wp_kses_post( implode( '<br>', $parts ) );
										?>
									</td>
									<td data-label="<?php esc_attr_e( 'Ending', 'whatomate-chat-widget' ); ?>"><?php echo esc_html( $sub->final_action_type ); ?></td>
									<td data-label="<?php esc_attr_e( 'CRM', 'whatomate-chat-widget' ); ?>">
										<?php if ( 'whatsapp' !== $sub->final_action_type ) : ?>
											&#8212;
										<?php elseif ( ! empty( $sub->crm_synced ) ) : ?>
											<span style="color:#1a7a1a;font-weight:600;" title="<?php esc_attr_e( 'Pushed to CRM successfully', 'whatomate-chat-widget' ); ?>">✓</span>
										<?php elseif ( ! empty( $sub->crm_sync_error ) ) : ?>
											<span style="color:#b32d2e;font-weight:600;cursor:help;" title="<?php echo esc_attr( $sub->crm_sync_error ); ?>">✗</span>
										<?php else : ?>
											&#8212;
										<?php endif; ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Date', 'whatomate-chat-widget' ); ?>"><?php echo esc_html( get_date_from_gmt( $sub->created_at, 'Y-m-d H:i' ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Actions', 'whatomate-chat-widget' ); ?>">
										<a href="<?php echo esc_url( $delete_url ); ?>" style="color:#b32d2e;" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission? This cannot be undone.', 'whatomate-chat-widget' ) ); ?>');">
											<?php esc_html_e( 'Delete', 'whatomate-chat-widget' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'total'   => $total_pages,
								'current' => $current_page,
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function decode_answers( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'whatomate-chat-widget' ) );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wcw_delete_submission_' . $id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'whatomate-chat-widget' ) );
		}

		WCW_DB::delete_submission( $id );

		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$redirect = add_query_arg(
			array( 'page' => 'wcw-submissions', 'paged' => $paged, 'wcw_deleted' => '1' ),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_bulk_delete() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wcw_bulk_delete_submissions' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'whatomate-chat-widget' ) );
		}

		$ids = isset( $_POST['submission_ids'] ) && is_array( $_POST['submission_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['submission_ids'] ) )
			: array();

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( $id && WCW_DB::delete_submission( $id ) ) {
				++$deleted;
			}
		}

		$paged        = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
		$route_filter = isset( $_POST['route'] ) ? sanitize_key( wp_unslash( $_POST['route'] ) ) : '';

		$redirect = add_query_arg(
			array_filter(
				array(
					'page'             => 'wcw-submissions',
					'paged'            => $paged,
					'route'            => $route_filter,
					'wcw_bulk_deleted' => $deleted,
				)
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wcw_export_submissions_csv' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'whatomate-chat-widget' ) );
		}

		$route_filter = isset( $_GET['route'] ) ? sanitize_key( wp_unslash( $_GET['route'] ) ) : '';
		$submissions  = WCW_DB::get_all_submissions( $route_filter );

		// The CSV's answer columns are the union of every answer key
		// present across the exported rows, since different routes
		// collect different fields — a Sales row and a Careers row won't
		// share the same shape, and the export needs to accommodate both.
		$all_keys = array();
		$decoded_rows = array();
		foreach ( $submissions as $sub ) {
			$answers          = self::decode_answers( $sub->answers );
			$decoded_rows[]   = $answers;
			$all_keys         = array_unique( array_merge( $all_keys, array_keys( $answers ) ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=chatneo-submissions-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array_merge( array( 'Route', 'Name' ), $all_keys, array( 'Ending', 'CRM Synced', 'CRM Error', 'Message Sent', 'Page URL', 'Date (UTC)' ) ) );

		foreach ( $submissions as $i => $sub ) {
			$row = array( $sub->route_label, $sub->name );
			foreach ( $all_keys as $key ) {
				$row[] = isset( $decoded_rows[ $i ][ $key ] ) ? $decoded_rows[ $i ][ $key ] : '';
			}
			$row[] = $sub->final_action_type;
			$row[] = ! empty( $sub->crm_synced ) ? 'Yes' : 'No';
			$row[] = $sub->crm_sync_error;
			$row[] = $sub->outcome_message;
			$row[] = $sub->page_url;
			$row[] = $sub->created_at;
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
