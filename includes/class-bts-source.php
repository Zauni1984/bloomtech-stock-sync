<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fetches the stock file from Nextcloud.
 *
 * Preferred mode is a public share link — no account credentials are stored in
 * WordPress at all, and the link can be revoked in Nextcloud with one click.
 * A public share also speaks WebDAV via /public.php/webdav, which lets us list a
 * shared folder and pick the newest export automatically.
 */
class BTS_Source {

	/** @return array{body:string,filename:string,modified:int}|WP_Error */
	public static function fetch() {
		$mode = BTS_Settings::get( 'source_mode', 'share' );
		return $mode === 'webdav' ? self::fetch_webdav() : self::fetch_share();
	}

	/* ---------------------------------------------------------------- share */

	public static function parse_share_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return null;
		}
		// Accepted: https://host/s/TOKEN , .../s/TOKEN/download , .../index.php/s/TOKEN
		if ( ! preg_match( '#^(https?://[^/]+)(?:/index\.php)?/s/([A-Za-z0-9]+)#', $url, $m ) ) {
			return null;
		}
		return array(
			'host'  => $m[1],
			'token' => $m[2],
		);
	}

	private static function fetch_share() {
		$share = self::parse_share_url( BTS_Settings::credential( 'share_url' ) );
		if ( ! $share ) {
			return new WP_Error( 'bts_share_url', 'Der Freigabe-Link ist leer oder hat nicht die Form https://…/s/TOKEN' );
		}
		$pass = BTS_Settings::credential( 'share_password' );

		if ( BTS_Settings::get( 'file_mode', 'newest' ) === 'exact' && BTS_Settings::get( 'file_name', '' ) === '' ) {
			// Share points directly at one file.
			$res = self::http_get( $share['host'] . '/s/' . $share['token'] . '/download', $pass ? array( $share['token'], $pass ) : null );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return array(
				'body'     => $res['body'],
				'filename' => self::filename_from_headers( $res['headers'], 'freigabe.csv' ),
				'modified' => self::modified_from_headers( $res['headers'] ),
			);
		}

		// Folder share: list via public WebDAV, then download the chosen file.
		$dav  = $share['host'] . '/public.php/webdav/';
		$auth = array( $share['token'], $pass );

		$list = self::propfind( $dav, $auth );
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$pick = self::pick_file( $list );
		if ( is_wp_error( $pick ) ) {
			return $pick;
		}

		$res = self::http_get( $dav . rawurlencode( $pick['name'] ), $auth );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'body'     => $res['body'],
			'filename' => $pick['name'],
			'modified' => $pick['modified'],
		);
	}

	/* ---------------------------------------------------------------- webdav */

	private static function fetch_webdav() {
		$base = rtrim( (string) BTS_Settings::get( 'webdav_base', '' ), '/' );
		$user = BTS_Settings::credential( 'webdav_user' );
		$pass = BTS_Settings::credential( 'webdav_password' );
		if ( $base === '' || $user === '' || $pass === '' ) {
			return new WP_Error( 'bts_webdav_cfg', 'WebDAV-Adresse, Benutzer oder App-Passwort fehlt.' );
		}
		$auth = array( $user, $pass );
		$dir  = '/' . trim( (string) BTS_Settings::get( 'remote_path', '' ), '/' );
		$dir  = $dir === '/' ? '' : $dir;

		if ( BTS_Settings::get( 'file_mode', 'newest' ) === 'exact' ) {
			$name = (string) BTS_Settings::get( 'file_name', '' );
			if ( $name === '' ) {
				return new WP_Error( 'bts_webdav_file', 'Es ist kein Dateiname hinterlegt.' );
			}
			$res = self::http_get( $base . self::encode_path( $dir . '/' . $name ), $auth );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return array(
				'body'     => $res['body'],
				'filename' => $name,
				'modified' => self::modified_from_headers( $res['headers'] ),
			);
		}

		$list = self::propfind( $base . self::encode_path( $dir ) . '/', $auth );
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$pick = self::pick_file( $list );
		if ( is_wp_error( $pick ) ) {
			return $pick;
		}
		$res = self::http_get( $base . self::encode_path( $dir . '/' . $pick['name'] ), $auth );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'body'     => $res['body'],
			'filename' => $pick['name'],
			'modified' => $pick['modified'],
		);
	}

	/* ---------------------------------------------------------------- helpers */

	private static function encode_path( $path ) {
		$parts = array_map( 'rawurlencode', explode( '/', ltrim( $path, '/' ) ) );
		return '/' . implode( '/', $parts );
	}

	/** Newest file matching the configured pattern. */
	private static function pick_file( array $files ) {
		$pattern = (string) BTS_Settings::get( 'file_pattern', '\.csv$' );
		$name    = (string) BTS_Settings::get( 'file_name', '' );
		$exact   = BTS_Settings::get( 'file_mode', 'newest' ) === 'exact';

		$hits = array();
		foreach ( $files as $f ) {
			if ( $f['is_dir'] ) {
				continue;
			}
			if ( $exact ) {
				if ( $f['name'] === $name ) {
					return $f;
				}
				continue;
			}
			if ( $pattern === '' || @preg_match( '#' . $pattern . '#i', $f['name'] ) === 1 ) {
				$hits[] = $f;
			}
		}
		if ( ! $hits ) {
			return new WP_Error( 'bts_no_file', $exact ? sprintf( 'Die Datei „%s" wurde im Ordner nicht gefunden.', $name ) : 'Im Ordner liegt keine Datei, die zum Muster passt.' );
		}
		usort(
			$hits,
			static function ( $a, $b ) {
				return $b['modified'] <=> $a['modified'];
			}
		);
		return $hits[0];
	}

	/** @return array<int,array{name:string,modified:int,size:int,is_dir:bool}>|WP_Error */
	public static function propfind( $url, $auth ) {
		$args = array(
			'method'  => 'PROPFIND',
			'timeout' => 45,
			'headers' => array(
				'Depth'        => '1',
				'Content-Type' => 'application/xml; charset=utf-8',
			),
			'body'    => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/><d:getlastmodified/><d:getcontentlength/><d:resourcetype/></d:prop></d:propfind>',
		);
		if ( $auth ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( $auth[0] . ':' . $auth[1] );
		}
		$resp = wp_remote_request( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code === 401 || $code === 403 ) {
			return new WP_Error( 'bts_auth', 'Nextcloud hat den Zugriff abgelehnt (HTTP ' . $code . '). Ist der Link noch gültig bzw. das Freigabe-Passwort richtig?' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'bts_http', 'Ordner konnte nicht gelesen werden (HTTP ' . $code . ').' );
		}

		$xml = @simplexml_load_string( wp_remote_retrieve_body( $resp ) );
		if ( ! $xml ) {
			return new WP_Error( 'bts_xml', 'Die Antwort von Nextcloud war kein gültiges XML.' );
		}
		$ns    = $xml->getNamespaces( true );
		$dav   = isset( $ns['d'] ) ? 'd' : ( isset( $ns['D'] ) ? 'D' : 'd' );
		$out   = array();
		$first = true;
		foreach ( $xml->children( $ns[ $dav ] ?? 'DAV:' )->response as $r ) {
			if ( $first ) {          // erste Antwort ist der Ordner selbst
				$first = false;
				continue;
			}
			$p      = $r->propstat->prop;
			$href   = rawurldecode( (string) $r->href );
			$name   = (string) ( $p->displayname ?? '' );
			if ( $name === '' ) {
				$name = basename( rtrim( $href, '/' ) );
			}
			$is_dir = isset( $p->resourcetype->collection );
			$out[]  = array(
				'name'     => $name,
				'modified' => strtotime( (string) ( $p->getlastmodified ?? '' ) ) ?: 0,
				'size'     => (int) ( $p->getcontentlength ?? 0 ),
				'is_dir'   => $is_dir,
			);
		}
		return $out;
	}

	private static function http_get( $url, $auth = null ) {
		$args = array(
			'timeout'     => 90,
			'redirection' => 5,
			'headers'     => array( 'Accept' => 'text/csv,application/octet-stream,*/*' ),
		);
		if ( $auth ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( $auth[0] . ':' . $auth[1] );
		}
		$resp = wp_remote_get( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code === 401 || $code === 403 ) {
			return new WP_Error( 'bts_auth', 'Nextcloud hat den Download abgelehnt (HTTP ' . $code . '). Link abgelaufen oder Passwort falsch?' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'bts_http', 'Download fehlgeschlagen (HTTP ' . $code . ').' );
		}
		$body = wp_remote_retrieve_body( $resp );
		if ( trim( $body ) === '' ) {
			return new WP_Error( 'bts_empty', 'Die heruntergeladene Datei ist leer.' );
		}
		if ( stripos( ltrim( $body ), '<!doctype html' ) === 0 || stripos( ltrim( $body ), '<html' ) === 0 ) {
			return new WP_Error( 'bts_html', 'Es kam eine HTML-Seite statt einer Datei zurück — der Link zeigt vermutlich auf die Weboberfläche statt auf die Datei, oder die Freigabe verlangt ein Passwort.' );
		}
		return array(
			'body'    => $body,
			'headers' => wp_remote_retrieve_headers( $resp ),
		);
	}

	private static function filename_from_headers( $headers, $fallback ) {
		$cd = is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ? $headers->offsetGet( 'content-disposition' ) : ( $headers['content-disposition'] ?? '' );
		if ( $cd && preg_match( '/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', (string) $cd, $m ) ) {
			return rawurldecode( $m[1] );
		}
		return $fallback;
	}

	private static function modified_from_headers( $headers ) {
		$lm = is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ? $headers->offsetGet( 'last-modified' ) : ( $headers['last-modified'] ?? '' );
		return $lm ? ( strtotime( (string) $lm ) ?: 0 ) : 0;
	}
}
