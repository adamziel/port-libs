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
     * @param list<array<string, mixed>|\stdClass>|array{pages?: array<mixed>, metadata?: array<string, mixed>} $pdftextPages
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
        bool $sort = false,
        bool $keepChars = false,
        bool $disableLinks = false,
        bool $quoteLoosebox = true
    ): array {
        $pdftextPages = $this->normalizeSuppliedDictionaryPageList($pdftextPages);
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
            $page = $this->normalizeSuppliedDictionaryValue($page);
            if (!is_array($page)) {
                throw new InvalidArgumentException('Supplied pdftext page entries must be arrays.');
            }
            $page = $this->sanitizeDictionaryOutputPage($page, $keepChars, $disableLinks);
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
                    'keep_chars' => $keepChars,
                    'flatten_pdf' => $flattenPdf,
                    'workers' => $workers,
                    'sort' => $sort ? true : null,
                    'disable_links' => $disableLinks ? true : null,
                    'quote_loosebox' => $quoteLoosebox,
                ], static fn (mixed $value): bool => $value !== null),
            ],
            'page_range' => $pageRange,
        ];
    }

    /**
     * Some callers cache pdftext.dictionary_output alongside adapter metadata.
     * The upstream markerPDF boundary only consumes the ordered page list, so
     * unwrap that list before slicing and keep envelope payloads out of pages.
     *
     * @param array<mixed> $pdftextPages
     * @return list<mixed>
     */
    private function normalizeSuppliedDictionaryPageList(array $pdftextPages): array
    {
        if (!array_key_exists('blocks', $pdftextPages) && array_key_exists('pages', $pdftextPages)) {
            $pages = $pdftextPages['pages'];
            if ($pages instanceof \stdClass) {
                $pages = get_object_vars($pages);
            }
            if (is_array($pages)) {
                return array_values($pages);
            }
        }

        return array_values($pdftextPages);
    }

    /**
     * pdftext dictionary_output is often passed through JSON before native PHP
     * import. PHP's default json_decode keeps dictionaries as stdClass objects;
     * normalize those plain data objects before applying the stricter boundary
     * whitelist.
     */
    private function normalizeSuppliedDictionaryValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $nestedValue) {
            $value[$key] = $this->normalizeSuppliedDictionaryValue($nestedValue);
        }

        return $value;
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
     * @param list<array<string, mixed>|\stdClass> $pdftextPages
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
        ?LayoutOrderer $orderer = null,
        bool $keepChars = false,
        bool $disableLinks = false,
        bool $quoteLoosebox = true
    ): array {
        $document = $this->getTextBlocks(
            $pdftextPages,
            maxPages: $maxPages,
            startPage: $startPage,
            toc: $toc,
            flattenPdf: $flattenPdf,
            workers: $workers,
            sort: $sort,
            keepChars: $keepChars,
            disableLinks: $disableLinks,
            quoteLoosebox: $quoteLoosebox
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
    private function sanitizeDictionaryOutputPage(array $page, bool $keepChars = false, bool $disableLinks = false): array
    {
        $bboxScale = $this->dictionaryOutputBboxScale($page);

        $sanitizedPage = [];
        foreach (['page', 'bbox', 'width', 'height', 'rotation'] as $key) {
            if (array_key_exists($key, $page)) {
                $sanitizedPage[$key] = $page[$key];
            }
        }

        if (!$disableLinks && array_key_exists('refs', $page)) {
            $sanitizedPage['refs'] = $page['refs'];
        }

        if (!isset($page['blocks']) || !is_array($page['blocks']) || !array_is_list($page['blocks'])) {
            if (array_key_exists('blocks', $page)) {
                $sanitizedPage['blocks'] = $page['blocks'];
            }

            return $sanitizedPage;
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
                $sanitizedBlock['lines'] = $this->sanitizeDictionaryOutputLines($block['lines'], $bboxScale, $keepChars, $disableLinks);
            }
            $blocks[] = $sanitizedBlock;
        }

        $sanitizedPage['blocks'] = $blocks;
        return $sanitizedPage;
    }

    /**
     * @param mixed $lines
     * @return mixed
     */
    private function sanitizeDictionaryOutputLines(mixed $lines, ?array $bboxScale = null, bool $keepChars = false, bool $disableLinks = false): mixed
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
                $sanitizedLine['spans'] = $this->sanitizeDictionaryOutputSpans($line['spans'], $bboxScale, $keepChars, $disableLinks);
            }
            $sanitizedLines[] = $sanitizedLine;
        }

        return $sanitizedLines;
    }

    /**
     * @param mixed $spans
     * @return mixed
     */
    private function sanitizeDictionaryOutputSpans(mixed $spans, ?array $bboxScale = null, bool $keepChars = false, bool $disableLinks = false): mixed
    {
        if (!is_array($spans) || !array_is_list($spans)) {
            return $spans;
        }

        $sanitizedSpans = [];
        foreach ($spans as $span) {
            if (is_array($span)) {
                $sanitizedSpan = [];
                foreach (['text', 'bbox', 'font', 'rotation', 'char_start_idx', 'char_end_idx', 'url', 'superscript', 'subscript'] as $key) {
                    if ($disableLinks && $key === 'url') {
                        continue;
                    }
                    if (array_key_exists($key, $span)) {
                        $sanitizedSpan[$key] = $span[$key];
                    }
                }
                foreach (['superscript', 'subscript'] as $scriptKey) {
                    if (array_key_exists($scriptKey, $sanitizedSpan) && !is_bool($sanitizedSpan[$scriptKey])) {
                        throw new InvalidArgumentException("pdftext span {$scriptKey} must be boolean when supplied.");
                    }
                }
                foreach (['char_start_idx', 'char_end_idx'] as $indexKey) {
                    if (array_key_exists($indexKey, $sanitizedSpan)) {
                        $sanitizedSpan[$indexKey] = $this->dictionaryOutputIntegerMetadata($sanitizedSpan[$indexKey], "span.{$indexKey}");
                    }
                }
                if (
                    array_key_exists('char_start_idx', $sanitizedSpan)
                    && array_key_exists('char_end_idx', $sanitizedSpan)
                    && $sanitizedSpan['char_start_idx'] > $sanitizedSpan['char_end_idx']
                ) {
                    throw new InvalidArgumentException('pdftext span.char_start_idx must be less than or equal to span.char_end_idx.');
                }
                if (array_key_exists('bbox', $sanitizedSpan)) {
                    $sanitizedSpan['bbox'] = $this->unnormalizeDictionaryOutputBbox($sanitizedSpan['bbox'], $bboxScale);
                }
                if (array_key_exists('font', $sanitizedSpan) && is_array($sanitizedSpan['font'])) {
                    $sanitizedSpan['font'] = $this->sanitizeDictionaryOutputFont($sanitizedSpan['font']);
                }
                if (array_key_exists('text', $span) && is_string($span['text'])) {
                    $sanitizedSpan['text'] = $this->normalizeDictionaryOutputText($span['text']);
                }
                if ($keepChars) {
                    if (!array_key_exists('chars', $span)) {
                        throw new InvalidArgumentException('pdftext span chars are required when keep_chars=true.');
                    }
                    $sanitizedChars = $this->sanitizeDictionaryOutputChars($span['chars'], $bboxScale, $sanitizedSpan);
                    $sanitizedSpan = $this->inferDictionaryOutputSpanCharacterRange($sanitizedSpan, $sanitizedChars);
                    $sanitizedSpan['chars'] = $this->validateDictionaryOutputSpanCharacterRange($sanitizedSpan, $sanitizedChars);
                }
                $span = $sanitizedSpan;
            }
            $sanitizedSpans[] = $span;
        }

        return $sanitizedSpans;
    }

    /**
     * pdftext inference derives parent span indexes from the first and last
     * kept character rows before dictionary_output scales and returns them.
     *
     * @param array<string, mixed> $span
     * @param list<array<string, mixed>> $chars
     * @return array<string, mixed>
     */
    private function inferDictionaryOutputSpanCharacterRange(array $span, array $chars): array
    {
        if ($chars === []) {
            return $span;
        }

        $first = $chars[0]['char_idx'] ?? null;
        $last = $chars[array_key_last($chars)]['char_idx'] ?? null;
        if (!array_key_exists('char_start_idx', $span) && is_int($first)) {
            $span['char_start_idx'] = $first;
        }
        if (!array_key_exists('char_end_idx', $span) && is_int($last)) {
            $span['char_end_idx'] = $last;
        }

        return $span;
    }

    /**
     * @param array<string, mixed> $span
     * @param list<array<string, mixed>> $chars
     * @return list<array<string, mixed>>
     */
    private function validateDictionaryOutputSpanCharacterRange(array $span, array $chars): array
    {
        if (!array_key_exists('char_start_idx', $span) || !array_key_exists('char_end_idx', $span)) {
            return $chars;
        }

        $startIndex = $this->dictionaryOutputIntegerMetadata($span['char_start_idx'], 'span.char_start_idx');
        $endIndex = $this->dictionaryOutputIntegerMetadata($span['char_end_idx'], 'span.char_end_idx');
        if ($startIndex > $endIndex) {
            throw new InvalidArgumentException('pdftext span.char_start_idx must be less than or equal to span.char_end_idx.');
        }

        foreach ($chars as $index => $char) {
            $charIndex = $this->dictionaryOutputIntegerMetadata($char['char_idx'] ?? null, "char {$index}.char_idx");
            if ($charIndex < $startIndex || $charIndex > $endIndex) {
                throw new InvalidArgumentException("pdftext char {$index}.char_idx must be within the parent span character range.");
            }
        }

        return $chars;
    }

    /**
     * @param mixed $chars
     * @param array{width: float, height: float}|null $bboxScale
     * @param array<string, mixed> $span
     * @return list<array<string, mixed>>
     */
    private function sanitizeDictionaryOutputChars(mixed $chars, ?array $bboxScale = null, array $span = []): array
    {
        if (!is_array($chars) || !array_is_list($chars)) {
            throw new InvalidArgumentException('pdftext span chars must be a list when keep_chars=true.');
        }

        $sanitizedChars = [];
        foreach ($chars as $index => $char) {
            if (!is_array($char)) {
                throw new InvalidArgumentException("pdftext char {$index} must be a dictionary when keep_chars=true.");
            }

            $sanitizedChar = [];
            foreach (['char', 'bbox', 'rotation', 'font', 'char_idx'] as $key) {
                if (array_key_exists($key, $char)) {
                    $sanitizedChar[$key] = $char[$key];
                }
            }

            $sanitizedChar = $this->validatedDictionaryOutputChar($sanitizedChar, $index, $bboxScale, $span);

            $sanitizedChars[] = $sanitizedChar;
        }

        return $sanitizedChars;
    }

    /**
     * @param array<string, mixed> $font
     * @return array<string, mixed>
     */
    private function sanitizeDictionaryOutputFont(array $font): array
    {
        $sanitizedFont = [];
        foreach (['name', 'flags', 'weight', 'size'] as $key) {
            if (array_key_exists($key, $font)) {
                $sanitizedFont[$key] = $font[$key];
            }
        }
        if (array_key_exists('flags', $sanitizedFont) && $sanitizedFont['flags'] !== null) {
            $sanitizedFont['flags'] = $this->dictionaryOutputUnsignedIntegerMetadata($sanitizedFont['flags'], 'font.flags');
        }

        return $sanitizedFont;
    }

    /**
     * @param array<string, mixed> $char
     * @param array{width: float, height: float}|null $bboxScale
     * @param array<string, mixed> $span
     * @return array{char: string, bbox: list<float>, rotation: int|float, font: array<string, mixed>, char_idx: int}
     */
    private function validatedDictionaryOutputChar(array $char, int $index, ?array $bboxScale, array $span = []): array
    {
        foreach (['char', 'bbox'] as $key) {
            if (!array_key_exists($key, $char)) {
                throw new InvalidArgumentException("pdftext char {$index}.{$key} is required when keep_chars=true.");
            }
        }

        if (!is_string($char['char'])) {
            throw new InvalidArgumentException("pdftext char {$index}.char must be a string when keep_chars=true.");
        }
        $this->assertUtf8String($char['char'], "char {$index}.char");

        $bbox = $this->unnormalizeDictionaryOutputBbox($char['bbox'], $bboxScale);
        $char['bbox'] = $this->dictionaryOutputRequiredBbox($bbox, "char {$index}.bbox");

        if (!array_key_exists('rotation', $char)) {
            $char['rotation'] = $span['rotation'] ?? 0;
        }
        $this->assertNumeric($char['rotation'], "char {$index}.rotation");
        $char['rotation'] = $this->dictionaryOutputNumberMetadata($char['rotation']);

        if (!array_key_exists('font', $char)) {
            $char['font'] = $span['font'] ?? null;
        }
        if (!is_array($char['font'])) {
            throw new InvalidArgumentException("pdftext char {$index}.font must be a dictionary when keep_chars=true.");
        }
        $this->assertDictionaryOutputFont($char['font'], "char {$index}.font");
        $char['font'] = $this->sanitizeDictionaryOutputFont($char['font']);

        if (!array_key_exists('char_idx', $char)) {
            if (!array_key_exists('char_start_idx', $span)) {
                throw new InvalidArgumentException("pdftext char {$index}.char_idx is required when keep_chars=true.");
            }
            $char['char_idx'] = $this->dictionaryOutputIntegerMetadata($span['char_start_idx'], 'span.char_start_idx') + $index;
        }
        $char['char_idx'] = $this->dictionaryOutputIntegerMetadata($char['char_idx'], "char {$index}.char_idx");
        if (
            array_key_exists('char_start_idx', $span)
            && array_key_exists('char_end_idx', $span)
            && ($char['char_idx'] < $span['char_start_idx'] || $char['char_idx'] > $span['char_end_idx'])
        ) {
            throw new InvalidArgumentException("pdftext char {$index}.char_idx must be within the parent span character range.");
        }

        return $char;
    }

    private function dictionaryOutputNumberMetadata(mixed $value): int|float
    {
        $floatValue = (float) $value;
        if (floor($floatValue) === $floatValue) {
            return (int) $value;
        }

        return $floatValue;
    }

    private function dictionaryOutputIntegerMetadata(mixed $value, string $field): int
    {
        $this->assertNumeric($value, $field);
        $floatValue = (float) $value;
        if (!is_finite($floatValue) || floor($floatValue) !== $floatValue) {
            throw new InvalidArgumentException("pdftext {$field} must be an integer.");
        }

        return (int) $value;
    }

    private function dictionaryOutputUnsignedIntegerMetadata(mixed $value, string $field): int
    {
        $integer = $this->dictionaryOutputIntegerMetadata($value, $field);
        if ($integer < 0) {
            throw new InvalidArgumentException("pdftext {$field} must be zero or greater.");
        }

        return $integer;
    }

    /**
     * @param mixed $value
     * @return list<float>
     */
    private function dictionaryOutputRequiredBbox(mixed $value, string $field): array
    {
        if (!is_array($value) || count($value) !== 4) {
            throw new InvalidArgumentException("pdftext {$field} must be a four-number bbox.");
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if ((!is_int($part) && !is_float($part)) || !is_finite((float) $part)) {
                throw new InvalidArgumentException("pdftext {$field} must be a four-number bbox.");
            }
            $bbox[] = (float) $part;
        }

        return $bbox;
    }

    private function assertNumeric(mixed $value, string $field): void
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidArgumentException("pdftext {$field} must be numeric.");
        }
    }

    /**
     * @param array<string, mixed> $font
     */
    private function assertDictionaryOutputFont(array $font, string $field): void
    {
        if (!array_key_exists('name', $font) || ($font['name'] !== null && !is_string($font['name']))) {
            throw new InvalidArgumentException("pdftext {$field}.name must be a string or null.");
        }
        if (!array_key_exists('flags', $font)) {
            throw new InvalidArgumentException("pdftext {$field}.flags is required.");
        }

        foreach (['weight', 'size'] as $fontKey) {
            $this->assertNumeric($font[$fontKey] ?? null, "{$field}.{$fontKey}");
        }

        if (array_key_exists('flags', $font) && $font['flags'] !== null) {
            $this->dictionaryOutputUnsignedIntegerMetadata($font['flags'], "{$field}.flags");
        }
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
        $widthHeightScale = null;
        if (
            isset($page['width'], $page['height'])
            && (is_int($page['width']) || is_float($page['width']))
            && (is_int($page['height']) || is_float($page['height']))
        ) {
            $width = (float) $page['width'];
            $height = (float) $page['height'];
            if (is_finite($width) && is_finite($height) && $width > 1.0 && $height > 1.0) {
                $widthHeightScale = ['width' => $width, 'height' => $height];
            }
        }

        $rotation = $page['rotation'] ?? null;
        if (
            ($rotation === 90 || $rotation === 90.0 || $rotation === 270 || $rotation === 270.0)
            && array_key_exists('bbox', $page)
        ) {
            $bboxScale = $this->dictionaryOutputPageBboxScale($page['bbox']);
            if ($bboxScale !== null) {
                return $bboxScale;
            }
        }

        return $widthHeightScale;
    }

    /**
     * pdftext swaps page width/height after span bbox processing on rotated
     * pages, while page bbox still carries the source page extent.
     *
     * @param mixed $value
     * @return array{width: float, height: float}|null
     */
    private function dictionaryOutputPageBboxScale(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if ((!is_int($part) && !is_float($part)) || !is_finite((float) $part)) {
                return null;
            }
            $bbox[] = (float) $part;
        }

        $width = abs($bbox[2] - $bbox[0]);
        $height = abs($bbox[3] - $bbox[1]);
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
            if ((!is_int($part) && !is_float($part)) || !is_finite((float) $part)) {
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
            if ($part < -0.5 || $part > 1.5) {
                return false;
            }
        }

        return abs($bbox[2] - $bbox[0]) <= 2.0 && abs($bbox[3] - $bbox[1]) <= 2.0;
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
            if ((!is_int($part) && !is_float($part)) || !is_finite((float) $part)) {
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
        $this->assertUtf8String($text, 'span.text');

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

    private function assertUtf8String(string $text, string $field): void
    {
        if (preg_match('//u', $text) !== 1) {
            throw new InvalidArgumentException("pdftext {$field} must be valid UTF-8.");
        }
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
