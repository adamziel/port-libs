<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects tailed Image XObject Alternates operands before alternate stream review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before tailed alternate images) Tj ET\n"
            . "q 18 0 0 9 72 690 cm /Direct#20Tail#20Image Do Q\n"
            . "q 16 0 0 8 100 690 cm /Indirect#20Tail#20Image Do Q\n"
            . "q 14 0 0 7 126 690 cm /Valid#20Alternate#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After tailed alternate images) Tj ET';

        $directPrimaryPayload = 'BT /F1 12 Tf 72 720 Td (Direct Tail Primary Image Payload Noise) Tj ET';
        $directAlternatePayload = 'BT /F1 12 Tf 72 720 Td (Direct Tail Alternate Payload Must Not Be Selected) Tj ET';
        $indirectPrimaryPayload = 'BT /F1 12 Tf 72 720 Td (Indirect Tail Primary Image Payload Noise) Tj ET';
        $indirectAlternatePayload = 'BT /F1 12 Tf 72 720 Td (Indirect Tail Alternate Payload Must Not Be Selected) Tj ET';
        $validPrimaryPayload = 'BT /F1 12 Tf 72 720 Td (Valid Alternate Primary Image Payload Noise) Tj ET';
        $validAlternatePayload = 'BT /F1 12 Tf 72 720 Td (Valid Alternate Payload Review Only) Tj ET';

        $directPrimaryCompressed = gzcompress($directPrimaryPayload);
        $directAlternateCompressed = gzcompress($directAlternatePayload);
        $indirectPrimaryCompressed = gzcompress($indirectPrimaryPayload);
        $indirectAlternateCompressed = gzcompress($indirectAlternatePayload);
        $validPrimaryCompressed = gzcompress($validPrimaryPayload);
        $validAlternateCompressed = gzcompress($validAlternatePayload);
        if (
            !is_string($directPrimaryCompressed)
            || !is_string($directAlternateCompressed)
            || !is_string($indirectPrimaryCompressed)
            || !is_string($indirectAlternateCompressed)
            || !is_string($validPrimaryCompressed)
            || !is_string($validAlternateCompressed)
        ) {
            throw new RuntimeException('Unable to compress Image XObject Alternates operand fixture payloads.');
        }

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Direct#20Tail#20Image 5 0 R /Indirect#20Tail#20Image 6 0 R /Valid#20Alternate#20Image 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($directPrimaryCompressed) . " /Alternates [<< /Image 8 0 R /DefaultForPrinting true >>] 99 0 R >>\nstream\n{$directPrimaryCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Alternates 20 0 R /Length " . strlen($indirectPrimaryCompressed) . " >>\nstream\n{$indirectPrimaryCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Alternates [<< /Image 10 0 R /DefaultForPrinting false >>] /Length " . strlen($validPrimaryCompressed) . " >>\nstream\n{$validPrimaryCompressed}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($directAlternateCompressed) . " >>\nstream\n{$directAlternateCompressed}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($indirectAlternateCompressed) . " >>\nstream\n{$indirectAlternateCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validAlternateCompressed) . " >>\nstream\n{$validAlternateCompressed}\nendstream\nendobj\n"
            . "20 0 obj\n[<< /Image 9 0 R /DefaultForPrinting true >>] 99 0 R\nendobj\n"
            . "99 0 obj\n<< /S /JavaScript /JS (app.alert\\('tailed alternates operand'\\)) >>\nendobj\n"
            . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

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
        $t->same(['Before tailed alternate images', 'After tailed alternate images'], $extractor->extractTextLines($pdf));
        $t->same("Before tailed alternate images\nAfter tailed alternate images", $plainText);

        $direct = $entriesByName['Direct Tail Image'];
        $t->same(0, $direct['alternate_image_count']);
        $t->same([], $direct['alternate_images']);
        $t->same(true, $direct['alternates_review_only']);
        $t->same('Alternates', $direct['alternates_operand_boundary']['name']);
        $t->same('trailing_top_level_operand', $direct['alternates_operand_boundary']['reason']);
        $t->same(false, $direct['alternates_operand_boundary']['valid_array_operand']);
        $t->same(false, $direct['alternates_operand_boundary']['native_raster_decode_blocked']);
        $t->same('reject_malformed_image_alternates_operand', $direct['alternates_operand_boundary']['policy']);
        $t->same(false, $direct['alternates_operand_boundary']['payload_in_visible_text']);

        $indirect = $entriesByName['Indirect Tail Image'];
        $t->same(hash('sha256', $indirectPrimaryPayload), $indirect['decoded_sha256']);
        $t->same(0, $indirect['alternate_image_count']);
        $t->same([], $indirect['alternate_images']);
        $t->same(true, $indirect['alternates_review_only']);
        $t->same('Alternates', $indirect['alternates_operand_boundary']['name']);
        $t->same('trailing_indirect_array_operand', $indirect['alternates_operand_boundary']['reason']);
        $t->same(20, $indirect['alternates_operand_boundary']['tailed_object_number']);
        $t->same(0, $indirect['alternates_operand_boundary']['tailed_generation']);
        $t->same(false, $indirect['alternates_operand_boundary']['valid_array_operand']);
        $t->same(false, $indirect['alternates_operand_boundary']['native_raster_decode_blocked']);

        $valid = $entriesByName['Valid Alternate Image'];
        $t->same(1, $valid['alternate_image_count']);
        $t->same(true, $valid['alternates_review_only']);
        $t->same(false, isset($valid['alternates_operand_boundary']));
        $t->same(10, $valid['alternate_images'][0]['object_number']);
        $t->same(false, $valid['alternate_images'][0]['default_for_printing']);
        $t->same(hash('sha256', $validAlternatePayload), $valid['alternate_images'][0]['decoded_sha256']);

        foreach ([
            $directPrimaryPayload,
            $directAlternatePayload,
            $indirectPrimaryPayload,
            $indirectAlternatePayload,
            $validPrimaryPayload,
            $validAlternatePayload,
            'tailed alternates operand',
        ] as $hiddenText) {
            $t->true(!str_contains($plainText, $hiddenText));
        }

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encoded, hash('sha256', $indirectPrimaryPayload)));
        $t->true(str_contains($encoded, hash('sha256', $validPrimaryPayload)));
        $t->true(str_contains($encoded, hash('sha256', $validAlternatePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $directAlternatePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $indirectAlternatePayload)));
        $t->true(!str_contains($encoded, $directAlternatePayload));
        $t->true(!str_contains($encoded, $indirectAlternatePayload));
        $t->true(!str_contains($encoded, 'tailed alternates operand'));
    },
];
