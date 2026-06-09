<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfTextDocumentExtractor
{
    private const SUPPLIED_DICTIONARY_PAGE_LIST_KEYS = ['dictionary_output', 'pdftext', 'pages', 'page_map', 'pageMap'];
    private const PDFTEXT_WORKER_PAGE_THRESHOLD = 10;

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
     *     metadata: array{pdf_toc: list<array<string, mixed>>, pages: int, source_pages: int, start_page: int, max_pages: int, pdftext_options: array<string, mixed>},
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

        if ($workers !== null && $workers < 0) {
            throw new InvalidArgumentException('pdftext workers must be zero or greater when supplied.');
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

        $metadata = [
            'pdf_toc' => array_values($toc),
            'pages' => count($pages),
            'source_pages' => $totalPages,
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
        ];

        $workerPlan = $this->pdftextWorkerPlan($workers, $pageCount);
        if ($workerPlan !== null) {
            $metadata['pdftext_worker_plan'] = $workerPlan;
        }

        return [
            'pages' => $pages,
            'toc' => array_values($toc),
            'metadata' => $metadata,
            'page_range' => $pageRange,
        ];
    }

    /**
     * pdftext clamps requested process workers to one worker per ten selected
     * pages, then uses the sequential path when that effective count is 0 or 1.
     *
     * @return array{requested_workers: int, selected_pages: int, worker_page_threshold: int, effective_workers: int, uses_multiprocessing: bool, sequential_fallback: bool}|null
     */
    private function pdftextWorkerPlan(?int $workers, int $selectedPageCount): ?array
    {
        if ($workers === null) {
            return null;
        }

        $selectedPageCount = max(0, $selectedPageCount);
        $effectiveWorkers = min($workers, intdiv($selectedPageCount, self::PDFTEXT_WORKER_PAGE_THRESHOLD));

        return [
            'requested_workers' => $workers,
            'selected_pages' => $selectedPageCount,
            'worker_page_threshold' => self::PDFTEXT_WORKER_PAGE_THRESHOLD,
            'effective_workers' => $effectiveWorkers,
            'uses_multiprocessing' => $effectiveWorkers > 1,
            'sequential_fallback' => $effectiveWorkers <= 1,
        ];
    }

    /**
     * Some callers cache pdftext.dictionary_output alongside adapter metadata.
     * The upstream markerPDF boundary only consumes the ordered page list, so
     * unwrap that list before slicing and keep envelope payloads out of pages.
     * Explicit dictionary_output wins first, nested explicit caches stay inside
     * that boundary, pdftext cache envelopes are next, and legacy adapter
     * pages/page maps stay a fallback.
     *
     * @param array<mixed> $pdftextPages
     * @return list<mixed>
     */
    private function normalizeSuppliedDictionaryPageList(array $pdftextPages): array
    {
        $explicitPageList = $this->explicitSuppliedDictionaryPageList($pdftextPages);
        if ($explicitPageList !== null) {
            return $explicitPageList;
        }

        if (array_key_exists('blocks', $pdftextPages)) {
            return [$pdftextPages];
        }

        foreach (self::SUPPLIED_DICTIONARY_PAGE_LIST_KEYS as $pageListKey) {
            if (!array_key_exists($pageListKey, $pdftextPages)) {
                continue;
            }

            $pages = $this->normalizeSuppliedDictionaryEnvelopeValue($pdftextPages[$pageListKey]);
            if (!is_array($pages)) {
                continue;
            }

            if (array_key_exists('blocks', $pages)) {
                return [$pages];
            }

            if (!array_key_exists('blocks', $pages)) {
                $nestedPages = $this->nestedSuppliedDictionaryPageMap($pages);
                if ($nestedPages !== null) {
                    return $nestedPages;
                }
            }

            return $this->orderedSuppliedDictionaryPageList($pages);
        }

        return $this->orderedSuppliedDictionaryPageList($pdftextPages);
    }

    /**
     * pdftext.dictionary_output returns page dictionaries in document order.
     * Native adapter caches sometimes preserve that output as a JSON object map
     * keyed by source page/index; sort only those page-shaped numeric maps before
     * applying upstream-style start_page/max_pages slicing.
     *
     * @param array<mixed> $pages
     * @return list<mixed>
     */
    private function orderedSuppliedDictionaryPageList(array $pages): array
    {
        if (array_key_exists('blocks', $pages)) {
            return [$pages];
        }

        if (array_is_list($pages)) {
            return $this->suppliedDictionaryPageListFallback($pages);
        }

        $keyedPages = [];
        $seenPageKeys = [];
        foreach ($pages as $key => $page) {
            $page = $this->unwrapSuppliedDictionaryPageEntry($page);
            $pageKey = $this->integerArrayKey($key);
            if ($pageKey === null) {
                if ($this->isSuppliedDictionaryAdapterMetadataKey($key)) {
                    continue;
                }

                if (is_array($page) && array_key_exists('blocks', $page)) {
                    return $this->suppliedDictionaryPageListFallback($pages);
                }

                continue;
            }

            if (!is_array($page) || !array_key_exists('blocks', $page)) {
                return $this->suppliedDictionaryPageListFallback($pages);
            }
            if ($pageKey < 0) {
                throw new InvalidArgumentException('Supplied pdftext page map keys must be zero or greater.');
            }

            $pageKeyFingerprint = (string) $pageKey;
            if (isset($seenPageKeys[$pageKeyFingerprint])) {
                throw new InvalidArgumentException('Supplied pdftext page map contains duplicate normalized page keys.');
            }
            $seenPageKeys[$pageKeyFingerprint] = true;

            $keyedPages[] = [
                'key' => $pageKey,
                'index' => count($keyedPages),
                'page' => $page,
            ];
        }

        if ($keyedPages === []) {
            return $this->suppliedDictionaryPageListFallback($pages);
        }

        usort(
            $keyedPages,
            static fn (array $left, array $right): int => ($left['key'] <=> $right['key'])
                ?: ($left['index'] <=> $right['index'])
        );

        return array_map(static fn (array $entry): mixed => $entry['page'], $keyedPages);
    }

    private function isSuppliedDictionaryAdapterMetadataKey(int|string $key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        return in_array($key, [
            'metadata',
            'page_metadata',
            'page_meta',
            'source_metadata',
            'pdftext_metadata',
            'adapter_metadata',
            'raw_payload',
            'raw_adapter_payload',
            'raw_pdftext_payload',
        ], true);
    }

    /**
     * @param array<mixed> $pages
     * @return list<mixed>
     */
    private function suppliedDictionaryPageListFallback(array $pages): array
    {
        return array_map(
            fn (mixed $page): mixed => $this->unwrapSuppliedDictionaryPageEntry($page),
            array_values($pages)
        );
    }

    /**
     * Adapter caches sometimes preserve each selected pdftext page as a
     * one-page dictionary_output/pdftext/pages envelope. Upstream Marker sees
     * only the page dictionaries returned by pdftext, so unwrap exactly one
     * page-shaped entry and leave ambiguous wrappers to normal validation.
     */
    private function unwrapSuppliedDictionaryPageEntry(mixed $entry): mixed
    {
        $entry = $this->normalizeSuppliedDictionaryPageEntryValue($entry);
        if (!is_array($entry)) {
            return $entry;
        }

        $explicitPageList = $this->explicitSuppliedDictionaryPageList($entry);
        if ($explicitPageList !== null) {
            if (count($explicitPageList) === 1 && is_array($explicitPageList[0]) && array_key_exists('blocks', $explicitPageList[0])) {
                return $explicitPageList[0];
            }

            throw new InvalidArgumentException('Supplied pdftext page entry envelopes must contain exactly one page dictionary.');
        }

        if (array_key_exists('blocks', $entry)) {
            return $entry;
        }

        foreach (self::SUPPLIED_DICTIONARY_PAGE_LIST_KEYS as $pageListKey) {
            if (!array_key_exists($pageListKey, $entry)) {
                continue;
            }

            $candidate = $this->normalizeSuppliedDictionaryValue($entry[$pageListKey]);
            if (!is_array($candidate)) {
                continue;
            }
            if (array_key_exists('blocks', $candidate)) {
                return $candidate;
            }

            $candidatePageList = $this->nestedSuppliedDictionaryPageMap($candidate);
            if ($candidatePageList !== null) {
                if (count($candidatePageList) === 1 && is_array($candidatePageList[0]) && array_key_exists('blocks', $candidatePageList[0])) {
                    return $candidatePageList[0];
                }
            }

            $pageList = $this->orderedSuppliedDictionaryPageList($candidate);
            if (count($pageList) === 1 && is_array($pageList[0]) && array_key_exists('blocks', $pageList[0])) {
                return $pageList[0];
            }
        }

        return $entry;
    }

    /**
     * JSONL-style adapter caches can store each pdftext dictionary page as a
     * raw JSON object string. Decode only at the page-entry boundary so visible
     * span text and arbitrary string payloads remain ordinary strings.
     */
    private function normalizeSuppliedDictionaryPageEntryValue(mixed $entry): mixed
    {
        if (is_string($entry)) {
            $decoded = $this->decodeSuppliedDictionaryJsonEnvelope($entry);
            if ($decoded !== null) {
                return $this->normalizeSuppliedDictionaryValue($decoded);
            }
        }

        return $this->normalizeSuppliedDictionaryValue($entry);
    }

    /**
     * Explicit pdftext cache envelopes should win before stale adapter wrapper
     * pages. Limit this precedence to page-shaped payloads so arbitrary wrapper
     * metadata named `pdftext` does not mask a valid direct page dictionary.
     *
     * @param array<mixed> $entry
     * @return list<mixed>|null
     */
    private function explicitSuppliedDictionaryPageList(array $entry): ?array
    {
        foreach (['dictionary_output', 'pdftext'] as $pageListKey) {
            if (!array_key_exists($pageListKey, $entry)) {
                continue;
            }

            $pageList = $this->pageListFromExplicitDictionaryEnvelope($entry[$pageListKey]);
            if ($pageList !== null) {
                return $pageList;
            }
            if (!array_key_exists('blocks', $entry)) {
                $envelopeName = $pageListKey === 'dictionary_output' ? 'dictionary_output' : 'cache';
                throw new InvalidArgumentException("Supplied pdftext {$envelopeName} envelope must contain a page dictionary or page list.");
            }
        }

        return null;
    }

    /**
     * @return list<mixed>|null
     */
    private function pageListFromExplicitDictionaryEnvelope(mixed $value): ?array
    {
        $value = $this->normalizeSuppliedDictionaryEnvelopeValue($value);
        if (!is_array($value)) {
            return null;
        }

        if ($value === []) {
            return [];
        }

        if (array_key_exists('blocks', $value)) {
            return [$value];
        }

        foreach (['dictionary_output', 'pdftext'] as $nestedPageListKey) {
            if (!array_key_exists($nestedPageListKey, $value)) {
                continue;
            }

            $nestedPageList = $this->pageListFromExplicitDictionaryEnvelope($value[$nestedPageListKey]);
            if ($nestedPageList !== null) {
                return $nestedPageList;
            }

            return null;
        }

        foreach (['pages', 'page_map', 'pageMap'] as $pageListKey) {
            if (!array_key_exists($pageListKey, $value)) {
                continue;
            }

            $pages = $this->normalizeSuppliedDictionaryEnvelopeValue($value[$pageListKey]);
            if (!is_array($pages)) {
                return null;
            }

            if ($pages === []) {
                return [];
            }

            $pageList = $this->orderedSuppliedDictionaryPageList($pages);
            if ($this->allSuppliedDictionaryPages($pageList)) {
                return $pageList;
            }

            return null;
        }

        $pageList = $this->orderedSuppliedDictionaryPageList($value);

        return $this->allSuppliedDictionaryPages($pageList) ? $pageList : null;
    }

    /**
     * @param array<mixed> $value
     * @return list<mixed>|null
     */
    private function nestedSuppliedDictionaryPageMap(array $value): ?array
    {
        foreach (['pages', 'page_map', 'pageMap'] as $pageListKey) {
            if (!array_key_exists($pageListKey, $value)) {
                continue;
            }

            $pages = $this->normalizeSuppliedDictionaryEnvelopeValue($value[$pageListKey]);
            if (is_array($pages)) {
                if ($pages === []) {
                    return [];
                }

                return $this->orderedSuppliedDictionaryPageList($pages);
            }
        }

        return null;
    }

    /**
     * pdftext's CLI `--json` path emits the same page-list structure as
     * dictionary_output. Native import adapters may cache that payload as a raw
     * JSON string under an explicit pdftext/dictionary_output envelope, but
     * arbitrary span text must remain string data.
     */
    private function normalizeSuppliedDictionaryEnvelopeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = $this->decodeSuppliedDictionaryJsonEnvelope($value);
            if ($decoded !== null) {
                return $this->normalizeSuppliedDictionaryValue($decoded);
            }
        }

        return $this->normalizeSuppliedDictionaryValue($value);
    }

    private function decodeSuppliedDictionaryJsonEnvelope(string $value): mixed
    {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, "\xEF\xBB\xBF")) {
            $trimmed = trim(substr($trimmed, 3));
        }
        if ($trimmed === '' || !in_array($trimmed[0], ['[', '{'], true)) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded) && !$decoded instanceof \stdClass) {
            return null;
        }

        $this->assertSuppliedDictionaryJsonEnvelopeHasUniqueObjectKeys($trimmed);

        return $decoded;
    }

    /**
     * json_decode keeps the last duplicate object member. At the supplied
     * pdftext cache boundary that can silently replace an earlier page-map row,
     * so detect duplicate decoded keys before normalizing into PHP arrays.
     */
    private function assertSuppliedDictionaryJsonEnvelopeHasUniqueObjectKeys(string $json): void
    {
        $offset = $this->skipJsonWhitespace($json, 0);
        $this->scanSuppliedDictionaryJsonValue($json, $offset);
    }

    private function scanSuppliedDictionaryJsonValue(string $json, int $offset): int
    {
        $offset = $this->skipJsonWhitespace($json, $offset);
        if ($offset >= strlen($json)) {
            return $offset;
        }

        return match ($json[$offset]) {
            '{' => $this->scanSuppliedDictionaryJsonObject($json, $offset),
            '[' => $this->scanSuppliedDictionaryJsonArray($json, $offset),
            '"' => $this->readSuppliedDictionaryJsonString($json, $offset)['next'],
            default => $this->skipSuppliedDictionaryJsonPrimitive($json, $offset),
        };
    }

    private function scanSuppliedDictionaryJsonObject(string $json, int $offset): int
    {
        $length = strlen($json);
        $offset++;
        $seenKeys = [];

        while ($offset < $length) {
            $offset = $this->skipJsonWhitespace($json, $offset);
            if ($offset < $length && $json[$offset] === '}') {
                return $offset + 1;
            }

            $key = $this->readSuppliedDictionaryJsonString($json, $offset);
            $fingerprint = 'key:' . $key['value'];
            if (isset($seenKeys[$fingerprint])) {
                throw new InvalidArgumentException('Supplied pdftext JSON envelope contains duplicate object keys.');
            }
            $seenKeys[$fingerprint] = true;

            $offset = $this->skipJsonWhitespace($json, $key['next']);
            if ($offset < $length && $json[$offset] === ':') {
                $offset++;
            }

            $offset = $this->scanSuppliedDictionaryJsonValue($json, $offset);
            $offset = $this->skipJsonWhitespace($json, $offset);
            if ($offset < $length && $json[$offset] === ',') {
                $offset++;
                continue;
            }
            if ($offset < $length && $json[$offset] === '}') {
                return $offset + 1;
            }
        }

        return $offset;
    }

    private function scanSuppliedDictionaryJsonArray(string $json, int $offset): int
    {
        $length = strlen($json);
        $offset++;

        while ($offset < $length) {
            $offset = $this->skipJsonWhitespace($json, $offset);
            if ($offset < $length && $json[$offset] === ']') {
                return $offset + 1;
            }

            $offset = $this->scanSuppliedDictionaryJsonValue($json, $offset);
            $offset = $this->skipJsonWhitespace($json, $offset);
            if ($offset < $length && $json[$offset] === ',') {
                $offset++;
                continue;
            }
            if ($offset < $length && $json[$offset] === ']') {
                return $offset + 1;
            }
        }

        return $offset;
    }

    /**
     * @return array{value: string, next: int}
     */
    private function readSuppliedDictionaryJsonString(string $json, int $offset): array
    {
        $length = strlen($json);
        $index = $offset + 1;
        $escaped = false;

        while ($index < $length) {
            $char = $json[$index];
            if ($escaped) {
                $escaped = false;
                $index++;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                $index++;
                continue;
            }
            if ($char === '"') {
                $token = substr($json, $offset, $index - $offset + 1);
                $value = json_decode($token, false, 16, JSON_THROW_ON_ERROR);
                return [
                    'value' => is_string($value) ? $value : '',
                    'next' => $index + 1,
                ];
            }
            $index++;
        }

        return ['value' => '', 'next' => $index];
    }

    private function skipSuppliedDictionaryJsonPrimitive(string $json, int $offset): int
    {
        $length = strlen($json);
        while ($offset < $length && !in_array($json[$offset], [',', ']', '}'], true)) {
            $offset++;
        }

        return $offset;
    }

    private function skipJsonWhitespace(string $json, int $offset): int
    {
        $length = strlen($json);
        while ($offset < $length && str_contains(" \n\r\t", $json[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @param list<mixed> $pageList
     */
    private function allSuppliedDictionaryPages(array $pageList): bool
    {
        if ($pageList === []) {
            return false;
        }

        foreach ($pageList as $page) {
            if (!is_array($page) || !array_key_exists('blocks', $page)) {
                return false;
            }
        }

        return true;
    }

    private function integerArrayKey(int|string $key): ?int
    {
        if (is_int($key)) {
            return $key;
        }

        $trimmed = trim($key);
        if (preg_match('/^([+-]?)(\d+)(?:\.0+)?$/', $trimmed, $match) !== 1) {
            return null;
        }

        $number = ltrim($match[2], '0');
        $number = $number === '' ? '0' : $number;
        $maxInteger = (string) PHP_INT_MAX;
        if (strlen($number) > strlen($maxInteger) || (strlen($number) === strlen($maxInteger) && strcmp($number, $maxInteger) > 0)) {
            throw new InvalidArgumentException('Supplied pdftext page map keys must fit in a PHP integer.');
        }

        $integer = (int) $number;

        return $match[1] === '-' ? -$integer : $integer;
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
     * @param list<array<string, mixed>|\stdClass>|array{pages?: array<mixed>, metadata?: array<string, mixed>} $pdftextPages
     * @param list<array<string, mixed>|\stdClass> $orderResults
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
        $sourcePageCount = (int) ($document['metadata']['source_pages'] ?? count($document['pages']));
        $selectedPageNumbers = $this->artifactSelector->pageNumbersFromPages($document['pages']);
        $orderImages = $this->selectSuppliedPageArtifacts(
            $orderImages,
            $sourcePageCount,
            $document['page_range'],
            count($document['pages']),
            $selectedPageNumbers
        );
        $orderResults = $this->selectSuppliedPageArtifacts(
            $orderResults,
            $sourcePageCount,
            $document['page_range'],
            count($document['pages']),
            $selectedPageNumbers
        );

        $ordered = $orderer->runWithSuppliedOrder(
            $orderImages,
            $document['pages'],
            $orderResults,
            $batchMultiplier,
            $document['page_range']
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
                        $sanitizedSpan[$indexKey] = $this->dictionaryOutputUnsignedIntegerMetadata($sanitizedSpan[$indexKey], "span.{$indexKey}");
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
                if (array_key_exists('url', $sanitizedSpan) && is_string($sanitizedSpan['url'])) {
                    $this->assertUtf8String($sanitizedSpan['url'], 'span.url');
                }
                if (array_key_exists('text', $span) && is_string($span['text'])) {
                    $sanitizedSpan['text'] = $this->normalizeDictionaryOutputText($span['text']);
                    if ($sanitizedSpan['text'] === '') {
                        continue;
                    }
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

        $startIndex = $this->dictionaryOutputUnsignedIntegerMetadata($span['char_start_idx'], 'span.char_start_idx');
        $endIndex = $this->dictionaryOutputUnsignedIntegerMetadata($span['char_end_idx'], 'span.char_end_idx');
        if ($startIndex > $endIndex) {
            throw new InvalidArgumentException('pdftext span.char_start_idx must be less than or equal to span.char_end_idx.');
        }

        foreach ($chars as $index => $char) {
            $charIndex = $this->dictionaryOutputUnsignedIntegerMetadata($char['char_idx'] ?? null, "char {$index}.char_idx");
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
        if (array_key_exists('name', $sanitizedFont) && is_string($sanitizedFont['name'])) {
            $this->assertUtf8String($sanitizedFont['name'], 'font.name');
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
        $this->assertSingleUtf8Codepoint($char['char'], "char {$index}.char");

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
            $char['char_idx'] = $this->dictionaryOutputUnsignedIntegerMetadata($span['char_start_idx'], 'span.char_start_idx') + $index;
        }
        $char['char_idx'] = $this->dictionaryOutputUnsignedIntegerMetadata($char['char_idx'], "char {$index}.char_idx");
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

        $bboxScale = array_key_exists('bbox', $page)
            ? $this->dictionaryOutputPageBboxScale($page['bbox'])
            : null;

        $rotation = $page['rotation'] ?? null;
        if (
            ($rotation === 90 || $rotation === 90.0 || $rotation === 270 || $rotation === 270.0)
            && $bboxScale !== null
        ) {
            return $bboxScale;
        }

        return $widthHeightScale ?? $bboxScale;
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
            $sortKey = $tolerance > 0.0
                ? round($bbox[1] / $tolerance, 0, PHP_ROUND_HALF_EVEN) * $tolerance
                : $bbox[1];
            if (abs($sortKey) < 0.000000000001) {
                $sortKey = 0.0;
            }
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

    private function assertSingleUtf8Codepoint(string $text, string $field): void
    {
        $this->assertUtf8String($text, $field);
        $codepointCount = preg_match_all('/./us', $text);
        if ($codepointCount !== 1) {
            throw new InvalidArgumentException("pdftext {$field} must be a single Unicode character.");
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
