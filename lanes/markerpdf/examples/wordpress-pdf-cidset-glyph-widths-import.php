<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "9 beginbfchar\n"
    . "<0001> <0057>\n"
    . "<0002> <0069>\n"
    . "<0003> <0064>\n"
    . "<0004> <0065>\n"
    . "<0005> <0042>\n"
    . "<0006> <006C>\n"
    . "<0007> <006F>\n"
    . "<0008> <0063>\n"
    . "<0009> <006B>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 118 720 Tm <00050006000700080009> Tj ET';
$cidSet = "\xff\xc0";
$compressedCidSet = gzcompress($cidSet);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused CIDSet fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDSetSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDSetSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /CIDSetSubset /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-cidset-glyph-widths ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cidset-subset-default-width-boundary',
    'font_width_sources' => ['/CIDSet', 'CIDFont default /DW 1000'],
    'kept_joined_text' => !str_contains($plainText, 'Wide Block'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
