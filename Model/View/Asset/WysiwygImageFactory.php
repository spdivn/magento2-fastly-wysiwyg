<?php

declare(strict_types=1);

namespace Spdivn\FastlyWysiwyg\Model\View\Asset;

use Magento\Framework\ObjectManagerInterface;

/**
 * @copyright (c) 2026, Spdivn
 * @package Spdivn/FastlyWysiwyg
 * @subpackage Model
 */
class WysiwygImageFactory {

    /** @const array */
    private const MISC_PARAMS = [
        'image_width'             => null,
        'image_height'            => null,
        'keep_aspect_ratio'       => false,
        'keep_frame'              => false,
        'keep_transparency'       => false,
        'remove_borders'          => false,
        'background'              => [255, 255, 255],
        'quality'                 => null,
        'angle'                   => null,
        'watermark_file'          => null,
        'watermark_image_opacity' => null,
        'watermark_position'      => null,
        'watermark_width'         => null,
        'watermark_height'        => null,
        'image_type'              => 'image',
    ];

    /**
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(private readonly ObjectManagerInterface $objectManager) {}

    /**
     *
     * @param string $sourceUrl
     * @return WysiwygImage
     */
    public function create(string $sourceUrl): WysiwygImage {
        return $this->objectManager->create(WysiwygImage::class, [
            'sourceUrl'  => $sourceUrl,
            'filePath'   => '',
            'miscParams' => self::MISC_PARAMS,
        ]);
    }
}
