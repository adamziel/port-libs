<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress SMask matte intro) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Matte#20Logo Do Q\n"
    . "q 24 0 0 12 108 690 cm /Mismatch#20Logo Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress SMask matte outro) Tj ET';
$matteImagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Matte Image Payload Noise) Tj ET';
$matteSoftMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Matte Soft Mask Payload Noise) Tj ET';
$mismatchImagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Mismatch Matte Image Payload Noise) Tj ET';
$mismatchSoftMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Mismatch Matte Soft Mask Payload Noise) Tj ET';
$matteImageCompressed = gzcompress($matteImagePayload);
$matteSoftMaskCompressed = gzcompress($matteSoftMaskPayload);
$mismatchImageCompressed = gzcompress($mismatchImagePayload);
$mismatchSoftMaskCompressed = gzcompress($mismatchSoftMaskPayload);
if (
    !is_string($matteImageCompressed)
    || !is_string($matteSoftMaskCompressed)
    || !is_string($mismatchImageCompressed)
    || !is_string($mismatchSoftMaskCompressed)
) {
    throw new RuntimeException('Unable to compress SMask Matte smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Matte#20Logo 5 0 R /Mismatch#20Logo 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /SMask 6 0 R /Length " . strlen($matteImageCompressed) . " >>\nstream\n{$matteImageCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Matte [0.25 0.5 0.75] /Length " . strlen($matteSoftMaskCompressed) . " >>\nstream\n{$matteSoftMaskCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /SMask 8 0 R /Length " . strlen($mismatchImageCompressed) . " >>\nstream\n{$mismatchImageCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Matte [0.1 0.2 0.3] /Length " . strlen($mismatchSoftMaskCompressed) . " >>\nstream\n{$mismatchSoftMaskCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$matteReview = $entriesByName['Matte Logo']['soft_mask_review']['matte_review'] ?? null;
$mismatchReview = $entriesByName['Mismatch Logo']['soft_mask_review']['matte_review'] ?? null;
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

if (
    $plainText !== "WordPress SMask matte intro\nWordPress SMask matte outro"
    || ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($matteReview['components'] ?? null) !== [0.25, 0.5, 0.75]
    || ($matteReview['matches_image_components'] ?? null) !== true
    || (($entriesByName['Matte Logo']['soft_mask_review']['matte_unblending_required'] ?? false) !== true)
    || ($mismatchReview['expected_components'] ?? null) !== 4
    || ($mismatchReview['matches_image_components'] ?? null) !== false
    || (($entriesByName['Mismatch Logo']['soft_mask_review']['matte_unblending_required'] ?? true) !== false)
    || str_contains($plainText, 'Payload Noise')
    || str_contains($encodedReview, $matteImagePayload)
    || str_contains($encodedReview, $matteSoftMaskPayload)
    || str_contains($encodedReview, $mismatchImagePayload)
    || str_contains($encodedReview, $mismatchSoftMaskPayload)
) {
    throw new RuntimeException('Image XObject SMask Matte smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-smask-matte-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB handoff records soft-mask /Matte unblending metadata without raster/model execution',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'matte_components' => $matteReview['components'] ?? [],
    'matte_matches_image_components' => $matteReview['matches_image_components'] ?? false,
    'matte_unblending_required' => $entriesByName['Matte Logo']['soft_mask_review']['matte_unblending_required'] ?? false,
    'mismatch_expected_components' => $mismatchReview['expected_components'] ?? null,
    'mismatch_matches_image_components' => $mismatchReview['matches_image_components'] ?? true,
    'payload_in_visible_text' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-smask-matte-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
