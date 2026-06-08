<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress decode native raster intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Valid#20Decode#20Image Do Q\n"
    . "q 20 0 0 10 108 690 cm /Mismatch#20Decode#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress decode native raster outro) Tj ET';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Decode Native Raster Noise) Tj ET';
$mismatchPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Mismatch Decode Native Raster Noise) Tj ET';
$validCompressed = gzcompress($validPayload);
$mismatchCompressed = gzcompress($mismatchPayload);
if (!is_string($validCompressed) || !is_string($mismatchCompressed)) {
    throw new RuntimeException('Unable to compress Image XObject Decode native-raster smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Valid#20Decode#20Image 5 0 R /Mismatch#20Decode#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1 0 1] /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /Length " . strlen($mismatchCompressed) . " >>\nstream\n{$mismatchCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] ?? [] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$valid = $entriesByName['Valid Decode Image'] ?? [];
$mismatch = $entriesByName['Mismatch Decode Image'] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress Valid Decode Native Raster Noise')
    && !str_contains($plainText, 'WordPress Mismatch Decode Native Raster Noise');

if (
    $lines !== ['WordPress decode native raster intro', 'WordPress decode native raster outro']
    || ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($valid['native_raster_decode'] ?? false) !== true
    || ($valid['image_decode_native_raster_blocked'] ?? true) !== false
    || ($mismatch['native_raster_decode'] ?? true) !== false
    || ($mismatch['image_decode_component_mismatch'] ?? false) !== true
    || ($mismatch['image_decode_native_raster_blocked'] ?? false) !== true
    || ($mismatch['image_decode_boundary_policy'] ?? null) !== 'reject_image_decode_component_mismatch_for_native_raster'
    || ($valid['decoded_sha256'] ?? null) !== hash('sha256', $validPayload)
    || ($mismatch['decoded_sha256'] ?? null) !== hash('sha256', $mismatchPayload)
    || !$payloadExcluded
) {
    throw new RuntimeException('Image XObject Decode native-raster boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-decode-native-raster-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image handoff; Image XObject /Decode component mismatches block native raster handoff while retaining review metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'valid_decode_native_raster_decode' => $valid['native_raster_decode'] ?? null,
    'mismatch_decode_component_mismatch' => $mismatch['image_decode_component_mismatch'] ?? null,
    'mismatch_decode_native_raster_blocked' => $mismatch['image_decode_native_raster_blocked'] ?? null,
    'mismatch_decode_boundary_policy' => $mismatch['image_decode_boundary_policy'] ?? null,
    'payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
];

echo '<!-- markerpdf:pdf-image-xobject-decode-native-raster-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
