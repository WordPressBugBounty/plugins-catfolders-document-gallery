<?php
namespace CatFolder_Document_Gallery\Engine\Blocks;

use CatFolder_Document_Gallery\Engine\Thumbnail\Thumbnail;
use CatFolder_Document_Gallery\Helpers\Helper;
use CatFolder_Document_Gallery\Utils\SingletonTrait;

class Blocks {
	private $script_handle = 'catfolders-document-gallery';

	use SingletonTrait;

	protected function __construct() {
		add_action( 'init', array( $this, 'register_block_type' ) );
		add_action( 'wp_head', array( $this, 'wp_head' ) );
	}
	public function wp_head() {
		echo '<link rel="preload" href="'.CATF_DG_URL . 'assets/css/styles.min.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
		echo '<noscript><link rel="stylesheet" href="' . CATF_DG_URL . 'assets/css/styles.min.css"></noscript>';
	}
	public function register_block_type() {
		wp_register_style(
			'catf-dg-datatables',
			CATF_DG_URL . 'assets/css/dataTables/jquery.dataTables.min.css',
			array(),
			CATF_DG_VERSION
		);

		wp_register_style(
			'catf-dg-frontend',
			CATF_DG_URL . 'assets/css/styles.min.css',
			array(),
			CATF_DG_VERSION
		);

		wp_register_style(
			'catf-dg-datatables-responsive',
			CATF_DG_URL . 'assets/css/dataTables/responsive.dataTables.min.css',
			array(),
			CATF_DG_VERSION
		);

		wp_register_script(
			'catf-dg-datatables',
			CATF_DG_URL . 'assets/js/dataTables/jquery.dataTables.min.js',
			array( 'jquery' ),
			CATF_DG_VERSION,
			true
		);

		wp_register_script(
			'catf-dg-datatables-responsive',
			CATF_DG_URL . 'assets/js/dataTables/dataTables.responsive.min.js',
			array( 'jquery' ),
			CATF_DG_VERSION,
			true
		);

		wp_register_script(
			'catf-dg-datatables-natural',
			CATF_DG_URL . 'assets/js/dataTables/natural.min.js',
			array( 'jquery' ),
			CATF_DG_VERSION,
			true
		);

		wp_register_script(
			'catf-dg-datatables-filesize',
			CATF_DG_URL . 'assets/js/dataTables/filesize.min.js',
			array( 'jquery' ),
			CATF_DG_VERSION,
			true
		);

		register_block_type( CATF_DG_DIR . '/build', array( 'render_callback' => array( $this, 'catf_dg_render_block' ) ) );

		wp_set_script_translations( "{$this->script_handle}-editor-script", 'catfolders-document-gallery', CATF_DG_DIR . '/languages/' );
		wp_set_script_translations( "{$this->script_handle}-view-script", 'catfolders-document-gallery', CATF_DG_DIR . '/languages/' );
	}

	public function catf_dg_render_block( $attributes ) {
		if ( ! $attributes || ! isset( $attributes['folders'] ) ) {
			return;
		}

		wp_enqueue_script( 'catf-dg-datatables' );
		wp_enqueue_script( 'catf-dg-datatables-natural' );
		wp_enqueue_script( 'catf-dg-datatables-filesize' );
		wp_enqueue_script( 'catf-dg-datatables-responsive' );

		wp_enqueue_style( 'catf-dg-datatables' );
		wp_enqueue_style( 'catf-dg-frontend' );
		wp_enqueue_style( 'catf-dg-datatables-responsive' );

		return $this->generate_attachment_table( $attributes );
	}

	public function generate_attachment_table( $attributes ) {
		$thumbnail_instance = Thumbnail::get_instance();

		$verify_imagick = $thumbnail_instance->verify_imagick();

		if ( ! $verify_imagick['status'] ) {
			$attributes['displayColumns']['image'] = false;
		}

		$args = [
			'verify_imagick' => $verify_imagick,
			'api'       => array(
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'rest_url'   => esc_url_raw( rest_url( 'CatFolders/v1' ) ),
			),
		];

		Helper::register_localize_script($args);

		ob_start();

		include CATF_DG_DIR . '/includes/Engine/Views/Table.php';

		return ob_get_clean();
	}
}
