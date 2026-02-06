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
		register_block_type( CATF_DG_DIR . '/build', array( 'render_callback' => array( $this, 'catf_dg_render_block' ) ) );

		wp_set_script_translations( "{$this->script_handle}-editor-script", 'catfolders-document-gallery', CATF_DG_DIR . '/languages/' );
		wp_set_script_translations( "{$this->script_handle}-view-script", 'catfolders-document-gallery', CATF_DG_DIR . '/languages/' );
	}

	public function catf_dg_render_block( $attributes ) {
		if ( ! $attributes || ! isset( $attributes['folders'] ) ) {
			return;
		}

		// Auto-convert actionIconId to actionIconUrl if needed
		$attributes = $this->process_action_icon_attributes( $attributes );

		return $this->generate_attachment_table( $attributes );
	}
	private function process_action_icon_attributes( $attributes ) {
		if ( 
			isset( $attributes['actionIconId'] ) && 
			! empty( $attributes['actionIconId'] ) &&
			isset( $attributes['actionIconType'] ) &&
			$attributes['actionIconType'] === 'library'
		) {
			$icon_url = wp_get_attachment_url( $attributes['actionIconId'] );
			if ( $icon_url ) {
				$attributes['actionIconUrl'] = $icon_url;
			}
		}

		return $attributes;
	}

	public function generate_attachment_table( $attributes ) {
		$thumbnail_instance = Thumbnail::get_instance();

		$verify_imagick = $thumbnail_instance->verify_imagick();

		if ( ! $verify_imagick['status'] ) {
			$attributes['displayColumns']['image'] = false;
		}
		ob_start();

		include CATF_DG_DIR . '/includes/Engine/Views/Table.php';

		return ob_get_clean();
	}
}
