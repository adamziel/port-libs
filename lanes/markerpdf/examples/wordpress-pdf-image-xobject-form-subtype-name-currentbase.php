<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Escaped Form Image Intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Escaped#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Escaped Form Image Outro) Tj ET';
$formContent = 'q 8 0 0 4 2 3 cm /Nested#20Escaped#20Image Do Q';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Escaped Form Image Payload Noise) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
if (!is_string($compressedImagePayload)) {
    throw new RuntimeException('Unable to compress escaped Form XObject image smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Escaped#20Form 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Sub#74ype /F#6frm /BBox [0 0 20 10] /Resources << /XObject << /Nested#20Escaped#20Image 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['resource_path'] ?? null) !== ['Escaped Form', 'Nested Escaped Image']
    || ($entry['parent_form_xobject_object'] ?? null) !== 5
    || ($entry['form_xobject_depth'] ?? null) !== 1
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || str_contains($plainText, 'WordPress Escaped Form Image Payload Noise')
) {
    throw new RuntimeException('Escaped Form XObject subtype image smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-form-subtype-name-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Form XObject names are PDF-name decoded before nested image review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'escaped_form_subtype_resolved' => ($entry['resource_path'] ?? null) === ['Escaped Form', 'Nested Escaped Image'],
    'nested_form_parent_object' => $entry['parent_form_xobject_object'] ?? null,
    'nested_form_bbox' => $entry['image_unit_bbox'] ?? null,
    'nested_image_sha256_matches' => ($entry['decoded_sha256'] ?? null) === hash('sha256', $imagePayload),
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Escaped Form Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-form-subtype-name-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
