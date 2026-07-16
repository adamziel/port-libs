<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_duplicate_numeric_operand_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before duplicate numeric image operands) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Duplicate#20Width#20Image Do Q\n"
        . "q 10 0 0 5 100 690 cm /Duplicate#20Height#20Image Do Q\n"
        . "q 8 0 0 4 126 690 cm /Duplicate#20Bpc#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After duplicate numeric image operands) Tj ET';
    $duplicateWidthPayload = 'BT /F1 12 Tf 72 720 Td (Duplicate Width Image Payload Noise) Tj ET';
    $duplicateHeightPayload = 'BT /F1 12 Tf 72 720 Td (Duplicate Height Image Payload Noise) Tj ET';
    $duplicateBpcPayload = 'BT /F1 12 Tf 72 720 Td (Duplicate BPC Image Payload Noise) Tj ET';
    $duplicateWidthCompressed = gzcompress($duplicateWidthPayload);
    $duplicateHeightCompressed = gzcompress($duplicateHeightPayload);
    $duplicateBpcCompressed = gzcompress($duplicateBpcPayload);
    if (
        !is_string($duplicateWidthCompressed)
        || !is_string($duplicateHeightCompressed)
        || !is_string($duplicateBpcCompressed)
    ) {
        throw new RuntimeException('Unable to compress duplicate Image XObject numeric operand fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Duplicate#20Width#20Image 5 0 R /Duplicate#20Height#20Image 6 0 R /Duplicate#20Bpc#20Image 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Width 4 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($duplicateWidthCompressed) . " >>\nstream\n{$duplicateWidthCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /Height 3 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($duplicateHeightCompressed) . " >>\nstream\n{$duplicateHeightCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /BitsPerComponent 1 /Filter /FlateDecode /Length " . strlen($duplicateBpcCompressed) . " >>\nstream\n{$duplicateBpcCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $duplicateWidthPayload, $duplicateHeightPayload, $duplicateBpcPayload];
}

return [
    'fails closed on duplicate Image XObject numeric operands before raster handoff' => static function (TestRunner $t): void {
        [$pdf, $duplicateWidthPayload, $duplicateHeightPayload, $duplicateBpcPayload] = markerpdf_image_xobject_duplicate_numeric_operand_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(3, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $duplicateWidth = $entriesByName['Duplicate Width Image'];
        $t->same(true, $duplicateWidth['invoked']);
        $t->same(1, $duplicateWidth['invocation_count']);
        $t->same(2, $duplicateWidth['width']);
        $t->same(1, $duplicateWidth['height']);
        $t->same(false, $duplicateWidth['image_dimensions_valid']);
        $t->same(false, $duplicateWidth['native_raster_decode']);
        $t->same(true, $duplicateWidth['decoded_with_current_filters']);
        $t->same(strlen($duplicateWidthPayload), $duplicateWidth['decoded_length']);
        $t->same(hash('sha256', $duplicateWidthPayload), $duplicateWidth['decoded_sha256']);
        $t->same(false, $duplicateWidth['payload_in_visible_text']);
        $t->same(false, $duplicateWidth['image_dimension_boundary']['width_operand_valid']);
        $t->same(true, $duplicateWidth['image_dimension_boundary']['height_operand_valid']);
        $t->same('duplicate_top_level_declaration', $duplicateWidth['image_dimension_boundary']['width_operand_boundary']['reason']);
        $t->same(1, $duplicateWidth['image_dimension_boundary']['width_operand_boundary']['duplicate_declaration_count']);
        $t->same('reject_malformed_image_dimension_operands', $duplicateWidth['image_dimension_boundary']['policy']);

        $duplicateHeight = $entriesByName['Duplicate Height Image'];
        $t->same(true, $duplicateHeight['invoked']);
        $t->same(1, $duplicateHeight['invocation_count']);
        $t->same(2, $duplicateHeight['width']);
        $t->same(1, $duplicateHeight['height']);
        $t->same(false, $duplicateHeight['image_dimensions_valid']);
        $t->same(false, $duplicateHeight['native_raster_decode']);
        $t->same(true, $duplicateHeight['decoded_with_current_filters']);
        $t->same(strlen($duplicateHeightPayload), $duplicateHeight['decoded_length']);
        $t->same(hash('sha256', $duplicateHeightPayload), $duplicateHeight['decoded_sha256']);
        $t->same(false, $duplicateHeight['payload_in_visible_text']);
        $t->same(true, $duplicateHeight['image_dimension_boundary']['width_operand_valid']);
        $t->same(false, $duplicateHeight['image_dimension_boundary']['height_operand_valid']);
        $t->same('duplicate_top_level_declaration', $duplicateHeight['image_dimension_boundary']['height_operand_boundary']['reason']);
        $t->same(1, $duplicateHeight['image_dimension_boundary']['height_operand_boundary']['duplicate_declaration_count']);
        $t->same('reject_malformed_image_dimension_operands', $duplicateHeight['image_dimension_boundary']['policy']);

        $duplicateBpc = $entriesByName['Duplicate Bpc Image'];
        $t->same(true, $duplicateBpc['invoked']);
        $t->same(1, $duplicateBpc['invocation_count']);
        $t->same(2, $duplicateBpc['width']);
        $t->same(1, $duplicateBpc['height']);
        $t->same(8, $duplicateBpc['bits_per_component']);
        $t->same(true, $duplicateBpc['image_dimensions_valid']);
        $t->same(false, $duplicateBpc['native_raster_decode']);
        $t->same(true, $duplicateBpc['decoded_with_current_filters']);
        $t->same(strlen($duplicateBpcPayload), $duplicateBpc['decoded_length']);
        $t->same(hash('sha256', $duplicateBpcPayload), $duplicateBpc['decoded_sha256']);
        $t->same(false, $duplicateBpc['payload_in_visible_text']);
        $t->same('BitsPerComponent', $duplicateBpc['bits_per_component_boundary']['name']);
        $t->same(8, $duplicateBpc['bits_per_component_boundary']['resolved_integer']);
        $t->same('duplicate_top_level_declaration', $duplicateBpc['bits_per_component_boundary']['reason']);
        $t->same(1, $duplicateBpc['bits_per_component_boundary']['duplicate_declaration_count']);
        $t->same('reject_malformed_image_numeric_operand', $duplicateBpc['bits_per_component_boundary']['policy']);
        $t->same(true, $duplicateBpc['bits_per_component_boundary']['native_raster_decode_blocked']);

        $t->same(['Before duplicate numeric image operands', 'After duplicate numeric image operands'], $extractor->extractTextLines($pdf));
        $t->same("Before duplicate numeric image operands\nAfter duplicate numeric image operands", $plainText);
        $t->true(!str_contains($plainText, 'Duplicate Width Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Duplicate Height Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Duplicate BPC Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $duplicateWidthPayload));
        $t->true(!str_contains($encoded, $duplicateHeightPayload));
        $t->true(!str_contains($encoded, $duplicateBpcPayload));
    },
];
