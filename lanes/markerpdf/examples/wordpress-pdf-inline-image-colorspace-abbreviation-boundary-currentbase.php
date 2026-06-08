<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dictionary = '/W 1 /H 1 /CS [/DeviceN [/I /RGB] /CMYK 99 0 R << /Subtype /NChannel >>] /BPC 8 /D [0 1 1 0]';
$payload = "\x01EI BT /F1 12 Tf 72 690 Td (DeviceN Colorant Inline Noise) Tj ET \x02";
$content = "BT /F1 12 Tf 72 720 Td (Before DeviceN Colorant Inline) Tj ET\n"
    . "BI {$dictionary} ID\n"
    . $payload . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After DeviceN Colorant Inline) Tj ET";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$plainText = $extractor->extractPlainText($pdf);
$review = $renderer->inlineImageReviewPlan($dictionary, "\x01\x02");

if (!str_contains($plainText, 'Before DeviceN Colorant Inline') || !str_contains($plainText, 'After DeviceN Colorant Inline')) {
    throw new RuntimeException('Visible text around inline DeviceN image was not preserved.');
}
if (str_contains($plainText, 'DeviceN Colorant Inline Noise') || str_contains($plainText, '/Indexed /DeviceRGB')) {
    throw new RuntimeException('Inline DeviceN image payload or over-expanded colorant names leaked into WordPress text.');
}
if (($review['inline_image']['canonical_dictionary'] ?? '') !== '<< /Width 1 /Height 1 /ColorSpace [/DeviceN [/I /RGB] /DeviceCMYK 99 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [0 1 1 0] >>') {
    throw new RuntimeException('Inline DeviceN colorant abbreviation boundary was not preserved.');
}

echo '<!-- markerpdf-inline-image-colorspace-abbreviation-boundary ' . htmlspecialchars(json_encode([
    'upstream_boundary' => 'PDF inline image short-name expansion applies to image dictionary keys, filter names, and color-space families, not DeviceN colorant identifiers',
    'native_boundary' => 'DeviceN inline image colorant names /I and /RGB stay review metadata while alternate /CMYK expands before WordPress media review',
    'visible_text' => $plainText,
    'canonical_dictionary' => $review['inline_image']['canonical_dictionary'],
    'image_decode_valid' => $review['image_decode']['valid_for_components'],
    'payload_excluded_from_text' => $review['inline_image_payload_excluded_from_text'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES)) . " -->\n";
echo '<p>' . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
