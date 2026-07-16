<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current image generation intro) Tj ET\n"
    . "q 30 0 0 10 72 690 cm /Exact#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current image generation outro) Tj ET';
$currentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Current Generation Image Payload Noise) Tj ET';
$stalePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stale Generation Image Payload Noise) Tj ET';
$currentCompressed = gzcompress($currentPayload);
$staleCompressed = gzcompress($stalePayload);
if (!is_string($currentCompressed) || !is_string($staleCompressed)) {
    throw new RuntimeException('Unable to compress image XObject generation smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Exact#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
    . "5 1 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_name'] ?? null) !== 'Exact Image'
    || ($entry['object_number'] ?? null) !== 5
    || ($entry['object_generation'] ?? null) !== 0
    || ($entry['width'] ?? null) !== 3
    || ($entry['height'] ?? null) !== 1
    || ($entry['color_space'] ?? null) !== 'DeviceRGB'
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $currentPayload)
    || ($entry['image_unit_bbox'] ?? null) !== [72.0, 690.0, 102.0, 700.0]
    || str_contains($plainText, 'WordPress Current Generation Image Payload Noise')
    || str_contains($plainText, 'WordPress Stale Generation Image Payload Noise')
) {
    throw new RuntimeException('Image XObject generation boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-generation-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB handoff with PDF indirect object generation-specific resource references',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'first_resource_name' => $entry['resource_name'] ?? null,
    'first_object_number' => $entry['object_number'] ?? null,
    'first_object_generation' => $entry['object_generation'] ?? null,
    'first_dimensions' => [$entry['width'] ?? null, $entry['height'] ?? null],
    'first_color_space' => $entry['color_space'] ?? null,
    'first_decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? false,
    'first_decoded_sha256' => $entry['decoded_sha256'] ?? null,
    'stale_generation_rejected' => ($entry['decoded_sha256'] ?? null) !== hash('sha256', $stalePayload),
    'payload_in_visible_text' => $entry['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-generation-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
