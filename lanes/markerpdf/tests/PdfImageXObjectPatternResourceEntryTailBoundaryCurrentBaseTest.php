<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects malformed tiling Pattern resource entry tails before image XObject traversal' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before pattern resource tail image) Tj ET\n"
            . "/Pattern cs /Bad#20Tail#20Tile scn 0 0 18 9 re f\n"
            . "/Pattern cs /Good#20Tile scn 24 0 18 9 re f\n"
            . "/Pattern cs /Comment#20Tail#20Tile scn 48 0 18 9 re f\n"
            . 'BT /F1 12 Tf 72 660 Td (After pattern resource tail image) Tj ET';
        $badPatternContent = 'q 5 0 0 2 1 1 cm /Bad#20Pattern#20Image Do Q';
        $goodPatternContent = 'q 6 0 0 3 2 2 cm /Good#20Pattern#20Image Do Q';
        $commentPatternContent = 'q 4 0 0 2 3 3 cm /Comment#20Pattern#20Image Do Q';
        $badPayload = 'BT /F1 12 Tf 72 720 Td (Bad Pattern Resource Tail Image Payload Noise) Tj ET';
        $goodPayload = 'BT /F1 12 Tf 72 720 Td (Good Pattern Resource Tail Image Payload Noise) Tj ET';
        $commentPayload = 'BT /F1 12 Tf 72 720 Td (Comment Pattern Resource Tail Image Payload Noise) Tj ET';
        $badCompressed = gzcompress($badPayload);
        $goodCompressed = gzcompress($goodPayload);
        $commentCompressed = gzcompress($commentPayload);
        if (!is_string($badCompressed) || !is_string($goodCompressed) || !is_string($commentCompressed)) {
            throw new RuntimeException('Unable to compress Pattern resource-tail image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /Pattern << /Bad#20Tail#20Tile 11 0 R 99 0 R /Good#20Tile 12 0 R /Comment#20Tail#20Tile 13 0 R % comment-only tail\n >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 5 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 6 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($goodCompressed) . " >>\nstream\n{$goodCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($commentCompressed) . " >>\nstream\n{$commentCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "11 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 18 9] /XStep 18 /YStep 9 /Resources << /XObject << /Bad#20Pattern#20Image 5 0 R >> >> /Length " . strlen($badPatternContent) . " >>\nstream\n{$badPatternContent}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 18 9] /XStep 18 /YStep 9 /Resources << /XObject << /Good#20Pattern#20Image 6 0 R >> >> /Length " . strlen($goodPatternContent) . " >>\nstream\n{$goodPatternContent}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 18 9] /XStep 18 /YStep 9 /Resources << /XObject << /Comment#20Pattern#20Image 7 0 R >> >> /Length " . strlen($commentPatternContent) . " >>\nstream\n{$commentPatternContent}\nendstream\nendobj\n"
            . "99 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 1 1] /XStep 1 /YStep 1 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

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
        $t->same(false, isset($entriesByName['Bad Pattern Image']));

        $good = $entriesByName['Good Pattern Image'];
        $t->same('Good Tile', $good['pattern_resource_name'] ?? null);
        $t->same(12, $good['parent_pattern_object'] ?? null);
        $t->same(1, $good['pattern_paint_count'] ?? null);
        $t->same(['Good Tile', 'Good Pattern Image'], $good['resource_path']);
        $t->same(true, $good['invoked']);
        $t->same(1, $good['invocation_count']);
        $t->true(($good['pattern_bboxes'] ?? []) !== []);
        $t->true(($good['invocation_matrices'] ?? []) !== []);
        $t->true(($good['invocation_bboxes'] ?? []) !== []);
        $t->same(true, $good['decoded_with_current_filters']);
        $t->same(strlen($goodPayload), $good['decoded_length']);
        $t->same(hash('sha256', $goodPayload), $good['decoded_sha256']);
        $t->same(false, $good['payload_in_visible_text']);

        $comment = $entriesByName['Comment Pattern Image'];
        $t->same('Comment Tail Tile', $comment['pattern_resource_name'] ?? null);
        $t->same(13, $comment['parent_pattern_object'] ?? null);
        $t->same(1, $comment['pattern_paint_count'] ?? null);
        $t->same(['Comment Tail Tile', 'Comment Pattern Image'], $comment['resource_path']);
        $t->same(true, $comment['invoked']);
        $t->same(1, $comment['invocation_count']);
        $t->true(($comment['pattern_bboxes'] ?? []) !== []);
        $t->true(($comment['invocation_matrices'] ?? []) !== []);
        $t->true(($comment['invocation_bboxes'] ?? []) !== []);
        $t->same(hash('sha256', $commentPayload), $comment['decoded_sha256']);
        $t->same(false, $comment['payload_in_visible_text']);

        $t->same(['Before pattern resource tail image', 'After pattern resource tail image'], $extractor->extractTextLines($pdf));
        $t->same("Before pattern resource tail image\nAfter pattern resource tail image", $plainText);
        $t->true(!str_contains($plainText, 'Bad Pattern Resource Tail Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Good Pattern Resource Tail Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Comment Pattern Resource Tail Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Bad Pattern Image'));
        $t->true(!str_contains($encoded, hash('sha256', $badPayload)));
        $t->true(!str_contains($encoded, $badPayload));
        $t->true(!str_contains($encoded, $goodPayload));
        $t->true(!str_contains($encoded, $commentPayload));
        $t->true(str_contains($encoded, hash('sha256', $goodPayload)));
        $t->true(str_contains($encoded, hash('sha256', $commentPayload)));
    },
];
