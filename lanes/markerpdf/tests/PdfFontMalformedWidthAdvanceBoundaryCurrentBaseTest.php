<?php
declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontMalformedWidthAdvanceBoundaryCurrentBasePdf = static function (bool $malformedWidths, bool $trailingMalformedWidth = false): string {
    $widths = array_fill(0, 36, '1000');
    if ($malformedWidths) {
        $widths[35] = '/BadWidth';
    }
    if ($trailingMalformedWidth) {
        $widths[] = '/BadWidth';
    }
    $widthArray = implode(' ', $widths);
    $content = 'BT /Fhelv 12 Tf 1 0 0 1 72 720 Tm (Ill) Tj 1 0 0 1 93 720 Tm (Word) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhelv 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FirstChar 73 /LastChar 108 /Widths [{$widthArray}] >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'falls back to Base14 advances when simple-font Widths contain malformed rows on current base' => static function (TestRunner $t) use ($fontMalformedWidthAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontMalformedWidthAdvanceBoundaryCurrentBasePdf(true);
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
        $t->same('Helvetica_', $spans[0]['font'] ?? null);
        $t->same('Helvetica_', $spans[1]['font'] ?? null);
        $t->true(isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 8.664) < 0.001, 'Expected Base14 Helvetica width for Ill.');
        $t->true(isset($secondBbox[0], $firstBbox[2]) && ((float) $secondBbox[0]) - ((float) $firstBbox[2]) > 12.0, 'Expected positioned word gap after Base14 fallback.');
        $t->true(isset($secondBbox[2], $secondBbox[0]) && ((float) $secondBbox[2]) - ((float) $secondBbox[0]) > 28.0, 'Expected Base14 Helvetica width for Word.');
        $t->true(!str_contains($plainText, 'IllWord'));
        $t->true(!str_contains($plainText, 'BadWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'still honors complete simple-font Widths arrays for advance boundaries on current base' => static function (TestRunner $t) use ($fontMalformedWidthAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontMalformedWidthAdvanceBoundaryCurrentBasePdf(false);
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
        $firstBbox = $spans[0]['bbox'] ?? [];

        $t->same(['IllWord'], $extractor->extractTextLines($pdf));
        $t->same(['Ill', 'Word'], $extractor->extractTextRuns($pdf));
        $t->same('IllWord', $plainText);
        $t->same("IllWord\n", $extractor->naiveGetText($pdf));
        $t->same(['Ill', 'Word'], array_column($spans, 'text'));
        $t->true(isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 36.0) < 0.001, 'Expected complete explicit Widths array to stay authoritative.');
        $t->true(!str_contains($plainText, 'Ill Word'));
        $t->true(!str_contains($plainText, 'BadWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'clips malformed Widths tokens after LastChar without discarding declared advances on current base' => static function (TestRunner $t) use ($fontMalformedWidthAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontMalformedWidthAdvanceBoundaryCurrentBasePdf(false, true);
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
        $firstBbox = $spans[0]['bbox'] ?? [];

        $t->same(['IllWord'], $extractor->extractTextLines($pdf));
        $t->same(['Ill', 'Word'], $extractor->extractTextRuns($pdf));
        $t->same('IllWord', $plainText);
        $t->same(['Ill', 'Word'], array_column($spans, 'text'));
        $t->true(isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 36.0) < 0.001, 'Expected declared Widths entries through LastChar to stay authoritative.');
        $t->true(!str_contains($plainText, 'Ill Word'));
        $t->true(!str_contains($plainText, 'BadWidth'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
