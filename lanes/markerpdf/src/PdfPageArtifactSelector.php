<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfPageArtifactSelector
{
    private const MISSING_PAGE_ARTIFACT = '__markerpdf_missing_page_artifact';

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
        foreach (array_values($pageRange) as $selectedIndex => $sourceIndex) {
            $pageNumber = $selectedPageNumbers[$selectedIndex] ?? null;
            $artifact = null;
            foreach ($artifacts as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                $markers = $this->pageMarkers($candidate);
                if ($markers === []) {
                    continue;
                }

                $hasMarkers = true;
                if ($this->pageMarkersMatchSelectedPage($markers, $sourceIndex, $pageNumber)) {
                    $artifact = $candidate;
                    break;
                }
            }

            if ($artifact !== null) {
                $selected[] = $artifact;
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
     * @return array{source_indexes?: list<int>, pages?: list<int>, page_numbers?: list<int>}
     */
    private function pageMarkers(array $artifact): array
    {
        $markers = [];
        $sourceIndexes = $this->integerFields($artifact, ['page_index', 'doc_page_index', 'document_page_index', 'source_page_index']);
        if ($sourceIndexes !== []) {
            $markers['source_indexes'] = $sourceIndexes;
        }

        $pages = $this->integerFields($artifact, ['pnum', 'page', 'pdftext_page']);
        if ($pages !== []) {
            $markers['pages'] = $pages;
        }

        $pageNumbers = $this->integerFields($artifact, ['page_number']);
        if ($pageNumbers !== []) {
            $markers['page_numbers'] = $pageNumbers;
        }

        return $markers;
    }

    /**
     * @param array{source_indexes?: list<int>, pages?: list<int>, page_numbers?: list<int>} $markers
     */
    private function pageMarkersMatchSelectedPage(array $markers, int $sourceIndex, ?int $pageNumber): bool
    {
        foreach ($markers['source_indexes'] ?? [] as $marker) {
            if ($marker !== $sourceIndex) {
                return false;
            }
        }

        foreach ($markers['pages'] ?? [] as $marker) {
            if ($marker !== ($pageNumber ?? $sourceIndex)) {
                return false;
            }
        }

        foreach ($markers['page_numbers'] ?? [] as $marker) {
            if ($marker !== (($pageNumber ?? $sourceIndex) + 1)) {
                return false;
            }
        }

        return $markers !== [];
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

            $value = $this->integerValue($artifact[$field]);
            if ($value !== null) {
                $values[] = $value;
            }
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

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
