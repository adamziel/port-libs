<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Clipped Transparent Image Intro) Tj ET\n"
    . "q 0 0 30 20 re W n /Transparent#20State gs 24 0 0 12 4 4 cm /Transparent#20Clipped Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Clipped Transparent Image Outro) Tj ET';
$payload = 'BT /F1 12 Tf 72 720 Td (WordPress Transparent Clipped Image Noise) Tj ET';
$compressed = gzcompress($payload);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress clipped transparent image XObject smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Transparent#20State 20 0 R >> /XObject << /Transparent#20Clipped 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /ca 0 /BM /Normal >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

$metadata = [
    'source' => 'native-pdf-image-xobject-clip-suppression-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image receives final page graphics state; native PHP distinguishes clipping metadata from zero-alpha Image XObject paint suppression before WordPress media review',
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'resource_name' => $entry['resource_name'] ?? null,
    'invoked' => $entry['invoked'] ?? null,
    'painted_invocation_count' => $entry['painted_invocation_count'] ?? null,
    'clip_applied' => $entry['clip_applied'] ?? null,
    'clip_reduces_painted_bbox' => $entry['clip_reduces_painted_bbox'] ?? null,
    'clip_excludes_image' => $entry['clip_excludes_image'] ?? null,
    'clip_excluded_invocation_count' => $entry['clip_excluded_invocation_count'] ?? null,
    'graphics_state_paint_suppressed' => $entry['graphics_state_paint_suppressed'] ?? null,
    'graphics_state_paint_suppression_reasons' => $entry['graphics_state_paint_suppression_reasons'] ?? [],
    'transparent_nonstroking_alpha' => $entry['invocation_graphics_states'][0]['nonstroking_alpha'] ?? null,
    'image_unit_bbox' => $entry['image_unit_bbox'] ?? null,
    'image_visible_bbox' => $entry['image_visible_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Transparent Clipped Image Noise'),
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

if (
    $metadata['image_xobject_count'] !== 1
    || $metadata['invoked_image_xobject_count'] !== 1
    || $metadata['resource_name'] !== 'Transparent Clipped'
    || $metadata['invoked'] !== true
    || $metadata['painted_invocation_count'] !== 0
    || $metadata['clip_applied'] !== true
    || $metadata['clip_reduces_painted_bbox'] !== false
    || $metadata['clip_excludes_image'] !== false
    || $metadata['clip_excluded_invocation_count'] !== 0
    || $metadata['graphics_state_paint_suppressed'] !== true
    || $metadata['graphics_state_paint_suppression_reasons'] !== ['nonstroking_alpha_zero']
    || $metadata['transparent_nonstroking_alpha'] !== 0.0
    || $metadata['image_unit_bbox'] !== [4.0, 4.0, 28.0, 16.0]
    || $metadata['image_visible_bbox'] !== null
    || $metadata['payload_in_visible_text'] !== false
    || $metadata['executes_python_or_models'] !== false
    || $metadata['executes_external_pdf_tools'] !== false
) {
    throw new RuntimeException('Clipped transparent image XObject boundary smoke failed.');
}

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
echo '<!-- markerpdf-image-xobject-clip-suppression-currentbase '
    . json_encode($metadata, JSON_UNESCAPED_SLASHES)
    . " -->\n";
