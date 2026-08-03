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
		$args = wp_parse_args(
			$args,
			array(
				'folders'        => array(),
				'displayColumns' => array(),
				'searchValue'    => '',
				'currentPage'    => 1,
			)
		);
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
			'posts_per_page' => -1, // Get all posts, pagination handled by offset
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

		/**
		 * Filter the files array before rendering.
		 *
		 * Each item in $files contains: document_id, title, type, size, url, link,
		 * alt, modified, updated, image, counter.
		 *
		 * Example — sort by the physical file's date (filemtime) instead of the
		 * WordPress upload date. Useful when files were copied to the server while
		 * preserving their original timestamps:
		 *
		 *     add_filter( 'catf_dg_files', function ( $files ) {
		 *         foreach ( $files as &$file ) {
		 *             $path = get_attached_file( $file['document_id'] );
		 *             // file_exists() guards against files that are missing or offloaded (e.g. S3).
		 *             $file['_mtime'] = ( $path && file_exists( $path ) ) ? filemtime( $path ) : 0;
		 *         }
		 *         unset( $file );
		 *
		 *         // Oldest first (ascending). For newest first, swap $a <=> $b to $b <=> $a
		 *         usort( $files, fn( $a, $b ) => $a['_mtime'] <=> $b['_mtime'] );
		 *
		 *         return $files;
		 *     }, 10, 1 );
		 *
		 * Note: filemtime() reads from disk once per file, so sorting a large set of
		 * files adds overhead. Files must be stored locally on the server.
		 *
		 * @param array $files     The files to display.
		 * @param array $queryArgs The WP_Query args used to fetch the attachments.
		 * @param array $args      The original arguments passed to get_attachments().
		 */
		$files = apply_filters( 'catf_dg_files', $files, $queryArgs, $args );
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
				return isset( $displayColumns[ $column['key'] ] ) && ( $displayColumns[ $column['key'] ] === true || $displayColumns[ $column['key'] ] === 'true' );
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
			$column  = apply_filters( 'catf_dg_columns_html', $column, $file, $attributes );
			$content = self::render_column_content( $file, $column, $attributes );
			if ( $content ) {
				$td_class = 'title' === $column['key'] ? ' class="sorting_1 dtr-control"' : '';
				echo '<td' . $td_class . '>' . $content . '</td>';
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
		} else {
			return apply_filters( 'catf_dg_render_custom_column', '', $column, $file, $attributes );
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
		<a data-action="<?php echo esc_attr( $attributes['actionLink'] ); ?>" <?php echo self::get_file_link( $attributes, $attributes['actionLink'], $file['link'] ); ?> class="btn-download" <?php echo $attributes['actionLink'] === 'download' ? 'data-document-id="' . esc_attr( self::encrypt( $file['document_id'] ) ) . '"' : ''; ?> >
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

		// $thumbnail_instance = Thumbnail::get_instance();

		// $verify_imagick = $thumbnail_instance->verify_imagick();

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

		// if ( ! $verify_imagick['status'] ) {
		// 	$attrs['displayColumns']['image'] = false;
		// } else {
		// 	$attrs['displayColumns']['image'] = rest_sanitize_boolean( $attrs['displayColumns']['image'] );
		// }
		$attrs['displayColumns']['image'] = rest_sanitize_boolean( $attrs['displayColumns']['image'] );

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

		$post_id = isset($args['id_document']) ? (int) self::decrypt( sanitize_text_field($args['id_document']) ) : false;

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
			'plugin_url' => CATF_DG_URL,
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

	/**
	 * Renders the HTML table for a given set of attributes.
	 *
	 * Custom columns can be added via filters. Use 'catf_dg_table_columns' to register
	 * the column definition and 'catf_dg_render_custom_column' to render its content.
	 *
	 * Supported column options:
	 *   - key        (string)  Column identifier.
	 *   - label      (string)  Column header text.
	 *   - type       (string)  Set 'date-sort' to enable date-aware sorting.
	 *   - orderable  (bool)    Set false to disable sorting. Default true.
	 *
	 * @example
	 * // Add an "Uploaded At" column before the "Updated" column.
	 *
	 * add_filter( 'catf_dg_table_columns', function( $columns, $attributes ) {
	 *     $columns   = array_values( $columns );
	 *     $insert_at = array_search( 'updated', array_column( $columns, 'key' ) );
	 *
	 *     $new_column = array(
	 *         'label' => __( 'Uploaded At', 'catfolders-document-gallery' ),
	 *         'key'   => 'uploaded_at',
	 *         'type'  => 'date-sort',
	 *     );
	 *
	 *     if ( $insert_at !== false ) {
	 *         array_splice( $columns, $insert_at, 0, array( $new_column ) );
	 *     } else {
	 *         $columns[] = $new_column;
	 *     }
	 *
	 *     return $columns;
	 * }, 10, 2 );
	 *
	 * add_filter( 'catf_dg_render_custom_column', function( $html, $column, $file, $attributes ) {
	 *     if ( $column['key'] !== 'uploaded_at' ) {
	 *         return $html;
	 *     }
	 *
	 *     $post        = get_post( $file['document_id'] );
	 *     $uploaded_at = $post ? wp_date( 'M d, Y H:i', strtotime( $post->post_date ) ) : '';
	 *
	 *     return '<div class="cf-column-uploaded-at">' . esc_html( $uploaded_at ) . '</div>';
	 * }, 10, 4 );
	 */
	public static function render_table_html($attributes) {
		$columns = self::generate_columns( $attributes['displayColumns'] );
		$columns = apply_filters( 'catf_dg_table_columns', $columns, $attributes );
		$get_files_of_children = apply_filters( 'catf_get_files_of_children', false );
		$data = self::get_attachments( $attributes, $get_files_of_children );
		ob_start();
		?>
		<table data-folders="<?php echo esc_attr( self::encrypt( wp_json_encode( $attributes['folders'] ) ) ); ?>" class="cf-table <?php echo ( count( $data['files'] ) == 0 ) ? 'cf-empty-data' : ''; ?>" style="--grid-column:<?php echo esc_attr( $attributes['gridColumn'] ); ?>">
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
	public static function encrypt($data) {
		$key = (defined('AUTH_KEY')) ? AUTH_KEY : 'catfolders_dg_key';
		$method = 'aes-256-cbc';
		$ivlen = openssl_cipher_iv_length($method);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
		return base64_encode($iv . $encrypted);
	}

	public static function decrypt($encrypted_data) {
		$key = (defined('AUTH_KEY')) ? AUTH_KEY : 'catfolders_dg_key';
		$method = 'aes-256-cbc';
		$ivlen = openssl_cipher_iv_length($method);
		$data = base64_decode($encrypted_data);
		$iv = substr($data, 0, $ivlen);
		$encrypted = substr($data, $ivlen);
		return openssl_decrypt($encrypted, $method, $key, 0, $iv);
	}

	/**
	 * Secret used to sign the folder-scope token. Same source as encrypt(), but
	 * the two must never be confused: encrypt() hides a value, sign_folders()
	 * authorizes one.
	 */
	private static function signing_key() {
		return defined( 'AUTH_KEY' ) ? AUTH_KEY : 'catfolders_dg_key';
	}

	/**
	 * Sign the set of root folder ids a gallery instance is allowed to browse.
	 *
	 * Generated server-side at render time (Views/Table.php), where the real
	 * folder attributes are known and trusted. The frontend echoes the token
	 * back on every AJAX call so the endpoints can authorize the request against
	 * what was actually published, instead of trusting a client-supplied id.
	 *
	 * HMAC (not the CBC encrypt() scheme) so the payload cannot be tampered with:
	 * flipping bytes invalidates the signature.
	 *
	 * @param int[] $folder_ids Root folder ids for this gallery instance.
	 * @return string Signed token: base64(json ids) . "." . hmac.
	 */
	public static function sign_folders( $folder_ids ) {
		$ids = array_values( array_unique( array_map( 'intval', (array) $folder_ids ) ) );
		sort( $ids );

		$payload = wp_json_encode( $ids );
		$sig     = hash_hmac( 'sha256', $payload, self::signing_key() );

		return base64_encode( $payload ) . '.' . $sig;
	}

	/**
	 * Verify a folder-scope token and return the signed root ids.
	 *
	 * @param string $token Token produced by sign_folders().
	 * @return int[]|false Root folder ids, or false when missing/tampered.
	 */
	public static function verify_folders( $token ) {
		if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
			return false;
		}

		list( $encoded, $sig ) = explode( '.', $token, 2 );

		$payload = base64_decode( $encoded, true );
		if ( false === $payload ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $payload, self::signing_key() );
		if ( ! hash_equals( $expected, (string) $sig ) ) {
			return false;
		}

		$ids = json_decode( $payload, true );
		if ( ! is_array( $ids ) ) {
			return false;
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * id => parent map for every folder, cached per request.
	 *
	 * @return array<int,int>
	 */
	private static function folder_parent_map() {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		global $wpdb;

		$map  = array();
		$rows = $wpdb->get_results( "SELECT id, parent FROM {$wpdb->prefix}catfolders" ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->id ] = (int) $row->parent;
		}

		return $map;
	}

	/**
	 * Is $folder_id one of $root_ids, or a descendant of one of them?
	 *
	 * Walks the parent chain up to the root. This is the authorization boundary:
	 * a gallery that publishes root folder A legitimately exposes every folder
	 * under A (hierarchical navigation), and nothing outside it.
	 *
	 * @param int   $folder_id Requested folder.
	 * @param int[] $root_ids  Signed root folders for the gallery.
	 * @return bool
	 */
	public static function folder_within_roots( $folder_id, $root_ids ) {
		$folder_id = (int) $folder_id;
		$root_ids  = array_map( 'intval', (array) $root_ids );

		if ( $folder_id <= 0 || empty( $root_ids ) ) {
			return false;
		}

		$map  = self::folder_parent_map();
		$seen = array();

		while ( $folder_id > 0 && ! isset( $seen[ $folder_id ] ) ) {
			if ( in_array( $folder_id, $root_ids, true ) ) {
				return true;
			}

			$seen[ $folder_id ] = true;
			$folder_id          = isset( $map[ $folder_id ] ) ? $map[ $folder_id ] : 0;
		}

		return false;
	}
}
