<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceShortWidthsBoundaryCurrentBasePdf = static function (bool $completeWidths): string {
    $widthArray = $completeWidths ? '[1000 1000]' : '[1000]';
    $content = 'BT /Fshort 12 Tf '
        . '1 0 0 1 72 720 Tm (A) Tj '
        . '1 0 0 1 94 720 Tm (B) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fshort 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FirstChar 65 /LastChar 66 /Widths {$widthArray} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'rejects underdeclared simple-font Widths arrays before text-advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceShortWidthsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceShortWidthsBoundaryCurrentBasePdf(false);
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $firstBbox = $spans[0]['bbox'] ?? [];
        $secondBbox = $spans[1]['bbox'] ?? [];

        $t->same(['A B'], $extractor->extractTextLines($pdf));
        $t->same(['A', 'B'], $extractor->extractTextRuns($pdf));
        $t->same('A B', $plainText);
        $t->same("A B\n", $extractor->naiveGetText($pdf));
        $t->same(['A', 'B'], array_column($spans, 'text'));
        $t->same('Helvetica_', $spans[0]['font'] ?? null);
        $t->same('Helvetica_', $spans[1]['font'] ?? null);
        $t->true(isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 8.004) < 0.001, 'Expected incomplete Widths array to fall back to Base14 Helvetica A width.');
        $t->true(isset($secondBbox[0], $firstBbox[2]) && ((float) $secondBbox[0]) - ((float) $firstBbox[2]) >= 12.0, 'Expected Base14 fallback to preserve positioned word gap.');
        $t->true(isset($secondBbox[2], $secondBbox[0]) && abs((((float) $secondBbox[2]) - ((float) $secondBbox[0])) - 8.004) < 0.001, 'Expected Base14 Helvetica B width after fallback.');
        $t->true(!str_contains($plainText, 'AB'));
        $t->true(!str_contains($plainText, 'Fshort'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'still honors complete simple-font Widths arrays before text-advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceShortWidthsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceShortWidthsBoundaryCurrentBasePdf(true);
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $firstBbox = $spans[0]['bbox'] ?? [];
        $secondBbox = $spans[1]['bbox'] ?? [];

        $t->same(['AB'], $extractor->extractTextLines($pdf));
        $t->same(['A', 'B'], $extractor->extractTextRuns($pdf));
        $t->same('AB', $plainText);
        $t->same("AB\n", $extractor->naiveGetText($pdf));
        $t->same(['A', 'B'], array_column($spans, 'text'));
        $t->true(isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 12.0) < 0.001, 'Expected complete Widths array to stay authoritative for A.');
        $t->true(isset($secondBbox[0]) && abs(((float) $secondBbox[0]) - 12.0) < 0.001, 'Expected complete Widths array to suppress the positioned word gap.');
        $t->true(isset($secondBbox[2], $secondBbox[0]) && abs((((float) $secondBbox[2]) - ((float) $secondBbox[0])) - 12.0) < 0.001, 'Expected complete Widths array to stay authoritative for B.');
        $t->true(!str_contains($plainText, 'A B'));
        $t->true(!str_contains($plainText, 'Fshort'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
