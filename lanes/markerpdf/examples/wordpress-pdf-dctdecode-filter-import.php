<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (DCT PDF Import) Tj T* (Clean Paragraphs) Tj ET';
$jpegLikeNoise = "\xff\xd8\xff\xe0JFIF\0BT /F1 12 Tf 72 720 Td (Raster JPEG Noise) Tj ET\xff\xd9";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /DCTDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 /Length " . strlen($jpegLikeNoise) . " >>\nstream\n{$jpegLikeNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf:pdf-dctdecode-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'stream_filters' => ['DCTDecode'],
    'paragraphs' => $lines,
    'excluded_dctdecode_image_noise' => !str_contains($plainText, 'Raster JPEG Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
