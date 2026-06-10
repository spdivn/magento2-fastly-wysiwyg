<?php

declare(strict_types=1);

namespace Spdivn\FastlyWysiwyg\Model\View\Asset;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Media\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @copyright (c) 2026, Spdivn
 * @package Spdivn/FastlyWysiwyg
 * @subpackage Model
 */
class WysiwygImage extends \Fastly\Cdn\Model\View\Asset\Image {

    private string $sourceUrl;
    private StoreManagerInterface $wysiwygStoreManager;

    /**
     *
     * @param ConfigInterface $mediaConfig
     * @param ContextInterface $context
     * @param EncryptorInterface $encryptor
     * @param ScopeConfigInterface $scopeConfig
     * @param ImageHelper $imageHelper
     * @param StoreManagerInterface $storeManager
     * @param File $file
     * @param Filesystem $filesystem
     * @param string $sourceUrl
     * @param string $filePath
     * @param array $miscParams
     */
    public function __construct(
        ConfigInterface $mediaConfig,
        ContextInterface $context,
        EncryptorInterface $encryptor,
        ScopeConfigInterface $scopeConfig,
        ImageHelper $imageHelper,
        StoreManagerInterface $storeManager,
        File $file,
        Filesystem $filesystem,
        string $sourceUrl,
        string $filePath = '',
        array $miscParams = []
    ) {
        $this->sourceUrl = $sourceUrl;
        $this->wysiwygStoreManager = $storeManager;
        parent::__construct(
            $mediaConfig,
            $context,
            $encryptor,
            $scopeConfig,
            $imageHelper,
            $storeManager,
            $file,
            $filesystem,
            $filePath,
            $miscParams
        );
    }

    /**
     * Returns the media-relative path (e.g. "wysiwyg/image.jpg") derived from
     * sourceUrl. Fixes two problems in the parent when used for WYSIWYG images:
     *
     * 1. getForceLossyUrl() reads pathinfo() on this value to detect PNG extension.
     * 2. When image_verify is on, the parent checks file existence against the media
     *    dir using this path. The correct relative path ensures the real file is found,
     *    avoiding Fastly's getDefaultPlaceholderUrl() branch which accesses
     *    miscParams['image_type'] — already unset by the Magento parent constructor.
     *
     * Falls back to '' so the directory-exists check passes safely on CDN URLs.
     */
    public function getSourceFile(): string
    {
        $mediaBaseUrl = $this->wysiwygStoreManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $urlPath = parse_url($this->sourceUrl, PHP_URL_PATH) ?? '';
        $mediaBasePath = rtrim(parse_url($mediaBaseUrl, PHP_URL_PATH) ?? '/', '/');

        if ($mediaBasePath === '' || !str_contains($urlPath, $mediaBasePath)) {
            return '';
        }

        return ltrim(str_replace($mediaBasePath, '', $urlPath), '/');
    }

    /**
     * Safety fallback: always return sourceUrl regardless of the base file path,
     * in case getSourceFile() returns '' on CDN/unusual URL structures.
     */
    public function getBaseFileUrl($baseFile): string
    {
        return $this->sourceUrl;
    }
}
