<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfPageArtifactSelector
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
    private const ARTIFACT_PAGE_LIST_ENVELOPES = ['pages', 'dictionary_output', 'pdftext', 'page_map', 'pageMap'];
    private const ENVELOPE_PAGE_KEY_MARKER = '__markerpdf_envelope_page_key_marker';
    private const MISSING_PAGE_ARTIFACT = '__markerpdf_missing_page_artifact';
    private const PAGE_MARKER_METADATA_WRAPPERS = [
        'metadata',
        'page_metadata',
        'page_meta',
        'page_info',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'pdftext_source',
    ];
    private const PAGE_MARKER_WRAPPERS = [
        'metadata',
        'page_metadata',
        'page_meta',
        'page_info',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'layout',
        'layout_result',
        'order',
        'order_result',
        'prediction',
        'result',
        'model_output',
        'output',
        'source',
        'pdftext',
    ];
    private const PAGE_MARKER_FIELD_GROUPS = [
        ['page_index', 'pageIndex', 'page_idx', 'doc_page_index', 'docPageIndex', 'doc_page_idx', 'document_page_index', 'documentPageIndex', 'document_page_idx', 'source_page_index', 'sourcePageIndex', 'source_page_idx', 'page_range', 'pageRange', 'source_page_range', 'sourcePageRange', 'document_page_range', 'documentPageRange', 'page_indices', 'pageIndices', 'source_page_indices', 'sourcePageIndices', 'document_page_indices', 'documentPageIndices'],
        ['selected_page_index', 'selectedPageIndex', 'selected_page_idx', 'trimmed_page_index', 'trimmedPageIndex', 'trimmed_page_idx', 'relative_page_index', 'relativePageIndex', 'relative_page_idx', 'selected_page_range', 'selectedPageRange', 'trimmed_page_range', 'trimmedPageRange', 'relative_page_range', 'relativePageRange', 'selected_page_indices', 'selectedPageIndices', 'trimmed_page_indices', 'trimmedPageIndices', 'relative_page_indices', 'relativePageIndices'],
        ['pnum', 'pnums', 'page', 'page_id', 'pageId', 'page_ids', 'pageIds', 'pdftext_page', 'pdftextPage', 'pdftext_pages', 'pdftextPages', 'pdftext_page_id', 'pdftextPageId', 'pdftext_page_ids', 'pdftextPageIds', 'source_page', 'sourcePage', 'source_pages', 'sourcePages', 'source_page_id', 'sourcePageId', 'source_page_ids', 'sourcePageIds', 'document_page', 'documentPage', 'document_pages', 'documentPages', 'document_page_id', 'documentPageId', 'document_page_ids', 'documentPageIds', 'doc_page_id', 'docPageId', 'doc_page_ids', 'docPageIds'],
        ['page_number', 'pageNumber', 'page_numbers', 'pageNumbers', 'page_num', 'pageNum', 'page_nums', 'pageNums', 'doc_page_number', 'docPageNumber', 'doc_page_numbers', 'docPageNumbers', 'document_page_number', 'documentPageNumber', 'document_page_numbers', 'documentPageNumbers', 'pdftext_page_number', 'pdftextPageNumber', 'pdftext_page_numbers', 'pdftextPageNumbers', 'source_page_number', 'sourcePageNumber', 'source_page_numbers', 'sourcePageNumbers'],
        ['selected_page_number', 'selectedPageNumber', 'selected_page_numbers', 'selectedPageNumbers', 'trimmed_page_number', 'trimmedPageNumber', 'trimmed_page_numbers', 'trimmedPageNumbers', 'relative_page_number', 'relativePageNumber', 'relative_page_numbers', 'relativePageNumbers', 'selected_page_num', 'selectedPageNum', 'selected_page_nums', 'selectedPageNums', 'trimmed_page_num', 'trimmedPageNum', 'trimmed_page_nums', 'trimmedPageNums', 'relative_page_num', 'relativePageNum', 'relative_page_nums', 'relativePageNums'],
    ];

    /**
     * @param list<mixed> $artifacts
     * @param list<int> $pageRange
     * @param list<int|null> $selectedPageNumbers
     * @return list<mixed>
     */
    public function select(
        array $artifacts,
        int $sourcePageCount,
        array $pageRange,
        int $selectedPageCount,
        array $selectedPageNumbers = []
    ): array {
        $artifacts = self::normalizeSuppliedArtifacts($artifacts);
        if ($artifacts === [] || $pageRange === [] || $selectedPageCount === 0) {
            return $artifacts;
        }

        $keyed = $this->selectByPageMarkers($artifacts, $pageRange, $selectedPageNumbers);
        if ($keyed !== null) {
            return $keyed;
        }

        if (count($artifacts) === $selectedPageCount) {
            return $artifacts;
        }

        if (count($artifacts) === $sourcePageCount) {
            return array_slice($artifacts, $pageRange[0], $selectedPageCount);
        }

        if (count($artifacts) > $selectedPageCount) {
            // Unmarked positional lists are only trusted when they match the selected range or full source.
            return [];
        }

        return $artifacts;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<int|null>
     */
    public function pageNumbersFromPages(array $pages): array
    {
        $numbers = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                $numbers[] = null;
                continue;
            }

            $numbers[] = $this->integerValue($page['pnum'] ?? $page['page'] ?? null);
        }

        return $numbers;
    }

    public static function isMissingPageArtifact(mixed $artifact): bool
    {
        return is_array($artifact) && ($artifact[self::MISSING_PAGE_ARTIFACT] ?? false) === true;
    }

    /**
     * @param list<mixed> $artifacts
     */
    public static function countPresentArtifacts(array $artifacts): int
    {
        return count(array_filter(
            $artifacts,
            static fn (mixed $artifact): bool => !self::isMissingPageArtifact($artifact)
        ));
    }

    /**
     * @param list<mixed> $artifacts
     * @param list<int> $pageRange
     * @param list<int|null> $selectedPageNumbers
     * @return list<mixed>|null
     */
    private function selectByPageMarkers(array $artifacts, array $pageRange, array $selectedPageNumbers): ?array
    {
        $hasMarkers = false;
        $selected = [];
        $matched = 0;
        $usedArtifactIndexes = [];
        foreach (array_values($pageRange) as $selectedIndex => $sourceIndex) {
            $pageNumber = $selectedPageNumbers[$selectedIndex] ?? null;
            $artifact = null;
            $bestScore = null;
            $bestArtifactIndex = null;
            $hasTie = false;
            foreach ($artifacts as $artifactIndex => $candidate) {
                if (isset($usedArtifactIndexes[$artifactIndex])) {
                    continue;
                }
                if (!is_array($candidate)) {
                    continue;
                }

                $markers = $this->pageMarkers($candidate);
                if ($markers === []) {
                    continue;
                }

                $hasMarkers = true;
                $score = $this->pageMarkerMatchScore($markers, $sourceIndex, $pageNumber, $selectedIndex);
                if ($score !== null && ($bestScore === null || $score > $bestScore)) {
                    $artifact = $candidate;
                    $bestScore = $score;
                    $bestArtifactIndex = $artifactIndex;
                    $hasTie = false;
                    continue;
                }
                if ($score !== null && $score === $bestScore) {
                    $hasTie = true;
                }
            }

            if ($artifact !== null && !$hasTie) {
                $selected[] = $artifact;
                if ($bestArtifactIndex !== null) {
                    $usedArtifactIndexes[$bestArtifactIndex] = true;
                }
                $matched++;
                continue;
            }

            $selected[] = [self::MISSING_PAGE_ARTIFACT => true];
        }

        if (!$hasMarkers) {
            return null;
        }

        return $matched > 0 ? $selected : [];
    }

    /**
     * Supplied marker/pdftext adapters are commonly JSON-decoded without the
     * associative-array flag. Normalize plain JSON objects before page-marker
     * matching while leaving non-data objects and scalar payloads untouched.
     * Some caches wrap selected layout/order artifacts in the same pdftext,
     * dictionary_output, or pages envelope used by pdftext page dictionaries;
     * unwrap those page-list envelopes before marker selection so stale cover
     * rows do not remain a single positional artifact.
     *
     * @param list<mixed> $artifacts
     * @return list<mixed>
     */
    public static function normalizeSuppliedArtifacts(array $artifacts): array
    {
        $artifacts = self::normalizeSuppliedArtifactValue($artifacts);
        $artifacts = self::directKeyedArtifactMap($artifacts)
            ?? self::artifactListFromEnvelope($artifacts)
            ?? $artifacts;

        $normalized = [];
        foreach (array_values($artifacts) as $artifact) {
            $artifact = self::normalizeSuppliedArtifactListEntryValue($artifact);
            if (is_array($artifact)) {
                $artifact = self::normalizeSuppliedArtifactValue($artifact);
                $unwrapped = self::directKeyedArtifactMap($artifact)
                    ?? self::artifactListFromEnvelope($artifact);
                if ($unwrapped !== null) {
                    foreach (array_values($unwrapped) as $unwrappedArtifact) {
                        $normalized[] = self::normalizeSuppliedArtifactValue($unwrappedArtifact);
                    }

                    continue;
                }
            }

            $normalized[] = self::normalizeSuppliedArtifactValue($artifact);
        }

        return $normalized;
    }

    /**
     * Some native adapters persist layout/order/image sidecars as JSONL-style
     * records, where each selected artifact list entry is a JSON object. Decode
     * only entries that are artifact-shaped so arbitrary string payloads remain
     * inert metadata.
     */
    private static function normalizeSuppliedArtifactListEntryValue(mixed $artifact): mixed
    {
        if (!is_string($artifact)) {
            return $artifact;
        }

        $decoded = self::decodeSuppliedArtifactJsonEnvelope($artifact);
        if ($decoded === null) {
            return $artifact;
        }

        $decoded = self::normalizeSuppliedArtifactValue($decoded);
        if (!is_array($decoded) || !self::jsonDecodedValueLooksLikeSuppliedArtifact($decoded)) {
            return $artifact;
        }

        return $decoded;
    }

    /**
     * Source-page keyed caches can store each layout/order/image artifact as a
     * JSON object string. Decode before map selection so the numeric key stays
     * attached as page identity instead of being lost during array_values().
     */
    private static function normalizeSuppliedArtifactMapCandidateValue(mixed $artifact): mixed
    {
        if (!is_string($artifact)) {
            return self::normalizeSuppliedArtifactValue($artifact);
        }

        $decoded = self::decodeSuppliedArtifactJsonEnvelope($artifact);
        if ($decoded === null) {
            return $artifact;
        }

        $decoded = self::normalizeSuppliedArtifactValue($decoded);
        if (!is_array($decoded) || !self::jsonDecodedValueLooksLikeSuppliedArtifact($decoded)) {
            return $artifact;
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $value
     */
    private static function jsonDecodedValueLooksLikeSuppliedArtifact(array $value, int $depth = 0): bool
    {
        if (self::hasJsonDirectArtifactPayload($value)) {
            return true;
        }

        if ($depth >= 3) {
            return false;
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                if (is_array($item) && self::jsonDecodedValueLooksLikeSuppliedArtifact($item, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        foreach (self::ARTIFACT_PAGE_LIST_ENVELOPES as $envelopeKey) {
            $nested = $value[$envelopeKey] ?? null;
            if (is_array($nested) && self::jsonDecodedValueLooksLikeSuppliedArtifact($nested, $depth + 1)) {
                return true;
            }
        }

        foreach ($value as $key => $candidate) {
            if (
                is_array($candidate)
                && self::jsonDecodedValueLooksLikeSuppliedArtifact($candidate, $depth + 1)
                && self::integerArrayKey($key) !== null
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * `blocks` and page-level `bbox` are pdftext page-copy markers, not model
     * geometry by themselves. JSON list-entry decoding requires model/image
     * payload keys so page text caches cannot become empty assigned artifacts.
     *
     * @param array<mixed> $value
     */
    private static function hasJsonDirectArtifactPayload(array $value): bool
    {
        return self::hasSelectableArtifactPayload($value);
    }

    /**
     * Adapter caches can serialize supplied images/layout/order predictions as
     * a source-page keyed JSON object instead of a list. Preserve the object key
     * as selector-only page identity before selected-page alignment.
     *
     * @param array<mixed> $value
     * @return list<mixed>|null
     */
    private static function directKeyedArtifactMap(array $value): ?array
    {
        if (array_is_list($value) || self::hasDirectArtifactPayload($value)) {
            return null;
        }

        $artifacts = [];
        $sawRawPdftextPageCopy = false;
        $seenPageKeys = [];
        foreach ($value as $key => $candidate) {
            $candidate = self::normalizeSuppliedArtifactMapCandidateValue($candidate);
            if (!is_array($candidate) || array_is_list($candidate)) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    continue;
                }

                return null;
            }

            $isRawPdftextPageCopy = self::isRawPdftextPageCopy($candidate);
            $hasPayload = self::hasSelectableArtifactPayload($candidate);
            if (!$isRawPdftextPageCopy && !$hasPayload) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    continue;
                }

                return null;
            }

            $pageKey = self::integerArrayKey($key);
            if ($pageKey !== null && $isRawPdftextPageCopy) {
                $sawRawPdftextPageCopy = true;
                continue;
            }
            if ($pageKey === null || !$hasPayload) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    continue;
                }

                return null;
            }

            self::rememberArtifactPageKey($seenPageKeys, $pageKey);
            $artifacts[] = self::withEnvelopePageKey($candidate, $pageKey);
        }

        return $artifacts !== [] ? $artifacts : ($sawRawPdftextPageCopy ? [] : null);
    }

    public static function normalizeSuppliedArtifactValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $nestedValue) {
            $value[$key] = self::normalizeSuppliedArtifactValue($nestedValue);
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     * @return list<mixed>|null
     */
    private static function artifactListFromEnvelope(array $value): ?array
    {
        if (self::hasEnvelopeBlockingDirectArtifactPayload($value)) {
            return null;
        }

        foreach (self::ARTIFACT_PAGE_LIST_ENVELOPES as $pageListKey) {
            if (!array_key_exists($pageListKey, $value)) {
                continue;
            }

            $artifacts = self::normalizeSuppliedArtifactEnvelopeValue($value[$pageListKey]);
            if (!is_array($artifacts)) {
                continue;
            }

            if (!self::hasDirectArtifactPayload($artifacts) && array_key_exists('pages', $artifacts)) {
                $nested = self::normalizeSuppliedArtifactValue($artifacts['pages']);
                if (is_array($nested)) {
                    if (self::hasDirectArtifactPayload($nested)) {
                        return null;
                    }

                    $singleKeyedPayload = self::singleKeyedDirectArtifactPayload($nested);
                    if ($singleKeyedPayload !== null) {
                        return [self::withEnvelopePageMarkers($singleKeyedPayload, $value)];
                    }

                    $keyedArtifacts = self::keyedEnvelopeArtifacts($nested);
                    if ($keyedArtifacts !== null) {
                        return $keyedArtifacts;
                    }

                    return array_values($nested);
                }
            }

            if (self::hasDirectArtifactPayload($artifacts)) {
                return null;
            }
            $singleKeyedPayload = self::singleKeyedDirectArtifactPayload($artifacts);
            if ($singleKeyedPayload !== null) {
                return [self::withEnvelopePageMarkers($singleKeyedPayload, $value)];
            }
            $keyedArtifacts = self::keyedEnvelopeArtifacts($artifacts);
            if ($keyedArtifacts !== null) {
                return $keyedArtifacts;
            }

            return array_values($artifacts);
        }

        return null;
    }

    /**
     * Supplied artifact caches can mirror pdftext's CLI `--json` envelope:
     * the page-list key is present, but its value is a raw JSON string. Decode
     * only at explicit artifact-envelope boundaries so scalar payload text is
     * not interpreted as trusted layout/order geometry.
     */
    private static function normalizeSuppliedArtifactEnvelopeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = self::decodeSuppliedArtifactJsonEnvelope($value);
            if ($decoded !== null) {
                return self::normalizeSuppliedArtifactValue($decoded);
            }
        }

        return self::normalizeSuppliedArtifactValue($value);
    }

    private static function decodeSuppliedArtifactJsonEnvelope(string $value): mixed
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

        return is_array($decoded) || $decoded instanceof \stdClass ? $decoded : null;
    }

    /**
     * @param array<mixed> $value
     */
    private static function withEnvelopePageMarkers(array $payload, array $value): array
    {
        foreach (self::PAGE_MARKER_FIELD_GROUPS as $fields) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $value) || array_key_exists($field, $payload)) {
                    continue;
                }

                $payload[$field] = $value[$field];
            }
        }

        return $payload;
    }

    /**
     * @param array<mixed> $value
     */
    private static function hasDirectArtifactPayload(array $value): bool
    {
        if (self::hasSelectableArtifactPayload($value)) {
            return true;
        }

        foreach (['blocks', 'bbox'] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `blocks` and a page-level `bbox` are pdftext page-copy fields. They are
     * valid wrapper geometry, but they are not selectable layout/order/image
     * artifacts by themselves.
     *
     * @param array<mixed> $value
     */
    private static function hasSelectableArtifactPayload(array $value): bool
    {
        foreach ([
            'bboxes',
            'image',
            'image_bbox',
            'layout',
            'layout_result',
            'order',
            'order_result',
            'prediction',
            'result',
            'model_output',
            'output',
            'page_data',
            'page_result',
        ] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $value
     */
    private static function isRawPdftextPageCopy(array $value): bool
    {
        return !self::hasSelectableArtifactPayload($value)
            && array_key_exists('blocks', $value)
            && array_key_exists('bbox', $value);
    }

    /**
     * pdftext page dictionaries carry page-level `bbox` and copied `blocks`.
     * Those keys describe the wrapper page, not layout/order model geometry.
     * Keep model-specific payload keys as envelope blockers, but allow
     * pdftext-shaped wrapper geometry to unwrap nested page artifact maps.
     *
     * @param array<mixed> $value
     */
    private static function hasEnvelopeBlockingDirectArtifactPayload(array $value): bool
    {
        foreach ([
            'bboxes',
            'image',
            'image_bbox',
            'layout',
            'layout_result',
            'order',
            'order_result',
            'prediction',
            'result',
            'model_output',
            'output',
            'page_data',
            'page_result',
        ] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Some adapter caches store a single selected payload under a source-page
     * object-map key. That is still one direct payload, not a page-list envelope.
     *
     * @param array<mixed> $value
     * @return array<string, mixed>|null
     */
    private static function singleKeyedDirectArtifactPayload(array $value): ?array
    {
        if (array_is_list($value) || self::hasDirectArtifactPayload($value)) {
            return null;
        }

        $dictionaryCount = 0;
        $payload = null;
        $payloadKey = null;
        $ignoredEntries = 0;
        foreach ($value as $key => $candidate) {
            $candidate = self::normalizeSuppliedArtifactMapCandidateValue($candidate);
            if (!is_array($candidate) || array_is_list($candidate) || !self::hasSelectableArtifactPayload($candidate)) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    $ignoredEntries++;
                    continue;
                }

                $dictionaryCount++;
                continue;
            }

            $pageKey = self::integerArrayKey($key);
            if (self::hasSelectableArtifactPayload($candidate)) {
                if ($payload !== null) {
                    return null;
                }

                $payload = $candidate;
                $payloadKey = $pageKey;
                $dictionaryCount++;
                continue;
            }

            if ($pageKey === null) {
                $ignoredEntries++;
                continue;
            }

            $dictionaryCount++;
        }

        if ($payload !== null && $payloadKey === null && $ignoredEntries > 0) {
            return null;
        }

        if ($payload !== null && $payloadKey !== null) {
            $payload = self::withEnvelopePageKey($payload, $payloadKey);
        }

        return $dictionaryCount === 1 ? $payload : null;
    }

    /**
     * Some native caches serialize a full source-page object map under a
     * pdftext-shaped envelope. Keep the key as selector-only page identity so
     * selected pages can be aligned without copying stale payloads downstream.
     *
     * @param array<mixed> $value
     * @return list<mixed>|null
     */
    private static function keyedEnvelopeArtifacts(array $value): ?array
    {
        if (array_is_list($value) || self::hasDirectArtifactPayload($value)) {
            return null;
        }

        $artifacts = [];
        $sawRawPdftextPageCopy = false;
        $seenPageKeys = [];
        foreach ($value as $key => $candidate) {
            $candidate = self::normalizeSuppliedArtifactMapCandidateValue($candidate);
            if (!is_array($candidate) || array_is_list($candidate)) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    continue;
                }

                return null;
            }

            $isRawPdftextPageCopy = self::isRawPdftextPageCopy($candidate);
            $hasPayload = self::hasSelectableArtifactPayload($candidate);
            if (!$isRawPdftextPageCopy && !$hasPayload) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    continue;
                }

                return null;
            }

            $pageKey = self::integerArrayKey($key);
            if ($pageKey !== null && $isRawPdftextPageCopy) {
                $sawRawPdftextPageCopy = true;
                continue;
            }
            if ($pageKey === null || !$hasPayload) {
                if (self::isIgnorableArtifactMapEntry($candidate)) {
                    continue;
                }

                return null;
            }

            self::rememberArtifactPageKey($seenPageKeys, $pageKey);
            $artifacts[] = self::withEnvelopePageKey($candidate, $pageKey);
        }

        if (count($artifacts) > 1) {
            return $artifacts;
        }

        return $sawRawPdftextPageCopy && $artifacts === [] ? [] : null;
    }

    /**
     * @param array<string, true> $seenPageKeys
     */
    private static function rememberArtifactPageKey(array &$seenPageKeys, int $pageKey): void
    {
        $fingerprint = (string) $pageKey;
        if (isset($seenPageKeys[$fingerprint])) {
            throw new InvalidArgumentException('Supplied page artifact map contains duplicate normalized page keys.');
        }

        $seenPageKeys[$fingerprint] = true;
    }

    /**
     * Source-page object-map keys are selector identity. They must agree with
     * any inner page metadata before an artifact is aligned to a selected page.
     *
     * @param array<mixed> $payload
     */
    private static function withEnvelopePageKey(array $payload, int $pageKey): array
    {
        $payload[self::ENVELOPE_PAGE_KEY_MARKER] = $pageKey;

        return $payload;
    }

    private static function isIgnorableArtifactMapEntry(mixed $candidate): bool
    {
        return !is_array($candidate) || !self::hasDirectArtifactPayload($candidate);
    }

    private static function integerArrayKey(int|string $key): ?int
    {
        if (is_int($key)) {
            if ($key < 0) {
                throw new InvalidArgumentException('Supplied page artifact map keys must be zero or greater.');
            }

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
            throw new InvalidArgumentException('Supplied page artifact map keys must fit in a PHP integer.');
        }

        $integer = (int) $number;
        if ($match[1] === '-' && $integer !== 0) {
            throw new InvalidArgumentException('Supplied page artifact map keys must be zero or greater.');
        }

        return $integer;
    }

    /**
     * @param array<mixed> $value
     */
    private static function hasPotentialPageMarker(array $value, int $depth = 0): bool
    {
        foreach (self::PAGE_MARKER_FIELD_GROUPS as $fields) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $value)) {
                    return true;
                }
            }
        }

        if ($depth >= 2) {
            return false;
        }

        foreach (self::PAGE_MARKER_METADATA_WRAPPERS as $key) {
            $candidate = $value[$key] ?? null;
            if (!is_array($candidate)) {
                continue;
            }

            if (!array_is_list($candidate)) {
                if (self::hasPotentialPageMarker($candidate, $depth + 1)) {
                    return true;
                }

                continue;
            }

            foreach ($candidate as $item) {
                if (is_array($item) && !array_is_list($item) && self::hasPotentialPageMarker($item, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{source_indexes?: list<int>, selected_indexes?: list<int>, pages?: list<int>, page_numbers?: list<int>, selected_page_numbers?: list<int>, envelope_page_keys?: list<int>, ambiguous_wrapper_lists?: list<int>, malformed_page_markers?: list<int>}
     */
    private function pageMarkers(array $artifact): array
    {
        $markers = [];
        $sources = $this->pageMarkerSources($artifact);
        $hasAmbiguousWrapperList = $this->pageMarkerSourcesHaveAmbiguousWrapperList($sources);
        $hasMalformedMarker = $this->pageMarkerSourcesHaveMalformedMarkers($sources);

        $sourceIndexes = $this->integerFieldsFromSources($sources, self::PAGE_MARKER_FIELD_GROUPS[0]);
        if ($sourceIndexes !== []) {
            $markers['source_indexes'] = $sourceIndexes;
        }

        $selectedIndexes = $this->integerFieldsFromSources($sources, self::PAGE_MARKER_FIELD_GROUPS[1]);
        if ($selectedIndexes !== []) {
            $markers['selected_indexes'] = $selectedIndexes;
        }

        $pages = $this->integerFieldsFromSources($sources, self::PAGE_MARKER_FIELD_GROUPS[2]);
        if ($pages !== []) {
            $markers['pages'] = $pages;
        }

        $pageNumbers = $this->integerFieldsFromSources($sources, self::PAGE_MARKER_FIELD_GROUPS[3]);
        if ($pageNumbers !== []) {
            $markers['page_numbers'] = $pageNumbers;
        }

        $selectedPageNumbers = $this->integerFieldsFromSources($sources, self::PAGE_MARKER_FIELD_GROUPS[4]);
        if ($selectedPageNumbers !== []) {
            $markers['selected_page_numbers'] = $selectedPageNumbers;
        }

        $envelopePageKeys = array_values(array_unique(array_merge(
            $this->integerFieldsFromSources($sources, [self::ENVELOPE_PAGE_KEY_MARKER]),
            self::directPayloadEnvelopePageKeys($artifact)
        ), SORT_REGULAR));
        if ($envelopePageKeys !== []) {
            $markers['envelope_page_keys'] = $envelopePageKeys;
        }

        if ($hasMalformedMarker) {
            $markers['malformed_page_markers'] = [1];
        }

        if ($markers === [] && $hasAmbiguousWrapperList) {
            $markers['ambiguous_wrapper_lists'] = [1];
        }

        return $markers;
    }

    /**
     * Direct layout/order/image payloads can be stored under a pdftext-shaped
     * source-page map inside an otherwise current wrapper. The numeric key is
     * selector-only identity and must agree with the selected page before the
     * payload can be assigned.
     *
     * @param array<mixed> $artifact
     * @return list<int>
     */
    private static function directPayloadEnvelopePageKeys(array $artifact): array
    {
        $keys = [];
        foreach (self::ARTIFACT_PAGE_LIST_ENVELOPES as $envelopeKey) {
            $value = $artifact[$envelopeKey] ?? null;
            if (is_array($value)) {
                self::collectDirectPayloadEnvelopePageKeys($value, $keys, 0);
            }
        }

        return array_values(array_unique($keys, SORT_REGULAR));
    }

    /**
     * @param array<mixed> $value
     * @param list<int> $keys
     */
    private static function collectDirectPayloadEnvelopePageKeys(array $value, array &$keys, int $depth): void
    {
        if ($depth > 1 || self::hasEnvelopeBlockingDirectArtifactPayload($value)) {
            return;
        }

        if (!array_is_list($value)) {
            foreach ($value as $key => $candidate) {
                if (
                    is_array($candidate)
                    && !array_is_list($candidate)
                    && self::hasEnvelopeBlockingDirectArtifactPayload($candidate)
                ) {
                    $pageKey = self::integerArrayKey($key);
                    if ($pageKey === null) {
                        continue;
                    }

                    $keys[] = $pageKey;
                }
            }
        }

        foreach (self::ARTIFACT_PAGE_LIST_ENVELOPES as $nestedKey) {
            $nested = $value[$nestedKey] ?? null;
            if (is_array($nested)) {
                self::collectDirectPayloadEnvelopePageKeys($nested, $keys, $depth + 1);
            }
        }
    }

    /**
     * Some supplied-layout adapters wrap upstream page identity in a shallow
     * metadata dictionary while leaving the model payload at the top level.
     *
     * @param array<string, mixed> $artifact
     * @return list<array<string, mixed>>
     */
    private function pageMarkerSources(array $artifact): array
    {
        $sources = [];
        $this->collectPageMarkerSources($artifact, $sources, 0, self::PAGE_MARKER_METADATA_WRAPPERS);

        if ($this->pageMarkerSourcesHaveMarkers($sources)) {
            return $sources;
        }

        $sources = [];
        $this->collectPageMarkerSources($artifact, $sources, 0, self::PAGE_MARKER_WRAPPERS);

        return $sources;
    }

    /**
     * Adapter output can wrap page identity in one shallow envelope around
     * another metadata envelope while keeping the model payload at top level.
     *
     * @param array<string, mixed> $artifact
     * @param list<array<string, mixed>> $sources
     * @param list<string> $wrapperKeys
     */
    private function collectPageMarkerSources(array $artifact, array &$sources, int $depth, array $wrapperKeys): void
    {
        $sources[] = $artifact;
        if ($depth >= 2) {
            return;
        }

        foreach ($wrapperKeys as $key) {
            $value = $artifact[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $wrapperValues = $this->dictionaryWrapperValues($value);
            if (array_is_list($value) && count($wrapperValues) > 1) {
                $sources[] = [self::AMBIGUOUS_PAGE_MARKER_WRAPPER => true];
                continue;
            }

            foreach ($wrapperValues as $wrapperValue) {
                $this->collectPageMarkerSources($wrapperValue, $sources, $depth + 1, $wrapperKeys);
            }
        }
    }

    /**
     * @param array<mixed> $value
     * @return list<array<string, mixed>>
     */
    private function dictionaryWrapperValues(array $value): array
    {
        if (!array_is_list($value)) {
            return [$value];
        }

        $dictionaries = [];
        foreach ($value as $item) {
            if (is_array($item) && !array_is_list($item)) {
                $dictionaries[] = $item;
            }
        }

        return $dictionaries;
    }

    /**
     * A nested pdftext dictionary is often a payload copy rather than adapter
     * identity. Typed model-result payloads can also carry stale page copies,
     * so payload-wrapper markers are used only when no root or metadata wrapper
     * carries page identity.
     *
     * @param list<array<string, mixed>> $sources
     */
    private function pageMarkerSourcesHaveMarkers(array $sources): bool
    {
        foreach ($sources as $source) {
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true || $this->pageMarkerSourceHasMalformedMarkers($source)) {
                return true;
            }

            foreach (self::PAGE_MARKER_FIELD_GROUPS as $fields) {
                if ($this->integerFields($source, $fields) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $sources
     */
    private function pageMarkerSourcesHaveAmbiguousWrapperList(array $sources): bool
    {
        foreach ($sources as $source) {
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $sources
     */
    private function pageMarkerSourcesHaveMalformedMarkers(array $sources): bool
    {
        foreach ($sources as $source) {
            if ($this->pageMarkerSourceHasMalformedMarkers($source)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function pageMarkerSourceHasMalformedMarkers(array $source): bool
    {
        foreach (self::PAGE_MARKER_FIELD_GROUPS as $fields) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $source)) {
                    continue;
                }

                if (!$this->isValidPageMarkerValue($source[$field])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isValidPageMarkerValue(mixed $value): bool
    {
        if ($this->integerValue($value) !== null) {
            return true;
        }

        if (!is_array($value) || !array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->integerValue($item) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{source_indexes?: list<int>, selected_indexes?: list<int>, pages?: list<int>, page_numbers?: list<int>, selected_page_numbers?: list<int>, envelope_page_keys?: list<int>, ambiguous_wrapper_lists?: list<int>, malformed_page_markers?: list<int>} $markers
     */
    private function pageMarkerMatchScore(array $markers, int $sourceIndex, ?int $pageNumber, int $selectedIndex): ?int
    {
        if (($markers['ambiguous_wrapper_lists'] ?? []) !== [] || ($markers['malformed_page_markers'] ?? []) !== []) {
            return null;
        }

        $score = 0;

        foreach ($markers['source_indexes'] ?? [] as $marker) {
            if ($marker !== $sourceIndex) {
                return null;
            }
        }
        if (($markers['source_indexes'] ?? []) !== []) {
            $score += 20;
        }

        foreach ($markers['selected_indexes'] ?? [] as $marker) {
            if ($marker !== $selectedIndex) {
                return null;
            }
        }
        if (($markers['selected_indexes'] ?? []) !== []) {
            $score += 40;
        }

        foreach ($markers['pages'] ?? [] as $marker) {
            if ($marker !== ($pageNumber ?? $sourceIndex)) {
                return null;
            }
        }
        if (($markers['pages'] ?? []) !== []) {
            $score += 100;
        }

        foreach ($markers['page_numbers'] ?? [] as $marker) {
            if ($marker !== (($pageNumber ?? $sourceIndex) + 1)) {
                return null;
            }
        }
        if (($markers['page_numbers'] ?? []) !== []) {
            $score += 10;
        }

        foreach ($markers['selected_page_numbers'] ?? [] as $marker) {
            if ($marker !== $selectedIndex + 1) {
                return null;
            }
        }
        if (($markers['selected_page_numbers'] ?? []) !== []) {
            $score += 30;
        }

        $envelopePageKeyScore = 0;
        foreach ($markers['envelope_page_keys'] ?? [] as $marker) {
            if (
                $pageNumber !== null
                && $marker === $pageNumber
            ) {
                $envelopePageKeyScore = max($envelopePageKeyScore, 95);
                continue;
            }

            if ($marker === $sourceIndex) {
                $envelopePageKeyScore = max($envelopePageKeyScore, 90);
                continue;
            }

            return null;
        }
        if (($markers['envelope_page_keys'] ?? []) !== []) {
            $score += $envelopePageKeyScore;
        }

        return $markers !== [] ? $score : null;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param list<string> $fields
     * @return list<int>
     */
    private function integerFields(array $artifact, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $artifact)) {
                continue;
            }

            array_push($values, ...$this->integerValues($artifact[$field]));
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param list<string> $fields
     * @return list<int>
     */
    private function integerFieldsFromSources(array $sources, array $fields): array
    {
        $values = [];
        foreach ($sources as $source) {
            array_push($values, ...$this->integerFields($source, $fields));
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                return null;
            }
            if (floor($value) === $value) {
                return (int) $value;
            }
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^[+-]?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }
            if (preg_match('/^[+-]?\d+\.0+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function integerValues(mixed $value): array
    {
        $single = $this->integerValue($value);
        if ($single !== null) {
            return [$single];
        }

        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $item) {
            $integer = $this->integerValue($item);
            if ($integer !== null) {
                $values[] = $integer;
            }
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }
}
