<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceComposedAdjustmentBoundaryCurrentBasePdf = static function (): string {
    $widths = implode(' ', array_fill(0, 35, '1000'));
    $content = 'BT /Fadj 2000 Tf '
        . '1 0 0 1 72 720 Tm [(A) 100000 (B)] TJ '
        . '1 0 0 1 4000 720 Tm (C) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fadj 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ComposedTjAdjustment /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 66 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'bounds composed TJ adjustment advances before current word-gap and styled bbox decisions on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceComposedAdjustmentBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceComposedAdjustmentBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $spanBboxes = array_column($spans, 'bbox');
        $allBboxNumbersFinite = true;
        $maxBboxMagnitude = 0.0;
        foreach ([...$spanBboxes, $line['bbox'] ?? []] as $bbox) {
            foreach ($bbox as $number) {
                $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
                $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
            }
        }

        $t->same(['ABC'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'C'], $extractor->extractTextRuns($pdf));
        $t->same('ABC', $plainText);
        $t->same("ABC\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'C'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 4000.0, 2000.0], [4000.0, 0.0, 6000.0, 2000.0]], $spanBboxes);
        $t->same([0.0, 0.0, 6000.0, 2000.0], $line['bbox'] ?? null);
        $t->true($allBboxNumbersFinite);
        $t->true($maxBboxMagnitude < 10000.0);
        $t->true(!str_contains($plainText, 'AB C'));
        $t->true(!str_contains($plainText, 'ComposedTjAdjustment'));
        $t->true(!str_contains($plainText, 'Fadj'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
