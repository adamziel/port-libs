<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Current Image Boundary Intro) Tj ET\n"
    . "q 32 0 0 16 72 690 cm /Logo#20Form Do Q\n"
    . "/OC /LayerOff BDC q 12 0 0 12 110 690 cm /HiddenMarked Do Q EMC\n"
    . "q 12 0 0 12 126 690 cm /HiddenObject Do Q\n"
    . 'BT /F1 12 Tf 72 668 Td (Current Image Boundary Outro) Tj ET';
$formContent = 'q 16 0 0 8 2 2 cm /Hero#20Image Do Q';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Image XObject Payload Noise) Tj ET';
$hiddenMarkedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Marked Image Noise) Tj ET';
$hiddenObjectPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Object Image Noise) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
$compressedHiddenMarkedPayload = gzcompress($hiddenMarkedPayload);
$compressedHiddenObjectPayload = gzcompress($hiddenObjectPayload);
if (!is_string($compressedImagePayload) || !is_string($compressedHiddenMarkedPayload) || !is_string($compressedHiddenObjectPayload)) {
    throw new RuntimeException('Unable to compress image XObject smoke payload.');
}
$encodedImagePayload = strtoupper(bin2hex($compressedImagePayload)) . '>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Properties << /LayerOn 20 0 R /LayerOff 21 0 R >> /XObject << /Logo#20Form 5 0 R /Hero#20Image 6 0 R /HiddenMarked 7 0 R /HiddenObject 8 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 32 16] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Length " . strlen($encodedImagePayload) . " >>\nstream\n{$encodedImagePayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedHiddenMarkedPayload) . " >>\nstream\n{$compressedHiddenMarkedPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /OC 21 0 R /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedHiddenObjectPayload) . " >>\nstream\n{$compressedHiddenObjectPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible WordPress Image Layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden WordPress Image Layer) >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (
    ($review['image_xobject_count'] ?? 0) !== 3
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['entries'][0]['image_unit_bbox'] ?? null) !== [136.0, 722.0, 648.0, 850.0]
    || str_contains($plainText, 'WordPress Image XObject Payload Noise')
    || str_contains($plainText, 'WordPress Hidden Marked Image Noise')
    || str_contains($plainText, 'WordPress Hidden Object Image Noise')
) {
    throw new RuntimeException('Image XObject boundary smoke failed.');
}

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$metadata = [
    'source' => 'native-pdf-image-xobject-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB handoff, including images painted through Form XObjects and optional-content-hidden image resources',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'first_resource_name' => $review['entries'][0]['resource_name'] ?? null,
    'first_resource_path' => $review['entries'][0]['resource_path'] ?? [],
    'first_parent_form_xobject_object' => $review['entries'][0]['parent_form_xobject_object'] ?? null,
    'first_form_xobject_depth' => $review['entries'][0]['form_xobject_depth'] ?? null,
    'first_invocation_matrix' => $review['entries'][0]['invocation_matrices'][0] ?? null,
    'first_invocation_bbox' => $review['entries'][0]['invocation_bboxes'][0] ?? null,
    'first_image_unit_bbox' => $review['entries'][0]['image_unit_bbox'] ?? null,
    'first_placement_review_only' => $review['entries'][0]['placement_review_only'] ?? false,
    'first_image_filters' => $review['entries'][0]['filters'] ?? [],
    'first_image_decoded_with_current_filters' => $review['entries'][0]['decoded_with_current_filters'] ?? false,
    'hidden_marked_invoked' => $entriesByName['HiddenMarked']['invoked'] ?? true,
    'hidden_object_optional_content_visible' => $entriesByName['HiddenObject']['optional_content_visible'] ?? true,
    'hidden_object_invoked' => $entriesByName['HiddenObject']['invoked'] ?? true,
    'payload_in_visible_text' => $review['entries'][0]['payload_in_visible_text'] ?? true,
];

echo '<!-- markerpdf:pdf-image-xobject-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
