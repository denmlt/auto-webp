<?php
/**
 * WordPress hooks — auto-convert on upload, cleanup on delete.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_generate_attachment_metadata', function ( $metadata, $attachment_id ) {
	if ( ! get_option( 'awebp_convert_on_upload', 1 ) ) {
		return $metadata;
	}

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/gif' ], true ) ) {
		return $metadata;
	}

	awebp_convert_attachment( $attachment_id );

	return $metadata;
}, 10, 2 );

add_action( 'delete_attachment', function ( $attachment_id ) {
	if ( ! get_option( 'awebp_delete_on_media_delete', 1 ) ) {
		return;
	}
	awebp_delete_webp_files( $attachment_id );
} );
