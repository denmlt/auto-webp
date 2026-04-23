<?php
/**
 * Serve WebP versions to supporting browsers.
 * Pure PHP approach — no .htaccess rules needed. Works on any hosting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function awebp_browser_supports_webp(): bool {
	return isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false;
}

function awebp_url_to_webp( string $url ): string {
	return preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $url );
}

function awebp_webp_exists_for_url( string $url ): bool {
	$upload_dir = wp_get_upload_dir();
	$base_url   = $upload_dir['baseurl'];
	$base_dir   = $upload_dir['basedir'];

	if ( strpos( $url, $base_url ) === false ) {
		return false;
	}

	$relative  = str_replace( $base_url, '', $url );
	$file_path = $base_dir . $relative;
	$webp_path = awebp_webp_path( $file_path );

	return file_exists( $webp_path );
}

add_filter( 'wp_get_attachment_image_attributes', function ( $attr ) {
	if ( ! get_option( 'awebp_serve_webp', 1 ) || ! awebp_browser_supports_webp() ) {
		return $attr;
	}

	if ( ! empty( $attr['src'] ) && awebp_webp_exists_for_url( $attr['src'] ) ) {
		$attr['src'] = awebp_url_to_webp( $attr['src'] );
	}

	if ( ! empty( $attr['srcset'] ) ) {
		$attr['srcset'] = preg_replace_callback(
			'/(\S+\.(jpe?g|png|gif))/i',
			function ( $matches ) {
				if ( awebp_webp_exists_for_url( $matches[1] ) ) {
					return awebp_url_to_webp( $matches[1] );
				}
				return $matches[0];
			},
			$attr['srcset']
		);
	}

	return $attr;
}, 99 );

add_filter( 'the_content', function ( $content ) {
	if ( ! get_option( 'awebp_serve_webp', 1 ) || ! awebp_browser_supports_webp() ) {
		return $content;
	}

	$upload_dir = wp_get_upload_dir();
	$base_url   = preg_quote( $upload_dir['baseurl'], '/' );

	return preg_replace_callback(
		'/(' . $base_url . '\/[^\s"\']+\.(jpe?g|png|gif))/i',
		function ( $matches ) {
			if ( awebp_webp_exists_for_url( $matches[1] ) ) {
				return awebp_url_to_webp( $matches[1] );
			}
			return $matches[0];
		},
		$content
	);
}, 99 );

add_filter( 'wp_get_attachment_url', function ( $url ) {
	if ( ! get_option( 'awebp_serve_webp', 1 ) || ! awebp_browser_supports_webp() ) {
		return $url;
	}

	$ext = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif' ], true ) ) {
		return $url;
	}

	if ( awebp_webp_exists_for_url( $url ) ) {
		return awebp_url_to_webp( $url );
	}

	return $url;
}, 99 );

add_filter( 'wp_calculate_image_srcset', function ( $sources ) {
	if ( ! get_option( 'awebp_serve_webp', 1 ) || ! awebp_browser_supports_webp() ) {
		return $sources;
	}

	foreach ( $sources as &$source ) {
		if ( awebp_webp_exists_for_url( $source['url'] ) ) {
			$source['url'] = awebp_url_to_webp( $source['url'] );
		}
	}

	return $sources;
}, 99 );
