<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_top_level_subtype_review_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before top-level subtype image review) Tj ET\n"
        . "q 14 0 0 7 72 690 cm /Primary#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After top-level subtype image review) Tj ET';

    $primaryPayload = 'BT /F1 12 Tf 72 720 Td (Primary Top Level Subtype Image Payload Noise) Tj ET';
    $softMaskPayload = 'BT /F1 12 Tf 72 720 Td (Soft Mask Top Level Subtype Payload Noise) Tj ET';
    $maskPayload = 'BT /F1 12 Tf 72 720 Td (Mask Top Level Subtype Payload Noise) Tj ET';
    $alternatePayload = 'BT /F1 12 Tf 72 720 Td (Alternate Top Level Subtype Payload Noise) Tj ET';
    $metadataPayload = '<x:xmpmeta><dc:title>Top Level Subtype Metadata Noise</dc:title></x:xmpmeta>';

    $primaryCompressed = gzcompress($primaryPayload);
    $softMaskCompressed = gzcompress($softMaskPayload);
    $maskCompressed = gzcompress($maskPayload);
    $alternateCompressed = gzcompress($alternatePayload);
    $metadataCompressed = gzcompress($metadataPayload);
    if (
        !is_string($primaryCompressed)
        || !is_string($softMaskCompressed)
        || !is_string($maskCompressed)
        || !is_string($alternateCompressed)
        || !is_string($metadataCompressed)
    ) {
        throw new RuntimeException('Unable to compress Image XObject top-level subtype fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Primary#20Image 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Private << /Subtype /PS /Type /Metadata >> /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 6 0 R /Mask 7 0 R /Alternates [<< /Image 8 0 R /DefaultForPrinting true >>] /Metadata 9 0 R /Filter /FlateDecode /Length " . strlen($primaryCompressed) . " >>\nstream\n{$primaryCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Private << /Subtype /Form >> /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [0 1] /Filter /FlateDecode /Length " . strlen($softMaskCompressed) . " >>\nstream\n{$softMaskCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Private << /Subtype /PS >> /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Decode [1 0] /Filter /FlateDecode /Length " . strlen($maskCompressed) . " >>\nstream\n{$maskCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Private << /Subtype /Metadata >> /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateCompressed) . " >>\nstream\n{$alternateCompressed}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Private << /Subtype /Image >> /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataCompressed) . " >>\nstream\n{$metadataCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $primaryPayload, $softMaskPayload, $maskPayload, $alternatePayload, $metadataPayload];
}

return [
    'reports Image XObject stream subtypes from top-level dictionaries only' => static function (TestRunner $t): void {
        [$pdf, $primaryPayload, $softMaskPayload, $maskPayload, $alternatePayload, $metadataPayload] = markerpdf_image_xobject_top_level_subtype_review_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entry = $review['entries'][0] ?? null;
        $t->true(is_array($entry), 'Primary image review row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Primary Image', $entry['resource_name']);
        $t->same(5, $entry['object_number']);
        $t->same('Image', $entry['subtype']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[14.0, 0.0, 0.0, 7.0, 72.0, 690.0]], $entry['invocation_matrices']);
        $t->same([72.0, 690.0, 86.0, 697.0], $entry['image_unit_bbox']);
        $t->same(2, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same('DeviceRGB', $entry['color_space']);
        $t->same(8, $entry['bits_per_component']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($primaryPayload), $entry['decoded_length']);
        $t->same(hash('sha256', $primaryPayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);

        $softMask = $entry['soft_mask_review'];
        $t->same('soft_mask_stream', $softMask['type']);
        $t->same('Image', $softMask['subtype']);
        $t->same(6, $softMask['object_number']);
        $t->same(2, $softMask['width']);
        $t->same(1, $softMask['height']);
        $t->same('DeviceGray', $softMask['color_space']);
        $t->same(8, $softMask['bits_per_component']);
        $t->same(hash('sha256', $softMaskPayload), $softMask['decoded_sha256']);
        $t->same(false, $softMask['payload_in_visible_text']);

        $mask = $entry['mask_review'];
        $t->same('image_mask_stream', $mask['type']);
        $t->same('Image', $mask['subtype']);
        $t->same(7, $mask['object_number']);
        $t->same(2, $mask['width']);
        $t->same(1, $mask['height']);
        $t->same(true, $mask['image_mask']);
        $t->same(1, $mask['bits_per_component']);
        $t->same(hash('sha256', $maskPayload), $mask['decoded_sha256']);
        $t->same(false, $mask['payload_in_visible_text']);

        $alternate = $entry['alternate_images'][0] ?? null;
        $t->true(is_array($alternate), 'Alternate image review row should be present.');
        if (!is_array($alternate)) {
            return;
        }
        $t->same('Image', $alternate['subtype']);
        $t->same(8, $alternate['object_number']);
        $t->same(4, $alternate['width']);
        $t->same(2, $alternate['height']);
        $t->same('DeviceRGB', $alternate['color_space']);
        $t->same(8, $alternate['bits_per_component']);
        $t->same(hash('sha256', $alternatePayload), $alternate['decoded_sha256']);
        $t->same(false, $alternate['payload_in_visible_text']);

        $metadata = $entry['metadata_stream'];
        $t->same(9, $metadata['object_number']);
        $t->same('XML', $metadata['subtype']);
        $t->same(true, $metadata['decoded_with_current_filters']);
        $t->same(hash('sha256', $metadataPayload), $metadata['decoded_sha256']);
        $t->same(false, $metadata['payload_in_visible_text']);
        $t->same(true, $metadata['review_only']);

        $t->same(['Before top-level subtype image review', 'After top-level subtype image review'], $extractor->extractTextLines($pdf));
        $t->same("Before top-level subtype image review\nAfter top-level subtype image review", $plainText);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([$primaryPayload, $softMaskPayload, $maskPayload, $alternatePayload, $metadataPayload] as $payload) {
            $t->true(!str_contains($plainText, $payload));
            $t->true(!str_contains($encoded, $payload));
        }
    },
];
