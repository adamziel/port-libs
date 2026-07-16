<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$baseEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<0001> <000A> 40\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$derivedEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincidrange\n"
    . "<0014> <001B> 60\n"
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
    . "18 beginbfchar\n"
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
    . "<0014> <0044>\n"
    . "<0015> <0061>\n"
    . "<0016> <0074>\n"
    . "<0017> <0061>\n"
    . "<0018> <0046>\n"
    . "<0019> <006C>\n"
    . "<001A> <006F>\n"
    . "<001B> <0077>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fv 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 72 696 Tm <00050006000700080009000A> Tj '
    . '1 0 0 1 96 720 Tm <0014001500160017> Tj '
    . '1 0 0 1 96 708 Tm <00180019001A001B> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UseCMapVerticalSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /WPVerticalDerived-V /UseCMap /WPVerticalBase-V /Length " . strlen($derivedEncodingCMap) . " >>\nstream\n{$derivedEncodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /UseCMapVerticalSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 49 -500 500 880 60 67 -250 500 880] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /CMap /CMapName /WPVerticalBase-V /WMode 1 /Length " . strlen($baseEncodingCMap) . " >>\nstream\n{$baseEncodingCMap}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-font-cmap-usecmap-vertical-width-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-cmap-usecmap-vertical-width-currentbase',
    'source' => 'native-pdf-cmap-stream-dictionary-usecmap-vertical-width-boundary',
    'font_width_sources' => ['CMap stream dictionary /UseCMap', 'base CMap stream dictionary /WMode', 'Descendant CIDFont /W2'],
    'dictionary_usecmap_applied' => str_contains($plainText, 'VertImport') && in_array('Vert', $runs, true),
    'vertical_wmode_inherited' => $plainText === "VertImport\nDataFlow",
    'base_cid_widths_selected' => str_contains($plainText, 'VertImport') && !str_contains($plainText, 'Vert Import'),
    'derived_cid_widths_selected' => str_contains($plainText, 'DataFlow') && !str_contains($plainText, 'Data Flow'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
