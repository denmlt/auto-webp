<?php
/**
 * Core conversion logic — GD with Imagick fallback.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function awebp_can_convert(): bool {
	if ( extension_loaded( 'gd' ) ) {
		$info = gd_info();
		if ( ! empty( $info['WebP Support'] ) ) {
			return true;
		}
	}
	if ( extension_loaded( 'imagick' ) ) {
		$formats = \Imagick::queryFormats( 'WEBP' );
		if ( ! empty( $formats ) ) {
			return true;
		}
	}
	return false;
}

function awebp_get_engine(): string {
	if ( extension_loaded( 'gd' ) ) {
		$info = gd_info();
		if ( ! empty( $info['WebP Support'] ) ) {
			return 'gd';
		}
	}
	if ( extension_loaded( 'imagick' ) ) {
		$formats = \Imagick::queryFormats( 'WEBP' );
		if ( ! empty( $formats ) ) {
			return 'imagick';
		}
	}
	return 'none';
}

function awebp_webp_path( string $file_path ): string {
	return preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $file_path );
}

function awebp_convert_file( string $source_path, int $quality = 0 ): array {
	if ( ! file_exists( $source_path ) ) {
		return [ 'success' => false, 'error' => 'Source file not found' ];
	}

	$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif' ], true ) ) {
		return [ 'success' => false, 'error' => 'Unsupported format: ' . $ext ];
	}

	if ( $quality <= 0 ) {
		$quality = (int) get_option( 'awebp_quality', 82 );
	}

	$dest_path = awebp_webp_path( $source_path );

	if ( file_exists( $dest_path ) && filemtime( $dest_path ) >= filemtime( $source_path ) ) {
		return [ 'success' => true, 'path' => $dest_path, 'skipped' => true ];
	}

	$engine = awebp_get_engine();

	if ( $engine === 'gd' ) {
		return awebp_convert_gd( $source_path, $dest_path, $ext, $quality );
	}

	if ( $engine === 'imagick' ) {
		return awebp_convert_imagick( $source_path, $dest_path, $quality );
	}

	return [ 'success' => false, 'error' => 'No conversion engine available' ];
}

function awebp_convert_gd( string $source, string $dest, string $ext, int $quality ): array {
	switch ( $ext ) {
		case 'jpg':
		case 'jpeg':
			$image = @imagecreatefromjpeg( $source );
			break;
		case 'png':
			$image = @imagecreatefrompng( $source );
			if ( $image ) {
				imagepalettetotruecolor( $image );
				imagealphablending( $image, true );
				imagesavealpha( $image, true );
			}
			break;
		case 'gif':
			$image = @imagecreatefromgif( $source );
			if ( $image ) {
				imagepalettetotruecolor( $image );
			}
			break;
		default:
			return [ 'success' => false, 'error' => 'Unsupported format' ];
	}

	if ( ! $image ) {
		return [ 'success' => false, 'error' => 'Failed to read source image' ];
	}

	$result = @imagewebp( $image, $dest, $quality );
	imagedestroy( $image );

	if ( ! $result || ! file_exists( $dest ) ) {
		return [ 'success' => false, 'error' => 'GD WebP encoding failed' ];
	}

	if ( filesize( $dest ) < 100 ) {
		@unlink( $dest );
		return [ 'success' => false, 'error' => 'GD produced corrupt WebP (too small)' ];
	}

	$saved = filesize( $source ) - filesize( $dest );

	return [
		'success'     => true,
		'path'        => $dest,
		'engine'      => 'gd',
		'source_size' => filesize( $source ),
		'webp_size'   => filesize( $dest ),
		'saved'       => $saved,
	];
}

function awebp_convert_imagick( string $source, string $dest, int $quality ): array {
	try {
		$image = new \Imagick( $source );
		$image->setImageFormat( 'webp' );
		$image->setImageCompressionQuality( $quality );
		$image->setOption( 'webp:method', '4' );
		$image->writeImage( $dest );

		$source_size = filesize( $source );
		$webp_size   = filesize( $dest );

		$image->clear();
		$image->destroy();

		return [
			'success'     => true,
			'path'        => $dest,
			'engine'      => 'imagick',
			'source_size' => $source_size,
			'webp_size'   => $webp_size,
			'saved'       => $source_size - $webp_size,
		];
	} catch ( \Exception $e ) {
		return [ 'success' => false, 'error' => 'Imagick error: ' . $e->getMessage() ];
	}
}

function awebp_convert_attachment( int $attachment_id, int $quality = 0 ): array {
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) {
		return [ 'success' => false, 'error' => 'Attachment file not found' ];
	}

	$results = [];

	$results['original'] = awebp_convert_file( $file, $quality );

	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $metadata['sizes'] ) ) {
		$base_dir = dirname( $file );
		foreach ( $metadata['sizes'] as $size_name => $size_data ) {
			$size_path = $base_dir . '/' . $size_data['file'];
			$results[ $size_name ] = awebp_convert_file( $size_path, $quality );
		}
	}

	return $results;
}

function awebp_delete_webp_files( int $attachment_id ): void {
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) {
		return;
	}

	$webp = awebp_webp_path( $file );
	if ( file_exists( $webp ) ) {
		@unlink( $webp );
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $metadata['sizes'] ) ) {
		$base_dir = dirname( $file );
		foreach ( $metadata['sizes'] as $size_data ) {
			$size_webp = awebp_webp_path( $base_dir . '/' . $size_data['file'] );
			if ( file_exists( $size_webp ) ) {
				@unlink( $size_webp );
			}
		}
	}
}

function awebp_get_stats(): array {
	$upload_dir = wp_get_upload_dir();
	$base       = $upload_dir['basedir'];

	$originals   = 0;
	$converted   = 0;
	$total_saved = 0;

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$ext = strtolower( $file->getExtension() );
		if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif' ], true ) ) {
			$originals++;
			$webp_path = awebp_webp_path( $file->getPathname() );
			if ( file_exists( $webp_path ) ) {
				$converted++;
				$total_saved += $file->getSize() - filesize( $webp_path );
			}
		}
	}

	return [
		'total'       => $originals,
		'converted'   => $converted,
		'pending'     => $originals - $converted,
		'saved_bytes' => $total_saved,
	];
}
