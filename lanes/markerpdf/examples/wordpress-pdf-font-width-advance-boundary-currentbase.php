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

$negativeTcBacktrackContent = 'BT /FnegTc 12 Tf -30 Tc 1 0 0 1 72 720 Tm <4142> Tj ET';
$negativeTcBacktrackPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /FnegTc 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeTcBacktrack /Encoding 6 0 R /FirstChar 65 /LastChar 66 /Widths [1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeTcBacktrackContent) . " >>\nstream\n{$negativeTcBacktrackContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B] >>\nendobj\n%%EOF";

$sparseWidthContent = 'BT /Fsparse 12 Tf 1 0 0 1 72 720 Tm <43> Tj 1 0 0 1 87 720 Tm <44> Tj ET';
$sparseWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsparse 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+SparseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 99 0 R 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($sparseWidthContent) . " >>\nstream\n{$sparseWidthContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

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

$rotatedTextMatrixContent = 'BT /Frot 12 Tf '
    . '0 1 -1 0 72 720 Tm <4142> Tj '
    . '0.6 0.8 0 1 96 720 Tm <4344> Tj ET';
$rotatedTextMatrixPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frot 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RotatedAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($rotatedTextMatrixContent) . " >>\nstream\n{$rotatedTextMatrixContent}\nendstream\nendobj\n"
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
$negativeTcBacktrackLines = $extractor->extractTextLines($negativeTcBacktrackPdf);
$negativeTcBacktrackPages = $extractor->extractStyledTextPages($negativeTcBacktrackPdf);
$negativeTcBacktrackLine = $negativeTcBacktrackPages[0]['blocks'][0]['lines'][0] ?? [];
$negativeTcBacktrackSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeTcBacktrackLine['spans'] ?? []
);
$sparseWidthLines = $extractor->extractTextLines($sparseWidthPdf);
$sparseWidthPlainText = implode("\n", $sparseWidthLines);
$sparseWidthPages = $extractor->extractStyledTextPages($sparseWidthPdf);
$sparseWidthSpans = $sparseWidthPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$sparseWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $sparseWidthSpans
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
$rotatedTextMatrixLines = $extractor->extractTextLines($rotatedTextMatrixPdf);
$rotatedTextMatrixPages = $extractor->extractStyledTextPages($rotatedTextMatrixPdf);
$rotatedTextMatrixLine = $rotatedTextMatrixPages[0]['blocks'][0]['lines'][0] ?? [];
$rotatedTextMatrixSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $rotatedTextMatrixLine['spans'] ?? []
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

