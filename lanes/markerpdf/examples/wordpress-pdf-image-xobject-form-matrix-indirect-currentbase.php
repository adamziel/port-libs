<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect matrix image) Tj ET\n"
    . "q 30 0 0 15 100 200 cm /Matrix#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After indirect matrix image) Tj ET';
$formContent = 'q 4 0 0 2 6 8 cm /Nested#20Matrix#20Image Do Q';
$nestedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect Matrix Image Payload Noise) Tj ET';
$nestedCompressed = gzcompress($nestedPayload);
if (!is_string($nestedCompressed)) {
    throw new RuntimeException('Unable to compress WordPress indirect matrix image fixture payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Matrix#20Form 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 30 15] /Matrix [21 0 R 22 0 R 23 0 R 24 0 R 25 0 R 26 0 R] /Resources << /XObject << /Nested#20Matrix#20Image 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($nestedCompressed) . " >>\nstream\n{$nestedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "21 0 obj\n1\nendobj\n"
    . "22 0 obj\n0\nendobj\n"
    . "23 0 obj\n0\nendobj\n"
    . "24 0 obj\n1\nendobj\n"
    . "25 0 obj\n3\nendobj\n"
    . "26 0 obj\n4\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

if (
    ($review['source'] ?? null) !== 'pdf_image_xobject_boundary_review'
    || ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_name'] ?? null) !== 'Nested Matrix Image'
    || ($entry['resource_path'] ?? null) !== ['Matrix Form', 'Nested Matrix Image']
    || ($entry['parent_form_xobject_object'] ?? null) !== 5
    || ($entry['form_xobject_depth'] ?? null) !== 1
    || ($entry['invocation_matrices'] ?? null) !== [[120.0, 0.0, 0.0, 30.0, 370.0, 380.0]]
    || ($entry['invocation_bboxes'] ?? null) !== [[370.0, 380.0, 490.0, 410.0]]
    || ($entry['image_unit_bbox'] ?? null) !== [370.0, 380.0, 490.0, 410.0]
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $nestedPayload)
    || ($entry['payload_in_visible_text'] ?? true) !== false
    || str_contains($plainText, 'WordPress Indirect Matrix Image Payload Noise')
    || $plainText !== "Before indirect matrix image\nAfter indirect matrix image"
) {
    throw new RuntimeException('WordPress indirect Form Matrix image XObject smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-form-matrix-indirect-currentbase',
    'upstream_boundary' => 'marker.pdf images painted through Form XObjects: concatenate Form /Matrix before nested image CTM placement without raster payload text extraction',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'form_matrix_indirect_operands_resolved' => true,
    'nested_resource_path' => $entry['resource_path'] ?? [],
    'nested_parent_form_xobject_object' => $entry['parent_form_xobject_object'] ?? null,
    'nested_invocation_matrix' => $entry['invocation_matrices'][0] ?? null,
    'nested_invocation_bbox' => $entry['invocation_bboxes'][0] ?? null,
    'nested_image_unit_bbox' => $entry['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => $entry['payload_in_visible_text'] ?? null,
    'visible_text_imported' => $plainText === "Before indirect matrix image\nAfter indirect matrix image",
    'review_only' => $review['review_only'] ?? false,
];

$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);
if (!is_string($metadataJson)) {
    throw new RuntimeException('Unable to encode WordPress indirect Form Matrix image metadata.');
}

echo "<!-- markerpdf:pdf-image-xobject-form-matrix-indirect-currentbase {$metadataJson} -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
