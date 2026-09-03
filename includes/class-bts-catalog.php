<?php
defined( 'ABSPATH' ) || exit;

/**
 * The Bloomtech article catalogue.
 *
 * Every article number that ever appeared in an export is kept here — including
 * articles that do not (yet) exist as products in the shop. That is what makes
 * it possible to pick an article number from a list when a new product is added
 * later, instead of hunting through the CSV by hand.
 */
class BTS_Catalog {

	public static function upsert_many( array $articles ) {
		global $wpdb;
		$t     = BTS_Install::articles_table();
		$now   = current_time( 'mysql' );
		$new   = 0;
		$known = 0;

		foreach ( $articles as $a ) {
			$artnr = (string) $a['artnr'];
			if ( $artnr === '' ) {
				continue;
			}
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE artnr = %s", $artnr ) );
			$data   = array(
				'ean'       => (string) ( $a['ean'] ?? '' ),
				'name'      => mb_substr( (string) ( $a['name'] ?? '' ), 0, 255 ),
				'brand'     => mb_substr( (string) ( $a['brand'] ?? '' ), 0, 191 ),
				'stock'     => isset( $a['stock'] ) ? $a['stock'] : null,
				'price'     => isset( $a['price'] ) ? $a['price'] : null,
				'raw'       => wp_json_encode( $a['raw'] ?? array(), JSON_UNESCAPED_UNICODE ),
				'last_seen' => $now,
			);
			if ( $exists ) {
				$wpdb->update( $t, $data, array( 'id' => $exists ) );
				++$known;
			} else {
				$data['artnr']      = $artnr;
				$data['first_seen'] = $now;
				$wpdb->insert( $t, $data );
				++$new;
			}
		}
		return array(
			'new'   => $new,
			'known' => $known,
		);
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . BTS_Install::articles_table() );
	}

	public static function get( $artnr ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . BTS_Install::articles_table() . ' WHERE artnr = %s', $artnr ) );
	}

	/**
	 * @param string $filter all|linked|unlinked
	 */
	public static function query( $search = '', $filter = 'all', $limit = 50, $offset = 0 ) {
		global $wpdb;
		$t      = BTS_Install::articles_table();
		$linked = self::linked_artnrs();

		$where = array( '1=1' );
		$args  = array();
		if ( $search !== '' ) {
			$where[] = '(artnr LIKE %s OR ean LIKE %s OR name LIKE %s OR brand LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			array_push( $args, $like, $like, $like, $like );
		}
		if ( $filter === 'linked' || $filter === 'unlinked' ) {
			if ( ! $linked ) {
				$where[] = $filter === 'linked' ? '1=0' : '1=1';
			} else {
				$ph      = implode( ',', array_fill( 0, count( $linked ), '%s' ) );
				$where[] = 'artnr ' . ( $filter === 'linked' ? 'IN' : 'NOT IN' ) . " ({$ph})";
				$args    = array_merge( $args, array_keys( $linked ) );
			}
		}
		$sql   = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . ' ORDER BY name ASC, artnr ASC LIMIT %d OFFSET %d';
		$args[] = (int) $limit;
		$args[] = (int) $offset;
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		$csql  = "SELECT COUNT(*) FROM {$t} WHERE " . implode( ' AND ', $where );
		$cargs = array_slice( $args, 0, count( $args ) - 2 );
		$total = (int) ( $cargs ? $wpdb->get_var( $wpdb->prepare( $csql, $cargs ) ) : $wpdb->get_var( $csql ) );

		return array(
			'rows'   => $rows,
			'total'  => $total,
			'linked' => $linked,
		);
	}

	/** artnr => array of post IDs (products and variations). */
	public static function linked_artnrs() {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", BTS_META_ARTNR )
		);
		$map = array();
		foreach ( $rows as $r ) {
			$map[ (string) $r->meta_value ][] = (int) $r->post_id;
		}
		return $map;
	}
}
