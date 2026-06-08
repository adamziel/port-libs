<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceTzOperandCountBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftzcount 12 Tf '
        . '100 Tz 1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj '
        . 'T* 100 50 Tz 1 0 0 1 72 704 Tm <4546> Tj 1 0 0 1 96 704 Tm <4748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftzcount 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TzOperandCountAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H] >>\nendobj\n%%EOF";
};

return [
    'rejects malformed extra-operand Tz before font-width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceTzOperandCountBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceTzOperandCountBoundaryCurrentBasePdf();
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
        $t->same(['EF', 'GH'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same([0.0, 0.0, 48.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'EF GH'));
        $t->true(!str_contains($plainText, 'TzOperandCountAdvance'));
        $t->true(!str_contains($plainText, 'Ftzcount'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
