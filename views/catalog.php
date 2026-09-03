<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
<h1>Bloomtech-Artikelkatalog</h1>
<p>
	Hier stehen <strong>alle</strong> Artikelnummern, die je in einer Bestandsliste aufgetaucht sind — auch die,
	zu denen es im Shop noch kein Produkt gibt. Wenn du später ein neues Bloomtech-Produkt anlegst,
	findest du die passende Nummer hier und trägst sie beim Produkt ein.
</p>
<p><strong>Wichtig:</strong> Der Bestandsabgleich fasst ausschließlich Produkte an, bei denen eine Artikelnummer
	hinterlegt ist. Produkte, die ihr selbst auf Lager habt — etwa Biobizz — lässt du einfach leer;
	sie bleiben dann für immer unberührt.</p>

<?php if ( ! empty( $_GET['linked'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo (int) $_GET['linked'] === 2 ? 'Verknüpfung entfernt.' : 'Verknüpft.'; ?></p></div>
<?php endif; ?>
<?php if ( ! empty( $_GET['linkerr'] ) ) : ?>
	<div class="notice notice-error"><p>
	<?php
	$e = (int) $_GET['linkerr'];
	echo esc_html(
		$e === 3 ? 'Dieses Produkt ist als Eigenbestand markiert und wird deshalb nicht verknüpft.'
			: ( $e === 2 ? 'Die angegebene ID gehört zu keinem Produkt und zu keiner Variante.' : 'Artikelnummer oder Produkt-ID fehlt.' )
	);
	?>
	</p></div>
<?php endif; ?>

<form method="get" style="margin:14px 0">
	<input type="hidden" name="page" value="bts-catalog">
	<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Artikelnummer, EAN, Name oder Marke" class="regular-text">
	<select name="filter">
		<option value="all" <?php selected( $filter, 'all' ); ?>>Alle</option>
		<option value="linked" <?php selected( $filter, 'linked' ); ?>>Nur verknüpfte</option>
		<option value="unlinked" <?php selected( $filter, 'unlinked' ); ?>>Nur noch nicht verknüpfte</option>
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
	<th style="width:280px">Produkt im Shop</th>
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
					<div style="margin-bottom:4px">
						<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
							<?php echo esc_html( get_the_title( $pid ) ?: ( '#' . $pid ) ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<?php wp_nonce_field( 'bts_link' ); ?>
							<input type="hidden" name="action" value="bts_link">
							<input type="hidden" name="unlink" value="1">
							<input type="hidden" name="product_id" value="<?php echo (int) $pid; ?>">
							<button class="button-link" style="color:#b32d2e">lösen</button>
						</form>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bts_link' ); ?>
					<input type="hidden" name="action" value="bts_link">
					<input type="hidden" name="artnr" value="<?php echo esc_attr( $r->artnr ); ?>">
					<input type="number" name="product_id" placeholder="Produkt-ID" style="width:110px">
					<button class="button button-small">verknüpfen</button>
				</form>
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
