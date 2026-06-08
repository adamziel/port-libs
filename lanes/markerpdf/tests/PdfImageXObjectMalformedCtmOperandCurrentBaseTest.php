<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'records malformed Image XObject CTM operands without promoting image payload text' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before malformed CTM image) Tj ET\n"
            . "q 18 0 0 /Bad#20Scale 9 72 cm /Malformed#20Ctm#20Image Do Q\n"
            . "q 12 0 0 6 110 690 cm /Valid#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After malformed CTM image) Tj ET';
        $malformedPayload = 'BT /F1 12 Tf 72 720 Td (Malformed CTM Image Payload Noise) Tj ET';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid CTM Image Payload Noise) Tj ET';
        $malformedCompressed = gzcompress($malformedPayload);
        $validCompressed = gzcompress($validPayload);
        if (!is_string($malformedCompressed) || !is_string($validCompressed)) {
            throw new RuntimeException('Unable to compress malformed CTM Image XObject fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Malformed#20Ctm#20Image 5 0 R /Valid#20Image 6 0 R >> >> >>\nendobj\n"
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
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $malformed = $entriesByName['Malformed Ctm Image'];
        $t->same(true, $malformed['invoked']);
        $t->same(1, $malformed['invocation_count']);
        $t->same([[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]], $malformed['invocation_matrices']);
        $t->same([0.0, 0.0, 1.0, 1.0], $malformed['image_unit_bbox']);
        $t->same(1, $malformed['malformed_ctm_operand_count']);
        $t->same(true, $malformed['malformed_ctm_operand_review_only']);
        $t->same('preserve_prior_ctm_and_review_image_placement', $malformed['malformed_ctm_operand_policy']);
        $t->same('malformed_ctm_operands', $malformed['malformed_ctm_operands'][0]['reason']);
        $t->same('cm', $malformed['malformed_ctm_operands'][0]['operator']);
        $t->same(6, $malformed['malformed_ctm_operands'][0]['expected_operand_count']);
        $t->same(6, $malformed['malformed_ctm_operands'][0]['operand_count']);
        $t->same(['number', 'number', 'number', 'name', 'number', 'number'], $malformed['malformed_ctm_operands'][0]['operand_types']);
        $t->same(['18', '0', '0', '/Bad#20Scale', '9', '72'], $malformed['malformed_ctm_operands'][0]['operand_previews']);
        $t->same([[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]], $malformed['malformed_ctm_operands'][0]['matrices_before_operator']);
        $t->same([[0.0, 0.0, 1.0, 1.0]], $malformed['malformed_ctm_operands'][0]['bboxes_before_operator']);
        $t->same(true, $malformed['malformed_ctm_operands'][0]['matrix_unchanged']);
        $t->same(false, $malformed['payload_in_visible_text']);
        $t->same(hash('sha256', $malformedPayload), $malformed['decoded_sha256']);

        $valid = $entriesByName['Valid Image'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[12.0, 0.0, 0.0, 6.0, 110.0, 690.0]], $valid['invocation_matrices']);
        $t->same([110.0, 690.0, 122.0, 696.0], $valid['image_unit_bbox']);
        $t->same(0, $valid['malformed_ctm_operand_count']);
        $t->same([], $valid['malformed_ctm_operands']);
        $t->same(null, $valid['malformed_ctm_operand_policy']);
        $t->same(false, $valid['malformed_ctm_operand_review_only']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);

        $t->same(['Before malformed CTM image', 'After malformed CTM image'], $extractor->extractTextLines($pdf));
        $t->same("Before malformed CTM image\nAfter malformed CTM image", $plainText);
        $t->true(!str_contains($plainText, 'Malformed CTM Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid CTM Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $malformedPayload));
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(str_contains($encoded, hash('sha256', $malformedPayload)));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
    },
];
