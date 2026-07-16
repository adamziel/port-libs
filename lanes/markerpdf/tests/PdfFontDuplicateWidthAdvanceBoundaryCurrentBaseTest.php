<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontDuplicateWidthAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fdup 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 96 720 Tm <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4546> Tj '
        . '1 0 0 1 96 704 Tm <4748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdup 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DuplicateWidthAdvance "
        . "/Encoding 6 0 R /FirstChar 67 /LastChar 70 /Widths [250 250 250 250] "
        . "/FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H] >>\nendobj\n%%EOF";
};

return [
    'uses current duplicate simple-font width range keys before advance gap decisions on current base' => static function (TestRunner $t) use ($fontDuplicateWidthAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontDuplicateWidthAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = [];
        foreach (($pages[0]['blocks'] ?? []) as $block) {
            foreach (($block['lines'] ?? []) as $line) {
                $styledLines[] = $line;
            }
        }
        $firstLine = $styledLines[0] ?? [];
        $secondLine = $styledLines[1] ?? [];

        $t->same(['ABCD', 'EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'EF', 'GH'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nEFGH", $plainText);
        $t->same("ABCD\nEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['EF', 'GH'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'EF GH'));
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]]);
        $t->true(!str_contains($plainText, 'DuplicateWidthAdvance'));
        $t->true(!str_contains($plainText, 'Fdup'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
