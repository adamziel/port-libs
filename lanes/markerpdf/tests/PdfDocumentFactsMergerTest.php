<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfDocumentFacts;
use PortLibs\MarkerPDF\PdfDocumentFactsMerger;

$mergeFactsPdf = static function (): string {
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R] /Count 3 >>',
        9 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    foreach (['Alpha', 'Beta', 'Gamma'] as $index => $text) {
        $pageObject = 3 + ($index * 2);
        $contentObject = $pageObject + 1;
        $content = "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET";
        $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> >> /Contents {$contentObject} 0 R >>";
        $objects[$contentObject] = "<< /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
};

$singlePageFacts = static function (PdfDocumentFacts $facts, int $page): PdfDocumentFacts {
    $data = $facts->toArray();
    $pageFacts = $facts->page($page);
    if ($pageFacts === null) {
        throw new RuntimeException('The requested fixture page was not present.');
    }
    $totalPages = (int) ($facts->inventory()['totalPages'] ?? 0);
    $data['pages'] = [$pageFacts->toArray()];
    $data['inventory'] = [
        'totalPages' => $totalPages,
        'startPage' => $page,
        'endPage' => $page,
        'pageNumbers' => [$page],
        'hasMorePages' => $page < $totalPages,
        'nextPage' => $page < $totalPages ? $page + 1 : null,
    ];

    return PdfDocumentFacts::fromArray($data);
};

$mergeRiskFactsPdf = static function (): string {
    $covered = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (covered text) Tj ET '
        . 'q 180 0 0 60 60 690 cm /Im8 Do Q';
    $visible = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (visible text) Tj ET';
    $imageBytes = "\x80";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << /Font << /F1 7 0 R >> /XObject << /Im8 8 0 R >> >> "
        . "/Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($covered) . " >>\nstream\n{$covered}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 "
        . "/ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($imageBytes)
        . " >>\nstream\n{$imageBytes}\nendstream\nendobj\n"
        . "%%EOF\n";
};

