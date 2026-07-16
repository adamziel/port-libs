<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress alternate color-space intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Spot#20Logo Do Q\n"
    . "q 18 0 0 9 104 690 cm /DeviceN#20Logo Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress alternate color-space outro) Tj ET';
$spotPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Spot Image Payload Noise) Tj ET';
$deviceNPayload = 'BT /F1 12 Tf 72 720 Td (WordPress DeviceN Image Payload Noise) Tj ET';
$spotCompressed = gzcompress($spotPayload);
$deviceNCompressed = gzcompress($deviceNPayload);
if (!is_string($spotCompressed) || !is_string($deviceNCompressed)) {
    throw new RuntimeException('Unable to compress alternate color-space smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Spot#20Logo 5 0 R /DeviceN#20Logo 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Separation /Brand#20Red /DeviceCMYK 40 0 R] /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($spotCompressed) . " >>\nstream\n{$spotCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace [/DeviceN [/Brand#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /Length " . strlen($deviceNCompressed) . " >>\nstream\n{$deviceNCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "40 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>\nstream\n{ dup dup dup }\nendstream\nendobj\n"
    . "60 0 obj\n<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>\nstream\n{ pop dup dup dup }\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$spot = $entriesByName['Spot Logo'] ?? null;
$deviceN = $entriesByName['DeviceN Logo'] ?? null;
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

if (
    $plainText !== "WordPress alternate color-space intro\nWordPress alternate color-space outro"
    || ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($spot['color_space'] ?? null) !== 'Separation'
    || ($spot['color_space_component_count'] ?? null) !== 1
    || (($spot['image_decode']['valid_for_components'] ?? null) !== true)
    || ($deviceN['color_space'] ?? null) !== 'DeviceN'
    || ($deviceN['color_space_component_count'] ?? null) !== 2
    || (($deviceN['image_decode']['valid_for_components'] ?? null) !== true)
    || str_contains($plainText, 'Payload Noise')
    || str_contains($encodedReview, $spotPayload)
    || str_contains($encodedReview, $deviceNPayload)
) {
    throw new RuntimeException('Image XObject alternate color-space boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-alt-colorspace-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image alternate color spaces reviewed before RGB preview without raster/model execution',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'spot_color_space' => $spot['color_space'] ?? null,
    'spot_component_count' => $spot['color_space_component_count'] ?? null,
    'spot_decode_valid' => $spot['image_decode']['valid_for_components'] ?? false,
    'devicen_color_space' => $deviceN['color_space'] ?? null,
    'devicen_component_count' => $deviceN['color_space_component_count'] ?? null,
    'devicen_decode_valid' => $deviceN['image_decode']['valid_for_components'] ?? false,
    'payload_in_visible_text' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-alt-colorspace-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
