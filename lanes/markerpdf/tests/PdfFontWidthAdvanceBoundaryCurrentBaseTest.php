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

$fontWidthQuoteAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fquote 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj 24 6 (A B) " ET';
    $widths = implode(' ', array_fill(0, 35, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fquote 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+QuoteAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 66 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthRelativeTdAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftd 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj '
        . '1 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftd 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RelativeTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthScaledTdAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fscale 12 Tf '
        . '0.5 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj '
        . 'T* 0.5 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fscale 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ScaledTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthUnresolvedSlotBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fsparse 12 Tf 1 0 0 1 72 720 Tm <43> Tj 1 0 0 1 87 720 Tm <44> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsparse 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+SparseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 99 0 R 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontVerticalWidthAdvanceBoundaryCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/WMode 1 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<0001> <0004> 40\n"
        . "<0005> <000A> 50\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "10 beginbfchar\n"
        . "<0001> <0056>\n"
        . "<0002> <0065>\n"
        . "<0003> <0072>\n"
        . "<0004> <0074>\n"
        . "<0005> <0049>\n"
        . "<0006> <006D>\n"
        . "<0007> <0070>\n"
        . "<0008> <006F>\n"
        . "<0009> <0072>\n"
        . "<000A> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 0 -24 Td <00050006000700080009000A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalAdvanceCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /VerticalAdvanceCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalAdvanceCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 43 -500 500 880 50 55 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
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
    'applies quote operator spacing before styled font advance bboxes on current base' => static function (TestRunner $t) use ($fontWidthQuoteAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthQuoteAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $blocks = $pages[0]['blocks'] ?? [];
        $quotedSpan = $blocks[1]['lines'][0]['spans'][0] ?? [];

        $t->same(['Lead', 'A B'], $extractor->extractTextLines($pdf));
        $t->same(['Lead', 'A B'], $extractor->extractTextRuns($pdf));
        $t->same("Lead\nA B", $plainText);
        $t->same("Lead\nA B\n", $extractor->naiveGetText($pdf));
        $t->same('A B', $quotedSpan['text'] ?? null);
        $t->same([0.0, 0.0, 72.0, 12.0], $quotedSpan['bbox'] ?? null);
        $t->same(12.0, $quotedSpan['font_size'] ?? null);
        $t->true(!str_contains($plainText, 'QuoteAdvance'));
        $t->true(!str_contains($plainText, 'Fquote'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses font-width current text advance before relative Td word-gap decisions on current base' => static function (TestRunner $t) use ($fontWidthRelativeTdAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRelativeTdAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['ABCD', 'AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nAB CD", $plainText);
        $t->same("ABCD\nAB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD' . "\n" . 'AB CD'));
        $t->true(str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'RelativeTdAdvance'));
        $t->true(!str_contains($plainText, 'Ftd'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses scaled text matrix advance before relative Td word-gap decisions on current base' => static function (TestRunner $t) use ($fontWidthScaledTdAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthScaledTdAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['ABCD', 'AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nAB CD", $plainText);
        $t->same("ABCD\nAB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD' . "\n" . 'AB CD'));
        $t->true(str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'ScaledTdAdvance'));
        $t->true(!str_contains($plainText, 'Fscale'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'preserves unresolved simple-font width slots before current advance gap decisions' => static function (TestRunner $t) use ($fontWidthUnresolvedSlotBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthUnresolvedSlotBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['CD'], $extractor->extractTextLines($pdf));
        $t->same(['C', 'D'], $extractor->extractTextRuns($pdf));
        $t->same('CD', $plainText);
        $t->same("CD\n", $extractor->naiveGetText($pdf));
        $t->same(['C', 'D'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 9.0, 12.0], [9.0, 0.0, 12.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 12.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'C D'));
        $t->true(!str_contains($plainText, 'SparseAdvance'));
        $t->true(!str_contains($plainText, 'Fsparse'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses vertical CIDFont W2 advances for native styled span bboxes on current base' => static function (TestRunner $t) use ($fontVerticalWidthAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontVerticalWidthAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['VertImport'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'Import'], $extractor->extractTextRuns($pdf));
        $t->same('VertImport', $plainText);
        $t->same("VertImport\n", $extractor->naiveGetText($pdf));
        $t->same(['Vert', 'Import'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 18.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 24.0], $line['bbox'] ?? null);
        $t->same('VerticalAdvanceCID_', $spans[0]['font'] ?? null);
        $t->true(!str_contains($plainText, 'Vert Import'));
        $t->true(!str_contains($plainText, 'Fv'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
