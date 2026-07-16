<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (JPX JBIG2 Boundary) Tj ET';
$after = 'BT /F1 12 Tf 72 688 Td (Native Import) Tj ET';
$jpxNoise = "\x00\x00\x00\x0cjP  \r\n\x87\nBT /F1 12 Tf 72 704 Td (Raster JPX Noise) Tj ET";
$jbig2Noise = "\x97JB2\r\n\x1a\nBT /F1 12 Tf 72 672 Td (Raster JBIG2 Noise) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /JPXDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 /Length " . strlen($jpxNoise) . " >>\nstream\n{$jpxNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter /JBIG2Decode /DecodeParms 8 0 R /Width 1 /Height 1 /Length " . strlen($jbig2Noise) . " >>\nstream\n{$jbig2Noise}\nendstream\nendobj\n"
    . "8 0 obj\n<< /JBIG2Globals 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf:pdf-jpx-jbig2-image-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-boundary',
    'stream_filters' => ['JPXDecode', 'JBIG2Decode'],
    'decode_parms' => ['JBIG2Globals' => true],
    'paragraphs' => $lines,
    'excluded_jpx_image_noise' => !str_contains($plainText, 'Raster JPX Noise'),
    'excluded_jbig2_image_noise' => !str_contains($plainText, 'Raster JBIG2 Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
