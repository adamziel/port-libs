<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Decode image intro) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Rgb#20Decode Do Q\n"
    . "q 24 0 0 12 108 690 cm /Cmyk#20Mismatch Do Q\n"
    . "q 12 0 0 12 144 690 cm /Stencil#20Default Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Decode image outro) Tj ET';
$rgbPayload = 'BT /F1 12 Tf 72 720 Td (WordPress RGB Decode Image Noise) Tj ET';
$mismatchPayload = 'BT /F1 12 Tf 72 720 Td (WordPress CMYK Decode Mismatch Noise) Tj ET';
$stencilPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stencil Decode Noise) Tj ET';
$rgbCompressed = gzcompress($rgbPayload);
$mismatchCompressed = gzcompress($mismatchPayload);
$stencilCompressed = gzcompress($stencilPayload);
if (!is_string($rgbCompressed) || !is_string($mismatchCompressed) || !is_string($stencilCompressed)) {
    throw new RuntimeException('Unable to compress Image XObject Decode smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Rgb#20Decode 5 0 R /Cmyk#20Mismatch 6 0 R /Stencil#20Default 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1 0 1] /Length " . strlen($rgbCompressed) . " >>\nstream\n{$rgbCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /Length " . strlen($mismatchCompressed) . " >>\nstream\n{$mismatchCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ImageMask true /Filter /FlateDecode /Length " . strlen($stencilCompressed) . " >>\nstream\n{$stencilCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] ?? [] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$rgb = $entriesByName['Rgb Decode'] ?? [];
$mismatch = $entriesByName['Cmyk Mismatch'] ?? [];
$stencil = $entriesByName['Stencil Default'] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress RGB Decode Image Noise')
    && !str_contains($plainText, 'WordPress CMYK Decode Mismatch Noise')
    && !str_contains($plainText, 'WordPress Stencil Decode Noise');

if (
    $lines !== ['WordPress Decode image intro', 'WordPress Decode image outro']
    || ($review['image_xobject_count'] ?? 0) !== 3
    || ($review['invoked_image_xobject_count'] ?? 0) !== 3
    || ($rgb['image_decode']['inverted_components'] ?? null) !== [0]
    || ($rgb['image_decode_applied_before_rgb'] ?? false) !== true
    || ($mismatch['image_decode_component_mismatch'] ?? false) !== true
    || ($mismatch['image_decode_applied_before_rgb'] ?? true) !== false
    || ($stencil['image_decode']['source'] ?? null) !== 'default'
    || ($stencil['image_decode_applied_before_rgb'] ?? false) !== true
    || ($rgb['decoded_sha256'] ?? null) !== hash('sha256', $rgbPayload)
    || ($mismatch['decoded_sha256'] ?? null) !== hash('sha256', $mismatchPayload)
    || ($stencil['decoded_sha256'] ?? null) !== hash('sha256', $stencilPayload)
    || !$payloadExcluded
) {
    throw new RuntimeException('Image XObject Decode boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-decode-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB handoff; Image XObject /Decode metadata is reviewed before any raster backend executes',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'rgb_decode_applied_before_rgb' => $rgb['image_decode_applied_before_rgb'] ?? false,
    'rgb_decode_inverted_components' => $rgb['image_decode']['inverted_components'] ?? [],
    'cmyk_decode_component_mismatch' => $mismatch['image_decode_component_mismatch'] ?? false,
    'cmyk_decode_applied_before_rgb' => $mismatch['image_decode_applied_before_rgb'] ?? true,
    'stencil_default_decode_source' => $stencil['image_decode']['source'] ?? null,
    'stencil_decode_applied_before_rgb' => $stencil['image_decode_applied_before_rgb'] ?? false,
    'payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
];

echo '<!-- markerpdf:pdf-image-xobject-decode-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
