<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before wrapped pattern image) Tj ET\n"
    . "/Pattern cs /Wrapped#20Tile scn 0 0 20 10 re f\n"
    . "/Pattern cs /Cycle#20Tile scn 30 0 10 5 re f\n"
    . 'BT /F1 12 Tf 72 660 Td (After wrapped pattern image) Tj ET';
$patternContent = 'q 6 0 0 3 1 2 cm /Tile#20Image Do Q';
$tilePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Wrapped Pattern Tile Image Noise) Tj ET';
$unusedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Unused Wrapped Pattern Image Noise) Tj ET';
$tileCompressed = gzcompress($tilePayload);
$unusedCompressed = gzcompress($unusedPayload);
if (!is_string($tileCompressed) || !is_string($unusedCompressed)) {
    throw new RuntimeException('Unable to compress wrapped pattern image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Wrapped#20Tile 12 0 R /Cycle#20Tile 13 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tileCompressed) . " >>\nstream\n{$tileCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unusedCompressed) . " >>\nstream\n{$unusedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Tile#20Image 5 0 R /Unused#20Wrapped#20Image 6 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
    . "12 0 obj\n11 0 R\nendobj\n"
    . "13 0 obj\n13 0 R\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$tile = $entriesByName['Tile Image'] ?? [];
$unused = $entriesByName['Unused Wrapped Image'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$metadata = [
    'source' => 'native-pdf-image-xobject-pattern-wrapper-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image receives images painted through resolved PDF pattern resources while PDF text extraction keeps raster payloads separate',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? 0,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? 0,
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'] ?? 0,
    'wrapped_pattern_resolved' => ($tile['parent_pattern_object'] ?? null) === 11,
    'wrapped_pattern_generation' => $tile['parent_pattern_generation'] ?? null,
    'wrapped_pattern_resource_name' => $tile['pattern_resource_name'] ?? null,
    'wrapped_pattern_resource_path' => $tile['resource_path'] ?? [],
    'wrapped_pattern_invoked' => $tile['invoked'] ?? false,
    'wrapped_pattern_bbox' => $tile['image_unit_bbox'] ?? null,
    'wrapped_pattern_visible_bbox' => $tile['image_visible_bbox'] ?? null,
    'wrapped_pattern_decoded_with_current_filters' => $tile['decoded_with_current_filters'] ?? false,
    'wrapped_pattern_payload_hash_matches' => ($tile['decoded_sha256'] ?? null) === hash('sha256', $tilePayload),
    'unused_wrapped_pattern_reviewed' => ($unused['invoked'] ?? true) === false
        && ($unused['decoded_sha256'] ?? null) === hash('sha256', $unusedPayload),
    'cycle_pattern_wrapper_skipped' => !isset($entriesByName['Cycle Tile']),
    'payload_in_visible_text' => str_contains($plainText, 'Wrapped Pattern Tile Image Noise')
        || str_contains($plainText, 'Unused Wrapped Pattern Image Noise'),
    'payload_in_review_json' => str_contains($encodedReview, $tilePayload)
        || str_contains($encodedReview, $unusedPayload),
];

foreach ([
    'wrapped_pattern_resolved',
    'wrapped_pattern_invoked',
    'wrapped_pattern_decoded_with_current_filters',
    'wrapped_pattern_payload_hash_matches',
    'unused_wrapped_pattern_reviewed',
    'cycle_pattern_wrapper_skipped',
] as $requiredCheck) {
    if (($metadata[$requiredCheck] ?? false) !== true) {
        throw new RuntimeException("Wrapped pattern image smoke failed: {$requiredCheck}");
    }
}
if (($metadata['payload_in_visible_text'] ?? true) !== false || ($metadata['payload_in_review_json'] ?? true) !== false) {
    throw new RuntimeException('Wrapped pattern image payload leaked into WordPress-visible output.');
}

echo '<!-- markerpdf:pdf-image-xobject-pattern-wrapper-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
