<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Favg 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 108 720 Tm <464748494A> Tj '
    . 'T* 1 0 0 1 72 704 Tm <464748> Tj '
    . '1 0 0 1 120 704 Tm <494A> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Favg 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+AverageAdvance /Encoding 6 0 R /FirstChar 33 /LastChar 36 /Widths [1000 1000 1000 0] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /W /i /d /e 70 /B /l /o /c /k] >>\nendobj\n%%EOF";

$quoteContent = 'BT /Fquote 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj 24 6 (A B) " ET';
$quoteWidths = implode(' ', array_fill(0, 35, '1000'));
$quotePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fquote 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+QuoteAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 66 /Widths [{$quoteWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($quoteContent) . " >>\nstream\n{$quoteContent}\nendstream\nendobj\n%%EOF";

$terminalTcContent = 'BT /Ftc 12 Tf 6 Tc '
    . '1 0 0 1 72 720 Tm <4142> Tj 42 0 Td <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$terminalTcPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TerminalTcAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($terminalTcContent) . " >>\nstream\n{$terminalTcContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$terminalTwContent = 'BT /Ftw 12 Tf 24 Tw '
    . '1 0 0 1 72 720 Tm (AB ) Tj 1 0 0 1 120 720 Tm (CD) Tj '
    . 'T* 1 0 0 1 72 704 Tm (AB ) Tj 1 0 0 1 114 704 Tm (CD) Tj ET';
$terminalTwWidths = implode(' ', array_fill(0, 37, '1000'));
$terminalTwPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TerminalTwAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 68 /Widths [{$terminalTwWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($terminalTwContent) . " >>\nstream\n{$terminalTwContent}\nendstream\nendobj\n%%EOF";

$relativeTdContent = 'BT /Ftd 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj '
    . '1 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$relativeTdPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftd 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RelativeTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($relativeTdContent) . " >>\nstream\n{$relativeTdContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$relativeTdStyledGapContent = 'BT /Ftdgap 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj 48 0 Td <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4142> Tj 24 0 Td <4344> Tj ET';
$relativeTdStyledGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftdgap 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RelativeTdStyledGap /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($relativeTdStyledGapContent) . " >>\nstream\n{$relativeTdStyledGapContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$scaledTdContent = 'BT /Fscale 12 Tf '
    . '0.5 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj '
    . 'T* 0.5 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$scaledTdPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fscale 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ScaledTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($scaledTdContent) . " >>\nstream\n{$scaledTdContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$textMatrixScaleContent = 'BT /Fmatrix 12 Tf '
    . '1 0 0 0.5 72 720 Tm <4142> Tj '
    . '1 0 0 2 96 720 Tm <4344> Tj ET';
$textMatrixScalePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fmatrix 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextMatrixScale /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($textMatrixScaleContent) . " >>\nstream\n{$textMatrixScaleContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$negativeTextMatrixContent = 'BT /Fneg 12 Tf -1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 100 720 Tm <4344> Tj ET';
$negativeTextMatrixPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fneg 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeTextMatrixContent) . " >>\nstream\n{$negativeTextMatrixContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$textRiseContent = 'BT /Frise 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 6 Ts <4344> Tj -3 Ts <4546> Tj 0 Ts <4748> Tj ET';
$textRisePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frise 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextRiseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($textRiseContent) . " >>\nstream\n{$textRiseContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H] >>\nendobj\n%%EOF";

$tjBacktrackContent = 'BT /Ftj 12 Tf 1 0 0 1 72 720 Tm [(AB) 3000 (CD)] TJ ET';
$tjBacktrackWidths = implode(' ', array_fill(0, 36, '1000'));
$tjBacktrackPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftj 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TjBacktrackAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 67 /Widths [{$tjBacktrackWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($tjBacktrackContent) . " >>\nstream\n{$tjBacktrackContent}\nendstream\nendobj\n%%EOF";

$tjDrawnExtentContent = 'BT /FtjExtent 12 Tf '
    . '1 0 0 1 72 720 Tm [(AB) 3000 (CD)] TJ 1 0 0 1 100 720 Tm (EF) Tj '
    . 'T* 1 0 0 1 72 704 Tm [(AB) 3000 (CD)] TJ 1 0 0 1 116 704 Tm (EF) Tj ET';
$tjDrawnExtentWidths = implode(' ', array_fill(0, 39, '1000'));
$tjDrawnExtentPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /FtjExtent 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TjDrawnExtentAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 70 /Widths [{$tjDrawnExtentWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($tjDrawnExtentContent) . " >>\nstream\n{$tjDrawnExtentContent}\nendstream\nendobj\n%%EOF";

$tjInterElementSpacingContent = 'BT /Ftjsp 12 Tf '
    . '6 Tc 1 0 0 1 72 720 Tm [(A)(B)] TJ '
    . 'T* 0 Tc 18 Tw 1 0 0 1 72 704 Tm [(A )(B)] TJ '
    . 'T* 3 Tc 9 Tw 1 0 0 1 72 688 Tm [(A )(B)] TJ '
    . 'T* 6 Tc 0 Tw 1 0 0 1 72 672 Tm [(A) -500 (B)] TJ ET';
$tjInterElementSpacingWidths = implode(' ', array_fill(0, 35, '1000'));
$tjInterElementSpacingPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftjsp 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TjInterElementSpacing /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 66 /Widths [{$tjInterElementSpacingWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($tjInterElementSpacingContent) . " >>\nstream\n{$tjInterElementSpacingContent}\nendstream\nendobj\n%%EOF";

$absoluteTmStyledGapContent = 'BT /Ftm 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4142> Tj 1 0 0 1 108 704 Tm <4344> Tj ET';
$absoluteTmStyledGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftm 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+AbsoluteTmStyledGap /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($absoluteTmStyledGapContent) . " >>\nstream\n{$absoluteTmStyledGapContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$cidAbsoluteTmStyledGapToUnicode = "/CIDInit /ProcSet findresource begin\n"
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
$cidAbsoluteTmStyledGapContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <00010002> Tj 1 0 0 1 108 720 Tm <00030004> Tj '
    . 'T* 1 0 0 1 72 704 Tm <00010002> Tj 1 0 0 1 96 704 Tm <00030004> Tj ET';
$cidAbsoluteTmStyledGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDAbsoluteTmStyledGap /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cidAbsoluteTmStyledGapToUnicode) . " >>\nstream\n{$cidAbsoluteTmStyledGapToUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDAbsoluteTmStyledGap /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [1 4 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($cidAbsoluteTmStyledGapContent) . " >>\nstream\n{$cidAbsoluteTmStyledGapContent}\nendstream\nendobj\n%%EOF";

$negativeTcBacktrackContent = 'BT /FnegTc 12 Tf -30 Tc 1 0 0 1 72 720 Tm <4142> Tj ET';
$negativeTcBacktrackPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /FnegTc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeTcBacktrack /Encoding 6 0 R /FirstChar 65 /LastChar 66 /Widths [1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeTcBacktrackContent) . " >>\nstream\n{$negativeTcBacktrackContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B] >>\nendobj\n%%EOF";

$negativeHorizontalScaleTdContent = 'BT /Ftz 12 Tf -100 Tz '
    . '1 0 0 1 72 720 Tm <4142> Tj 0 0 Td <4344> Tj '
    . 'T* 100 Tz 1 0 0 1 72 704 Tm <4142> Tj 24 0 Td <4344> Tj ET';
$negativeHorizontalScaleTdPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftz 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeTzAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeHorizontalScaleTdContent) . " >>\nstream\n{$negativeHorizontalScaleTdContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$sparseWidthContent = 'BT /Fsparse 12 Tf 1 0 0 1 72 720 Tm <43> Tj 1 0 0 1 87 720 Tm <44> Tj ET';
$sparseWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsparse 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+SparseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 99 0 R 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($sparseWidthContent) . " >>\nstream\n{$sparseWidthContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$exactGenerationWidthContent = 'BT /Fgen 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj ET';
$exactGenerationWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fgen 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+GenerationWidths /Encoding 6 0 R /FirstChar 65 /LastChar 73 /Widths 20 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($exactGenerationWidthContent) . " >>\nstream\n{$exactGenerationWidthContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /W /i /d /e /B /l /o /c /k] >>\nendobj\n"
    . "20 0 obj\n[1000 1000 1000 1000 1000 1000 1000 1000 1000]\nendobj\n"
    . "20 1 obj\n[250 250 250 250 250 250 250 250 250]\nendobj\n%%EOF";

$exactGenerationDescriptorContent = 'BT /Fdesc 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 96 720 Tm <4344> Tj ET';
$exactGenerationDescriptorPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdesc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DescriptorGeneration /Encoding /WinAnsiEncoding /FontDescriptor 7 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($exactGenerationDescriptorContent) . " >>\nstream\n{$exactGenerationDescriptorContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /FontDescriptor /FontName /ReferencedDescriptor /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "7 1 obj\n<< /Type /FontDescriptor /FontName /UnreferencedDescriptor /Flags 32 /MissingWidth 1000 >>\nendobj\n%%EOF";

$exactGenerationDescendantToUnicode = "/CIDInit /ProcSet findresource begin\n"
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
$exactGenerationDescendantContent = 'BT /Fcidgen 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 118 720 Tm <0005000600070008> Tj '
    . 'T* 1 0 0 1 72 704 Tm <0005000600070008> Tj '
    . '1 0 0 1 96 704 Tm <0001000200030004> Tj ET';
$exactGenerationDescendantPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidgen 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DescendantGeneration /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($exactGenerationDescendantToUnicode) . " >>\nstream\n{$exactGenerationDescendantToUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ReferencedDescendant /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 /W [1 4 1000 5 8 250] >>\nendobj\n"
    . "4 1 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /StaleDescendant /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [1 4 250 5 8 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($exactGenerationDescendantContent) . " >>\nstream\n{$exactGenerationDescendantContent}\nendstream\nendobj\n%%EOF";

$lastCharContent = 'BT /Flast 12 Tf '
    . '1 0 0 1 72 720 Tm <43> Tj 1 0 0 1 86 720 Tm <44> Tj '
    . 'T* 1 0 0 1 72 704 Tm <43> Tj 1 0 0 1 100 704 Tm <44> Tj ET';
$lastCharPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Flast 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+LastCharAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 66 /Widths [1000 1000 100] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($lastCharContent) . " >>\nstream\n{$lastCharContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$malformedRangeContent = 'BT /Fbad 12 Tf '
    . '1 0 0 1 72 720 Tm <4344> Tj 1 0 0 1 88 720 Tm <4546> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4344> Tj 1 0 0 1 100 704 Tm <4546> Tj ET';
$malformedRangePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fbad 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+MalformedRangeAdvance /Encoding 6 0 R /FirstChar 67.75 /LastChar 68.25 /Widths [100 100 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($malformedRangeContent) . " >>\nstream\n{$malformedRangeContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [67 /C /D /E /F] >>\nendobj\n%%EOF";

$nestedEncodingWidthContent = 'BT /Fnest 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj ET';
$nestedEncodingWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fnest 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NestedWidthDecoy /FirstChar 65 /LastChar 68 /Encoding << /Widths [250 250 250 250] /Differences [65 /A /B /C /D] >> /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($nestedEncodingWidthContent) . " >>\nstream\n{$nestedEncodingWidthContent}\nendstream\nendobj\n%%EOF";

$nonFiniteWidth = str_repeat('9', 400);
$nonFiniteWidthContent = 'BT /Finf 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$nonFiniteWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Finf 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NonFiniteAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 {$nonFiniteWidth} 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($nonFiniteWidthContent) . " >>\nstream\n{$nonFiniteWidthContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$hugeFiniteWidth = '1' . str_repeat('0', 308);
$hugeFiniteWidthContent = 'BT /Fhuge 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$hugeFiniteWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhuge 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeFiniteAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [{$hugeFiniteWidth} {$hugeFiniteWidth} 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($hugeFiniteWidthContent) . " >>\nstream\n{$hugeFiniteWidthContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$negativeWidthMetricContent = 'BT /Fnegw 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj ET';
$negativeWidthMetricPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fnegw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeMetricAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [-1000 -1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeWidthMetricContent) . " >>\nstream\n{$negativeWidthMetricContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$rotatedTextMatrixContent = 'BT /Frot 12 Tf '
    . '0 1 -1 0 72 720 Tm <4142> Tj '
    . '0.6 0.8 0 1 96 720 Tm <4344> Tj ET';
$rotatedTextMatrixPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frot 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RotatedAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($rotatedTextMatrixContent) . " >>\nstream\n{$rotatedTextMatrixContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$rotatedTdContent = 'BT /Frotd 12 Tf '
    . '0 1 -1 0 72 720 Tm <4142> Tj 48 0 Td <4344> Tj '
    . 'T* 0.6 0.8 0 1 72 704 Tm <4142> Tj 40 0 Td <4344> Tj ET';
$rotatedTdPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frotd 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RotatedTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($rotatedTdContent) . " >>\nstream\n{$rotatedTdContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$textObjectResetContent = 'BT /Freset 12 Tf 0.5 0 0 1 72 720 Tm <4142> Tj ET '
    . 'BT /Freset 12 Tf 72 704 Td [(CD) -1000 (EF)] TJ ET';
$textObjectResetPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Freset 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextObjectResetAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 70 /Widths [1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($textObjectResetContent) . " >>\nstream\n{$textObjectResetContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F] >>\nendobj\n%%EOF";

$verticalEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
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
$verticalToUnicode = "/CIDInit /ProcSet findresource begin\n"
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
$verticalContent = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 0 -24 Td <00050006000700080009000A> Tj ET';
$verticalPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalAdvanceCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /VerticalAdvanceCID-V /Length " . strlen($verticalEncodingCMap) . " >>\nstream\n{$verticalEncodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalAdvanceCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 43 -500 500 880 50 55 -250 500 880] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($verticalContent) . " >>\nstream\n{$verticalContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($verticalToUnicode) . " >>\nstream\n{$verticalToUnicode}\nendstream\nendobj\n%%EOF";
$verticalTjContent = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm [<00010002> -3000 <00030004>] TJ ET';
$verticalTjPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalBacktrackCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /VerticalBacktrackCID-V /Length " . strlen($verticalEncodingCMap) . " >>\nstream\n{$verticalEncodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalBacktrackCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 43 -500 500 880] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($verticalTjContent) . " >>\nstream\n{$verticalTjContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($verticalToUnicode) . " >>\nstream\n{$verticalToUnicode}\nendstream\nendobj\n%%EOF";
$negativeFirstCidToUnicode = "/CIDInit /ProcSet findresource begin\n"
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
$negativeFirstCidContent = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <00000001> Tj 1 0 0 1 100 720 Tm <00020003> Tj ET';
$negativeFirstCidPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NegativeFirstCid /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($negativeFirstCidToUnicode) . " >>\nstream\n{$negativeFirstCidToUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NegativeFirstCid /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [-1 [250 250 250 250]] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeFirstCidContent) . " >>\nstream\n{$negativeFirstCidContent}\nendstream\nendobj\n%%EOF";
$negativeFirstVerticalEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
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
$negativeFirstVerticalToUnicode = "/CIDInit /ProcSet findresource begin\n"
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
$negativeFirstW2Content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <00000001> Tj 0 -24 Td <00020003> Tj ET';
$negativeFirstW2Pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NegativeVerticalFirstCid /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /NegativeVerticalFirstCid-V /Length " . strlen($negativeFirstVerticalEncodingCMap) . " >>\nstream\n{$negativeFirstVerticalEncodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NegativeVerticalFirstCid /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [-1 [-250 500 880 -250 500 880 -250 500 880 -250 500 880]] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeFirstW2Content) . " >>\nstream\n{$negativeFirstW2Content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($negativeFirstVerticalToUnicode) . " >>\nstream\n{$negativeFirstVerticalToUnicode}\nendstream\nendobj\n%%EOF";
$type3FontMatrixWidthsToUnicode = "/CIDInit /ProcSet findresource begin\n"
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
$type3FontMatrixWidthsContent = 'BT /Ft3 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 96 720 Tm <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4142> Tj '
    . '1 0 0 1 108 704 Tm <4344> Tj ET';
$type3FontMatrixWidthsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MatrixWidths /BaseFont /T3MatrixWidths "
    . "/FontBBox [0 0 500 700] /FontMatrix [0.002 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 68 /Widths [500 500 500 500] "
    . "/Encoding /WinAnsiEncoding /CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($type3FontMatrixWidthsContent) . " >>\nstream\n{$type3FontMatrixWidthsContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($type3FontMatrixWidthsToUnicode) . " >>\nstream\n{$type3FontMatrixWidthsToUnicode}\nendstream\nendobj\n%%EOF";
$type3FontMatrixVectorContent = 'BT /Ft3v 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 99 720 Tm <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4142> Tj '
    . '1 0 0 1 108 704 Tm <4344> Tj ET';
$type3FontMatrixVectorPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3v 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MatrixVector /BaseFont /T3MatrixVector "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.0006 0.0008 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] "
    . "/Encoding /WinAnsiEncoding /CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($type3FontMatrixVectorContent) . " >>\nstream\n{$type3FontMatrixVectorContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($type3FontMatrixWidthsToUnicode) . " >>\nstream\n{$type3FontMatrixWidthsToUnicode}\nendstream\nendobj\n%%EOF";
$type3CharProcMatrixVectorWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide charproc matrix-vector text leak) Tj ET\n";
$type3CharProcMatrixVectorThinCharProc = "250 0 d0\nBT /Fghost 9 Tf (thin charproc matrix-vector text leak) Tj ET\n";
$type3CharProcMatrixVectorContent = 'BT /Ft3cv 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 100 720 Tm <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4546> Tj '
    . '1 0 0 1 90 704 Tm <4748> Tj ET';
$type3CharProcMatrixVectorPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3cv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcMatrixVector /BaseFont /T3CharProcMatrixVector "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.0006 0.0008 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 72 /Widths [100 100 100 100 100 100 100 100] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /E 4 0 R /F 4 0 R /G 4 0 R /H 4 0 R >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($type3CharProcMatrixVectorWideCharProc) . " >>\nstream\n{$type3CharProcMatrixVectorWideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($type3CharProcMatrixVectorThinCharProc) . " >>\nstream\n{$type3CharProcMatrixVectorThinCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($type3CharProcMatrixVectorContent) . " >>\nstream\n{$type3CharProcMatrixVectorContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $spans
);
$quoteLines = $extractor->extractTextLines($quotePdf);
$quotePages = $extractor->extractStyledTextPages($quotePdf);
$quoteSpan = $quotePages[0]['blocks'][1]['lines'][0]['spans'][0] ?? [];
$terminalTcLines = $extractor->extractTextLines($terminalTcPdf);
$terminalTcPlainText = implode("\n", $terminalTcLines);
$terminalTcPages = $extractor->extractStyledTextPages($terminalTcPdf);
$terminalTcSpans = $terminalTcPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$terminalTcSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $terminalTcSpans
);
$terminalTwLines = $extractor->extractTextLines($terminalTwPdf);
$terminalTwPlainText = implode("\n", $terminalTwLines);
$terminalTwPages = $extractor->extractStyledTextPages($terminalTwPdf);
$terminalTwStyledLines = [];
foreach (($terminalTwPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $terminalTwStyledLines[] = $line;
    }
}
$terminalTwFirstLine = $terminalTwStyledLines[0] ?? [];
$terminalTwSecondLine = $terminalTwStyledLines[1] ?? [];
$terminalTwFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $terminalTwFirstLine['spans'] ?? []
);
$terminalTwSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $terminalTwSecondLine['spans'] ?? []
);
$relativeTdLines = $extractor->extractTextLines($relativeTdPdf);
$relativeTdPlainText = implode("\n", $relativeTdLines);
$relativeTdPages = $extractor->extractStyledTextPages($relativeTdPdf);
$relativeTdSpans = $relativeTdPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$relativeTdSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $relativeTdSpans
);
$relativeTdStyledGapLines = $extractor->extractTextLines($relativeTdStyledGapPdf);
$relativeTdStyledGapPlainText = implode("\n", $relativeTdStyledGapLines);
$relativeTdStyledGapPages = $extractor->extractStyledTextPages($relativeTdStyledGapPdf);
$relativeTdStyledGapLinesStyled = [];
foreach (($relativeTdStyledGapPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $relativeTdStyledGapLinesStyled[] = $line;
    }
}
$relativeTdStyledGapFirstLine = $relativeTdStyledGapLinesStyled[0] ?? [];
$relativeTdStyledGapSecondLine = $relativeTdStyledGapLinesStyled[1] ?? [];
$relativeTdStyledGapFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $relativeTdStyledGapFirstLine['spans'] ?? []
);
$relativeTdStyledGapSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $relativeTdStyledGapSecondLine['spans'] ?? []
);
$scaledTdLines = $extractor->extractTextLines($scaledTdPdf);
$scaledTdPlainText = implode("\n", $scaledTdLines);
$scaledTdPages = $extractor->extractStyledTextPages($scaledTdPdf);
$scaledTdSpans = $scaledTdPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$scaledTdSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $scaledTdSpans
);
$textMatrixScaleLines = $extractor->extractTextLines($textMatrixScalePdf);
$textMatrixScalePlainText = implode("\n", $textMatrixScaleLines);
$textMatrixScalePages = $extractor->extractStyledTextPages($textMatrixScalePdf);
$textMatrixScaleSpans = $textMatrixScalePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$textMatrixScaleSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $textMatrixScaleSpans
);
$negativeTextMatrixLines = $extractor->extractTextLines($negativeTextMatrixPdf);
$negativeTextMatrixPages = $extractor->extractStyledTextPages($negativeTextMatrixPdf);
$negativeTextMatrixSpans = $negativeTextMatrixPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$negativeTextMatrixSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeTextMatrixSpans
);
$textRiseLines = $extractor->extractTextLines($textRisePdf);
$textRisePlainText = implode("\n", $textRiseLines);
$textRisePages = $extractor->extractStyledTextPages($textRisePdf);
$textRiseLine = $textRisePages[0]['blocks'][0]['lines'][0] ?? [];
$textRiseSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $textRiseLine['spans'] ?? []
);
$tjBacktrackLines = $extractor->extractTextLines($tjBacktrackPdf);
$tjBacktrackPlainText = implode("\n", $tjBacktrackLines);
$tjBacktrackPages = $extractor->extractStyledTextPages($tjBacktrackPdf);
$tjBacktrackLine = $tjBacktrackPages[0]['blocks'][0]['lines'][0] ?? [];
$tjBacktrackSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $tjBacktrackLine['spans'] ?? []
);
$tjDrawnExtentLines = $extractor->extractTextLines($tjDrawnExtentPdf);
$tjDrawnExtentPlainText = implode("\n", $tjDrawnExtentLines);
$tjDrawnExtentPages = $extractor->extractStyledTextPages($tjDrawnExtentPdf);
$tjDrawnExtentLine = $tjDrawnExtentPages[0]['blocks'][0]['lines'][0] ?? [];
$tjDrawnExtentSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $tjDrawnExtentLine['spans'] ?? []
);
$tjInterElementSpacingLines = $extractor->extractTextLines($tjInterElementSpacingPdf);
$tjInterElementSpacingPlainText = implode("\n", $tjInterElementSpacingLines);
$tjInterElementSpacingPages = $extractor->extractStyledTextPages($tjInterElementSpacingPdf);
$tjInterElementSpacingStyledLines = [];
foreach (($tjInterElementSpacingPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $tjInterElementSpacingStyledLines[] = $line;
    }
}
$tjInterElementSpacingSpanTexts = array_map(
    static fn (array $line): string => implode('', array_column($line['spans'] ?? [], 'text')),
    $tjInterElementSpacingStyledLines
);
$tjInterElementSpacingSpanBboxes = array_map(
    static fn (array $line): array => array_map(
        static fn (array $span): array => $span['bbox'] ?? [],
        $line['spans'] ?? []
    ),
    $tjInterElementSpacingStyledLines
);
$tjInterElementSpacingLineBboxes = array_map(
    static fn (array $line): array => $line['bbox'] ?? [],
    $tjInterElementSpacingStyledLines
);
$absoluteTmStyledGapLines = $extractor->extractTextLines($absoluteTmStyledGapPdf);
$absoluteTmStyledGapPlainText = implode("\n", $absoluteTmStyledGapLines);
$absoluteTmStyledGapPages = $extractor->extractStyledTextPages($absoluteTmStyledGapPdf);
$absoluteTmStyledGapStyledLines = [];
foreach (($absoluteTmStyledGapPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $absoluteTmStyledGapStyledLines[] = $line;
    }
}
$absoluteTmStyledGapFirstLine = $absoluteTmStyledGapStyledLines[0] ?? [];
$absoluteTmStyledGapSecondLine = $absoluteTmStyledGapStyledLines[1] ?? [];
$absoluteTmStyledGapFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $absoluteTmStyledGapFirstLine['spans'] ?? []
);
$absoluteTmStyledGapSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $absoluteTmStyledGapSecondLine['spans'] ?? []
);
$cidAbsoluteTmStyledGapLines = $extractor->extractTextLines($cidAbsoluteTmStyledGapPdf);
$cidAbsoluteTmStyledGapPlainText = implode("\n", $cidAbsoluteTmStyledGapLines);
$cidAbsoluteTmStyledGapPages = $extractor->extractStyledTextPages($cidAbsoluteTmStyledGapPdf);
$cidAbsoluteTmStyledGapStyledLines = [];
foreach (($cidAbsoluteTmStyledGapPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $cidAbsoluteTmStyledGapStyledLines[] = $line;
    }
}
$cidAbsoluteTmStyledGapFirstLine = $cidAbsoluteTmStyledGapStyledLines[0] ?? [];
$cidAbsoluteTmStyledGapSecondLine = $cidAbsoluteTmStyledGapStyledLines[1] ?? [];
$cidAbsoluteTmStyledGapFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $cidAbsoluteTmStyledGapFirstLine['spans'] ?? []
);
$cidAbsoluteTmStyledGapSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $cidAbsoluteTmStyledGapSecondLine['spans'] ?? []
);
$negativeTcBacktrackLines = $extractor->extractTextLines($negativeTcBacktrackPdf);
$negativeTcBacktrackPages = $extractor->extractStyledTextPages($negativeTcBacktrackPdf);
$negativeTcBacktrackLine = $negativeTcBacktrackPages[0]['blocks'][0]['lines'][0] ?? [];
$negativeTcBacktrackSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeTcBacktrackLine['spans'] ?? []
);
$negativeHorizontalScaleTdLines = $extractor->extractTextLines($negativeHorizontalScaleTdPdf);
$negativeHorizontalScaleTdPlainText = implode("\n", $negativeHorizontalScaleTdLines);
$negativeHorizontalScaleTdPages = $extractor->extractStyledTextPages($negativeHorizontalScaleTdPdf);
$negativeHorizontalScaleTdStyledLines = [];
foreach (($negativeHorizontalScaleTdPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $negativeHorizontalScaleTdStyledLines[] = $line;
    }
}
$negativeHorizontalScaleTdFirstLine = $negativeHorizontalScaleTdStyledLines[0] ?? [];
$negativeHorizontalScaleTdSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeHorizontalScaleTdFirstLine['spans'] ?? []
);
$sparseWidthLines = $extractor->extractTextLines($sparseWidthPdf);
$sparseWidthPlainText = implode("\n", $sparseWidthLines);
$sparseWidthPages = $extractor->extractStyledTextPages($sparseWidthPdf);
$sparseWidthSpans = $sparseWidthPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$sparseWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $sparseWidthSpans
);
$exactGenerationWidthLines = $extractor->extractTextLines($exactGenerationWidthPdf);
$exactGenerationWidthPlainText = implode("\n", $exactGenerationWidthLines);
$exactGenerationWidthPages = $extractor->extractStyledTextPages($exactGenerationWidthPdf);
$exactGenerationWidthLine = $exactGenerationWidthPages[0]['blocks'][0]['lines'][0] ?? [];
$exactGenerationWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $exactGenerationWidthLine['spans'] ?? []
);
$exactGenerationDescriptorLines = $extractor->extractTextLines($exactGenerationDescriptorPdf);
$exactGenerationDescriptorPlainText = implode("\n", $exactGenerationDescriptorLines);
$exactGenerationDescriptorPages = $extractor->extractStyledTextPages($exactGenerationDescriptorPdf);
$exactGenerationDescriptorLine = $exactGenerationDescriptorPages[0]['blocks'][0]['lines'][0] ?? [];
$exactGenerationDescriptorSpans = $exactGenerationDescriptorLine['spans'] ?? [];
$exactGenerationDescriptorSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $exactGenerationDescriptorSpans
);
$exactGenerationDescriptorSpanFonts = array_map(
    static fn (array $span): string => (string) ($span['font'] ?? ''),
    $exactGenerationDescriptorSpans
);
$exactGenerationDescendantLines = $extractor->extractTextLines($exactGenerationDescendantPdf);
$exactGenerationDescendantPlainText = implode("\n", $exactGenerationDescendantLines);
$exactGenerationDescendantPages = $extractor->extractStyledTextPages($exactGenerationDescendantPdf);
$exactGenerationDescendantStyledLines = [];
foreach (($exactGenerationDescendantPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $exactGenerationDescendantStyledLines[] = $line;
    }
}
$exactGenerationDescendantFirstLine = $exactGenerationDescendantStyledLines[0] ?? [];
$exactGenerationDescendantSecondLine = $exactGenerationDescendantStyledLines[1] ?? [];
$exactGenerationDescendantFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $exactGenerationDescendantFirstLine['spans'] ?? []
);
$exactGenerationDescendantSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $exactGenerationDescendantSecondLine['spans'] ?? []
);
$lastCharLines = $extractor->extractTextLines($lastCharPdf);
$lastCharPlainText = implode("\n", $lastCharLines);
$lastCharPages = $extractor->extractStyledTextPages($lastCharPdf);
$lastCharFirstLine = $lastCharPages[0]['blocks'][0]['lines'][0] ?? [];
$lastCharSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $lastCharFirstLine['spans'] ?? []
);
$malformedRangeLines = $extractor->extractTextLines($malformedRangePdf);
$malformedRangePlainText = implode("\n", $malformedRangeLines);
$malformedRangePages = $extractor->extractStyledTextPages($malformedRangePdf);
$malformedRangeFirstLine = $malformedRangePages[0]['blocks'][0]['lines'][0] ?? [];
$malformedRangeSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $malformedRangeFirstLine['spans'] ?? []
);
$nestedEncodingWidthLines = $extractor->extractTextLines($nestedEncodingWidthPdf);
$nestedEncodingWidthPlainText = implode("\n", $nestedEncodingWidthLines);
$nestedEncodingWidthPages = $extractor->extractStyledTextPages($nestedEncodingWidthPdf);
$nestedEncodingWidthLine = $nestedEncodingWidthPages[0]['blocks'][0]['lines'][0] ?? [];
$nestedEncodingWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $nestedEncodingWidthLine['spans'] ?? []
);
$nonFiniteWidthLines = $extractor->extractTextLines($nonFiniteWidthPdf);
$nonFiniteWidthPlainText = implode("\n", $nonFiniteWidthLines);
$nonFiniteWidthPages = $extractor->extractStyledTextPages($nonFiniteWidthPdf);
$nonFiniteWidthLine = $nonFiniteWidthPages[0]['blocks'][0]['lines'][0] ?? [];
$nonFiniteWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $nonFiniteWidthLine['spans'] ?? []
);
$nonFiniteWidthBboxesAreFinite = true;
foreach ($nonFiniteWidthSpanBboxes as $bbox) {
    foreach ($bbox as $number) {
        $nonFiniteWidthBboxesAreFinite = $nonFiniteWidthBboxesAreFinite
            && is_float($number)
            && is_finite($number);
    }
}
$hugeFiniteWidthLines = $extractor->extractTextLines($hugeFiniteWidthPdf);
$hugeFiniteWidthPlainText = implode("\n", $hugeFiniteWidthLines);
$hugeFiniteWidthPages = $extractor->extractStyledTextPages($hugeFiniteWidthPdf);
$hugeFiniteWidthLine = $hugeFiniteWidthPages[0]['blocks'][0]['lines'][0] ?? [];
$hugeFiniteWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $hugeFiniteWidthLine['spans'] ?? []
);
$hugeFiniteWidthBboxesAreFinite = true;
$hugeFiniteWidthMaxBboxMagnitude = 0.0;
foreach (array_merge($hugeFiniteWidthSpanBboxes, [$hugeFiniteWidthLine['bbox'] ?? []]) as $bbox) {
    foreach ($bbox as $number) {
        $hugeFiniteWidthBboxesAreFinite = $hugeFiniteWidthBboxesAreFinite
            && is_float($number)
            && is_finite($number);
        $hugeFiniteWidthMaxBboxMagnitude = max($hugeFiniteWidthMaxBboxMagnitude, abs((float) $number));
    }
}
$negativeWidthMetricLines = $extractor->extractTextLines($negativeWidthMetricPdf);
$negativeWidthMetricPlainText = implode("\n", $negativeWidthMetricLines);
$negativeWidthMetricPages = $extractor->extractStyledTextPages($negativeWidthMetricPdf);
$negativeWidthMetricLine = $negativeWidthMetricPages[0]['blocks'][0]['lines'][0] ?? [];
$negativeWidthMetricSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeWidthMetricLine['spans'] ?? []
);
$rotatedTextMatrixLines = $extractor->extractTextLines($rotatedTextMatrixPdf);
$rotatedTextMatrixPages = $extractor->extractStyledTextPages($rotatedTextMatrixPdf);
$rotatedTextMatrixLine = $rotatedTextMatrixPages[0]['blocks'][0]['lines'][0] ?? [];
$rotatedTextMatrixSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $rotatedTextMatrixLine['spans'] ?? []
);
$rotatedTdLines = $extractor->extractTextLines($rotatedTdPdf);
$rotatedTdPlainText = implode("\n", $rotatedTdLines);
$rotatedTdPages = $extractor->extractStyledTextPages($rotatedTdPdf);
$rotatedTdStyledLines = [];
foreach (($rotatedTdPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $rotatedTdStyledLines[] = $line;
    }
}
$rotatedTdFirstLine = $rotatedTdStyledLines[0] ?? [];
$rotatedTdSecondLine = $rotatedTdStyledLines[1] ?? [];
$rotatedTdFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $rotatedTdFirstLine['spans'] ?? []
);
$rotatedTdSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $rotatedTdSecondLine['spans'] ?? []
);
$textObjectResetLines = $extractor->extractTextLines($textObjectResetPdf);
$textObjectResetPages = $extractor->extractStyledTextPages($textObjectResetPdf);
$textObjectResetStyledLines = [];
foreach (($textObjectResetPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $textObjectResetStyledLines[] = $line;
    }
}
$textObjectResetSecondLine = $textObjectResetStyledLines[1] ?? [];
$textObjectResetSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $textObjectResetSecondLine['spans'] ?? []
);
$textObjectResetSpanTexts = array_map(
    static fn (array $span): string => (string) ($span['text'] ?? ''),
    $textObjectResetSecondLine['spans'] ?? []
);
$verticalLines = $extractor->extractTextLines($verticalPdf);
$verticalPages = $extractor->extractStyledTextPages($verticalPdf);
$verticalLine = $verticalPages[0]['blocks'][0]['lines'][0] ?? [];
$verticalSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $verticalLine['spans'] ?? []
);
$verticalTjLines = $extractor->extractTextLines($verticalTjPdf);
$verticalTjPlainText = implode("\n", $verticalTjLines);
$verticalTjPages = $extractor->extractStyledTextPages($verticalTjPdf);
$verticalTjLine = $verticalTjPages[0]['blocks'][0]['lines'][0] ?? [];
$verticalTjSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $verticalTjLine['spans'] ?? []
);
$negativeFirstCidLines = $extractor->extractTextLines($negativeFirstCidPdf);
$negativeFirstCidPlainText = implode("\n", $negativeFirstCidLines);
$negativeFirstCidPages = $extractor->extractStyledTextPages($negativeFirstCidPdf);
$negativeFirstCidLine = $negativeFirstCidPages[0]['blocks'][0]['lines'][0] ?? [];
$negativeFirstCidSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeFirstCidLine['spans'] ?? []
);
$negativeFirstW2Lines = $extractor->extractTextLines($negativeFirstW2Pdf);
$negativeFirstW2PlainText = implode("\n", $negativeFirstW2Lines);
$negativeFirstW2Pages = $extractor->extractStyledTextPages($negativeFirstW2Pdf);
$negativeFirstW2Line = $negativeFirstW2Pages[0]['blocks'][0]['lines'][0] ?? [];
$negativeFirstW2SpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeFirstW2Line['spans'] ?? []
);
$type3FontMatrixWidthsLines = $extractor->extractTextLines($type3FontMatrixWidthsPdf);
$type3FontMatrixWidthsPlainText = implode("\n", $type3FontMatrixWidthsLines);
$type3FontMatrixWidthsPages = $extractor->extractStyledTextPages($type3FontMatrixWidthsPdf);
$type3FontMatrixWidthsFirstLine = $type3FontMatrixWidthsPages[0]['blocks'][0]['lines'][0] ?? [];
$type3FontMatrixWidthsSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $type3FontMatrixWidthsFirstLine['spans'] ?? []
);
$type3FontMatrixVectorLines = $extractor->extractTextLines($type3FontMatrixVectorPdf);
$type3FontMatrixVectorPlainText = implode("\n", $type3FontMatrixVectorLines);
$type3FontMatrixVectorPages = $extractor->extractStyledTextPages($type3FontMatrixVectorPdf);
$type3FontMatrixVectorFirstLine = $type3FontMatrixVectorPages[0]['blocks'][0]['lines'][0] ?? [];
$type3FontMatrixVectorSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $type3FontMatrixVectorFirstLine['spans'] ?? []
);
$type3CharProcMatrixVectorLines = $extractor->extractTextLines($type3CharProcMatrixVectorPdf);
$type3CharProcMatrixVectorPlainText = implode("\n", $type3CharProcMatrixVectorLines);
$type3CharProcMatrixVectorPages = $extractor->extractStyledTextPages($type3CharProcMatrixVectorPdf);
$type3CharProcMatrixVectorStyledLines = [];
foreach (($type3CharProcMatrixVectorPages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $type3CharProcMatrixVectorStyledLines[] = $line;
    }
}
$type3CharProcMatrixVectorFirstLine = $type3CharProcMatrixVectorStyledLines[0] ?? [];
$type3CharProcMatrixVectorSecondLine = $type3CharProcMatrixVectorStyledLines[1] ?? [];
$type3CharProcMatrixVectorFirstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $type3CharProcMatrixVectorFirstLine['spans'] ?? []
);
$type3CharProcMatrixVectorSecondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $type3CharProcMatrixVectorSecondLine['spans'] ?? []
);

