<?php
defined( 'ABSPATH' ) || exit;

/**
 * CSV reader that copes with whatever a supplier throws at it:
 * unknown delimiter, unknown encoding, German or English decimal separators.
 */
class BTS_CSV {

	/** @return array{header:array<int,string>,rows:array<int,array<int,string>>,delimiter:string,encoding:string}|WP_Error */
	public static function parse( $raw ) {
		$enc_opt = BTS_Settings::get( 'encoding', 'auto' );
		$text    = self::to_utf8( $raw, $enc_opt, $encoding_used );

		$delim_opt = BTS_Settings::get( 'delimiter', 'auto' );
		$delim     = $delim_opt === 'auto' ? self::sniff_delimiter( $text ) : self::delim_char( $delim_opt );

		$fh = fopen( 'php://temp', 'r+' );
		if ( ! $fh ) {
			return new WP_Error( 'bts_csv_mem', 'Die Datei konnte nicht zwischengespeichert werden.' );
		}
		fwrite( $fh, $text );
		rewind( $fh );

		$rows = array();
		while ( ( $r = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) {
			if ( $r === array( null ) ) {
				continue; // Leerzeile
			}
			$rows[] = array_map( static function ( $v ) {
				return trim( (string) $v, " \t\n\r\0\x0B\xC2\xA0" );
			}, $r );
		}
		fclose( $fh );

		if ( ! $rows ) {
			return new WP_Error( 'bts_csv_empty', 'In der Datei wurde keine einzige Zeile gefunden.' );
		}

		$header = array();
		if ( BTS_Settings::get( 'has_header', 1 ) ) {
			$header = array_shift( $rows );
			$header = array_map(
				static function ( $h ) {
					return trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) );
				},
				$header
			);
		} else {
			$n = count( $rows[0] );
			for ( $i = 0; $i < $n; $i++ ) {
				$header[] = 'Spalte ' . ( $i + 1 );
			}
		}

		return array(
			'header'    => $header,
			'rows'      => $rows,
			'delimiter' => $delim,
			'encoding'  => $encoding_used,
		);
	}

	private static function delim_char( $opt ) {
		$map = array(
			'semicolon' => ';',
			'comma'     => ',',
			'tab'       => "\t",
			'pipe'      => '|',
		);
		return $map[ $opt ] ?? ';';
	}

	public static function sniff_delimiter( $text ) {
		$sample = substr( $text, 0, 65536 );
		$lines  = preg_split( '/\r\n|\r|\n/', $sample );
		$lines  = array_slice( array_filter( $lines, 'strlen' ), 0, 12 );
		$best   = ';';
		$score  = -1;
		foreach ( array( ';', ',', "\t", '|' ) as $d ) {
			$counts = array();
			foreach ( $lines as $l ) {
				$counts[] = substr_count( $l, $d );
			}
			if ( ! $counts ) {
				continue;
			}
			$avg = array_sum( $counts ) / count( $counts );
			if ( $avg < 1 ) {
				continue;
			}
			// Konsistenz über die Zeilen zählt mehr als schiere Menge.
			$var = 0;
			foreach ( $counts as $c ) {
				$var += ( $c - $avg ) ** 2;
			}
			$var /= count( $counts );
			$s    = $avg - $var * 2;
			if ( $s > $score ) {
				$score = $s;
				$best  = $d;
			}
		}
		return $best;
	}

	public static function to_utf8( $raw, $opt = 'auto', &$used = null ) {
		// BOM entfernen.
		if ( strncmp( $raw, "\xEF\xBB\xBF", 3 ) === 0 ) {
			$used = 'UTF-8 (BOM)';
			return substr( $raw, 3 );
		}
		if ( $opt !== 'auto' ) {
			$used = $opt;
			return $opt === 'UTF-8' ? $raw : self::convert( $raw, $opt );
		}
		if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $raw, 'UTF-8' ) ) {
			$used = 'UTF-8';
			return $raw;
		}
		$used = 'Windows-1252';
		return self::convert( $raw, 'Windows-1252' );
	}

	private static function convert( $raw, $from ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $raw, 'UTF-8', $from );
		}
		if ( function_exists( 'iconv' ) ) {
			$r = @iconv( $from, 'UTF-8//TRANSLIT', $raw );
			if ( $r !== false ) {
				return $r;
			}
		}
		return $raw;
	}

	/**
	 * Turns "1.234,50", "1,234.50", "12 Stk", "> 100", "auf Anfrage" into a number
	 * (or null when the cell genuinely carries no quantity).
	 */
	public static function to_number( $value, $decimal = 'auto' ) {
		$v = trim( (string) $value );
		if ( $v === '' ) {
			return null;
		}
		$low = mb_strtolower( $v );
		foreach ( array( 'ja', 'yes', 'verfügbar', 'verfugbar', 'lieferbar', 'auf lager', 'in stock', 'x' ) as $t ) {
			if ( $low === $t ) {
				return null; // Textzustand — siehe to_availability()
			}
		}
		$v = preg_replace( '/[^\d,.\-]/u', '', $v );
		if ( $v === '' || $v === '-' ) {
			return null;
		}
		// Ein Trennzeichen mit genau drei Ziffern dahinter ist ein Tausenderzeichen,
		// kein Dezimaltrennzeichen: "1.250" und "1,250" ergeben beide 1250.
		// Die führende Ziffer muss 1–9 sein, damit "0.500" weiterhin 0,5 bleibt.
		if ( $decimal === 'auto' && preg_match( '/^-?[1-9]\\d{0,2}(?:[.,]\\d{3})+$/', $v ) ) {
			return (float) str_replace( array( '.', ',' ), '', $v );
		}

		$has_c = strpos( $v, ',' ) !== false;
		$has_d = strpos( $v, '.' ) !== false;

		if ( $decimal === 'comma' || ( $decimal === 'auto' && $has_c && ! $has_d ) ) {
			$v = str_replace( array( '.', ' ' ), '', $v );
			$v = str_replace( ',', '.', $v );
		} elseif ( $decimal === 'dot' || ( $decimal === 'auto' && $has_d && ! $has_c ) ) {
			$v = str_replace( ',', '', $v );
		} elseif ( $has_c && $has_d ) {
			// Das zuletzt stehende Zeichen ist das Dezimaltrennzeichen.
			$v = strrpos( $v, ',' ) > strrpos( $v, '.' )
				? str_replace( ',', '.', str_replace( '.', '', $v ) )
				: str_replace( ',', '', $v );
		}
		return is_numeric( $v ) ? (float) $v : null;
	}

	/**
	 * Some suppliers ship words instead of counts. Returns true/false/null.
	 */
	public static function to_availability( $value ) {
		$v = mb_strtolower( trim( (string) $value ) );
		if ( $v === '' ) {
			return null;
		}
		$yes = array( 'ja', 'yes', 'true', 'verfügbar', 'verfugbar', 'lieferbar', 'auf lager', 'in stock', 'vorrätig', 'vorratig', 'x' );
		$no  = array( 'nein', 'no', 'false', 'nicht verfügbar', 'nicht verfugbar', 'nicht lieferbar', 'ausverkauft', 'out of stock', 'vergriffen', 'ausgelistet' );
		if ( in_array( $v, $yes, true ) ) {
			return true;
		}
		if ( in_array( $v, $no, true ) ) {
			return false;
		}
		return null;
	}
}
