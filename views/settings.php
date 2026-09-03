<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
<h1>Bloomtech Stock Sync</h1>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>
<?php endif; ?>

<?php if ( $test ) : ?>
	<?php if ( ! empty( $test['error'] ) ) : ?>
		<div class="notice notice-error"><p><strong>Verbindung fehlgeschlagen:</strong> <?php echo esc_html( $test['error'] ); ?></p></div>
	<?php else : ?>
		<div class="notice notice-success">
			<p><strong>Verbindung steht.</strong>
				Datei <code><?php echo esc_html( $test['file'] ); ?></code>,
				<?php echo esc_html( number_format_i18n( $test['size'] / 1024, 1 ) ); ?> KB,
				<?php echo (int) $test['rows']; ?> Datenzeilen,
				Trennzeichen <code><?php echo esc_html( $test['delimiter'] ); ?></code>,
				Zeichensatz <?php echo esc_html( $test['encoding'] ); ?>
				<?php if ( ! empty( $test['modified'] ) ) : ?>
					, Stand <?php echo esc_html( date_i18n( 'd.m.Y H:i', $test['modified'] ) ); ?>
				<?php endif; ?>
			</p>
			<div style="overflow-x:auto;max-width:100%">
			<table class="widefat striped" style="width:auto">
				<thead><tr><?php foreach ( $test['header'] as $h ) : ?><th><?php echo esc_html( $h ); ?></th><?php endforeach; ?></tr></thead>
				<tbody>
				<?php foreach ( $test['sample'] as $r ) : ?>
					<tr><?php foreach ( $test['header'] as $i => $h ) : ?><td><?php echo esc_html( $r[ $i ] ?? '' ); ?></td><?php endforeach; ?></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p>Die Spaltennamen stehen jetzt unten in den Auswahllisten bereit.</p>
		</div>
	<?php endif; ?>
<?php endif; ?>

<div style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bts_test' ); ?>
		<input type="hidden" name="action" value="bts_test">
		<button class="button">Verbindung testen &amp; Datei ansehen</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bts_run' ); ?>
		<input type="hidden" name="action" value="bts_run">
		<input type="hidden" name="dry" value="1">
		<button class="button">Trockenlauf (ändert nichts)</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		onsubmit="return confirm('Bestände jetzt wirklich schreiben?');">
		<?php wp_nonce_field( 'bts_run' ); ?>
		<input type="hidden" name="action" value="bts_run">
		<button class="button button-primary">Jetzt synchronisieren</button>
	</form>
</div>

<p>
	Katalog: <strong><?php echo (int) BTS_Catalog::count(); ?></strong> Artikelnummern vorgemerkt ·
	Verknüpfte Produkte: <strong><?php echo (int) count( BTS_Catalog::linked_artnrs() ); ?></strong> ·
	Nächster automatischer Lauf:
	<strong><?php echo $next ? esc_html( date_i18n( 'd.m.Y H:i', $next ) ) : 'nicht geplant'; ?></strong>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<?php wp_nonce_field( 'bts_save' ); ?>
<input type="hidden" name="action" value="bts_save">

<h2 class="title">1. Woher kommt die Datei</h2>
<table class="form-table" role="presentation">
<tr>
	<th scope="row">Zugriffsart</th>
	<td>
		<label><input type="radio" name="source_mode" value="share" <?php checked( $s['source_mode'], 'share' ); ?>>
			<strong>Öffentlicher Freigabe-Link</strong> — empfohlen, keine Zugangsdaten nötig</label><br>
		<label><input type="radio" name="source_mode" value="webdav" <?php checked( $s['source_mode'], 'webdav' ); ?>>
			WebDAV mit Benutzerkonto und App-Passwort</label>
		<p class="description">
			So legst du den Freigabe-Link an: in Nextcloud den Ordner <code>Bloomtech/export</code> (oder direkt die CSV)
			auswählen → <em>Teilen</em> → <em>Link teilen</em> → als Berechtigung <em>Nur Lesen</em>.
			Den erzeugten Link hier eintragen. Er lässt sich jederzeit in Nextcloud wieder entziehen,
			und es liegt kein Kontopasswort in WordPress.
		</p>
	</td>
