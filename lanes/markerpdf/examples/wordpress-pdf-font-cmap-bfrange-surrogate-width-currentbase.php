<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPBfrangeSurrogateCID-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "3 begincidrange\n"
    . "<0100> <0102> 900\n"
    . "<0300> <0300> 903\n"
    . "<0200> <0207> 1000\n"
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
    . "3 beginbfrange\n"
    . "<0100> <0102> <D83DDE000049006D>\n"
    . "<0300> <0300> [<D83DDE03>]\n"
    . "<0200> <0207> [<0044> <0061> <0074> <0061> <0046> <006C> <006F> <0077>]\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <010001010102> Tj '
    . '1 0 0 1 122 720 Tm <0300> Tj '
    . 'T* 1 0 0 1 72 704 Tm <0200020102020203> Tj '
    . '1 0 0 1 96 704 Tm <0204020502060207> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /BfrangeSurrogateCIDSubset /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /BfrangeSurrogateCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [900 903 1000 1000 1007 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expectedSurrogateLine = "\u{1F600}Im\u{1F600}In\u{1F600}Io \u{1F603}";

echo '<!-- markerpdf-font-cmap-bfrange-surrogate-width-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-cmap-bfrange-surrogate-width-currentbase',
    'source' => 'native-pdf-tounicode-bfrange-surrogate-target-cid-width-boundary',
    'font_width_sources' => ['ToUnicode beginbfrange surrogate-pair targets', 'Type0 /Encoding CMap CIDs', 'Descendant CIDFont /W'],
    'long_bfrange_surrogate_targets_decoded' => str_contains($plainText, "\u{1F600}Im") && str_contains($plainText, "\u{1F600}In"),
    'array_bfrange_surrogate_target_decoded' => str_contains($plainText, "\u{1F603}"),
    'cid_width_gap_preserved' => str_contains($plainText, 'Data Flow') && !str_contains($plainText, 'DataFlow'),
    'surrogate_line_gap_preserved' => str_contains($plainText, $expectedSurrogateLine),
    'nul_bytes_removed' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
