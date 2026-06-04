<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfPageArtifactSelector
{
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

    /**
     * @param list<mixed> $artifacts
     * @param list<int> $pageRange
     * @param list<int|null> $selectedPageNumbers
     * @return list<mixed>|null
     */
    private function selectByPageMarkers(array $artifacts, array $pageRange, array $selectedPageNumbers): ?array
    {
        $hasMarkers = false;
        $bySourceIndex = [];
        $byPage = [];
        $byPageNumber = [];

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $sourceIndex = $this->firstIntegerField($artifact, ['page_index', 'doc_page_index', 'document_page_index', 'source_page_index']);
            if ($sourceIndex !== null) {
                $hasMarkers = true;
                $bySourceIndex[$sourceIndex] ??= $artifact;
            }

            $page = $this->firstIntegerField($artifact, ['pnum', 'page', 'pdftext_page']);
            if ($page !== null) {
                $hasMarkers = true;
                $byPage[$page] ??= $artifact;
            }

            $pageNumber = $this->firstIntegerField($artifact, ['page_number']);
            if ($pageNumber !== null) {
                $hasMarkers = true;
                $byPageNumber[$pageNumber] ??= $artifact;
            }
        }

        if (!$hasMarkers) {
            return null;
        }

        $selected = [];
        $matched = 0;
        foreach (array_values($pageRange) as $selectedIndex => $sourceIndex) {
            $pageNumber = $selectedPageNumbers[$selectedIndex] ?? null;
            $artifact = $this->artifactForSelectedPage($bySourceIndex, $byPage, $byPageNumber, $sourceIndex, $pageNumber);
            if ($artifact !== null) {
                $selected[] = $artifact;
                $matched++;
                continue;
            }

            $selected[] = [];
        }

        return $matched > 0 ? $selected : [];
    }

    /**
     * @param array<int, mixed> $bySourceIndex
     * @param array<int, mixed> $byPage
     * @param array<int, mixed> $byPageNumber
     */
    private function artifactForSelectedPage(
        array $bySourceIndex,
        array $byPage,
        array $byPageNumber,
        int $sourceIndex,
        ?int $pageNumber
    ): mixed {
        if (array_key_exists($sourceIndex, $bySourceIndex)) {
            return $bySourceIndex[$sourceIndex];
        }

        if ($pageNumber !== null && array_key_exists($pageNumber, $byPage)) {
            return $byPage[$pageNumber];
        }

        if ($pageNumber !== null && array_key_exists($pageNumber + 1, $byPageNumber)) {
            return $byPageNumber[$pageNumber + 1];
        }

        if ($pageNumber !== null) {
            return null;
        }

        if (array_key_exists($sourceIndex, $byPage)) {
            return $byPage[$sourceIndex];
        }

        if (array_key_exists($sourceIndex + 1, $byPageNumber)) {
            return $byPageNumber[$sourceIndex + 1];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param list<string> $fields
     */
    private function firstIntegerField(array $artifact, array $fields): ?int
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $artifact)) {
                continue;
            }

            $value = $this->integerValue($artifact[$field]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
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
