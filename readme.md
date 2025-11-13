# WP Ongoing Image Weight Tracker

**Version:** 1.0.0  
**Author:** WordPress Ongoing  
**License:** GPL2  
**Requires WordPress:** 5.0 or higher  
**Tested up to:** 6.8  
**Minimum PHP:** 7.4  

---

## 📝 Description

**WP Ongoing Image Weight Tracker** is an admin plugin that allows you to **scan and analyze all images** used across your WordPress site, including:

- Posts  
- Pages  
- Custom Post Types (CPTs)  
- Featured Images  
- ACF (Advanced Custom Fields) image fields  

Each image is automatically **categorized by weight** (Heavy, Medium, Optimal) and displays which posts/pages use it, helping you identify optimization opportunities to improve site performance and SEO.

Perfect for administrators, SEO professionals, and developers who need to audit image performance and reduce page load times without guesswork.

---

## ⚙️ Main Features

- 🔍 **Comprehensive scanning** of images in content, featured images, and ACF fields.  
- 📊 **Automatic categorization** based on file size (Heavy > 500KB, Medium 150-500KB, Optimal ≤ 150KB).  
- 🎯 **Advanced filtering** by image format (JPG, PNG, WebP, AVIF, GIF, SVG) and status.  
- 📌 **Usage tracking** shows exactly which posts/pages use each image.  
- 🖼️ **Visual dashboard** with image previews and detailed information.  
- 🌐 **External image support** for CDN-hosted and remote images.  
- 🧠 Compatible with **ACF (Advanced Custom Fields)**.  
- 🌍 **Multilingual ready** (English/Spanish translations included).  
- 🔌 **Polylang compatible** for multilingual sites.  
- ⚡ **Performance optimized** with batch processing and 24-hour caching.  

---

## 🧭 How to Use

1. In the WordPress admin panel, go to  
   **Image Weight Tracker** in the sidebar.
2. Click **"Re-scan"** to start analyzing all published content.
3. Wait for the scan to complete (progress indicator shows status).
4. Review the results in the table:
   - **Red badges** = Heavy images (> 500KB)
   - **Yellow badges** = Medium images (150-500KB)
   - **Green badges** = Optimal images (≤ 150KB)
5. Use the **filters** to focus on specific formats or status categories.
6. Click on **"Page – Post"** links to see where each image is used.

The plugin provides analysis data; you'll need to optimize images manually or with a separate optimization tool.

---

## 🔄 How to Re-scan

1. Click the **"Re-scan"** button at the top of the dashboard.  
2. The plugin will clear cached data and scan all content again.  
3. New images will be detected automatically.  
4. Results are cached for 24 hours to ensure fast performance.

Each scan analyzes post content, featured images, and custom fields in batches to prevent server timeouts.

---

## 📦 Installation

1. Download or clone this repository into:  
   `/wp-content/plugins/`

2. Activate the plugin from the WordPress admin panel:  
   **Plugins → Image Weight Tracker by WP Ongoing → Activate**

3. Start using it from the new **Image Weight Tracker** menu in the sidebar.

---

## 🧰 Technical Notes

- Image sizes are detected using `filesize()` for local files and `wp_remote_head()` / `wp_remote_get()` for external URLs.  
- Results are cached in WordPress transients with a 24-hour TTL:  
  `wpoiwt_sz_{md5_hash}`  
- Batch processing prevents memory issues on large sites (default: 25 posts per batch).  
- Content is processed through a custom hook `wpoiwt_the_content` that applies standard WordPress filters.  
- Compatible with **multisite** installations (can be activated per site).  
- Follows **WordPress Coding Standards** (PHPCS validated).  

---

## ⚠️ Recommendations

- **Backup your database** before making bulk image changes based on scan results.  
- For sites with 10,000+ images, the initial scan may take several minutes.  
- External images require HTTP requests; slow CDNs may affect scan speed.  
- Use the filters to focus on specific categories instead of reviewing all images at once.  
- Re-scan after uploading new content or optimizing images to see updated results.

---

## 🎨 Supported Image Formats

- **JPG/JPEG** - Standard compressed images  
- **PNG** - Lossless images with transparency  
- **WebP** - Modern format with better compression  
- **AVIF** - Next-gen format with superior compression  
- **GIF** - Animated and simple images  
- **SVG** - Vector graphics  

---

## 📜 License

This plugin is licensed under the **GNU General Public License v2.0 (GPL2)**.  
You may use, modify, and distribute it freely under the terms of this license.

---

## 🧩 Credits

Developed by **WordPress Ongoing**  
With contributions from the WordPress developer community.
