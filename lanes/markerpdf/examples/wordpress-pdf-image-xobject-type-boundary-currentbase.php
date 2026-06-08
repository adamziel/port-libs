<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress image type boundary intro) Tj ET\n"
    . "q 12 0 0 6 72 690 cm /Metadata#20Type#20Image Do Q\n"
    . "q 10 0 0 5 94 690 cm /Literal#20Type#20Image Do Q\n"
    . "q 8 0 0 4 114 690 cm /Tailed#20Type#20Image Do Q\n"
    . "q 6 0 0 3 130 690 cm /Duplicate#20Type#20Image Do Q\n"
    . "q 14 0 0 7 134 690 cm /Typeless#20Image Do Q\n"
    . "q 16 0 0 8 160 690 cm /Valid#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress image type boundary outro) Tj ET';

$payloads = [
    'Metadata Type Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Metadata Type Image Payload Noise) Tj ET',
    'Literal Type Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Literal Type Image Payload Noise) Tj ET',
    'Tailed Type Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Tailed Type Image Payload Noise) Tj ET',
    'Duplicate Type Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Duplicate Type Image Payload Noise) Tj ET',
    'Typeless Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Typeless Image Payload Noise) Tj ET',
    'Valid Image' => 'BT /F1 12 Tf 72 720 Td (WordPress Valid Type Boundary Image Payload Noise) Tj ET',
];

$compressed = [];
foreach ($payloads as $name => $payload) {
    $bytes = gzcompress($payload);
    if (!is_string($bytes)) {
        throw new RuntimeException("Unable to compress {$name} type-boundary smoke payload.");
    }

    $compressed[$name] = $bytes;
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Metadata#20Type#20Image 5 0 R /Literal#20Type#20Image 6 0 R /Tailed#20Type#20Image 7 0 R /Duplicate#20Type#20Image 11 0 R /Typeless#20Image 8 0 R /Valid#20Image 9 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Metadata Type Image']) . " >>\nstream\n{$compressed['Metadata Type Image']}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type (XObject) /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Literal Type Image']) . " >>\nstream\n{$compressed['Literal Type Image']}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject 99 0 R /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Tailed Type Image']) . " >>\nstream\n{$compressed['Tailed Type Image']}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Typeless Image']) . " >>\nstream\n{$compressed['Typeless Image']}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Valid Image']) . " >>\nstream\n{$compressed['Valid Image']}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Type /Metadata /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Duplicate Type Image']) . " >>\nstream\n{$compressed['Duplicate Type Image']}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$textLines = $extractor->extractTextLines($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$rejectedNames = ['Metadata Type Image', 'Literal Type Image', 'Tailed Type Image', 'Duplicate Type Image'];
$rejectedPresent = false;
$rejectedHashesPresent = false;
foreach ($rejectedNames as $name) {
    $rejectedPresent = $rejectedPresent || isset($entriesByName[$name]) || str_contains($encodedReview, $name);
    $rejectedHashesPresent = $rejectedHashesPresent || str_contains($encodedReview, hash('sha256', $payloads[$name]));
}

$payloadInVisibleText = false;
foreach ($payloads as $payload) {
    $payloadInVisibleText = $payloadInVisibleText || str_contains($plainText, $payload);
}

$typeless = $entriesByName['Typeless Image'] ?? [];
$valid = $entriesByName['Valid Image'] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || $rejectedPresent
    || $rejectedHashesPresent
    || ($typeless['decoded_sha256'] ?? null) !== hash('sha256', $payloads['Typeless Image'])
    || ($valid['decoded_sha256'] ?? null) !== hash('sha256', $payloads['Valid Image'])
    || $payloadInVisibleText
    || $textLines !== ['WordPress image type boundary intro', 'WordPress image type boundary outro']
) {
    throw new RuntimeException('Image XObject Type boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-type-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; explicit non-XObject /Type values are rejected before media review while Type-less image dictionaries remain reviewable',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'explicit_type_rejected' => !$rejectedPresent,
    'rejected_hashes_excluded' => !$rejectedHashesPresent,
    'typeless_image_reviewed' => ($typeless['decoded_sha256'] ?? null) === hash('sha256', $payloads['Typeless Image']),
    'valid_type_image_reviewed' => ($valid['decoded_sha256'] ?? null) === hash('sha256', $payloads['Valid Image']),
    'typeless_image_bbox' => $typeless['image_unit_bbox'] ?? null,
    'valid_image_bbox' => $valid['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => $payloadInVisibleText,
];

echo '<!-- markerpdf:pdf-image-xobject-type-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($textLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
