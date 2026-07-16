<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
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

return [
    'merges durable page ranges into one contiguous document facts snapshot' => static function (
        TestRunner $t
    ) use ($mergeFactsPdf): void {
        $pdf = $mergeFactsPdf();
        $provider = new NativePdfFactsProvider();
        $first = $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 2]);
        $second = $provider->extract($pdf, ['startPage' => 3, 'maxPages' => 1]);
        $merged = (new PdfDocumentFactsMerger())->mergeComplete([$first, $second]);

        $t->same([1, 2, 3], $merged->inventory()['pageNumbers']);
        $t->same(false, $merged->inventory()['hasMorePages']);
        $t->same(['Alpha'], array_column($merged->page(1)->text()['lines'], 'text'));
        $t->same(['Gamma'], array_column($merged->page(3)->text()['lines'], 'text'));
        $t->same($first->source(), $merged->source());
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
