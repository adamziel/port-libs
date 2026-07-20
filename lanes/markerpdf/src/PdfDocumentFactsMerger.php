<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use RuntimeException;

/** Merge contiguous page-range facts into one verified document snapshot. */
final class PdfDocumentFactsMerger
{
    /** @var list<string> */
    private const TEXT_VISIBILITY_COUNT_KEYS = [
        'visibleRuns',
        'visibleOutputRuns',
        'suppressedNonPaintingRuns',
        'suppressedRenderingModeRuns',
        'suppressedZeroAlphaRuns',
        'suppressedOptionalContentRuns',
        'suppressedOutsidePageRuns',
        'suppressedNonPaintingActualTextRuns',
        'suppressedAccessibilityReplacementRuns',
        'unresolvedRuns',
        'unresolvedClippingRuns',
        'unresolvedOcclusionRiskRuns',
        'laterPaintRiskCount',
        'laterPaintRiskRecordedCount',
        'laterPaintRiskUnboundCount',
        'laterPaintRiskTruncatedCount',
    ];

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
        $layoutProfiles = [];
        $unassignedAnnotations = [];
        $diagnostics = [];
        $textVisibilityInputs = [];
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
            if (is_array($rangeData['structure']['documentProfile'] ?? null)) {
                $layoutProfiles[] = $rangeData['structure']['documentProfile'];
            }
            if ($unassignedAnnotations === [] && is_array($rangeData['unassignedAnnotations'] ?? null)) {
                $unassignedAnnotations = $rangeData['unassignedAnnotations'];
            }
            if ($diagnostics === [] && is_array($rangeData['diagnostics'] ?? null)) {
                $diagnostics = $rangeData['diagnostics'];
            }
            $textVisibilityInputs[] = [
                'receipt' => is_array($rangeData['diagnostics']['textVisibility'] ?? null)
                    ? $rangeData['diagnostics']['textVisibility']
                    : null,
                'ownedPages' => array_map(
                    static fn (PdfPageFacts $page): int => $page->pageNumber(),
                    $range->pages()
                ),
            ];
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

