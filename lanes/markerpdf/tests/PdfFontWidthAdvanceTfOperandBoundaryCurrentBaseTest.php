<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceTfOperandBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftf 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '/Ftf 12 2 Tf <4344> Tj '
        . '1 0 0 1 114 720 Tm <4546> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftf 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TfOperandBoundary /Encoding 6 0 R /FirstChar 65 /LastChar 70 /Widths [1000 1000 1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F] >>\nendobj\n%%EOF";
};

return [
    'rejects malformed Tf trailing operands before font-width advance grouping on current base' => static function (
        TestRunner $t
    ) use ($fontWidthAdvanceTfOperandBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceTfOperandBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEF'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'EF'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEF', $plainText);
        $t->same("ABCDEF\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD', 'EF'], array_column($spans, 'text'));
        $t->same([12.0, 12.0, 12.0], array_column($spans, 'font_size'));
        $t->same(
            [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
            array_column($spans, 'bbox')
        );
        $t->same([0.0, 0.0, 72.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EF'));
        $t->true(!str_contains($plainText, 'TfOperandBoundary'));
        $t->true(!str_contains($plainText, 'Ftf'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
