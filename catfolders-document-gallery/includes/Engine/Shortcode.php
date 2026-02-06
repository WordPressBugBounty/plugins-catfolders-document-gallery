<?php

namespace CatFolder_Document_Gallery\Engine;

use CatFolder_Document_Gallery\Helpers\Helper;
use CatFolder_Document_Gallery\Utils\SingletonTrait;
use CatFolder_Document_Gallery\Engine\Thumbnail\Thumbnail;
class Shortcode {
	use SingletonTrait;

	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	public function register_shortcode() {
		add_shortcode( 'catf_dg', array( $this, 'handle_custom_shortcode' ) );
	}

	public function handle_custom_shortcode( $attrs ) {
		if ( ! isset( $attrs['id'] ) ) {
			return;
		}

		$args = array(
			'shortcodeId' => $attrs['id'],
		);

		$attributes = Helper::get_shortcode_data( $args );

		ob_start();

		include CATF_DG_DIR . '/includes/Engine/Views/Table.php';

		return ob_get_clean();

	}

}
