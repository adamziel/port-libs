<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$contentOpen = "BT /F1 12 Tf 72 720 Td (Current Image Boundary Intro) Tj ET\n"
    . 'q 32 0 0 16 72 690 cm ';
$contentClose = "/Logo#20Form Do Q\n"
    . "/OC /LayerOff BDC q 12 0 0 12 110 690 cm /HiddenMarked Do Q EMC\n"
    . "q 12 0 0 12 126 690 cm /HiddenObject Do Q\n"
    . 'BT /F1 12 Tf 72 668 Td (Current Image Boundary Outro) Tj ET';
$formContent = 'q 4 2 8 4 re W n 16 0 0 8 2 2 cm /Hero#20Image Do Q';
$imagePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Image XObject Payload Noise) Tj ET';
$maskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Image Mask Payload Noise) Tj ET';
$staleMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stale Mask Generation Noise) Tj ET';
$staleSoftMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stale Soft Mask Payload Noise) Tj ET';
$softMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Soft Mask Payload Noise) Tj ET';
$metadataPayload = '<x:xmpmeta>WordPress Image XObject Metadata Noise</x:xmpmeta>';
$staleMetadataPayload = '<x:xmpmeta>WordPress Stale Image Metadata Generation Noise</x:xmpmeta>';
$printAlternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Print Alternate Image Noise) Tj ET';
$stalePrintAlternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Stale Print Alternate Image Noise) Tj ET';
$screenAlternatePayload = "\xff\x4fBT /F1 12 Tf 72 720 Td (WordPress Screen Alternate Image Noise) Tj ET\xff\xd9";
$hiddenMarkedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Marked Image Noise) Tj ET';
$hiddenObjectPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Object Image Noise) Tj ET';
$compressedImagePayload = gzcompress($imagePayload);
$compressedMaskPayload = gzcompress($maskPayload);
$compressedStaleMaskPayload = gzcompress($staleMaskPayload);
$compressedStaleSoftMaskPayload = gzcompress($staleSoftMaskPayload);
$compressedSoftMaskPayload = gzcompress($softMaskPayload);
$compressedMetadataPayload = gzcompress($metadataPayload);
$compressedStaleMetadataPayload = gzcompress($staleMetadataPayload);
$compressedPrintAlternatePayload = gzcompress($printAlternatePayload);
$compressedStalePrintAlternatePayload = gzcompress($stalePrintAlternatePayload);
$compressedHiddenMarkedPayload = gzcompress($hiddenMarkedPayload);
$compressedHiddenObjectPayload = gzcompress($hiddenObjectPayload);
if (
    !is_string($compressedImagePayload)
    || !is_string($compressedMaskPayload)
    || !is_string($compressedStaleMaskPayload)
    || !is_string($compressedStaleSoftMaskPayload)
    || !is_string($compressedSoftMaskPayload)
    || !is_string($compressedMetadataPayload)
    || !is_string($compressedStaleMetadataPayload)
    || !is_string($compressedPrintAlternatePayload)
    || !is_string($compressedStalePrintAlternatePayload)
    || !is_string($compressedHiddenMarkedPayload)
    || !is_string($compressedHiddenObjectPayload)
) {
    throw new RuntimeException('Unable to compress image XObject smoke payload.');
}
$encodedImagePayload = strtoupper(bin2hex($compressedImagePayload)) . '>';
$encodedSoftMaskPayload = strtoupper(bin2hex($compressedSoftMaskPayload)) . '>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 11 1 R /AuxGenerationDecoys [12 1 R 15 1 R] /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Properties << /LayerOn 20 0 R /LayerOff 21 0 R >> /XObject << /Logo#20Form 5 0 R /Hero#20Image 6 0 R /HiddenMarked 7 0 R /HiddenObject 8 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($contentOpen) . " >>\nstream\n{$contentOpen}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 32 16] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Interpolate true /Intent /RelativeColorimetric /Name /Hero#20Image /StructParent 8 /StructParents 9 /SMask 16 1 R /Mask 15 0 R /Metadata 11 0 R /Alternates [<< /Image 12 0 R /DefaultForPrinting true >> << /Image 13 0 R /DefaultForPrinting false >>] /Length " . strlen($encodedImagePayload) . " >>\nstream\n{$encodedImagePayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Mask [0 255] /Length " . strlen($compressedHiddenMarkedPayload) . " >>\nstream\n{$compressedHiddenMarkedPayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /OC 21 0 R /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedHiddenObjectPayload) . " >>\nstream\n{$compressedHiddenObjectPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadataPayload) . " >>\nstream\n{$compressedMetadataPayload}\nendstream\nendobj\n"
    . "11 1 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedStaleMetadataPayload) . " >>\nstream\n{$compressedStaleMetadataPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedPrintAlternatePayload) . " >>\nstream\n{$compressedPrintAlternatePayload}\nendstream\nendobj\n"
    . "12 1 obj\n<< /Type /XObject /Subtype /Image /Width 7 /Height 3 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedStalePrintAlternatePayload) . " >>\nstream\n{$compressedStalePrintAlternatePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Length " . strlen($screenAlternatePayload) . " >>\nstream\n{$screenAlternatePayload}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Length " . strlen($contentClose) . " >>\nstream\n{$contentClose}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($compressedMaskPayload) . " >>\nstream\n{$compressedMaskPayload}\nendstream\nendobj\n"
    . "15 1 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($compressedStaleMaskPayload) . " >>\nstream\n{$compressedStaleMaskPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($compressedStaleSoftMaskPayload) . " >>\nstream\n{$compressedStaleSoftMaskPayload}\nendstream\nendobj\n"
    . "16 1 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($encodedSoftMaskPayload) . " >>\nstream\n{$encodedSoftMaskPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible WordPress Image Layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden WordPress Image Layer) >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (
    ($review['image_xobject_count'] ?? 0) !== 3
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['entries'][0]['image_unit_bbox'] ?? null) !== [136.0, 722.0, 648.0, 850.0]
    || ($review['entries'][0]['image_visible_bbox'] ?? null) !== [200.0, 722.0, 456.0, 786.0]
    || ($review['entries'][0]['clip_reduces_painted_bbox'] ?? false) !== true
    || ($review['entries'][0]['interpolate'] ?? null) !== true
    || ($review['entries'][0]['rendering_intent'] ?? null) !== 'RelativeColorimetric'
    || ($review['entries'][0]['image_name'] ?? null) !== 'Hero Image'
    || ($review['entries'][0]['struct_parent'] ?? null) !== 8
    || ($review['entries'][0]['soft_mask_object'] ?? null) !== 16
    || ($review['entries'][0]['soft_mask_generation'] ?? null) !== 1
    || ($review['entries'][0]['soft_mask_review']['type'] ?? null) !== 'soft_mask_stream'
    || ($review['entries'][0]['soft_mask_review']['decoded_sha256'] ?? null) !== hash('sha256', $softMaskPayload)
    || ($review['entries'][0]['soft_mask_review']['decode']['inverted_components'] ?? null) !== [0]
    || ($review['entries'][0]['mask_object'] ?? null) !== 15
    || ($review['entries'][0]['mask_review']['type'] ?? null) !== 'image_mask_stream'
    || ($review['entries'][0]['mask_review']['decoded_sha256'] ?? null) !== hash('sha256', $maskPayload)
    || ($review['entries'][0]['mask_review']['decoded_sha256'] ?? null) === hash('sha256', $staleMaskPayload)
    || ($review['entries'][0]['metadata_stream']['decoded_sha256'] ?? null) !== hash('sha256', $metadataPayload)
    || ($review['entries'][0]['metadata_stream']['decoded_sha256'] ?? null) === hash('sha256', $staleMetadataPayload)
    || ($review['entries'][0]['alternate_image_count'] ?? 0) !== 2
    || ($review['entries'][0]['alternate_images'][0]['decoded_sha256'] ?? null) !== hash('sha256', $printAlternatePayload)
    || ($review['entries'][0]['alternate_images'][0]['decoded_sha256'] ?? null) === hash('sha256', $stalePrintAlternatePayload)
    || ($review['entries'][0]['alternate_images'][1]['preview_only_filters'] ?? null) !== ['JPXDecode']
    || str_contains($plainText, 'WordPress Image XObject Payload Noise')
    || str_contains($plainText, 'WordPress Image Mask Payload Noise')
    || str_contains($plainText, 'WordPress Stale Mask Generation Noise')
    || str_contains($plainText, 'WordPress Stale Soft Mask Payload Noise')
    || str_contains($plainText, 'WordPress Soft Mask Payload Noise')
    || str_contains($plainText, 'WordPress Image XObject Metadata Noise')
    || str_contains($plainText, 'WordPress Stale Image Metadata Generation Noise')
    || str_contains($plainText, 'WordPress Print Alternate Image Noise')
    || str_contains($plainText, 'WordPress Stale Print Alternate Image Noise')
    || str_contains($plainText, 'WordPress Screen Alternate Image Noise')
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
    'page_contents_array_graphics_state_preserved' => ($review['entries'][0]['invocation_matrices'][0] ?? null) === [512.0, 0.0, 0.0, 128.0, 136.0, 722.0],
    'first_invocation_matrix' => $review['entries'][0]['invocation_matrices'][0] ?? null,
    'first_invocation_bbox' => $review['entries'][0]['invocation_bboxes'][0] ?? null,
    'first_invocation_clip_bbox' => $review['entries'][0]['invocation_clip_bboxes'][0] ?? null,
    'first_invocation_visible_bbox' => $review['entries'][0]['invocation_visible_bboxes'][0] ?? null,
    'first_image_unit_bbox' => $review['entries'][0]['image_unit_bbox'] ?? null,
    'first_image_visible_bbox' => $review['entries'][0]['image_visible_bbox'] ?? null,
    'first_clip_reduces_painted_bbox' => $review['entries'][0]['clip_reduces_painted_bbox'] ?? false,
    'first_clip_excludes_image' => $review['entries'][0]['clip_excludes_image'] ?? true,
    'first_painted_invocation_count' => $review['entries'][0]['painted_invocation_count'] ?? null,
    'first_placement_review_only' => $review['entries'][0]['placement_review_only'] ?? false,
    'first_interpolate' => $review['entries'][0]['interpolate'] ?? null,
    'first_rendering_intent' => $review['entries'][0]['rendering_intent'] ?? null,
    'first_image_name' => $review['entries'][0]['image_name'] ?? null,
    'first_struct_parent' => $review['entries'][0]['struct_parent'] ?? null,
    'first_struct_parents' => $review['entries'][0]['struct_parents'] ?? null,
    'first_soft_mask_object' => $review['entries'][0]['soft_mask_object'] ?? null,
    'first_soft_mask_generation' => $review['entries'][0]['soft_mask_generation'] ?? null,
    'first_soft_mask_type' => $review['entries'][0]['soft_mask_review']['type'] ?? null,
    'first_soft_mask_decoded_with_current_filters' => $review['entries'][0]['soft_mask_review']['decoded_with_current_filters'] ?? false,
    'first_soft_mask_decode_inverted' => ($review['entries'][0]['soft_mask_review']['decode']['inverted_components'] ?? []) === [0],
    'first_soft_mask_review_only' => $review['entries'][0]['soft_mask_review_only'] ?? false,
    'stale_soft_mask_generation_rejected' => ($review['entries'][0]['soft_mask_review']['decoded_sha256'] ?? null) !== hash('sha256', $staleSoftMaskPayload),
    'first_mask_object' => $review['entries'][0]['mask_object'] ?? null,
    'first_mask_type' => $review['entries'][0]['mask_review']['type'] ?? null,
    'first_mask_decoded_with_current_filters' => $review['entries'][0]['mask_review']['decoded_with_current_filters'] ?? false,
    'first_mask_decode_inverted' => ($review['entries'][0]['mask_review']['decode']['inverted_components'] ?? []) === [0],
    'stale_mask_generation_rejected' => ($review['entries'][0]['mask_review']['decoded_sha256'] ?? null) !== hash('sha256', $staleMaskPayload),
    'first_metadata_object' => $review['entries'][0]['metadata_stream']['object_number'] ?? null,
    'first_metadata_subtype' => $review['entries'][0]['metadata_stream']['subtype'] ?? null,
    'first_metadata_filters' => $review['entries'][0]['metadata_stream']['filters'] ?? [],
    'first_metadata_decoded_with_current_filters' => $review['entries'][0]['metadata_stream']['decoded_with_current_filters'] ?? false,
    'first_metadata_decoded_length' => $review['entries'][0]['metadata_stream']['decoded_length'] ?? null,
    'stale_metadata_generation_rejected' => ($review['entries'][0]['metadata_stream']['decoded_sha256'] ?? null) !== hash('sha256', $staleMetadataPayload),
    'first_alternate_image_count' => $review['entries'][0]['alternate_image_count'] ?? null,
    'first_alternate_images_review_only' => $review['entries'][0]['alternates_review_only'] ?? false,
    'first_print_alternate_object' => $review['entries'][0]['alternate_images'][0]['object_number'] ?? null,
    'first_print_alternate_default_for_printing' => $review['entries'][0]['alternate_images'][0]['default_for_printing'] ?? null,
    'first_print_alternate_decoded_with_current_filters' => $review['entries'][0]['alternate_images'][0]['decoded_with_current_filters'] ?? false,
    'stale_print_alternate_generation_rejected' => ($review['entries'][0]['alternate_images'][0]['decoded_sha256'] ?? null) !== hash('sha256', $stalePrintAlternatePayload),
    'first_screen_alternate_object' => $review['entries'][0]['alternate_images'][1]['object_number'] ?? null,
    'first_screen_alternate_preview_only_filters' => $review['entries'][0]['alternate_images'][1]['preview_only_filters'] ?? [],
    'first_image_filters' => $review['entries'][0]['filters'] ?? [],
    'first_image_decoded_with_current_filters' => $review['entries'][0]['decoded_with_current_filters'] ?? false,
    'hidden_marked_invoked' => $entriesByName['HiddenMarked']['invoked'] ?? true,
    'hidden_marked_mask_type' => $entriesByName['HiddenMarked']['mask_review']['type'] ?? null,
    'hidden_marked_color_key_valid' => $entriesByName['HiddenMarked']['mask_review']['valid_for_components'] ?? false,
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
