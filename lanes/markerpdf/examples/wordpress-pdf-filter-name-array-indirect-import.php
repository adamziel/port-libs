<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Name Array Indirect Filter) Tj T* (Block Ready Import) Tj ET';
$compressed = gzcompress($content);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress PDF stream-filter fixture.');
}

$encoded = strtoupper(bin2hex($compressed)) . '>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Filter [ 2 0 R null 3 0 R ] /Length " . strlen($encoded) . " >>\n"
    . "stream\n{$encoded}\nendstream\nendobj\n"
    . "2 0 obj\n/ASCIIHexDecode\nendobj\n"
    . "3 0 obj\n/FlateDecode\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-filter-name-array-indirect ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'stream_filters' => ['2 0 R => ASCIIHexDecode', '3 0 R => FlateDecode'],
    'filter_array_contains_indirect_names' => true,
    'null_filter_entry_ignored' => true,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
