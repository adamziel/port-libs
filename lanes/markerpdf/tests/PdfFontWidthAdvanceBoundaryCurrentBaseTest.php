<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Favg 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 108 720 Tm <464748494A> Tj '
        . 'T* 1 0 0 1 72 704 Tm <464748> Tj '
        . '1 0 0 1 120 704 Tm <494A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Favg 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+AverageAdvance /Encoding 6 0 R /FirstChar 33 /LastChar 36 /Widths [1000 1000 1000 0] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /W /i /d /e 70 /B /l /o /c /k] >>\nendobj\n%%EOF";
};

return [
    'uses simple-font average positive width fallback for missing glyph advances on current base' => static function (TestRunner $t) use ($fontWidthAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['WideBlock', 'Blo ck'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Blo', 'ck'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nBlo ck", $plainText);
        $t->same("WideBlock\nBlo ck\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Block'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 108.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(str_contains($plainText, 'Blo ck'));
        $t->true(!str_contains($plainText, 'Favg'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
