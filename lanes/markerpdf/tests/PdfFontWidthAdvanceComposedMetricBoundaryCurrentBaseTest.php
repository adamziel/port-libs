<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthComposedMetricBoundaryCurrentBasePdf = static function (): array {
    $longToken = str_repeat('A', 100);
    $widths = array_fill(0, 83, '1000');
    $widths[65 - 32] = '100000';
    $widthArray = implode(' ', $widths);
    $content = 'BT /Fcompose 12 Tf '
        . '1 0 0 1 72 720 Tm (' . $longToken . ') Tj '
        . '1 0 0 1 720 720 Tm (Word) Tj ET';

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcompose 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ComposedMetricAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 114 /Widths [{$widthArray}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

    return [$pdf, $longToken];
};

return [
    'bounds composed simple-font width advances before WordPress paragraph gap decisions on current base' => static function (
        TestRunner $t
    ) use ($fontWidthComposedMetricBoundaryCurrentBasePdf): void {
        [$pdf, $longToken] = $fontWidthComposedMetricBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same([$longToken . ' Word'], $extractor->extractTextLines($pdf));
        $t->same([$longToken, 'Word'], $extractor->extractTextRuns($pdf));
        $t->same($longToken . ' Word', $plainText);
        $t->same($longToken . " Word\n", $extractor->naiveGetText($pdf));
        $t->same([$longToken, 'Word'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 600.0, 12.0], [648.0, 0.0, 696.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 696.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, $longToken . 'Word'));
        $t->true(!str_contains($plainText, 'ComposedMetricAdvance'));
        $t->true(!str_contains($plainText, 'Fcompose'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
