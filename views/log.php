<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
<h1>Bloomtech — Protokoll</h1>

<?php
$show = $run ?: $last;
if ( $show ) :
	?>
	<h2><?php echo ! empty( $show['dry_run'] ) ? 'Ergebnis des Trockenlaufs' : 'Letzter Abgleich'; ?>
		<span style="font-weight:400;color:#666">
			· <?php echo esc_html( $show['started'] ?? '' ); ?></span></h2>

	<?php if ( ! empty( $show['aborted'] ) ) : ?>
		<div class="notice notice-error"><p><strong>Abgebrochen — es wurde nichts geändert.</strong></p>
		<?php foreach ( (array) $show['errors'] as $e ) : ?>
			<p><?php echo esc_html( $e ); ?></p>
		<?php endforeach; ?>
		</div>
	<?php elseif ( ! empty( $show['errors'] ) ) : ?>
		<div class="notice notice-warning">
		<?php foreach ( (array) $show['errors'] as $e ) : ?>
			<p><?php echo esc_html( $e ); ?></p>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $show['dry_run'] ) ) : ?>
		<div class="notice notice-info"><p>Das war ein Trockenlauf. Es wurde <strong>nichts</strong> gespeichert —
			die Liste unten zeigt nur, was ein echter Lauf tun würde.</p></div>
	<?php endif; ?>

	<table class="widefat" style="max-width:760px;margin-bottom:20px"><tbody>
		<tr><td>Datei</td><td><code><?php echo esc_html( $show['file'] ?? '' ); ?></code></td></tr>
		<tr><td>Zeilen / Artikel</td><td><?php echo (int) ( $show['rows'] ?? 0 ); ?> / <?php echo (int) ( $show['articles'] ?? 0 ); ?></td></tr>
		<tr><td>Davon im Shop gefunden</td><td><strong><?php echo (int) ( $show['matched'] ?? 0 ); ?></strong>
			<span style="color:#666">(Abgleich über die SKU)</span></td></tr>
		<tr><td>Neu in den Katalog</td><td><?php echo (int) ( $show['new_art'] ?? 0 ); ?></td></tr>
		<tr><td>Produkte geändert</td><td><strong><?php echo (int) ( $show['updated'] ?? 0 ); ?></strong></td></tr>
		<tr><td>Unverändert</td><td><?php echo (int) ( $show['unchanged'] ?? 0 ); ?></td></tr>
		<tr><td>Übersprungen</td><td><?php echo (int) ( $show['skipped'] ?? 0 ); ?> <span style="color:#666">(Eigenbestand oder keine Bestandsangabe)</span></td></tr>
		<tr><td>Nicht mehr in der Liste</td><td><?php echo (int) ( $show['missing'] ?? 0 ); ?></td></tr>
	</tbody></table>

	<?php if ( ! empty( $show['changes'] ) ) : ?>
		<h3>Änderungen</h3>
		<table class="wp-list-table widefat fixed striped">
		<thead><tr><th style="width:130px">Artikelnr.</th><th>Produkt</th><th style="width:130px">SKU</th>
			<th style="width:120px">Bestand</th><th style="width:220px">Status</th><th style="width:170px">Grund</th></tr></thead>
		<tbody>
		<?php foreach ( array_slice( $show['changes'], 0, 500 ) as $c ) : ?>
			<tr>
				<td><code><?php echo esc_html( $c['artnr'] ); ?></code></td>
				<td><a href="<?php echo esc_url( get_edit_post_link( $c['pid'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a></td>
				<td><?php echo esc_html( $c['sku'] ); ?></td>
				<td>
					<?php
					if ( empty( $c['qty'] ) ) {
						echo '<span style="color:#666">nur Status</span>';
					} else {
						echo $c['from'] === null ? '—' : (int) $c['from'];
						echo ' → <strong>' . (int) $c['to'] . '</strong>';
					}
					?>
				</td>
				<td><?php echo esc_html( $c['status'] ); ?></td>
				<td><?php echo esc_html( $c['reason'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>
<?php endif; ?>

<h2>Meldungen</h2>
<table class="wp-list-table widefat fixed striped">
<thead><tr><th style="width:150px">Zeitpunkt</th><th style="width:80px">Art</th><th>Meldung</th></tr></thead>
<tbody>
<?php if ( ! $rows ) : ?>
	<tr><td colspan="3">Noch keine Einträge.</td></tr>
<?php endif; ?>
<?php foreach ( $rows as $r ) : ?>
	<tr>
		<td><?php echo esc_html( $r->ts ); ?></td>
		<td><?php
			$c = $r->level === 'error' ? '#b32d2e' : ( $r->level === 'warn' ? '#996800' : '#666' );
			printf( '<span style="color:%s">%s</span>', esc_attr( $c ), esc_html( $r->level ) );
		?></td>
		<td><?php echo esc_html( $r->msg ); ?></td>
	</tr>
<?php endforeach; ?>
</tbody></table>
</div>
