<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfTextDocumentExtractor
{
    private PdfTextBlockConverter $converter;
    private PdfPageArtifactSelector $artifactSelector;

    public function __construct(?PdfTextBlockConverter $converter = null, ?PdfPageArtifactSelector $artifactSelector = null)
    {
        $this->converter = $converter ?? new PdfTextBlockConverter();
        $this->artifactSelector = $artifactSelector ?? new PdfPageArtifactSelector();
    }

    /**
     * Native supplied-data boundary for marker.pdf.extract_text::get_text_blocks.
     *
     * Upstream gets the dictionary pages from pdftext over page_range and then
     * enumerates that sliced result, so span IDs restart at 0 even when page["page"]
     * remains the original document page number.
     *
     * @param list<array<string, mixed>> $pdftextPages
     * @param list<array<string, mixed>> $toc
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     toc: list<array<string, mixed>>,
     *     metadata: array{pdf_toc: list<array<string, mixed>>, pages: int, start_page: int, max_pages: int, pdftext_options: array<string, mixed>},
     *     page_range: list<int>
     * }
     */
    public function getTextBlocks(
        array $pdftextPages,
        ?int $maxPages = null,
        ?int $startPage = null,
        array $toc = [],
        bool $flattenPdf = false,
        ?int $workers = null,
        bool $sort = false
    ): array {
        $totalPages = count($pdftextPages);
        $startPage ??= 0;

        if ($startPage < 0 || ($totalPages > 0 && $startPage >= $totalPages) || ($totalPages === 0 && $startPage !== 0)) {
            throw new InvalidArgumentException('start_page must be within supplied pdftext pages.');
        }

        if ($maxPages !== null && $maxPages < 0) {
            throw new InvalidArgumentException('max_pages must be zero or greater.');
        }

        if ($workers !== null && $workers < 1) {
            throw new InvalidArgumentException('pdftext workers must be at least one when supplied.');
        }

        $pageCount = $totalPages - $startPage;
        if ($maxPages !== null && $maxPages > 0) {
            $pageCount = min($maxPages, $pageCount);
        }

        $selectedPages = array_slice($pdftextPages, $startPage, $pageCount);
        $pages = [];
        foreach (array_values($selectedPages) as $relativeIndex => $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Supplied pdftext page entries must be arrays.');
            }
            $page = $this->sanitizeDictionaryOutputPage($page);
            if ($sort) {
                $page['blocks'] = $this->sortDictionaryOutputBlocks($page['blocks'] ?? null);
            }
            $pages[] = $this->converter->pdftextFormatToPage($page, $relativeIndex);
        }

        $pageRange = $pageCount > 0 ? range($startPage, $startPage + $pageCount - 1) : [];

        return [
            'pages' => $pages,
            'toc' => array_values($toc),
            'metadata' => [
                'pdf_toc' => array_values($toc),
                'pages' => count($pages),
                'start_page' => $startPage,
                'max_pages' => $pageCount,
                'pdftext_options' => array_filter([
                    'page_range' => $pageRange,
                    'keep_chars' => false,
                    'flatten_pdf' => $flattenPdf,
                    'workers' => $workers,
                    'sort' => $sort ? true : null,
                ], static fn (mixed $value): bool => $value !== null),
            ],
            'page_range' => $pageRange,
        ];
    }

    /**
     * Native supplied-data bridge across marker.pdf.extract_text::get_text_blocks
     * and marker.layout.order::sort_blocks_in_reading_order.
     *
     * Upstream trims the PDFium document to the selected page range before
     * rendering layout/order images, then zips ordering predictions with the
     * relative pdftext pages. This helper preserves that boundary for callers
     * that already have pdftext dictionaries and supplied order-model output.
     *
     * @param list<array<string, mixed>> $pdftextPages
     * @param list<array<string, mixed>> $orderResults
     * @param list<mixed> $orderImages
     * @param list<array<string, mixed>> $toc
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     toc: list<array<string, mixed>>,
     *     metadata: array<string, mixed>,
     *     page_range: list<int>
     * }
     */
    public function getOrderedTextBlocks(
        array $pdftextPages,
        array $orderResults,
        array $orderImages = [],
        ?int $maxPages = null,
        ?int $startPage = null,
        array $toc = [],
        bool $flattenPdf = false,
        ?int $workers = null,
        bool $sort = false,
        float $batchMultiplier = 1.0,
        ?LayoutOrderer $orderer = null
    ): array {
        $document = $this->getTextBlocks(
            $pdftextPages,
            maxPages: $maxPages,
            startPage: $startPage,
            toc: $toc,
            flattenPdf: $flattenPdf,
            workers: $workers,
            sort: $sort
        );
        $orderer ??= new LayoutOrderer();
        $selectedPageNumbers = $this->artifactSelector->pageNumbersFromPages($document['pages']);
        $orderImages = $this->selectSuppliedPageArtifacts(
            $orderImages,
            count($pdftextPages),
            $document['page_range'],
            count($document['pages']),
            $selectedPageNumbers
        );
        $orderResults = $this->selectSuppliedPageArtifacts(
            $orderResults,
            count($pdftextPages),
            $document['page_range'],
            count($document['pages']),
            $selectedPageNumbers
        );

        $ordered = $orderer->runWithSuppliedOrder(
            $orderImages,
            $document['pages'],
            $orderResults,
            $batchMultiplier
        );
        $document['pages'] = $orderer->sortBlocksInReadingOrder($ordered['pages']);
        $document['metadata']['order_plan'] = $ordered['plan'];
        $document['metadata']['supplied_boundaries'] = ['pdftext-dictionary', 'layout-order'];

        return $document;
    }

    /**
     * Upstream trims the PDFium document to the selected page range before
     * rendering order images and zipping model results with marker pages. If a
     * supplied artifact list still spans the original pdftext page list, slice it
     * to the same selected range; selected-only lists are already aligned.
     *
     * @param list<mixed> $artifacts
     * @param list<int> $pageRange
     * @return list<mixed>
     */
    private function selectSuppliedPageArtifacts(
        array $artifacts,
        int $sourcePageCount,
        array $pageRange,
        int $selectedPageCount,
        array $selectedPageNumbers = []
    ): array {
        return $this->artifactSelector->select($artifacts, $sourcePageCount, $pageRange, $selectedPageCount, $selectedPageNumbers);
    }

    /**
     * Upstream markerPDF calls pdftext.dictionary_output with keep_chars=false.
     * That path keeps page-level metadata, strips block/line keys down to bbox
     * and child collections, and returns only the core span fields emitted by
     * pdftext inference after removing per-character payloads before marker
     * stores char_blocks.
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function sanitizeDictionaryOutputPage(array $page): array
    {
        $bboxScale = $this->dictionaryOutputBboxScale($page);

        if (!isset($page['blocks']) || !is_array($page['blocks']) || !array_is_list($page['blocks'])) {
            return $page;
        }

        $blocks = [];
        foreach ($page['blocks'] as $block) {
            if (!is_array($block)) {
                $blocks[] = $block;
                continue;
            }

            $sanitizedBlock = [];
            if (array_key_exists('bbox', $block)) {
                $sanitizedBlock['bbox'] = $this->unnormalizeDictionaryOutputBbox($block['bbox'], $bboxScale);
            }
            if (array_key_exists('lines', $block)) {
                $sanitizedBlock['lines'] = $this->sanitizeDictionaryOutputLines($block['lines'], $bboxScale);
            }
            $blocks[] = $sanitizedBlock;
        }

        $page['blocks'] = $blocks;
        return $page;
    }

    /**
     * @param mixed $lines
     * @return mixed
     */
    private function sanitizeDictionaryOutputLines(mixed $lines, ?array $bboxScale = null): mixed
    {
        if (!is_array($lines) || !array_is_list($lines)) {
            return $lines;
        }

        $sanitizedLines = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                $sanitizedLines[] = $line;
                continue;
            }

            $sanitizedLine = [];
            if (array_key_exists('bbox', $line)) {
                $sanitizedLine['bbox'] = $this->unnormalizeDictionaryOutputBbox($line['bbox'], $bboxScale);
            }
            if (array_key_exists('spans', $line)) {
                $sanitizedLine['spans'] = $this->sanitizeDictionaryOutputSpans($line['spans'], $bboxScale);
            }
            $sanitizedLines[] = $sanitizedLine;
        }

        return $sanitizedLines;
    }

    /**
     * @param mixed $spans
     * @return mixed
     */
    private function sanitizeDictionaryOutputSpans(mixed $spans, ?array $bboxScale = null): mixed
    {
        if (!is_array($spans) || !array_is_list($spans)) {
            return $spans;
        }

        $sanitizedSpans = [];
        foreach ($spans as $span) {
            if (is_array($span)) {
                $sanitizedSpan = [];
                foreach (['text', 'bbox', 'font', 'rotation', 'char_start_idx', 'char_end_idx', 'url'] as $key) {
                    if (array_key_exists($key, $span)) {
                        $sanitizedSpan[$key] = $span[$key];
                    }
                }
                if (array_key_exists('bbox', $sanitizedSpan)) {
                    $sanitizedSpan['bbox'] = $this->unnormalizeDictionaryOutputBbox($sanitizedSpan['bbox'], $bboxScale);
                }
                if (array_key_exists('text', $span) && is_string($span['text'])) {
                    $sanitizedSpan['text'] = $this->normalizeDictionaryOutputText($span['text']);
                }
                $span = $sanitizedSpan;
            }
            $sanitizedSpans[] = $span;
        }

        return $sanitizedSpans;
    }

    /**
     * pdftext keeps page bboxes absolute, while block/line/span bboxes from
     * PDFium character extraction are normalized until dictionary_output scales
     * them by page width and height.
     *
     * @param array<string, mixed> $page
     * @return array{width: float, height: float}|null
     */
    private function dictionaryOutputBboxScale(array $page): ?array
    {
        if (!isset($page['width'], $page['height'])) {
            return null;
        }
        if (!is_int($page['width']) && !is_float($page['width'])) {
            return null;
        }
        if (!is_int($page['height']) && !is_float($page['height'])) {
            return null;
        }

        $width = (float) $page['width'];
        $height = (float) $page['height'];
        if ($width <= 1.0 || $height <= 1.0) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * @param mixed $value
     * @param array{width: float, height: float}|null $bboxScale
     * @return mixed
     */
    private function unnormalizeDictionaryOutputBbox(mixed $value, ?array $bboxScale): mixed
    {
        if ($bboxScale === null || !is_array($value) || count($value) !== 4) {
            return $value;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                return $value;
            }
            $bbox[] = (float) $part;
        }

        if (!$this->isNormalizedDictionaryOutputBbox($bbox)) {
            return $value;
        }

        return [
            round($bbox[0] * $bboxScale['width'], 1),
            round($bbox[1] * $bboxScale['height'], 1),
            round($bbox[2] * $bboxScale['width'], 1),
            round($bbox[3] * $bboxScale['height'], 1),
        ];
    }

    /**
     * @param list<float> $bbox
     */
    private function isNormalizedDictionaryOutputBbox(array $bbox): bool
    {
        foreach ($bbox as $part) {
            if ($part < -0.25 || $part > 1.25) {
                return false;
            }
        }

        return abs($bbox[2] - $bbox[0]) <= 1.5 && abs($bbox[3] - $bbox[1]) <= 1.5;
    }

    /**
     * Mirrors pdftext.postprocessing::sort_blocks for callers that request
     * dictionary_output(sort=true) before Marker page conversion.
     *
     * @param mixed $blocks
     * @return list<array<string, mixed>>
     */
    private function sortDictionaryOutputBlocks(mixed $blocks, float $tolerance = 1.25): array
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidArgumentException('pdftext page blocks must be a list before dictionary sort.');
        }

        $groups = [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                throw new InvalidArgumentException("pdftext block {$index} must be a dictionary before dictionary sort.");
            }

            $bbox = $this->dictionaryOutputBbox($block['bbox'] ?? null, "blocks[{$index}].bbox");
            $sortKey = $tolerance > 0.0 ? round($bbox[1] / $tolerance) * $tolerance : $bbox[1];
            $key = (string) $sortKey;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'sort_key' => $sortKey,
                    'blocks' => [],
                ];
            }
            $groups[$key]['blocks'][] = [
                'index' => $index,
                'left' => $bbox[0],
                'block' => $block,
            ];
        }

        usort(
            $groups,
            static fn (array $left, array $right): int => $left['sort_key'] <=> $right['sort_key']
        );

        $sorted = [];
        foreach ($groups as $group) {
            $groupBlocks = $group['blocks'];
            usort(
                $groupBlocks,
                static fn (array $left, array $right): int => ($left['left'] <=> $right['left']) ?: ($left['index'] <=> $right['index'])
            );
            foreach ($groupBlocks as $entry) {
                $sorted[] = $entry['block'];
            }
        }

        return $sorted;
    }

    /**
     * @param mixed $value
     * @return list<float>
     */
    private function dictionaryOutputBbox(mixed $value, string $field): array
    {
        if (!is_array($value) || count($value) !== 4) {
            throw new InvalidArgumentException("pdftext {$field} must be a four-number bbox before dictionary sort.");
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                throw new InvalidArgumentException("pdftext {$field} must be a four-number bbox before dictionary sort.");
            }
            $bbox[] = (float) $part;
        }

        return $bbox;
    }

    /**
     * pdftext.extraction.dictionary_output post-processes span text before
     * markerPDF receives it: special spaces are normalized, unsafe controls are
     * dropped, ligatures are expanded, and the internal hyphen marker becomes
     * the "-\n" sequence that markerPDF's converter later removes.
     */
    private function normalizeDictionaryOutputText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace(["\u{FFFE}", "\u{FEFF}", "\u{00A0}"], ' ', $text);
        $text = str_replace(["\r", "\n"], "\n", $text);
        $text = str_replace("\u{0009}", "\t", $text);

        $text = $this->removeUnsafeControlCharacters($text);

        foreach ([
            "\u{FB00}" => 'ff',
            "\u{FB03}" => 'ffi',
            "\u{FB04}" => 'ffl',
            "\u{FB01}" => 'fi',
            "\u{FB02}" => 'fl',
            "\u{FB06}" => 'st',
            "\u{FB05}" => 'st',
        ] as $ligature => $replacement) {
            $text = str_replace($ligature, $replacement, $text);
        }

        return str_replace("\x02", "-\n", $text);
    }

    private function removeUnsafeControlCharacters(string $text): string
    {
        $normalized = preg_replace_callback(
            '/\p{C}/u',
            static function (array $match): string {
                $char = $match[0];
                if (in_array($char, ["\x02", "\n", "\r", "\f", "\t", ' '], true)) {
                    return $char;
                }

                return '';
            },
            $text
        );

        if ($normalized === null) {
            return preg_replace('/[\x00-\x01\x03-\x08\x0B\x0E-\x1F\x7F]/', '', $text) ?? $text;
        }

        return $normalized;
    }
}
