<?php
defined( 'ABSPATH' ) || exit;

/**
 * The "Eigenbestand" checkbox on products — the hard opt-out that wins over any
 * SKU match — and the Bloomtech column in the product list.
 */
class BTS_Product_Fields {

	public static function init() {
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'simple_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_simple' ) );

		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column_content' ), 20, 2 );
	}

	public static function simple_field() {
		global $post;
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_bloomtech_exclude',
				'label'       => 'Eigenbestand',
				'description' => 'Nie über Bloomtech synchronisieren, auch wenn die SKU passt. Für Ware, die ihr selbst auf Lager habt.',
				'value'       => get_post_meta( $post->ID, '_bloomtech_exclude', true ) === 'yes' ? 'yes' : 'no',
			)
		);
		echo '</div>';
	}

	public static function save_simple( $product ) {
		$id = $product->get_id();
		update_post_meta( $id, '_bloomtech_exclude', isset( $_POST['_bloomtech_exclude'] ) ? 'yes' : 'no' );
	}

	public static function column( $cols ) {
		$cols['bts_artnr'] = 'Bloomtech';
		return $cols;
	}

	public static function column_content( $col, $post_id ) {
		if ( $col !== 'bts_artnr' ) {
			return;
		}
		if ( get_post_meta( $post_id, '_bloomtech_exclude', true ) === 'yes' ) {
			echo '<span style="color:#996800" title="Eigenbestand – wird nie synchronisiert">Eigenbestand</span>';
			return;
		}
		$sku = get_post_meta( $post_id, '_sku', true );
		if ( $sku && BTS_Catalog::get( $sku ) ) {
			echo '<code>' . esc_html( $sku ) . '</code>';
			return;
		}
		echo '<span style="color:#aaa">—</span>';
	}
}
