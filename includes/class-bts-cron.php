<?php
defined( 'ABSPATH' ) || exit;

class BTS_Cron {

	const HOOK = 'bts_sync_event';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_' . BTS_Settings::OPTION, array( __CLASS__, 'reschedule' ) );
	}

	public static function schedules( $s ) {
		$s['bts_2h']  = array(
			'interval' => 2 * HOUR_IN_SECONDS,
			'display'  => 'Alle 2 Stunden (12× täglich)',
		);
		$s['bts_4h']  = array(
			'interval' => 4 * HOUR_IN_SECONDS,
			'display'  => 'Alle 4 Stunden (6× täglich)',
		);
		$s['bts_6h']  = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => 'Alle 6 Stunden (4× täglich)',
		);
		$s['bts_8h']  = array(
			'interval' => 8 * HOUR_IN_SECONDS,
			'display'  => 'Alle 8 Stunden (3× täglich)',
		);
		$s['bts_12h'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => 'Alle 12 Stunden (2× täglich)',
		);
		return $s;
	}

	public static function reschedule() {
		self::unschedule();
		if ( ! BTS_Settings::get( 'enabled', 0 ) ) {
			return;
		}
		$interval = BTS_Settings::get( 'interval', 'bts_6h' );
		if ( ! isset( self::schedules( array() )[ $interval ] ) && ! in_array( $interval, array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$interval = 'bts_6h';
		}
		wp_schedule_event( time() + 300, $interval, self::HOOK );
	}

	public static function unschedule() {
		$ts = wp_next_scheduled( self::HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
			$ts = wp_next_scheduled( self::HOOK );
		}
	}

	public static function run() {
		if ( ! BTS_Settings::get( 'enabled', 0 ) ) {
			return;
		}
		if ( get_transient( 'bts_running' ) ) {
			return;
		}
		set_transient( 'bts_running', 1, 15 * MINUTE_IN_SECONDS );
		try {
			BTS_Sync::run( false );
		} finally {
			delete_transient( 'bts_running' );
		}
	}
}
