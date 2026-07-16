<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress parent form generation intro) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Generated#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress parent form generation outro) Tj ET';
$currentFormContent = 'q 4 0 0 2 1 1 cm /Nested#20Generated#20Image Do Q';
$staleFormContent = 'q 9 0 0 9 2 2 cm /Nested#20Generated#20Image Do Q';
$currentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Current Parent Form Generation Image Payload Noise) Tj ET';
$stalePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stale Parent Form Generation Image Payload Noise) Tj ET';
$currentCompressed = gzcompress($currentPayload);
$staleCompressed = gzcompress($stalePayload);
if (!is_string($currentCompressed) || !is_string($staleCompressed)) {
    throw new RuntimeException('Unable to compress parent Form XObject generation smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Generated#20Form 5 1 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Resources << /XObject << /Nested#20Generated#20Image 6 0 R >> >> /Length " . strlen($staleFormContent) . " >>\nstream\n{$staleFormContent}\nendstream\nendobj\n"
    . "5 1 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Resources << /XObject << /Nested#20Generated#20Image 6 1 R >> >> /Length " . strlen($currentFormContent) . " >>\nstream\n{$currentFormContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
    . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_name'] ?? null) !== 'Nested Generated Image'
    || ($entry['resource_path'] ?? null) !== ['Generated Form', 'Nested Generated Image']
    || ($entry['object_number'] ?? null) !== 6
    || ($entry['object_generation'] ?? null) !== 1
    || ($entry['parent_form_xobject_object'] ?? null) !== 5
    || ($entry['parent_form_xobject_generation'] ?? null) !== 1
    || ($entry['width'] ?? null) !== 4
    || ($entry['height'] ?? null) !== 2
    || ($entry['color_space'] ?? null) !== 'DeviceRGB'
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $currentPayload)
    || ($entry['decoded_sha256'] ?? null) === hash('sha256', $stalePayload)
    || str_contains($plainText, 'WordPress Current Parent Form Generation Image Payload Noise')
    || str_contains($plainText, 'WordPress Stale Parent Form Generation Image Payload Noise')
) {
    throw new RuntimeException('Parent Form XObject generation image smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-parent-form-generation-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text page text plus marker.pdf.images.render_image handoff with generation-specific Form XObject parent resources',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'resource_path' => $entry['resource_path'] ?? null,
    'parent_form_xobject_object' => $entry['parent_form_xobject_object'] ?? null,
    'parent_form_xobject_generation' => $entry['parent_form_xobject_generation'] ?? null,
    'nested_image_object' => $entry['object_number'] ?? null,
    'nested_image_generation' => $entry['object_generation'] ?? null,
    'nested_image_dimensions' => [$entry['width'] ?? null, $entry['height'] ?? null],
    'current_generation_sha256_matches' => ($entry['decoded_sha256'] ?? null) === hash('sha256', $currentPayload),
    'stale_generation_rejected' => ($entry['decoded_sha256'] ?? null) !== hash('sha256', $stalePayload),
    'payload_in_visible_text' => $entry['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-parent-form-generation-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
