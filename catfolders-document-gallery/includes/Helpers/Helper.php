<?php
namespace CatFolder_Document_Gallery\Helpers;

use CatFolder_Document_Gallery\Engine\Thumbnail\Thumbnail;
use CatFolder_Document_Gallery\Utils\SingletonTrait;

class Helper {

	use SingletonTrait;

	protected function __construct() {}

	public static function get_folder_detail( $folder_id, $select = '*' ) {
		global $wpdb;
		$folders = $wpdb->get_results("SELECT $select FROM " . $wpdb->prefix . "catfolders WHERE `id` = " . intval( $folder_id ));
		return $folders ? $folders[0] : null;
	}
	public static function get_available_type( $file_type ) {
		$file_type = strtolower( $file_type );

		$types = array( 'doc', 'jpg', 'mp4', 'other', 'pdf', 'ppt', 'wav', 'xls', 'zip' );
		if ( in_array( $file_type, $types, true ) ) {
			return $file_type;
		}

		$img_types = array( 'tif', 'tiff', 'jpeg', 'png', 'bmp', 'ithmb', 'gif', 'eps', 'raw', 'cr2', 'nef', 'orf', 'sr2', 'apng', 'avif', 'jfif', 'pjpeg', 'pjp', 'svg', 'ico', 'webp', 'psd', 'wbmp' );
		if ( in_array( $file_type, $img_types, true ) ) {
			return 'jpg';
		}

		$audio_types = array( 'mp3', 'aac', 'aif', 'aifc', 'aiff', 'au', 'flac', 'm4a', 'mid', 'm4b', 'm4p', 'm4r', 'oga', 'ogg', 'opus', 'ra', 'ram', 'spx', 'wm' );
		if ( in_array( $file_type, $audio_types, true ) ) {
			return 'wav';
		}

		$video_types = array( '3gp', '3gpp', '3gpp2', '3g2', 'asf', 'avi', 'dv', 'dvi', 'flv', 'm2t', 'm4v', 'mkv', 'mov', 'mpeg', 'mpg', 'mts', 'ogv', 'ogx', 'rm', 'rmvb', 'ts', 'vob', 'webm', 'wm' );
		if ( in_array( $file_type, $video_types, true ) ) {
			return 'mp4';
		}

		$ppts_types = array( 'pptx', 'ppthtml', 'pptm', 'pptxml', 'prn', 'ps', 'pps', 'ppsx', 'pwz', 'rtf', 'tab', 'template', 'tsv', 'vdx', 'vsd', 'vss', 'vst', 'vsx', 'vtx' );
		if ( in_array( $file_type, $ppts_types, true ) ) {
			return 'ppt';
		}

		$xls_types = array( 'xlsx', 'csv', 'wpd', 'wps', 'xdp', 'xdf', 'xlam', 'xll', 'xlr', 'xlsb', 'xlsm', 'xltm', 'xltx', 'xps', 'wbk', 'wpd', 'wi' );
		if ( in_array( $file_type, $xls_types, true ) ) {
			return 'xls';
		}

		$docs_types = array( 'docx', 'dochtml', 'docm', 'docxml', 'odt', 'dot', 'dothtml', 'dotm', 'dotx', 'eps', 'fdf', 'key', 'keynote', 'kth', 'mpp', 'mpt', 'mpx', 'mpd', 'txt' );
		if ( in_array( $file_type, $docs_types, true ) ) {
			return 'doc';
		}

		$zip_types = array( 'zip', 'rar', 'taz', 'gzip', 'tar.bz2', 'tar.gz' );
		if ( in_array( $file_type, $zip_types, true ) ) {
			return 'zip';
		}

		return 'other';
	}

