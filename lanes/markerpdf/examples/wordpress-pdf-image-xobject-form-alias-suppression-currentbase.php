<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Form Alias Image Intro) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Logo#20Form Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Form Alias Image Outro) Tj ET';
$formContent = 'q 18 0 0 9 2 3 cm /Nested#20Alias Do Q';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Form Alias Image Payload Noise) Tj ET';
$compressed = gzcompress($imagePayload);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress Form XObject alias image smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Logo#20Form 5 0 R /Page#20Alias 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Resources << /XObject << /Nested#20Alias 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$nested = $entriesByName['Nested Alias'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 0
    || isset($entriesByName['Page Alias'])
    || ($nested['resource_path'] ?? null) !== ['Logo Form', 'Nested Alias']
    || ($nested['object_number'] ?? null) !== 6
    || ($nested['parent_form_xobject_object'] ?? null) !== 5
    || ($nested['image_unit_bbox'] ?? null) !== [120.0, 726.0, 552.0, 834.0]
    || ($nested['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || str_contains($plainText, 'WordPress Form Alias Image Payload Noise')
    || str_contains($encodedReview, 'Page Alias')
    || str_contains($encodedReview, $imagePayload)
) {
    throw new RuntimeException('Form XObject image alias suppression smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-form-alias-suppression-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; page-scope image aliases are suppressed when the same object is painted through a Form XObject alias',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'page_alias_suppressed' => !isset($entriesByName['Page Alias']),
    'nested_resource_path' => $nested['resource_path'] ?? [],
    'nested_parent_form_xobject_object' => $nested['parent_form_xobject_object'] ?? null,
    'nested_bbox' => $nested['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Form Alias Image Payload Noise'),
    'payload_in_review_json' => str_contains($encodedReview, $imagePayload),
];

echo '<!-- markerpdf:pdf-image-xobject-form-alias-suppression-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
