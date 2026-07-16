<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects image XObject numeric operands with trailing top-level tokens before raster handoff' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before image numeric operand boundary) Tj ET\n"
            . "q 18 0 0 9 72 690 cm /Width#20Tail#20Image Do Q\n"
            . "q 14 0 0 7 104 690 cm /Bpc#20Tail#20Image Do Q\n"
            . "q 12 0 0 6 132 690 cm /Valid#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After image numeric operand boundary) Tj ET';
        $widthTailPayload = 'BT /F1 12 Tf 72 720 Td (Width Tail Image Payload Noise) Tj ET';
        $bpcTailPayload = 'BT /F1 12 Tf 72 720 Td (Bits Tail Image Payload Noise) Tj ET';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Numeric Image Payload Noise) Tj ET';
        $widthTailCompressed = gzcompress($widthTailPayload);
        $bpcTailCompressed = gzcompress($bpcTailPayload);
        $validCompressed = gzcompress($validPayload);
        if (!is_string($widthTailCompressed) || !is_string($bpcTailCompressed) || !is_string($validCompressed)) {
            throw new RuntimeException('Unable to compress Image XObject numeric operand fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Width#20Tail#20Image 5 0 R /Bpc#20Tail#20Image 6 0 R /Valid#20Image 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 99 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($widthTailCompressed) . " >>\nstream\n{$widthTailCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 99 /Filter /FlateDecode /Length " . strlen($bpcTailCompressed) . " >>\nstream\n{$bpcTailCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(3, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $widthTail = $entriesByName['Width Tail Image'];
        $t->same(true, $widthTail['invoked']);
        $t->same(1, $widthTail['invocation_count']);
        $t->same(2, $widthTail['width']);
        $t->same(1, $widthTail['height']);
        $t->same(false, $widthTail['image_dimensions_valid']);
        $t->same(false, $widthTail['native_raster_decode']);
        $t->same(true, $widthTail['decoded_with_current_filters']);
        $t->same(hash('sha256', $widthTailPayload), $widthTail['decoded_sha256']);
        $t->same(false, $widthTail['payload_in_visible_text']);
        $t->same(false, $widthTail['image_dimension_boundary']['width_operand_valid']);
        $t->same(true, $widthTail['image_dimension_boundary']['height_operand_valid']);
        $t->same('trailing_top_level_operand', $widthTail['image_dimension_boundary']['width_operand_boundary']['reason']);
        $t->same('reject_malformed_image_dimension_operands', $widthTail['image_dimension_boundary']['policy']);

        $bpcTail = $entriesByName['Bpc Tail Image'];
        $t->same(true, $bpcTail['invoked']);
        $t->same(1, $bpcTail['invocation_count']);
        $t->same(2, $bpcTail['width']);
        $t->same(1, $bpcTail['height']);
        $t->same(8, $bpcTail['bits_per_component']);
        $t->same(true, $bpcTail['image_dimensions_valid']);
        $t->same(false, $bpcTail['native_raster_decode']);
        $t->same(true, $bpcTail['decoded_with_current_filters']);
        $t->same(hash('sha256', $bpcTailPayload), $bpcTail['decoded_sha256']);
        $t->same(false, $bpcTail['payload_in_visible_text']);
        $t->same('BitsPerComponent', $bpcTail['bits_per_component_boundary']['name']);
        $t->same(8, $bpcTail['bits_per_component_boundary']['resolved_integer']);
        $t->same('trailing_top_level_operand', $bpcTail['bits_per_component_boundary']['reason']);
        $t->same(true, $bpcTail['bits_per_component_boundary']['native_raster_decode_blocked']);
        $t->same(true, $bpcTail['bits_per_component_boundary']['review_only']);

        $valid = $entriesByName['Valid Image'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same(true, $valid['image_dimensions_valid']);
        $t->same(true, $valid['native_raster_decode']);
        $t->same(false, isset($valid['image_dimension_boundary']));
        $t->same(false, isset($valid['bits_per_component_boundary']));
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before image numeric operand boundary', 'After image numeric operand boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before image numeric operand boundary\nAfter image numeric operand boundary", $plainText);
        $t->true(!str_contains($plainText, 'Width Tail Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Bits Tail Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Numeric Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $widthTailPayload));
        $t->true(!str_contains($encoded, $bpcTailPayload));
        $t->true(!str_contains($encoded, $validPayload));
    },
];
