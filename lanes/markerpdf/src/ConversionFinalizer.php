<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class ConversionFinalizer
{
    private MarkdownPostProcessor $markdown;
    private TextCleaner $textCleaner;
    private BlockSpanFilter $spanFilter;
    private HeadingCleaner $headingCleaner;
    private FontStyleCleaner $fontStyleCleaner;
    private HeaderFooterCleaner $headerFooterCleaner;
    private ImageExtractor $imageExtractor;

    public function __construct(
        ?MarkdownPostProcessor $markdown = null,
        ?TextCleaner $textCleaner = null,
        ?BlockSpanFilter $spanFilter = null,
        ?HeadingCleaner $headingCleaner = null,
        ?FontStyleCleaner $fontStyleCleaner = null,
        ?HeaderFooterCleaner $headerFooterCleaner = null,
        ?ImageExtractor $imageExtractor = null
    ) {
        $this->markdown = $markdown ?? new MarkdownPostProcessor();
        $this->textCleaner = $textCleaner ?? new TextCleaner();
        $this->spanFilter = $spanFilter ?? new BlockSpanFilter();
        $this->headingCleaner = $headingCleaner ?? new HeadingCleaner();
        $this->fontStyleCleaner = $fontStyleCleaner ?? new FontStyleCleaner();
        $this->headerFooterCleaner = $headerFooterCleaner ?? new HeaderFooterCleaner();
        $this->imageExtractor = $imageExtractor ?? new ImageExtractor();
    }

    /**
     * Native late-stage boundary for marker.convert::convert_single_pdf after OCR/layout/table/equation stages.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<string> $badSpanIds
     * @param array<string, mixed> $metadata
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     merged_pages: list<list<array<string, mixed>>>,
     *     text_blocks: list<array{text: string, block_type: string, page_start: bool, pnum: int|null}>,
     *     text: string,
     *     images: array<string, mixed>,
     *     metadata: array<string, mixed>
     * }
     */
    public function finalizePages(
        array $pages,
        array $badSpanIds = [],
        ?MarkerSettings $settings = null,
        array $metadata = []
    ): array {
        $settings ??= new MarkerSettings();

        $pages = $this->spanFilter->filterPages($pages, $badSpanIds, $settings);
        $pages = $this->headingCleaner->splitHeadingBlocks(
            $pages,
            (float) $settings->get('BBOX_INTERSECTION_THRESH')
        );
        $pages = $this->headingCleaner->inferHeadingLevels($pages);
        $pages = $this->markBoldItalic($pages);

        $metadata['computed_toc'] = $this->headingCleaner->computeToc($pages);
        if (!isset($metadata['block_stats']) || !is_array($metadata['block_stats'])) {
            $metadata['block_stats'] = [];
        }
        $metadata['block_stats']['header_footer'] = count($badSpanIds);

        $mergedPages = $this->markdown->mergeSpans($pages);
        $textBlocks = $this->markdown->mergeBlocks(
            $mergedPages,
            paginateOutput: $settings->paginateOutput(),
            defaultBlockType: (string) $settings->get('DEFAULT_BLOCK_TYPE')
        );
        $textBlocks = $this->headerFooterCleaner->filterCommonTitles($textBlocks);
        $fullText = $this->markdown->getFullText($textBlocks, $settings->pageSeparator());
        $fullText = $this->textCleaner->cleanForMarkdown($fullText);

        return [
            'pages' => $pages,
            'merged_pages' => $mergedPages,
            'text_blocks' => $textBlocks,
            'text' => $fullText,
            'images' => $settings->extractImages() ? $this->imageExtractor->imagesToDict($pages) : [],
            'metadata' => $metadata,
        ];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    private function markBoldItalic(array $pages): array
    {
        foreach ($pages as $pageIndex => $page) {
            if (!isset($page['blocks']) || !is_array($page['blocks'])) {
                continue;
            }

            $pages[$pageIndex]['blocks'] = $this->fontStyleCleaner->markBoldItalicSpans(
                array_values(array_filter($page['blocks'], static fn (mixed $block): bool => is_array($block)))
            );
        }

        return $pages;
    }
}
