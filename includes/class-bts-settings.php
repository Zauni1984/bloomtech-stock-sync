<?php
defined( 'ABSPATH' ) || exit;

/**
 * Option storage. Credentials are read from wp-config.php constants when present,
 * otherwise stored encrypted with the site's own auth salts.
 */
class BTS_Settings {

	const OPTION = 'bts_settings';

	public static function defaults() {
		return array(
			// Source.
			'source_mode'      => 'share',   // share | webdav
			'share_url'        => '',        // https://host/s/TOKEN  (öffentlicher Link)
			'share_password'   => '',
			'webdav_base'      => '',        // https://host/remote.php/dav/files/USER
			'webdav_user'      => '',
			'webdav_password'  => '',
			'remote_path'      => '/Bloomtech/export',
			'file_mode'        => 'newest',  // newest | exact
			'file_name'        => '',
			'file_pattern'     => '\.csv$',

			// CSV.
			'delimiter'        => 'auto',
			'encoding'         => 'auto',
			'decimal'          => 'auto',
			'has_header'       => 1,
			'col_artnr'        => '',
			'col_ean'          => '',
			'col_stock'        => '',
			'col_name'         => '',
			'col_brand'        => '',
			'col_price'        => '',

			// Sync behaviour.
			'enabled'          => 0,
			'interval'         => 'bts_6h',
			'buffer'           => 0,          // Sicherheitspuffer: so viel wird vom Lieferantenbestand abgezogen.
			'threshold'        => 0,          // Bestand <= Schwelle  =>  ausverkauft.
			'missing_action'   => 'ignore',   // ignore | zero | backorder
			'backorder_mode'   => 'notify',   // no | notify | yes  -> Verhalten bei Bestand 0
			'write_stock_qty'  => 1,
			'max_zero_ratio'   => 30,         // Notbremse in Prozent.
			'min_rows'         => 20,         // Notbremse: Mindestzeilen in der Datei.
			'max_file_age_h'   => 48,
			'notify_email'     => '',
			'notify_on'        => 'error',    // never | error | changes
			'log_keep_days'    => 30,
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function save( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Effective credential: wp-config constant wins over the database.
	 */
	public static function credential( $which ) {
		$map = array(
			'webdav_user'     => 'BLOOMTECH_DAV_USER',
			'webdav_password' => 'BLOOMTECH_DAV_PASS',
			'share_password'  => 'BLOOMTECH_SHARE_PASS',
			'share_url'       => 'BLOOMTECH_SHARE_URL',
		);
		if ( isset( $map[ $which ] ) && defined( $map[ $which ] ) && constant( $map[ $which ] ) !== '' ) {
			return (string) constant( $map[ $which ] );
		}
		$raw = self::get( $which, '' );
		if ( $raw === '' ) {
			return '';
		}
		return in_array( $which, array( 'webdav_password', 'share_password' ), true ) ? self::decrypt( $raw ) : (string) $raw;
	}

	public static function credential_is_constant( $which ) {
		$map = array(
			'webdav_user'     => 'BLOOMTECH_DAV_USER',
			'webdav_password' => 'BLOOMTECH_DAV_PASS',
			'share_password'  => 'BLOOMTECH_SHARE_PASS',
			'share_url'       => 'BLOOMTECH_SHARE_URL',
		);
		return isset( $map[ $which ] ) && defined( $map[ $which ] ) && constant( $map[ $which ] ) !== '';
	}

	private static function key() {
		$salt = ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ) . ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : '' );
		return hash( 'sha256', 'bts|' . $salt, true );
	}

	public static function encrypt( $plain ) {
		if ( $plain === '' || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return 'v1:' . base64_encode( $iv . $cipher );
	}

	public static function decrypt( $stored ) {
		if ( strpos( (string) $stored, 'v1:' ) !== 0 || ! function_exists( 'openssl_decrypt' ) ) {
			return (string) $stored;
		}
		$blob = base64_decode( substr( $stored, 3 ), true );
		if ( $blob === false || strlen( $blob ) <= 16 ) {
			return '';
		}
		$iv    = substr( $blob, 0, 16 );
		$plain = openssl_decrypt( substr( $blob, 16 ), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return $plain === false ? '' : $plain;
	}
}
