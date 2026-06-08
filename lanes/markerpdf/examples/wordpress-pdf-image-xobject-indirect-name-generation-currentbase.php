<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress indirect name generation intro) Tj ET\n"
    . "q 16 0 0 8 72 690 cm /Exact#20Indirect#20Name#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress indirect name generation outro) Tj ET';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect Name Generation Image Payload Noise) Tj ET';
$compressedPayload = gzcompress($imagePayload);
if (!is_string($compressedPayload)) {
    throw new RuntimeException('Unable to compress indirect-name generation image payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Exact#20Indirect#20Name#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type 30 0 R /Subtype 31 0 R /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "30 0 obj\n/XObject\nendobj\n"
    . "30 1 obj\n/NotXObject\nendobj\n"
    . "31 0 obj\n/Image\nendobj\n"
    . "31 1 obj\n/Form\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_name'] ?? null) !== 'Exact Indirect Name Image'
    || ($entry['object_number'] ?? null) !== 5
    || ($entry['object_generation'] ?? null) !== 0
    || ($entry['subtype'] ?? null) !== 'Image'
    || ($entry['width'] ?? null) !== 2
    || ($entry['height'] ?? null) !== 1
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || str_contains($plainText, 'WordPress Indirect Name Generation Image Payload Noise')
) {
    throw new RuntimeException('Image XObject indirect name generation boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-indirect-name-generation-currentbase',
    'upstream_boundary' => 'PDF indirect object references are object-number plus generation pairs for Image XObject /Type and /Subtype name operands',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'first_resource_name' => $entry['resource_name'] ?? null,
    'first_object_number' => $entry['object_number'] ?? null,
    'first_object_generation' => $entry['object_generation'] ?? null,
    'first_subtype' => $entry['subtype'] ?? null,
    'first_dimensions' => [$entry['width'] ?? null, $entry['height'] ?? null],
    'first_decoded_sha256' => $entry['decoded_sha256'] ?? null,
    'exact_name_generation_resolved' => ($entry['subtype'] ?? null) === 'Image',
    'higher_generation_name_decoys_rejected' => true,
    'payload_in_visible_text' => $entry['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-indirect-name-generation-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
