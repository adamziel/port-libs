<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_top_level_dimension_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before nested image dimensions) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Nested#20Dimension#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After nested image dimensions) Tj ET';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Top Level Image Payload Noise) Tj ET';
    $softMaskPayload = 'BT /F1 12 Tf 72 720 Td (Top Level Soft Mask Payload Noise) Tj ET';
    $maskPayload = 'BT /F1 12 Tf 72 720 Td (Top Level Mask Payload Noise) Tj ET';
    $alternatePayload = 'BT /F1 12 Tf 72 720 Td (Top Level Alternate Payload Noise) Tj ET';
    $imageCompressed = gzcompress($imagePayload);
    $softMaskCompressed = gzcompress($softMaskPayload);
    $maskCompressed = gzcompress($maskPayload);
    $alternateCompressed = gzcompress($alternatePayload);
    if (
        !is_string($imageCompressed)
        || !is_string($softMaskCompressed)
        || !is_string($maskCompressed)
        || !is_string($alternateCompressed)
    ) {
        throw new RuntimeException('Unable to compress Image XObject top-level dimension fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Nested#20Dimension#20Image 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 99 /Height 88 /BitsPerComponent 1 /StructParent 44 /StructParents 45 >> /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /StructParent 7 /StructParents 8 /Filter /FlateDecode /SMask 6 0 R /Mask 7 0 R /Alternates [<< /Image 8 0 R /DefaultForPrinting true >>] /Length " . strlen($imageCompressed) . " >>\nstream\n{$imageCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 80 /Height 70 /BitsPerComponent 2 >> /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($softMaskCompressed) . " >>\nstream\n{$softMaskCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 60 /Height 50 /BitsPerComponent 2 >> /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($maskCompressed) . " >>\nstream\n{$maskCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Width 40 /Height 30 /BitsPerComponent 2 >> /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateCompressed) . " >>\nstream\n{$alternateCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload, $softMaskPayload, $maskPayload, $alternatePayload];
}

return [
    'uses only top-level Image XObject dimensions before review metadata and raster handoff' => static function (TestRunner $t): void {
        [$pdf, $imagePayload, $softMaskPayload, $maskPayload, $alternatePayload] = markerpdf_image_xobject_top_level_dimension_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entry = $review['entries'][0];
        $t->same('Nested Dimension Image', $entry['resource_name']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same(2, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same(8, $entry['bits_per_component']);
        $t->same(7, $entry['struct_parent']);
        $t->same(8, $entry['struct_parents']);
        $t->same(true, $entry['image_dimensions_valid']);
        $t->same(null, $entry['image_dimension_boundary'] ?? null);
        $t->same(true, $entry['native_raster_decode']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $entry['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);

        $softMask = $entry['soft_mask_review'];
        $t->same('soft_mask_stream', $softMask['type']);
        $t->same(2, $softMask['width']);
        $t->same(1, $softMask['height']);
        $t->same(8, $softMask['bits_per_component']);
        $t->same(strlen($softMaskPayload), $softMask['decoded_length']);
        $t->same(hash('sha256', $softMaskPayload), $softMask['decoded_sha256']);

        $mask = $entry['mask_review'];
        $t->same('image_mask_stream', $mask['type']);
        $t->same(2, $mask['width']);
        $t->same(1, $mask['height']);
        $t->same(1, $mask['bits_per_component']);
        $t->same(strlen($maskPayload), $mask['decoded_length']);
        $t->same(hash('sha256', $maskPayload), $mask['decoded_sha256']);

        $alternate = $entry['alternate_images'][0];
        $t->same(4, $alternate['width']);
        $t->same(2, $alternate['height']);
        $t->same(8, $alternate['bits_per_component']);
        $t->same(strlen($alternatePayload), $alternate['decoded_length']);
        $t->same(hash('sha256', $alternatePayload), $alternate['decoded_sha256']);

        $t->same(['Before nested image dimensions', 'After nested image dimensions'], $extractor->extractTextLines($pdf));
        $t->same("Before nested image dimensions\nAfter nested image dimensions", $plainText);
        $t->true(!str_contains($plainText, 'Top Level Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Top Level Soft Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Top Level Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Top Level Alternate Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(!str_contains($encoded, $softMaskPayload));
        $t->true(!str_contains($encoded, $maskPayload));
        $t->true(!str_contains($encoded, $alternatePayload));
    },
];
