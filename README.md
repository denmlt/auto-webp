# Auto WebP

Lightweight WordPress plugin that converts images to WebP format. Auto-converts on upload, serves WebP to supporting browsers, and bulk-converts existing images.

Works on any hosting — no `.htaccess` rules or server configuration required.

## Features

- **Auto-convert on upload** — creates WebP copies for all image sizes when you upload JPEG, PNG, or GIF
- **Smart delivery** — replaces image URLs with WebP versions for browsers that support it (pure PHP, no server config needed)
- **Bulk conversion** — convert all existing images with a progress bar, processed in batches to avoid timeouts
- **Media Library column** — see WebP status and savings percentage for each image
- **Single-click convert** — convert individual images directly from the Media Library
- **Cleanup** — automatically removes WebP files when original media is deleted
- **Quality control** — adjustable WebP quality (default: 82%)
- **Dual engine** — uses GD library (available on 95%+ of hosts) with Imagick fallback

## Requirements

- WordPress 5.0+
- PHP 7.4+
- GD library with WebP support **or** Imagick with WebP support

## Installation

1. Download or clone this repository into `wp-content/plugins/auto-webp/`
2. Activate the plugin in WordPress admin (Plugins page)
3. Configure at **Settings > Auto WebP**

```bash
cd wp-content/plugins/
git clone https://github.com/denmlt/auto-webp.git
```

## Settings

| Option | Default | Description |
|--------|---------|-------------|
| WebP Quality | 82% | Compression quality (1-100). Lower = smaller files |
| Auto-convert on Upload | On | Create WebP copies automatically |
| Serve WebP | On | Replace URLs for supporting browsers |
| Cleanup | On | Delete WebP files when media is deleted |

## How It Works

The plugin creates `.webp` copies alongside your original images (e.g., `photo.jpg` -> `photo.webp`). When a visitor's browser supports WebP (all modern browsers do), the plugin swaps image URLs through WordPress filters — no redirect, no rewrite rules.

This approach works on:
- Apache
- Nginx
- LiteSpeed
- Any PHP-capable hosting

## Screenshots

Settings page shows conversion engine, stats, bulk converter, and configuration options. The Media Library gets a "WebP" column showing conversion status and space savings.

## License

GPL-2.0+ — see [LICENSE](LICENSE) for details.

## Author

[Denys Dyuzhaev](https://dyuzhaev.com/)