</tr>
<tr>
	<th scope="row">Freigabe-Link</th>
	<td>
		<input type="url" name="share_url" class="large-text" placeholder="https://nx58197.your-storageshare.de/s/XXXXXXXX"
			value="<?php echo esc_attr( BTS_Settings::credential_is_constant( 'share_url' ) ? '' : $s['share_url'] ); ?>"
			<?php disabled( BTS_Settings::credential_is_constant( 'share_url' ) ); ?>>
		<?php if ( BTS_Settings::credential_is_constant( 'share_url' ) ) : ?>
			<p class="description">Wird über die Konstante <code>BLOOMTECH_SHARE_URL</code> in der <code>wp-config.php</code> gesetzt.</p>
		<?php endif; ?>
	</td>
</tr>
<tr>
	<th scope="row">Freigabe-Passwort</th>
	<td>
		<input type="password" name="share_password" class="regular-text" autocomplete="new-password"
			placeholder="<?php echo BTS_Settings::credential( 'share_password' ) !== '' ? '•••••••• (gesetzt)' : 'nur falls die Freigabe eines hat'; ?>">
		<label style="margin-left:10px"><input type="checkbox" name="share_password_clear" value="1"> löschen</label>
	</td>
</tr>
<tr class="bts-dav">
	<th scope="row">WebDAV-Adresse</th>
	<td><input type="url" name="webdav_base" class="large-text"
		placeholder="https://nx58197.your-storageshare.de/remote.php/dav/files/BENUTZERNAME"
		value="<?php echo esc_attr( $s['webdav_base'] ); ?>"></td>
</tr>
<tr class="bts-dav">
	<th scope="row">Benutzer / App-Passwort</th>
	<td>
		<input type="text" name="webdav_user" class="regular-text" autocomplete="off"
			value="<?php echo esc_attr( BTS_Settings::credential_is_constant( 'webdav_user' ) ? '' : $s['webdav_user'] ); ?>"
			placeholder="Benutzername">
		<input type="password" name="webdav_password" class="regular-text" autocomplete="new-password"
			placeholder="<?php echo BTS_Settings::credential( 'webdav_password' ) !== '' ? '•••••••• (gesetzt)' : 'App-Passwort'; ?>">
		<p class="description">
			Falls du diesen Weg nimmst: In Nextcloud unter <em>Einstellungen → Sicherheit</em> ein
			<strong>App-Passwort</strong> erzeugen und nur dieses eintragen — nie das Hauptpasswort.
			Noch sicherer ist es, beides in die <code>wp-config.php</code> zu schreiben:
			<code>define('BLOOMTECH_DAV_USER', '…'); define('BLOOMTECH_DAV_PASS', '…');</code>
		</p>
	</td>
</tr>
<tr>
	<th scope="row">Ordner</th>
	<td><input type="text" name="remote_path" class="regular-text" value="<?php echo esc_attr( $s['remote_path'] ); ?>">
		<p class="description">Nur bei WebDAV nötig. Beim Freigabe-Link steckt der Ordner schon im Link.</p></td>
</tr>
<tr>
	<th scope="row">Welche Datei</th>
	<td>
		<label><input type="radio" name="file_mode" value="newest" <?php checked( $s['file_mode'], 'newest' ); ?>>
			Immer die <strong>neueste</strong> Datei im Ordner</label>
		<input type="text" name="file_pattern" value="<?php echo esc_attr( $s['file_pattern'] ); ?>" class="regular-text"
			style="margin-left:8px" placeholder="Muster, z. B. \.csv$">
		<br>
		<label><input type="radio" name="file_mode" value="exact" <?php checked( $s['file_mode'], 'exact' ); ?>>
			Feste Datei</label>
		<input type="text" name="file_name" value="<?php echo esc_attr( $s['file_name'] ); ?>" class="regular-text"
			style="margin-left:8px" placeholder="bestand.csv">
		<p class="description">„Neueste Datei" ist die richtige Wahl, wenn Bloomtech jeden Tag eine neue Datei mit Datum im Namen ablegt.</p>
	</td>
</tr>
</table>

<h2 class="title">2. Spalten zuordnen</h2>
<table class="form-table" role="presentation">
<tr><th scope="row">Erste Zeile ist Kopfzeile</th>
	<td><label><input type="checkbox" name="has_header" value="1" <?php checked( $s['has_header'], 1 ); ?>> ja</label></td></tr>
<tr><th scope="row">Artikelnummer <span style="color:#d63638">*</span></th>
	<td><?php BTS_Admin::col_select( 'col_artnr', $s['col_artnr'], $header ); ?>
		<p class="description">Das Feld, über das Produkte zugeordnet werden. Pflichtangabe.</p></td></tr>
