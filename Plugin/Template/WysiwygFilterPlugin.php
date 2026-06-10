<?php

declare(strict_types=1);

namespace Spdivn\FastlyWysiwyg\Plugin\Template;

use Spdivn\FastlyWysiwyg\Model\Processor\WysiwygImageUrlProcessor;
use Magento\Framework\Filter\Template;

/**
 * @copyright (c) 2026, Spdivn
 * @package Spdivn/FastlyWysiwyg
 * @subpackage Plugin
 *
 * After-plugin on CMS and Catalog template filters.
 * Appends Fastly Image Optimization parameters to <img> src URLs
 * found in WYSIWYG-rendered HTML content.
 *
 * Targets (declared in etc/di.xml):
 *  - Magento\Cms\Model\Template\Filter      (CMS Pages, CMS Blocks, Widgets)
 *  - Magento\Catalog\Model\Template\Filter  (Product & Category descriptions)
 */
class WysiwygFilterPlugin {

    /**
     *
     * @param WysiwygImageUrlProcessor $processor
     */
    public function __construct(
        private readonly WysiwygImageUrlProcessor $processor
    ) {}

    /**
     * Processes the rendered HTML string after the core filter runs.
     *
     * @param Template $subject
     * @param string   $result  The HTML already processed by the core filter.
     * @return string
     */
    public function afterFilter(Template $subject, string $result): string {
        return $this->processor->process($result);
    }
}