        $mergedTextVisibility = $this->mergeTextVisibilityReceipts(
            $textVisibilityInputs,
            array_keys($pages),
            (string) ($source['sha256'] ?? '')
        );
        if ($mergedTextVisibility !== null) {
            $diagnostics['textVisibility'] = $mergedTextVisibility;
        } else {
            unset($diagnostics['textVisibility']);
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
        if ($layoutProfiles !== []) {
            $structure['documentProfile'] = PdfDocumentLayoutProfile::merge($layoutProfiles, $totalPages);
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

    /**
     * Page facts can be persisted independently after one wider extraction,
     * so several input ranges may carry the same parent visibility receipt.
     * Validate every parent before combining its unique page evidence; this
     * prevents a malformed aggregate from being repaired into a trusted one
     * merely because its page summaries happen to look plausible.
     *
     * @param list<array{receipt:array<string,mixed>|null,ownedPages:list<int>}> $inputs
     * @param list<int> $pageNumbers
     * @return array<string,mixed>|null
     */
    private function mergeTextVisibilityReceipts(
        array $inputs,
        array $pageNumbers,
        string $sourceSha256
    ): ?array {
        $receipts = array_column($inputs, 'receipt');
        $arrayReceipts = array_values(array_filter($receipts, 'is_array'));
        if ($arrayReceipts === []) {
            return null;
        }
        $first = $arrayReceipts[0];
        if (in_array(null, $receipts, true)) {
            return $this->invalidTextVisibilityMerge($first, 'range-visibility-merge-input-missing');
        }

        $summariesByPage = [];
        $risksByPage = [];
        foreach ($inputs as $input) {
            $receipt = $input['receipt'];
            if (!is_array($receipt)) {
                return $this->invalidTextVisibilityMerge($first, 'range-visibility-merge-input-missing');
            }
            $validated = $this->validatedTextVisibilityReceipt($receipt, $sourceSha256);
            if ($validated === null) {
                return $this->invalidTextVisibilityMerge($first, 'range-visibility-merge-input-invalid');
            }
            foreach ($input['ownedPages'] as $ownedPage) {
                if (!isset($validated['summariesByPage'][$ownedPage])) {
                    return $this->invalidTextVisibilityMerge(
                        $first,
                        'range-visibility-merge-owned-page-summary-missing'
                    );
                }
            }
            foreach ($validated['summariesByPage'] as $page => $summary) {
                $pageRisks = $validated['risksByPage'][$page] ?? [];
                if (isset($summariesByPage[$page])) {
                    if ($summariesByPage[$page] !== $summary
                        || ($risksByPage[$page] ?? []) !== $pageRisks) {
                        return $this->invalidTextVisibilityMerge(
                            $first,
                            'range-visibility-merge-page-conflict'
                        );
                    }
                    continue;
                }
                $summariesByPage[$page] = $summary;
                $risksByPage[$page] = $pageRisks;
            }
        }

        sort($pageNumbers, SORT_NUMERIC);
        $pageSummaries = [];
        $laterPaintRisks = [];
        $selectedRiskIds = [];
        $selectedRiskDigests = [];
        $counts = array_fill_keys(self::TEXT_VISIBILITY_COUNT_KEYS, 0);
        $reasonCounts = [];
        foreach ($pageNumbers as $page) {
            if (!isset($summariesByPage[$page])) {
                return $this->invalidTextVisibilityMerge(
                    $first,
                    'range-visibility-merge-page-summary-missing'
                );
            }
            $summary = $summariesByPage[$page];
            $pageSummaries[] = $summary;
            foreach (self::TEXT_VISIBILITY_COUNT_KEYS as $key) {
                $counts[$key] += $summary[$key];
            }
            foreach ($summary['unresolvedReasonCounts'] as $reason => $count) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + $count;
            }
            foreach ($risksByPage[$page] ?? [] as $risk) {
                $riskId = $risk['id'];
                $riskDigest = $risk['riskDigest'];
                if (isset($selectedRiskIds[$riskId]) || isset($selectedRiskDigests[$riskDigest])) {
                    return $this->invalidTextVisibilityMerge(
                        $first,
                        'range-visibility-merge-risk-conflict'
                    );
                }
                $selectedRiskIds[$riskId] = true;
                $selectedRiskDigests[$riskDigest] = true;
                $laterPaintRisks[] = $risk;
            }
        }
        ksort($reasonCounts, SORT_STRING);

        $merged = $first;
        foreach ($counts as $key => $count) {
            $merged[$key] = $count;
        }
        $merged['policy'] = 'visible-painted-text-v1';
        $merged['complete'] = $counts['unresolvedRuns'] === 0
            && $counts['unresolvedOcclusionRiskRuns'] === 0;
        $merged['unresolvedReasons'] = array_keys($reasonCounts);
        $merged['unresolvedReasonCounts'] = $reasonCounts;
        $merged['laterPaintRisks'] = $laterPaintRisks;
        $merged['laterPaintRisksDigest'] = $this->textVisibilityDigest($laterPaintRisks);
        $merged['pages'] = $pageSummaries;
        unset(
            $merged['rangeMergeComplete'],
            $merged['rangeMergeFailureReason'],
            $merged['rangeLocalizationComplete'],
            $merged['rangeLocalizationFailureReason']
        );

        return $merged;
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array{
     *     summariesByPage:array<int,array<string,mixed>>,
     *     risksByPage:array<int,list<array<string,mixed>>>
     * }|null
     */
    private function validatedTextVisibilityReceipt(array $receipt, string $sourceSha256): ?array
    {
        if (($receipt['policy'] ?? null) !== 'visible-painted-text-v1'
            || !is_array($receipt['pages'] ?? null)
            || !array_is_list($receipt['pages'])
            || $receipt['pages'] === []
            || !is_array($receipt['laterPaintRisks'] ?? null)
            || !array_is_list($receipt['laterPaintRisks'])) {
            return null;
        }
        foreach (self::TEXT_VISIBILITY_COUNT_KEYS as $key) {
            if (!is_int($receipt[$key] ?? null) || $receipt[$key] < 0) {
                return null;
            }
        }
        $reasonCounts = $this->normalizedTextVisibilityReasonCounts(
            $receipt['unresolvedReasonCounts'] ?? null,
            $receipt['unresolvedReasons'] ?? null
        );
        if ($reasonCounts === null
            || !$this->textVisibilityStateIsInternallyComplete($receipt, $reasonCounts)
            || !is_string($receipt['laterPaintRisksDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $receipt['laterPaintRisksDigest']) !== 1
            || !hash_equals(
                $receipt['laterPaintRisksDigest'],
                $this->textVisibilityDigest($receipt['laterPaintRisks'])
            )
            || count($receipt['laterPaintRisks']) !== $receipt['laterPaintRiskRecordedCount']) {
            return null;
        }

        $summariesByPage = [];
        $aggregateCounts = array_fill_keys(self::TEXT_VISIBILITY_COUNT_KEYS, 0);
        $aggregateReasonCounts = [];
        foreach ($receipt['pages'] as $summary) {
            $page = is_array($summary) && is_int($summary['page'] ?? null)
                ? $summary['page']
                : 0;
            if ($page < 1 || isset($summariesByPage[$page])) {
                return null;
            }
            foreach (self::TEXT_VISIBILITY_COUNT_KEYS as $key) {
                if (!is_int($summary[$key] ?? null) || $summary[$key] < 0) {
                    return null;
                }
                $aggregateCounts[$key] += $summary[$key];
            }
            $summaryReasonCounts = $this->normalizedTextVisibilityReasonCounts(
                $summary['unresolvedReasonCounts'] ?? null,
                $summary['unresolvedReasons'] ?? null
            );
            if ($summaryReasonCounts === null
                || !$this->textVisibilityStateIsInternallyComplete($summary, $summaryReasonCounts)
                || !is_string($summary['laterPaintRisksDigest'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $summary['laterPaintRisksDigest']) !== 1) {
                return null;
            }
            foreach ($summaryReasonCounts as $reason => $count) {
                $aggregateReasonCounts[$reason] = ($aggregateReasonCounts[$reason] ?? 0) + $count;
            }
            $summariesByPage[$page] = $summary;
        }
        ksort($aggregateReasonCounts, SORT_STRING);
        foreach (self::TEXT_VISIBILITY_COUNT_KEYS as $key) {
            if ($aggregateCounts[$key] !== $receipt[$key]) {
                return null;
            }
        }
        if ($aggregateReasonCounts !== $reasonCounts) {
            return null;
        }

        $risksByPage = [];
        $riskIds = [];
        $riskDigests = [];
        foreach ($receipt['laterPaintRisks'] as $risk) {
            $page = is_array($risk) && is_int($risk['page'] ?? null) ? $risk['page'] : 0;
            $id = is_array($risk) && is_string($risk['id'] ?? null) ? $risk['id'] : '';
            $riskDigest = is_array($risk) && is_string($risk['riskDigest'] ?? null)
                ? $risk['riskDigest']
                : '';
            if ($page < 1
                || !isset($summariesByPage[$page])
                || $id === ''
                || isset($riskIds[$id])
                || preg_match('/^[a-f0-9]{64}$/D', $riskDigest) !== 1
                || isset($riskDigests[$riskDigest])
                || !is_string($risk['sourceSha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $risk['sourceSha256']) !== 1
                || ($sourceSha256 !== '' && !hash_equals($sourceSha256, $risk['sourceSha256']))) {
                return null;
            }
            $payload = $risk;
            unset($payload['id'], $payload['riskDigest']);
            if (!hash_equals($riskDigest, $this->textVisibilityDigest($payload))
                || $id !== 'pdf-later-paint-risk-' . substr($riskDigest, 0, 32)) {
                return null;
            }
            $riskIds[$id] = true;
            $riskDigests[$riskDigest] = true;
            $risksByPage[$page][] = $risk;
        }
        foreach ($summariesByPage as $page => $summary) {
            $pageRisks = $risksByPage[$page] ?? [];
            if (count($pageRisks) !== $summary['laterPaintRiskRecordedCount']
                || !hash_equals(
                    $summary['laterPaintRisksDigest'],
                    $this->textVisibilityDigest($pageRisks)
                )) {
                return null;
            }
        }

        return compact('summariesByPage', 'risksByPage');
    }

    /** @return array<string,int>|null */
    private function normalizedTextVisibilityReasonCounts(mixed $counts, mixed $reasons): ?array
    {
        if (!is_array($counts) || !is_array($reasons) || !array_is_list($reasons)) {
            return null;
        }
        $normalized = [];
        foreach ($counts as $reason => $count) {
            if (!is_string($reason) || $reason === '' || !is_int($count) || $count < 1) {
                return null;
            }
            $normalized[$reason] = $count;
        }
        ksort($normalized, SORT_STRING);

        return $reasons === array_keys($normalized) ? $normalized : null;
    }

    /** @param array<string,mixed> $state @param array<string,int> $reasonCounts */
    private function textVisibilityStateIsInternallyComplete(array $state, array $reasonCounts): bool
    {
        return is_bool($state['complete'] ?? null)
            && $state['visibleRuns'] === $state['visibleOutputRuns']
            && $state['suppressedNonPaintingRuns']
                === $state['suppressedRenderingModeRuns']
                    + $state['suppressedZeroAlphaRuns']
                    + $state['suppressedOptionalContentRuns']
            && $state['suppressedNonPaintingActualTextRuns']
                === $state['suppressedAccessibilityReplacementRuns']
            && $state['unresolvedClippingRuns']
                === ($reasonCounts['unresolved-clipping-path'] ?? 0)
            && $state['unresolvedOcclusionRiskRuns']
                === ($reasonCounts['later-paint-occlusion'] ?? 0)
            && $state['laterPaintRiskCount'] === $state['unresolvedOcclusionRiskRuns']
            && $state['laterPaintRiskCount']
                === $state['laterPaintRiskRecordedCount']
                    + $state['laterPaintRiskUnboundCount']
                    + $state['laterPaintRiskTruncatedCount']
            && $state['complete']
                === ($state['unresolvedRuns'] === 0
                    && $state['unresolvedOcclusionRiskRuns'] === 0);
    }

    /** @param array<string,mixed> $receipt @return array<string,mixed> */
    private function invalidTextVisibilityMerge(array $receipt, string $reason): array
    {
        $receipt['policy'] = 'visibility-evidence-merge-invalid';
        $receipt['complete'] = false;
        $receipt['rangeMergeComplete'] = false;
        $receipt['rangeMergeFailureReason'] = $reason;

        return $receipt;
    }

    /** @param array<mixed> $value */
    private function textVisibilityDigest(array $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        return hash('sha256', is_string($encoded) ? $encoded : serialize($value));
    }
}
