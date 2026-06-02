<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontType0CMapDescriptorWidthCurrentBasePdf = static function (): string {
    $wideEncoding = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DirectWide-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<01> <09> 40\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $thinEncoding = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DirectThin-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<01> <09> 60\n"
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
        . "9 beginbfchar\n"
        . "<01> <0054>\n"
        . "<02> <0068>\n"
        . "<03> <0069>\n"
        . "<04> <006E>\n"
        . "<05> <0054>\n"
        . "<06> <0065>\n"
        . "<07> <0078>\n"
        . "<08> <0074>\n"
        . "<09> <0021>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fthin 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj '
        . '1 0 0 1 96 720 Tm <05060708> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << "
        . "/Fwide << /Type /Font /Subtype /Type0 /BaseFont /DirectWide /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 7 0 R >> "
        . "/Fthin << /Type /Font /Subtype /Type0 /BaseFont /DirectThin /Encoding 5 0 R /DescendantFonts [6 0 R] /ToUnicode 7 0 R >> "
        . ">> >> /Contents 8 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideEncoding) . " >>\nstream\n{$wideEncoding}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectWide /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /FontDescriptor 9 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($thinEncoding) . " >>\nstream\n{$thinEncoding}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectThin /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 /FontDescriptor 10 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /FontDescriptor /FontName /DirectWideSerif /Flags 34 >>\nendobj\n"
        . "10 0 obj\n<< /Type /FontDescriptor /FontName /DirectThinSerif /Flags 34 >>\nendobj\n%%EOF";
};

return [
    'uses direct Type0 resource CMap descriptor widths before current-base WordPress text gaps' => static function (TestRunner $t) use ($fontType0CMapDescriptorWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontType0CMapDescriptorWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $pages = $extractor->extractStyledTextPages($pdf);
        $firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

        $t->same(['Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("Thin Text", $plainText);
        $t->same("Thin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plainText));
        $t->same('DirectThinSerif_serif_non_symbolic', $firstSpan['font'] ?? null);
        $t->same(34, $firstSpan['font_flags'] ?? null);
    },
];
