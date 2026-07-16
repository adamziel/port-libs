<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'resolves wrapped tiling pattern resources before Image XObject boundary review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before wrapped pattern image) Tj ET\n"
            . "/Pattern cs /Wrapped#20Tile scn 0 0 20 10 re f\n"
            . "/Pattern cs /Cycle#20Tile scn 30 0 10 5 re f\n"
            . 'BT /F1 12 Tf 72 660 Td (After wrapped pattern image) Tj ET';
        $patternContent = 'q 6 0 0 3 1 2 cm /Tile#20Image Do Q';
        $tilePayload = 'BT /F1 12 Tf 72 720 Td (Wrapped Pattern Tile Image Payload Noise) Tj ET';
        $unusedPayload = 'BT /F1 12 Tf 72 720 Td (Unused Wrapped Pattern Image Payload Noise) Tj ET';
        $tileCompressed = gzcompress($tilePayload);
        $unusedCompressed = gzcompress($unusedPayload);
        if (!is_string($tileCompressed) || !is_string($unusedCompressed)) {
            throw new RuntimeException('Unable to compress wrapped pattern image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Wrapped#20Tile 12 0 R /Cycle#20Tile 13 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tileCompressed) . " >>\nstream\n{$tileCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unusedCompressed) . " >>\nstream\n{$unusedCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 20 20] /XStep 20 /YStep 20 /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Tile#20Image 5 0 R /Unused#20Wrapped#20Image 6 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
            . "12 0 obj\n11 0 R\nendobj\n"
            . "13 0 obj\n13 0 R\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(2, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(1, $review['uninvoked_image_xobject_count']);
        $t->same(false, isset($entriesByName['Cycle Tile']));

        $tile = $entriesByName['Tile Image'];
        $t->same('Wrapped Tile', $tile['pattern_resource_name'] ?? null);
        $t->same(11, $tile['parent_pattern_object'] ?? null);
        $t->same(0, $tile['parent_pattern_generation'] ?? null);
        $t->same(1, $tile['pattern_paint_count'] ?? null);
        $t->same([[1.0, 0.0, 0.0, 1.0, 3.0, 4.0]], $tile['pattern_matrices'] ?? null);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $tile['pattern_bboxes'] ?? null);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $tile['pattern_visible_bboxes'] ?? null);
        $t->same(true, $tile['pattern_review_only'] ?? null);
        $t->same(['Wrapped Tile', 'Tile Image'], $tile['resource_path']);
        $t->same(true, $tile['invoked']);
        $t->same(1, $tile['invocation_count']);
        $t->same([[6.0, 0.0, 0.0, 3.0, 4.0, 6.0]], $tile['invocation_matrices']);
        $t->same([[4.0, 6.0, 10.0, 9.0]], $tile['invocation_bboxes']);
        $t->same([[3.0, 4.0, 20.0, 10.0]], $tile['invocation_clip_bboxes']);
        $t->same([[4.0, 6.0, 10.0, 9.0]], $tile['invocation_visible_bboxes']);
        $t->same([4.0, 6.0, 10.0, 9.0], $tile['image_unit_bbox']);
        $t->same([4.0, 6.0, 10.0, 9.0], $tile['image_visible_bbox']);
        $t->same(true, $tile['decoded_with_current_filters']);
        $t->same(strlen($tilePayload), $tile['decoded_length']);
        $t->same(hash('sha256', $tilePayload), $tile['decoded_sha256']);
        $t->same(false, $tile['payload_in_visible_text']);

        $unused = $entriesByName['Unused Wrapped Image'];
        $t->same('Wrapped Tile', $unused['pattern_resource_name'] ?? null);
        $t->same(false, $unused['invoked']);
        $t->same(0, $unused['invocation_count']);
        $t->same([], $unused['invocation_matrices']);
        $t->same(hash('sha256', $unusedPayload), $unused['decoded_sha256']);

        $t->same(['Before wrapped pattern image', 'After wrapped pattern image'], $extractor->extractTextLines($pdf));
        $t->same("Before wrapped pattern image\nAfter wrapped pattern image", $plainText);
        $t->true(!str_contains($plainText, 'Wrapped Pattern Tile Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Unused Wrapped Pattern Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $tilePayload));
        $t->true(!str_contains($encoded, $unusedPayload));
        $t->true(str_contains($encoded, hash('sha256', $tilePayload)));
        $t->true(str_contains($encoded, hash('sha256', $unusedPayload)));
    },
];
