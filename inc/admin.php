<?php
/**
 * Admin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_options_page(
		'Auto WebP',
		'Auto WebP',
		'manage_options',
		'auto-webp',
		'awebp_render_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'awebp_settings', 'awebp_quality', [
		'type'              => 'integer',
		'sanitize_callback' => function ( $val ) {
			$val = (int) $val;
			return max( 1, min( 100, $val ) );
		},
		'default'           => 82,
	] );
	register_setting( 'awebp_settings', 'awebp_convert_on_upload', [
		'type'              => 'boolean',
		'sanitize_callback' => 'absint',
		'default'           => 1,
	] );
	register_setting( 'awebp_settings', 'awebp_serve_webp', [
		'type'              => 'boolean',
		'sanitize_callback' => 'absint',
		'default'           => 1,
	] );
	register_setting( 'awebp_settings', 'awebp_delete_on_media_delete', [
		'type'              => 'boolean',
		'sanitize_callback' => 'absint',
		'default'           => 1,
	] );
} );

add_filter( 'manage_media_columns', function ( $columns ) {
	$columns['awebp_status'] = 'WebP';
	return $columns;
} );

add_action( 'manage_media_custom_column', function ( $column_name, $post_id ) {
	if ( $column_name !== 'awebp_status' ) {
		return;
	}

	$mime = get_post_mime_type( $post_id );
	if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/gif' ], true ) ) {
		echo '<span style="color:#999">—</span>';
		return;
	}

	$file = get_attached_file( $post_id );
	if ( ! $file ) {
		echo '<span style="color:#999">—</span>';
		return;
	}

	$webp = awebp_webp_path( $file );
	if ( file_exists( $webp ) ) {
		$saved = filesize( $file ) - filesize( $webp );
		$pct   = $saved > 0 ? round( $saved / filesize( $file ) * 100 ) : 0;
		echo '<span style="color:#46b450" title="Saved ' . size_format( $saved ) . '">&#10004; -' . $pct . '%</span>';
	} else {
		echo '<button type="button" class="button button-small awebp-convert-single" data-id="' . esc_attr( $post_id ) . '">Convert</button>';
	}
}, 10, 2 );

add_action( 'admin_footer', function () {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'upload' ) {
		return;
	}
	?>
	<script>
	document.addEventListener('click', function(e) {
		if (!e.target.classList.contains('awebp-convert-single')) return;
		var btn = e.target;
		var id = btn.dataset.id;
		btn.disabled = true;
		btn.textContent = '...';
		fetch(ajaxurl + '?action=awebp_convert_single&id=' + id + '&_wpnonce=<?php echo wp_create_nonce( 'awebp_convert' ); ?>')
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (data.success) {
					btn.outerHTML = '<span style="color:#46b450">&#10004;</span>';
				} else {
					btn.textContent = 'Error';
					btn.title = data.data || 'Unknown error';
				}
			});
	});
	</script>
	<?php
} );

add_action( 'wp_ajax_awebp_convert_single', function () {
	check_ajax_referer( 'awebp_convert', '_wpnonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied' );
	}

	$id = (int) ( $_GET['id'] ?? 0 );
	if ( ! $id ) {
		wp_send_json_error( 'Invalid ID' );
	}

	$results = awebp_convert_attachment( $id );
	$has_error = false;
	foreach ( $results as $r ) {
		if ( empty( $r['success'] ) ) {
			$has_error = true;
		}
	}

	if ( $has_error ) {
		wp_send_json_error( 'Some sizes failed to convert' );
	}

	wp_send_json_success( $results );
} );

function awebp_render_settings_page(): void {
	$engine = awebp_get_engine();
	$stats  = awebp_get_stats();
	?>
	<div class="wrap">
		<h1>Auto WebP</h1>

		<div class="card" style="max-width:600px;margin-bottom:20px;padding:15px">
			<h2 style="margin-top:0">Status</h2>
			<table class="form-table" style="margin:0">
				<tr>
					<th>Engine</th>
					<td><code><?php echo esc_html( strtoupper( $engine ) ); ?></code></td>
				</tr>
				<tr>
					<th>Images</th>
					<td>
						<strong><?php echo number_format( $stats['converted'] ); ?></strong> converted
						/ <strong><?php echo number_format( $stats['total'] ); ?></strong> total
						<?php if ( $stats['pending'] > 0 ) : ?>
							<br><span style="color:#d63638"><?php echo number_format( $stats['pending'] ); ?> pending</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $stats['saved_bytes'] > 0 ) : ?>
				<tr>
					<th>Space Saved</th>
					<td><strong><?php echo size_format( $stats['saved_bytes'] ); ?></strong></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<?php if ( $stats['pending'] > 0 ) : ?>
		<div class="card" style="max-width:600px;margin-bottom:20px;padding:15px">
			<h2 style="margin-top:0">Bulk Convert</h2>
			<p><?php echo number_format( $stats['pending'] ); ?> images have not been converted yet.</p>
			<button type="button" id="awebp-bulk-start" class="button button-primary">Convert All</button>
			<div id="awebp-bulk-progress" style="display:none;margin-top:10px">
				<div style="background:#e0e0e0;border-radius:3px;overflow:hidden;height:24px">
					<div id="awebp-bulk-bar" style="background:#2271b1;height:100%;width:0%;transition:width .3s;line-height:24px;color:#fff;text-align:center;font-size:12px">0%</div>
				</div>
				<p id="awebp-bulk-status" style="margin-top:5px"></p>
			</div>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'awebp_settings' ); ?>
			<table class="form-table">
				<tr>
					<th>WebP Quality</th>
					<td>
						<input type="number" name="awebp_quality" value="<?php echo esc_attr( get_option( 'awebp_quality', 82 ) ); ?>" min="1" max="100" style="width:80px"> %
						<p class="description">Recommended: 75–85. Lower = smaller files, more compression artifacts.</p>
					</td>
				</tr>
				<tr>
					<th>Auto-convert on Upload</th>
					<td>
						<label>
							<input type="checkbox" name="awebp_convert_on_upload" value="1" <?php checked( get_option( 'awebp_convert_on_upload', 1 ) ); ?>>
							Automatically create WebP copies when images are uploaded
						</label>
					</td>
				</tr>
				<tr>
					<th>Serve WebP</th>
					<td>
						<label>
							<input type="checkbox" name="awebp_serve_webp" value="1" <?php checked( get_option( 'awebp_serve_webp', 1 ) ); ?>>
							Replace image URLs with WebP versions for supporting browsers
						</label>
					</td>
				</tr>
				<tr>
					<th>Cleanup</th>
					<td>
						<label>
							<input type="checkbox" name="awebp_delete_on_media_delete" value="1" <?php checked( get_option( 'awebp_delete_on_media_delete', 1 ) ); ?>>
							Delete WebP files when original media is deleted
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
