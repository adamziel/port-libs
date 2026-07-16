<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects malformed direct XObject resource entry tails before image placement review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before direct resource tail) Tj ET\n"
            . "q 12 0 0 6 72 690 cm /Bad#20Tail#20Image Do Q\n"
            . "q 10 0 0 5 100 690 cm /Good#20Image Do Q\n"
            . "q 8 0 0 4 126 690 cm /Comment#20Tail#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After direct resource tail) Tj ET';
        $badPayload = 'BT /F1 12 Tf 72 720 Td (Bad Direct Resource Tail Image Payload Noise) Tj ET';
        $goodPayload = 'BT /F1 12 Tf 72 720 Td (Good Direct Resource Tail Image Payload Noise) Tj ET';
        $commentPayload = 'BT /F1 12 Tf 72 720 Td (Comment Direct Resource Tail Image Payload Noise) Tj ET';
        $badCompressed = gzcompress($badPayload);
        $goodCompressed = gzcompress($goodPayload);
        $commentCompressed = gzcompress($commentPayload);
        if (!is_string($badCompressed) || !is_string($goodCompressed) || !is_string($commentCompressed)) {
            throw new RuntimeException('Unable to compress direct XObject resource-tail fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Bad#20Tail#20Image 5 0 R 99 0 R /Good#20Image 6 0 R /Comment#20Tail#20Image 7 0 R % comment-only tail\n >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($goodCompressed) . " >>\nstream\n{$goodCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($commentCompressed) . " >>\nstream\n{$commentCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "99 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

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
        $t->same(false, isset($entriesByName['Bad Tail Image']));

        $good = $entriesByName['Good Image'];
        $t->same(6, $good['object_number']);
        $t->same(true, $good['invoked']);
        $t->same(1, $good['invocation_count']);
        $t->same([[10.0, 0.0, 0.0, 5.0, 100.0, 690.0]], $good['invocation_matrices']);
        $t->same([100.0, 690.0, 110.0, 695.0], $good['image_unit_bbox']);
        $t->same(true, $good['decoded_with_current_filters']);
        $t->same(strlen($goodPayload), $good['decoded_length']);
        $t->same(hash('sha256', $goodPayload), $good['decoded_sha256']);
        $t->same(false, $good['payload_in_visible_text']);

        $comment = $entriesByName['Comment Tail Image'];
        $t->same(7, $comment['object_number']);
        $t->same(true, $comment['invoked']);
        $t->same(1, $comment['invocation_count']);
        $t->same([[8.0, 0.0, 0.0, 4.0, 126.0, 690.0]], $comment['invocation_matrices']);
        $t->same([126.0, 690.0, 134.0, 694.0], $comment['image_unit_bbox']);
        $t->same(true, $comment['decoded_with_current_filters']);
        $t->same(strlen($commentPayload), $comment['decoded_length']);
        $t->same(hash('sha256', $commentPayload), $comment['decoded_sha256']);
        $t->same(false, $comment['payload_in_visible_text']);

        $t->same(['Before direct resource tail', 'After direct resource tail'], $extractor->extractTextLines($pdf));
        $t->same("Before direct resource tail\nAfter direct resource tail", $plainText);
        $t->true(!str_contains($plainText, 'Bad Direct Resource Tail Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Good Direct Resource Tail Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Comment Direct Resource Tail Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Bad Tail Image'));
        $t->true(!str_contains($encoded, hash('sha256', $badPayload)));
        $t->true(!str_contains($encoded, $badPayload));
        $t->true(!str_contains($encoded, $goodPayload));
        $t->true(!str_contains($encoded, $commentPayload));
        $t->true(str_contains($encoded, hash('sha256', $goodPayload)));
        $t->true(str_contains($encoded, hash('sha256', $commentPayload)));
    },
];
