<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPIndirectWidthDiff-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "17 begincidchar\n"
    . "<01> 40\n"
    . "<02> 41\n"
    . "<03> 42\n"
    . "<04> 43\n"
    . "<05> 44\n"
    . "<06> 45\n"
    . "<07> 46\n"
    . "<08> 47\n"
    . "<09> 48\n"
    . "<14> 60\n"
    . "<15> 61\n"
    . "<16> 62\n"
    . "<17> 63\n"
    . "<18> 64\n"
    . "<19> 65\n"
    . "<1A> 66\n"
    . "<1B> 67\n"
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
    . "17 beginbfchar\n"
    . "<01> <0057>\n"
    . "<02> <0069>\n"
    . "<03> <0064>\n"
    . "<04> <0065>\n"
    . "<05> <0042>\n"
    . "<06> <006C>\n"
    . "<07> <006F>\n"
    . "<08> <0063>\n"
    . "<09> <006B>\n"
    . "<14> <0054>\n"
    . "<15> <0068>\n"
    . "<16> <0069>\n"
    . "<17> <006E>\n"
    . "<18> <0054>\n"
    . "<19> <0065>\n"
    . "<1A> <0078>\n"
    . "<1B> <0074>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj 1 0 0 1 118 720 Tm <0506070809> Tj '
    . 'T* 1 0 0 1 72 704 Tm <14151617> Tj 1 0 0 1 96 704 Tm <18191A1B> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectWidthDiffSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectWidthDiffSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W 7 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "7 0 obj\n[40 [1000 1000 1000 1000 1000 1000 1000 1000 1000] 60 67 250]\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type0-indirect-w-widths ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type0-indirect-cidfont-w-width-differences',
    'font_width_sources' => ['Type0 /Encoding CMap begincidchar', 'Descendant CIDFont indirect /W', 'Descendant CIDFont /DW fallback'],
    'resolves_indirect_w_array' => $plainText === "WideBlock\nThin Text",
    'wide_cid_widths_preserve_joined_word' => str_contains($plainText, 'WideBlock'),
    'narrow_cid_widths_preserve_word_gap' => str_contains($plainText, 'Thin Text'),
    'default_width_fallback_excluded' => !str_contains($plainText, 'Wide Block') && !str_contains($plainText, 'ThinText'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
