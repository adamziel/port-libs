<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfPageArtifactSelector
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
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
        ['page_index', 'doc_page_index', 'document_page_index', 'source_page_index', 'page_range', 'source_page_range', 'document_page_range', 'page_indices', 'source_page_indices', 'document_page_indices'],
        ['selected_page_index', 'trimmed_page_index', 'relative_page_index', 'selected_page_range', 'trimmed_page_range', 'relative_page_range', 'selected_page_indices', 'trimmed_page_indices', 'relative_page_indices'],
        ['pnum', 'page', 'pdftext_page', 'source_page', 'document_page'],
        ['page_number'],
        ['selected_page_number', 'trimmed_page_number', 'relative_page_number'],
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
        $artifacts = array_values($artifacts);
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
                }
            }

            if ($artifact !== null) {
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
     * @return array{source_indexes?: list<int>, selected_indexes?: list<int>, pages?: list<int>, page_numbers?: list<int>, selected_page_numbers?: list<int>, ambiguous_wrapper_lists?: list<int>}
     */
    private function pageMarkers(array $artifact): array
    {
        $markers = [];
        $sources = $this->pageMarkerSources($artifact);
        $hasAmbiguousWrapperList = $this->pageMarkerSourcesHaveAmbiguousWrapperList($sources);

        $sourceIndexes = $this->integerFieldsFromSources($sources, ['page_index', 'doc_page_index', 'document_page_index', 'source_page_index', 'page_range', 'source_page_range', 'document_page_range', 'page_indices', 'source_page_indices', 'document_page_indices']);
        if ($sourceIndexes !== []) {
            $markers['source_indexes'] = $sourceIndexes;
        }

        $selectedIndexes = $this->integerFieldsFromSources($sources, ['selected_page_index', 'trimmed_page_index', 'relative_page_index', 'selected_page_range', 'trimmed_page_range', 'relative_page_range', 'selected_page_indices', 'trimmed_page_indices', 'relative_page_indices']);
        if ($selectedIndexes !== []) {
            $markers['selected_indexes'] = $selectedIndexes;
        }

        $pages = $this->integerFieldsFromSources($sources, ['pnum', 'page', 'pdftext_page', 'source_page', 'document_page']);
        if ($pages !== []) {
            $markers['pages'] = $pages;
        }

        $pageNumbers = $this->integerFieldsFromSources($sources, ['page_number']);
        if ($pageNumbers !== []) {
            $markers['page_numbers'] = $pageNumbers;
        }

        $selectedPageNumbers = $this->integerFieldsFromSources($sources, ['selected_page_number', 'trimmed_page_number', 'relative_page_number']);
        if ($selectedPageNumbers !== []) {
            $markers['selected_page_numbers'] = $selectedPageNumbers;
        }

        if ($markers === [] && $hasAmbiguousWrapperList) {
            $markers['ambiguous_wrapper_lists'] = [1];
        }

        return $markers;
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
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true) {
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
     * @param array{source_indexes?: list<int>, selected_indexes?: list<int>, pages?: list<int>, page_numbers?: list<int>, selected_page_numbers?: list<int>, ambiguous_wrapper_lists?: list<int>} $markers
     */
    private function pageMarkerMatchScore(array $markers, int $sourceIndex, ?int $pageNumber, int $selectedIndex): ?int
    {
        if (($markers['ambiguous_wrapper_lists'] ?? []) !== []) {
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

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
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