return [
    'merges durable page ranges into one contiguous document facts snapshot' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $whole = $provider->extract($pdf);
        $first = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 2]);
        $second = $provider->extract($pdf, ['startPage' => 3, 'maxPages' => 1]);
        $merged = (new PdfDocumentFactsMerger())->mergeComplete([$first, $second]);

        $t->same([1, 2, 3], $merged->inventory()['pageNumbers']);
        $t->same(false, $merged->inventory()['hasMorePages']);
        $t->same(['Alpha'], array_column($merged->page(1)->text()['lines'], 'text'));
        $t->same(['Gamma'], array_column($merged->page(3)->text()['lines'], 'text'));
        $t->same($first->source(), $merged->source());
        $t->same(
            $whole->structure()['documentProfile'],
            $merged->structure()['documentProfile'],
            'Document-wide layout evidence must not depend on extraction chunk size.'
        );
        $t->same(true, $merged->structure()['documentProfile']['complete']);
        $visibility = $merged->diagnostics()['textVisibility'] ?? [];
        $t->same([1, 2, 3], array_column($visibility['pages'] ?? [], 'page'));
        $t->same(3, $visibility['visibleRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
        $t->same($merged->toArray(), \PortLibs\MarkerPDF\PdfDocumentFacts::fromJson($merged->toJson())->toArray());
    },
    'merges a bounded contiguous range without losing the source page inventory' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $first = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1]);
        $second = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1]);
        $merged = (new PdfDocumentFactsMerger())->mergeRange([$first, $second], 1, 2);

        $t->same(3, $merged->inventory()['totalPages']);
        $t->same(1, $merged->inventory()['startPage']);
        $t->same(2, $merged->inventory()['endPage']);
        $t->same([1, 2], $merged->inventory()['pageNumbers']);
        $t->same(true, $merged->inventory()['hasMorePages']);
        $t->same(3, $merged->inventory()['nextPage']);
        $t->same(['Alpha'], array_column($merged->page(1)->text()['lines'], 'text'));
        $t->same(['Beta'], array_column($merged->page(2)->text()['lines'], 'text'));
        $t->same(null, $merged->page(3));
        $t->same($first->source(), $merged->source());
        $t->same(false, $merged->structure()['documentProfile']['complete']);
        $t->same([1, 2], $merged->structure()['documentProfile']['coveredPages']);
        $visibility = $merged->diagnostics()['textVisibility'] ?? [];
        $t->same([1, 2], array_column($visibility['pages'] ?? [], 'page'));
        $t->same(2, $visibility['visibleRuns'] ?? null);
    },
    'deduplicates copied extraction receipts while retaining exact page visibility evidence' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf, $singlePageFacts): void {
        $whole = (new NativePdfFactsProvider())->extract($mergeFactsPdf());
        $ranges = [
            $singlePageFacts($whole, 1),
            $singlePageFacts($whole, 2),
            $singlePageFacts($whole, 3),
        ];
        $merged = (new PdfDocumentFactsMerger())->mergeComplete($ranges);
        $visibility = $merged->diagnostics()['textVisibility'] ?? [];

        $t->same([1, 2, 3], array_column($visibility['pages'] ?? [], 'page'));
        $t->same(3, $visibility['visibleRuns'] ?? null, 'A copied three-page receipt must be counted once, not once per checkpoint.');
        $t->same(0, $visibility['laterPaintRiskCount'] ?? null);
        $t->same(hash('sha256', '[]'), $visibility['laterPaintRisksDigest'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },
    'merges page risk inventories once and keeps their source page binding' => static function (
        TestRunner $t
    ) use ($mergeRiskFactsPdf): void {
        $pdf = $mergeRiskFactsPdf();
        $provider = new NativePdfFactsProvider();
        $first = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1]);
        $second = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1]);
        $visibility = (new PdfDocumentFactsMerger())
            ->mergeComplete([$first, $second])
            ->diagnostics()['textVisibility'] ?? [];

        $t->same([1, 2], array_column($visibility['pages'] ?? [], 'page'));
        $t->same(false, $visibility['complete'] ?? null);
        $t->same(1, $visibility['laterPaintRiskCount'] ?? null);
        $t->same(1, $visibility['laterPaintRiskRecordedCount'] ?? null);
        $t->same([1], array_column($visibility['laterPaintRisks'] ?? [], 'page'));
        $t->same(
            hash('sha256', json_encode($visibility['laterPaintRisks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
            $visibility['laterPaintRisksDigest'] ?? null
        );
    },
    'fails closed when an input receipt does not cover its own facts page' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf, $singlePageFacts): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $whole = $provider->extract($pdf);
        $pageOne = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1]);
        $pageTwo = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1]);
        $misbound = $pageOne->toArray();
        $misbound['diagnostics']['textVisibility'] = $pageTwo->diagnostics()['textVisibility'];
        $merged = (new PdfDocumentFactsMerger())->mergeRange([
            PdfDocumentFacts::fromArray($misbound),
            $singlePageFacts($whole, 2),
        ], 1, 2);
        $visibility = $merged->diagnostics()['textVisibility'] ?? [];

        $t->same(false, $visibility['complete'] ?? null);
        $t->same('visibility-evidence-merge-invalid', $visibility['policy'] ?? null);
        $t->same(
            'range-visibility-merge-owned-page-summary-missing',
            $visibility['rangeMergeFailureReason'] ?? null
        );
    },
    'fails closed instead of repairing a malformed parent visibility aggregate' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $first = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1]);
        $secondData = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1])->toArray();
        $secondData['diagnostics']['textVisibility']['visibleRuns']++;
        $visibility = (new PdfDocumentFactsMerger())->mergeRange([
            $first,
            PdfDocumentFacts::fromArray($secondData),
        ], 1, 2)->diagnostics()['textVisibility'] ?? [];

        $t->same(false, $visibility['complete'] ?? null);
        $t->same('visibility-evidence-merge-invalid', $visibility['policy'] ?? null);
        $t->same('range-visibility-merge-input-invalid', $visibility['rangeMergeFailureReason'] ?? null);
    },
    'distinguishes absent visibility evidence from empty or partially missing receipts' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $firstData = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1])->toArray();
        $secondData = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1])->toArray();

        $emptyFirst = $firstData;
        $emptySecond = $secondData;
        $emptyFirst['diagnostics']['textVisibility'] = [];
        $emptySecond['diagnostics']['textVisibility'] = [];
        $emptyVisibility = (new PdfDocumentFactsMerger())->mergeRange([
            PdfDocumentFacts::fromArray($emptyFirst),
            PdfDocumentFacts::fromArray($emptySecond),
        ], 1, 2)->diagnostics()['textVisibility'] ?? [];
        $t->same('visibility-evidence-merge-invalid', $emptyVisibility['policy'] ?? null);
        $t->same(
            'range-visibility-merge-input-invalid',
            $emptyVisibility['rangeMergeFailureReason'] ?? null
        );

        unset($firstData['diagnostics']['textVisibility']);
        $mixedVisibility = (new PdfDocumentFactsMerger())->mergeRange([
            PdfDocumentFacts::fromArray($firstData),
            PdfDocumentFacts::fromArray($secondData),
        ], 1, 2)->diagnostics()['textVisibility'] ?? [];
        $t->same('visibility-evidence-merge-invalid', $mixedVisibility['policy'] ?? null);
        $t->same(
            'range-visibility-merge-input-missing',
            $mixedVisibility['rangeMergeFailureReason'] ?? null
        );

        unset($secondData['diagnostics']['textVisibility']);
        $absentDiagnostics = (new PdfDocumentFactsMerger())->mergeRange([
            PdfDocumentFacts::fromArray($firstData),
            PdfDocumentFacts::fromArray($secondData),
        ], 1, 2)->diagnostics();
        $t->same(false, array_key_exists('textVisibility', $absentDiagnostics));
    },
    'rejects missing overlapping and cross-source page facts' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $first = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 2]);
        $second = $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 2]);
        $other = $provider->extract(str_replace('(Gamma)', '(Delta)', $pdf), ['startPage' => 3, 'maxPages' => 1]);
        $merger = new PdfDocumentFactsMerger();

        $t->throws(RuntimeException::class, static fn () => $merger->mergeComplete([$first]));
        $t->throws(RuntimeException::class, static fn () => $merger->mergeComplete([$first, $second]));
        $t->throws(RuntimeException::class, static fn () => $merger->mergeComplete([$first, $other]));
        $t->throws(RuntimeException::class, static fn () => $merger->mergeRange([$first], 0, 1));
        $t->throws(RuntimeException::class, static fn () => $merger->mergeRange([$first], 1, 4));
    },
];
