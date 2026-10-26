<?php
defined( 'ABSPATH' ) || exit;

class BTS_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_bts_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_bts_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_bts_run', array( __CLASS__, 'handle_run' ) );
	}

	public static function menu() {
		add_menu_page( 'Bloomtech', 'Bloomtech', self::CAP, 'bts', array( __CLASS__, 'page_settings' ), 'dashicons-update', 56 );
		add_submenu_page( 'bts', 'Einstellungen', 'Einstellungen', self::CAP, 'bts', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'bts', 'Artikelkatalog', 'Artikelkatalog', self::CAP, 'bts-catalog', array( __CLASS__, 'page_catalog' ) );
		add_submenu_page( 'bts', 'Protokoll', 'Protokoll', self::CAP, 'bts-log', array( __CLASS__, 'page_log' ) );
	}

	private static function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
	}

	private static function back( $page, $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	/* ============================================================ Speichern */

	public static function handle_save() {
		self::guard();
		check_admin_referer( 'bts_save' );
		$in  = wp_unslash( $_POST );
		$out = array();

		foreach ( array( 'source_mode', 'file_mode', 'delimiter', 'encoding', 'decimal', 'interval', 'missing_action', 'backorder_mode', 'notify_on' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $in[ $k ] );
			}
		}
		foreach ( array( 'remote_path', 'file_name', 'file_pattern', 'webdav_base', 'webdav_user', 'exclude_sku_prefix', 'col_artnr', 'col_ean', 'col_stock', 'col_name', 'col_brand', 'col_price' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $in[ $k ] );
			}
		}
		if ( isset( $in['share_url'] ) ) {
			$out['share_url'] = esc_url_raw( trim( $in['share_url'] ) );
		}
		if ( isset( $in['notify_email'] ) ) {
			$out['notify_email'] = sanitize_email( $in['notify_email'] );
		}
		foreach ( array( 'enabled', 'has_header', 'write_stock_qty' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? 1 : 0;
		}
		foreach ( array( 'buffer', 'threshold', 'max_zero_ratio', 'min_rows', 'max_file_age_h', 'log_keep_days' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = max( 0, (float) str_replace( ',', '.', $in[ $k ] ) );
			}
		}
		// Passwörter nur überschreiben, wenn wirklich etwas eingegeben wurde.
		foreach ( array( 'share_password', 'webdav_password' ) as $k ) {
			if ( isset( $in[ $k ] ) && $in[ $k ] !== '' ) {
				$out[ $k ] = BTS_Settings::encrypt( $in[ $k ] );
			}
			if ( isset( $in[ $k . '_clear' ] ) ) {
				$out[ $k ] = '';
			}
		}

		BTS_Settings::save( $out );
		BTS_Cron::reschedule();
		self::back( 'bts', array( 'saved' => 1 ) );
	}

	/* ============================================================ Test */

	public static function handle_test() {
		self::guard();
		check_admin_referer( 'bts_test' );

		$file = BTS_Source::fetch();
		if ( is_wp_error( $file ) ) {
			set_transient( 'bts_test_result', array( 'error' => $file->get_error_message() ), 300 );
			self::back( 'bts', array( 'tested' => 1 ) );
		}
		$csv = BTS_CSV::parse( $file['body'] );
		if ( is_wp_error( $csv ) ) {
			set_transient( 'bts_test_result', array( 'error' => $csv->get_error_message() ), 300 );
			self::back( 'bts', array( 'tested' => 1 ) );
		}
		update_option( 'bts_last_header', $csv['header'], false );
		set_transient(
			'bts_test_result',
			array(
				'file'      => $file['filename'],
				'modified'  => $file['modified'],
				'size'      => strlen( $file['body'] ),
				'delimiter' => $csv['delimiter'] === "\t" ? 'Tabulator' : $csv['delimiter'],
				'encoding'  => $csv['encoding'],
				'header'    => $csv['header'],
				'rows'      => count( $csv['rows'] ),
				'sample'    => array_slice( $csv['rows'], 0, 5 ),
			),
			600
		);
		self::back( 'bts', array( 'tested' => 1 ) );
	}

	/* ============================================================ Lauf */

	public static function handle_run() {
		self::guard();
		check_admin_referer( 'bts_run' );
		$dry = ! empty( $_POST['dry'] );
		set_transient( 'bts_run_result', BTS_Sync::run( $dry ), 900 );
		self::back( 'bts-log', array( 'ran' => 1 ) );
	}

	/* ============================================================ Seiten */

	public static function page_settings() {
		self::guard();
		$s      = BTS_Settings::all();
		$header = get_option( 'bts_last_header', array() );
		$test   = get_transient( 'bts_test_result' );
		$next   = wp_next_scheduled( BTS_Cron::HOOK );
		require BTS_PATH . 'views/settings.php';
	}

	public static function page_catalog() {
		self::guard();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';
		$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per    = 50;
		BTS_Matcher::flush();
		$data   = BTS_Catalog::query( $search, $filter, $per, ( $paged - 1 ) * $per );
		require BTS_PATH . 'views/catalog.php';
	}

	public static function page_log() {
		self::guard();
		$run  = get_transient( 'bts_run_result' );
		$last = get_option( 'bts_last_run', array() );
		$rows = BTS_Logger::recent( 200 );
		require BTS_PATH . 'views/log.php';
	}

	/* ============================================================ Hilfen */

	public static function col_select( $name, $value, $header ) {
		if ( ! $header ) {
			printf(
				'<input type="text" name="%s" value="%s" class="regular-text" placeholder="Erst „Verbindung testen" – dann erscheint hier eine Auswahlliste">',
				esc_attr( $name ),
				esc_attr( $value )
			);
			return;
		}
		echo '<select name="' . esc_attr( $name ) . '"><option value="">— nicht verwenden —</option>';
		foreach ( $header as $h ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $h ),
				selected( $h, $value, false ),
				esc_html( $h )
			);
		}
		echo '</select>';
	}
}
