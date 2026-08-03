<?php
namespace CatFolder_Document_Gallery\Engine;

use CatFolder_Document_Gallery\Utils\SingletonTrait;

class Settings {

	use SingletonTrait;

	const POST_TYPE   = 'catfolder-post-type';
	const AJAX_ACTION = 'catf_dg_save_settings';

	/**
	 * Option holding the folder ids that are NOT allowed to be requested from the
	 * frontend AJAX endpoints (catfdoc-reload-table / catfdoc-search-data).
	 *
	 * A blocklist (instead of an allowlist) keeps the default behaviour "every
	 * folder is allowed", so existing sites and folders created later keep working
	 * until the admin explicitly turns one off.
	 */
	const BLOCKED_FOLDERS_OPTION = 'catf_dg_frontend_blocked_folders';

	private function __construct() {
		add_action( 'admin_footer-edit.php', array( $this, 'inject_settings_ui' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_save' ) );
	}

	/**
	 * Folder ids the admin has turned off for the frontend endpoints.
	 *
	 * @return int[]
	 */
	public static function get_blocked_folders() {
		$blocked = get_option( self::BLOCKED_FOLDERS_OPTION, array() );

		if ( ! is_array( $blocked ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'intval', $blocked ) ) );
	}

	/**
	 * All attachment folders, keyed by id.
	 *
	 * @return array<int, object> id => { id, title, parent }
	 */
	private static function get_folders() {
		static $folders = null;

		if ( null !== $folders ) {
			return $folders;
		}

		global $wpdb;

		$folders = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT id, title, parent FROM {$wpdb->prefix}catfolders WHERE type = 'attachment' ORDER BY ord ASC, title ASC" );

		foreach ( (array) $rows as $row ) {
			$row->id        = (int) $row->id;
			$row->parent    = (int) $row->parent;
			$folders[ $row->id ] = $row;
		}

		return $folders;
	}

	/**
	 * Is this folder allowed to be served by the frontend endpoints?
	 *
	 * A folder is blocked when it is turned off itself, or when any of its
	 * ancestors is turned off (turning off a parent hides the whole branch).
	 */
	public static function is_folder_allowed( $folder_id ) {
		$folder_id = (int) $folder_id;
		$blocked   = self::get_blocked_folders();

		// Nothing blocked, or no real folder requested: keep the default behaviour.
		if ( empty( $blocked ) || $folder_id <= 0 ) {
			return true;
		}

		$folders = self::get_folders();
		$seen    = array();

		while ( $folder_id > 0 && ! isset( $seen[ $folder_id ] ) ) {
			if ( in_array( $folder_id, $blocked, true ) ) {
				return false;
			}

			$seen[ $folder_id ] = true;

			if ( ! isset( $folders[ $folder_id ] ) ) {
				break;
			}

			$folder_id = $folders[ $folder_id ]->parent;
		}

		return true;
	}

	/**
	 * True only when every requested folder is allowed.
	 *
	 * @param array $folder_ids Decrypted folder ids.
	 */
	public static function are_folders_allowed( $folder_ids ) {
		foreach ( (array) $folder_ids as $folder_id ) {
			if ( ! self::is_folder_allowed( $folder_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build the shared nav-tab bar. $current is 'list' or 'settings'.
	 */
	public function render_nav_tabs( $current ) {
		$tabs = array(
			'list'     => array(
				'label' => __( 'Document Gallery', 'catfolders-document-gallery' ),
				'url'   => add_query_arg( 'post_type', self::POST_TYPE, admin_url( 'edit.php' ) ),
			),
			'settings' => array(
				'label' => __( 'Settings', 'catfolders-document-gallery' ),
				'url'   => '#catf-dg-settings',
			),
		);

		$html = '<nav class="nav-tab-wrapper wp-clearfix catf-dg-tabs">';
		foreach ( $tabs as $key => $tab ) {
			$html .= sprintf(
				'<a href="%s" class="nav-tab%s" data-catf-tab="%s">%s</a>',
				esc_url( $tab['url'] ),
				$key === $current ? ' nav-tab-active' : '',
				esc_attr( $key ),
				esc_html( $tab['label'] )
			);
		}
		$html .= '</nav>';

		return $html;
	}

	/**
	 * Markup for the inline settings panel (hidden until the tab is selected).
	 */
	public function render_settings_panel() {
		$allowed_roles  = PostType::get_instance()->get_allowed_roles();
		$editable_roles = get_editable_roles();

		ob_start();
		?>
		<div class="wrap catf-dg-settings-panel" style="display:none;">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Document Gallery Settings', 'catfolders-document-gallery' ); ?></h1>

			<form class="catf-dg-settings-form" method="post">
				<?php wp_nonce_field( self::AJAX_ACTION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Allowed User Roles', 'catfolders-document-gallery' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $editable_roles as $role_slug => $role_details ) : ?>
									<?php $is_admin_role = ( 'administrator' === $role_slug ); ?>
									<label style="display:block;margin-bottom:8px;">
										<input
											type="checkbox"
											name="catf_dg_allowed_roles[]"
											value="<?php echo esc_attr( $role_slug ); ?>"
											<?php checked( $is_admin_role || in_array( $role_slug, $allowed_roles, true ) ); ?>
											<?php disabled( $is_admin_role ); ?>
										/>
										<?php echo esc_html( translate_user_role( $role_details['name'] ) ); ?>
									</label>
									<?php if ( $is_admin_role ) : ?>
										<input type="hidden" name="catf_dg_allowed_roles[]" value="administrator" />
									<?php endif; ?>
								<?php endforeach; ?>
							</fieldset>
							<p class="description">
								<?php echo esc_html__( 'Administrators always have access. Select which other roles can view and manage the Document Gallery.', 'catfolders-document-gallery' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Folders Allowed on the Frontend', 'catfolders-document-gallery' ); ?></th>
						<td>
							<?php echo $this->render_frontend_folders_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Changes', 'catfolders-document-gallery' ) ); ?>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Checkbox tree of every folder, checked = usable by the frontend endpoints.
	 */
	private function render_frontend_folders_field() {
		$folders = self::get_folders();

		$description = sprintf(
			/* translators: %s: list of the frontend endpoint names. */
			esc_html__( 'Uncheck a folder to stop the frontend from loading or downloading its files through the Document Gallery AJAX endpoints. Unchecking a folder also blocks all of its subfolders. All folders are enabled by default; folders you create later are enabled too.', 'catfolders-document-gallery' ),
		);

		if ( empty( $folders ) ) {
			return '<p class="description">' . esc_html__( 'No CatFolders folders found yet.', 'catfolders-document-gallery' ) . '</p>';
		}

		// Group by parent; folders whose parent no longer exists are treated as roots.
		$children_map = array();
		foreach ( $folders as $folder ) {
			$parent = isset( $folders[ $folder->parent ] ) ? $folder->parent : 0;
			$children_map[ $parent ][] = $folder;
		}

		$html  = '<fieldset class="catf-dg-folders-field">';
		$html .= '<p class="catf-dg-folders-bulk">';
		$html .= '<button type="button" class="button button-small" data-catf-folders="all">' . esc_html__( 'Enable all', 'catfolders-document-gallery' ) . '</button> ';
		$html .= '<button type="button" class="button button-small" data-catf-folders="none">' . esc_html__( 'Disable all', 'catfolders-document-gallery' ) . '</button>';
		$html .= '</p>';
		// Roots have no parent to inherit a disabled state from.
		$html .= $this->render_folder_branch( $children_map, 0, true );
		$html .= '<input type="hidden" name="catf_dg_all_folder_ids" value="' . esc_attr( implode( ',', array_keys( $folders ) ) ) . '" />';
		$html .= '</fieldset>';
		$html .= '<p class="description">' . $description . '</p>';
		return $html;
	}

	/**
	 * Recursively render one level of the folder checkbox tree.
	 */
	private function render_folder_branch( $children_map, $parent_id, $parent_allowed ) {
		if ( empty( $children_map[ $parent_id ] ) ) {
			return '';
		}

		$blocked = self::get_blocked_folders();

		$html = '<ul class="catf-dg-folder-tree">';
		foreach ( $children_map[ $parent_id ] as $folder ) {
			// Show the effective state: a folder inside a disabled branch reads as
			// disabled even if it was never unchecked itself (e.g. created later).
			$allowed = $parent_allowed && ! in_array( $folder->id, $blocked, true );

			$html .= '<li>';
			$html .= '<label><input type="checkbox" name="catf_dg_frontend_folders[]" value="' . esc_attr( $folder->id ) . '" ' . checked( $allowed, true, false ) . ' /> ' . esc_html( $folder->title ) . '</label>';
			$html .= $this->render_folder_branch( $children_map, $folder->id, $allowed );
			$html .= '</li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * On the post type list screen, inject the nav tabs + settings panel next to
	 * .wrap and wire up tab switching and AJAX save.
	 */
	public function inject_settings_ui() {
		$screen = get_current_screen();

		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nav   = $this->render_nav_tabs( 'list' );
		$panel = $this->render_settings_panel();
		?>
		<style>
			.catf-dg-tabs.nav-tab-wrapper {
				display: flex;
				gap: 32px;
				border: 0;
				border-bottom: 1px solid #dcdcde;
				margin: 0 0 20px;
				padding: 0 4px;
			}
			.catf-dg-tabs .nav-tab {
				float: none;
				margin: 0;
				padding: 14px 2px;
				border: 0;
				background: none;
				border-radius: 0;
				font-size: 15px;
				font-weight: 500;
				line-height: 1.4;
				color: #646970;
				box-shadow: none;
				transition: color 0.15s ease, box-shadow 0.15s ease;
			}
			.catf-dg-tabs .nav-tab:hover,
			.catf-dg-tabs .nav-tab:focus {
				color: #2271b1;
				box-shadow: none;
			}
			.catf-dg-tabs .nav-tab-active,
			.catf-dg-tabs .nav-tab-active:hover,
			.catf-dg-tabs .nav-tab-active:focus {
				color: #1d2327;
				font-weight: 600;
				background: none;
				box-shadow: inset 0 -3px 0 0 #2271b1;
			}
			.catf-dg-tabs .nav-tab:focus {
				outline: none;
				box-shadow: inset 0 -3px 0 0 #2271b1;
			}
			.catf-dg-toast-wrap {
				position: fixed;
				right: 24px;
				bottom: 24px;
				z-index: 100000;
				display: flex;
				flex-direction: column;
				gap: 10px;
			}
			.catf-dg-toast {
				min-width: 240px;
				max-width: 360px;
				padding: 12px 16px;
				border-radius: 6px;
				color: #fff;
				font-size: 13px;
				line-height: 1.5;
				box-shadow: 0 4px 14px rgba( 0, 0, 0, 0.18 );
				opacity: 0;
				transform: translateY( 10px );
				transition: opacity 0.25s ease, transform 0.25s ease;
			}
			.catf-dg-toast.is-visible {
				opacity: 1;
				transform: translateY( 0 );
			}
			.catf-dg-folders-field {
				max-width: 520px;
			}
			.catf-dg-folders-bulk {
				margin: 8px 0 !important;
			}
			.catf-dg-folder-tree {
				margin: 0;
				padding: 0;
				list-style: none;
			}
			.catf-dg-folder-tree .catf-dg-folder-tree {
				margin-left: 22px;
				padding-left: 8px;
				border-left: 1px solid #dcdcde;
			}
			.catf-dg-folder-tree label {
				display: block;
				padding: 3px 0;
			}
			.catf-dg-folders-field > .catf-dg-folder-tree {
				max-height: 320px;
				overflow: auto;
				padding: 10px 12px;
				border: 1px solid #dcdcde;
				background: #fff;
				border-radius: 4px;
			}
			.catf-dg-toast.is-success { background: #1a7f37; }
			.catf-dg-toast.is-error   { background: #b32d2e; }
		</style>
		<script>
			( function () {
				var wrap = document.querySelector( '.wrap' );
				if ( ! wrap ) {
					return;
				}

				function build( html ) {
					var holder = document.createElement( 'div' );
					holder.innerHTML = html;
					return holder.firstElementChild;
				}

				var nav   = build( <?php echo wp_json_encode( $nav ); ?> );
				var panel = build( <?php echo wp_json_encode( $panel ); ?> );

				// Insert as siblings, before the list .wrap.
				wrap.parentNode.insertBefore( nav, wrap );
				wrap.parentNode.insertBefore( panel, wrap );

				var tabs = nav.querySelectorAll( '.nav-tab' );

				function activate( name ) {
					tabs.forEach( function ( t ) {
						t.classList.toggle( 'nav-tab-active', t.getAttribute( 'data-catf-tab' ) === name );
					} );
					var settings = ( name === 'settings' );
					wrap.style.display  = settings ? 'none' : '';
					panel.style.display = settings ? '' : 'none';
				}

				tabs.forEach( function ( t ) {
					t.addEventListener( 'click', function ( e ) {
						e.preventDefault();
						var name = t.getAttribute( 'data-catf-tab' );
						activate( name );
						if ( name === 'settings' ) {
							history.replaceState( null, '', '#catf-dg-settings' );
						} else {
							history.replaceState( null, '', location.pathname + location.search );
						}
					} );
				} );

				if ( location.hash === '#catf-dg-settings' ) {
					activate( 'settings' );
				}

				// Toast notifications.
				function showToast( message, type ) {
					var container = document.querySelector( '.catf-dg-toast-wrap' );
					if ( ! container ) {
						container = document.createElement( 'div' );
						container.className = 'catf-dg-toast-wrap';
						document.body.appendChild( container );
					}

					var toast = document.createElement( 'div' );
					toast.className = 'catf-dg-toast is-' + ( type === 'error' ? 'error' : 'success' );
					toast.textContent = message;
					container.appendChild( toast );

					requestAnimationFrame( function () {
						toast.classList.add( 'is-visible' );
					} );

					setTimeout( function () {
						toast.classList.remove( 'is-visible' );
						setTimeout( function () { toast.remove(); }, 300 );
					}, 3000 );
				}

				// Folder tree: toggling a folder cascades to its subfolders, and checking
				// a subfolder re-checks its parents (a blocked parent blocks the branch).
				var foldersField = panel.querySelector( '.catf-dg-folders-field' );

				if ( foldersField ) {
					foldersField.addEventListener( 'change', function ( e ) {
						var box = e.target;
						if ( box.type !== 'checkbox' ) {
							return;
						}

						var item = box.closest( 'li' );

						item.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( child ) {
							child.checked = box.checked;
						} );

						if ( box.checked ) {
							var parent = item.parentNode.closest( 'li' );
							while ( parent ) {
								parent.querySelector( 'input[type="checkbox"]' ).checked = true;
								parent = parent.parentNode.closest( 'li' );
							}
						}
					} );

					foldersField.querySelectorAll( '[data-catf-folders]' ).forEach( function ( btn ) {
						btn.addEventListener( 'click', function () {
							var checked = btn.getAttribute( 'data-catf-folders' ) === 'all';
							foldersField.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( box ) {
								box.checked = checked;
							} );
						} );
					} );
				}

				// AJAX save.
				var form = panel.querySelector( '.catf-dg-settings-form' );

				form.addEventListener( 'submit', function ( e ) {
					e.preventDefault();

					var btn = form.querySelector( '[type="submit"]' );
					btn.disabled = true;

					var data = new FormData( form );
					data.append( 'action', <?php echo wp_json_encode( self::AJAX_ACTION ); ?> );

					fetch( ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: data
					} )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							btn.disabled = false;
							var msg = ( res.data && res.data.message ) ? res.data.message : '';
							showToast( msg, res.success ? 'success' : 'error' );
						} )
						.catch( function () {
							btn.disabled = false;
							showToast( <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'catfolders-document-gallery' ) ); ?>, 'error' );
						} );
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * Persist the allowed roles and re-sync capabilities. AJAX endpoint.
	 */
	public function ajax_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'catfolders-document-gallery' ) ), 403 );
		}
		check_ajax_referer( self::AJAX_ACTION );

		$submitted      = isset( $_POST['catf_dg_allowed_roles'] ) ? (array) wp_unslash( $_POST['catf_dg_allowed_roles'] ) : array();
		$submitted      = array_map( 'sanitize_key', $submitted );
		$editable_roles = array_keys( get_editable_roles() );

		// Only keep real, existing roles; administrator is always included.
		$allowed_roles = array_values( array_intersect( $submitted, $editable_roles ) );
		$allowed_roles = array_unique( array_merge( array( 'administrator' ), $allowed_roles ) );

		update_option( PostType::ALLOWED_ROLES_OPTION, $allowed_roles );

		// Frontend folders: store the ids that were rendered but left unchecked, so
		// folders created after this save stay allowed by default.
		if ( isset( $_POST['catf_dg_all_folder_ids'] ) ) {
			$all_ids = array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['catf_dg_all_folder_ids'] ) ) ) ) );
			$enabled = isset( $_POST['catf_dg_frontend_folders'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['catf_dg_frontend_folders'] ) ) : array();
			$blocked = array_values( array_diff( $all_ids, $enabled ) );

			update_option( self::BLOCKED_FOLDERS_OPTION, $blocked );
		}

		// Re-apply capabilities so the change takes effect immediately.
		PostType::get_instance()->sync_capabilities();

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'catfolders-document-gallery' ) ) );
	}
}
