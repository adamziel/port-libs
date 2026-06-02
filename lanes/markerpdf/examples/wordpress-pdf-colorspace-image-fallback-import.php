<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$visibleBefore = 'BT /F1 12 Tf 72 720 Td (ColorSpace PDF Import) Tj ET';
$visibleAfter = 'BT /F1 12 Tf 72 688 Td (Clean WordPress Paragraph) Tj ET';
$deviceNoise = 'BT /F1 12 Tf 72 704 Td (Device RGB Image Noise) Tj ET';
$indexedNoise = 'BT /F1 12 Tf 72 704 Td (Indexed Palette Noise) Tj ET';
$iccNoise = 'BT /F1 12 Tf 72 704 Td (ICC Profile Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> >> /Contents [4 0 R 5 0 R 6 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleBefore) . " >>\nstream\n{$visibleBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace /DeviceRGB /Length " . strlen($deviceNoise) . " >>\nstream\n{$deviceNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace [/Indexed /DeviceRGB 1 <000000FFFFFF>] /Length " . strlen($indexedNoise) . " >>\nstream\n{$indexedNoise}\nendstream\nendobj\n"
    . "7 0 obj\n[/ICCBased 11 0 R]\nendobj\n"
    . "8 0 obj\n<< /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace 7 0 R /Length " . strlen($iccNoise) . " >>\nstream\n{$iccNoise}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nICCFAKE\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-colorspace-image-fallback-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'device, Indexed, and ICCBased image stream dictionaries skipped before Gutenberg paragraph rendering',
    'visible_text_imported' => str_contains($plainText, 'ColorSpace PDF Import')
        && str_contains($plainText, 'Clean WordPress Paragraph'),
    'excluded_colorspace_image_text' => !str_contains($plainText, 'Device RGB Image Noise')
        && !str_contains($plainText, 'Indexed Palette Noise')
        && !str_contains($plainText, 'ICC Profile Noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