	public static function get_attachments( $args, $get_children_folders = false ) {
		$selectedFolders = isset( $args['folders'] ) ? array_map( 'intval', $args['folders'] ) : array();
		$columns         = self::generate_columns( $args['displayColumns'] );

		if ( ! $selectedFolders ) {
			return array(
				'files'       => array(),
				'foundPosts'  => 0,
				'maxNumPages' => 0,
				'columns'     => $columns,
			);
		}
		global $wpdb;
		$search      = $args['searchValue'] ?? '';
		$search      = $wpdb->esc_like( $search );
		$limit       = apply_filters( 'catf_dg_posts_per_page', 1000 );
		$currentPage = isset( $args['currentPage'] ) ? intval( $args['currentPage'] ) : 1;
		remove_all_filters( 'pre_get_posts' );
		$ids          = $selectedFolders;

		if( $get_children_folders ) {
			$children_ids = self::get_children_folders_ids($ids);
			$ids = array_merge($ids, $children_ids);
		}

		$where_args[] = '`folder_id` IN (' . implode( ',', $ids ) . ')';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$in_not_in = $wpdb->get_col( "SELECT `post_id` FROM {$wpdb->prefix}catfolders_posts" . ' WHERE ' . implode( ' AND ', $where_args ) );
		if ( ! $in_not_in ) {
			return array(
				'files'       => array(),
				'foundPosts'  => 0,
				'maxNumPages' => 0,
				'columns'     => $columns,
			);
		}

		$queryArgs = array(
			'post_type'      => 'attachment',
			'post__in'       => $in_not_in,
			'orderby'        => array(
				'ID' => 'DESC',
			),
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			's'              => $search,
			'offset'         => ( $currentPage - 1 ) * $limit,
		);
		// if( ! empty( $search ) ) {
		// 	//search by size
		// 	$queryArgs['meta_query'] = array(
		// 		array(
		// 			'key' => 'catf_filesize',
		// 			'value' => $search,
		// 			'compare' => 'LIKE',
		// 		),
		// 	);
		// }
		// if( isset( $args['orderBy'] ) && isset( $args['orderType'] ) ) {
		// 	$queryArgs['orderby'] = $args['orderBy'];
		// 	$queryArgs['order'] = $args['orderType'];// ASC or DESC

		// 	if( $args['orderBy'] === 'updated' ) {
		// 		$queryArgs['orderby'] = 'modified';
		// 	}
		// 	if( $args['orderBy'] === 'counter' ) {
		// 		//order by meta key cf_download_count
		// 		$queryArgs['orderby'] = 'meta_value_num';
		// 		$queryArgs['meta_key'] = 'cf_download_count';
		// 	}
		// 	if( $args['orderBy'] === 'size' ) {
		// 		$queryArgs['orderby'] = 'meta_value_num';
		// 		$queryArgs['meta_key'] = 'catf_filesize';
		// 	}
		// }
		$queryArgs = apply_filters( 'catf_dg_query_args', $queryArgs );
		$query = new \WP_Query( $queryArgs );
		$posts = $query->get_posts();

		$files = array();
		foreach ( $posts as $post ) {
			$size  = \get_post_meta( $post->ID, 'catf_filesize', true );
			$url   = \wp_get_attachment_url( $post->ID );
			$type  = \wp_check_filetype( strtok( $url, '?' ) );
			$image = Thumbnail::get_thumbnail( $post->ID );
			$counter = self::get_download_count($post->ID);
			$file  = array(
				'document_id' => $post->ID,
				'title'    => $post->post_title,
				'type'     => $type['ext'],
				'size'     => ! empty( $size ) ? \size_format( $size ) : '',
				'url'      => $url,
				'link'     => $url,
				'alt'      => $post->post_excerpt,
				'modified' => wp_date( 'M d, Y', strtotime( $post->post_modified ) ),
				'updated' => wp_date( 'M d, Y', strtotime( $post->post_modified ) ),
				'image'    => $image,
				'counter' => $counter,
			);

			$files[] = $file;
		}

		return array(
			'files'       => $files,
			'foundPosts'  => $query->found_posts,
			'maxNumPages' => $query->max_num_pages,
			'columns'     => $columns,
		);
	}

	public static function get_children_folders_ids($ids){
		global $wpdb;
		
		// If $ids is empty, return empty array
		if (empty($ids)) {
			return array();
		}
		
		// Convert to array if it's not already
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		
		// Sanitize IDs to prevent SQL injection
		$ids = array_map('intval', $ids);
		$ids_string = implode(',', $ids);
		
		// Get all children folders recursively
		$children_ids = array();
		$current_parents = $ids;
		
		while (!empty($current_parents)) {
			// Get direct children of current parents
			$query = $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}catfolders 
				WHERE parent IN (" . str_repeat('%d,', count($current_parents) - 1) . "%d)
				AND type = 'attachment' 
				AND created_by = %d",
				array_merge($current_parents, array(apply_filters('catf_folder_created_by', 0)))
			);
			
