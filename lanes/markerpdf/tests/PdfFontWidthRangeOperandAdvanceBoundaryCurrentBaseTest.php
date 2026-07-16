<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf = static function (
    string $rangeDictionary,
    string $extraObjects = ''
): string {
    $content = 'BT /Frange 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frange 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RangeOperandAdvance /Encoding 6 0 R {$rangeDictionary} /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

return [
    'rejects tailed simple-font FirstChar operands before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf('/FirstChar 64 65 /LastChar 68');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($plainText, 'RangeOperandAdvance'));
        $t->true(!str_contains($plainText, 'Frange'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed simple-font LastChar operands before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf('/FirstChar 65 /LastChar 68 69');
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($plainText, 'RangeOperandAdvance'));
        $t->true(!str_contains($plainText, 'Frange'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed indirect simple-font FirstChar helpers before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf(
            '/FirstChar 8 0 R /LastChar 68',
            "8 0 obj\n65 /Tail\nendobj\n"
        );
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'Tail'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects tailed indirect simple-font LastChar helpers before width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRangeOperandAdvanceBoundaryCurrentBasePdf(
            '/FirstChar 65 /LastChar 9 0 R',
            "9 0 obj\n68 69\nendobj\n"
        );
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
