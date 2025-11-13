=== Image Weight Tracker by WP Ongoing ===
Contributors: wordpressongoing
Tags: images, optimization, performance, seo, page speed
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Analyze and track image weights across your WordPress site to improve performance and SEO.

== Description ==

Image Weight Tracker by WP Ongoing provides a comprehensive dashboard to scan and analyze all images actually used in your posts, pages, and custom post types.

With this plugin, you can:

- Scan all published content for images
- Track image file sizes and formats
- Identify heavy images that may slow down your site
- See which pages use each image
- Filter images by format (JPG, PNG, WebP, AVIF, GIF, SVG)
- Filter images by status (Heavy, Medium, Optimal)
- View image previews directly in the dashboard

The plugin automatically categorizes images as:

- **Heavy** (> 500 KB) - Images that significantly impact page load times
- **Medium** (150 KB - 500 KB) - Images that could be optimized
- **Optimal** (≤ 150 KB) - Well-optimized images

Ideal for agencies, SEO professionals, and site administrators who need to audit image performance and identify optimization opportunities.

== Features ==

- **Comprehensive Image Scanning** - Analyzes images in content, featured images, and ACF fields
- **Real-time Size Detection** - Measures both local and external image file sizes
- **Smart Categorization** - Automatically classifies images by weight (Heavy/Medium/Optimal)
- **Advanced Filtering** - Filter by image format and status
- **Usage Tracking** - See exactly which posts/pages use each image
- **Visual Dashboard** - Clean, intuitive interface with image previews
- **Multilingual Ready** - Translation files included (English/Spanish)
- **Performance Optimized** - Batch processing with caching to prevent slowdowns
- **Multi-format Support** - JPG, JPEG, PNG, WebP, AVIF, GIF, SVG
- **External Image Support** - Tracks images hosted on CDNs and external sources
- **ACF Compatible** - Scans images from Advanced Custom Fields
- **Polylang Compatible** - Works seamlessly with multilingual sites

== Installation ==

1. Upload the plugin folder `wp-ongoing-image-weight-tracker` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Image Weight Tracker** in the WordPress admin sidebar.
4. Click **Re-scan** to start analyzing your images.
5. Review the results and identify images that need optimization.

== How It Works ==

1. **Scanning Process** - The plugin scans all published posts, pages, and custom post types in batches
2. **Content Analysis** - Extracts images from post content, featured images, and ACF fields
3. **Size Calculation** - Determines file size for local attachments and external images
4. **Categorization** - Classifies each image as Heavy, Medium, or Optimal based on thresholds
5. **Dashboard Display** - Shows results in an organized, filterable table with previews

The plugin uses WordPress caching (transients) to store image sizes for 24 hours, preventing repeated requests and ensuring fast performance.

== Frequently Asked Questions ==

= Does this plugin optimize images automatically? =

No. This is an analysis and tracking tool. It helps you identify which images need optimization, but you'll need to optimize them manually or use a separate optimization plugin.

= Can it scan images from page builders? =

Yes, it scans all content that passes through WordPress's content filters, including most page builders. It also supports ACF (Advanced Custom Fields).

= Does it work with external/CDN images? =

Yes, the plugin can measure the size of external images by making HTTP requests to retrieve file size information.

= Will it slow down my site? =

No. The scanning process runs on-demand in the admin dashboard and uses batch processing with caching. It doesn't affect your front-end site performance.

= What image formats are supported? =

JPG, JPEG, PNG, WebP, AVIF, GIF, and SVG.

= Can I change the size thresholds? =

The current version uses fixed thresholds (150 KB for Optimal, 500 KB for Medium). Custom thresholds will be available in a future update.

= Is it compatible with Polylang? =

Yes, the plugin is fully compatible with Polylang and Polylang Pro. It scans content in all languages.

== Screenshots ==

1. Main dashboard showing image analysis with status indicators
2. Filter images by format and status
3. View which posts/pages use each image
4. Image preview functionality

== Changelog ==

= 1.0.0 =
* Initial release
* Image scanning across posts, pages, and CPTs
* Support for local and external images
* File size detection and categorization
* Advanced filtering (format and status)
* Usage tracking per image
* Image preview functionality
* Multilingual support (English/Spanish)
* ACF compatibility
* Polylang compatibility
* WordPress Coding Standards compliant

== Upgrade Notice ==

= 1.0.0 =
Initial release of Image Weight Tracker by WP Ongoing.

== License ==

This plugin is licensed under the GPLv2 or later.
You are free to modify and redistribute it under the same license.

Developed by [WordPress Ongoing](https://wordpressongoing.com)
