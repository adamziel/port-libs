<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Compound Clip Image Boundary Intro) Tj ET\n"
    . "q 0 0 20 20 re W n 40 40 10 10 re W n 50 0 0 40 0 0 cm /Empty#20Compound#20Clip Do Q\n"
    . "q 0 0 40 40 re W n 10 10 30 20 re W n 50 0 0 40 0 0 cm /Visible#20Compound#20Clip Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Compound Clip Image Boundary Outro) Tj ET';
$emptyPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Empty Compound Clip Image Noise) Tj ET';
$visiblePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Visible Compound Clip Image Noise) Tj ET';
$emptyCompressed = gzcompress($emptyPayload);
$visibleCompressed = gzcompress($visiblePayload);
if (!is_string($emptyCompressed) || !is_string($visibleCompressed)) {
    throw new RuntimeException('Unable to compress compound clip image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Empty#20Compound#20Clip 5 0 R /Visible#20Compound#20Clip 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($emptyCompressed) . " >>\nstream\n{$emptyCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$emptyEntry = $entriesByName['Empty Compound Clip'] ?? [];
$visibleEntry = $entriesByName['Visible Compound Clip'] ?? [];
$metadata = [
    'source' => 'native-pdf-image-xobject-compound-clip-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; consecutive PDF clipping paths intersect before Image XObject painting',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'empty_clip_bbox_normalized' => ($emptyEntry['invocation_clip_bboxes'][0] ?? null) === [40.0, 40.0, 40.0, 40.0],
    'empty_clip_excludes_image' => ($emptyEntry['clip_excludes_image'] ?? false) === true,
    'empty_clip_painted_invocations' => $emptyEntry['painted_invocation_count'] ?? null,
    'visible_clip_bbox' => $visibleEntry['image_visible_bbox'] ?? null,
    'visible_clip_painted_invocations' => $visibleEntry['painted_invocation_count'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Empty Compound Clip Image Noise')
        || str_contains($plainText, 'WordPress Visible Compound Clip Image Noise'),
];

if (
    $lines !== ['Compound Clip Image Boundary Intro', 'Compound Clip Image Boundary Outro']
    || $metadata['image_xobject_count'] !== 2
    || $metadata['invoked_image_xobject_count'] !== 2
    || $metadata['empty_clip_bbox_normalized'] !== true
    || $metadata['empty_clip_excludes_image'] !== true
    || $metadata['empty_clip_painted_invocations'] !== 0
    || $metadata['visible_clip_bbox'] !== [10.0, 10.0, 40.0, 30.0]
    || $metadata['visible_clip_painted_invocations'] !== 1
    || $metadata['payload_in_visible_text'] !== false
) {
    throw new RuntimeException('Compound clip image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-compound-clip-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
