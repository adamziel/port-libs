<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfTextDocumentExtractor
{
    private PdfTextBlockConverter $converter;

    public function __construct(?PdfTextBlockConverter $converter = null)
    {
        $this->converter = $converter ?? new PdfTextBlockConverter();
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
        ?int $workers = null
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
        float $batchMultiplier = 1.0,
        ?LayoutOrderer $orderer = null
    ): array {
        $document = $this->getTextBlocks(
            $pdftextPages,
            maxPages: $maxPages,
            startPage: $startPage,
            toc: $toc,
            flattenPdf: $flattenPdf,
            workers: $workers
        );
        $orderer ??= new LayoutOrderer();

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
     * Upstream markerPDF calls pdftext.dictionary_output with keep_chars=false.
     * That path keeps page-level metadata, strips block/line keys down to bbox
     * and child collections, and removes per-character payloads from spans
     * before marker stores char_blocks.
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function sanitizeDictionaryOutputPage(array $page): array
    {
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
                $sanitizedBlock['bbox'] = $block['bbox'];
            }
            if (array_key_exists('lines', $block)) {
                $sanitizedBlock['lines'] = $this->sanitizeDictionaryOutputLines($block['lines']);
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
    private function sanitizeDictionaryOutputLines(mixed $lines): mixed
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
                $sanitizedLine['bbox'] = $line['bbox'];
            }
            if (array_key_exists('spans', $line)) {
                $sanitizedLine['spans'] = $this->sanitizeDictionaryOutputSpans($line['spans']);
            }
            $sanitizedLines[] = $sanitizedLine;
        }

        return $sanitizedLines;
    }

    /**
     * @param mixed $spans
     * @return mixed
     */
    private function sanitizeDictionaryOutputSpans(mixed $spans): mixed
    {
        if (!is_array($spans) || !array_is_list($spans)) {
            return $spans;
        }

        $sanitizedSpans = [];
        foreach ($spans as $span) {
            if (is_array($span)) {
                unset($span['chars']);
            }
            $sanitizedSpans[] = $span;
        }

        return $sanitizedSpans;
    }
}
