<?php

declare(strict_types=1);

namespace Spdivn\FastlyWysiwyg\Model\Processor;

use Spdivn\FastlyWysiwyg\Model\View\Asset\WysiwygImageFactory;
use Fastly\Cdn\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PageCache\Model\Config as PageCacheConfig;

/**
 * Processes HTML content and appends Fastly Image Optimization query parameters
 * to every URL that contains "wysiwyg" in the path, covering:
 *
 *  - <img src="...">                           standard WYSIWYG images
 *  - <source srcset="...">                     responsive picture elements
 *  - <video poster="...">                      video poster images
 *  - <div data-video-fallback-src="...">       PageBuilder data-* URL attributes
 *  - style="background-image: url(...)"        inline CSS background images
 *  - data-background-images="{...JSON...}"     PageBuilder JSON attribute (with &quot; encoding)
 *
 * Matches both /media/wysiwyg/ and /media/.renditions/wysiwyg/ paths.
 * Delegates URL optimization to \Fastly\Cdn\Model\View\Asset\Image::getUrl() via
 * WysiwygImage subclass that overrides getBaseFileUrl() for WYSIWYG paths.
 * Already-processed URLs (Fastly params already present) are skipped (idempotent).
 */
class WysiwygImageUrlProcessor {
    /** @const string */
    private const HTML_ATTR_PATTERN = '~(\b(?:src|srcset|poster)\s*=\s*["\'])([^"\']*wysiwyg[^"\']+)(["\'])~i';
    /** @const string */
    private const DATA_ATTR_URL_PATTERN = '~(\bdata-(?!background-images)[\w-]+\s*=\s*["\'])([^"\']*wysiwyg[^"\']+)(["\'])~i';
    /** @const string */
    private const CSS_URL_PATTERN = '~(url\s*\(\s*["\']?)([^"\')\s]*wysiwyg[^"\')\s]*)(["\']?\s*\))~i';
    /** @const string */
    private const DATA_BG_IMAGES_PATTERN = '~(\bdata-background-images\s*=\s*")([^"]+)(")~i';
    /** @const string */
    private const JSON_URL_PATTERN = '~(https?://[^\s"\'<>&\\\\]*wysiwyg[^\s"\'<>&\\\\]*)~i';

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param WysiwygImageFactory $wysiwygImageFactory
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WysiwygImageFactory $wysiwygImageFactory
    ) {}

    /**
     *
     * @param string $html
     * @return string
     */
    public function process(string $html): string {
        if (!$this->hasAnyActiveParam()) {
            return $html;
        }

        $html = preg_replace_callback(
            self::HTML_ATTR_PATTERN,
            fn(array $m): string => $this->replaceUrl($m),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            self::DATA_ATTR_URL_PATTERN,
            fn(array $m): string => $this->replaceUrl($m),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            self::CSS_URL_PATTERN,
            fn(array $m): string => $this->replaceUrl($m),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            self::DATA_BG_IMAGES_PATTERN,
            fn(array $m): string => $this->replaceJsonBgImages($m),
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Checks if Fastly Image Optimization is enabled in config and Fastly is the selected page cache type.
     *
     * @return bool
     */
    private function isFastlyImageOptimizationEnabled(): bool {
        return $this->scopeConfig->isSetFlag(Config::XML_FASTLY_IMAGE_OPTIMIZATIONS)
            && (int)$this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TYPE) === Config::FASTLY;
    }

    /**
     * Checks if Force Lossy Optimization is enabled in config and Fastly is the selected page cache type.
     *
     * @return bool
     */
    private function isForceLossyEnabled(): bool {
        return $this->scopeConfig->isSetFlag(Config::XML_FASTLY_FORCE_LOSSY)
            && (int)$this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TYPE) === Config::FASTLY;
    }

    /**
     * Checks if either Fastly Image Optimization or Force Lossy Optimization is enabled, to determine if processing is needed.
     *
     * @return bool
     */
    private function hasAnyActiveParam(): bool {
        return $this->isFastlyImageOptimizationEnabled() || $this->isForceLossyEnabled();
    }

    /**
     * Common replacement logic for URLs found in HTML attributes and inline CSS.
     *
     * @param array $matches
     * @return string
     */
    private function replaceUrl(array $matches): string {
        [, $prefix, $url, $suffix] = $matches;

        return $prefix . $this->applyFastlyParams($url) . $suffix;
    }

    /**
     * Special handling for JSON-encoded URLs in data-background-images attributes.
     *
     * @param array $matches
     * @return string
     */
    private function replaceJsonBgImages(array $matches): string {
        [, $attrPrefix, $jsonValue, $attrSuffix] = $matches;

        $processedJson = preg_replace_callback(
            self::JSON_URL_PATTERN,
            fn(array $m): string => $this->applyFastlyParams($m[1]),
            $jsonValue
        ) ?? $jsonValue;

        return $attrPrefix . $processedJson . $attrSuffix;
    }

    /**
     * Delegates to \Fastly\Cdn\Model\View\Asset\Image::getUrl() via WysiwygImage.
     * Strips width/height/canvas params afterward — Fastly appends these from
     * miscParams but they are meaningless for WYSIWYG images without fixed dimensions.
     *
     * @param string $url
     * @return string
     */
    private function applyFastlyParams(string $url): string {
        if ($this->hasAlreadyFastlyParams($url)) {
            return $url;
        }
        try {
            $optimizedUrl = $this->wysiwygImageFactory->create($url)->getUrl();

            return $this->stripDimensionParams($optimizedUrl);
        } catch (FileSystemException|NoSuchEntityException $e) {
            unset($e);
            return $url;
        }
    }

    /**
     * Removes width, height and canvas params added by Fastly's compileFastlyParameters().
     * These are derived from miscParams['image_width'/'image_height'] which are null
     * for WYSIWYG images — they would appear as width=&height=&canvas=: in the URL.
     *
     * @param string $url
     * @return string
     */
    private function stripDimensionParams(string $url): string {
        $parts = parse_url($url);
        if (!isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $params);
        unset($params['width'], $params['height'], $params['canvas']);

        $params = array_filter($params, static fn($v) => $v !== null && $v !== '');

        $base = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '');

        return empty($params) ? $base : $base . '?' . http_build_query($params);
    }

    /**
     * Checks if the URL already contains any Fastly Image Optimization parameters to avoid double-processing.
     *
     * @param string $url
     * @return bool
     */
    private function hasAlreadyFastlyParams(string $url): bool {
        $query = parse_url($url, PHP_URL_QUERY) ?? '';

        return str_contains($query, 'auto=')
            || str_contains($query, 'fit=')
            || str_contains($query, 'bg-color=')
            || str_contains($query, 'quality=')
            || str_contains($query, 'optimize=')
            || str_contains($query, 'format=');
    }
}
