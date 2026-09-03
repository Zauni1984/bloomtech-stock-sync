<?php
defined( 'ABSPATH' ) || exit;

/**
 * The article-number field on products and variations.
 *
 * Nothing is ever filled in automatically. A product only joins the sync once
 * somebody types an article number here — which is exactly what keeps
 * shop-owned stock (Biobizz and the like) out of it.
 */
class BTS_Product_Fields {

	public static function init() {
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'simple_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_simple' ) );
		add_action( 'woocommerce_variation_options_inventory', array( __CLASS__, 'variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );

		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column_content' ), 20, 2 );
	}

	public static function simple_field() {
		global $post;
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => BTS_META_ARTNR,
				'label'       => 'Bloomtech-Artikelnummer',
				'desc_tip'    => true,
				'description' => 'Nur ausfüllen, wenn dieses Produkt über Bloomtech bezogen wird. Solange das Feld leer ist, rührt der Bestandsabgleich das Produkt nicht an.',
				'value'       => get_post_meta( $post->ID, BTS_META_ARTNR, true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_bloomtech_exclude',
				'label'       => 'Eigenbestand',
				'description' => 'Nie über Bloomtech synchronisieren (auch nicht, wenn eine Artikelnummer eingetragen ist).',
				'value'       => get_post_meta( $post->ID, '_bloomtech_exclude', true ) === 'yes' ? 'yes' : 'no',
			)
		);
		echo '</div>';
	}

	public static function save_simple( $product ) {
		$id = $product->get_id();
		if ( isset( $_POST[ BTS_META_ARTNR ] ) ) {
			$v = sanitize_text_field( wp_unslash( $_POST[ BTS_META_ARTNR ] ) );
			$v === '' ? delete_post_meta( $id, BTS_META_ARTNR ) : update_post_meta( $id, BTS_META_ARTNR, $v );
		}
		update_post_meta( $id, '_bloomtech_exclude', isset( $_POST['_bloomtech_exclude'] ) ? 'yes' : 'no' );
	}

	public static function variation_field( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input(
			array(
				'id'            => 'bts_artnr_' . $variation->ID,
				'name'          => 'bts_artnr[' . $variation->ID . ']',
				'label'         => 'Bloomtech-Artikelnummer',
				'value'         => get_post_meta( $variation->ID, BTS_META_ARTNR, true ),
				'wrapper_class' => 'form-row form-row-full',
				'desc_tip'      => true,
				'description'   => 'Leer lassen, wenn diese Variante nicht über Bloomtech läuft.',
			)
		);
	}

	public static function save_variation( $variation_id, $i ) {
		if ( ! isset( $_POST['bts_artnr'][ $variation_id ] ) ) {
			return;
		}
		$v = sanitize_text_field( wp_unslash( $_POST['bts_artnr'][ $variation_id ] ) );
		$v === '' ? delete_post_meta( $variation_id, BTS_META_ARTNR ) : update_post_meta( $variation_id, BTS_META_ARTNR, $v );
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
		$a = get_post_meta( $post_id, BTS_META_ARTNR, true );
		if ( $a ) {
			echo '<code>' . esc_html( $a ) . '</code>';
			return;
		}
		$kids = get_posts(
			array(
				'post_type'   => 'product_variation',
				'post_parent' => $post_id,
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => BTS_META_ARTNR,
				'meta_compare'=> 'EXISTS',
			)
		);
		echo $kids ? '<span title="Auf Variantenebene verknüpft">Varianten</span>' : '<span style="color:#aaa">—</span>';
	}
}
