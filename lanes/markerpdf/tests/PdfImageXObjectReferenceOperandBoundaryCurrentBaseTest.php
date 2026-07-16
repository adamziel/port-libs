<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects tailed image XObject reference operands before hidden stream review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before image reference operand boundary) Tj ET\n"
            . "q 20 0 0 10 72 690 cm /Soft#20Mask#20Tail#20Image Do Q\n"
            . "q 18 0 0 9 102 690 cm /Mask#20Tail#20Image Do Q\n"
            . "q 16 0 0 8 130 690 cm /Mask#20Array#20Tail#20Image Do Q\n"
            . "q 14 0 0 7 154 690 cm /Metadata#20Tail#20Image Do Q\n"
            . "q 12 0 0 6 176 690 cm /Valid#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After image reference operand boundary) Tj ET';

        $softMaskImagePayload = 'BT /F1 12 Tf 72 720 Td (Soft Mask Tail Image Payload Noise) Tj ET';
        $softMaskPayload = 'BT /F1 12 Tf 72 720 Td (Tailed Soft Mask Hidden Payload Noise) Tj ET';
        $maskImagePayload = 'BT /F1 12 Tf 72 720 Td (Mask Tail Image Payload Noise) Tj ET';
        $maskPayload = 'BT /F1 12 Tf 72 720 Td (Tailed Mask Hidden Payload Noise) Tj ET';
        $maskArrayImagePayload = 'BT /F1 12 Tf 72 720 Td (Mask Array Tail Image Payload Noise) Tj ET';
        $metadataImagePayload = 'BT /F1 12 Tf 72 720 Td (Metadata Tail Image Payload Noise) Tj ET';
        $metadataPayload = '<x:xmpmeta><dc:title>Tailed Metadata Hidden Payload</dc:title></x:xmpmeta>';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Reference Operand Image Payload Noise) Tj ET';

        $softMaskImageCompressed = gzcompress($softMaskImagePayload);
        $softMaskCompressed = gzcompress($softMaskPayload);
        $maskImageCompressed = gzcompress($maskImagePayload);
        $maskCompressed = gzcompress($maskPayload);
        $maskArrayImageCompressed = gzcompress($maskArrayImagePayload);
        $metadataImageCompressed = gzcompress($metadataImagePayload);
        $metadataCompressed = gzcompress($metadataPayload);
        $validCompressed = gzcompress($validPayload);
        if (
            !is_string($softMaskImageCompressed)
            || !is_string($softMaskCompressed)
            || !is_string($maskImageCompressed)
            || !is_string($maskCompressed)
            || !is_string($maskArrayImageCompressed)
            || !is_string($metadataImageCompressed)
            || !is_string($metadataCompressed)
            || !is_string($validCompressed)
        ) {
            throw new RuntimeException('Unable to compress image reference operand boundary fixture payloads.');
        }

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Soft#20Mask#20Tail#20Image 5 0 R /Mask#20Tail#20Image 6 0 R /Mask#20Array#20Tail#20Image 7 0 R /Metadata#20Tail#20Image 8 0 R /Valid#20Image 9 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 20 0 R 99 0 R /Filter /FlateDecode /Length " . strlen($softMaskImageCompressed) . " >>\nstream\n{$softMaskImageCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Mask 21 0 R 99 0 R /Filter /FlateDecode /Length " . strlen($maskImageCompressed) . " >>\nstream\n{$maskImageCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Mask 23 0 R /Filter /FlateDecode /Length " . strlen($maskArrayImageCompressed) . " >>\nstream\n{$maskArrayImageCompressed}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Metadata 22 0 R 99 0 R /Filter /FlateDecode /Length " . strlen($metadataImageCompressed) . " >>\nstream\n{$metadataImageCompressed}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($softMaskCompressed) . " >>\nstream\n{$softMaskCompressed}\nendstream\nendobj\n"
            . "21 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Length " . strlen($maskCompressed) . " >>\nstream\n{$maskCompressed}\nendstream\nendobj\n"
            . "22 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataCompressed) . " >>\nstream\n{$metadataCompressed}\nendstream\nendobj\n"
            . "23 0 obj\n[0 255 0 255 0 255] 99 0 R\nendobj\n"
            . "99 0 obj\n<< /S /JavaScript /JS (app.alert\\('tailed image reference operand'\\)) >>\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(5, $review['image_xobject_count']);
        $t->same(5, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $softMaskTail = $entriesByName['Soft Mask Tail Image'];
        $t->same(false, $softMaskTail['native_raster_decode']);
        $t->same(true, $softMaskTail['decoded_with_current_filters']);
        $t->same(hash('sha256', $softMaskImagePayload), $softMaskTail['decoded_sha256']);
        $t->same(null, $softMaskTail['soft_mask_object']);
        $t->same(null, $softMaskTail['soft_mask_generation']);
        $t->same('soft_mask_reference_operand_boundary', $softMaskTail['soft_mask_review']['type']);
        $t->same('SMask', $softMaskTail['soft_mask_review']['name']);
        $t->same(20, $softMaskTail['soft_mask_review']['tailed_object_number']);
        $t->same(0, $softMaskTail['soft_mask_review']['tailed_generation']);
        $t->same(true, $softMaskTail['soft_mask_review']['resolved']);
        $t->same(false, $softMaskTail['soft_mask_review']['valid_reference_operand']);
        $t->same('trailing_top_level_operand', $softMaskTail['soft_mask_review']['reason']);
        $t->same('reject_malformed_image_reference_operand', $softMaskTail['soft_mask_review']['policy']);
        $t->same(true, $softMaskTail['soft_mask_review']['native_raster_decode_blocked']);
        $t->same(false, $softMaskTail['soft_mask_review']['payload_in_visible_text']);
        $t->same(true, $softMaskTail['soft_mask_review']['review_only']);

        $maskTail = $entriesByName['Mask Tail Image'];
        $t->same(false, $maskTail['native_raster_decode']);
        $t->same(true, $maskTail['decoded_with_current_filters']);
        $t->same(hash('sha256', $maskImagePayload), $maskTail['decoded_sha256']);
        $t->same(null, $maskTail['mask_object']);
        $t->same(null, $maskTail['mask_generation']);
        $t->same('mask_reference_operand_boundary', $maskTail['mask_review']['type']);
        $t->same('Mask', $maskTail['mask_review']['name']);
        $t->same(21, $maskTail['mask_review']['tailed_object_number']);
        $t->same(false, $maskTail['mask_review']['valid_reference_operand']);
        $t->same('trailing_top_level_operand', $maskTail['mask_review']['reason']);
        $t->same(true, $maskTail['mask_review']['native_raster_decode_blocked']);
        $t->same(false, $maskTail['mask_review']['payload_in_visible_text']);

        $maskArrayTail = $entriesByName['Mask Array Tail Image'];
        $t->same(false, $maskArrayTail['native_raster_decode']);
        $t->same(true, $maskArrayTail['decoded_with_current_filters']);
        $t->same(hash('sha256', $maskArrayImagePayload), $maskArrayTail['decoded_sha256']);
        $t->same(null, $maskArrayTail['mask_object']);
        $t->same(null, $maskArrayTail['mask_generation']);
        $t->same('mask_array_operand_boundary', $maskArrayTail['mask_review']['type']);
        $t->same('Mask', $maskArrayTail['mask_review']['name']);
        $t->same(23, $maskArrayTail['mask_review']['tailed_object_number']);
        $t->same(false, $maskArrayTail['mask_review']['valid_array_operand']);
        $t->same('trailing_indirect_array_operand', $maskArrayTail['mask_review']['reason']);
        $t->same(true, $maskArrayTail['mask_review']['native_raster_decode_blocked']);
        $t->same(false, $maskArrayTail['mask_review']['payload_in_visible_text']);

        $metadataTail = $entriesByName['Metadata Tail Image'];
        $t->same(true, $metadataTail['native_raster_decode']);
        $t->same(true, $metadataTail['decoded_with_current_filters']);
        $t->same(hash('sha256', $metadataImagePayload), $metadataTail['decoded_sha256']);
        $t->same('rejected_malformed_image_xobject_metadata_stream_reference_operand', $metadataTail['metadata_stream']['status']);
        $t->same('Metadata', $metadataTail['metadata_stream']['name']);
        $t->same(22, $metadataTail['metadata_stream']['tailed_object_number']);
        $t->same(false, $metadataTail['metadata_stream']['valid_reference_operand']);
        $t->same(false, $metadataTail['metadata_stream']['native_raster_decode_blocked']);
        $t->same('reject_malformed_reference_operands', $metadataTail['metadata_stream']['reference_operand_policy']);
        $t->same(false, $metadataTail['metadata_stream']['decoded_with_current_filters']);
        $t->same(null, $metadataTail['metadata_stream']['decoded_sha256']);
        $t->same(false, $metadataTail['metadata_stream']['payload_in_visible_text']);

        $valid = $entriesByName['Valid Image'];
        $t->same(true, $valid['native_raster_decode']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, isset($valid['soft_mask_review']));
        $t->same(false, isset($valid['mask_review']));
        $t->same(false, isset($valid['metadata_stream']));

        $t->same(['Before image reference operand boundary', 'After image reference operand boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before image reference operand boundary\nAfter image reference operand boundary", $plainText);

        foreach ([
            $softMaskImagePayload,
            $softMaskPayload,
            $maskImagePayload,
            $maskPayload,
            $maskArrayImagePayload,
            $metadataImagePayload,
            $metadataPayload,
            $validPayload,
            'tailed image reference operand',
        ] as $hiddenText) {
            $t->true(!str_contains($plainText, $hiddenText));
        }

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encoded, hash('sha256', $softMaskImagePayload)));
        $t->true(str_contains($encoded, hash('sha256', $maskImagePayload)));
        $t->true(str_contains($encoded, hash('sha256', $maskArrayImagePayload)));
        $t->true(str_contains($encoded, hash('sha256', $metadataImagePayload)));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $softMaskPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $maskPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $metadataPayload)));
        $t->true(!str_contains($encoded, $softMaskPayload));
        $t->true(!str_contains($encoded, $maskPayload));
        $t->true(!str_contains($encoded, $metadataPayload));
        $t->true(!str_contains($encoded, 'tailed image reference operand'));
    },
];
