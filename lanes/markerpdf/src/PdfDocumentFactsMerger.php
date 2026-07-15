<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use RuntimeException;

/** Merge contiguous page-range facts into one verified document snapshot. */
final class PdfDocumentFactsMerger
{
    /**
     * @param list<PdfDocumentFacts> $ranges
     */
    public function mergeComplete(array $ranges): PdfDocumentFacts
    {
        if ($ranges === []) {
            throw new RuntimeException('No PDF page facts were available to merge.');
        }
        $first = $ranges[0];
        if (!$first instanceof PdfDocumentFacts) {
            throw new RuntimeException('PDF page facts ranges must contain PdfDocumentFacts values.');
        }
        $source = $first->source();
        $totalPages = max(0, (int) ($first->inventory()['totalPages'] ?? 0));
        if ($totalPages < 1) {
            throw new RuntimeException('PDF page facts did not declare a complete page inventory.');
        }

        $pages = [];
        $providerParts = [];
        $warnings = [];
        $browserFailures = [];
        $structure = [];
        $unassignedAnnotations = [];
        $diagnostics = [];
        foreach ($ranges as $range) {
            if (!$range instanceof PdfDocumentFacts) {
                throw new RuntimeException('PDF page facts ranges must contain PdfDocumentFacts values.');
            }
            if ($range->source() !== $source || (int) ($range->inventory()['totalPages'] ?? 0) !== $totalPages) {
                throw new RuntimeException('PDF page facts ranges came from different sources or page inventories.');
            }
            foreach (explode('+', $range->provider()) as $providerPart) {
                if ($providerPart !== '') {
                    $providerParts[$providerPart] = true;
                }
            }
            $rangeData = $range->toArray();
            if ($structure === [] && is_array($rangeData['structure'] ?? null)) {
                $structure = $rangeData['structure'];
            }
            if ($unassignedAnnotations === [] && is_array($rangeData['unassignedAnnotations'] ?? null)) {
                $unassignedAnnotations = $rangeData['unassignedAnnotations'];
            }
            if ($diagnostics === [] && is_array($rangeData['diagnostics'] ?? null)) {
                $diagnostics = $rangeData['diagnostics'];
            }
            foreach ($rangeData['diagnostics']['warnings'] ?? [] as $warning) {
                if (is_string($warning) && $warning !== '') {
                    $warnings[$warning] = true;
                }
            }
            foreach ($rangeData['diagnostics']['browserFacts']['failures'] ?? [] as $failure) {
                if (is_array($failure)) {
                    $browserFailures[json_encode($failure, JSON_UNESCAPED_SLASHES) ?: serialize($failure)] = $failure;
                }
            }
            foreach ($range->pages() as $page) {
                $pageNumber = $page->pageNumber();
                if (isset($pages[$pageNumber])) {
                    throw new RuntimeException('PDF page facts ranges overlapped at page ' . $pageNumber . '.');
                }
                $pages[$pageNumber] = $page;
            }
        }
        ksort($pages, SORT_NUMERIC);
        if (array_keys($pages) !== range(1, $totalPages)) {
            throw new RuntimeException('PDF page facts ranges were not a complete contiguous document.');
        }

        $diagnostics['warnings'] = array_keys($warnings);
        $pagesWithIssues = 0;
        $browserPages = 0;
        foreach ($pages as $page) {
            if ($page->issues() !== []) {
                $pagesWithIssues++;
            }
            if (isset($page->text()['browser'])) {
                $browserPages++;
            }
        }
        $diagnostics['pagesWithExtractionIssues'] = $pagesWithIssues;
        if (isset($providerParts['pdfjs-v1']) || isset($diagnostics['browserFacts'])) {
            $diagnostics['browserFacts'] = [
                'provider' => 'pdfjs-v1',
                'status' => $browserPages === 0 ? 'unavailable' : ($browserPages === $totalPages ? 'applied' : 'partial'),
                'reason' => $browserPages === $totalPages
                    ? 'Browser text and structure facts were attached to every page.'
                    : 'Native facts cover every page; browser facts were attached only where available.',
                'providedPages' => $browserPages,
                'appliedPages' => $browserPages,
                'failures' => array_values($browserFailures),
            ];
        }

        return new PdfDocumentFacts(
            implode('+', array_keys($providerParts)),
            $source,
            [
                'totalPages' => $totalPages,
                'startPage' => 1,
                'endPage' => $totalPages,
                'pageNumbers' => range(1, $totalPages),
                'hasMorePages' => false,
                'nextPage' => null,
            ],
            array_values($pages),
            $structure,
            $diagnostics,
            $unassignedAnnotations
        );
    }
}
