<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceIndirectScalarSlotBoundaryCurrentBasePdf = static function (string $helperBody): string {
    $widths = array_fill(0, 36, '1000');
    $widths[35] = '8 0 R';
    $widthArray = implode(' ', $widths);
    $content = 'BT /Fhelv 12 Tf 1 0 0 1 72 720 Tm (Ill) Tj 1 0 0 1 93 720 Tm (Word) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhelv 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FirstChar 73 /LastChar 108 /Widths [{$widthArray}] >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n{$helperBody}\nendobj\n%%EOF";
};

return [
    'honors valid indirect simple-font Widths scalar slots before current advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceIndirectScalarSlotBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceIndirectScalarSlotBoundaryCurrentBasePdf('1000');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

        $t->same(['IllWord'], $extractor->extractTextLines($pdf));
        $t->same(['Ill', 'Word'], $extractor->extractTextRuns($pdf));
        $t->same('IllWord', $plainText);
        $t->same("IllWord\n", $extractor->naiveGetText($pdf));
        $t->same(['Ill', 'Word'], array_column($spans, 'text'));
        $t->true(!str_contains($plainText, 'Ill Word'));
        $t->true(!str_contains($plainText, 'Helvetica'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed indirect simple-font Widths scalar slots before current advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceIndirectScalarSlotBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceIndirectScalarSlotBoundaryCurrentBasePdf('1000 /Tail');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $firstBbox = $spans[0]['bbox'] ?? [];
        $secondBbox = $spans[1]['bbox'] ?? [];

        $t->same(['Ill Word'], $extractor->extractTextLines($pdf));
        $t->same(['Ill', 'Word'], $extractor->extractTextRuns($pdf));
        $t->same('Ill Word', $plainText);
        $t->same("Ill Word\n", $extractor->naiveGetText($pdf));
        $t->same(['Ill', 'Word'], array_column($spans, 'text'));
        $t->true(isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 8.664) < 0.001, 'Expected Base14 Helvetica fallback for Ill.');
        $t->true(isset($secondBbox[0], $firstBbox[2]) && ((float) $secondBbox[0]) - ((float) $firstBbox[2]) > 12.0, 'Expected malformed helper to preserve positioned word gap.');
        $t->true(isset($secondBbox[2], $secondBbox[0]) && ((float) $secondBbox[2]) - ((float) $secondBbox[0]) > 28.0, 'Expected Base14 Helvetica fallback for Word.');
        $t->true(!str_contains($plainText, 'IllWord'));
        $t->true(!str_contains($plainText, 'Tail'));
        $t->true(!str_contains($plainText, 'Helvetica'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
