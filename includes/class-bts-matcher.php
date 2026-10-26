<?php
defined( 'ABSPATH' ) || exit;

/**
 * Finds the shop products belonging to a Bloomtech article number.
 *
 * Matching is by SKU, because Bloomtech-supplied products carry the supplier's
 * article number in the SKU field (e.g. "16287"), while stock the shop owns
 * itself carries an auto-generated "HJ-…" SKU. A Bloomtech list therefore can
 * never match own stock — no manual linking required.
 */
class BTS_Matcher {

	/** @var array<string,array<int,array{pid:int,via:string,sku:string}>>|null */
	private static $cache = null;

	public static function flush() {
		self::$cache = null;
	}

	/** article number (normalised) => list of matching posts */
	public static function map() {
		if ( self::$cache !== null ) {
			return self::$cache;
		}
		global $wpdb;
		$map    = array();
		$prefix = trim( (string) BTS_Settings::get( 'exclude_sku_prefix', 'HJ-' ) );

		$rows = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_value AS sku
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_sku'
			   AND pm.meta_value <> ''
			   AND p.post_type IN ('product','product_variation')
			   AND p.post_status NOT IN ('trash','auto-draft')"
		);
		foreach ( $rows as $r ) {
			$sku = trim( (string) $r->sku );
			if ( $sku === '' ) {
				continue;
			}
			if ( $prefix !== '' && stripos( $sku, $prefix ) === 0 ) {
				continue; // Eigenbestand — nie über den Lieferanten steuern.
			}
			$map[ self::key( $sku ) ][] = array(
				'pid' => (int) $r->post_id,
				'via' => 'sku',
				'sku' => $sku,
			);
		}

		// Optionale Ausnahme: abweichende Artikelnummer direkt am Produkt hinterlegt.
		$overrides = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value AS artnr
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND pm.meta_value <> ''
				   AND p.post_status NOT IN ('trash','auto-draft')",
				BTS_META_ARTNR
			)
		);
		foreach ( $overrides as $r ) {
			$map[ self::key( $r->artnr ) ][] = array(
				'pid' => (int) $r->post_id,
				'via' => 'meta',
				'sku' => (string) $r->artnr,
			);
		}

		self::$cache = $map;
		return $map;
	}

	public static function for_article( $artnr ) {
		$map = self::map();
		return $map[ self::key( $artnr ) ] ?? array();
	}

	public static function matched_count( array $artnrs ) {
		$map = self::map();
		$n   = 0;
		foreach ( $artnrs as $a ) {
			if ( isset( $map[ self::key( $a ) ] ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Every article number the catalogue has ever seen, normalised.
	 *
	 * This is what tells a Bloomtech product apart from a product of some other
	 * supplier that happens to have a non-HJ SKU: only SKUs that appeared in an
	 * export at least once are considered Bloomtech goods, so an article that
	 * drops out of today's list can be recognised without touching anything else.
	 */
	public static function catalogue_keys() {
		global $wpdb;
		$rows = $wpdb->get_col( 'SELECT artnr FROM ' . BTS_Install::articles_table() );
		$out  = array();
		foreach ( $rows as $a ) {
			$out[ self::key( $a ) ] = true;
		}
		return $out;
	}

	public static function is_excluded( $pid ) {
		return get_post_meta( $pid, '_bloomtech_exclude', true ) === 'yes';
	}

	public static function key( $s ) {
		return mb_strtolower( trim( (string) $s ) );
	}
}
