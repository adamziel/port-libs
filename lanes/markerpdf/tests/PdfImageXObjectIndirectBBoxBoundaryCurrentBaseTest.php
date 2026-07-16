<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'resolves indirect Form XObject BBox numeric operands before nested image clipping' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect form bbox image) Tj ET\n"
            . "q 40 0 0 20 100 200 cm /Indirect#20BBox#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After indirect form bbox image) Tj ET';
        $formContent = 'q 4 0 0 3 0.5 0.25 cm /Nested#20BBox#20Image Do Q';
        $nestedPayload = 'BT /F1 12 Tf 72 720 Td (Indirect Form BBox Image Payload Noise) Tj ET';
        $nestedCompressed = gzcompress($nestedPayload);
        if (!is_string($nestedCompressed)) {
            throw new RuntimeException('Unable to compress indirect Form BBox image fixture payload.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Indirect#20BBox#20Form 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [31 0 R 32 0 R 33 0 R 34 0 R] /Resources << /XObject << /Nested#20BBox#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($nestedCompressed) . " >>\nstream\n{$nestedCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "31 0 obj\n0\nendobj\n"
            . "32 0 obj\n0\nendobj\n"
            . "33 0 obj\n1\nendobj\n"
            . "34 0 obj\n1\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0] ?? null;

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->true(is_array($entry));
        $t->same('Nested BBox Image', $entry['resource_name']);
        $t->same(['Indirect BBox Form', 'Nested BBox Image'], $entry['resource_path']);
        $t->same(5, $entry['parent_form_xobject_object']);
        $t->same([[160.0, 0.0, 0.0, 60.0, 120.0, 205.0]], $entry['invocation_matrices']);
        $t->same([[120.0, 205.0, 280.0, 265.0]], $entry['invocation_bboxes']);
        $t->same([[100.0, 200.0, 140.0, 220.0]], $entry['invocation_clip_bboxes']);
        $t->same([[120.0, 205.0, 140.0, 220.0]], $entry['invocation_visible_bboxes']);
        $t->same([120.0, 205.0, 280.0, 265.0], $entry['image_unit_bbox']);
        $t->same([120.0, 205.0, 140.0, 220.0], $entry['image_visible_bbox']);
        $t->same(true, $entry['clip_applied']);
        $t->same(true, $entry['clip_reduces_painted_bbox']);
        $t->same(false, $entry['clip_excludes_image']);
        $t->same(1, $entry['painted_invocation_count']);
        $t->same(hash('sha256', $nestedPayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);

        $t->same(['Before indirect form bbox image', 'After indirect form bbox image'], $extractor->extractTextLines($pdf));
        $t->same("Before indirect form bbox image\nAfter indirect form bbox image", $plainText);
        $t->true(!str_contains($plainText, 'Indirect Form BBox Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $nestedPayload));
        $t->true(str_contains($encoded, hash('sha256', $nestedPayload)));
    },
    'resolves indirect tiling Pattern BBox numeric operands before image XObject review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect pattern bbox image) Tj ET\n"
            . "/Pattern cs /Indirect#20Tile scn 0 0 20 10 re f\n"
            . 'BT /F1 12 Tf 72 660 Td (After indirect pattern bbox image) Tj ET';
        $patternContent = 'q 5 0 0 2 1 1 cm /Pattern#20BBox#20Image Do Q';
        $patternPayload = 'BT /F1 12 Tf 72 720 Td (Indirect Pattern BBox Image Payload Noise) Tj ET';
        $patternCompressed = gzcompress($patternPayload);
        if (!is_string($patternCompressed)) {
            throw new RuntimeException('Unable to compress indirect Pattern BBox image fixture payload.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Indirect#20Tile 11 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($patternCompressed) . " >>\nstream\n{$patternCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [41 0 R 42 0 R 43 0 R 44 0 R] /XStep 20 /YStep 10 /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Pattern#20BBox#20Image 5 0 R >> >> /Length " . strlen($patternContent) . " >>\nstream\n{$patternContent}\nendstream\nendobj\n"
            . "41 0 obj\n0\nendobj\n"
            . "42 0 obj\n0\nendobj\n"
            . "43 0 obj\n20\nendobj\n"
            . "44 0 obj\n10\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0] ?? null;

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->true(is_array($entry));
        $t->same('Pattern BBox Image', $entry['resource_name']);
        $t->same('Indirect Tile', $entry['pattern_resource_name'] ?? null);
        $t->same(11, $entry['parent_pattern_object'] ?? null);
        $t->same(1, $entry['pattern_paint_count'] ?? null);
        $t->same([[1.0, 0.0, 0.0, 1.0, 3.0, 4.0]], $entry['pattern_matrices'] ?? null);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $entry['pattern_bboxes'] ?? null);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $entry['pattern_visible_bboxes'] ?? null);
        $t->same([[5.0, 0.0, 0.0, 2.0, 4.0, 5.0]], $entry['invocation_matrices']);
        $t->same([[4.0, 5.0, 9.0, 7.0]], $entry['invocation_bboxes']);
        $t->same([[3.0, 4.0, 20.0, 10.0]], $entry['invocation_clip_bboxes']);
        $t->same([[4.0, 5.0, 9.0, 7.0]], $entry['invocation_visible_bboxes']);
        $t->same([4.0, 5.0, 9.0, 7.0], $entry['image_visible_bbox']);
        $t->same(true, $entry['pattern_review_only'] ?? null);
        $t->same(true, $entry['clip_applied']);
        $t->same(false, $entry['clip_excludes_image']);
        $t->same(1, $entry['painted_invocation_count']);
        $t->same(hash('sha256', $patternPayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);

        $t->same(['Before indirect pattern bbox image', 'After indirect pattern bbox image'], $extractor->extractTextLines($pdf));
        $t->same("Before indirect pattern bbox image\nAfter indirect pattern bbox image", $plainText);
        $t->true(!str_contains($plainText, 'Indirect Pattern BBox Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $patternPayload));
        $t->true(str_contains($encoded, hash('sha256', $patternPayload)));
    },
];
