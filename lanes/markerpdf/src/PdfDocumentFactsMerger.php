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
        if ($ranges === [] || !$ranges[0] instanceof PdfDocumentFacts) {
            throw new RuntimeException('No PDF page facts were available to merge.');
        }
        $totalPages = max(0, (int) ($ranges[0]->inventory()['totalPages'] ?? 0));
        if ($totalPages < 1) {
            throw new RuntimeException('PDF page facts did not declare a complete page inventory.');
        }

        return $this->mergeRange($ranges, 1, $totalPages);
    }

    /**
     * Merge one verified contiguous page range while retaining the source
     * document's complete inventory. This lets a bounded WordPress request
     * resolve layout for a long-document part without pretending the part is
     * a different PDF or loading every page fact into memory at once.
     *
     * @param list<PdfDocumentFacts> $ranges
     */
    public function mergeRange(array $ranges, int $startPage, int $endPage): PdfDocumentFacts
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
        if ($startPage < 1 || $endPage < $startPage || $endPage > $totalPages) {
            throw new RuntimeException('The requested PDF facts range was outside the source page inventory.');
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
        if (array_keys($pages) !== range($startPage, $endPage)) {
            throw new RuntimeException('PDF page facts ranges were not the requested contiguous page range.');
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
        $rangePageCount = $endPage - $startPage + 1;
        if (isset($providerParts['pdfjs-v1']) || isset($diagnostics['browserFacts'])) {
            $diagnostics['browserFacts'] = [
                'provider' => 'pdfjs-v1',
                'status' => $browserPages === 0 ? 'unavailable' : ($browserPages === $rangePageCount ? 'applied' : 'partial'),
                'reason' => $browserPages === $rangePageCount
                    ? 'Browser text and structure facts were attached to every page in this range.'
                    : 'Native facts cover every page in this range; browser facts were attached only where available.',
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
                'startPage' => $startPage,
                'endPage' => $endPage,
                'pageNumbers' => range($startPage, $endPage),
                'hasMorePages' => $endPage < $totalPages,
                'nextPage' => $endPage < $totalPages ? $endPage + 1 : null,
            ],
            array_values($pages),
            $structure,
            $diagnostics,
            $unassignedAnnotations
        );
    }
}
