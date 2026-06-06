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

$fontWidthTerminalCharacterSpacingAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftc 12 Tf 6 Tc '
        . '1 0 0 1 72 720 Tm <4142> Tj 42 0 Td <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TerminalTcAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthTerminalWordSpacingAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftw 12 Tf 24 Tw '
        . '1 0 0 1 72 720 Tm (AB ) Tj 1 0 0 1 120 720 Tm (CD) Tj '
        . 'T* 1 0 0 1 72 704 Tm (AB ) Tj 1 0 0 1 114 704 Tm (CD) Tj ET';
    $widths = implode(' ', array_fill(0, 37, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TerminalTwAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$widths}] >>\nendobj\n"
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

$fontWidthRelativeTdStyledGapBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftdgap 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj 48 0 Td <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4142> Tj 24 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftdgap 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RelativeTdStyledGap /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
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

$fontWidthTextMatrixVerticalScaleBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fmatrix 12 Tf '
        . '1 0 0 0.5 72 720 Tm <4142> Tj '
        . '1 0 0 2 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fmatrix 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextMatrixScale /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthNegativeTextMatrixBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fneg 12 Tf -1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 100 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fneg 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthTextRiseBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Frise 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 6 Ts <4344> Tj -3 Ts <4546> Tj 0 Ts <4748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frise 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextRiseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H] >>\nendobj\n%%EOF";
};

$fontWidthTjBacktrackBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftj 12 Tf 1 0 0 1 72 720 Tm [(AB) 3000 (CD)] TJ ET';
    $widths = implode(' ', array_fill(0, 36, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftj 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TjBacktrackAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 67 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthTjDrawnExtentTmBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /FtjExtent 12 Tf '
        . '1 0 0 1 72 720 Tm [(AB) 3000 (CD)] TJ 1 0 0 1 100 720 Tm (EF) Tj '
        . 'T* 1 0 0 1 72 704 Tm [(AB) 3000 (CD)] TJ 1 0 0 1 116 704 Tm (EF) Tj ET';
    $widths = implode(' ', array_fill(0, 39, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /FtjExtent 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TjDrawnExtentAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 70 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthAbsoluteTmStyledGapBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftm 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4142> Tj 1 0 0 1 108 704 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftm 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+AbsoluteTmStyledGap /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthCidAbsoluteTmStyledGapBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<0001> <0041>\n"
        . "<0002> <0042>\n"
        . "<0003> <0043>\n"
        . "<0004> <0044>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <00010002> Tj 1 0 0 1 108 720 Tm <00030004> Tj '
        . 'T* 1 0 0 1 72 704 Tm <00010002> Tj 1 0 0 1 96 704 Tm <00030004> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDAbsoluteTmStyledGap /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDAbsoluteTmStyledGap /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [1 4 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthNegativeTcBacktrackBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /FnegTc 12 Tf -30 Tc 1 0 0 1 72 720 Tm <4142> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /FnegTc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeTcBacktrack /Encoding 6 0 R /FirstChar 65 /LastChar 66 /Widths [1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B] >>\nendobj\n%%EOF";
};

$fontWidthNegativeHorizontalScaleTdBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Ftz 12 Tf -100 Tz '
        . '1 0 0 1 72 720 Tm <4142> Tj 0 0 Td <4344> Tj '
        . 'T* 100 Tz 1 0 0 1 72 704 Tm <4142> Tj 24 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftz 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeTzAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
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

$fontWidthExactGenerationArrayBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fgen 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fgen 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+GenerationWidths /Encoding 6 0 R /FirstChar 65 /LastChar 73 /Widths 20 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /W /i /d /e /B /l /o /c /k] >>\nendobj\n"
        . "20 0 obj\n[1000 1000 1000 1000 1000 1000 1000 1000 1000]\nendobj\n"
        . "20 1 obj\n[250 250 250 250 250 250 250 250 250]\nendobj\n%%EOF";
};

$fontWidthExactGenerationDescriptorBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fdesc 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdesc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DescriptorGeneration /Encoding /WinAnsiEncoding /FontDescriptor 7 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /FontDescriptor /FontName /ReferencedDescriptor /Flags 4 /MissingWidth 250 >>\nendobj\n"
        . "7 1 obj\n<< /Type /FontDescriptor /FontName /UnreferencedDescriptor /Flags 32 /MissingWidth 1000 >>\nendobj\n%%EOF";
};

$fontWidthExactGenerationDescendantBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<0001> <0057>\n"
        . "<0002> <0069>\n"
        . "<0003> <0064>\n"
        . "<0004> <0065>\n"
        . "<0005> <0054>\n"
        . "<0006> <0068>\n"
        . "<0007> <0069>\n"
        . "<0008> <006E>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fcidgen 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 118 720 Tm <0005000600070008> Tj '
        . 'T* 1 0 0 1 72 704 Tm <0005000600070008> Tj '
        . '1 0 0 1 96 704 Tm <0001000200030004> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidgen 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DescendantGeneration /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ReferencedDescendant /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 /W [1 4 1000 5 8 250] >>\nendobj\n"
        . "4 1 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /StaleDescendant /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [1 4 250 5 8 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthLastCharBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Flast 12 Tf '
        . '1 0 0 1 72 720 Tm <43> Tj 1 0 0 1 86 720 Tm <44> Tj '
        . 'T* 1 0 0 1 72 704 Tm <43> Tj 1 0 0 1 100 704 Tm <44> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Flast 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+LastCharAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 66 /Widths [1000 1000 100] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthMalformedRangeBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fbad 12 Tf '
        . '1 0 0 1 72 720 Tm <4344> Tj 1 0 0 1 88 720 Tm <4546> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4344> Tj 1 0 0 1 100 704 Tm <4546> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fbad 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+MalformedRangeAdvance /Encoding 6 0 R /FirstChar 67.75 /LastChar 68.25 /Widths [100 100 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [67 /C /D /E /F] >>\nendobj\n%%EOF";
};

$fontWidthNonFiniteWidthBoundaryCurrentBasePdf = static function (): string {
    $hugeWidth = str_repeat('9', 400);
    $content = 'BT /Finf 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 48 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Finf 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NonFiniteAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 {$hugeWidth} 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthHugeFiniteWidthBoundaryCurrentBasePdf = static function (): string {
    $hugeWidth = '1' . str_repeat('0', 308);
    $content = 'BT /Fhuge 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 48 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhuge 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeFiniteAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [{$hugeWidth} {$hugeWidth} 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthNegativeWidthMetricBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Fnegw 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fnegw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeMetricAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [-1000 -1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthNonFiniteTjAdjustmentBoundaryCurrentBasePdf = static function (): string {
    $hugeAdjustment = str_repeat('9', 400);
    $content = 'BT /Ftjinf 12 Tf 1 0 0 1 72 720 Tm [(AB) ' . $hugeAdjustment . ' (CD)] TJ '
        . '1 0 0 1 120 720 Tm <4546> Tj ET';
    $widths = implode(' ', array_fill(0, 39, '1000'));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftjinf 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NonFiniteTjAdjustment /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 70 /Widths [{$widths}] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthRotatedTextMatrixBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Frot 12 Tf '
        . '0 1 -1 0 72 720 Tm <4142> Tj '
        . '0.6 0.8 0 1 96 720 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frot 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RotatedAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthRotatedTdAdvanceBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Frotd 12 Tf '
        . '0 1 -1 0 72 720 Tm <4142> Tj 48 0 Td <4344> Tj '
        . 'T* 0.6 0.8 0 1 72 704 Tm <4142> Tj 40 0 Td <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frotd 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RotatedTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";
};

$fontWidthTextObjectResetBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /Freset 12 Tf 0.5 0 0 1 72 720 Tm <4142> Tj ET '
        . 'BT /Freset 12 Tf 72 704 Td [(CD) -1000 (EF)] TJ ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Freset 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextObjectResetAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 70 /Widths [1000 1000 1000 1000 1000 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F] >>\nendobj\n%%EOF";
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

$fontVerticalTjBacktrackBoundaryCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/WMode 1 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0001> <0004> 40\n"
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
        . "4 beginbfchar\n"
        . "<0001> <0056>\n"
        . "<0002> <0065>\n"
        . "<0003> <0072>\n"
        . "<0004> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm [<00010002> -3000 <00030004>] TJ ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalBacktrackCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /VerticalBacktrackCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalBacktrackCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 43 -500 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$fontWidthIndirectCidArrayAdvanceBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "17 beginbfchar\n"
        . "<0001> <0057>\n"
        . "<0002> <0069>\n"
        . "<0003> <0064>\n"
        . "<0004> <0065>\n"
        . "<0005> <0042>\n"
        . "<0006> <006C>\n"
        . "<0007> <006F>\n"
        . "<0008> <0063>\n"
        . "<0009> <006B>\n"
        . "<0014> <0054>\n"
        . "<0015> <0068>\n"
        . "<0016> <0069>\n"
        . "<0017> <006E>\n"
        . "<0018> <0054>\n"
        . "<0019> <0065>\n"
        . "<001A> <0078>\n"
        . "<001B> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 118 720 Tm <00050006000700080009> Tj '
        . 'T* 1 0 0 1 72 704 Tm <0014001500160017> Tj '
        . '1 0 0 1 96 704 Tm <00180019001A001B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectCidWidthArray /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectCidWidthArray /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 6 0 R 20 27 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n[1000 1000 1000 1000 1000 1000 1000 1000 1000]\nendobj\n%%EOF";
};

