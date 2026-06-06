<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Image Indirect BBox Intro) Tj ET\n"
    . "q 40 0 0 20 100 200 cm /Indirect#20BBox#20Form Do Q\n"
    . "/Pattern cs /Indirect#20Tile scn 0 0 20 10 re f\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Image Indirect BBox Outro) Tj ET';
$formContent = 'q 4 0 0 3 0.5 0.25 cm /Nested#20BBox#20Image Do Q';
$patternContent = 'q 5 0 0 2 1 1 cm /Pattern#20BBox#20Image Do Q';
$formPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect Form BBox Image Payload Noise) Tj ET';
$patternPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect Pattern BBox Image Payload Noise) Tj ET';
$formCompressed = gzcompress($formPayload);
$patternCompressed = gzcompress($patternPayload);
if (!is_string($formCompressed) || !is_string($patternCompressed)) {
    throw new RuntimeException('Unable to compress indirect BBox image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Indirect#20BBox#20Form 5 0 R >> /Pattern << /Indirect#20Tile 11 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [31 0 R 32 0 R 33 0 R 34 0 R] /Resources << /XObject << /Nested#20BBox#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($formCompressed) . " >>\nstream\n{$formCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($patternCompressed) . " >>\nstream\n{$patternCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [41 0 R 42 0 R 43 0 R 44 0 R] /XStep 20 /YStep 10 /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Pattern#20BBox#20Image 7 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
    . "31 0 obj\n0\nendobj\n"
    . "32 0 obj\n0\nendobj\n"
    . "33 0 obj\n1\nendobj\n"
    . "34 0 obj\n1\nendobj\n"
    . "41 0 obj\n0\nendobj\n"
    . "42 0 obj\n0\nendobj\n"
    . "43 0 obj\n20\nendobj\n"
    . "44 0 obj\n10\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$payloadInVisibleText = str_contains($plainText, $formPayload) || str_contains($plainText, $patternPayload);
$formClip = $entriesByName['Nested BBox Image']['invocation_clip_bboxes'][0] ?? null;
$patternClip = $entriesByName['Pattern BBox Image']['invocation_clip_bboxes'][0] ?? null;

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || $formClip !== [100.0, 200.0, 140.0, 220.0]
    || ($entriesByName['Nested BBox Image']['image_visible_bbox'] ?? null) !== [120.0, 205.0, 140.0, 220.0]
    || $patternClip !== [3.0, 4.0, 20.0, 10.0]
    || ($entriesByName['Pattern BBox Image']['image_visible_bbox'] ?? null) !== [4.0, 5.0, 9.0, 7.0]
    || $payloadInVisibleText
) {
    throw new RuntimeException('Image XObject indirect BBox boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-indirect-bbox-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Form and Pattern BBox arrays clip nested image paints before raster review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'form_bbox_indirect_numbers_resolved' => $formClip === [100.0, 200.0, 140.0, 220.0],
    'form_visible_bbox' => $entriesByName['Nested BBox Image']['image_visible_bbox'] ?? null,
    'pattern_bbox_indirect_numbers_resolved' => $patternClip === [3.0, 4.0, 20.0, 10.0],
    'pattern_visible_bbox' => $entriesByName['Pattern BBox Image']['image_visible_bbox'] ?? null,
    'payload_in_visible_text' => $payloadInVisibleText,
];

echo '<!-- markerpdf:pdf-image-xobject-indirect-bbox-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
