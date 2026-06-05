<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before stroking pattern image) Tj ET\n"
    . "2 w /Pattern CS /Stroke#20Tile SCN 0 0 m 20 0 l 20 10 l S\n"
    . 'BT /F1 12 Tf 72 660 Td (After stroking pattern image) Tj ET';
$patternContent = 'q 5 0 0 2 2 1 cm /Stroke#20Image Do Q';
$strokePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stroke Pattern Image Payload Noise) Tj ET';
$unusedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Unused Stroke Pattern Image Payload Noise) Tj ET';
$strokeCompressed = gzcompress($strokePayload);
$unusedCompressed = gzcompress($unusedPayload);
if (!is_string($strokeCompressed) || !is_string($unusedCompressed)) {
    throw new RuntimeException('Unable to compress stroking tiling pattern image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Stroke#20Tile 11 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($strokeCompressed) . " >>\nstream\n{$strokeCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unusedCompressed) . " >>\nstream\n{$unusedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 25 12] /XStep 25 /YStep 12 /Matrix [1 0 0 1 4 5] /Resources << /XObject << /Stroke#20Image 5 0 R /Unused#20Stroke#20Pattern#20Image 6 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$stroke = $entriesByName['Stroke Image'] ?? [];
$unused = $entriesByName['Unused Stroke Pattern Image'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 1
    || ($stroke['pattern_resource_name'] ?? null) !== 'Stroke Tile'
    || ($stroke['parent_pattern_object'] ?? null) !== 11
    || ($stroke['pattern_paint_count'] ?? null) !== 1
    || ($stroke['pattern_matrices'] ?? null) !== [[1.0, 0.0, 0.0, 1.0, 4.0, 5.0]]
    || ($stroke['pattern_visible_bboxes'] ?? null) !== [[0.0, 0.0, 20.0, 10.0]]
    || ($stroke['invoked'] ?? null) !== true
    || ($stroke['invocation_matrices'] ?? null) !== [[5.0, 0.0, 0.0, 2.0, 6.0, 6.0]]
    || ($stroke['invocation_clip_bboxes'] ?? null) !== [[4.0, 5.0, 20.0, 10.0]]
    || ($stroke['image_unit_bbox'] ?? null) !== [6.0, 6.0, 11.0, 8.0]
    || ($stroke['decoded_sha256'] ?? null) !== hash('sha256', $strokePayload)
    || ($unused['pattern_resource_name'] ?? null) !== 'Stroke Tile'
    || ($unused['invoked'] ?? null) !== false
    || ($unused['invocation_count'] ?? null) !== 0
    || ($unused['decoded_sha256'] ?? null) !== hash('sha256', $unusedPayload)
    || $plainText !== "Before stroking pattern image\nAfter stroking pattern image"
    || str_contains($plainText, 'WordPress Stroke Pattern Image Payload Noise')
    || str_contains($plainText, 'WordPress Unused Stroke Pattern Image Payload Noise')
    || str_contains($encodedReview, $strokePayload)
    || str_contains($encodedReview, $unusedPayload)
) {
    throw new RuntimeException('Stroking tiling pattern Image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-stroking-pattern-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB handoff plus PDF stroking tiling PatternType 1 XObject resource traversal before WordPress media review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'pattern_resource_name' => $stroke['pattern_resource_name'] ?? null,
    'parent_pattern_object' => $stroke['parent_pattern_object'] ?? null,
    'pattern_paint_count' => $stroke['pattern_paint_count'] ?? null,
    'pattern_visible_bbox' => $stroke['pattern_visible_bboxes'][0] ?? null,
    'stroke_image_unit_bbox' => $stroke['image_unit_bbox'] ?? null,
    'stroke_invocation_matrix' => $stroke['invocation_matrices'][0] ?? null,
    'stroke_image_decoded_sha256' => $stroke['decoded_sha256'] ?? null,
    'unused_stroke_pattern_image_unpainted' => ($unused['invoked'] ?? true) === false,
    'unused_stroke_pattern_image_decoded_sha256' => $unused['decoded_sha256'] ?? null,
    'payload_in_visible_text' => false,
    'pattern_review_only' => ($stroke['pattern_review_only'] ?? false) === true,
];

echo '<!-- markerpdf:pdf-image-xobject-stroking-pattern-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