<tr><th scope="row">Bestand <span style="color:#d63638">*</span></th>
	<td><?php BTS_Admin::col_select( 'col_stock', $s['col_stock'], $header ); ?>
		<p class="description">Zahlen wie <code>12</code> oder <code>1.250</code> genauso wie Worte („lieferbar", „ausverkauft") werden verstanden.</p></td></tr>
<tr><th scope="row">EAN / GTIN</th><td><?php BTS_Admin::col_select( 'col_ean', $s['col_ean'], $header ); ?></td></tr>
<tr><th scope="row">Bezeichnung</th><td><?php BTS_Admin::col_select( 'col_name', $s['col_name'], $header ); ?></td></tr>
<tr><th scope="row">Marke</th><td><?php BTS_Admin::col_select( 'col_brand', $s['col_brand'], $header ); ?></td></tr>
<tr><th scope="row">Einkaufspreis</th><td><?php BTS_Admin::col_select( 'col_price', $s['col_price'], $header ); ?>
	<p class="description">Wird nur im Katalog mitgeführt. Preise im Shop werden <strong>nie</strong> verändert.</p></td></tr>
<tr><th scope="row">Format</th>
	<td>
		Trennzeichen
		<select name="delimiter">
			<?php foreach ( array( 'auto' => 'automatisch', 'semicolon' => 'Semikolon ;', 'comma' => 'Komma ,', 'tab' => 'Tabulator', 'pipe' => 'Senkrechter Strich |' ) as $k => $v ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['delimiter'], $k ); ?>><?php echo esc_html( $v ); ?></option>
			<?php endforeach; ?>
		</select>
		Zeichensatz
		<select name="encoding">
			<?php foreach ( array( 'auto' => 'automatisch', 'UTF-8' => 'UTF-8', 'Windows-1252' => 'Windows-1252', 'ISO-8859-1' => 'ISO-8859-1' ) as $k => $v ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['encoding'], $k ); ?>><?php echo esc_html( $v ); ?></option>
			<?php endforeach; ?>
		</select>
		Dezimaltrennzeichen
		<select name="decimal">
			<?php foreach ( array( 'auto' => 'automatisch', 'comma' => 'Komma', 'dot' => 'Punkt' ) as $k => $v ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['decimal'], $k ); ?>><?php echo esc_html( $v ); ?></option>
			<?php endforeach; ?>
		</select>
	</td></tr>
</table>

<h2 class="title">3. Wie abgeglichen wird</h2>
<table class="form-table" role="presentation">
<tr><th scope="row">Automatik</th>
	<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], 1 ); ?>>
		aktiv</label>
		<select name="interval" style="margin-left:10px">
			<?php
			$ivs = array(
				'hourly'     => 'Jede Stunde (24× täglich)',
				'bts_2h'     => 'Alle 2 Stunden (12× täglich)',
				'bts_4h'     => 'Alle 4 Stunden (6× täglich)',
				'bts_6h'     => 'Alle 6 Stunden (4× täglich)',
				'bts_8h'     => 'Alle 8 Stunden (3× täglich)',
				'bts_12h'    => 'Alle 12 Stunden (2× täglich)',
				'daily'      => 'Einmal täglich',
			);
			foreach ( $ivs as $k => $v ) :
				?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['interval'], $k ); ?>><?php echo esc_html( $v ); ?></option>
			<?php endforeach; ?>
		</select>
	</td></tr>
<tr><th scope="row">Sicherheitspuffer</th>
	<td><input type="number" name="buffer" step="1" min="0" value="<?php echo esc_attr( $s['buffer'] ); ?>" class="small-text"> Stück
		<p class="description">Wird vom gemeldeten Lieferantenbestand abgezogen. Bei 2 gilt ein Bestand von 3 im Shop als 1 — schützt gegen Überverkauf durch zeitversetzte Exporte.</p></td></tr>
<tr><th scope="row">Als ausverkauft ab</th>
	<td>Bestand ≤ <input type="number" name="threshold" step="1" min="0" value="<?php echo esc_attr( $s['threshold'] ); ?>" class="small-text"></td></tr>
