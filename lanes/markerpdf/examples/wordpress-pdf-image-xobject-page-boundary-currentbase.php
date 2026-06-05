<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress page box image intro) Tj ET\n"
    . "q 100 0 0 80 120 120 cm /Partially#20Cropped Do Q\n"
    . "q 30 0 0 20 160 170 cm /OutsidePage Do Q\n"
    . "q 50 0 0 50 130 140 cm /Crop#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress page box image outro) Tj ET';
$formContent = '/NestedPageImage Do';
$partialPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Partial Page Box Image Noise) Tj ET';
$outsidePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Outside Page Box Image Noise) Tj ET';
$nestedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Nested Page Box Image Noise) Tj ET';
$partialCompressed = gzcompress($partialPayload);
$outsideCompressed = gzcompress($outsidePayload);
$nestedCompressed = gzcompress($nestedPayload);
if (!is_string($partialCompressed) || !is_string($outsideCompressed) || !is_string($nestedCompressed)) {
    throw new RuntimeException('Unable to compress page-boundary image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 200 200] /CropBox [-20 20 150 160] /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Partially#20Cropped 5 0 R /OutsidePage 6 0 R /Crop#20Form 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 10 /Height 8 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($partialCompressed) . " >>\nstream\n{$partialCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($outsideCompressed) . " >>\nstream\n{$outsideCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Resources << /XObject << /NestedPageImage 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($nestedCompressed) . " >>\nstream\n{$nestedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$partial = $entriesByName['Partially Cropped'] ?? [];
$outside = $entriesByName['OutsidePage'] ?? [];
$nested = $entriesByName['NestedPageImage'] ?? [];

$metadata = [
    'source' => 'native-pdf-image-xobject-page-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image receives page-cropped render bounds while native PHP records Image XObject media bboxes as review-only metadata',
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'page_media_box' => $partial['page_media_box'] ?? null,
    'page_crop_box' => $partial['page_crop_box'] ?? null,
    'page_clip_bbox' => $partial['page_clip_bbox'] ?? null,
    'page_clip_source' => $partial['page_clip_source'] ?? null,
    'page_crop_box_clipped_to_media' => $partial['page_crop_box_clipped_to_media'] ?? null,
    'partial_image_unit_bbox' => $partial['image_unit_bbox'] ?? null,
    'partial_image_visible_bbox' => $partial['image_visible_bbox'] ?? null,
    'outside_page_clip_excludes_image' => $outside['page_clip_excludes_image'] ?? null,
    'outside_painted_invocation_count' => $outside['painted_invocation_count'] ?? null,
    'nested_resource_path' => $nested['resource_path'] ?? null,
    'nested_image_visible_bbox' => $nested['image_visible_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'Page Box Image Noise'),
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

if (
    $metadata['image_xobject_count'] !== 3
    || $metadata['invoked_image_xobject_count'] !== 3
    || $metadata['page_media_box'] !== [0.0, 0.0, 200.0, 200.0]
    || $metadata['page_crop_box'] !== [-20.0, 20.0, 150.0, 160.0]
    || $metadata['page_clip_bbox'] !== [0.0, 20.0, 150.0, 160.0]
    || $metadata['page_clip_source'] !== 'crop_box_clipped_to_media_box'
    || $metadata['page_crop_box_clipped_to_media'] !== true
    || $metadata['partial_image_unit_bbox'] !== [120.0, 120.0, 220.0, 200.0]
    || $metadata['partial_image_visible_bbox'] !== [120.0, 120.0, 150.0, 160.0]
    || $metadata['outside_page_clip_excludes_image'] !== true
    || $metadata['outside_painted_invocation_count'] !== 0
    || $metadata['nested_resource_path'] !== ['Crop Form', 'NestedPageImage']
    || $metadata['nested_image_visible_bbox'] !== [130.0, 140.0, 150.0, 160.0]
    || $metadata['payload_in_visible_text'] !== false
    || $metadata['executes_python_or_models'] !== false
    || $metadata['executes_external_pdf_tools'] !== false
    || str_contains($plainText, 'WordPress Partial Page Box Image Noise')
    || str_contains($plainText, 'WordPress Outside Page Box Image Noise')
    || str_contains($plainText, 'WordPress Nested Page Box Image Noise')
) {
    throw new RuntimeException('Image XObject page-boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-page-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