echo '<!-- markerpdf-font-width-advance-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-width-advance-boundary-currentbase',
    'source' => 'native-pdf-simple-font-average-positive-lastchar-malformed-range-nested-encoding-width-decoy-nonfinite-width-huge-finite-width-negative-width-metric-exact-generation-widths-fontdescriptor-and-descendant-cidfont-quote-terminal-tc-terminal-tw-relative-td-styled-gap-rotated-td-vector-gap-absolute-tm-styled-gap-cid-absolute-tm-styled-gap-scaled-td-text-matrix-vertical-negative-rotated-horizontal-vector-text-object-reset-text-rise-tj-drawn-extent-vertical-width-vertical-tj-negative-tc-negative-first-cid-array-type3-fontmatrix-width-and-type3-fontmatrix-vector-and-type3-charproc-fontmatrix-vector-advance',
    'average_width_preserves_joined_word' => str_contains($plainText, 'WideBlock'),
    'generic_500_width_gap_excluded' => !str_contains($plainText, 'Wide Block'),
    'narrow_positioned_gap_still_preserved' => str_contains($plainText, 'Blo ck'),
    'quote_operator_text_lines_preserved' => $quoteLines === ['Lead', 'A B'],
    'quote_operator_spacing_styled_bbox_preserved' => ($quoteSpan['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'terminal_tc_uses_cursor_advance_for_td_gap' => ($terminalTcLines[0] ?? null) === 'ABCD',
    'terminal_tc_larger_gap_still_preserved' => ($terminalTcLines[1] ?? null) === 'AB CD',
    'terminal_tc_drawn_bbox_excludes_terminal_spacing' => $terminalTcSpanBboxes === [[0.0, 0.0, 30.0, 12.0], [30.0, 0.0, 60.0, 12.0]],
    'terminal_tc_false_gap_excluded' => !str_contains($terminalTcPlainText, 'AB CD' . "\n" . 'AB CD'),
    'terminal_tw_uses_cursor_advance_for_tm_gap' => $terminalTwLines === ['AB CD', 'AB CD'],
    'terminal_tw_drawn_bbox_excludes_terminal_word_spacing' => $terminalTwFirstBboxes === [[0.0, 0.0, 36.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'terminal_tw_subthreshold_gap_compacts_styled_bbox' => $terminalTwSecondBboxes === [[0.0, 0.0, 36.0, 12.0], [36.0, 0.0, 60.0, 12.0]],
    'terminal_tw_stale_word_spacing_bbox_excluded' => $terminalTwFirstBboxes !== [[0.0, 0.0, 60.0, 12.0], [60.0, 0.0, 84.0, 12.0]],
    'relative_td_uses_font_width_current_end' => ($relativeTdLines[0] ?? null) === 'ABCD',
    'relative_td_larger_gap_still_preserved' => ($relativeTdLines[1] ?? null) === 'AB CD',
    'relative_td_false_gap_excluded' => !str_contains($relativeTdPlainText, 'AB CD' . "\n" . 'AB CD'),
    'relative_td_span_bboxes_preserved' => $relativeTdSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'relative_td_styled_gap_lines_preserved' => $relativeTdStyledGapLines === ['AB CD', 'ABCD'],
    'relative_td_styled_gap_plain_text_preserved' => $relativeTdStyledGapPlainText === "AB CD\nABCD",
    'relative_td_styled_gap_bboxes_preserved' => $relativeTdStyledGapFirstBboxes === [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'relative_td_styled_gap_line_bbox_preserved' => ($relativeTdStyledGapFirstLine['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'relative_td_styled_gap_compaction_excluded' => $relativeTdStyledGapFirstBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'relative_td_styled_no_gap_bboxes_preserved' => $relativeTdStyledGapSecondBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'scaled_td_uses_text_matrix_current_end' => ($scaledTdLines[0] ?? null) === 'ABCD',
    'scaled_td_larger_gap_still_preserved' => ($scaledTdLines[1] ?? null) === 'AB CD',
    'scaled_td_false_gap_excluded' => !str_contains($scaledTdPlainText, 'AB CD' . "\n" . 'AB CD'),
    'scaled_td_span_bboxes_preserved' => $scaledTdSpanBboxes === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'text_matrix_vertical_scale_line_preserved' => $textMatrixScaleLines === ['ABCD'],
    'text_matrix_vertical_scale_false_gap_excluded' => !str_contains($textMatrixScalePlainText, 'AB CD'),
    'text_matrix_vertical_scale_span_bboxes_preserved' => $textMatrixScaleSpanBboxes === [[0.0, 0.0, 24.0, 6.0], [24.0, 0.0, 48.0, 24.0]],
    'text_matrix_vertical_unscaled_height_excluded' => $textMatrixScaleSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_text_matrix_line_boundary_preserved' => $negativeTextMatrixLines === ['AB CD'],
    'negative_text_matrix_span_bboxes_preserved' => $negativeTextMatrixSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_text_matrix_collapsed_bbox_excluded' => $negativeTextMatrixSpanBboxes !== [[0.0, 0.0, 1.0, 12.0], [1.0, 0.0, 25.0, 12.0]],
    'text_rise_preserves_horizontal_advance' => $textRiseLines === ['ABCDEFGH'],
    'text_rise_span_bboxes_preserved' => $textRiseSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 6.0, 48.0, 18.0], [48.0, -3.0, 72.0, 9.0], [72.0, 0.0, 96.0, 12.0]],
    'text_rise_line_bbox_preserved' => ($textRiseLine['bbox'] ?? null) === [0.0, -3.0, 96.0, 18.0],
    'text_rise_zero_baseline_fallback_excluded' => $textRiseSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0], [48.0, 0.0, 72.0, 12.0], [72.0, 0.0, 96.0, 12.0]],
    'tj_backtrack_text_preserved' => $tjBacktrackLines === ['ABCD'],
    'tj_backtrack_plain_text_preserved' => $tjBacktrackPlainText === 'ABCD',
    'tj_backtrack_span_bbox_preserved' => $tjBacktrackSpanBboxes === [[0.0, 0.0, 36.0, 12.0]],
    'tj_backtrack_line_bbox_preserved' => ($tjBacktrackLine['bbox'] ?? null) === [0.0, 0.0, 36.0, 12.0],
    'tj_backtrack_final_cursor_collapse_excluded' => $tjBacktrackSpanBboxes !== [[0.0, 0.0, 12.0, 12.0]],
    'tj_drawn_extent_near_tm_gap_excluded' => ($tjDrawnExtentLines[0] ?? null) === 'ABCDEF',
    'tj_drawn_extent_real_tm_gap_preserved' => ($tjDrawnExtentLines[1] ?? null) === 'ABCD EF',
    'tj_drawn_extent_double_gap_output_excluded' => !str_contains($tjDrawnExtentPlainText, 'ABCD EF' . "\n" . 'ABCD EF'),
    'tj_drawn_extent_styled_bboxes_preserved' => $tjDrawnExtentSpanBboxes === [[0.0, 0.0, 36.0, 12.0], [36.0, 0.0, 60.0, 12.0]],
    'tj_inter_element_spacing_lines_preserved' => $tjInterElementSpacingLines === ['AB', 'A B', 'A B', 'AB'],
    'tj_inter_element_spacing_plain_text_preserved' => $tjInterElementSpacingPlainText === "AB\nA B\nA B\nAB",
    'tj_inter_element_spacing_span_texts_preserved' => $tjInterElementSpacingSpanTexts === ['AB', 'A B', 'A B', 'AB'],
    'tj_inter_element_tc_bbox_preserved' => ($tjInterElementSpacingSpanBboxes[0] ?? null) === [[0.0, 0.0, 30.0, 12.0]],
    'tj_inter_element_tw_bbox_preserved' => ($tjInterElementSpacingSpanBboxes[1] ?? null) === [[0.0, 0.0, 54.0, 12.0]],
    'tj_inter_element_tc_tw_bbox_preserved' => ($tjInterElementSpacingSpanBboxes[2] ?? null) === [[0.0, 0.0, 51.0, 12.0]],
    'tj_inter_element_adjustment_bbox_preserved' => ($tjInterElementSpacingSpanBboxes[3] ?? null) === [[0.0, 0.0, 36.0, 12.0]],
    'tj_inter_element_line_bboxes_preserved' => $tjInterElementSpacingLineBboxes === [[0.0, 0.0, 30.0, 12.0], [0.0, 0.0, 54.0, 12.0], [0.0, 0.0, 51.0, 12.0], [0.0, 0.0, 36.0, 12.0]],
    'tj_inter_element_collapsed_tc_bbox_excluded' => ($tjInterElementSpacingSpanBboxes[0] ?? null) !== [[0.0, 0.0, 24.0, 12.0]],
    'tj_inter_element_collapsed_tw_bbox_excluded' => ($tjInterElementSpacingSpanBboxes[1] ?? null) !== [[0.0, 0.0, 36.0, 12.0]],
    'tj_inter_element_collapsed_adjustment_bbox_excluded' => ($tjInterElementSpacingSpanBboxes[3] ?? null) !== [[0.0, 0.0, 30.0, 12.0]],
    'absolute_tm_styled_gap_lines_preserved' => $absoluteTmStyledGapLines === ['ABCD', 'AB CD'],
    'absolute_tm_styled_gap_plain_text_preserved' => $absoluteTmStyledGapPlainText === "ABCD\nAB CD",
    'absolute_tm_styled_gap_first_bboxes_preserved' => $absoluteTmStyledGapFirstBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'absolute_tm_styled_gap_second_bboxes_preserved' => $absoluteTmStyledGapSecondBboxes === [[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]],
    'absolute_tm_styled_gap_line_bbox_preserved' => ($absoluteTmStyledGapSecondLine['bbox'] ?? null) === [0.0, 0.0, 60.0, 12.0],
    'absolute_tm_styled_gap_compaction_excluded' => $absoluteTmStyledGapSecondBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'cid_absolute_tm_styled_gap_lines_preserved' => $cidAbsoluteTmStyledGapLines === ['AB CD', 'ABCD'],
    'cid_absolute_tm_styled_gap_plain_text_preserved' => $cidAbsoluteTmStyledGapPlainText === "AB CD\nABCD",
    'cid_absolute_tm_styled_gap_first_bboxes_preserved' => $cidAbsoluteTmStyledGapFirstBboxes === [[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]],
    'cid_absolute_tm_styled_gap_second_bboxes_preserved' => $cidAbsoluteTmStyledGapSecondBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'cid_absolute_tm_styled_gap_line_bbox_preserved' => ($cidAbsoluteTmStyledGapFirstLine['bbox'] ?? null) === [0.0, 0.0, 60.0, 12.0],
    'cid_absolute_tm_styled_gap_compaction_excluded' => $cidAbsoluteTmStyledGapFirstBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_tc_backtrack_text_preserved' => $negativeTcBacktrackLines === ['AB'],
    'negative_tc_backtrack_span_bbox_preserved' => $negativeTcBacktrackSpanBboxes === [[0.0, 0.0, 30.0, 12.0]],
    'negative_tc_backtrack_line_bbox_preserved' => ($negativeTcBacktrackLine['bbox'] ?? null) === [0.0, 0.0, 30.0, 12.0],
    'negative_tc_final_cursor_collapse_excluded' => $negativeTcBacktrackSpanBboxes !== [[0.0, 0.0, 6.0, 12.0]],
    'negative_horizontal_scale_td_false_gap_excluded' => $negativeHorizontalScaleTdLines === ['ABCD', 'ABCD'],
    'negative_horizontal_scale_td_plain_text_preserved' => $negativeHorizontalScaleTdPlainText === "ABCD\nABCD",
    'negative_horizontal_scale_td_styled_bboxes_preserved' => $negativeHorizontalScaleTdSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_horizontal_scale_td_stale_logical_end_gap_excluded' => $negativeHorizontalScaleTdSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'unresolved_width_slot_preserved' => $sparseWidthLines === ['CD'],
    'unresolved_width_false_gap_excluded' => !str_contains($sparseWidthPlainText, 'C D'),
    'unresolved_width_slot_bboxes_preserved' => $sparseWidthSpanBboxes === [[0.0, 0.0, 9.0, 12.0], [9.0, 0.0, 12.0, 12.0]],
    'exact_generation_width_array_resolved' => $exactGenerationWidthLines === ['WideBlock'],
    'exact_generation_width_false_gap_excluded' => !str_contains($exactGenerationWidthPlainText, 'Wide Block'),
    'exact_generation_width_stale_generation_excluded' => $exactGenerationWidthSpanBboxes !== [[0.0, 0.0, 12.0, 12.0], [46.0, 0.0, 61.0, 12.0]],
    'exact_generation_width_bboxes_preserved' => $exactGenerationWidthSpanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]],
    'exact_generation_fontdescriptor_missingwidth_gap_preserved' => $exactGenerationDescriptorLines === ['AB CD'],
    'exact_generation_fontdescriptor_false_join_excluded' => !str_contains($exactGenerationDescriptorPlainText, 'ABCD'),
    'exact_generation_fontdescriptor_bboxes_preserved' => $exactGenerationDescriptorSpanBboxes === [[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]],
    'exact_generation_fontdescriptor_unreferenced_bboxes_excluded' => $exactGenerationDescriptorSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'exact_generation_fontdescriptor_font_preserved' => $exactGenerationDescriptorSpanFonts === ['ReferencedDescriptor_symbolic', 'ReferencedDescriptor_symbolic'],
    'exact_generation_fontdescriptor_unreferenced_font_excluded' => !in_array('UnreferencedDescriptor_non_symbolic', $exactGenerationDescriptorSpanFonts, true),
    'exact_generation_descendant_cid_widths_resolved' => $exactGenerationDescendantLines === ['WideThin', 'Thin Wide'],
    'exact_generation_descendant_stale_generation_excluded' => !str_contains($exactGenerationDescendantPlainText, 'Wide Thin' . "\n" . 'ThinWide'),
    'exact_generation_descendant_first_bboxes_preserved' => $exactGenerationDescendantFirstBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'exact_generation_descendant_second_bboxes_preserved' => $exactGenerationDescendantSecondBboxes === [[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 72.0, 12.0]],
    'exact_generation_descendant_stale_first_bboxes_excluded' => $exactGenerationDescendantFirstBboxes !== [[0.0, 0.0, 12.0, 12.0], [46.0, 0.0, 94.0, 12.0]],
    'exact_generation_descendant_stale_second_bboxes_excluded' => $exactGenerationDescendantSecondBboxes !== [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'lastchar_width_decoy_gap_excluded' => ($lastCharLines[0] ?? null) === 'CD',
    'lastchar_real_positioned_gap_preserved' => ($lastCharLines[1] ?? null) === 'C D',
    'lastchar_double_gap_output_excluded' => !str_contains($lastCharPlainText, 'C D' . "\n" . 'C D'),
    'lastchar_styled_bboxes_preserved' => $lastCharSpanBboxes === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'malformed_range_decimal_widths_ignored' => ($malformedRangeLines[0] ?? null) === 'CDEF',
    'malformed_range_real_positioned_gap_preserved' => ($malformedRangeLines[1] ?? null) === 'CD EF',
    'malformed_range_double_gap_output_excluded' => !str_contains($malformedRangePlainText, 'CD EF' . "\n" . 'CD EF'),
    'malformed_range_styled_bboxes_preserved' => $malformedRangeSpanBboxes === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'nested_encoding_width_decoy_excluded' => $nestedEncodingWidthLines === ['ABCD'],
    'nested_encoding_width_false_gap_excluded' => !str_contains($nestedEncodingWidthPlainText, 'AB CD'),
    'nested_encoding_width_top_level_bboxes_preserved' => $nestedEncodingWidthSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'nested_encoding_width_decoy_bboxes_excluded' => $nestedEncodingWidthSpanBboxes !== [[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]],
    'nonfinite_width_current_gap_preserved' => $nonFiniteWidthLines === ['AB CD'],
    'nonfinite_width_plain_text_preserved' => $nonFiniteWidthPlainText === 'AB CD',
    'nonfinite_width_infinite_bbox_excluded' => $nonFiniteWidthBboxesAreFinite,
    'nonfinite_width_styled_bboxes_preserved' => $nonFiniteWidthSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'nonfinite_width_joined_text_excluded' => !str_contains($nonFiniteWidthPlainText, 'ABCD'),
    'huge_finite_width_current_gap_preserved' => $hugeFiniteWidthLines === ['AB CD'],
    'huge_finite_width_plain_text_preserved' => $hugeFiniteWidthPlainText === 'AB CD',
    'huge_finite_width_bbox_overflow_excluded' => $hugeFiniteWidthBboxesAreFinite && $hugeFiniteWidthMaxBboxMagnitude < 1000.0,
    'huge_finite_width_styled_bboxes_preserved' => $hugeFiniteWidthSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'huge_finite_width_joined_text_excluded' => !str_contains($hugeFiniteWidthPlainText, 'ABCD'),
    'negative_width_metric_rejected' => $negativeWidthMetricLines === ['ABCD'],
    'negative_width_metric_plain_text_preserved' => $negativeWidthMetricPlainText === 'ABCD',
    'negative_width_metric_false_gap_excluded' => !str_contains($negativeWidthMetricPlainText, 'AB CD'),
    'negative_width_metric_styled_bboxes_preserved' => $negativeWidthMetricSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_width_metric_reversed_bbox_excluded' => $negativeWidthMetricSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'rotated_text_matrix_horizontal_vector_line_preserved' => $rotatedTextMatrixLines === ['AB CD'],
    'rotated_text_matrix_horizontal_vector_bboxes_preserved' => $rotatedTextMatrixSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'rotated_text_matrix_collapsed_bbox_excluded' => $rotatedTextMatrixSpanBboxes !== [[0.0, 0.0, 1.0, 12.0], [1.0, 0.0, 15.4, 12.0]],
    'rotated_td_vector_gap_lines_preserved' => $rotatedTdLines === ['AB CD', 'AB CD'],
    'rotated_td_vector_gap_plain_text_preserved' => $rotatedTdPlainText === "AB CD\nAB CD",
    'rotated_td_vector_gap_first_bboxes_preserved' => $rotatedTdFirstBboxes === [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'rotated_td_vector_gap_second_bboxes_preserved' => $rotatedTdSecondBboxes === [[0.0, 0.0, 24.0, 12.0], [40.0, 0.0, 64.0, 12.0]],
    'rotated_td_vector_gap_first_line_bbox_preserved' => ($rotatedTdFirstLine['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'rotated_td_vector_gap_second_line_bbox_preserved' => ($rotatedTdSecondLine['bbox'] ?? null) === [0.0, 0.0, 64.0, 12.0],
    'rotated_td_a_only_compaction_excluded' => !str_contains($rotatedTdPlainText, 'ABCD' . "\n" . 'ABCD'),
    'text_object_reset_tj_line_gap_preserved' => $textObjectResetLines === ['AB', 'CD EF'],
    'text_object_reset_styled_span_gap_preserved' => $textObjectResetSpanTexts === ['CD EF'],
    'text_object_reset_styled_bbox_preserved' => $textObjectResetSpanBboxes === [[0.0, 0.0, 60.0, 12.0]],
    'text_object_reset_stale_half_scale_excluded' => $textObjectResetSpanBboxes !== [[0.0, 0.0, 30.0, 12.0]],
    'vertical_w2_text_line_preserved' => $verticalLines === ['VertImport'],
    'vertical_w2_styled_bboxes_preserved' => $verticalSpanBboxes === [[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 18.0]],
    'vertical_horizontal_fallback_excluded' => $verticalSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 60.0, 12.0]],
    'vertical_tj_backtrack_text_gap_preserved' => $verticalTjLines === ['Ve rt'],
    'vertical_tj_backtrack_plain_text_preserved' => $verticalTjPlainText === 'Ve rt',
    'vertical_tj_backtrack_bbox_preserved' => $verticalTjSpanBboxes === [[0.0, 0.0, 12.0, 36.0]],
    'vertical_tj_backtrack_line_bbox_preserved' => ($verticalTjLine['bbox'] ?? null) === [0.0, 0.0, 12.0, 36.0],
    'vertical_tj_final_cursor_collapse_excluded' => $verticalTjSpanBboxes !== [[0.0, 0.0, 12.0, 12.0]],
    'negative_first_cid_w_array_segment_rejected' => $negativeFirstCidLines === ['Wide'],
    'negative_first_cid_w_false_gap_excluded' => !str_contains($negativeFirstCidPlainText, 'Wi de'),
    'negative_first_cid_w_shifted_bboxes_excluded' => $negativeFirstCidSpanBboxes !== [[0.0, 0.0, 6.0, 12.0], [6.0, 0.0, 21.0, 12.0]],
    'negative_first_cid_w_bboxes_preserved' => $negativeFirstCidSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_first_cid_w2_array_segment_rejected' => $negativeFirstW2Lines === ['Vert'],
    'negative_first_cid_w2_false_gap_excluded' => !str_contains($negativeFirstW2PlainText, 'Ve rt'),
    'negative_first_cid_w2_shifted_bboxes_excluded' => $negativeFirstW2SpanBboxes !== [[0.0, 0.0, 12.0, 6.0], [12.0, 0.0, 24.0, 15.0]],
    'negative_first_cid_w2_bboxes_preserved' => $negativeFirstW2SpanBboxes === [[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 24.0]],
    'type3_fontmatrix_widths_false_gap_excluded' => ($type3FontMatrixWidthsLines[0] ?? null) === 'ABCD',
    'type3_fontmatrix_widths_real_gap_preserved' => ($type3FontMatrixWidthsLines[1] ?? null) === 'AB CD',
    'type3_fontmatrix_widths_double_gap_output_excluded' => !str_contains($type3FontMatrixWidthsPlainText, 'AB CD' . "\n" . 'AB CD'),
    'type3_fontmatrix_widths_styled_bboxes_preserved' => $type3FontMatrixWidthsSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'type3_fontmatrix_widths_raw_500_bbox_excluded' => $type3FontMatrixWidthsSpanBboxes !== [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'type3_fontmatrix_vector_false_gap_excluded' => ($type3FontMatrixVectorLines[0] ?? null) === 'ABCD',
    'type3_fontmatrix_vector_real_gap_preserved' => ($type3FontMatrixVectorLines[1] ?? null) === 'AB CD',
    'type3_fontmatrix_vector_double_gap_output_excluded' => !str_contains($type3FontMatrixVectorPlainText, 'AB CD' . "\n" . 'AB CD'),
    'type3_fontmatrix_vector_styled_bboxes_preserved' => $type3FontMatrixVectorSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'type3_fontmatrix_vector_x_only_bbox_excluded' => $type3FontMatrixVectorSpanBboxes !== [[0.0, 0.0, 14.4, 12.0], [14.4, 0.0, 28.8, 12.0]],
    'type3_charproc_fontmatrix_vector_false_gap_excluded' => ($type3CharProcMatrixVectorLines[0] ?? null) === 'ABCD',
    'type3_charproc_fontmatrix_vector_real_gap_preserved' => ($type3CharProcMatrixVectorLines[1] ?? null) === 'EF GH',
    'type3_charproc_fontmatrix_vector_double_gap_output_excluded' => !str_contains($type3CharProcMatrixVectorPlainText, 'AB CD'),
    'type3_charproc_fontmatrix_vector_first_bboxes_preserved' => $type3CharProcMatrixVectorFirstBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'type3_charproc_fontmatrix_vector_second_bboxes_preserved' => $type3CharProcMatrixVectorSecondBboxes === [[0.0, 0.0, 6.0, 12.0], [18.0, 0.0, 24.0, 12.0]],
    'type3_charproc_fontmatrix_vector_x_projection_bbox_excluded' => $type3CharProcMatrixVectorFirstBboxes !== [[0.0, 0.0, 14.4, 12.0], [28.0, 0.0, 42.4, 12.0]],
    'type3_charproc_fontmatrix_vector_payload_excluded' => !str_contains($type3CharProcMatrixVectorPlainText, 'matrix-vector text leak'),
    'span_bboxes' => $spanBboxes,
    'quote_span_bbox' => $quoteSpan['bbox'] ?? null,
    'terminal_tc_lines' => $terminalTcLines,
    'terminal_tc_span_bboxes' => $terminalTcSpanBboxes,
    'terminal_tw_lines' => $terminalTwLines,
    'terminal_tw_plain_text' => $terminalTwPlainText,
    'terminal_tw_first_bboxes' => $terminalTwFirstBboxes,
    'terminal_tw_second_bboxes' => $terminalTwSecondBboxes,
    'relative_td_lines' => $relativeTdLines,
    'relative_td_span_bboxes' => $relativeTdSpanBboxes,
    'relative_td_styled_gap_lines' => $relativeTdStyledGapLines,
    'relative_td_styled_gap_first_bboxes' => $relativeTdStyledGapFirstBboxes,
    'relative_td_styled_gap_second_bboxes' => $relativeTdStyledGapSecondBboxes,
    'scaled_td_lines' => $scaledTdLines,
    'scaled_td_span_bboxes' => $scaledTdSpanBboxes,
    'text_matrix_vertical_scale_lines' => $textMatrixScaleLines,
    'text_matrix_vertical_scale_span_bboxes' => $textMatrixScaleSpanBboxes,
    'negative_text_matrix_lines' => $negativeTextMatrixLines,
    'negative_text_matrix_span_bboxes' => $negativeTextMatrixSpanBboxes,
    'text_rise_lines' => $textRiseLines,
    'text_rise_plain_text' => $textRisePlainText,
    'text_rise_span_bboxes' => $textRiseSpanBboxes,
    'text_rise_line_bbox' => $textRiseLine['bbox'] ?? null,
    'tj_backtrack_lines' => $tjBacktrackLines,
    'tj_backtrack_span_bboxes' => $tjBacktrackSpanBboxes,
    'tj_backtrack_line_bbox' => $tjBacktrackLine['bbox'] ?? null,
    'tj_drawn_extent_lines' => $tjDrawnExtentLines,
    'tj_drawn_extent_plain_text' => $tjDrawnExtentPlainText,
    'tj_drawn_extent_span_bboxes' => $tjDrawnExtentSpanBboxes,
    'tj_drawn_extent_line_bbox' => $tjDrawnExtentLine['bbox'] ?? null,
    'tj_inter_element_spacing_lines' => $tjInterElementSpacingLines,
    'tj_inter_element_spacing_plain_text' => $tjInterElementSpacingPlainText,
    'tj_inter_element_spacing_span_texts' => $tjInterElementSpacingSpanTexts,
    'tj_inter_element_spacing_span_bboxes' => $tjInterElementSpacingSpanBboxes,
    'tj_inter_element_spacing_line_bboxes' => $tjInterElementSpacingLineBboxes,
    'absolute_tm_styled_gap_lines' => $absoluteTmStyledGapLines,
    'absolute_tm_styled_gap_first_bboxes' => $absoluteTmStyledGapFirstBboxes,
    'absolute_tm_styled_gap_second_bboxes' => $absoluteTmStyledGapSecondBboxes,
    'absolute_tm_styled_gap_second_line_bbox' => $absoluteTmStyledGapSecondLine['bbox'] ?? null,
    'cid_absolute_tm_styled_gap_lines' => $cidAbsoluteTmStyledGapLines,
    'cid_absolute_tm_styled_gap_first_bboxes' => $cidAbsoluteTmStyledGapFirstBboxes,
    'cid_absolute_tm_styled_gap_second_bboxes' => $cidAbsoluteTmStyledGapSecondBboxes,
    'cid_absolute_tm_styled_gap_first_line_bbox' => $cidAbsoluteTmStyledGapFirstLine['bbox'] ?? null,
    'negative_tc_backtrack_lines' => $negativeTcBacktrackLines,
    'negative_tc_backtrack_span_bboxes' => $negativeTcBacktrackSpanBboxes,
    'negative_tc_backtrack_line_bbox' => $negativeTcBacktrackLine['bbox'] ?? null,
    'negative_horizontal_scale_td_lines' => $negativeHorizontalScaleTdLines,
    'negative_horizontal_scale_td_span_bboxes' => $negativeHorizontalScaleTdSpanBboxes,
    'sparse_width_lines' => $sparseWidthLines,
    'sparse_width_span_bboxes' => $sparseWidthSpanBboxes,
    'exact_generation_width_lines' => $exactGenerationWidthLines,
    'exact_generation_width_span_bboxes' => $exactGenerationWidthSpanBboxes,
    'exact_generation_descriptor_lines' => $exactGenerationDescriptorLines,
    'exact_generation_descriptor_span_bboxes' => $exactGenerationDescriptorSpanBboxes,
    'exact_generation_descriptor_span_fonts' => $exactGenerationDescriptorSpanFonts,
    'exact_generation_descendant_lines' => $exactGenerationDescendantLines,
    'exact_generation_descendant_first_bboxes' => $exactGenerationDescendantFirstBboxes,
    'exact_generation_descendant_second_bboxes' => $exactGenerationDescendantSecondBboxes,
    'lastchar_lines' => $lastCharLines,
    'lastchar_span_bboxes' => $lastCharSpanBboxes,
    'malformed_range_lines' => $malformedRangeLines,
    'malformed_range_span_bboxes' => $malformedRangeSpanBboxes,
    'nested_encoding_width_lines' => $nestedEncodingWidthLines,
    'nested_encoding_width_span_bboxes' => $nestedEncodingWidthSpanBboxes,
    'nonfinite_width_lines' => $nonFiniteWidthLines,
    'nonfinite_width_span_bboxes' => $nonFiniteWidthSpanBboxes,
    'nonfinite_width_bboxes_are_finite' => $nonFiniteWidthBboxesAreFinite,
    'huge_finite_width_lines' => $hugeFiniteWidthLines,
    'huge_finite_width_span_bboxes' => $hugeFiniteWidthSpanBboxes,
    'huge_finite_width_bboxes_are_finite' => $hugeFiniteWidthBboxesAreFinite,
    'huge_finite_width_max_bbox_magnitude' => $hugeFiniteWidthMaxBboxMagnitude,
    'negative_width_metric_lines' => $negativeWidthMetricLines,
    'negative_width_metric_span_bboxes' => $negativeWidthMetricSpanBboxes,
    'rotated_text_matrix_lines' => $rotatedTextMatrixLines,
    'rotated_text_matrix_span_bboxes' => $rotatedTextMatrixSpanBboxes,
    'rotated_td_lines' => $rotatedTdLines,
    'rotated_td_first_bboxes' => $rotatedTdFirstBboxes,
    'rotated_td_second_bboxes' => $rotatedTdSecondBboxes,
    'text_object_reset_lines' => $textObjectResetLines,
    'text_object_reset_span_texts' => $textObjectResetSpanTexts,
    'text_object_reset_span_bboxes' => $textObjectResetSpanBboxes,
    'vertical_span_bboxes' => $verticalSpanBboxes,
    'vertical_tj_lines' => $verticalTjLines,
    'vertical_tj_span_bboxes' => $verticalTjSpanBboxes,
    'negative_first_cid_lines' => $negativeFirstCidLines,
    'negative_first_cid_span_bboxes' => $negativeFirstCidSpanBboxes,
    'negative_first_w2_lines' => $negativeFirstW2Lines,
    'negative_first_w2_span_bboxes' => $negativeFirstW2SpanBboxes,
    'type3_fontmatrix_widths_lines' => $type3FontMatrixWidthsLines,
    'type3_fontmatrix_widths_span_bboxes' => $type3FontMatrixWidthsSpanBboxes,
    'type3_fontmatrix_vector_lines' => $type3FontMatrixVectorLines,
    'type3_fontmatrix_vector_span_bboxes' => $type3FontMatrixVectorSpanBboxes,
    'type3_charproc_fontmatrix_vector_lines' => $type3CharProcMatrixVectorLines,
    'type3_charproc_fontmatrix_vector_first_bboxes' => $type3CharProcMatrixVectorFirstBboxes,
    'type3_charproc_fontmatrix_vector_second_bboxes' => $type3CharProcMatrixVectorSecondBboxes,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $quoteLines, $terminalTcLines, $terminalTwLines, $relativeTdLines, $relativeTdStyledGapLines, $scaledTdLines, $textMatrixScaleLines, $negativeTextMatrixLines, $textRiseLines, $tjBacktrackLines, $tjDrawnExtentLines, $tjInterElementSpacingLines, $absoluteTmStyledGapLines, $cidAbsoluteTmStyledGapLines, $negativeTcBacktrackLines, $negativeHorizontalScaleTdLines, $sparseWidthLines, $exactGenerationWidthLines, $exactGenerationDescriptorLines, $exactGenerationDescendantLines, $lastCharLines, $malformedRangeLines, $nestedEncodingWidthLines, $nonFiniteWidthLines, $hugeFiniteWidthLines, $negativeWidthMetricLines, $rotatedTextMatrixLines, $rotatedTdLines, $textObjectResetLines, $verticalLines, $verticalTjLines, $negativeFirstCidLines, $negativeFirstW2Lines, $type3FontMatrixWidthsLines, $type3FontMatrixVectorLines, $type3CharProcMatrixVectorLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
