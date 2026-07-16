<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'recognizes Separation and DeviceN image XObject color spaces before RGB review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before alternate color-space images) Tj ET\n"
            . "q 20 0 0 10 72 690 cm /Sep#20Image Do Q\n"
            . "q 18 0 0 9 104 690 cm /DeviceN#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After alternate color-space images) Tj ET';
        $separationPayload = 'BT /F1 12 Tf 72 720 Td (Separation Image Payload Noise) Tj ET';
        $deviceNPayload = 'BT /F1 12 Tf 72 720 Td (DeviceN Image Payload Noise) Tj ET';
        $separationCompressed = gzcompress($separationPayload);
        $deviceNCompressed = gzcompress($deviceNPayload);
        if (!is_string($separationCompressed) || !is_string($deviceNCompressed)) {
            throw new RuntimeException('Unable to compress alternate color-space image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Sep#20Image 5 0 R /DeviceN#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Separation /Spot#20Red /DeviceCMYK 40 0 R] /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($separationCompressed) . " >>\nstream\n{$separationCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /Length " . strlen($deviceNCompressed) . " >>\nstream\n{$deviceNCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "40 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>\nstream\n{ dup dup dup }\nendstream\nendobj\n"
            . "60 0 obj\n<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>\nstream\n{ pop dup dup dup }\nendstream\nendobj\n%%EOF";

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
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $separation = $entriesByName['Sep Image'];
        $t->same('Separation', $separation['color_space']);
        $t->same(1, $separation['color_space_component_count']);
        $t->same([
            'ranges' => [
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 1,
            'expected_components' => 1,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [0],
            'source' => 'explicit',
        ], $separation['image_decode']);
        $t->same(true, $separation['image_decode_applied_before_rgb']);
        $t->same(false, $separation['image_decode_component_mismatch']);
        $t->same([72.0, 690.0, 92.0, 700.0], $separation['image_visible_bbox']);
        $t->same(true, $separation['native_raster_decode']);
        $t->same(strlen($separationPayload), $separation['decoded_length']);
        $t->same(hash('sha256', $separationPayload), $separation['decoded_sha256']);

        $deviceN = $entriesByName['DeviceN Image'];
        $t->same('DeviceN', $deviceN['color_space']);
        $t->same(2, $deviceN['color_space_component_count']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 2,
            'expected_components' => 2,
            'valid_for_components' => true,
            'identity' => false,
            'inverted_components' => [1],
            'source' => 'explicit',
        ], $deviceN['image_decode']);
        $t->same(true, $deviceN['image_decode_applied_before_rgb']);
        $t->same(false, $deviceN['image_decode_component_mismatch']);
        $t->same([104.0, 690.0, 122.0, 699.0], $deviceN['image_visible_bbox']);
        $t->same(true, $deviceN['native_raster_decode']);
        $t->same(strlen($deviceNPayload), $deviceN['decoded_length']);
        $t->same(hash('sha256', $deviceNPayload), $deviceN['decoded_sha256']);

        $t->same(
            ['Before alternate color-space images', 'After alternate color-space images'],
            $extractor->extractTextLines($pdf)
        );
        $t->same("Before alternate color-space images\nAfter alternate color-space images", $plainText);
        $t->true(!str_contains($plainText, 'Separation Image Payload Noise'));
        $t->true(!str_contains($plainText, 'DeviceN Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $separationPayload));
        $t->true(!str_contains($encoded, $deviceNPayload));
    },
];
