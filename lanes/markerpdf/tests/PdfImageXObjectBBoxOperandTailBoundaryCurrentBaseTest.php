<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'ignores malformed Form XObject BBox arrays with trailing operands before image clipping' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before malformed form bbox image) Tj ET\n"
            . "q 40 0 0 20 100 200 cm /Bad#20BBox#20Form Do Q\n"
            . "q 40 0 0 20 200 200 cm /Good#20BBox#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After malformed form bbox image) Tj ET';
        $badFormContent = 'q 4 0 0 3 0.5 0.25 cm /Bad#20Nested#20Image Do Q';
        $goodFormContent = 'q 4 0 0 3 0.5 0.25 cm /Good#20Nested#20Image Do Q';
        $badPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Form BBox Image Payload Noise) Tj ET';
        $goodPayload = 'BT /F1 12 Tf 72 720 Td (Valid Form BBox Image Payload Noise) Tj ET';
        $badCompressed = gzcompress($badPayload);
        $goodCompressed = gzcompress($goodPayload);
        if (!is_string($badCompressed) || !is_string($goodCompressed)) {
            throw new RuntimeException('Unable to compress Form BBox operand-tail image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Bad#20BBox#20Form 5 0 R /Good#20BBox#20Form 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] 99 /Resources << /XObject << /Bad#20Nested#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($badFormContent) . " >>\nstream\n{$badFormContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Resources << /XObject << /Good#20Nested#20Image 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($goodFormContent) . " >>\nstream\n{$goodFormContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($goodCompressed) . " >>\nstream\n{$goodCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $bad = $entriesByName['Bad Nested Image'];
        $t->same(['Bad BBox Form', 'Bad Nested Image'], $bad['resource_path']);
        $t->same(5, $bad['parent_form_xobject_object']);
        $t->same([[160.0, 0.0, 0.0, 60.0, 120.0, 205.0]], $bad['invocation_matrices']);
        $t->same([[120.0, 205.0, 280.0, 265.0]], $bad['invocation_bboxes']);
        $t->same([], $bad['invocation_clip_bboxes']);
        $t->same([[120.0, 205.0, 280.0, 265.0]], $bad['invocation_visible_bboxes']);
        $t->same([120.0, 205.0, 280.0, 265.0], $bad['image_visible_bbox']);
        $t->same(false, $bad['clip_applied']);
        $t->same(false, $bad['clip_reduces_painted_bbox']);
        $t->same(false, $bad['clip_excludes_image']);
        $t->same(1, $bad['painted_invocation_count']);
        $t->same(hash('sha256', $badPayload), $bad['decoded_sha256']);
        $t->same(false, $bad['payload_in_visible_text']);

        $good = $entriesByName['Good Nested Image'];
        $t->same(['Good BBox Form', 'Good Nested Image'], $good['resource_path']);
        $t->same(7, $good['parent_form_xobject_object']);
        $t->same([[160.0, 0.0, 0.0, 60.0, 220.0, 205.0]], $good['invocation_matrices']);
        $t->same([[220.0, 205.0, 380.0, 265.0]], $good['invocation_bboxes']);
        $t->same([[200.0, 200.0, 240.0, 220.0]], $good['invocation_clip_bboxes']);
        $t->same([[220.0, 205.0, 240.0, 220.0]], $good['invocation_visible_bboxes']);
        $t->same([220.0, 205.0, 240.0, 220.0], $good['image_visible_bbox']);
        $t->same(true, $good['clip_applied']);
        $t->same(true, $good['clip_reduces_painted_bbox']);
        $t->same(false, $good['clip_excludes_image']);
        $t->same(1, $good['painted_invocation_count']);
        $t->same(hash('sha256', $goodPayload), $good['decoded_sha256']);
        $t->same(false, $good['payload_in_visible_text']);

        $t->same(['Before malformed form bbox image', 'After malformed form bbox image'], $extractor->extractTextLines($pdf));
        $t->same("Before malformed form bbox image\nAfter malformed form bbox image", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Form BBox Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Form BBox Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $badPayload));
        $t->true(!str_contains($encoded, $goodPayload));
        $t->true(str_contains($encoded, hash('sha256', $badPayload)));
        $t->true(str_contains($encoded, hash('sha256', $goodPayload)));
    },
    'ignores malformed tiling Pattern BBox arrays with trailing operands before image clipping' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before malformed pattern bbox image) Tj ET\n"
            . "/Pattern cs /Bad#20BBox#20Tile scn 0 0 20 10 re f\n"
            . "/Pattern cs /Good#20BBox#20Tile scn 0 0 20 10 re f\n"
            . 'BT /F1 12 Tf 72 660 Td (After malformed pattern bbox image) Tj ET';
        $badPatternContent = 'q 5 0 0 2 4 4 cm /Bad#20Pattern#20Image Do Q';
        $goodPatternContent = 'q 5 0 0 2 4 4 cm /Good#20Pattern#20Image Do Q';
        $badPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Pattern BBox Image Payload Noise) Tj ET';
        $goodPayload = 'BT /F1 12 Tf 72 720 Td (Valid Pattern BBox Image Payload Noise) Tj ET';
        $badCompressed = gzcompress($badPayload);
        $goodCompressed = gzcompress($goodPayload);
        if (!is_string($badCompressed) || !is_string($goodCompressed)) {
            throw new RuntimeException('Unable to compress Pattern BBox operand-tail image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Bad#20BBox#20Tile 11 0 R /Good#20BBox#20Tile 12 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($goodCompressed) . " >>\nstream\n{$goodCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 2 2] 99 /XStep 20 /YStep 10 /Resources << /XObject << /Bad#20Pattern#20Image 5 0 R >> >> /Length " . strlen($badPatternContent) . " >>\nstream\n{$badPatternContent}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 2 2] /XStep 20 /YStep 10 /Resources << /XObject << /Good#20Pattern#20Image 6 0 R >> >> /Length " . strlen($goodPatternContent) . " >>\nstream\n{$goodPatternContent}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $bad = $entriesByName['Bad Pattern Image'];
        $t->same('Bad BBox Tile', $bad['pattern_resource_name'] ?? null);
        $t->same(11, $bad['parent_pattern_object'] ?? null);
        $t->same(1, $bad['pattern_paint_count'] ?? null);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $bad['pattern_bboxes'] ?? null);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $bad['pattern_visible_bboxes'] ?? null);
        $t->same(['Bad BBox Tile', 'Bad Pattern Image'], $bad['resource_path']);
        $t->same([[5.0, 0.0, 0.0, 2.0, 4.0, 4.0]], $bad['invocation_matrices']);
        $t->same([[4.0, 4.0, 9.0, 6.0]], $bad['invocation_bboxes']);
        $t->same([[0.0, 0.0, 20.0, 10.0]], $bad['invocation_clip_bboxes']);
        $t->same([[4.0, 4.0, 9.0, 6.0]], $bad['invocation_visible_bboxes']);
        $t->same([4.0, 4.0, 9.0, 6.0], $bad['image_visible_bbox']);
        $t->same(true, $bad['clip_applied']);
        $t->same(false, $bad['clip_reduces_painted_bbox']);
        $t->same(false, $bad['clip_excludes_image']);
        $t->same(1, $bad['painted_invocation_count']);
        $t->same(0, $bad['clip_excluded_invocation_count']);
        $t->same(hash('sha256', $badPayload), $bad['decoded_sha256']);
        $t->same(false, $bad['payload_in_visible_text']);

        $good = $entriesByName['Good Pattern Image'];
        $t->same('Good BBox Tile', $good['pattern_resource_name'] ?? null);
        $t->same(12, $good['parent_pattern_object'] ?? null);
        $t->same(['Good BBox Tile', 'Good Pattern Image'], $good['resource_path']);
        $t->same([[5.0, 0.0, 0.0, 2.0, 4.0, 4.0]], $good['invocation_matrices']);
        $t->same([[4.0, 4.0, 9.0, 6.0]], $good['invocation_bboxes']);
        $t->same([[0.0, 0.0, 2.0, 2.0]], $good['invocation_clip_bboxes']);
        $t->same([], $good['invocation_visible_bboxes']);
        $t->same(null, $good['image_visible_bbox']);
        $t->same(true, $good['clip_applied']);
        $t->same(true, $good['clip_reduces_painted_bbox']);
        $t->same(true, $good['clip_excludes_image']);
        $t->same(0, $good['painted_invocation_count']);
        $t->same(1, $good['clip_excluded_invocation_count']);
        $t->same(hash('sha256', $goodPayload), $good['decoded_sha256']);
        $t->same(false, $good['payload_in_visible_text']);

        $t->same(['Before malformed pattern bbox image', 'After malformed pattern bbox image'], $extractor->extractTextLines($pdf));
        $t->same("Before malformed pattern bbox image\nAfter malformed pattern bbox image", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Pattern BBox Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Pattern BBox Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $badPayload));
        $t->true(!str_contains($encoded, $goodPayload));
        $t->true(str_contains($encoded, hash('sha256', $badPayload)));
        $t->true(str_contains($encoded, hash('sha256', $goodPayload)));
    },
];
