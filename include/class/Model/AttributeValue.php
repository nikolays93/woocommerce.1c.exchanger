<?php


namespace NikolayS93\Exchange\Model;


use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Plugin;

class AttributeValue extends Term {

	public function get_taxonomy_name() {
		// TODO: Implement get_taxonomy_name() method.
		return '';
	}

	function prepare() {
		$Plugin = Plugin::get_instance();
		/** @var Int $term_id WP_Term->term_id */
		$term_id = $this->get_id();

		if ( $this->check_mode( $term_id, $Plugin->get_setting( 'attribute_mode' ) ) ) {
			// Do not update name?
			switch ( $Plugin->get_setting( 'pa_name' ) ) {
				case false:
					if ( $term_id ) {
						$this->set_name( '' );
					}
					break;
			}

			if( !$this->check_mode($term_id, $Plugin->get_setting( 'pa_desc' )) ) {
				$this->set_description( '' );
			}

			return true;
		}

		return false;
	}
}