<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /F1 12 Tf 72 720 Td (Visible Import Text) Tj T* (Clean Paragraph) Tj ET';
$imageBytes = 'BT /F1 12 Tf 72 720 Td (Raster Image Noise) Tj ET';
$compressedImageBytes = (string) gzcompress('BT /F1 12 Tf 72 720 Td (Compressed Image Noise) Tj ET');

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "2 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($imageBytes) . " >>\nstream\n{$imageBytes}\nendstream\nendobj\n"
    . "3 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImageBytes) . " >>\nstream\n{$compressedImageBytes}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-image-xobject-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'stream fallback skips /Subtype /Image XObject payloads before Gutenberg paragraph rendering',
    'visible_text_imported' => str_contains($plainText, 'Visible Import Text'),
    'excluded_image_xobject_text' => !str_contains($plainText, 'Raster Image Noise')
        && !str_contains($plainText, 'Compressed Image Noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