			$direct_children = $wpdb->get_col($query);
			
			if (empty($direct_children)) {
				break; // No more children found
			}
			
			// Add these children to our result
			$children_ids = array_merge($children_ids, $direct_children);
			
			// Use these children as parents for next iteration
			$current_parents = $direct_children;
		}
		
		// Remove duplicates and return
		return array_unique($children_ids);
	}
	public static function get_all_columns() {
		return array(
			array(
				'label' => __( 'Image', 'catfolders-document-gallery' ),
				'key'   => 'image',
			),
			array(
				'label' => __( 'Title', 'catfolders-document-gallery' ),
				'key'   => 'title',
			),
			array(
				'label' => __( 'Type', 'catfolders-document-gallery' ),
				'key'   => 'type',
			),
			array(
				'label' => __( 'Size', 'catfolders-document-gallery' ),
				'key'   => 'size',
			),
			array(
				'label' => __( 'Updated', 'catfolders-document-gallery' ),
				'key'   => 'updated',
			),
			array(
				'label' => __( 'Counter' , 'catfolders-document-gallery' ),
				'key'   => 'counter',
			),
			array(
				'label' => __( 'Link', 'catfolders-document-gallery' ),
				'key'   => 'link',
			),
		);
	}
	public static function generate_columns( $displayColumns ) {
		$columns = self::get_all_columns();
		$columns = array_filter(
			$columns,
			function( $column ) use ( $displayColumns ) {
				return $displayColumns[ $column['key'] ] === true || $displayColumns[ $column['key'] ] === 'true';
			}
		);

		return apply_filters( 'catf_dg_columns', $columns );
	}

	public static function get_file_link( $attributes, $action,$link ) {
		switch ( $action ) {
			case 'preview':
				return 'rel="noopener noreferrer" target="_blank" href="' . esc_url( $link ) . '"';
			case 'popup':
				return 'href="' . esc_url( $link ) . '" data-popup data-popupwidth="' . esc_attr( $attributes['popupWidth'] ) . '" data-popupheight="' . esc_attr( $attributes['popupHeight'] ) . '"';
			case 'download':
				return 'download rel="noopener noreferrer" target="_blank" href="' . esc_url( $link ) . '"';
			default:
				return '';
		}
	}

	public static function render_row( $columns, $file, $attributes ) {
		ob_start();

		foreach ( $columns as $column ) {
			$column = apply_filters( 'catf_dg_columns_html', $column, $file, $attributes );
			if ( 'image' === $column['key'] ) {
				?>
					<td>
						<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td>
				<?php
			}

			if ( 'title' === $column['key'] ) {
				?>
					<td class="sorting_1 dtr-control">
					<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td> 
				<?php
			}

			if ( 'type' === $column['key'] ) {
				?>
					<td>
					<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td>
				<?php
			}

			if ( 'size' === $column['key'] ) {
				?>
					<td>
					<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td>
				<?php
			}

			if ( 'updated' === $column['key'] ) {
				?>
					<td>
					<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td>
				<?php
			}

			if ('counter' === $column['key']) {
				?>
					<td>
					<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td>
				<?php
			}

			if ( 'link' === $column['key'] ) {
				?>
					<td>
					<?php echo self::render_column_content($file, $column, $attributes); ?>
					</td>
				<?php
			}
		}

		echo ob_get_clean();
	}
	public static function render_column_content($file = array(), $column = array(), $attributes = array()) {
		if($column['key'] === 'image') {
			return '<p class="cf-column-thumbnail">' . $file['image'] . '</p>';
		} elseif($column['key'] === 'title') {
			$file_type              = self::get_available_type( $file['type'] );
			$visible_document_icons = isset( $attributes['documentIcons']['display'] ) ? $attributes['documentIcons']['display'] : true;

			ob_start();
			?>
			<div  class="flex">
				<a <?php echo self::get_file_link( $attributes, $attributes['linkTo'], $file['link'] ); ?> class="cf-icon icon-<?php echo esc_attr( $file_type ); ?> <?php echo esc_attr( $visible_document_icons ? '' : 'cf-hidden' ); ?>"><?php echo esc_html( $file['title'] ); ?></a>
			</div>
			<?php
			return ob_get_clean();
		} elseif($column['key'] === 'type') {
			return '<div class="cf-column-type">'. esc_html( $file['type'] ) .'</div>';
		} elseif($column['key'] === 'size') {
			return '<div class="cf-column-size">' . esc_html( $file['size'] ) . '</div>';
		} elseif($column['key'] === 'updated') {
			$updated_class = isset( $column['link'] ) ? 'cf-column-modified cf-column-link' : 'cf-column-modified';
			return '<div class="' . esc_attr( $updated_class ) . '">' . esc_html( $file['modified'] ) . '</div>';
		} elseif($column['key'] === 'counter') {
			return '<div class="cf-download-count">' . esc_html( $file['counter'] ) . '</div>';
		} elseif($column['key'] === 'link') {
			ob_start();
			?>
			<div class="cf-column-last">
				<span class="cf-updated">
					<?php if ( isset( $column['updated'] ) ) : ?>
						<small> <?php esc_html_e( 'Updated', 'catfolders-document-gallery' ); ?></small>
						<?php echo esc_html( $file['modified'] ); ?>
					<?php endif; ?>
				</span>
				<?php echo self::generate_column_download_btn($file, $column, $attributes); ?>
			</div>
			<?php
			return ob_get_clean();
		}
	}
	public static function generate_column_download_btn($file, $column = array(), $attributes = array()) {
		ob_start();
		$actionIconType = $attributes['actionIconType'] ?? 'library';
		$actionIconSvg = $attributes['actionIconSvg'] ?? '';
		$actionIconId = $attributes['actionIconId'] ?? 0;
		$actionIconUrl = $attributes['actionIconUrl'] ?? '';
		
		$actionIcon = '';
		if($actionIconType === 'library') {
			$actionIcon = '<img src="' . esc_url($actionIconUrl) . '" alt="">';
		} else if($actionIconType === 'custom' && $actionIconSvg) {
			$actionIcon = $actionIconSvg;
		}
		?>
		<a data-action="<?php echo esc_attr( $attributes['actionLink'] ); ?>" <?php echo self::get_file_link( $attributes, $attributes['actionLink'], $file['link'] ); ?> class="btn-download" <?php echo $attributes['actionLink'] === 'download' ? 'data-document-id="' . esc_attr($file['document_id']) . '"' : ''; ?> >
			<?php echo $actionIcon; ?>
			<?php echo $attributes['actionLabel'] ?? esc_html__( 'Download', 'catfolders-document-gallery' ); ?>
		</a>
		<?php
		return ob_get_clean();
	}
	public static function get_shortcode_data( $args ) {
		$post_id = isset( $args['shortcodeId'] ) ? sanitize_text_field( $args['shortcodeId'] ) : '';

		$data = get_post_meta( $post_id, 'shortcode_settings', true );

		if ( ! $data ) {
			$data = array();
		}

		$thumbnail_instance = Thumbnail::get_instance();

		$verify_imagick = $thumbnail_instance->verify_imagick();

		$default_data = self::get_defaults_attribute();

		$attrs = shortcode_atts( $default_data, $data );

		$attrs['displayTitle']              = rest_sanitize_boolean( $attrs['displayTitle'] );
		$attrs['libraryIcon']['display']    = rest_sanitize_boolean( $attrs['libraryIcon']['display'] );
		$attrs['displayColumns']['title']   = rest_sanitize_boolean( $attrs['displayColumns']['title'] );
		$attrs['displayColumns']['type']    = rest_sanitize_boolean( $attrs['displayColumns']['type'] );
		$attrs['displayColumns']['size']    = rest_sanitize_boolean( $attrs['displayColumns']['size'] );
		$attrs['displayColumns']['updated'] = rest_sanitize_boolean( $attrs['displayColumns']['updated'] );
		$attrs['displayColumns']['link']    = rest_sanitize_boolean( $attrs['displayColumns']['link'] );
		$attrs['displayColumns']['counter'] = rest_sanitize_boolean( $attrs['displayColumns']['counter']);
		$attrs['documentIcons']['display']  = rest_sanitize_boolean( $attrs['documentIcons']['display'] );

		$attrs['gridColumn']  = (int) $attrs['gridColumn'];
		$attrs['popupWidth']  = (int) $attrs['popupWidth'];
		$attrs['popupHeight'] = (int) $attrs['popupHeight'];
		$attrs['limit']       = (int) $attrs['limit'];

		if ( ! $verify_imagick['status'] ) {
			$attrs['displayColumns']['image'] = false;
		} else {
			$attrs['displayColumns']['image'] = rest_sanitize_boolean( $attrs['displayColumns']['image'] );
		}

		// Auto-convert actionIconId to actionIconUrl if needed
		$attrs = self::process_action_icon_attributes( $attrs );

		return $attrs;
	}

	public static function process_action_icon_attributes( $attrs ) {
		if ( 
			isset( $attrs['actionIconId'] ) && 
			! empty( $attrs['actionIconId'] ) &&
			isset( $attrs['actionIconType'] ) &&
			$attrs['actionIconType'] === 'library'
		) {
			$icon_url = wp_get_attachment_url( $attrs['actionIconId'] );
			
			if ( $icon_url ) {
				$attrs['actionIconUrl'] = $icon_url;
			}
		}
		return $attrs;
	}

	public static function get_defaults_attribute() {
		$json = wp_json_file_decode( CATF_DG_DIR . '/build/block.json', array( 'associative' => true ) );

		$defaults = array();

		foreach ( $json['attributes'] as $key => $value ) {
			$defaults[ $key ] = $value['default'];
		}

		return $defaults;
	}

	public static function get_download_count($post_id){
		$default_count = 0;

		if (!$post_id) return $default_count;

		$download_count = get_post_meta($post_id, 'cf_download_count', true);

		if (!$download_count) return $default_count;

		return $download_count;
	}

	public static function update_download_count($args){
		$message = "Failed to update download count";

		$post_id = isset($args['id_document']) ? (int) sanitize_text_field($args['id_document']) : false;

		if (!$post_id) {
			return $message;
		}

		$download_count = (int) get_post_meta($post_id,'cf_download_count',true);

		$download_count++;

		$update_status = update_post_meta($post_id,'cf_download_count',$download_count);

		if (!$update_status) return $message;
	}

	public static function register_localize_script(){
		$thumbnail_instance = Thumbnail::get_instance();
		$verify_imagick = $thumbnail_instance->verify_imagick();
		$args = [
			'verify_imagick' => $verify_imagick,
			'api'       => array(
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'rest_url'   => esc_url_raw( rest_url( 'CatFolders/v1' ) ),
			),
		];
		$args = apply_filters('catf_dg_localize_script', $args);
		wp_localize_script(
			'catf-dg-datatables',
			'catf_dg',
			$args
		);
	}
	public static function render_table_html($attributes) {
		$columns = self::generate_columns( $attributes['displayColumns'] );
		$data = self::get_attachments( $attributes );
		ob_start();
		?>
		<table data-folders="<?php echo esc_attr( wp_json_encode( $attributes['folders'] ) ); ?>" class="cf-table <?php echo ( count( $data['files'] ) == 0 ) ? 'cf-empty-data' : ''; ?>" style="--grid-column:<?php echo esc_attr( $attributes['gridColumn'] ); ?>">
				<thead>
					<tr>
						<?php foreach ( $columns as $column ) { ?>
							<th class="<?php if ("Title" === $column['label']) echo esc_attr('cf-title-th') ?>">
								<span><?php echo $column['key'] === 'link' ? esc_html( $attributes['actionColumnLabel'] ) : esc_html( $column['label'] ); ?></span>
							</th>	
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['files'] as $file ) { ?>
						<tr><?php self::render_row( $columns, $file, $attributes ); ?></tr>
					<?php } ?> 
				</tbody>
			</table>
		<?php
		return ob_get_clean();
	}
}