<tr><th scope="row">Bestand mitschreiben</th>
	<td><label><input type="checkbox" name="write_stock_qty" value="1" <?php checked( $s['write_stock_qty'], 1 ); ?>>
		Bestandsmenge in WooCommerce eintragen</label>
		<p class="description">
			<strong>Mit Haken:</strong> Das Plugin schaltet die Lagerverwaltung beim ersten Lauf selbst ein
			und trägt die Stückzahl ein. Du musst vorher nichts vorbereiten. WooCommerce begrenzt dann die
			bestellbare Menge und zählt bei jeder Bestellung herunter — <em>das ist der wirksame Schutz gegen
			Überverkauf</em>, auch zwischen zwei Abgleichen.<br>
			<strong>Ohne Haken:</strong> Es wird nur zwischen „vorrätig", „Lieferrückstand" und „nicht vorrätig"
			umgeschaltet, so wie die Produkte heute laufen. Solange der Lieferant „vorrätig" meldet, ist die
			Bestellmenge im Shop unbegrenzt — ein Kunde kann also 10 Stück kaufen, obwohl nur noch 2 da sind.<br>
			Meldet die Liste statt Zahlen nur Worte wie „lieferbar", wird auch mit Haken <em>keine</em> Menge
			erfunden — dann greift automatisch die reine Statusumschaltung.
		</p></td></tr>
<tr><th scope="row">Bei Bestand 0</th>
	<td><select name="backorder_mode">
			<option value="no" <?php selected( $s['backorder_mode'], 'no' ); ?>>Nicht bestellbar (ausverkauft)</option>
			<option value="notify" <?php selected( $s['backorder_mode'], 'notify' ); ?>>Lieferbar mit Hinweis (Nachbestellung)</option>
			<option value="yes" <?php selected( $s['backorder_mode'], 'yes' ); ?>>Bestellbar ohne Hinweis</option>
		</select></td></tr>
<tr><th scope="row">Artikel fehlt in der Liste</th>
	<td><select name="missing_action">
			<option value="ignore" <?php selected( $s['missing_action'], 'ignore' ); ?>>Unverändert lassen</option>
			<option value="zero" <?php selected( $s['missing_action'], 'zero' ); ?>>Auf 0 setzen</option>
		</select>
		<p class="description">„Unverändert lassen" ist die sichere Voreinstellung — ein unvollständiger Export legt dann nicht versehentlich Produkte still.</p></td></tr>
</table>

<h2 class="title">4. Notbremsen</h2>
<p>Diese Grenzen verhindern, dass ein kaputter oder halb geschriebener Export den Shop leerräumt. Wird eine überschritten, bricht der Lauf ab, <strong>ohne irgendetwas zu ändern</strong>, und du bekommst eine E-Mail.</p>
<table class="form-table" role="presentation">
<tr><th scope="row">Mindestens Zeilen in der Datei</th>
	<td><input type="number" name="min_rows" min="0" step="1" value="<?php echo esc_attr( $s['min_rows'] ); ?>" class="small-text"></td></tr>
<tr><th scope="row">Höchstens auf 0 gesetzt</th>
	<td><input type="number" name="max_zero_ratio" min="0" max="100" step="1" value="<?php echo esc_attr( $s['max_zero_ratio'] ); ?>" class="small-text"> %
		der verknüpften Produkte</td></tr>
<tr><th scope="row">Datei höchstens alt</th>
	<td><input type="number" name="max_file_age_h" min="0" step="1" value="<?php echo esc_attr( $s['max_file_age_h'] ); ?>" class="small-text"> Stunden
		<p class="description">0 schaltet die Prüfung ab. Bei Überschreitung läuft der Abgleich weiter, es gibt aber eine Warnung.</p></td></tr>
<tr><th scope="row">Benachrichtigung</th>
	<td><select name="notify_on">
			<option value="never" <?php selected( $s['notify_on'], 'never' ); ?>>Nie</option>
			<option value="error" <?php selected( $s['notify_on'], 'error' ); ?>>Nur bei Problemen</option>
			<option value="changes" <?php selected( $s['notify_on'], 'changes' ); ?>>Bei jeder Änderung</option>
		</select>
		<input type="email" name="notify_email" class="regular-text" style="margin-left:10px"
			placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
			value="<?php echo esc_attr( $s['notify_email'] ); ?>">
	</td></tr>
<tr><th scope="row">Protokoll aufbewahren</th>
	<td><input type="number" name="log_keep_days" min="1" step="1" value="<?php echo esc_attr( $s['log_keep_days'] ); ?>" class="small-text"> Tage</td></tr>
</table>

<?php submit_button( 'Einstellungen speichern' ); ?>
</form>
</div>
