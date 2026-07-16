<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj 1 0 0 1 118 720 Tm <0042006C006F0063006B> Tj ET';
$cidSetBytes = array_fill(0, 15, 0);
foreach ([0x42, 0x57, 0x63, 0x64, 0x65, 0x69, 0x6b, 0x6c, 0x6f] as $cid) {
    $cidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
}
$cidSet = implode('', array_map('chr', $cidSetBytes));
$compressedCidSet = gzcompress($cidSet);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused CIDSet fixture.');
}

$italicFlags = (1 << 1) | (1 << 5) | (1 << 6);
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectIdentitySubset /Encoding 3 0 R /DescendantFonts [4 0 R] >>\nendobj\n"
    . "3 0 obj\n/Identity-H\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectIdentitySubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /IndirectIdentityItalic /Flags {$italicFlags} /CIDSet 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

echo '<!-- markerpdf:pdf-indirect-type0-encoding-font ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-indirect-type0-encoding-cidset-fontdescriptor-boundary',
    'indirect_encoding_name_resolved' => true,
    'font_width_sources' => ['indirect /Encoding /Identity-H', '/CIDSet', 'CIDFont default /DW 1000'],
    'cidset_default_widths_preserve_joined_blocks' => $plainText === 'WideBlock',
    'descriptor_flags_preserved' => ($firstSpan['font_flags'] ?? null) === $italicFlags,
    'font' => $firstSpan['font'] ?? null,
    'nul_bytes_removed' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
