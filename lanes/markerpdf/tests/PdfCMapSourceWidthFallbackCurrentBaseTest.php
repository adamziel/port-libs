<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$cMapSourceWidthFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ZeroPaddedSourceWidth /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ZeroPaddedSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceDefaultWidthFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DefaultSourceWidth /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DefaultSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceIdentityCodeSpaceFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentitySourceWidth /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentitySourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourcePredefinedUcs2FallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUcs2SourceWidth /Encoding /UniJIS-UCS2-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUcs2SourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceIdentityMetricMissFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 108 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityMetricMissFallback /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceIdentityDefaultMetricMissFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 108 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityDefaultMetricMissFallback /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityDefaultMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourcePartialMetricMissFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 102 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityPartialMetricMissFallback /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityPartialMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 250 69 72 250 16706 16706 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceTjAdjustmentGapCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm [<41424344> -1000 <45464748>] TJ ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TjSourceWidthFallback /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /TjSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceVerticalTjAdjustmentGapCurrentBasePdf = static function (): string {
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

    $content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm [<0001000200030004> 1000 <00050006000700080009000A>] TJ ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalTjSourceWidthFallback /Encoding /Identity-V /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalTjSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [1 4 -500 500 880 5 10 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapMalformedVerticalSubtypeSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "6 beginbfchar\n"
        . "<01> <0056>\n"
        . "<02> <0065>\n"
        . "<03> <0072>\n"
        . "<04> <0074>\n"
        . "<05> <0058>\n"
        . "<06> <0059>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 72 690 Tm <00050006> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedVerticalSourceWidth /Encoding /MissingCustom-V /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /BaseFont /MalformedVerticalSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [1 4 -500 500 880 5 6 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceOddHexFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <4142434> Tj '
        . '1 0 0 1 108 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddHexSourceWidthFallback /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OddHexSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [64 67 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapOddSourceKeyPaddingCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<4> <0044>\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <4142434> Tj '
        . '1 0 0 1 108 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddCMapSourceKeyPadding /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OddCMapSourceKeyPadding /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [64 67 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceCodespacePaddingFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CodespacePaddingFallback /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CodespacePaddingFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSourceRepeatedZeroPaddingFallbackCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <00000041000000420000004300000044> Tj '
        . '1 0 0 1 132 720 Tm <00000045000000460000004700000048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /RepeatedZeroPaddingFallback /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /RepeatedZeroPaddingFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapExplicitLongSourceBeatsNarrowCodespaceCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ExplicitLongSourceWidthFallback /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ExplicitLongSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapMalformedMixedWidthBfrangeCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MixedWidthBfrangeSourceWidth /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MixedWidthBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapPredefinedUseCMapSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/Identity-H usecmap\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUseCMapSourceWidth /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUseCMapSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapLateUseCMapSourceWidthCurrentBasePdf = static function (): string {
    $baseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LateBaseSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<20> <27> 14\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LateUseCMapSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<20> <23>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<20> <23> 100\n"
        . "endcidrange\n"
        . "/LateBaseSourceWidth-H usecmap\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<20> <0041>\n"
        . "<21> <0042>\n"
        . "<22> <0043>\n"
        . "<23> <0044>\n"
        . "<24> <0045>\n"
        . "<25> <0046>\n"
        . "<26> <0047>\n"
        . "<27> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <20212223> Tj '
        . '1 0 0 1 120 720 Tm <24252627> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LateUseCMapSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 7 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LateUseCMapSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [18 21 250 100 103 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapCidCharMalformedCodespaceSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
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

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 132 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ExplicitCIDRowsMalformedCodespace /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ExplicitCIDRowsMalformedCodespace /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapCidPartialMetricMissSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /PartialCIDMetricMiss-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 begincidchar\n"
        . "<4142> 900\n"
        . "endcidchar\n"
        . "6 begincidchar\n"
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

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 120 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PartialCIDMetricMiss /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PartialCIDMetricMiss /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [67 68 1000 69 72 250 900 900 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapCidRangeZeroPaddedRemapSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
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

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
        . '1 0 0 1 108 720 Tm <0045004600470048> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ZeroPaddedRemappedCIDRange /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ZeroPaddedRemappedCIDRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /W [1 4 250 5 8 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapCidRangeHighSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /HighRangeSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <03FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<0000> <03FF> 1000\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <03FF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<0300> <0041>\n"
        . "<0301> <0042>\n"
        . "<0302> <0043>\n"
        . "<0303> <0044>\n"
        . "<0304> <0045>\n"
        . "<0305> <0046>\n"
        . "<0306> <0047>\n"
        . "<0307> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <0300030103020303> Tj '
        . '1 0 0 1 132 720 Tm <0304030503060307> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /HighRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /HighRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1768 1771 1000 1772 1775 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapMalformedBroadToUnicodeCodespaceSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 132 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedBroadToUnicodeCodespace /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedBroadToUnicodeCodespace /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapMixedExplicitBroadCodespaceSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
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

    $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <4142434400450046> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MixedExplicitBroadCodespace /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MixedExplicitBroadCodespace /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 70 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapCidNotdefRangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /NotdefRangeSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "1 beginnotdefrange\n"
        . "<20> <27> 100\n"
        . "endnotdefrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<20> <0041>\n"
        . "<21> <0042>\n"
        . "<22> <0043>\n"
        . "<23> <0044>\n"
        . "<24> <0045>\n"
        . "<25> <0046>\n"
        . "<26> <0047>\n"
        . "<27> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <20212223> Tj '
        . '1 0 0 1 132 720 Tm <24252627> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotdefRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 1000 104 107 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapCidNotdefCharSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /NotdefCharSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "8 beginnotdefchar\n"
        . "<20> 100\n"
        . "<21> 101\n"
        . "<22> 102\n"
        . "<23> 103\n"
        . "<24> 104\n"
        . "<25> 105\n"
        . "<26> 106\n"
        . "<27> 107\n"
        . "endnotdefchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<20> <0041>\n"
        . "<21> <0042>\n"
        . "<22> <0043>\n"
        . "<23> <0044>\n"
        . "<24> <0045>\n"
        . "<25> <0046>\n"
        . "<26> <0047>\n"
        . "<27> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <20212223> Tj '
        . '1 0 0 1 132 720 Tm <24252627> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefCharSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotdefCharSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 1000 104 107 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapCidRangeCodespaceSequenceSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /CodespaceSequenceCIDRange-H def\n"
        . "1 begincodespacerange\n"
        . "<3030> <3232>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<3030> <3232> 100\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<3030> <3232>\n"
        . "endcodespacerange\n"
        . "9 beginbfchar\n"
        . "<3030> <0041>\n"
        . "<3031> <0042>\n"
        . "<3032> <0043>\n"
        . "<3130> <0044>\n"
        . "<3131> <0045>\n"
        . "<3132> <0046>\n"
        . "<3230> <0047>\n"
        . "<3231> <0048>\n"
        . "<3232> <0049>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <303030313032> Tj '
        . '1 0 0 1 120 720 Tm <323032313232> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CodespaceSequenceCIDRange /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CodespaceSequenceCIDRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [100 102 1000 106 108 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapToUnicodeCodespaceSequenceBfrangeSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /ToUnicodeCodespaceSequenceBfrange-H def\n"
        . "1 begincodespacerange\n"
        . "<3030> <3232>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<3030> <3232> 100\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<3030> <3232>\n"
        . "endcodespacerange\n"
        . "1 beginbfrange\n"
        . "<3030> <3232> <0041>\n"
        . "endbfrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <303030313032> Tj '
        . '1 0 0 1 120 720 Tm <323032313232> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ToUnicodeCodespaceSequenceBfrange /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ToUnicodeCodespaceSequenceBfrange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [100 102 1000 106 108 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$cMapToUnicodeLateBfcharSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<20> <27>\n"
        . "endcodespacerange\n"
        . "1 beginbfrange\n"
        . "<20> <27> <0030>\n"
        . "endbfrange\n"
        . "8 beginbfchar\n"
        . "<20> <0041>\n"
        . "<21> <0042>\n"
        . "<22> <0043>\n"
        . "<23> <0044>\n"
        . "<24> <0045>\n"
        . "<25> <0046>\n"
        . "<26> <0047>\n"
        . "<27> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <20212223> Tj '
        . '1 0 0 1 132 720 Tm <24252627> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LateBfcharSourceWidth /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LateBfcharSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 35 1000 36 39 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapToUnicodeLateNarrowBfcharSourceWidthCurrentBasePdf = static function (): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfrange\n"
        . "<0000> <FFFF> <2600>\n"
        . "endbfrange\n"
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

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 132 720 Tm <45464748> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LateNarrowBfcharSourceWidth /Encoding /MissingCustom-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LateNarrowBfcharSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 68 1000 69 72 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$cMapSparseCodespaceSourceWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /SparseCodespaceSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<000000> <FF0000>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<000000> <FF0000> 1000\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<000000> <FF0000>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<000000> <0041>\n"
        . "<010000> <0042>\n"
        . "<020000> <0043>\n"
        . "<030000> <0044>\n"
        . "<200000> <0045>\n"
        . "<210000> <0046>\n"
        . "<220000> <0047>\n"
        . "<230000> <0048>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <000000010000020000030000> Tj '
        . '1 0 0 1 120 720 Tm <200000210000220000230000> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SparseCodespaceSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SparseCodespaceSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1000 1003 1000 1032 1035 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'uses zero-padded CMap source widths before CID fallback text gaps on current base' => static function (TestRunner $t) use ($cMapSourceWidthFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceWidthFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses predefined Identity-H source width when ToUnicode declares one-byte codespace before WordPress gaps' => static function (TestRunner $t) use ($cMapSourceIdentityCodeSpaceFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceIdentityCodeSpaceFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses predefined UCS2-H CMap source widths before ToUnicode one-byte fallback gaps on current base' => static function (TestRunner $t) use ($cMapSourcePredefinedUcs2FallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourcePredefinedUcs2FallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps' => static function (TestRunner $t) use ($cMapSourceDefaultWidthFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceDefaultWidthFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 96.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 96.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'falls back to ToUnicode source widths when Identity-H chunks miss explicit CID metrics on current base' => static function (TestRunner $t) use ($cMapSourceIdentityMetricMissFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceIdentityMetricMissFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'falls back to ToUnicode source widths when Identity-H chunks only have default CID metrics on current base' => static function (TestRunner $t) use ($cMapSourceIdentityDefaultMetricMissFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceIdentityDefaultMetricMissFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 96.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 96.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'falls back per source chunk when Identity-H has partial CID metric evidence on current base' => static function (TestRunner $t) use ($cMapSourcePartialMetricMissFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourcePartialMetricMissFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 18.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([18.0, 0.0, 30.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 30.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'inserts TJ adjustment word gap after CMap source-width fallback on current base' => static function (TestRunner $t) use ($cMapSourceTjAdjustmentGapCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceTjAdjustmentGapCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same('ABCD EFGH', $spans[0]['text'] ?? null);
        $t->same([0.0, 0.0, 72.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'preserves TJ source-width adjustment gaps in extracted text runs on current base' => static function (TestRunner $t) use ($cMapSourceTjAdjustmentGapCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceTjAdjustmentGapCurrentBasePdf();

        $t->same(['ABCD EFGH'], $extractor->extractTextRuns($pdf));
        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same('ABCD EFGH', $extractor->extractPlainText($pdf));
        $t->true(!in_array('ABCDEFGH', $extractor->extractTextRuns($pdf), true));
    },
    'preserves vertical TJ source-width adjustment gaps before WordPress text grouping on current base' => static function (TestRunner $t) use ($cMapSourceVerticalTjAdjustmentGapCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceVerticalTjAdjustmentGapCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $span = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

        $t->same(['Vert Import'], $extractor->extractTextLines($pdf));
        $t->same(['Vert Import'], $extractor->extractTextRuns($pdf));
        $t->same('Vert Import', $plainText);
        $t->same("Vert Import\n", $extractor->naiveGetText($pdf));
        $t->same('Vert Import', $span['text'] ?? null);
        $t->same([0.0, 0.0, 12.0, 54.0], $span['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'VertImport'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses vertical W2 source-width evidence before padding bytes create false gaps on current base' => static function (TestRunner $t) use ($cMapMalformedVerticalSubtypeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapMalformedVerticalSubtypeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['VertXY'], $extractor->extractTextLines($pdf));
        $t->same(['Vert', 'XY'], $runs);
        $t->same('VertXY', $plainText);
        $t->same("VertXY\n", $extractor->naiveGetText($pdf));
        $t->same(['Vert', 'XY'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 12.0, 24.0], $spans[0]['bbox'] ?? null);
        $t->same([12.0, 0.0, 24.0, 6.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 24.0, 24.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'Vert XY'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'pads odd-length hex string operands on the right before CMap source-width fallback on current base' => static function (TestRunner $t) use ($cMapSourceOddHexFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceOddHexFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'pads odd-length CMap source keys on the right before source-width fallback on current base' => static function (TestRunner $t) use ($cMapOddSourceKeyPaddingCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapOddSourceKeyPaddingCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABC@'));
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses ToUnicode mapped source bytes before one-byte codespace padding fallback on current base' => static function (TestRunner $t) use ($cMapSourceCodespacePaddingFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceCodespacePaddingFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $extractor->extractTextRuns($pdf));
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'collapses repeated zero-padded source bytes before CMap source-width fallback on current base' => static function (TestRunner $t) use ($cMapSourceRepeatedZeroPaddingFallbackCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSourceRepeatedZeroPaddingFallbackCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'prefers explicit longer ToUnicode source keys over narrow codespace before width fallback on current base' => static function (TestRunner $t) use ($cMapExplicitLongSourceBeatsNarrowCodespaceCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapExplicitLongSourceBeatsNarrowCodespaceCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ZxZyZzZw'));
        $t->true(!str_contains($plainText, 'ZqZrZsZt'));
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'ignores mixed-width ToUnicode bfrange rows before CMap source-width fallback on current base' => static function (TestRunner $t) use ($cMapMalformedMixedWidthBfrangeCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapMalformedMixedWidthBfrangeCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, '0q0r0s0t'));
        $t->true(!str_contains($plainText, '0u0v0w0x'));
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'inherits predefined ToUnicode usecmap source widths before raw byte fallback on current base' => static function (TestRunner $t) use ($cMapPredefinedUseCMapSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapPredefinedUseCMapSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'ignores late CMap usecmap after source ranges before source-width fallback on current base' => static function (TestRunner $t) use ($cMapLateUseCMapSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapLateUseCMapSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 96.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 96.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'prefers explicit CID CMap source rows over malformed broad codespace before width grouping on current base' => static function (TestRunner $t) use ($cMapCidCharMalformedCodespaceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidCharMalformedCodespaceSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'falls back per chunk when Type0 CID CMap has partial metric evidence on current base' => static function (TestRunner $t) use ($cMapCidPartialMetricMissSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidPartialMetricMissSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 36.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([36.0, 0.0, 48.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 48.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses zero-padded remapped CID range source widths before default-width fallback on current base' => static function (TestRunner $t) use ($cMapCidRangeZeroPaddedRemapSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidRangeZeroPaddedRemapSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 12.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([12.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'expands high CID range source rows before source-width fallback on current base' => static function (TestRunner $t) use ($cMapCidRangeHighSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidRangeHighSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'prefers explicit ToUnicode source rows over malformed broad codespace before source-width fallback on current base' => static function (TestRunner $t) use ($cMapMalformedBroadToUnicodeCodespaceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapMalformedBroadToUnicodeCodespaceSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, '䅂'));
        $t->true(!str_contains($plainText, '䍄'));
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses late ToUnicode bfchar rows before earlier bfrange rows for source-width fallback on current base' => static function (TestRunner $t) use ($cMapToUnicodeLateBfcharSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapToUnicodeLateBfcharSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, '0123'));
        $t->true(!str_contains($plainText, '4567'));
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses late narrow ToUnicode bfchar rows before stale broad bfrange source-width fallback on current base' => static function (TestRunner $t) use ($cMapToUnicodeLateNarrowBfcharSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapToUnicodeLateNarrowBfcharSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, '䅂'));
        $t->true(!str_contains($plainText, '䍄'));
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses explicit CMap source rows before broad fallback codespace on mixed current-base text' => static function (TestRunner $t) use ($cMapMixedExplicitBroadCodespaceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapMixedExplicitBroadCodespaceSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEF'], $extractor->extractTextLines($pdf));
        $t->same(['ABCDEF'], $runs);
        $t->same('ABCDEF', $plainText);
        $t->same("ABCDEF\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCDEF'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 54.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([0.0, 0.0, 54.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, '䅂'));
        $t->true(!str_contains($plainText, '䍄'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses Encoding CMap notdef ranges before source-width fallback on current base' => static function (TestRunner $t) use ($cMapCidNotdefRangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidNotdefRangeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses Encoding CMap notdef chars before source-width fallback on current base' => static function (TestRunner $t) use ($cMapCidNotdefCharSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidNotdefCharSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCD EFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCD EFGH', $plainText);
        $t->same("ABCD EFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCDEFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses code-space sequence order for multi-byte CID ranges before source-width fallback on current base' => static function (TestRunner $t) use ($cMapCidRangeCodespaceSequenceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapCidRangeCodespaceSequenceSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABC GHI'], $extractor->extractTextLines($pdf));
        $t->same(['ABC', 'GHI'], $runs);
        $t->same('ABC GHI', $plainText);
        $t->same("ABC GHI\n", $extractor->naiveGetText($pdf));
        $t->same(['ABC', 'GHI'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 36.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([36.0, 0.0, 45.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 45.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCGHI'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses code-space sequence order for ToUnicode bfrange text before source-width fallback on current base' => static function (TestRunner $t) use ($cMapToUnicodeCodespaceSequenceBfrangeSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapToUnicodeCodespaceSequenceBfrangeSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABC GHI'], $extractor->extractTextLines($pdf));
        $t->same(['ABC', 'GHI'], $runs);
        $t->same('ABC GHI', $plainText);
        $t->same("ABC GHI\n", $extractor->naiveGetText($pdf));
        $t->same(['ABC', 'GHI'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 36.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([36.0, 0.0, 45.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 45.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, "ABC\u{0241}\u{0242}\u{0243}"));
        $t->true(!str_contains($plainText, 'ABCGHI'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'uses sparse multi-byte code-space CID sequence order past scan cap before source-width fallback on current base' => static function (TestRunner $t) use ($cMapSparseCodespaceSourceWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $cMapSparseCodespaceSourceWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
        $spans = $line['spans'] ?? [];

        $t->same(['ABCDEFGH'], $extractor->extractTextLines($pdf));
        $t->same(['ABCD', 'EFGH'], $runs);
        $t->same('ABCDEFGH', $plainText);
        $t->same("ABCDEFGH\n", $extractor->naiveGetText($pdf));
        $t->same(['ABCD', 'EFGH'], array_column($spans, 'text'));
        $t->same([0.0, 0.0, 48.0, 12.0], $spans[0]['bbox'] ?? null);
        $t->same([48.0, 0.0, 60.0, 12.0], $spans[1]['bbox'] ?? null);
        $t->same([0.0, 0.0, 60.0, 12.0], $line['bbox'] ?? null);
        $t->true(!str_contains($plainText, 'ABCD EFGH'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
