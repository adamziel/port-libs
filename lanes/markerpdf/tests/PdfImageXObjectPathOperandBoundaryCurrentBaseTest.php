<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects extra-operand image clipping paths before XObject placement review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before image path operand boundary) Tj ET\n"
            . "q 20 0 0 10 72 690 cm 99 0 0 .4 .8 re W n /Malformed#20Clip#20Image Do Q\n"
            . "q 20 0 0 10 104 690 cm 0 0 .4 .8 re W n /Valid#20Clip#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After image path operand boundary) Tj ET';
        $malformedPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Clip Image Payload Noise) Tj ET';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Clip Image Payload Noise) Tj ET';
        $malformedCompressed = gzcompress($malformedPayload);
        $validCompressed = gzcompress($validPayload);
        if (!is_string($malformedCompressed) || !is_string($validCompressed)) {
            throw new RuntimeException('Unable to compress Image XObject path operand fixture.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Malformed#20Clip#20Image 5 0 R /Valid#20Clip#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedCompressed) . " >>\nstream\n{$malformedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

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

        $malformed = $entriesByName['Malformed Clip Image'];
        $t->same(true, $malformed['invoked']);
        $t->same(1, $malformed['invocation_count']);
        $t->same([[20.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $malformed['invocation_matrices']);
        $t->same([72.0, 690.0, 92.0, 700.0], $malformed['image_unit_bbox']);
        $t->same([], $malformed['invocation_clip_bboxes']);
        $t->same([[72.0, 690.0, 92.0, 700.0]], $malformed['invocation_visible_bboxes']);
        $t->same([72.0, 690.0, 92.0, 700.0], $malformed['image_visible_bbox']);
        $t->same(false, $malformed['clip_applied']);
        $t->same(false, $malformed['clip_reduces_painted_bbox']);
        $t->same(false, $malformed['clip_excludes_image']);
        $t->same(true, $malformed['decoded_with_current_filters']);
        $t->same(strlen($malformedPayload), $malformed['decoded_length']);
        $t->same(hash('sha256', $malformedPayload), $malformed['decoded_sha256']);
        $t->same(false, $malformed['payload_in_visible_text']);

        $valid = $entriesByName['Valid Clip Image'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[20.0, 0.0, 0.0, 10.0, 104.0, 690.0]], $valid['invocation_matrices']);
        $t->same([104.0, 690.0, 124.0, 700.0], $valid['image_unit_bbox']);
        $t->same([[104.0, 690.0, 112.0, 698.0]], $valid['invocation_clip_bboxes']);
        $t->same([[104.0, 690.0, 112.0, 698.0]], $valid['invocation_visible_bboxes']);
        $t->same([104.0, 690.0, 112.0, 698.0], $valid['image_visible_bbox']);
        $t->same(true, $valid['clip_applied']);
        $t->same(true, $valid['clip_reduces_painted_bbox']);
        $t->same(false, $valid['clip_excludes_image']);
        $t->same(true, $valid['decoded_with_current_filters']);
        $t->same(strlen($validPayload), $valid['decoded_length']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before image path operand boundary', 'After image path operand boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before image path operand boundary\nAfter image path operand boundary", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Clip Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Clip Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $malformedPayload));
        $t->true(!str_contains($encoded, $validPayload));
    },
];
