<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfPageArtifactSelector
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
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
        ['page_index', 'page_idx', 'doc_page_index', 'doc_page_idx', 'document_page_index', 'document_page_idx', 'source_page_index', 'source_page_idx', 'page_range', 'source_page_range', 'document_page_range', 'page_indices', 'source_page_indices', 'document_page_indices'],
        ['selected_page_index', 'selected_page_idx', 'trimmed_page_index', 'trimmed_page_idx', 'relative_page_index', 'relative_page_idx', 'selected_page_range', 'trimmed_page_range', 'relative_page_range', 'selected_page_indices', 'trimmed_page_indices', 'relative_page_indices'],
        ['pnum', 'pnums', 'page', 'pdftext_page', 'pdftext_pages', 'source_page', 'source_pages', 'document_page', 'document_pages'],
        ['page_number', 'page_numbers', 'page_num', 'page_nums', 'doc_page_number', 'doc_page_numbers', 'document_page_number', 'document_page_numbers', 'pdftext_page_number', 'pdftext_page_numbers', 'source_page_number', 'source_page_numbers'],
        ['selected_page_number', 'selected_page_numbers', 'trimmed_page_number', 'trimmed_page_numbers', 'relative_page_number', 'relative_page_numbers', 'selected_page_num', 'selected_page_nums', 'trimmed_page_num', 'trimmed_page_nums', 'relative_page_num', 'relative_page_nums'],
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
            if (is_array($artifact)) {
                $artifact = self::normalizeSuppliedArtifactValue($artifact);
                $unwrapped = self::artifactListFromEnvelope($artifact);
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
        foreach ($value as $key => $candidate) {
            $pageKey = self::integerArrayKey($key);
            if ($pageKey === null || !is_array($candidate) || array_is_list($candidate)) {
                return null;
            }

            if (!self::hasPotentialPageMarker($candidate)) {
                $candidate[self::ENVELOPE_PAGE_KEY_MARKER] = $pageKey;
            }
            $artifacts[] = $candidate;
        }

        return $artifacts !== [] ? $artifacts : null;
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

        foreach (['pages', 'dictionary_output', 'pdftext'] as $pageListKey) {
            if (!array_key_exists($pageListKey, $value)) {
                continue;
            }

            $artifacts = self::normalizeSuppliedArtifactValue($value[$pageListKey]);
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
                        return self::hasPotentialPageMarker($value) ? null : [$singleKeyedPayload];
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
                return self::hasPotentialPageMarker($value) ? null : [$singleKeyedPayload];
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
     * @param array<mixed> $value
     */
    private static function hasDirectArtifactPayload(array $value): bool
    {
        foreach ([
            'blocks',
            'bbox',
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
        foreach ($value as $key => $candidate) {
            if (!is_array($candidate) || array_is_list($candidate)) {
                continue;
            }

            $dictionaryCount++;
            if (self::hasDirectArtifactPayload($candidate)) {
                if ($payload !== null) {
                    return null;
                }

                $payload = $candidate;
                $payloadKey = self::integerArrayKey($key);
            }
        }

        if ($payload !== null && $payloadKey !== null && !self::hasPotentialPageMarker($payload)) {
            $payload[self::ENVELOPE_PAGE_KEY_MARKER] = $payloadKey;
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
        foreach ($value as $key => $candidate) {
            $pageKey = self::integerArrayKey($key);
            if ($pageKey === null || !is_array($candidate) || array_is_list($candidate)) {
                return null;
            }

            if (!self::hasPotentialPageMarker($candidate)) {
                $candidate[self::ENVELOPE_PAGE_KEY_MARKER] = $pageKey;
            }
            $artifacts[] = $candidate;
        }

        return count($artifacts) > 1 ? $artifacts : null;
    }

    private static function integerArrayKey(int|string $key): ?int
    {
        if (is_int($key)) {
            return $key;
        }

        $trimmed = trim($key);
        if (preg_match('/^[+-]?\d+$/', $trimmed) !== 1) {
            return null;
        }

        return (int) $trimmed;
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

        $sourceIndexes = $this->integerFieldsFromSources($sources, ['page_index', 'page_idx', 'doc_page_index', 'doc_page_idx', 'document_page_index', 'document_page_idx', 'source_page_index', 'source_page_idx', 'page_range', 'source_page_range', 'document_page_range', 'page_indices', 'source_page_indices', 'document_page_indices']);
        if ($sourceIndexes !== []) {
            $markers['source_indexes'] = $sourceIndexes;
        }

        $selectedIndexes = $this->integerFieldsFromSources($sources, ['selected_page_index', 'selected_page_idx', 'trimmed_page_index', 'trimmed_page_idx', 'relative_page_index', 'relative_page_idx', 'selected_page_range', 'trimmed_page_range', 'relative_page_range', 'selected_page_indices', 'trimmed_page_indices', 'relative_page_indices']);
        if ($selectedIndexes !== []) {
            $markers['selected_indexes'] = $selectedIndexes;
        }

        $pages = $this->integerFieldsFromSources($sources, ['pnum', 'pnums', 'page', 'pdftext_page', 'pdftext_pages', 'source_page', 'source_pages', 'document_page', 'document_pages']);
        if ($pages !== []) {
            $markers['pages'] = $pages;
        }

        $pageNumbers = $this->integerFieldsFromSources($sources, ['page_number', 'page_numbers', 'page_num', 'page_nums', 'doc_page_number', 'doc_page_numbers', 'document_page_number', 'document_page_numbers', 'pdftext_page_number', 'pdftext_page_numbers', 'source_page_number', 'source_page_numbers']);
        if ($pageNumbers !== []) {
            $markers['page_numbers'] = $pageNumbers;
        }

        $selectedPageNumbers = $this->integerFieldsFromSources($sources, ['selected_page_number', 'selected_page_numbers', 'trimmed_page_number', 'trimmed_page_numbers', 'relative_page_number', 'relative_page_numbers', 'selected_page_num', 'selected_page_nums', 'trimmed_page_num', 'trimmed_page_nums', 'relative_page_num', 'relative_page_nums']);
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
        foreach (['pages', 'dictionary_output', 'pdftext'] as $envelopeKey) {
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
                $pageKey = self::integerArrayKey($key);
                if (
                    $pageKey !== null
                    && is_array($candidate)
                    && !array_is_list($candidate)
                    && self::hasEnvelopeBlockingDirectArtifactPayload($candidate)
                ) {
                    $keys[] = $pageKey;
                }
            }
        }

        foreach (['pages', 'dictionary_output', 'pdftext'] as $nestedKey) {
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

        foreach ($markers['envelope_page_keys'] ?? [] as $marker) {
            if (
                $marker !== $sourceIndex
                && ($pageNumber === null || ($marker !== $pageNumber && $marker !== $pageNumber + 1))
            ) {
                return null;
            }
        }
        if (($markers['envelope_page_keys'] ?? []) !== []) {
            $score += 90;
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
