<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Duplicate Image Resource Intro) Tj ET\n"
    . "q 10 0 0 5 72 690 cm /Dup#20Image Do Q\n"
    . "q 8 0 0 4 100 690 cm /Unique#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Duplicate Image Resource Outro) Tj ET';
$staleDuplicatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stale Duplicate Image Payload Noise) Tj ET';
$currentDuplicatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Current Duplicate Image Payload Noise) Tj ET';
$uniquePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Unique Image Payload Noise) Tj ET';
$staleCompressed = gzcompress($staleDuplicatePayload);
$currentCompressed = gzcompress($currentDuplicatePayload);
$uniqueCompressed = gzcompress($uniquePayload);
if (!is_string($staleCompressed) || !is_string($currentCompressed) || !is_string($uniqueCompressed)) {
    throw new RuntimeException('Unable to compress duplicate Image XObject resource-name smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Dup#20Image 5 0 R /Dup#20Image 6 0 R /Unique#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($uniqueCompressed) . " >>\nstream\n{$uniqueCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 0
    || isset($entriesByName['Dup Image'])
    || ($entriesByName['Unique Image']['object_number'] ?? null) !== 7
    || ($entriesByName['Unique Image']['invoked'] ?? false) !== true
    || ($entriesByName['Unique Image']['decoded_sha256'] ?? null) !== hash('sha256', $uniquePayload)
    || str_contains($plainText, 'WordPress Stale Duplicate Image Payload Noise')
    || str_contains($plainText, 'WordPress Current Duplicate Image Payload Noise')
    || str_contains($plainText, 'WordPress Unique Image Payload Noise')
    || str_contains($encodedReview, 'Dup Image')
    || str_contains($encodedReview, hash('sha256', $staleDuplicatePayload))
    || str_contains($encodedReview, hash('sha256', $currentDuplicatePayload))
) {
    throw new RuntimeException('Duplicate Image XObject resource-name boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-duplicate-resource-name-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; ambiguous duplicate /XObject resource names are rejected before media review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'duplicate_resource_name_rejected' => !isset($entriesByName['Dup Image']),
    'unique_sibling_image_reviewed' => ($entriesByName['Unique Image']['invoked'] ?? false) === true,
    'unique_sibling_bbox' => $entriesByName['Unique Image']['image_unit_bbox'] ?? null,
    'duplicate_hashes_excluded' => !str_contains($encodedReview, hash('sha256', $staleDuplicatePayload))
        && !str_contains($encodedReview, hash('sha256', $currentDuplicatePayload)),
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Stale Duplicate Image Payload Noise')
        || str_contains($plainText, 'WordPress Current Duplicate Image Payload Noise')
        || str_contains($plainText, 'WordPress Unique Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-duplicate-resource-name-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
