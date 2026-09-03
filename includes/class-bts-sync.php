<?php
defined( 'ABSPATH' ) || exit;

/**
 * The actual stock run.
 *
 * Two rules govern everything here:
 *   1. Only products that carry a Bloomtech article number are touched. Products
 *      stocked by the shop itself (Biobizz and friends) simply have no article
 *      number and are therefore invisible to this plugin.
 *   2. Only stock quantity and stock status are ever written. Price, texts,
 *      status, slug, categories and attributes are never modified.
 */
class BTS_Sync {

	/**
	 * @param bool $dry_run When true nothing is written; the report shows what would happen.
	 */
	public static function run( $dry_run = false ) {
		$run_id = BTS_Logger::start_run();
		$s      = BTS_Settings::all();
		$report = array(
			'run_id'   => $run_id,
			'dry_run'  => $dry_run,
			'started'  => current_time( 'mysql' ),
			'file'     => '',
			'rows'     => 0,
			'articles' => 0,
			'new_art'  => 0,
			'updated'  => 0,
			'unchanged'=> 0,
			'to_zero'  => 0,
			'missing'  => 0,
			'skipped'  => 0,
			'changes'  => array(),
			'errors'   => array(),
			'aborted'  => false,
		);

		BTS_Logger::info( $dry_run ? 'Trockenlauf gestartet.' : 'Abgleich gestartet.' );

		/* ---------------------------------------------------- 1. Datei holen */
		$file = BTS_Source::fetch();
		if ( is_wp_error( $file ) ) {
			return self::fail( $report, $file->get_error_message() );
		}
		$report['file'] = $file['filename'];
		BTS_Logger::info( sprintf( 'Datei geladen: %s (%s KB)', $file['filename'], number_format_i18n( strlen( $file['body'] ) / 1024, 1 ) ) );

		$max_age = (int) $s['max_file_age_h'];
		if ( $max_age > 0 && $file['modified'] > 0 ) {
			$age_h = ( time() - $file['modified'] ) / 3600;
			if ( $age_h > $max_age ) {
				BTS_Logger::warn( sprintf( 'Die Datei ist %s Stunden alt (Grenze: %d). Der Abgleich läuft trotzdem, aber die Zahlen sind womöglich veraltet.', number_format_i18n( $age_h, 1 ), $max_age ) );
				$report['errors'][] = sprintf( 'Warnung: Die Bestandsliste ist %s Stunden alt.', number_format_i18n( $age_h, 1 ) );
			}
		}

		/* ---------------------------------------------------- 2. CSV lesen */
		$csv = BTS_CSV::parse( $file['body'] );
		if ( is_wp_error( $csv ) ) {
			return self::fail( $report, $csv->get_error_message() );
		}
		$report['rows'] = count( $csv['rows'] );

		$idx = self::column_indexes( $csv['header'] );
		if ( $idx['artnr'] === null ) {
			return self::fail( $report, 'Die Spalte mit der Artikelnummer ist nicht zugeordnet. Bitte in den Einstellungen unter „Spalten zuordnen" festlegen.' );
		}

		/* ---------------------------------------------------- 3. Notbremse: zu wenig Zeilen */
		$min_rows = (int) $s['min_rows'];
		if ( $min_rows > 0 && $report['rows'] < $min_rows ) {
			return self::fail(
				$report,
				sprintf(
					'Abbruch aus Sicherheitsgründen: Die Datei enthält nur %d Zeilen, erwartet werden mindestens %d. Es wurde nichts geändert.',
					$report['rows'],
					$min_rows
				)
			);
		}

		/* ---------------------------------------------------- 4. Artikel einlesen */
		$articles = array();
		foreach ( $csv['rows'] as $row ) {
			$artnr = self::cell( $row, $idx['artnr'] );
			if ( $artnr === '' ) {
				continue;
			}
			$stock_raw = $idx['stock'] !== null ? self::cell( $row, $idx['stock'] ) : '';
			$avail     = BTS_CSV::to_availability( $stock_raw );
			$stock     = BTS_CSV::to_number( $stock_raw, $s['decimal'] );
			if ( $stock === null && $avail !== null ) {
				$stock = $avail ? 999 : 0;   // Textzustand in eine Zahl übersetzen
			}
			$articles[ $artnr ] = array(
				'artnr' => $artnr,
				'ean'   => $idx['ean'] !== null ? self::cell( $row, $idx['ean'] ) : '',
				'name'  => $idx['name'] !== null ? self::cell( $row, $idx['name'] ) : '',
				'brand' => $idx['brand'] !== null ? self::cell( $row, $idx['brand'] ) : '',
				'stock' => $stock,
				'price' => $idx['price'] !== null ? BTS_CSV::to_number( self::cell( $row, $idx['price'] ), $s['decimal'] ) : null,
				'raw'   => array_combine(
					array_slice( $csv['header'], 0, count( $row ) ),
					array_slice( $row, 0, count( $csv['header'] ) )
				) ?: array(),
			);
		}
		$report['articles'] = count( $articles );
		if ( ! $articles ) {
			return self::fail( $report, 'In der Datei stand keine einzige verwertbare Artikelnummer. Stimmt die Spaltenzuordnung?' );
		}

		/* ---------------------------------------------------- 5. Katalog pflegen */
		if ( ! $dry_run ) {
			$cat                = BTS_Catalog::upsert_many( $articles );
			$report['new_art']  = $cat['new'];
			if ( $cat['new'] > 0 ) {
				BTS_Logger::info( sprintf( '%d neue Artikelnummern in den Katalog aufgenommen.', $cat['new'] ) );
			}
		} else {
			$known = BTS_Catalog::linked_artnrs();
			foreach ( $articles as $a ) {
				if ( ! BTS_Catalog::get( $a['artnr'] ) ) {
					++$report['new_art'];
				}
			}
			unset( $known );
		}

		/* ---------------------------------------------------- 6. Verknüpfte Produkte bestimmen */
		$linked = BTS_Catalog::linked_artnrs();
		if ( ! $linked ) {
			BTS_Logger::warn( 'Es ist noch kein Produkt mit einer Bloomtech-Artikelnummer verknüpft — es gibt nichts abzugleichen.' );
			$report['errors'][] = 'Noch kein Produkt verknüpft. Der Katalog wurde eingelesen, es wurde aber kein Bestand geändert.';
			return self::finish( $report );
		}

		/* ---------------------------------------------------- 7. Planen (erst rechnen, dann schreiben) */
		$plan = array();
		foreach ( $linked as $artnr => $post_ids ) {
			$has = isset( $articles[ $artnr ] );
			foreach ( $post_ids as $pid ) {
				if ( get_post_meta( $pid, '_bloomtech_exclude', true ) === 'yes' ) {
					++$report['skipped'];
					continue;
				}
				$product = wc_get_product( $pid );
				if ( ! $product ) {
					continue;
				}
				if ( ! $has ) {
					++$report['missing'];
					if ( $s['missing_action'] === 'ignore' ) {
						continue;
					}
					$target = 0;
				} else {
					$target = $articles[ $artnr ]['stock'];
					if ( $target === null ) {
						++$report['skipped'];
						continue; // keine verwertbare Bestandsangabe -> nichts anfassen
					}
					$target = max( 0, (float) $target - (float) $s['buffer'] );
					if ( $target <= (float) $s['threshold'] ) {
						$target = 0;
					}
				}
				$plan[] = array(
					'pid'     => $pid,
					'artnr'   => $artnr,
					'product' => $product,
					'target'  => (int) round( $target ),
					'missing' => ! $has,
				);
				if ( (int) round( $target ) === 0 ) {
					++$report['to_zero'];
				}
			}
		}

		/* ---------------------------------------------------- 8. Notbremse: zu viele Nullstellungen */
		$ratio = $plan ? ( $report['to_zero'] / count( $plan ) ) * 100 : 0;
		$maxr  = (float) $s['max_zero_ratio'];
		if ( $maxr > 0 && $ratio > $maxr && count( $plan ) >= 5 ) {
			return self::fail(
				$report,
				sprintf(
					'Abbruch aus Sicherheitsgründen: %s %% der verknüpften Produkte (%d von %d) würden auf „ausverkauft" gesetzt — die Grenze liegt bei %s %%. Das deutet auf einen fehlerhaften Export hin. Es wurde nichts geändert.',
					number_format_i18n( $ratio, 1 ),
					$report['to_zero'],
					count( $plan ),
					number_format_i18n( $maxr, 0 )
				)
			);
		}

		/* ---------------------------------------------------- 9. Schreiben */
		foreach ( $plan as $p ) {
			$product = $p['product'];
			$before  = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null;
			$b_stat  = $product->get_stock_status();
			$target  = $p['target'];
			$status  = $target > 0 ? 'instock' : self::zero_status( $s['backorder_mode'] );

			$changed = ( $before !== $target ) || ( $b_stat !== $status ) || ! $product->get_manage_stock();
			if ( ! $changed ) {
				++$report['unchanged'];
				continue;
			}

			$report['changes'][] = array(
				'pid'    => $p['pid'],
				'artnr'  => $p['artnr'],
				'name'   => $product->get_name(),
				'sku'    => $product->get_sku(),
				'from'   => $before,
				'to'     => $target,
				'status' => $b_stat . ' → ' . $status,
				'reason' => $p['missing'] ? 'nicht mehr in der Liste' : 'Bestandsmeldung',
			);
			++$report['updated'];

			if ( $dry_run ) {
				continue;
			}

			try {
				if ( (int) $s['write_stock_qty'] ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( $target );
					$product->set_backorders( $target > 0 ? $product->get_backorders() : self::backorders( $s['backorder_mode'] ) );
				}
				$product->set_stock_status( $status );
				$product->save();
			} catch ( Exception $e ) {
				$report['errors'][] = sprintf( '#%d (%s): %s', $p['pid'], $p['artnr'], $e->getMessage() );
				BTS_Logger::error( sprintf( 'Produkt #%d konnte nicht gespeichert werden: %s', $p['pid'], $e->getMessage() ) );
			}
		}

		if ( ! $dry_run && $report['updated'] > 0 ) {
			wc_delete_product_transients();
		}

		return self::finish( $report );
	}

