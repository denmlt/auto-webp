<?php
/**
 * Bulk conversion via AJAX — processes images in small batches to avoid timeouts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_awebp_bulk_get_ids', function () {
	check_ajax_referer( 'awebp_bulk', '_wpnonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied' );
	}

	$ids = get_posts( [
		'post_type'      => 'attachment',
		'post_mime_type' => [ 'image/jpeg', 'image/png', 'image/gif' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	] );

	$pending = [];
	foreach ( $ids as $id ) {
		$file = get_attached_file( $id );
		if ( $file && ! file_exists( awebp_webp_path( $file ) ) ) {
			$pending[] = $id;
		}
	}

	wp_send_json_success( [ 'ids' => $pending, 'total' => count( $pending ) ] );
} );

add_action( 'wp_ajax_awebp_bulk_convert', function () {
	check_ajax_referer( 'awebp_bulk', '_wpnonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied' );
	}

	$ids = json_decode( stripslashes( $_POST['ids'] ?? '[]' ), true );
	if ( ! is_array( $ids ) ) {
		wp_send_json_error( 'Invalid IDs' );
	}

	$results = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$results[ $id ] = awebp_convert_attachment( $id );
		}
	}

	wp_send_json_success( $results );
} );

add_action( 'admin_footer', function () {
	$screen = get_current_screen();
	if ( ! $screen || $screen->base !== 'settings_page_auto-webp' ) {
		return;
	}
	$nonce = wp_create_nonce( 'awebp_bulk' );
	?>
	<script>
	(function() {
		var btn = document.getElementById('awebp-bulk-start');
		if (!btn) return;

		var BATCH_SIZE = 5;

		btn.addEventListener('click', function() {
			btn.disabled = true;
			btn.textContent = 'Loading...';

			var progress = document.getElementById('awebp-bulk-progress');
			var bar = document.getElementById('awebp-bulk-bar');
			var status = document.getElementById('awebp-bulk-status');
			progress.style.display = 'block';

			fetch(ajaxurl + '?action=awebp_bulk_get_ids&_wpnonce=<?php echo $nonce; ?>')
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (!data.success) {
						status.textContent = 'Error: ' + (data.data || 'Unknown');
						return;
					}

					var ids = data.data.ids;
					var total = ids.length;
					var done = 0;
					var errors = 0;

					if (total === 0) {
						status.textContent = 'Nothing to convert!';
						return;
					}

					btn.style.display = 'none';

					function processBatch() {
						if (done >= total) {
							bar.style.width = '100%';
							bar.textContent = '100%';
							status.innerHTML = '<strong>Done!</strong> Converted ' + (total - errors) + ' images.' +
								(errors > 0 ? ' ' + errors + ' errors.' : '') +
								' <a href="">Refresh</a> to see updated stats.';
							return;
						}

						var batch = ids.slice(done, done + BATCH_SIZE);
						var formData = new FormData();
						formData.append('action', 'awebp_bulk_convert');
						formData.append('_wpnonce', '<?php echo $nonce; ?>');
						formData.append('ids', JSON.stringify(batch));

						fetch(ajaxurl, { method: 'POST', body: formData })
							.then(function(r) { return r.json(); })
							.then(function(res) {
								done += batch.length;
								var pct = Math.round(done / total * 100);
								bar.style.width = pct + '%';
								bar.textContent = pct + '%';
								status.textContent = done + ' / ' + total + ' processed...';
								processBatch();
							})
							.catch(function(err) {
								errors += batch.length;
								done += batch.length;
								processBatch();
							});
					}

					processBatch();
				});
		});
	})();
	</script>
	<?php
} );
