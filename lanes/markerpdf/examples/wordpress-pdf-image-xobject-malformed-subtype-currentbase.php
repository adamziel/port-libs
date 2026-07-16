<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress malformed subtype intro) Tj ET\n"
    . "q 12 0 0 6 72 690 cm /String#20Subtype#20Image Do Q\n"
    . "q 10 0 0 5 96 690 cm /Unresolved#20Subtype#20Image Do Q\n"
    . "q 8 0 0 4 120 690 cm /Fallback#20Dimension#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress malformed subtype outro) Tj ET';
$stringSubtypePayload = 'BT /F1 12 Tf 72 720 Td (WordPress String Subtype Image Payload Noise) Tj ET';
$unresolvedSubtypePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Unresolved Subtype Image Payload Noise) Tj ET';
$fallbackPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Fallback Dimension Image Payload Noise) Tj ET';
$stringSubtypeCompressed = gzcompress($stringSubtypePayload);
$unresolvedSubtypeCompressed = gzcompress($unresolvedSubtypePayload);
$fallbackCompressed = gzcompress($fallbackPayload);
if (
    !is_string($stringSubtypeCompressed)
    || !is_string($unresolvedSubtypeCompressed)
    || !is_string($fallbackCompressed)
) {
    throw new RuntimeException('Unable to compress malformed Image XObject subtype smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /String#20Subtype#20Image 5 0 R /Unresolved#20Subtype#20Image 6 0 R /Fallback#20Dimension#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype (Image) /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($stringSubtypeCompressed) . " >>\nstream\n{$stringSubtypeCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype 99 0 R /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unresolvedSubtypeCompressed) . " >>\nstream\n{$unresolvedSubtypeCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($fallbackCompressed) . " >>\nstream\n{$fallbackCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}
$fallback = $entriesByName['Fallback Dimension Image'] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || isset($entriesByName['String Subtype Image'])
    || isset($entriesByName['Unresolved Subtype Image'])
    || ($fallback['decoded_sha256'] ?? null) !== hash('sha256', $fallbackPayload)
    || str_contains($plainText, 'WordPress String Subtype Image Payload Noise')
    || str_contains($plainText, 'WordPress Unresolved Subtype Image Payload Noise')
    || str_contains($plainText, 'WordPress Fallback Dimension Image Payload Noise')
) {
    throw new RuntimeException('Malformed Image XObject subtype boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-malformed-subtype-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; explicit malformed Image XObject Subtype values fail closed before dimension fallback',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'string_subtype_rejected' => !isset($entriesByName['String Subtype Image']),
    'unresolved_subtype_rejected' => !isset($entriesByName['Unresolved Subtype Image']),
    'missing_subtype_dimension_fallback_preserved' => ($fallback['decoded_sha256'] ?? null) === hash('sha256', $fallbackPayload),
    'fallback_image_bbox' => $fallback['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress String Subtype Image Payload Noise')
        || str_contains($plainText, 'WordPress Unresolved Subtype Image Payload Noise')
        || str_contains($plainText, 'WordPress Fallback Dimension Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-malformed-subtype-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