	private static function zero_status( $mode ) {
		return $mode === 'no' ? 'outofstock' : 'onbackorder';
	}

	private static function backorders( $mode ) {
		if ( $mode === 'yes' ) {
			return 'yes';
		}
		if ( $mode === 'notify' ) {
			return 'notify';
		}
		return 'no';
	}

	private static function cell( array $row, $i ) {
		return isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
	}

	/** Maps the configured column headers onto their position in this file. */
	public static function column_indexes( array $header ) {
		$norm = array();
		foreach ( $header as $i => $h ) {
			$norm[ self::normalise( $h ) ] = $i;
		}
		$out = array();
		foreach ( array( 'artnr', 'ean', 'stock', 'name', 'brand', 'price' ) as $key ) {
			$want          = self::normalise( (string) BTS_Settings::get( 'col_' . $key, '' ) );
			$out[ $key ] = ( $want !== '' && isset( $norm[ $want ] ) ) ? $norm[ $want ] : null;
		}
		return $out;
	}

	private static function normalise( $s ) {
		$s = mb_strtolower( trim( (string) $s ) );
		return preg_replace( '/[^a-z0-9äöüß]/u', '', $s );
	}

	private static function fail( array $report, $message ) {
		$report['aborted']  = true;
		$report['errors'][] = $message;
		BTS_Logger::error( $message );
		return self::finish( $report );
	}

	private static function finish( array $report ) {
		$report['finished'] = current_time( 'mysql' );
		if ( ! $report['dry_run'] ) {
			update_option( 'bts_last_run', $report, false );
			update_option( 'bts_last_run_time', time(), false );
			BTS_Logger::info(
				sprintf(
					'Fertig. %d Artikel in der Datei, %d Produkte geändert, %d unverändert, %d ohne Bestandsangabe übersprungen.',
					$report['articles'],
					$report['updated'],
					$report['unchanged'],
					$report['skipped']
				)
			);
			BTS_Logger::purge();
			BTS_Notifier::maybe_send( $report );
		}
		return $report;
	}
}
