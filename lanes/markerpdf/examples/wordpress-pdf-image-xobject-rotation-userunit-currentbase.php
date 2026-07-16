<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Rotated UserUnit Image Import) Tj ET\n"
    . "q 40 0 0 20 30 60 cm /Rotated#20Image Do Q\n"
    . "q 50 0 0 40 160 230 cm /Clipped#20Rotated Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Rotated UserUnit Image Complete) Tj ET';
$rotatedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Rotated UserUnit Image Noise) Tj ET';
$clippedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Clipped Rotated UserUnit Image Noise) Tj ET';
$rotatedCompressed = gzcompress($rotatedPayload);
$clippedCompressed = gzcompress($clippedPayload);
if (!is_string($rotatedCompressed) || !is_string($clippedCompressed)) {
    throw new RuntimeException('Unable to compress rotated UserUnit image smoke payloads.');
}

$pdf = "%PDF-1.6\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [10 20 210 320] /CropBox [20 40 180 240] /Rotate 90 /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Rotated#20Image 5 0 R /Clipped#20Rotated 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /UserUnit 2 /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($rotatedCompressed) . " >>\nstream\n{$rotatedCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 4 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($clippedCompressed) . " >>\nstream\n{$clippedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($entriesByName['Rotated Image']['page_rotation'] ?? null) !== 90
    || ($entriesByName['Rotated Image']['page_user_unit'] ?? null) !== 2.0
    || ($entriesByName['Rotated Image']['page_display_size'] ?? null) !== ['width' => 400.0, 'height' => 320.0]
    || ($entriesByName['Rotated Image']['image_display_bbox'] ?? null) !== [40.0, 20.0, 80.0, 100.0]
    || ($entriesByName['Clipped Rotated']['image_display_bbox'] ?? null) !== [380.0, 280.0, 460.0, 380.0]
    || ($entriesByName['Clipped Rotated']['image_visible_display_bbox'] ?? null) !== [380.0, 280.0, 400.0, 320.0]
    || ($entriesByName['Clipped Rotated']['page_clip_reduces_painted_bbox'] ?? false) !== true
    || str_contains($plainText, 'WordPress Rotated UserUnit Image Noise')
    || str_contains($plainText, 'WordPress Clipped Rotated UserUnit Image Noise')
) {
    throw new RuntimeException('Rotated UserUnit image XObject smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-rotation-userunit-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image page geometry after marker.pdf.extract_text text separation',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'page_rotation' => $entriesByName['Rotated Image']['page_rotation'] ?? null,
    'page_rotation_source' => $entriesByName['Rotated Image']['page_rotation_source'] ?? null,
    'page_user_unit' => $entriesByName['Rotated Image']['page_user_unit'] ?? null,
    'page_user_unit_source' => $entriesByName['Rotated Image']['page_user_unit_source'] ?? null,
    'page_display_size' => $entriesByName['Rotated Image']['page_display_size'] ?? null,
    'rotated_image_display_bbox' => $entriesByName['Rotated Image']['image_display_bbox'] ?? null,
    'clipped_image_raw_display_bbox' => $entriesByName['Clipped Rotated']['image_display_bbox'] ?? null,
    'clipped_image_visible_display_bbox' => $entriesByName['Clipped Rotated']['image_visible_display_bbox'] ?? null,
    'display_geometry_review_only' => $entriesByName['Rotated Image']['display_geometry_review_only'] ?? false,
    'payload_in_visible_text' => $entriesByName['Rotated Image']['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-rotation-userunit-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
