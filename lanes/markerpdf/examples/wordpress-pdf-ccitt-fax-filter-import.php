<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (CCITT Boundary) Tj ET';
$after = 'BT /F1 12 Tf 72 688 Td (Native Import) Tj ET';
$scanNoise = 'BT /F1 12 Tf 72 704 Td (Raster Fax Noise) Tj ET';
$aliasNoise = 'BT /F1 12 Tf 72 672 Td (Alias Fax Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 1728 /Rows 1 /BlackIs1 true >> /Length " . strlen($scanNoise) . " >>\nstream\n{$scanNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter /CCF /DecodeParms << /K 0 /Columns 8 /Rows 1 /EncodedByteAlign true >> /Length " . strlen($aliasNoise) . " >>\nstream\n{$aliasNoise}\nendstream\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-ccitt-fax-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-boundary',
    'stream_filters' => ['CCITTFaxDecode', 'CCF'],
    'decode_parms' => [
        ['K' => -1, 'Columns' => 1728, 'Rows' => 1, 'BlackIs1' => true],
        ['K' => 0, 'Columns' => 8, 'Rows' => 1, 'EncodedByteAlign' => true],
    ],
    'paragraphs' => $lines,
    'image_only_filter_skipped' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
