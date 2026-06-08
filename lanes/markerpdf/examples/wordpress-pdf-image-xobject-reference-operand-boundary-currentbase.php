<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress image reference operand boundary intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Soft#20Mask#20Tail#20Image Do Q\n"
    . "q 16 0 0 8 104 690 cm /Metadata#20Tail#20Image Do Q\n"
    . "q 12 0 0 6 128 690 cm /Valid#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress image reference operand boundary outro) Tj ET';

$softMaskImagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Soft Mask Reference Operand Image Noise) Tj ET';
$softMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Soft Mask Reference Operand Noise) Tj ET';
$metadataImagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Metadata Reference Operand Image Noise) Tj ET';
$metadataPayload = '<x:xmpmeta><dc:title>WordPress Hidden Tailed Metadata</dc:title></x:xmpmeta>';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Reference Operand Image Noise) Tj ET';

$softMaskImageCompressed = gzcompress($softMaskImagePayload);
$softMaskCompressed = gzcompress($softMaskPayload);
$metadataImageCompressed = gzcompress($metadataImagePayload);
$metadataCompressed = gzcompress($metadataPayload);
$validCompressed = gzcompress($validPayload);
if (
    !is_string($softMaskImageCompressed)
    || !is_string($softMaskCompressed)
    || !is_string($metadataImageCompressed)
    || !is_string($metadataCompressed)
    || !is_string($validCompressed)
) {
    throw new RuntimeException('Unable to compress image reference operand boundary smoke payloads.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Soft#20Mask#20Tail#20Image 5 0 R /Metadata#20Tail#20Image 6 0 R /Valid#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 20 0 R 99 0 R /Filter /FlateDecode /Length " . strlen($softMaskImageCompressed) . " >>\nstream\n{$softMaskImageCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Metadata 21 0 R 99 0 R /Filter /FlateDecode /Length " . strlen($metadataImageCompressed) . " >>\nstream\n{$metadataImageCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($softMaskCompressed) . " >>\nstream\n{$softMaskCompressed}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataCompressed) . " >>\nstream\n{$metadataCompressed}\nendstream\nendobj\n"
    . "99 0 obj\n<< /S /JavaScript /JS (app.alert\\('image reference operand tail'\\)) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$softMaskTail = $entriesByName['Soft Mask Tail Image'] ?? [];
$metadataTail = $entriesByName['Metadata Tail Image'] ?? [];
$valid = $entriesByName['Valid Image'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$softMaskRejected = ($softMaskTail['native_raster_decode'] ?? true) === false
    && ($softMaskTail['soft_mask_review']['type'] ?? null) === 'soft_mask_reference_operand_boundary'
    && ($softMaskTail['soft_mask_review']['tailed_object_number'] ?? null) === 20
    && ($softMaskTail['soft_mask_review']['native_raster_decode_blocked'] ?? false) === true
    && ($softMaskTail['soft_mask_object'] ?? null) === null;
$metadataRejected = ($metadataTail['native_raster_decode'] ?? false) === true
    && ($metadataTail['metadata_stream']['status'] ?? null) === 'rejected_malformed_image_xobject_metadata_stream_reference_operand'
    && ($metadataTail['metadata_stream']['tailed_object_number'] ?? null) === 21
    && ($metadataTail['metadata_stream']['decoded_with_current_filters'] ?? true) === false
    && ($metadataTail['metadata_stream']['native_raster_decode_blocked'] ?? true) === false;
$validSiblingDecoded = ($valid['native_raster_decode'] ?? false) === true
    && ($valid['decoded_sha256'] ?? null) === hash('sha256', $validPayload);
$payloadExcluded = $plainText === "WordPress image reference operand boundary intro\nWordPress image reference operand boundary outro"
    && !str_contains($plainText, 'WordPress Soft Mask Reference Operand Image Noise')
    && !str_contains($plainText, 'WordPress Hidden Soft Mask Reference Operand Noise')
    && !str_contains($plainText, 'WordPress Metadata Reference Operand Image Noise')
    && !str_contains($plainText, 'WordPress Hidden Tailed Metadata')
    && !str_contains($encodedReview, $softMaskPayload)
    && !str_contains($encodedReview, $metadataPayload)
    && !str_contains($encodedReview, 'image reference operand tail');

if (!$softMaskRejected || !$metadataRejected || !$validSiblingDecoded || !$payloadExcluded) {
    throw new RuntimeException('Image XObject reference operand boundary smoke failed.');
}

$summary = [
    'source' => 'native-pdf-image-xobject-reference-operand-boundary-currentbase',
    'upstream_boundary' => 'Image XObject SMask, Mask, and Metadata operands are single PDF objects; tailed reference operands are rejected before hidden stream decode',
    'image_xobject_count' => $review['image_xobject_count'],
    'soft_mask_reference_operand_rejected' => $softMaskRejected,
    'metadata_reference_operand_rejected' => $metadataRejected,
    'valid_sibling_native_raster_decode' => $validSiblingDecoded,
    'payload_excluded_from_text_and_review' => $payloadExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-reference-operand-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
