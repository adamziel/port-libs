<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects extra-operand rectangular path clips before Image XObject placement' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before image rect path operand boundary) Tj ET\n"
            . "q 999 10 10 30 20 re W n 50 0 0 40 0 0 cm /Malformed#20Rect#20Clip Do Q\n"
            . "q 70 10 30 20 re W n 50 0 0 40 60 0 cm /Valid#20Rect#20Clip Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After image rect path operand boundary) Tj ET';
        $malformedPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Rect Clip Image Payload Noise) Tj ET';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Rect Clip Image Payload Noise) Tj ET';
        $malformedCompressed = gzcompress($malformedPayload);
        $validCompressed = gzcompress($validPayload);
        if (!is_string($malformedCompressed) || !is_string($validCompressed)) {
            throw new RuntimeException('Unable to compress Image XObject rectangular path operand fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Malformed#20Rect#20Clip 5 0 R /Valid#20Rect#20Clip 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedCompressed) . " >>\nstream\n{$malformedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
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
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $malformed = $entriesByName['Malformed Rect Clip'];
        $t->same(true, $malformed['invoked']);
        $t->same(1, $malformed['invocation_count']);
        $t->same([[50.0, 0.0, 0.0, 40.0, 0.0, 0.0]], $malformed['invocation_matrices']);
        $t->same([[0.0, 0.0, 50.0, 40.0]], $malformed['invocation_bboxes']);
        $t->same([], $malformed['invocation_clip_bboxes']);
        $t->same([[0.0, 0.0, 50.0, 40.0]], $malformed['invocation_visible_bboxes']);
        $t->same([0.0, 0.0, 50.0, 40.0], $malformed['image_visible_bbox']);
        $t->same(false, $malformed['clip_applied']);
        $t->same(false, $malformed['clip_reduces_painted_bbox']);
        $t->same(false, $malformed['clip_excludes_image']);
        $t->same(1, $malformed['painted_invocation_count']);
        $t->same(0, $malformed['clip_excluded_invocation_count']);
        $t->same(hash('sha256', $malformedPayload), $malformed['decoded_sha256']);
        $t->same(false, $malformed['payload_in_visible_text']);

        $valid = $entriesByName['Valid Rect Clip'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[50.0, 0.0, 0.0, 40.0, 60.0, 0.0]], $valid['invocation_matrices']);
        $t->same([[60.0, 0.0, 110.0, 40.0]], $valid['invocation_bboxes']);
        $t->same([[70.0, 10.0, 100.0, 30.0]], $valid['invocation_clip_bboxes']);
        $t->same([[70.0, 10.0, 100.0, 30.0]], $valid['invocation_visible_bboxes']);
        $t->same([70.0, 10.0, 100.0, 30.0], $valid['image_visible_bbox']);
        $t->same(true, $valid['clip_applied']);
        $t->same(true, $valid['clip_reduces_painted_bbox']);
        $t->same(false, $valid['clip_excludes_image']);
        $t->same(1, $valid['painted_invocation_count']);
        $t->same(0, $valid['clip_excluded_invocation_count']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before image rect path operand boundary', 'After image rect path operand boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before image rect path operand boundary\nAfter image rect path operand boundary", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Rect Clip Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Rect Clip Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $malformedPayload));
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(str_contains($encoded, hash('sha256', $malformedPayload)));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
    },
];
