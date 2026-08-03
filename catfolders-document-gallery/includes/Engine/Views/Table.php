<?php
/** @var array $attributes */

use CatFolder_Document_Gallery\Helpers\Helper;
use CatFolder_Document_Gallery\Helpers\FolderHierarchy;

// $data    = Helper::get_attachments( $attributes );
$columns = Helper::generate_columns( $attributes['displayColumns'] );
$columns = apply_filters( 'catf_dg_table_columns', $columns, $attributes );

$allowedTitleTags   = array( 'p', 'h1', 'h2', 'h3', 'h4' );
$libraryTitleTag    = in_array( $attributes['titleTag'], $allowedTitleTags, true ) ? $attributes['titleTag'] : 'p';
$libraryTitle       = $attributes['title'];
$libraryIconAltText = $attributes['libraryIcon']['altText'];
$gridColumn         = $attributes['gridColumn'];

$is_display_title = $attributes['displayTitle'];
$is_display_icon  = $attributes['libraryIcon']['display'];

$libraryType   = $attributes['libraryType'];
$showBreadCrumb = $attributes['showBreadCrumb'];
$isNestedFolders = $attributes['isNestedFolders'];
$searchScope = $attributes['searchScope'];
$isHierarchicalFolders = ($libraryType == 'hierarchical_folders');

global $wpdb;

$json_attributes            = $attributes;
$json_attributes['folders'] = array_map( array( Helper::class, 'encrypt' ), $attributes['folders'] );

// Signed scope of this gallery instance. The frontend sends it back on every
// AJAX call so the endpoints authorize requests against the folders actually
// published here, instead of trusting a client-supplied (and forgeable) id.
$folders_token                   = Helper::sign_folders( $attributes['folders'] );
$json_attributes['foldersToken'] = $folders_token;
?>
<div id="cf-app" class="cf-app" data-json="<?php echo esc_attr( wp_json_encode( $json_attributes ) ); ?>" data-columns="<?php echo esc_attr( wp_json_encode( $columns ) ); ?>">
	<div class="cf-main">
		<div class="cf-container">
			<?php if ( $is_display_icon || $is_display_title ) : ?>
				<<?php echo esc_html( $libraryTitleTag ); ?> class="cf-title">
					<img src="<?php echo esc_url( CATF_DG_IMAGES . 'icons/icon-folders.svg' ); ?>" alt=""<?php echo esc_attr( $libraryIconAltText ); ?>/>
					<span><?php echo esc_html( $libraryTitle ); ?></span>
				</<?php echo esc_html( $libraryTitleTag ); ?>>
			<?php endif; ?>

			<?php
			if($isHierarchicalFolders && $showBreadCrumb) {
				$cfdoc_folder_hierarchy = new FolderHierarchy($wpdb);
				$selected_folder_id = (isset($attributes['folders']) && is_array($attributes['folders'])) ? (int)$attributes['folders'][0] : 0;
				if($selected_folder_id > 0) {
					echo $cfdoc_folder_hierarchy->render_hierarchy($selected_folder_id);
				}
			}
			if($isHierarchicalFolders && $isNestedFolders) {
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
