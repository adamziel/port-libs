<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Pattern Resource Tail Intro) Tj ET\n"
    . "/Pattern cs /Bad#20Tail#20Tile scn 0 0 18 9 re f\n"
    . "/Pattern cs /Good#20Tile scn 24 0 18 9 re f\n"
    . "/Pattern cs /Comment#20Tail#20Tile scn 48 0 18 9 re f\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Pattern Resource Tail Outro) Tj ET';
$badPatternContent = 'q 5 0 0 2 1 1 cm /Bad#20Pattern#20Image Do Q';
$goodPatternContent = 'q 6 0 0 3 2 2 cm /Good#20Pattern#20Image Do Q';
$commentPatternContent = 'q 4 0 0 2 3 3 cm /Comment#20Pattern#20Image Do Q';
$badPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Bad Pattern Resource Tail Image Payload Noise) Tj ET';
$goodPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Good Pattern Resource Tail Image Payload Noise) Tj ET';
$commentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Comment Pattern Resource Tail Image Payload Noise) Tj ET';
$badCompressed = gzcompress($badPayload);
$goodCompressed = gzcompress($goodPayload);
$commentCompressed = gzcompress($commentPayload);
if (!is_string($badCompressed) || !is_string($goodCompressed) || !is_string($commentCompressed)) {
    throw new RuntimeException('Unable to compress Pattern resource-entry-tail smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Bad#20Tail#20Tile 11 0 R 99 0 R /Good#20Tile 12 0 R /Comment#20Tail#20Tile 13 0 R % comment-only tail\n >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($goodCompressed) . " >>\nstream\n{$goodCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($commentCompressed) . " >>\nstream\n{$commentCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 18 9] /XStep 18 /YStep 9 /Resources << /XObject << /Bad#20Pattern#20Image 5 0 R >> >> /Length " . strlen($badPatternContent) . " >>\nstream\n{$badPatternContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 18 9] /XStep 18 /YStep 9 /Resources << /XObject << /Good#20Pattern#20Image 6 0 R >> >> /Length " . strlen($goodPatternContent) . " >>\nstream\n{$goodPatternContent}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 18 9] /XStep 18 /YStep 9 /Resources << /XObject << /Comment#20Pattern#20Image 7 0 R >> >> /Length " . strlen($commentPatternContent) . " >>\nstream\n{$commentPatternContent}\nendstream\nendobj\n"
    . "99 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 1 1] /XStep 1 /YStep 1 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || isset($entriesByName['Bad Pattern Image'])
    || ($entriesByName['Good Pattern Image']['decoded_sha256'] ?? null) !== hash('sha256', $goodPayload)
    || ($entriesByName['Comment Pattern Image']['decoded_sha256'] ?? null) !== hash('sha256', $commentPayload)
    || str_contains($plainText, 'WordPress Bad Pattern Resource Tail Image Payload Noise')
    || str_contains($plainText, 'WordPress Good Pattern Resource Tail Image Payload Noise')
    || str_contains($plainText, 'WordPress Comment Pattern Resource Tail Image Payload Noise')
) {
    throw new RuntimeException('Image XObject Pattern resource-entry-tail boundary smoke failed.');
}

$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$metadata = [
    'source' => 'native-pdf-image-xobject-pattern-resource-entry-tail-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable page text plus marker.pdf.images.render_image image handoff; Pattern resource dictionary entries must end before the next name key before tiling-pattern image traversal',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'malformed_pattern_resource_entry_excluded' => !isset($entriesByName['Bad Pattern Image']),
    'valid_pattern_image_painted' => ($entriesByName['Good Pattern Image']['invoked'] ?? false) === true,
    'comment_tail_pattern_image_painted' => ($entriesByName['Comment Pattern Image']['invoked'] ?? false) === true,
    'bad_payload_hash_excluded_from_review' => !str_contains($encodedReview, hash('sha256', $badPayload)),
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Bad Pattern Resource Tail Image Payload Noise')
        || str_contains($plainText, 'WordPress Good Pattern Resource Tail Image Payload Noise')
        || str_contains($plainText, 'WordPress Comment Pattern Resource Tail Image Payload Noise'),
    'payload_in_review_json' => str_contains($encodedReview, $badPayload)
        || str_contains($encodedReview, $goodPayload)
        || str_contains($encodedReview, $commentPayload),
];

foreach ([
    'malformed_pattern_resource_entry_excluded',
    'valid_pattern_image_painted',
    'comment_tail_pattern_image_painted',
    'bad_payload_hash_excluded_from_review',
] as $requiredCheck) {
    if (($metadata[$requiredCheck] ?? false) !== true) {
        throw new RuntimeException("Image XObject Pattern resource-entry-tail smoke failed: {$requiredCheck}");
    }
}
if (($metadata['payload_in_visible_text'] ?? true) !== false || ($metadata['payload_in_review_json'] ?? true) !== false) {
    throw new RuntimeException('Image XObject Pattern resource-entry-tail payload leaked into WordPress-visible output.');
}

echo '<!-- markerpdf:pdf-image-xobject-pattern-resource-entry-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
