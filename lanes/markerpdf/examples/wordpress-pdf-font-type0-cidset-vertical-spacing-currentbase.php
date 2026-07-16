<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fv 12 Tf '
    . '1 0 0 1 72 720 Tm <0056006500720074> Tj '
    . '1 0 0 1 72 672 Tm <0049006D0070006F00720074> Tj '
    . '1 0 0 1 96 720 Tm <0044006100740061> Tj '
    . '1 0 0 1 96 672 Tm <0046006C006F0077> Tj ET';

$cidSetBytes = array_fill(0, 16, 0);
foreach ([0x44, 0x46, 0x49, 0x56, 0x61, 0x64, 0x65, 0x6c, 0x6d, 0x6f, 0x70, 0x72, 0x74, 0x77] as $cid) {
    $cidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
}
$cidSet = implode('', array_map('chr', $cidSetBytes));
$compressedCidSet = gzcompress($cidSet);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused Type0 UCS2 vertical CIDSet fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectUcs2VerticalCIDSet /Encoding /UniJIS-UCS2-V /DescendantFonts [4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectUcs2VerticalCIDSet /CIDSystemInfo << /Registry (Adobe) /Ordering (Adobe-Japan1) /Supplement 6 >> /DW2 [880 -1000] /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /DirectUcs2VerticalCIDSet /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-font-type0-cidset-vertical-spacing-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-type0-cidset-vertical-spacing-currentbase',
    'source' => 'native-pdf-type0-ucs2-vertical-cmap-cidset-spacing-boundary',
    'font_width_sources' => [
        'direct /Encoding /UniJIS-UCS2-V predefined CMap',
        '2-byte UCS2 source code-space fallback',
        'descendant CIDFont /DW2',
        'FontDescriptor /CIDSet subset membership',
    ],
    'vertical_cidset_spacing_preserved' => $lines === ['VertImport', 'DataFlow'],
    'nul_bytes_excluded' => !str_contains($plainText, "\0"),
    'fallback_gap_excluded' => !str_contains($plainText, 'Vert Import') && !str_contains($plainText, 'Data Flow'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
