<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class ConversionFinalizer
{
    private MarkdownPostProcessor $markdown;
    private TextCleaner $textCleaner;
    private BlockSpanFilter $spanFilter;
    private HeadingCleaner $headingCleaner;
    private FontStyleCleaner $fontStyleCleaner;
    private HeaderFooterCleaner $headerFooterCleaner;
    private ImageExtractor $imageExtractor;
    private CodeBlockDetector $codeBlockDetector;
    private EquationReplacer $equationReplacer;

    public function __construct(
        ?MarkdownPostProcessor $markdown = null,
        ?TextCleaner $textCleaner = null,
        ?BlockSpanFilter $spanFilter = null,
        ?HeadingCleaner $headingCleaner = null,
        ?FontStyleCleaner $fontStyleCleaner = null,
        ?HeaderFooterCleaner $headerFooterCleaner = null,
        ?ImageExtractor $imageExtractor = null,
        ?CodeBlockDetector $codeBlockDetector = null,
        ?EquationReplacer $equationReplacer = null
    ) {
        $this->markdown = $markdown ?? new MarkdownPostProcessor();
        $this->textCleaner = $textCleaner ?? new TextCleaner();
        $this->spanFilter = $spanFilter ?? new BlockSpanFilter();
        $this->headingCleaner = $headingCleaner ?? new HeadingCleaner();
        $this->fontStyleCleaner = $fontStyleCleaner ?? new FontStyleCleaner();
        $this->headerFooterCleaner = $headerFooterCleaner ?? new HeaderFooterCleaner();
        $this->imageExtractor = $imageExtractor ?? new ImageExtractor();
        $this->codeBlockDetector = $codeBlockDetector ?? new CodeBlockDetector();
        $this->equationReplacer = $equationReplacer ?? new EquationReplacer();
    }

    /**
     * Native late-stage boundary for marker.convert::convert_single_pdf after OCR/layout/table stages.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<string> $badSpanIds
     * @param array<string, mixed> $metadata
     * @param list<list<mixed>> $imagePayloads
     * @param list<string> $equationPredictions
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
        array $metadata = [],
        array $imagePayloads = [],
        array $equationPredictions = [],
        ?int $equationModelMaxTokens = null,
        ?float $equationIntersectionThreshold = null
    ): array {
        $settings ??= new MarkerSettings();

        $pages = $this->spanFilter->filterPages($pages, $badSpanIds, $settings);
        if (!isset($metadata['block_stats']) || !is_array($metadata['block_stats'])) {
            $metadata['block_stats'] = [];
        }
        $pages = $this->identifyAndIndentCodeBlocks($pages, $metadata);
        if ($equationPredictions !== []) {
            $equationResult = $this->equationReplacer->replaceEquations(
                $pages,
                $equationPredictions,
                $equationModelMaxTokens ?? (int) $settings->get('TEXIFY_MODEL_MAX'),
                $equationIntersectionThreshold ?? (float) $settings->get('BBOX_INTERSECTION_THRESH')
            );
            $pages = $equationResult['pages'];
            $metadata['block_stats']['equations'] = $equationResult['metadata'];
            if ($equationResult['converted_spans'] !== []) {
                $metadata['converted_equation_spans'] = $equationResult['converted_spans'];
            }
        }
        if ($settings->extractImages() && $imagePayloads !== []) {
            $pages = $this->insertSuppliedImagePayloads($pages, $imagePayloads);
            $metadata['block_stats']['images'] = count($this->imageExtractor->imagesToDict($pages));
        }
        $pages = $this->headingCleaner->splitHeadingBlocks(
            $pages,
            (float) $settings->get('BBOX_INTERSECTION_THRESH')
        );
        $pages = $this->headingCleaner->inferHeadingLevels($pages);
        $pages = $this->markBoldItalic($pages);

        $metadata['computed_toc'] = $this->headingCleaner->computeToc($pages);
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
     * Native supplied-payload boundary for marker.images.extract::extract_images.
     *
     * Upstream renders image regions after bad span types are filtered, then
     * inserts Marker Markdown image spans before heading splitting and final
     * Markdown assembly. This method accepts the rendered page image payloads
     * that pypdfium/PIL would otherwise create.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<list<mixed>> $imagePayloads
     * @return list<array<string, mixed>>
     */
    private function insertSuppliedImagePayloads(array $pages, array $imagePayloads): array
    {
        foreach ($imagePayloads as $pageIndex => $payloads) {
            if (!is_array($payloads) || !array_is_list($payloads)) {
                throw new InvalidArgumentException('Supplied image payloads must be a list per page.');
            }
            if (!isset($pages[$pageIndex]) || !is_array($pages[$pageIndex])) {
                throw new InvalidArgumentException('Supplied image payloads must match an extracted page index.');
            }

            $pages[$pageIndex] = $this->imageExtractor->insertImagePlaceholders($pages[$pageIndex], $payloads);
        }

        return $pages;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param array<string, mixed> $metadata
     * @return list<array<string, mixed>>
     */
    private function identifyAndIndentCodeBlocks(array $pages, array &$metadata): array
    {
        $stats = $this->pageTextStats($pages);
        $codeBlockCount = 0;
        $spanCounter = 0;

        foreach ($pages as $pageIndex => $page) {
            if (!isset($page['blocks']) || !is_array($page['blocks'])) {
                continue;
            }

            foreach ($page['blocks'] as $blockIndex => $block) {
                if (!is_array($block) || $this->blockType($block) !== 'Text') {
                    continue;
                }

                $lines = array_values(array_filter(
                    $block['lines'] ?? [],
                    static fn (mixed $line): bool => is_array($line)
                ));
                if ($lines === [] || !$this->blockHasSpans($lines)) {
                    continue;
                }

                $detectorLines = array_map(fn (array $line): array => $this->codeDetectorLine($line), $lines);
                if (!$this->codeBlockDetector->isCodeBlock(
                    $detectorLines,
                    80,
                    $stats['average_font_size'],
                    $stats['median_line_height']
                )) {
                    continue;
                }

                $firstSpan = $this->firstSpan($lines);
                $bbox = $this->bbox($block['bbox'] ?? null) ?? $this->bboxFromLines($lines);
                $pages[$pageIndex]['blocks'][$blockIndex]['block_type'] = 'Code';
                if (array_key_exists('type', $pages[$pageIndex]['blocks'][$blockIndex])) {
                    $pages[$pageIndex]['blocks'][$blockIndex]['type'] = 'Code';
                }
                $pages[$pageIndex]['blocks'][$blockIndex]['lines'] = [[
                    'bbox' => $bbox,
                    'spans' => [[
                        'span_id' => $spanCounter . '_fix_code',
                        'text' => $this->codeBlockDetector->indentBlock($detectorLines),
                        'font' => (string) ($firstSpan['font'] ?? ''),
                        'font_weight' => (float) ($firstSpan['font_weight'] ?? 0.0),
                        'font_size' => (float) ($firstSpan['font_size'] ?? 0.0),
                        'bbox' => $bbox,
                    ]],
                ]];

                $spanCounter++;
                $codeBlockCount++;
            }
        }

        $metadata['block_stats']['code'] = $codeBlockCount;

        return $pages;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return array{average_font_size: float|null, median_line_height: float|null}
     */
    private function pageTextStats(array $pages): array
    {
        $fontSizes = [];
        $lineHeights = [];

        foreach ($pages as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if (!is_array($block)) {
                    continue;
                }
                foreach (($block['lines'] ?? []) as $line) {
                    if (!is_array($line)) {
                        continue;
                    }

                    $lineText = '';
                    foreach (($line['spans'] ?? []) as $span) {
                        if (is_array($span)) {
                            $lineText .= (string) ($span['text'] ?? '');
                        }
                    }
                    if (trim($lineText) === '') {
                        continue;
                    }

                    $bbox = $this->bbox($line['bbox'] ?? null);
                    if ($bbox !== null) {
                        $lineHeights[] = $bbox[3] - $bbox[1];
                    }

                    foreach (($line['spans'] ?? []) as $span) {
                        if (
                            is_array($span)
                            && trim((string) ($span['text'] ?? '')) !== ''
                            && isset($span['font_size'])
                            && (is_int($span['font_size']) || is_float($span['font_size']))
                        ) {
                            $fontSizes[] = (float) $span['font_size'];
                        }
                    }
                }
            }
        }

        return [
            'average_font_size' => $fontSizes === [] ? null : array_sum($fontSizes) / count($fontSizes),
            'median_line_height' => $lineHeights === [] ? null : $this->median($lineHeights),
        ];
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function codeDetectorLine(array $line): array
    {
        $text = '';
        $fontSizes = [];
        foreach (($line['spans'] ?? []) as $span) {
            if (!is_array($span)) {
                continue;
            }
            $text .= (string) ($span['text'] ?? '');
            if (isset($span['font_size']) && (is_int($span['font_size']) || is_float($span['font_size']))) {
                $fontSizes[] = (float) $span['font_size'];
            }
        }

        $bbox = $this->bbox($line['bbox'] ?? null) ?? [0.0, 0.0, 0.0, 0.0];
        $detectorLine = [
            'text' => $text,
            'bbox' => $bbox,
            'height' => $bbox[3] - $bbox[1],
        ];
        if ($fontSizes !== []) {
            $detectorLine['font_size'] = array_sum($fontSizes) / count($fontSizes);
        }

        return $detectorLine;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function blockHasSpans(array $lines): bool
    {
        foreach ($lines as $line) {
            foreach (($line['spans'] ?? []) as $span) {
                if (is_array($span)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    private function firstSpan(array $lines): array
    {
        foreach ($lines as $line) {
            foreach (($line['spans'] ?? []) as $span) {
                if (is_array($span)) {
                    return $span;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockType(array $block): string
    {
        return (string) ($block['type'] ?? $block['block_type'] ?? 'Text');
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        foreach ($value as $item) {
            if (!is_float($item) && !is_int($item)) {
                return null;
            }
        }

        return array_map(static fn (float|int $item): float => (float) $item, array_values($value));
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<float>
     */
    private function bboxFromLines(array $lines): array
    {
        $bboxes = [];
        foreach ($lines as $line) {
            $bbox = $this->bbox($line['bbox'] ?? null);
            if ($bbox !== null) {
                $bboxes[] = $bbox;
            }
        }
        if ($bboxes === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return [
            min(array_column($bboxes, 0)),
            min(array_column($bboxes, 1)),
            max(array_column($bboxes, 2)),
            max(array_column($bboxes, 3)),
        ];
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2.0;
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
