<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';
require_once __DIR__ . '/../src/PdfTextBlockConverter.php';

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
    . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';
$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<44> <0044>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "<47> <0047>\n"
    . "<48> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SourceWidthFallbackSubset /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SourceWidthFallbackSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n%%EOF";
$predefinedUcs2Pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUcs2SourceWidth /Encoding /UniJIS-UCS2-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUcs2SourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$metricMissContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 108 720 Tm <45464748> Tj ET';
$metricMissPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityMetricMissFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($metricMissContent) . " >>\nstream\n{$metricMissContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$defaultMetricMissPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityDefaultMetricMissFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($metricMissContent) . " >>\nstream\n{$metricMissContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityDefaultMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n%%EOF";
$partialMetricMissContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 102 720 Tm <45464748> Tj ET';
$partialMetricMissPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityPartialMetricMissFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($partialMetricMissContent) . " >>\nstream\n{$partialMetricMissContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityPartialMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 250 69 72 250 16706 16706 1000] >>\nendobj\n%%EOF";
$tjGapContent = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm [<41424344> -1000 <45464748>] TJ ET';
$tjGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TjSourceWidthFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($tjGapContent) . " >>\nstream\n{$tjGapContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /TjSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$verticalTjGapCmap = "/CIDInit /ProcSet findresource begin\n"
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
$verticalTjGapContent = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm [<0001000200030004> 1000 <00050006000700080009000A>] TJ ET';
$verticalTjGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalTjSourceWidthFallback /Encoding /Identity-V /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($verticalTjGapCmap) . " >>\nstream\n{$verticalTjGapCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($verticalTjGapContent) . " >>\nstream\n{$verticalTjGapContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalTjSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [1 4 -500 500 880 5 10 -250 500 880] >>\nendobj\n%%EOF";
$oddHexCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<40> <0044>\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "<47> <0047>\n"
    . "<48> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$oddHexContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <4142434> Tj '
    . '1 0 0 1 108 720 Tm <45464748> Tj ET';
$oddHexPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddHexSourceWidthFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($oddHexCmap) . " >>\nstream\n{$oddHexCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($oddHexContent) . " >>\nstream\n{$oddHexContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OddHexSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [64 67 1000 69 72 250] >>\nendobj\n%%EOF";
$oddCMapSourceKeyCmap = str_replace('<40> <0044>', '<4> <0044>', $oddHexCmap);
$oddCMapSourceKeyPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddCMapSourceKeyPadding /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($oddCMapSourceKeyCmap) . " >>\nstream\n{$oddCMapSourceKeyCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($oddHexContent) . " >>\nstream\n{$oddHexContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OddCMapSourceKeyPadding /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [64 67 1000 69 72 250] >>\nendobj\n%%EOF";
$codespacePaddingContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
    . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';
$codespacePaddingPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CodespacePaddingFallback /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($codespacePaddingContent) . " >>\nstream\n{$codespacePaddingContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CodespacePaddingFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$repeatedZeroPaddingContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <00000041000000420000004300000044> Tj '
    . '1 0 0 1 132 720 Tm <00000045000000460000004700000048> Tj ET';
$repeatedZeroPaddingPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /RepeatedZeroPaddingFallback /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($repeatedZeroPaddingContent) . " >>\nstream\n{$repeatedZeroPaddingContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /RepeatedZeroPaddingFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$explicitLongSourceCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "17 beginbfchar\n"
    . "<00> <005A>\n"
    . "<41> <0078>\n"
    . "<42> <0079>\n"
    . "<43> <007A>\n"
    . "<44> <0077>\n"
    . "<45> <0071>\n"
    . "<46> <0072>\n"
    . "<47> <0073>\n"
    . "<48> <0074>\n"
    . "<0041> <0041>\n"
    . "<0042> <0042>\n"
    . "<0043> <0043>\n"
    . "<0044> <0044>\n"
    . "<0045> <0045>\n"
    . "<0046> <0046>\n"
    . "<0047> <0047>\n"
    . "<0048> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$explicitLongSourcePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ExplicitLongSourceWidthFallback /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($explicitLongSourceCmap) . " >>\nstream\n{$explicitLongSourceCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($codespacePaddingContent) . " >>\nstream\n{$codespacePaddingContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ExplicitLongSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$mixedWidthBfrangeCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<44> <0044>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "<47> <0047>\n"
    . "<48> <0048>\n"
    . "endbfchar\n"
    . "1 beginbfrange\n"
    . "<00> <FFFF> <0030>\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$mixedWidthBfrangePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MixedWidthBfrangeSourceWidth /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($mixedWidthBfrangeCmap) . " >>\nstream\n{$mixedWidthBfrangeCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($codespacePaddingContent) . " >>\nstream\n{$codespacePaddingContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MixedWidthBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$predefinedUseCMapCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/Identity-H usecmap\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$predefinedUseCMapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUseCMapSourceWidth /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($predefinedUseCMapCmap) . " >>\nstream\n{$predefinedUseCMapCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($codespacePaddingContent) . " >>\nstream\n{$codespacePaddingContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUseCMapSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$cidCharMalformedCodespaceEncodingCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /ExplicitCIDRowsMalformedCodespace-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "8 begincidchar\n"
    . "<41> 65\n"
    . "<42> 66\n"
    . "<43> 67\n"
    . "<44> 68\n"
    . "<45> 69\n"
    . "<46> 70\n"
    . "<47> 71\n"
    . "<48> 72\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$cidCharMalformedCodespaceContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 132 720 Tm <45464748> Tj ET';
$cidCharMalformedCodespacePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ExplicitCIDRowsMalformedCodespace /Encoding 3 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cidCharMalformedCodespaceEncodingCmap) . " >>\nstream\n{$cidCharMalformedCodespaceEncodingCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($cidCharMalformedCodespaceContent) . " >>\nstream\n{$cidCharMalformedCodespaceContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ExplicitCIDRowsMalformedCodespace /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n%%EOF";
$cidRangeZeroPaddedEncodingCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /ZeroPaddedRemappedCIDRange-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<41> <48> 1\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$cidRangeZeroPaddedContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
    . '1 0 0 1 108 720 Tm <0045004600470048> Tj ET';
$cidRangeZeroPaddedPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ZeroPaddedRemappedCIDRange /Encoding 3 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cidRangeZeroPaddedEncodingCmap) . " >>\nstream\n{$cidRangeZeroPaddedEncodingCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($cidRangeZeroPaddedContent) . " >>\nstream\n{$cidRangeZeroPaddedContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ZeroPaddedRemappedCIDRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /W [1 4 250 5 8 1000] >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$predefinedUcs2Lines = $extractor->extractTextLines($predefinedUcs2Pdf);
$predefinedUcs2Pages = $extractor->extractStyledTextPages($predefinedUcs2Pdf);
$predefinedUcs2Spans = $predefinedUcs2Pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$metricMissLines = $extractor->extractTextLines($metricMissPdf);
$metricMissPages = $extractor->extractStyledTextPages($metricMissPdf);
$metricMissSpans = $metricMissPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$defaultMetricMissLines = $extractor->extractTextLines($defaultMetricMissPdf);
$defaultMetricMissPages = $extractor->extractStyledTextPages($defaultMetricMissPdf);
$defaultMetricMissSpans = $defaultMetricMissPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$partialMetricMissLines = $extractor->extractTextLines($partialMetricMissPdf);
$partialMetricMissPages = $extractor->extractStyledTextPages($partialMetricMissPdf);
$partialMetricMissSpans = $partialMetricMissPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$tjGapLines = $extractor->extractTextLines($tjGapPdf);
$tjGapRuns = $extractor->extractTextRuns($tjGapPdf);
$tjGapPages = $extractor->extractStyledTextPages($tjGapPdf);
$tjGapSpans = $tjGapPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$verticalTjGapLines = $extractor->extractTextLines($verticalTjGapPdf);
$verticalTjGapRuns = $extractor->extractTextRuns($verticalTjGapPdf);
$verticalTjGapPages = $extractor->extractStyledTextPages($verticalTjGapPdf);
$verticalTjGapSpans = $verticalTjGapPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$oddHexLines = $extractor->extractTextLines($oddHexPdf);
$oddHexPages = $extractor->extractStyledTextPages($oddHexPdf);
$oddHexSpans = $oddHexPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$oddCMapSourceKeyLines = $extractor->extractTextLines($oddCMapSourceKeyPdf);
$oddCMapSourceKeyPages = $extractor->extractStyledTextPages($oddCMapSourceKeyPdf);
$oddCMapSourceKeySpans = $oddCMapSourceKeyPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$codespacePaddingLines = $extractor->extractTextLines($codespacePaddingPdf);
$codespacePaddingRuns = $extractor->extractTextRuns($codespacePaddingPdf);
$codespacePaddingPages = $extractor->extractStyledTextPages($codespacePaddingPdf);
$codespacePaddingSpans = $codespacePaddingPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$repeatedZeroPaddingLines = $extractor->extractTextLines($repeatedZeroPaddingPdf);
$repeatedZeroPaddingRuns = $extractor->extractTextRuns($repeatedZeroPaddingPdf);
$repeatedZeroPaddingPages = $extractor->extractStyledTextPages($repeatedZeroPaddingPdf);
$repeatedZeroPaddingSpans = $repeatedZeroPaddingPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$explicitLongSourceLines = $extractor->extractTextLines($explicitLongSourcePdf);
$explicitLongSourceRuns = $extractor->extractTextRuns($explicitLongSourcePdf);
$explicitLongSourcePages = $extractor->extractStyledTextPages($explicitLongSourcePdf);
$explicitLongSourceSpans = $explicitLongSourcePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$mixedWidthBfrangeLines = $extractor->extractTextLines($mixedWidthBfrangePdf);
$mixedWidthBfrangeRuns = $extractor->extractTextRuns($mixedWidthBfrangePdf);
$mixedWidthBfrangePages = $extractor->extractStyledTextPages($mixedWidthBfrangePdf);
$mixedWidthBfrangeSpans = $mixedWidthBfrangePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$predefinedUseCMapLines = $extractor->extractTextLines($predefinedUseCMapPdf);
$predefinedUseCMapRuns = $extractor->extractTextRuns($predefinedUseCMapPdf);
$predefinedUseCMapPages = $extractor->extractStyledTextPages($predefinedUseCMapPdf);
$predefinedUseCMapSpans = $predefinedUseCMapPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$cidCharMalformedCodespaceLines = $extractor->extractTextLines($cidCharMalformedCodespacePdf);
$cidCharMalformedCodespaceRuns = $extractor->extractTextRuns($cidCharMalformedCodespacePdf);
$cidCharMalformedCodespacePages = $extractor->extractStyledTextPages($cidCharMalformedCodespacePdf);
$cidCharMalformedCodespaceSpans = $cidCharMalformedCodespacePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$cidRangeZeroPaddedLines = $extractor->extractTextLines($cidRangeZeroPaddedPdf);
$cidRangeZeroPaddedRuns = $extractor->extractTextRuns($cidRangeZeroPaddedPdf);
$cidRangeZeroPaddedPages = $extractor->extractStyledTextPages($cidRangeZeroPaddedPdf);
$cidRangeZeroPaddedSpans = $cidRangeZeroPaddedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo "<!-- markerpdf-cmap-source-width-fallback-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'predefined Identity-H/UCS2-H source-width fallback with CIDFont default, metric-miss ToUnicode width fallback, partial metric-miss chunk fallback, DW-only metric-miss fallback, horizontal and vertical TJ adjustment gap recovery, odd hex operand and CMap source-key right-padding, one-byte ToUnicode codespace padding fallback, repeated zero-padded source-byte collapse, explicit longer source-key precedence, malformed mixed-width bfrange rejection, predefined ToUnicode usecmap source-code inheritance, explicit CID CMap row recovery over malformed broad codespace, and zero-padded remapped CID range source widths before Gutenberg paragraph rendering',
    'default_width_source_fallback_applied' => $lines === ['ABCD EFGH'],
    'predefined_identity_source_width_applied' => $lines === ['ABCD EFGH'],
    'padding_bytes_not_counted_as_glyphs' => ($spans[0]['bbox'][2] ?? null) === 48.0,
    'predefined_ucs2_source_width_applied' => $predefinedUcs2Lines === ['ABCD EFGH'],
    'predefined_ucs2_false_join_excluded' => !in_array('ABCDEFGH', $predefinedUcs2Lines, true),
    'predefined_ucs2_span_widths' => array_column($predefinedUcs2Spans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'identity_metric_miss_tounicode_widths_applied' => $metricMissLines === ['ABCDEFGH'],
    'identity_metric_miss_false_gap_excluded' => !in_array('ABCD EFGH', $metricMissLines, true),
    'identity_metric_miss_span_widths' => array_column($metricMissSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'identity_default_metric_miss_tounicode_widths_applied' => $defaultMetricMissLines === ['ABCDEFGH'],
    'identity_default_metric_miss_false_gap_excluded' => !in_array('ABCD EFGH', $defaultMetricMissLines, true),
    'identity_default_metric_miss_span_widths' => array_column($defaultMetricMissSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 96.0, 12.0]],
    'identity_partial_metric_miss_tounicode_chunks_applied' => $partialMetricMissLines === ['ABCD EFGH'],
    'identity_partial_metric_miss_direct_cid_width_preserved' => ($partialMetricMissSpans[0]['bbox'] ?? null) === [0.0, 0.0, 18.0, 12.0],
    'identity_partial_metric_miss_false_join_excluded' => !in_array('ABCDEFGH', $partialMetricMissLines, true),
    'identity_partial_metric_miss_span_widths' => array_column($partialMetricMissSpans, 'bbox') === [[0.0, 0.0, 18.0, 12.0], [18.0, 0.0, 30.0, 12.0]],
    'tj_adjustment_source_width_gap_applied' => $tjGapLines === ['ABCD EFGH'],
    'tj_adjustment_source_width_runs_gap_applied' => $tjGapRuns === ['ABCD EFGH'],
    'tj_adjustment_false_join_excluded' => !in_array('ABCDEFGH', $tjGapLines, true),
    'tj_adjustment_runs_false_join_excluded' => !in_array('ABCDEFGH', $tjGapRuns, true),
    'tj_adjustment_span_bbox_preserved' => ($tjGapSpans[0]['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'vertical_tj_adjustment_source_width_gap_applied' => $verticalTjGapLines === ['Vert Import'],
    'vertical_tj_adjustment_source_width_runs_gap_applied' => $verticalTjGapRuns === ['Vert Import'],
    'vertical_tj_adjustment_false_join_excluded' => !in_array('VertImport', $verticalTjGapLines, true),
    'vertical_tj_adjustment_runs_false_join_excluded' => !in_array('VertImport', $verticalTjGapRuns, true),
    'vertical_tj_adjustment_span_bbox_preserved' => ($verticalTjGapSpans[0]['bbox'] ?? null) === [0.0, 0.0, 12.0, 54.0],
    'odd_hex_operand_right_padding_applied' => $oddHexLines === ['ABCDEFGH'],
    'odd_hex_operand_false_gap_excluded' => !in_array('ABCD EFGH', $oddHexLines, true),
    'odd_hex_operand_span_widths' => array_column($oddHexSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'odd_cmap_source_key_right_padding_applied' => $oddCMapSourceKeyLines === ['ABCDEFGH'],
    'odd_cmap_source_key_false_at_sign_excluded' => !in_array('ABC@ EFGH', $oddCMapSourceKeyLines, true),
    'odd_cmap_source_key_false_gap_excluded' => !in_array('ABCD EFGH', $oddCMapSourceKeyLines, true),
    'odd_cmap_source_key_span_widths' => array_column($oddCMapSourceKeySpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'codespace_padding_tounicode_source_widths_applied' => $codespacePaddingLines === ['ABCD EFGH'],
    'codespace_padding_runs_preserved' => $codespacePaddingRuns === ['ABCD', 'EFGH'],
    'codespace_padding_false_join_excluded' => !in_array('ABCDEFGH', $codespacePaddingLines, true),
    'codespace_padding_span_widths' => array_column($codespacePaddingSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'repeated_zero_padding_source_widths_applied' => $repeatedZeroPaddingLines === ['ABCD EFGH'],
    'repeated_zero_padding_runs_preserved' => $repeatedZeroPaddingRuns === ['ABCD', 'EFGH'],
    'repeated_zero_padding_false_join_excluded' => !in_array('ABCDEFGH', $repeatedZeroPaddingLines, true),
    'repeated_zero_padding_span_widths' => array_column($repeatedZeroPaddingSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'explicit_long_source_key_precedes_narrow_codespace' => $explicitLongSourceLines === ['ABCD EFGH'],
    'explicit_long_source_runs_preserved' => $explicitLongSourceRuns === ['ABCD', 'EFGH'],
    'explicit_long_source_decoy_prefix_excluded' => !in_array('ZxZyZzZw ZqZrZsZt', $explicitLongSourceLines, true),
    'explicit_long_source_span_widths' => array_column($explicitLongSourceSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'mixed_width_bfrange_ignored' => $mixedWidthBfrangeLines === ['ABCD EFGH'],
    'mixed_width_bfrange_runs_preserved' => $mixedWidthBfrangeRuns === ['ABCD', 'EFGH'],
    'mixed_width_bfrange_decoy_range_excluded' => !in_array('0q0r0s0t0u0v0w0x', $mixedWidthBfrangeLines, true),
    'mixed_width_bfrange_span_widths' => array_column($mixedWidthBfrangeSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'predefined_tounicode_usecmap_source_widths_applied' => $predefinedUseCMapLines === ['ABCD EFGH'],
    'predefined_tounicode_usecmap_runs_preserved' => $predefinedUseCMapRuns === ['ABCD', 'EFGH'],
    'predefined_tounicode_usecmap_raw_bytes_excluded' => !str_contains(implode('', $predefinedUseCMapLines), "\0"),
    'predefined_tounicode_usecmap_span_widths' => array_column($predefinedUseCMapSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'explicit_cid_rows_codespace_recovered' => $cidCharMalformedCodespaceLines === ['ABCD EFGH'],
    'explicit_cid_rows_codespace_runs_preserved' => $cidCharMalformedCodespaceRuns === ['ABCD', 'EFGH'],
    'explicit_cid_rows_codespace_combined_chunk_width_excluded' => array_column($cidCharMalformedCodespaceSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'explicit_cid_rows_codespace_false_join_excluded' => !in_array('ABCDEFGH', $cidCharMalformedCodespaceLines, true),
    'zero_padded_cid_range_remap_widths_applied' => $cidRangeZeroPaddedLines === ['ABCD EFGH'],
    'zero_padded_cid_range_runs_preserved' => $cidRangeZeroPaddedRuns === ['ABCD', 'EFGH'],
    'zero_padded_cid_range_default_width_excluded' => array_column($cidRangeZeroPaddedSpans, 'bbox') === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 60.0, 12.0]],
    'zero_padded_cid_range_false_join_excluded' => !in_array('ABCDEFGH', $cidRangeZeroPaddedLines, true),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $predefinedUcs2Lines, $metricMissLines, $defaultMetricMissLines, $partialMetricMissLines, $tjGapLines, $verticalTjGapLines, $oddHexLines, $oddCMapSourceKeyLines, $codespacePaddingLines, $repeatedZeroPaddingLines, $explicitLongSourceLines, $mixedWidthBfrangeLines, $predefinedUseCMapLines, $cidCharMalformedCodespaceLines, $cidRangeZeroPaddedLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
