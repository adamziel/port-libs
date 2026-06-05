<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before tiling pattern image) Tj ET\n"
    . "/Pattern cs /Image#20Tile scn 0 0 20 10 re f\n"
    . 'BT /F1 12 Tf 72 660 Td (After tiling pattern image) Tj ET';
$patternContent = 'q 6 0 0 3 1 2 cm /Tile#20Image Do Q';
$tilePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Pattern Tile Image Payload Noise) Tj ET';
$unusedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Unused Pattern Image Payload Noise) Tj ET';
$tileCompressed = gzcompress($tilePayload);
$unusedCompressed = gzcompress($unusedPayload);
if (!is_string($tileCompressed) || !is_string($unusedCompressed)) {
    throw new RuntimeException('Unable to compress tiling pattern image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Image#20Tile 11 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tileCompressed) . " >>\nstream\n{$tileCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unusedCompressed) . " >>\nstream\n{$unusedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Tile#20Image 5 0 R /Unused#20Pattern#20Image 6 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$tile = $entriesByName['Tile Image'] ?? [];
$unused = $entriesByName['Unused Pattern Image'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 1
    || ($tile['pattern_resource_name'] ?? null) !== 'Image Tile'
    || ($tile['parent_pattern_object'] ?? null) !== 11
    || ($tile['pattern_paint_count'] ?? null) !== 1
    || ($tile['pattern_matrices'] ?? null) !== [[1.0, 0.0, 0.0, 1.0, 3.0, 4.0]]
    || ($tile['pattern_visible_bboxes'] ?? null) !== [[0.0, 0.0, 20.0, 10.0]]
    || ($tile['invoked'] ?? null) !== true
    || ($tile['invocation_matrices'] ?? null) !== [[6.0, 0.0, 0.0, 3.0, 4.0, 6.0]]
    || ($tile['image_unit_bbox'] ?? null) !== [4.0, 6.0, 10.0, 9.0]
    || ($tile['decoded_sha256'] ?? null) !== hash('sha256', $tilePayload)
    || ($unused['pattern_resource_name'] ?? null) !== 'Image Tile'
    || ($unused['invoked'] ?? null) !== false
    || ($unused['invocation_count'] ?? null) !== 0
    || ($unused['decoded_sha256'] ?? null) !== hash('sha256', $unusedPayload)
    || $plainText !== "Before tiling pattern image\nAfter tiling pattern image"
    || str_contains($plainText, 'WordPress Pattern Tile Image Payload Noise')
    || str_contains($plainText, 'WordPress Unused Pattern Image Payload Noise')
    || str_contains($encodedReview, $tilePayload)
    || str_contains($encodedReview, $unusedPayload)
) {
    throw new RuntimeException('Tiling pattern Image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-tiling-pattern-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB handoff plus PDF tiling PatternType 1 XObject resource traversal before WordPress media review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'pattern_resource_name' => $tile['pattern_resource_name'] ?? null,
    'parent_pattern_object' => $tile['parent_pattern_object'] ?? null,
    'pattern_paint_count' => $tile['pattern_paint_count'] ?? null,
    'pattern_visible_bbox' => $tile['pattern_visible_bboxes'][0] ?? null,
    'tile_image_unit_bbox' => $tile['image_unit_bbox'] ?? null,
    'tile_invocation_matrix' => $tile['invocation_matrices'][0] ?? null,
    'tile_image_decoded_sha256' => $tile['decoded_sha256'] ?? null,
    'unused_pattern_image_unpainted' => ($unused['invoked'] ?? true) === false,
    'unused_pattern_image_decoded_sha256' => $unused['decoded_sha256'] ?? null,
    'payload_in_visible_text' => false,
    'pattern_review_only' => ($tile['pattern_review_only'] ?? false) === true,
];

echo '<!-- markerpdf:pdf-image-xobject-tiling-pattern-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
