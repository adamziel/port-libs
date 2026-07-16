<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Visible Before Image) Tj ET\n"
    . "BI /W 3 /H 1 /CS /DeviceGray /BPC 8 ID \n"
    . "rawEIbytes BT /F1 12 Tf 72 720 Td (Inline Image Noise) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 704 Td (Visible After Image) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-inline-image-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page /Contents BI ID EI inline image data exclusion before Gutenberg paragraph rendering',
    'visible_text_imported' => str_contains($plainText, 'Visible Before Image')
        && str_contains($plainText, 'Visible After Image'),
    'excluded_inline_image_text' => !str_contains($plainText, 'Inline Image Noise')
        && !str_contains($plainText, 'rawEIbytes'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
