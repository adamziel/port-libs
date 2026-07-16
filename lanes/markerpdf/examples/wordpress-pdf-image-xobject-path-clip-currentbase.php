<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Path Clip Image Intro) Tj ET\n"
    . "q 10 10 m 40 10 l 40 30 l 10 30 l h W n 50 0 0 40 0 0 cm /Path#20Clip#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Path Clip Image Outro) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Path Clip Image Payload Noise) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
if (!is_string($compressedImagePayload)) {
    throw new RuntimeException('Unable to compress path clip image XObject smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Path#20Clip#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

$metadata = [
    'source' => 'native-pdf-image-xobject-path-clip-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image media handoff; path-constructed clipping paths bound painted Image XObject review bboxes',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'resource_name' => $entry['resource_name'] ?? null,
    'image_unit_bbox' => $entry['image_unit_bbox'] ?? null,
    'image_visible_bbox' => $entry['image_visible_bbox'] ?? null,
    'clip_applied' => $entry['clip_applied'] ?? false,
    'clip_reduces_painted_bbox' => $entry['clip_reduces_painted_bbox'] ?? false,
    'clip_excludes_image' => $entry['clip_excludes_image'] ?? true,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Path Clip Image Payload Noise'),
];

if (
    $metadata['image_xobject_count'] !== 1
    || $metadata['invoked_image_xobject_count'] !== 1
    || $metadata['resource_name'] !== 'Path Clip Image'
    || $metadata['image_unit_bbox'] !== [0.0, 0.0, 50.0, 40.0]
    || $metadata['image_visible_bbox'] !== [10.0, 10.0, 40.0, 30.0]
    || $metadata['clip_applied'] !== true
    || $metadata['clip_reduces_painted_bbox'] !== true
    || $metadata['clip_excludes_image'] !== false
    || $metadata['payload_in_visible_text'] !== false
) {
    throw new RuntimeException('Path clip image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-path-clip-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
