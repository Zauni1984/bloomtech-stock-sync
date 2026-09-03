<?php
defined( 'ABSPATH' ) || exit;

class BTS_Notifier {

	public static function maybe_send( array $report ) {
		$when = BTS_Settings::get( 'notify_on', 'error' );
		if ( $when === 'never' ) {
			return;
		}
		$has_problem = $report['aborted'] || ! empty( $report['errors'] );
		if ( $when === 'error' && ! $has_problem ) {
			return;
		}
		if ( $when === 'changes' && ! $has_problem && empty( $report['changes'] ) ) {
			return;
		}

		$to = BTS_Settings::get( 'notify_email', '' );
		if ( $to === '' ) {
			$to = get_option( 'admin_email' );
		}
		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = $report['aborted']
			? '[Bloomtech] Bestandsabgleich abgebrochen'
			: sprintf( '[Bloomtech] Bestandsabgleich: %d Änderungen', count( $report['changes'] ) );

		$l   = array();
		$l[] = 'Datei: ' . $report['file'];
		$l[] = 'Zeilen: ' . $report['rows'] . '  ·  Artikel: ' . $report['articles'];
		$l[] = 'Geändert: ' . $report['updated'] . '  ·  Unverändert: ' . $report['unchanged'] . '  ·  Übersprungen: ' . $report['skipped'];
		$l[] = 'Nicht mehr in der Liste: ' . $report['missing'];
		$l[] = '';
		if ( $report['errors'] ) {
			$l[] = 'Meldungen:';
			foreach ( $report['errors'] as $e ) {
				$l[] = '  · ' . $e;
			}
			$l[] = '';
		}
		if ( $report['changes'] ) {
			$l[] = 'Änderungen:';
			foreach ( array_slice( $report['changes'], 0, 100 ) as $c ) {
				$l[] = empty( $c['qty'] )
					? sprintf( '  · %s (%s) nur Status: %s  [%s]', $c['name'], $c['artnr'], $c['status'], $c['reason'] )
					: sprintf(
						'  · %s (%s) %s → %s  [%s]',
						$c['name'],
						$c['artnr'],
						$c['from'] === null ? '—' : $c['from'],
						$c['to'],
						$c['reason']
					);
			}
			if ( count( $report['changes'] ) > 100 ) {
				$l[] = sprintf( '  … und %d weitere', count( $report['changes'] ) - 100 );
			}
		}
		$l[] = '';
		$l[] = admin_url( 'admin.php?page=bts-log' );

		wp_mail( $to, $subject, implode( "\n", $l ) );
	}
}
