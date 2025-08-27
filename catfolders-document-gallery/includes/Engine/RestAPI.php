<?php
namespace CatFolder_Document_Gallery\Engine;

use CatFolder_Document_Gallery\Utils\SingletonTrait;
use CatFolders\Models\FolderModel;
use CatFolder_Document_Gallery\Helpers\Helper;
use CatFolder_Document_Gallery\Helpers\FolderHierarchy;

class RestAPI {

	use SingletonTrait;

	/**
	 * The Constructor that load the engine classes
	 */
	protected function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			CATF_ROUTE_NAMESPACE,
			'/get-all-tree-folders',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_all_tree_folders' ),
					'permission_callback' => array( $this, 'resPermissionsCheck' ),
				),
			)
		);

		register_rest_route(
			CATF_ROUTE_NAMESPACE,
			'/get-attachments-folders',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_attachments_folders' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			CATF_ROUTE_NAMESPACE,
			'/get-folders-shortcode-data',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_folder_shortcode_data' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			CATF_ROUTE_NAMESPACE,
			'/update-download-count',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_download_count' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			CATF_ROUTE_NAMESPACE,
			'/catfdoc-reload-table',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'reload_table' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			CATF_ROUTE_NAMESPACE,
			'/catfdoc-search-data',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'search_data' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public function resPermissionsCheck() {
		return current_user_can( 'upload_files' );
	}

	public function get_all_tree_folders( \WP_REST_Request $request ) {

		$orderBy   = sanitize_key( $request->get_param( 'orderby' ) );
		$orderType = sanitize_key( $request->get_param( 'ordertype' ) );

		//Get all folders
		$result = FolderModel::get_all(
			array(
				'orderBy'   => $orderBy,
				'orderType' => $orderType,
			)
		);

		//Return the response as Json format
		return new \WP_REST_Response( $result );
	}

	public function get_attachments_folders( \WP_REST_Request $request ) {
		global $wpdb;
		$cfdoc_folder_hierarchy = new FolderHierarchy($wpdb);

		$params = $request->get_params();
		$data   = Helper::get_attachments( $params );
		//this function is used at admin to preview
		$data['breadcrumbHtml'] = $cfdoc_folder_hierarchy->render_hierarchy($params['folders'][0] ?? 0);
		$data['childrenHtml'] = $cfdoc_folder_hierarchy->get_lv1_children($params['folders'][0] ?? 0);
		return new \WP_REST_Response( $data );
	}

	public function get_folder_shortcode_data( \WP_REST_Request $request ) {
		try {
			$params = $request->get_params();
			$data   = Helper::get_shortcode_data( $params );
		} catch ( \Exception $exc ) {
			$data = $exc->getMessage();
		}
		return new \WP_REST_Response( $data );
	}

	public function update_download_count( \WP_REST_Request $request ) {
		try {
			$params = $request->get_params();
			$data   = Helper::update_download_count( $params );
		} catch ( \Exception $exc ) {
			$data = $exc->getMessage();
		}
		return new \WP_REST_Response( $data );
	}
	public function reload_table( \WP_REST_Request $request ) {
		global $wpdb;
		try {
			$params = $request->get_params();
			$attributes = $params['attr'];
			$limit_parent_id = $attributes['limit_parent_id'];
			$data    = Helper::get_attachments( $attributes );
			$data['tableHtml'] = Helper::render_table_html( $attributes );

			$data['breadCrumbHtml'] = '';
			if( $attributes['libraryType'] == 'hierarchical_folders' && $attributes['showBreadCrumb'] ) {
				$selected_folder_id = (isset($attributes['folders']) && is_array($attributes['folders'])) ? (int)$attributes['folders'][0] : 0;
				if($selected_folder_id > 0) {
					$cfdoc_folder_hierarchy = new FolderHierarchy($wpdb);
					$data['breadCrumbHtml'] = $cfdoc_folder_hierarchy->render_hierarchy($selected_folder_id, $limit_parent_id, true, false);
				}
				
			}
			$data['childrenHtml'] = '';
			if( $attributes['libraryType'] == 'hierarchical_folders' && $attributes['isNestedFolders'] ) {
				$selected_folder_id = (isset($attributes['folders']) && is_array($attributes['folders'])) ? (int)$attributes['folders'][0] : 0;
				if($selected_folder_id > 0) {
					if(!isset($cfdoc_folder_hierarchy)) {
						$cfdoc_folder_hierarchy = new FolderHierarchy($wpdb);
					}
					$data['childrenHtml'] = $cfdoc_folder_hierarchy->get_lv1_children($selected_folder_id, false);
				}
				
			}

		} catch ( \Exception $exc ) {
			$data = $exc->getMessage();
		}
		return new \WP_REST_Response( $data );
	}
	public function search_data( \WP_REST_Request $request ) {
		try {
			$params = $request->get_params();
			$searchValue = $params['searchValue'] ?? '';
			$catfDataJson = $params['catfDataJson'] ?? array();

			//update folders to catfDataJson
			$catfDataJson['folders'] = $params['currentFolders'] ?? array();
			$folders = $catfDataJson['folders'] ?? array();
			$displayColumns = $catfDataJson['displayColumns'] ?? array();
			if( empty($folders) || empty($displayColumns) ) {
				return new \WP_REST_Response( array() );
			}

			$catfDataJson['searchValue'] = $searchValue;
			$data = Helper::get_attachments( $catfDataJson, !empty($searchValue) );
			$files = $data['files'] ?? array();

			$attributes = $catfDataJson;
			$columns = Helper::generate_columns( $attributes['displayColumns'] );

			$formattedFiles = array();
			foreach ($files as $file) {
				$file = wp_parse_args( $file, array(
					'image' => '',
					'title' => '',
					'type' => '',
					'size' => '',
					'modified' => '',
					'counter' => '',
					'link' => ''
				) );

				//render column content
				foreach($columns as $column) {
					$columnKey = $column['key'];
					if( isset($file[$columnKey]) && ! empty( $file[$columnKey] ) ) {
						$file[$columnKey] = Helper::render_column_content($file, $column, $attributes);
					}
				}
				
				$formattedFile = array();
				$index = 0;
				foreach($columns as $column) {
					$columnKey = $column['key'];
					$formattedFile[$index] = $file[$columnKey];
					$index++;
				}

				$formattedFiles[] = $formattedFile;
			}

			$data = array();
			$data['rows'] = $formattedFiles;
			$data['total'] = count($formattedFiles);

		} catch ( \Exception $exc ) {
			$data = $exc->getMessage();
		}
		return new \WP_REST_Response( $data );
	}
}
