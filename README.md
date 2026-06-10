# Spdivn_FastlyWysiwyg — Magento 2 Fastly Image Optimization for WYSIWYG

A Magento 2 module that extends Fastly's native Image Optimization to images inserted via the WYSIWYG editor in CMS Pages, CMS Blocks, Widgets, and product/category descriptions.

---

## Features

- **WYSIWYG image optimization** — automatically appends Fastly Image Optimization query parameters to every WYSIWYG image URL rendered on the frontend.
- **Broad HTML coverage** — handles `<img src>`, `<source srcset>`, `<video poster>`, inline CSS `background-image: url()`, `data-*` URL attributes, and PageBuilder's JSON-encoded `data-background-images`.
- **Renditions support** — matches both `/media/wysiwyg/` and `/media/.renditions/wysiwyg/` paths.
- **Idempotent** — already-optimized URLs (Fastly params already present) are skipped; safe to run multiple times.
- **Zero admin config** — activates automatically when Fastly Image Optimization or Force Lossy Optimization is enabled in the existing Fastly module configuration.
- **Clean URLs** — strips meaningless `width`, `height`, and `canvas` parameters that Fastly would otherwise append for WYSIWYG images without fixed dimensions.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.1` |
| Magento Framework | `*` |
| `magento/module-config` | `*` |
| `fastly/magento2` | `*` |

The [Fastly CDN for Magento 2](https://github.com/fastly/fastly-magento2) module must be installed and configured as the full-page cache provider.

---

## Installation

### Via Composer

Add the repository to your project's `composer.json` before requiring the package:

```json
"repositories": {
    "spdivn-fastly-wysiwyg": {
        "type": "git",
        "url": "https://github.com/spdivn/magento2-fastly-wysiwyg.git"
    }
}
```

Then install the module:

```bash
composer require spdivn/module-fastly-wysiwyg
bin/magento module:enable Spdivn_FastlyWysiwyg
bin/magento setup:upgrade
bin/magento cache:flush
```

### Manual

1. Copy the module to `app/code/Spdivn/FastlyWysiwyg`.
2. Run:

```bash
bin/magento module:enable Spdivn_FastlyWysiwyg
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## Configuration

No additional configuration is required. The module reads the existing Fastly Image Optimization settings:

- **Stores → Configuration → Fastly CDN → Image Optimization → Enable Image Optimization**
- **Stores → Configuration → Fastly CDN → Image Optimization → Enable Force Lossy**

When either option is enabled and Fastly is the active full-page cache provider, the module will automatically optimize WYSIWYG image URLs on the frontend.

---

## How It Works

### Plugin Intercept

An after-plugin registered on both `Magento\Cms\Model\Template\Filter` and `Magento\Catalog\Model\Template\Filter` intercepts the final HTML output after template rendering. This covers:

- CMS Pages and CMS Blocks
- Widgets
- Product and category descriptions

### URL Processing

`WysiwygImageUrlProcessor` scans the HTML string for WYSIWYG image URLs using targeted regex patterns and delegates optimization to `WysiwygImage::getUrl()`, which wraps Fastly's own `\Fastly\Cdn\Model\View\Asset\Image`. The following URL contexts are handled:

| Context | Pattern matched |
|---|---|
| `<img src>`, `<source srcset>`, `<video poster>` | HTML attribute values |
| `data-*` attributes (e.g. `data-video-fallback-src`) | Generic `data-` URL attributes |
| Inline CSS | `background-image: url(...)` |
| PageBuilder JSON | `data-background-images="{...}"` (including `&quot;`-encoded variants) |

### WysiwygImage Asset

`WysiwygImage` subclasses `\Fastly\Cdn\Model\View\Asset\Image` and overrides `getSourceFile()` and `getBaseFileUrl()` to correctly resolve WYSIWYG paths (e.g. `wysiwyg/image.jpg`) from a full media URL, instead of relying on catalog product image conventions.

---

## Module Structure

```
etc/
  di.xml                                    Plugin declarations
  module.xml                                Module registration
Model/
  Processor/WysiwygImageUrlProcessor.php    HTML scanning and URL replacement logic
  View/Asset/WysiwygImage.php               Fastly asset subclass for WYSIWYG URLs
  View/Asset/WysiwygImageFactory.php        Generated factory
Plugin/
  Template/WysiwygFilterPlugin.php          After-plugin on CMS and Catalog template filters
registration.php
```

---

## License

[MIT](LICENSE) — © 2026 Ivan Spada
