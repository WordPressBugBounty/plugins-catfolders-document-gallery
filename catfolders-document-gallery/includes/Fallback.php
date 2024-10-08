<?php

defined( 'ABSPATH' ) || exit;
add_action(
	'admin_notices',
	function() {
		if ( current_user_can( 'activate_plugins' ) ) {
			?>
				<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'It looks like you have another CatFolders Document Gallery version installed, please delete it before activating this new version. All of the settings and data are still preserved.', 'catfolders-document-gallery' ); ?>
					<a href="#"><?php esc_html_e( 'Read more details.', 'catfolders-document-gallery' ); ?></a>
					</strong>
				</p>
				</div>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['activate'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				unset( $_GET['activate'] );
			}
		}
	}
);