$fontWidthIndirectW2ArrayAdvanceBoundaryCurrentBasePdf = static function (): string {
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
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectVerticalArrayCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /IndirectVerticalArrayCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectVerticalArrayCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 7 0 R 50 55 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n[-500 500 880 -500 500 880 -500 500 880 -500 500 880]\nendobj\n%%EOF";
};

$fontWidthNegativeFirstCidArrayAdvanceBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<0000> <0057>\n"
        . "<0001> <0069>\n"
        . "<0002> <0064>\n"
        . "<0003> <0065>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <00000001> Tj 1 0 0 1 100 720 Tm <00020003> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NegativeFirstCid /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NegativeFirstCid /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [-1 [250 250 250 250]] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthNegativeFirstW2ArrayAdvanceBoundaryCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/WMode 1 def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0000> <0003> 0\n"
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
        . "4 beginbfchar\n"
        . "<0000> <0056>\n"
        . "<0001> <0065>\n"
        . "<0002> <0072>\n"
        . "<0003> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <00000001> Tj 0 -24 Td <00020003> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NegativeVerticalFirstCid /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /NegativeVerticalFirstCid-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NegativeVerticalFirstCid /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [-1 [-250 500 880 -250 500 880 -250 500 880 -250 500 880]] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$fontWidthType3FontMatrixWidthsBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<41> <0041>\n"
        . "<42> <0042>\n"
        . "<43> <0043>\n"
        . "<44> <0044>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Ft3 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 96 720 Tm <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4142> Tj '
        . '1 0 0 1 108 704 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MatrixWidths /BaseFont /T3MatrixWidths "
        . "/FontBBox [0 0 500 700] /FontMatrix [0.002 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 68 /Widths [500 500 500 500] "
        . "/Encoding /WinAnsiEncoding /CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$fontWidthType3FontMatrixVectorBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<41> <0041>\n"
        . "<42> <0042>\n"
        . "<43> <0043>\n"
        . "<44> <0044>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Ft3v 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 99 720 Tm <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4142> Tj '
        . '1 0 0 1 108 704 Tm <4344> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3v 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MatrixVector /BaseFont /T3MatrixVector "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.0006 0.0008 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] "
        . "/Encoding /WinAnsiEncoding /CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$fontWidthType3CharProcFontMatrixVectorBoundaryCurrentBasePdf = static function (): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide charproc matrix-vector text leak) Tj ET\n";
    $thinCharProc = "250 0 d0\nBT /Fghost 9 Tf (thin charproc matrix-vector text leak) Tj ET\n";
    $content = 'BT /Ft3cv 12 Tf '
        . '1 0 0 1 72 720 Tm <4142> Tj '
        . '1 0 0 1 100 720 Tm <4344> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4546> Tj '
        . '1 0 0 1 90 704 Tm <4748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3cv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcMatrixVector /BaseFont /T3CharProcMatrixVector "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.0006 0.0008 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 72 /Widths [100 100 100 100 100 100 100 100] "
        . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /E 4 0 R /F 4 0 R /G 4 0 R /H 4 0 R >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$fontWidthType3MissingWidthFontMatrixBoundaryCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<43> <0043>\n"
        . "<44> <0044>\n"
        . "<45> <0045>\n"
        . "<46> <0046>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Ft3miss 12 Tf '
        . '1 0 0 1 72 720 Tm <4344> Tj '
        . '1 0 0 1 96 720 Tm <4546> Tj '
        . 'T* 1 0 0 1 72 704 Tm <4344> Tj '
        . '1 0 0 1 108 704 Tm <4546> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3miss 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MissingMatrix /BaseFont /T3MissingMatrix "
        . "/FontBBox [0 0 500 700] /FontMatrix [0.002 0 0 0.001 0 0] "
        . "/FirstChar 65 /LastChar 66 /Widths [500 500] "
        . "/Encoding /WinAnsiEncoding /CharProcs << >> /ToUnicode 6 0 R /FontDescriptor 7 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /FontDescriptor /FontName /T3MissingMatrix /Flags 4 /MissingWidth 500 >>\nendobj\n%%EOF";
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
    'keeps terminal character spacing in current text advance before relative Td gaps on current base' => static function (TestRunner $t) use ($fontWidthTerminalCharacterSpacingAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTerminalCharacterSpacingAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['ABCD', 'AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nAB CD", $plainText);
        $t->same("ABCD\nAB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 30.0, 12.0], [30.0, 0.0, 60.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD' . "\n" . 'AB CD'));
        $t->true(str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'TerminalTcAdvance'));
        $t->true(!str_contains($plainText, 'Ftc'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'keeps terminal word spacing in current advance but out of styled drawn bboxes on current base' => static function (TestRunner $t) use ($fontWidthTerminalWordSpacingAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTerminalWordSpacingAdvanceBoundaryCurrentBasePdf();
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

        $t->same(['AB CD', 'AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB ', 'CD', 'AB ', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("AB CD\nAB CD", $plainText);
        $t->same("AB CD\nAB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB ', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 36.0, 12.0], [48.0, 0.0, 72.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 72.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['AB ', 'CD'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 36.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 60.0, 12.0], [60.0, 0.0, 84.0, 12.0]]);
        $t->true(!str_contains($plainText, 'TerminalTwAdvance'));
        $t->true(!str_contains($plainText, 'Ftw'));
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
    'preserves relative Td word-gap geometry in native styled bboxes on current base' => static function (TestRunner $t) use ($fontWidthRelativeTdStyledGapBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRelativeTdStyledGapBoundaryCurrentBasePdf();
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

        $t->same(['AB CD', 'ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("AB CD\nABCD", $plainText);
        $t->same("AB CD\nABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 72.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['AB', 'CD'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($plainText, 'AB CD' . "\n" . 'AB CD'));
        $t->true(!str_contains($plainText, 'RelativeTdStyledGap'));
        $t->true(!str_contains($plainText, 'Ftdgap'));
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
    'uses text matrix vertical scale before native styled span bboxes on current base' => static function (TestRunner $t) use ($fontWidthTextMatrixVerticalScaleBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTextMatrixVerticalScaleBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 6.0], [24.0, 0.0, 48.0, 24.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 24.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(!str_contains($plainText, 'TextMatrixScale'));
        $t->true(!str_contains($plainText, 'Fmatrix'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'keeps negative text matrix font widths from collapsing styled bboxes on current base' => static function (TestRunner $t) use ($fontWidthNegativeTextMatrixBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeTextMatrixBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 1.0, 12.0], [1.0, 0.0, 25.0, 12.0]]);
        $t->true(!str_contains($plainText, 'NegativeAdvance'));
        $t->true(!str_contains($plainText, 'Fneg'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'applies text rise before native styled font advance bboxes on current base' => static function (TestRunner $t) use ($fontWidthTextRiseBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTextRiseBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'EF', 'GH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD', 'EF', 'GH'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 6.0, 48.0, 18.0], [48.0, -3.0, 72.0, 9.0], [72.0, 0.0, 96.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, -3.0, 96.0, 18.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0], [48.0, 0.0, 72.0, 12.0], [72.0, 0.0, 96.0, 12.0]]);
        $t->true(!str_contains($plainText, 'TextRiseAdvance'));
        $t->true(!str_contains($plainText, 'Frise'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'keeps TJ backtracking adjustments from collapsing native styled font bboxes on current base' => static function (TestRunner $t) use ($fontWidthTjBacktrackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTjBacktrackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 36.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 36.0, 12.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 12.0, 12.0]]);
        $t->true(!str_contains($plainText, 'TjBacktrackAdvance'));
        $t->true(!str_contains($plainText, 'Ftj'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses TJ drawn glyph extent before same-line Tm word-gap decisions on current base' => static function (TestRunner $t) use ($fontWidthTjDrawnExtentTmBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTjDrawnExtentTmBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['ABCDEF', 'ABCD EF'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EF', 'ABCD', 'EF'], $extractor->extractTextRuns($pdf));
        $t->same("ABCDEF\nABCD EF", $plainText);
        $t->same("ABCDEF\nABCD EF\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EF'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 36.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, "ABCD EF\nABCD EF"));
        $t->true(str_contains($plainText, 'ABCD EF'));
        $t->true(!str_contains($plainText, 'TjDrawnExtentAdvance'));
        $t->true(!str_contains($plainText, 'FtjExtent'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'preserves absolute Tm word-gap geometry in native styled bboxes on current base' => static function (TestRunner $t) use ($fontWidthAbsoluteTmStyledGapBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthAbsoluteTmStyledGapBoundaryCurrentBasePdf();
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

        $t->same(['ABCD', 'AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nAB CD", $plainText);
        $t->same("ABCD\nAB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['AB', 'CD'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(array_column($secondLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($plainText, 'AB CD' . "\n" . 'AB CD'));
        $t->true(!str_contains($plainText, 'AbsoluteTmStyledGap'));
        $t->true(!str_contains($plainText, 'Ftm'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'preserves Type0 CIDFont absolute Tm word-gap geometry in native styled bboxes on current base' => static function (TestRunner $t) use ($fontWidthCidAbsoluteTmStyledGapBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthCidAbsoluteTmStyledGapBoundaryCurrentBasePdf();
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

        $t->same(['AB CD', 'ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("AB CD\nABCD", $plainText);
        $t->same("AB CD\nABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['AB', 'CD'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($plainText, 'AB CD' . "\n" . 'AB CD'));
        $t->true(!str_contains($plainText, 'CIDAbsoluteTmStyledGap'));
        $t->true(!str_contains($plainText, 'Fcid'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'keeps negative character spacing backtracking from collapsing direct Tj styled font bboxes on current base' => static function (TestRunner $t) use ($fontWidthNegativeTcBacktrackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeTcBacktrackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB'], $extractor->extractTextLines($pdf));
        $t->same(['AB'], $extractor->extractTextRuns($pdf));
        $t->same('AB', $plainText);
        $t->same("AB\n", $extractor->naiveGetText($pdf));
        $t->same(['AB'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 30.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 30.0, 12.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 6.0, 12.0]]);
        $t->true(!str_contains($plainText, 'NegativeTcBacktrack'));
        $t->true(!str_contains($plainText, 'FnegTc'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses negative horizontal scale drawn extent before relative Td gap decisions on current base' => static function (TestRunner $t) use ($fontWidthNegativeHorizontalScaleTdBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeHorizontalScaleTdBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = [];
        foreach (($pages[0]['blocks'] ?? []) as $block) {
            foreach (($block['lines'] ?? []) as $line) {
                $styledLines[] = $line;
            }
        }
        $firstLine = $styledLines[0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];
        $secondLine = $styledLines[1] ?? [];

        $t->same(['ABCD', 'ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nABCD", $plainText);
        $t->same("ABCD\nABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(array_column($firstSpans, 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]]);
        $t->true(!str_contains($plainText, 'NegativeTzAdvance'));
        $t->true(!str_contains($plainText, 'Ftz'));
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
    'resolves exact-generation simple-font Widths arrays before current advance gaps' => static function (TestRunner $t) use ($fontWidthExactGenerationArrayBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthExactGenerationArrayBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['WideBlock'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block'], $extractor->extractTextRuns($pdf));
        $t->same('WideBlock', $plainText);
        $t->same("WideBlock\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Block'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 108.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'GenerationWidths'));
        $t->true(!str_contains($plainText, 'Fgen'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'resolves exact-generation FontDescriptor MissingWidth before current advance gaps' => static function (TestRunner $t) use ($fontWidthExactGenerationDescriptorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthExactGenerationDescriptorBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 30.0, 12.0], $line['bbox'] ?? null);
        $t->same(['ReferencedDescriptor_symbolic', 'ReferencedDescriptor_symbolic'], array_column($spans, 'font'));
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!in_array('UnreferencedDescriptor_non_symbolic', array_column($spans, 'font'), true));
        $t->true(!str_contains($plainText, 'DescriptorGeneration'));
        $t->true(!str_contains($plainText, 'Fdesc'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'resolves exact-generation Type0 descendant CIDFont widths before current advance gaps' => static function (TestRunner $t) use ($fontWidthExactGenerationDescendantBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthExactGenerationDescendantBoundaryCurrentBasePdf();
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

        $t->same(['WideThin', 'Thin Wide'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Thin', 'Thin', 'Wide'], $extractor->extractTextRuns($pdf));
        $t->same("WideThin\nThin Wide", $plainText);
        $t->same("WideThin\nThin Wide\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Thin'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['Thin', 'Wide'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 72.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 72.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Wide Thin' . "\n" . 'ThinWide'));
        $t->true(!str_contains($plainText, 'StaleDescendant'));
        $t->true(!str_contains($plainText, 'Fcidgen'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'clips simple-font Widths entries to LastChar before positioned word gaps on current base' => static function (TestRunner $t) use ($fontWidthLastCharBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthLastCharBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['CD', 'C D'], $extractor->extractTextLines($pdf));
        $t->same(['C', 'D', 'C', 'D'], $extractor->extractTextRuns($pdf));
        $t->same("CD\nC D", $plainText);
        $t->same("CD\nC D\n", $extractor->naiveGetText($pdf));
        $t->same(['C', 'D'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'C D' . "\n" . 'C D'));
        $t->true(str_contains($plainText, 'C D'));
        $t->true(!str_contains($plainText, 'LastCharAdvance'));
        $t->true(!str_contains($plainText, 'Flast'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects malformed simple-font width range operands before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthMalformedRangeBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthMalformedRangeBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['CDEF', 'CD EF'], $extractor->extractTextLines($pdf));
        $t->same(['CD', 'EF', 'CD', 'EF'], $extractor->extractTextRuns($pdf));
        $t->same("CDEF\nCD EF", $plainText);
        $t->same("CDEF\nCD EF\n", $extractor->naiveGetText($pdf));
        $t->same(['CD', 'EF'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'CD EF' . "\n" . 'CD EF'));
        $t->true(str_contains($plainText, 'CD EF'));
        $t->true(!str_contains($plainText, 'MalformedRangeAdvance'));
        $t->true(!str_contains($plainText, 'Fbad'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects negative simple-font Widths entries before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthNegativeWidthMetricBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeWidthMetricBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $spanBboxes = array_column($spans, 'bbox');

        $t->same(['ABCD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD', $plainText);
        $t->same("ABCD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], $spanBboxes);
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true($spanBboxes !== [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]]);
        $t->true(!str_contains($plainText, 'NegativeMetricAdvance'));
        $t->true(!str_contains($plainText, 'Fnegw'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'ignores non-finite simple-font Widths entries before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthNonFiniteWidthBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNonFiniteWidthBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $spanBboxes = array_column($spans, 'bbox');
        $allBboxNumbersFinite = true;
        foreach ($spanBboxes as $bbox) {
            foreach ($bbox as $number) {
                $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
            }
        }

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]], $spanBboxes);
        $t->same([0.0, 0.0, 72.0, 12.0], $line['bbox'] ?? null);
        $t->true($allBboxNumbersFinite);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'NonFiniteAdvance'));
        $t->true(!str_contains($plainText, 'Finf'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects huge finite simple-font Widths entries before current advance overflow on current base' => static function (TestRunner $t) use ($fontWidthHugeFiniteWidthBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthHugeFiniteWidthBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $spanBboxes = array_column($spans, 'bbox');
        $allBboxNumbersFinite = true;
        $maxBboxMagnitude = 0.0;
        foreach (array_merge($spanBboxes, [$line['bbox'] ?? []]) as $bbox) {
            foreach ($bbox as $number) {
                $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
                $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
            }
        }

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]], $spanBboxes);
        $t->same([0.0, 0.0, 72.0, 12.0], $line['bbox'] ?? null);
        $t->true($allBboxNumbersFinite);
        $t->true($maxBboxMagnitude < 1000.0);
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'HugeFiniteAdvance'));
        $t->true(!str_contains($plainText, 'Fhuge'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'ignores non-finite TJ adjustment operands before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthNonFiniteTjAdjustmentBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNonFiniteTjAdjustmentBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];
        $spanBboxes = array_column($spans, 'bbox');
        $allBboxNumbersFinite = true;
        foreach ($spanBboxes as $bbox) {
            foreach ($bbox as $number) {
                $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
            }
        }

        $t->same(['ABCDEF'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EF'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEF', $plainText);
        $t->same("ABCDEF\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EF'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 72.0, 12.0]], $spanBboxes);
        $t->same([0.0, 0.0, 72.0, 12.0], $line['bbox'] ?? null);
        $t->true($allBboxNumbersFinite);
        $t->true(!str_contains($plainText, 'ABCD EF'));
        $t->true(!str_contains($plainText, 'NonFiniteTjAdjustment'));
        $t->true(!str_contains($plainText, 'Ftjinf'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses rotated text matrix horizontal vector for native styled font advance bboxes on current base' => static function (TestRunner $t) use ($fontWidthRotatedTextMatrixBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRotatedTextMatrixBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same('AB CD', $plainText);
        $t->same("AB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 1.0, 12.0], [1.0, 0.0, 15.4, 12.0]]);
        $t->true(!str_contains($plainText, 'RotatedAdvance'));
        $t->true(!str_contains($plainText, 'Frot'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses rotated text matrix vector advance before relative Td word gaps on current base' => static function (TestRunner $t) use ($fontWidthRotatedTdAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthRotatedTdAdvanceBoundaryCurrentBasePdf();
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

        $t->same(['AB CD', 'AB CD'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'AB', 'CD'], $extractor->extractTextRuns($pdf));
        $t->same("AB CD\nAB CD", $plainText);
        $t->same("AB CD\nAB CD\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 72.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['AB', 'CD'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [40.0, 0.0, 64.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 64.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, "ABCD\nABCD"));
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(array_column($secondLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($plainText, 'RotatedTdAdvance'));
        $t->true(!str_contains($plainText, 'Frotd'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'resets text matrix horizontal scale between text objects before styled TJ word gaps on current base' => static function (TestRunner $t) use ($fontWidthTextObjectResetBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthTextObjectResetBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $styledLines = [];
        foreach (($pages[0]['blocks'] ?? []) as $block) {
            foreach (($block['lines'] ?? []) as $line) {
                $styledLines[] = $line;
            }
        }
        $secondLine = $styledLines[1] ?? [];
        $secondSpans = $secondLine['spans'] ?? [];

        $t->same(['AB', 'CD EF'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD EF'], $extractor->extractTextRuns($pdf));
        $t->same("AB\nCD EF", $plainText);
        $t->same("AB\nCD EF\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD EF'], array_map(
            static fn (array $line): string => implode('', array_column($line['spans'] ?? [], 'text')),
            $styledLines
        ));
        $t->same(['CD EF'], array_column($secondSpans, 'text'));
        $t->same([[0.0, 0.0, 60.0, 12.0]], array_column($secondSpans, 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(array_column($secondSpans, 'bbox') !== [[0.0, 0.0, 30.0, 12.0]]);
        $t->true(!str_contains($plainText, 'CDEF'));
        $t->true(!str_contains($plainText, 'TextObjectResetAdvance'));
        $t->true(!str_contains($plainText, 'Freset'));
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
    'keeps vertical TJ backtracking adjustments from collapsing W2 styled bboxes on current base' => static function (TestRunner $t) use ($fontVerticalTjBacktrackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontVerticalTjBacktrackBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Ve rt'], $extractor->extractTextLines($pdf));
        $t->same(['Ve rt'], $extractor->extractTextRuns($pdf));
        $t->same('Ve rt', $plainText);
        $t->same("Ve rt\n", $extractor->naiveGetText($pdf));
        $t->same(['Ve rt'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 36.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 12.0, 36.0], $line['bbox'] ?? null);
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 12.0, 12.0]]);
        $t->true(!str_contains($plainText, 'Vert'));
        $t->true(!str_contains($plainText, 'VerticalBacktrackCID'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'resolves indirect CIDFont W width arrays before current text advance gaps on current base' => static function (TestRunner $t) use ($fontWidthIndirectCidArrayAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthIndirectCidArrayAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstLine = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $firstSpans = $firstLine['spans'] ?? [];

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->same(['Wide', 'Block'], array_column($firstSpans, 'text'));
        $t->same([[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]], array_column($firstSpans, 'bbox'));
        $t->same([0.0, 0.0, 108.0, 12.0], $firstLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, 'IndirectCidWidthArray'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'resolves indirect CIDFont W2 metric arrays before vertical styled span bboxes on current base' => static function (TestRunner $t) use ($fontWidthIndirectW2ArrayAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthIndirectW2ArrayAdvanceBoundaryCurrentBasePdf();
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
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 72.0]]);
        $t->true(!str_contains($plainText, 'Vert Import'));
        $t->true(!str_contains($plainText, 'IndirectVerticalArrayCID'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects negative first CID array-form W segments before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthNegativeFirstCidArrayAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeFirstCidArrayAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Wide'], $extractor->extractTextLines($pdf));
        $t->same(['Wi', 'de'], $extractor->extractTextRuns($pdf));
        $t->same('Wide', $plainText);
        $t->same("Wide\n", $extractor->naiveGetText($pdf));
        $t->same(['Wi', 'de'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Wi de'));
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 6.0, 12.0], [6.0, 0.0, 21.0, 12.0]]);
        $t->true(!str_contains($plainText, 'NegativeFirstCid'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'rejects negative first CID array-form W2 segments before vertical styled span bboxes on current base' => static function (TestRunner $t) use ($fontWidthNegativeFirstW2ArrayAdvanceBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthNegativeFirstW2ArrayAdvanceBoundaryCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['Vert'], $extractor->extractTextLines($pdf));
        $t->same(['Ve', 'rt'], $extractor->extractTextRuns($pdf));
        $t->same('Vert', $plainText);
        $t->same("Vert\n", $extractor->naiveGetText($pdf));
        $t->same(['Ve', 'rt'], array_column($spans, 'text'));
        $t->same([[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 24.0]], array_column($spans, 'bbox'));
        $t->same([0.0, 0.0, 24.0, 24.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Ve rt'));
        $t->true(array_column($spans, 'bbox') !== [[0.0, 0.0, 12.0, 6.0], [12.0, 0.0, 24.0, 15.0]]);
        $t->true(!str_contains($plainText, 'NegativeVerticalFirstCid'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'normalizes Type3 Widths through FontMatrix before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthType3FontMatrixWidthsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthType3FontMatrixWidthsBoundaryCurrentBasePdf();
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
        $t->true(!str_contains($plainText, 'T3MatrixWidths'));
        $t->true(!str_contains($plainText, 'Ft3'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses Type3 FontMatrix vector advance before current gaps on current base' => static function (TestRunner $t) use ($fontWidthType3FontMatrixVectorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthType3FontMatrixVectorBoundaryCurrentBasePdf();
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
        $t->true(array_column($firstSpans, 'bbox') !== [[0.0, 0.0, 14.4, 12.0], [14.4, 0.0, 28.8, 12.0]]);
        $t->true(!str_contains($plainText, 'T3MatrixVector'));
        $t->true(!str_contains($plainText, 'Ft3v'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses Type3 CharProc horizontal FontMatrix vector advance before current gaps on current base' => static function (TestRunner $t) use ($fontWidthType3CharProcFontMatrixVectorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthType3CharProcFontMatrixVectorBoundaryCurrentBasePdf();
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

        $t->same(['ABCD', 'EF GH'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'CD', 'EF', 'GH'], $extractor->extractTextRuns($pdf));
        $t->same("ABCD\nEF GH", $plainText);
        $t->same("ABCD\nEF GH\n", $extractor->naiveGetText($pdf));
        $t->same(['AB', 'CD'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same(['EF', 'GH'], array_column($secondLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 6.0, 12.0], [18.0, 0.0, 24.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 24.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'AB CD'));
        $t->true(str_contains($plainText, 'EF GH'));
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 14.4, 12.0], [28.0, 0.0, 42.4, 12.0]]);
        $t->true(!str_contains($plainText, 'matrix-vector text leak'));
        $t->true(!str_contains($plainText, 'T3CharProcMatrixVector'));
        $t->true(!str_contains($plainText, 'Ft3cv'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'normalizes Type3 descriptor MissingWidth through FontMatrix before current advance gaps on current base' => static function (TestRunner $t) use ($fontWidthType3MissingWidthFontMatrixBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontWidthType3MissingWidthFontMatrixBoundaryCurrentBasePdf();
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

        $t->same(['CDEF', 'CD EF'], $extractor->extractTextLines($pdf));
        $t->same(['CD', 'EF', 'CD', 'EF'], $extractor->extractTextRuns($pdf));
        $t->same("CDEF\nCD EF", $plainText);
        $t->same("CDEF\nCD EF\n", $extractor->naiveGetText($pdf));
        $t->same(['CD', 'EF'], array_column($firstLine['spans'] ?? [], 'text'));
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], array_column($firstLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 48.0, 12.0], $firstLine['bbox'] ?? null);
        $t->same([[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]], array_column($secondLine['spans'] ?? [], 'bbox'));
        $t->same([0.0, 0.0, 60.0, 12.0], $secondLine['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'CD EF' . "\n" . 'CD EF'));
        $t->true(array_column($firstLine['spans'] ?? [], 'bbox') !== [[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]]);
        $t->true(!str_contains($plainText, 'T3MissingMatrix'));
        $t->true(!str_contains($plainText, 'Ft3miss'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