echo '<!-- markerpdf-font-width-advance-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-width-advance-boundary-currentbase',
    'source' => 'native-pdf-simple-font-average-positive-lastchar-malformed-range-quote-relative-td-styled-gap-scaled-td-text-matrix-vertical-negative-rotated-horizontal-vector-text-object-reset-text-rise-tj-drawn-extent-vertical-width-vertical-tj-negative-tc-type3-fontmatrix-width-and-type3-fontmatrix-vector-advance',
    'average_width_preserves_joined_word' => str_contains($plainText, 'WideBlock'),
    'generic_500_width_gap_excluded' => !str_contains($plainText, 'Wide Block'),
    'narrow_positioned_gap_still_preserved' => str_contains($plainText, 'Blo ck'),
    'quote_operator_text_lines_preserved' => $quoteLines === ['Lead', 'A B'],
    'quote_operator_spacing_styled_bbox_preserved' => ($quoteSpan['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'terminal_tc_uses_cursor_advance_for_td_gap' => ($terminalTcLines[0] ?? null) === 'ABCD',
    'terminal_tc_larger_gap_still_preserved' => ($terminalTcLines[1] ?? null) === 'AB CD',
    'terminal_tc_drawn_bbox_excludes_terminal_spacing' => $terminalTcSpanBboxes === [[0.0, 0.0, 30.0, 12.0], [30.0, 0.0, 60.0, 12.0]],
    'terminal_tc_false_gap_excluded' => !str_contains($terminalTcPlainText, 'AB CD' . "\n" . 'AB CD'),
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
    'negative_tc_backtrack_text_preserved' => $negativeTcBacktrackLines === ['AB'],
    'negative_tc_backtrack_span_bbox_preserved' => $negativeTcBacktrackSpanBboxes === [[0.0, 0.0, 30.0, 12.0]],
    'negative_tc_backtrack_line_bbox_preserved' => ($negativeTcBacktrackLine['bbox'] ?? null) === [0.0, 0.0, 30.0, 12.0],
    'negative_tc_final_cursor_collapse_excluded' => $negativeTcBacktrackSpanBboxes !== [[0.0, 0.0, 6.0, 12.0]],
    'unresolved_width_slot_preserved' => $sparseWidthLines === ['CD'],
    'unresolved_width_false_gap_excluded' => !str_contains($sparseWidthPlainText, 'C D'),
    'unresolved_width_slot_bboxes_preserved' => $sparseWidthSpanBboxes === [[0.0, 0.0, 9.0, 12.0], [9.0, 0.0, 12.0, 12.0]],
    'lastchar_width_decoy_gap_excluded' => ($lastCharLines[0] ?? null) === 'CD',
    'lastchar_real_positioned_gap_preserved' => ($lastCharLines[1] ?? null) === 'C D',
    'lastchar_double_gap_output_excluded' => !str_contains($lastCharPlainText, 'C D' . "\n" . 'C D'),
    'lastchar_styled_bboxes_preserved' => $lastCharSpanBboxes === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'malformed_range_decimal_widths_ignored' => ($malformedRangeLines[0] ?? null) === 'CDEF',
    'malformed_range_real_positioned_gap_preserved' => ($malformedRangeLines[1] ?? null) === 'CD EF',
    'malformed_range_double_gap_output_excluded' => !str_contains($malformedRangePlainText, 'CD EF' . "\n" . 'CD EF'),
    'malformed_range_styled_bboxes_preserved' => $malformedRangeSpanBboxes === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'rotated_text_matrix_horizontal_vector_line_preserved' => $rotatedTextMatrixLines === ['AB CD'],
    'rotated_text_matrix_horizontal_vector_bboxes_preserved' => $rotatedTextMatrixSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'rotated_text_matrix_collapsed_bbox_excluded' => $rotatedTextMatrixSpanBboxes !== [[0.0, 0.0, 1.0, 12.0], [1.0, 0.0, 15.4, 12.0]],
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
    'span_bboxes' => $spanBboxes,
    'quote_span_bbox' => $quoteSpan['bbox'] ?? null,
    'terminal_tc_lines' => $terminalTcLines,
    'terminal_tc_span_bboxes' => $terminalTcSpanBboxes,
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
    'negative_tc_backtrack_lines' => $negativeTcBacktrackLines,
    'negative_tc_backtrack_span_bboxes' => $negativeTcBacktrackSpanBboxes,
    'negative_tc_backtrack_line_bbox' => $negativeTcBacktrackLine['bbox'] ?? null,
    'sparse_width_lines' => $sparseWidthLines,
    'sparse_width_span_bboxes' => $sparseWidthSpanBboxes,
    'lastchar_lines' => $lastCharLines,
    'lastchar_span_bboxes' => $lastCharSpanBboxes,
    'malformed_range_lines' => $malformedRangeLines,
    'malformed_range_span_bboxes' => $malformedRangeSpanBboxes,
    'rotated_text_matrix_lines' => $rotatedTextMatrixLines,
    'rotated_text_matrix_span_bboxes' => $rotatedTextMatrixSpanBboxes,
    'text_object_reset_lines' => $textObjectResetLines,
    'text_object_reset_span_texts' => $textObjectResetSpanTexts,
    'text_object_reset_span_bboxes' => $textObjectResetSpanBboxes,
    'vertical_span_bboxes' => $verticalSpanBboxes,
    'vertical_tj_lines' => $verticalTjLines,
    'vertical_tj_span_bboxes' => $verticalTjSpanBboxes,
    'type3_fontmatrix_widths_lines' => $type3FontMatrixWidthsLines,
    'type3_fontmatrix_widths_span_bboxes' => $type3FontMatrixWidthsSpanBboxes,
    'type3_fontmatrix_vector_lines' => $type3FontMatrixVectorLines,
    'type3_fontmatrix_vector_span_bboxes' => $type3FontMatrixVectorSpanBboxes,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $quoteLines, $terminalTcLines, $relativeTdLines, $relativeTdStyledGapLines, $scaledTdLines, $textMatrixScaleLines, $negativeTextMatrixLines, $textRiseLines, $tjBacktrackLines, $tjDrawnExtentLines, $negativeTcBacktrackLines, $sparseWidthLines, $lastCharLines, $malformedRangeLines, $rotatedTextMatrixLines, $textObjectResetLines, $verticalLines, $verticalTjLines, $type3FontMatrixWidthsLines, $type3FontMatrixVectorLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
