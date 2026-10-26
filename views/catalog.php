<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
<h1>Bloomtech-Artikelkatalog</h1>
<p>
	Hier stehen <strong>alle</strong> Artikelnummern, die je in einer Bestandsliste aufgetaucht sind — auch die,
	zu denen es im Shop noch kein Produkt gibt. Legst du später ein neues Bloomtech-Produkt an, trägst du die
	Nummer einfach als SKU ein; ab dem nächsten Abgleich läuft es mit.
</p>
<p><strong>Die Zuordnung passiert von selbst über die SKU.</strong> Es gibt hier nichts zu verknüpfen.
	Produkte aus eigenem Lagerbestand tragen <code>HJ-</code>-Nummern und können deshalb gar nicht getroffen werden.</p>

<form method="get" style="margin:14px 0">
	<input type="hidden" name="page" value="bts-catalog">
	<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Artikelnummer, EAN, Name oder Marke" class="regular-text">
	<select name="filter">
		<option value="all" <?php selected( $filter, 'all' ); ?>>Alle</option>
		<option value="linked" <?php selected( $filter, 'linked' ); ?>>Nur mit Produkt im Shop</option>
		<option value="unlinked" <?php selected( $filter, 'unlinked' ); ?>>Nur ohne Produkt im Shop</option>
	</select>
	<button class="button">Filtern</button>
	<span style="margin-left:12px"><?php echo (int) $data['total']; ?> Treffer</span>
</form>

<table class="wp-list-table widefat fixed striped">
<thead><tr>
	<th style="width:150px">Artikelnummer</th>
	<th style="width:130px">EAN</th>
	<th>Bezeichnung</th>
	<th style="width:120px">Marke</th>
	<th style="width:80px">Bestand</th>
	<th style="width:120px">Zuletzt gesehen</th>
	<th style="width:300px">Produkt im Shop (über SKU)</th>
</tr></thead>
<tbody>
<?php if ( ! $data['rows'] ) : ?>
	<tr><td colspan="7">Noch keine Artikel im Katalog. Führe unter „Einstellungen" einmal <em>Verbindung testen</em>
		und anschließend einen Abgleich aus — danach steht die Liste hier.</td></tr>
<?php endif; ?>
<?php foreach ( $data['rows'] as $r ) : ?>
	<?php $links = $data['linked'][ $r->artnr ] ?? array(); ?>
	<tr>
		<td><code><?php echo esc_html( $r->artnr ); ?></code></td>
		<td><?php echo esc_html( $r->ean ); ?></td>
		<td><?php echo esc_html( $r->name ); ?></td>
		<td><?php echo esc_html( $r->brand ); ?></td>
		<td><?php echo $r->stock === null ? '—' : esc_html( rtrim( rtrim( number_format( (float) $r->stock, 3, ',', '.' ), '0' ), ',' ) ); ?></td>
		<td><?php echo $r->last_seen ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $r->last_seen ) ) ) : '—'; ?></td>
		<td>
			<?php if ( $links ) : ?>
				<?php foreach ( $links as $pid ) : ?>
					<div style="margin-bottom:3px">
						<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
							<?php echo esc_html( get_the_title( $pid ) ?: ( '#' . $pid ) ); ?></a>
						<?php if ( BTS_Matcher::is_excluded( $pid ) ) : ?>
							<span style="color:#996800">· Eigenbestand, wird übersprungen</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<span style="color:#aaa">noch kein Produkt mit dieser SKU</span>
			<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php
$pages = (int) ceil( $data['total'] / 50 );
if ( $pages > 1 ) :
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo paginate_links(
		array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'current'   => $paged,
			'total'     => $pages,
			'prev_text' => '‹',
			'next_text' => '›',
		)
	);
	echo '</div></div>';
endif;
?>
</div>
