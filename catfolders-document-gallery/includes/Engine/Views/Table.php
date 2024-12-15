<?php
use CatFolder_Document_Gallery\Helpers\Helper;
use CatFolder_Document_Gallery\Helpers\FolderHierarchy;

$data    = Helper::get_attachments( $attributes );
$columns = Helper::generate_columns( $attributes['displayColumns'] );

$libraryTitleTag    = $attributes['titleTag'];
$libraryTitle       = $attributes['title'];
$libraryIconAltText = $attributes['libraryIcon']['altText'];
$gridColumn         = $attributes['gridColumn'];

$is_display_title = $attributes['displayTitle'];
$is_display_icon  = $attributes['libraryIcon']['display'];

$libraryType   = $attributes['libraryType'];
$showBreadCrumb = $attributes['showBreadCrumb'];
$isNestedFolders = $attributes['isNestedFolders'];
$searchScope = $attributes['searchScope'];
$is_hierarchical_folders = ($libraryType == 'hierarchical_folders');

global $wpdb;
?>
<div id="cf-app" class="cf-app" data-json="<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>" data-columns="<?php echo esc_attr( wp_json_encode( $columns ) ); ?>">
	<div class="cf-main">
		<div class="cf-container">
			<?php if ( $is_display_icon || $is_display_title ) : ?>
				<<?php echo esc_html( $libraryTitleTag ); ?> class="cf-title">
					<img src="<?php echo esc_url( CATF_DG_IMAGES . 'icons/icon-folders.svg' ); ?>" alt=""<?php echo esc_attr( $libraryIconAltText ); ?>/>
					<span><?php echo esc_html( $libraryTitle ); ?></span>
				</<?php echo esc_html( $libraryTitleTag ); ?>>
			<?php endif; ?>

			<?php
			if($is_hierarchical_folders && $showBreadCrumb) {
				$cfdoc_folder_hierarchy = new FolderHierarchy($wpdb);
				$selected_folder_id = (isset($attributes['folders']) && is_array($attributes['folders'])) ? (int)$attributes['folders'][0] : 0;
				if($selected_folder_id > 0) {
					echo $cfdoc_folder_hierarchy->render_hierarchy($selected_folder_id);
				}
			}
			if($is_hierarchical_folders && $isNestedFolders) {
				if(!isset($cfdoc_folder_hierarchy)) {
					$cfdoc_folder_hierarchy = new FolderHierarchy($wpdb);
				}
				$selected_folder_id = (isset($attributes['folders']) && is_array($attributes['folders'])) ? (int)$attributes['folders'][0] : 0;
				if($selected_folder_id > 0) {
					echo $cfdoc_folder_hierarchy->get_lv1_children( $selected_folder_id );
				}
			}

			echo '<div class="cf-table-my-wrap">';
			echo Helper::render_table_html( $attributes );
			echo '</div>';
			?>
		</div>
	</div>
</div>
