<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect ColorKey import) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Indirect#20ColorKey Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After indirect ColorKey import) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect ColorKey Image Payload Noise) Tj ET';
$imageCompressed = gzcompress($imagePayload);
if (!is_string($imageCompressed)) {
    throw new RuntimeException('Unable to compress indirect ColorKey image payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Indirect#20ColorKey 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1 0 1] /Mask [20 0 R 21 0 R 22 0 R 23 0 R 24 0 R 25 0 R] /Length " . strlen($imageCompressed) . " >>\nstream\n{$imageCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n0\nendobj\n"
    . "21 0 obj\n0\nendobj\n"
    . "22 0 obj\n120\nendobj\n"
    . "23 0 obj\n140\nendobj\n"
    . "24 0 obj\n200\nendobj\n"
    . "25 0 obj\n255\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$mask = is_array($entry['mask_review'] ?? null) ? $entry['mask_review'] : [];

$expectedRanges = [
    ['min' => 0.0, 'max' => 0.0],
    ['min' => 120.0, 'max' => 140.0],
    ['min' => 200.0, 'max' => 255.0],
];
$maskResolved = ($mask['ranges'] ?? null) === $expectedRanges
    && ($mask['valid_for_components'] ?? null) === true
    && ($mask['component_count'] ?? null) === 3;
$payloadExcluded = !str_contains($plainText, 'WordPress Indirect ColorKey Image Payload Noise')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $imagePayload);

if (
    !$maskResolved
    || !$payloadExcluded
    || $lines !== ['Before indirect ColorKey import', 'After indirect ColorKey import']
    || ($entry['image_decode_applied_before_rgb'] ?? null) !== true
) {
    throw new RuntimeException('Indirect ColorKey image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-colorkey-indirect-mask-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-image-xobject-colorkey-indirect-mask-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB review handoff',
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'mask_ranges' => $mask['ranges'] ?? [],
    'indirect_mask_operands_resolved' => $maskResolved,
    'color_key_mask_compares_before_decode' => $mask['compares_before_decode'] ?? null,
    'image_decode_applied_before_rgb' => $entry['image_decode_applied_before_rgb'] ?? null,
    'image_payload_excluded_from_text' => $payloadExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
