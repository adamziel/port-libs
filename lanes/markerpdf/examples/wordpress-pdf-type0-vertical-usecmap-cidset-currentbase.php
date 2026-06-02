<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fv 12 Tf '
    . '1 0 0 1 72 720 Tm <0041> Tj '
    . '1 0 0 1 72 702 Tm <0057006F00720064> Tj '
    . '1 0 0 1 96 720 Tm <0056006500720074> Tj '
    . '1 0 0 1 96 672 Tm <0049006D0070006F00720074> Tj ET';

$cidSetBytes = str_repeat("\0", 15);
foreach ([0x44, 0x49, 0x56, 0x57, 0x64, 0x65, 0x6d, 0x6f, 0x70, 0x72, 0x74] as $cid) {
    $byteIndex = intdiv($cid, 8);
    $cidSetBytes[$byteIndex] = chr(ord($cidSetBytes[$byteIndex]) | (1 << (7 - ($cid % 8))));
}
$compressedCidSet = gzcompress($cidSetBytes);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused Type0 vertical UseCMap CIDSet fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /Type0VerticalUseCMapSubset /Encoding 3 0 R /DescendantFonts [4 0 R] >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /Type0VerticalUseCMapDerived-V /UseCMap /Identity-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Type0VerticalUseCMapSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /Type0VerticalUseCMapSubset /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type0-vertical-usecmap-cidset-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type0-predefined-vertical-usecmap-cidset-boundary',
    'font_width_sources' => ['Type0 /Encoding CMap dictionary /UseCMap /Identity-V', '/CIDSet', '/DW2', 'Identity-V source codes'],
    'predefined_vertical_usecmap_inherited' => $plainText === "A Word\nVertImport",
    'identity_v_codespace_decoded' => in_array('Word', $runs, true) && !str_contains($plainText, "\0"),
    'excluded_cid_fallback_gap_preserved' => str_contains($plainText, 'A Word') && !str_contains($plainText, 'AWord'),
    'included_cid_default_vertical_width_preserved' => str_contains($plainText, 'VertImport') && !str_contains($plainText, 'Vert Import'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
