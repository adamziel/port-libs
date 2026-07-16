<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Nested Form Image Provenance Intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Logo#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Nested Form Image Provenance Outro) Tj ET';
$formContent = 'q 8 0 0 4 1 2 cm /Nested#20Logo Do Q';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Nested Form Image Payload Noise) Tj ET';
$compressed = gzcompress($imagePayload);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress nested Form image smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Resources << /XObject << /Nested#20Logo 6 0 R >> /Font << /F1 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /Logo#20Form 5 0 R >> >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_path'] ?? null) !== ['Logo Form', 'Nested Logo']
    || ($entry['parent_form_xobject_object'] ?? null) !== 5
    || ($entry['page_resource_inherited'] ?? null) !== true
    || ($entry['page_resource_owner_object'] ?? null) !== 2
    || ($entry['page_resource_object'] ?? null) !== 10
    || ($entry['page_resource_review_only'] ?? null) !== true
    || ($entry['image_unit_bbox'] ?? null) !== [92.0, 710.0, 252.0, 750.0]
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || str_contains($plainText, 'WordPress Nested Form Image Payload Noise')
) {
    throw new RuntimeException('Nested Form Image XObject provenance smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-form-resource-provenance-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Form XObject image placements remain page-scoped review metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'nested_form_resource_path' => $entry['resource_path'] ?? [],
    'nested_form_parent_object' => $entry['parent_form_xobject_object'] ?? null,
    'page_resource_inherited' => $entry['page_resource_inherited'] ?? null,
    'page_resource_owner_object' => $entry['page_resource_owner_object'] ?? null,
    'page_resource_object' => $entry['page_resource_object'] ?? null,
    'page_resource_generation' => $entry['page_resource_generation'] ?? null,
    'page_resource_review_only' => $entry['page_resource_review_only'] ?? false,
    'placement_review_only' => $entry['placement_review_only'] ?? false,
    'nested_form_bbox' => $entry['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Nested Form Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-form-resource-provenance-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
