<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Explicit subtype image intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Dimensioned#20Form Do Q\n"
    . "q 9 0 0 9 96 690 cm /PostScript#20Decoy Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Explicit subtype image outro) Tj ET';
$formContent = 'q 5 0 0 5 2 2 cm /Nested#20Image Do Q';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Explicit Subtype Nested Image Payload Noise) Tj ET';
$postScriptPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Explicit Subtype PostScript Decoy Noise) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
if (!is_string($compressedImagePayload)) {
    throw new RuntimeException('Unable to compress explicit subtype image smoke payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Dimensioned#20Form 5 0 R /PostScript#20Decoy 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Resources << /XObject << /Nested#20Image 6 0 R >> >> /Width 99 /Height 99 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /PS /Width 88 /Height 77 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($postScriptPayload) . " >>\nstream\n{$postScriptPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$nested = $entriesByName['Nested Image'] ?? [];
if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || isset($entriesByName['Dimensioned Form'])
    || isset($entriesByName['PostScript Decoy'])
    || ($nested['resource_path'] ?? null) !== ['Dimensioned Form', 'Nested Image']
    || ($nested['parent_form_xobject_object'] ?? null) !== 5
    || ($nested['decoded_sha256'] ?? null) !== hash('sha256', $imagePayload)
    || str_contains($plainText, 'WordPress Explicit Subtype Nested Image Payload Noise')
    || str_contains($plainText, 'WordPress Explicit Subtype PostScript Decoy Noise')
) {
    throw new RuntimeException('Explicit non-Image XObject subtype smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-explicit-subtype-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; explicit non-Image XObject subtypes stay outside image fallback review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'dimensioned_form_not_counted_as_image' => !isset($entriesByName['Dimensioned Form']),
    'postscript_decoy_not_counted_as_image' => !isset($entriesByName['PostScript Decoy']),
    'nested_form_image_path' => $nested['resource_path'] ?? [],
    'nested_image_parent_form_object' => $nested['parent_form_xobject_object'] ?? null,
    'nested_image_sha256_matches' => ($nested['decoded_sha256'] ?? null) === hash('sha256', $imagePayload),
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Explicit Subtype Nested Image Payload Noise')
        || str_contains($plainText, 'WordPress Explicit Subtype PostScript Decoy Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-explicit-subtype-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
