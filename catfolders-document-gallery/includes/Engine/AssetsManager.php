<?php
namespace CatFolder_Document_Gallery\Engine;

use CatFolder_Document_Gallery\Helpers\Helper;
use CatFolder_Document_Gallery\Utils\SingletonTrait;

/**
 * Centralized Assets Manager for handling all scripts and styles
 */
class AssetsManager {
	use SingletonTrait;

	/**
	 * All registered assets configuration
	 */
	private $assets = array();

	private function __construct() {
		add_action( 'init', array( $this, 'register_all_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
	}

	/**
	 * Register all plugin assets
	 */
	public function register_all_assets() {
		$this->define_assets();
		$this->register_styles();
		$this->register_scripts();
	}

	/**
	 * Define all assets configuration
	 */
	private function define_assets() {
		$this->assets = array(
			'styles' => array(
				'catf-dg-datatables' => array(
					'src'  => CATF_DG_URL . 'assets/css/dataTables/jquery.dataTables.min.css',
					'deps' => array(),
					'ver'  => CATF_DG_VERSION,
				),
				'catf-dg-frontend' => array(
					'src'  => CATF_DG_URL . 'assets/css/styles.min.css',
					'deps' => array(),
					'ver'  => CATF_DG_VERSION,
				),
				'catf-dg-datatables-responsive' => array(
					'src'  => CATF_DG_URL . 'assets/css/dataTables/responsive.dataTables.min.css',
					'deps' => array(),
					'ver'  => CATF_DG_VERSION,
				),
				'catf-dg-post-type' => array(
					'src'  => CATF_DG_URL . 'build/apps/app.css',
					'deps' => array(),
					'ver'  => CATF_DG_VERSION,
				),
			),
			'scripts' => array(
				'catf-dg-datatables' => array(
					'src'       => CATF_DG_URL . 'assets/js/dataTables/jquery.dataTables.min.js',
					'deps'      => array( 'jquery' ),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
				'catf-dg-datatables-responsive' => array(
					'src'       => CATF_DG_URL . 'assets/js/dataTables/dataTables.responsive.min.js',
					'deps'      => array( 'jquery' ),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
				'catf-dg-datatables-natural' => array(
					'src'       => CATF_DG_URL . 'assets/js/dataTables/natural.min.js',
					'deps'      => array( 'jquery' ),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
				'catf-dg-datatables-filesize' => array(
					'src'       => CATF_DG_URL . 'assets/js/dataTables/filesize.min.js',
					'deps'      => array( 'jquery' ),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
				'catf-dg-react-app' => array(
					'src'       => CATF_DG_URL . 'build/apps/app.js',
					'deps'      => array( 'react', 'react-dom', 'wp-components', 'wp-element', 'wp-i18n', 'wp-media-utils' ),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
				'catf-dg-shortcode-settings' => array(
					'src'       => CATF_DG_URL . 'assets/js/shortcode/events.js',
					'deps'      => array(),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
				'catf-dg-frontend' => array(
					'src'       => CATF_DG_URL . 'build/view.js',
					'deps'      => array( 'wp-i18n' ),
					'ver'       => CATF_DG_VERSION,
					'in_footer' => true,
				),
			),
		);
	}

	/**
	 * Register all styles
	 */
	private function register_styles() {
		foreach ( $this->assets['styles'] as $handle => $config ) {
			wp_register_style(
				$handle,
				$config['src'],
				$config['deps'],
				$config['ver']
			);
		}
	}

	/**
	 * Register all scripts
	 */
	private function register_scripts() {
		foreach ( $this->assets['scripts'] as $handle => $config ) {
			wp_register_script(
				$handle,
				$config['src'],
				$config['deps'],
				$config['ver'],
				$config['in_footer']
			);
		}
	}

	/**
	 * Enqueue frontend assets (for blocks and shortcodes)
	 */
	public function enqueue_frontend_assets( $include_frontend_script = false ) {
		// DataTables scripts
		wp_enqueue_script( 'catf-dg-datatables' );
		wp_enqueue_script( 'catf-dg-datatables-natural' );
		wp_enqueue_script( 'catf-dg-datatables-filesize' );
		wp_enqueue_script( 'catf-dg-datatables-responsive' );

		// Frontend script for shortcodes
		if ( $include_frontend_script ) {
			wp_enqueue_script( 'catf-dg-frontend' );
			wp_set_script_translations( 'catf-dg-frontend', 'catfolders-document-gallery', CATF_DG_DIR . '/languages/' );
		}

		// DataTables styles
		wp_enqueue_style( 'catf-dg-datatables' );
		wp_enqueue_style( 'catf-dg-frontend' );
		wp_enqueue_style( 'catf-dg-datatables-responsive' );

		// Register localize script after enqueuing catf-dg-datatables
		$this->register_localize_script();
	}

	/**
	 * Enqueue admin assets (for post type editing)
	 */
	public function enqueue_admin_assets() {
		// Frontend assets for preview (includes localize script)
		$this->enqueue_frontend_assets();

		// Admin-specific assets
		wp_enqueue_script( 'catf-dg-react-app' );
		wp_enqueue_script( 'catf-dg-shortcode-settings' );
		wp_enqueue_media();

		// Admin styles
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'catf-dg-post-type' );
	}

	/**
	 * Admin enqueue scripts
	 */
	public function admin_enqueue_scripts() {
		$current_screen = get_current_screen();
		
		// Enqueue on any post edit/add page (any post type) or our plugin's list page
		if ( $current_screen && ( 
			$current_screen->base === 'post' || 
			$current_screen->base === 'edit' ||
			strpos( $current_screen->id, 'catfolder-post-type' ) !== false 
		)) {
			$this->enqueue_admin_assets();
		}
	}

	/**
	 * Frontend enqueue scripts
	 */
	public function frontend_enqueue_scripts() {
		$this->enqueue_frontend_assets( true );
	}

	/**
	 * Register localize script for catf-dg-datatables
	 * This should be called after catf-dg-datatables is enqueued
	 */
	private function register_localize_script() {
		// Only register if catf-dg-datatables is enqueued
		if ( ! wp_script_is( 'catf-dg-datatables', 'enqueued' ) ) {
			return;
		}

		Helper::register_localize_script();
	}
}
