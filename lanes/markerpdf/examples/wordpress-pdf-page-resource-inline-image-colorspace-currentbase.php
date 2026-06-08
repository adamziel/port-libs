<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$inlineImagePayload = "\x01EI BT /F1 12 Tf 72 660 Td (Inherited Inline ColorSpace Payload Noise) Tj ET \x02\x03";
$content = "BT /F1 12 Tf 72 720 Td (Before Inherited Inline ColorSpace) Tj ET\n"
    . "BI /W 1 /H 1 /CS /InheritedRGB /BPC 8 ID\n"
    . $inlineImagePayload . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Inherited Inline ColorSpace) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> /ColorSpace << /InheritedRGB /DeviceRGB >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Before Inherited Inline ColorSpace',
    'After Inherited Inline ColorSpace',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected inherited page ColorSpace inline image payload to stay out of WordPress paragraphs.');
}

if (str_contains($plainText, 'Inherited Inline ColorSpace Payload Noise') || str_contains($plainText, 'InheritedRGB')) {
    throw new RuntimeException('Inline image resource payload leaked into visible WordPress import text.');
}

echo '<!-- markerpdf-page-resource-inline-image-colorspace-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-inline-image-colorspace-currentbase',
    'native_boundary' => 'inline image /ColorSpace names use inherited page-tree resources for tokenizer sample boundaries',
    'inherited_color_space_resource' => 'InheritedRGB',
    'visible_paragraph_count' => count($lines),
    'inline_image_payload_excluded_from_text' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
