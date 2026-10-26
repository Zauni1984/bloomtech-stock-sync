<?php
defined( 'ABSPATH' ) || exit;

class BTS_Logger {

	private static $run_id = '';

	public static function start_run() {
		self::$run_id = substr( md5( uniqid( '', true ) ), 0, 12 );
		return self::$run_id;
	}

	public static function run_id() {
		if ( self::$run_id === '' ) {
			self::start_run();
		}
		return self::$run_id;
	}

	public static function log( $msg, $level = 'info' ) {
		global $wpdb;
		$wpdb->insert(
			BTS_Install::log_table(),
			array(
				'run_id' => self::run_id(),
				'ts'     => current_time( 'mysql' ),
				'level'  => $level,
				'msg'    => is_scalar( $msg ) ? (string) $msg : wp_json_encode( $msg ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	public static function info( $m ) {
		self::log( $m, 'info' ); }
	public static function warn( $m ) {
		self::log( $m, 'warn' ); }
	public static function error( $m ) {
		self::log( $m, 'error' ); }

	public static function purge() {
		global $wpdb;
		$days = max( 1, (int) BTS_Settings::get( 'log_keep_days', 30 ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . BTS_Install::log_table() . ' WHERE ts < DATE_SUB(NOW(), INTERVAL %d DAY)', $days ) );
	}

	public static function recent( $limit = 300 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . BTS_Install::log_table() . ' ORDER BY id DESC LIMIT %d', (int) $limit ) );
	}
}
