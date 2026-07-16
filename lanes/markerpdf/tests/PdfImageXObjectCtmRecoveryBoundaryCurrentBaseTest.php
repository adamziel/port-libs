<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'clears stale Image XObject malformed CTM review after a valid replacement CTM' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before recovered CTM image) Tj ET\n"
            . "q 18 0 0 /Bad#20Scale 9 72 cm 20 0 0 10 72 690 cm /Recovered#20Ctm#20Image Do Q\n"
            . "q 12 0 0 6 110 690 cm /Valid#20Sibling Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After recovered CTM image) Tj ET';
        $recoveredPayload = 'BT /F1 12 Tf 72 720 Td (Recovered CTM Image Payload Noise) Tj ET';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Sibling CTM Image Payload Noise) Tj ET';
        $recoveredCompressed = gzcompress($recoveredPayload);
        $validCompressed = gzcompress($validPayload);
        if (!is_string($recoveredCompressed) || !is_string($validCompressed)) {
            throw new RuntimeException('Unable to compress recovered CTM Image XObject fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Recovered#20Ctm#20Image 5 0 R /Valid#20Sibling 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($recoveredCompressed) . " >>\nstream\n{$recoveredCompressed}\nendstream\nendobj\n"
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

        $recovered = $entriesByName['Recovered Ctm Image'];
        $t->same(true, $recovered['invoked']);
        $t->same(1, $recovered['invocation_count']);
        $t->same([[20.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $recovered['invocation_matrices']);
        $t->same([72.0, 690.0, 92.0, 700.0], $recovered['image_unit_bbox']);
        $t->same(0, $recovered['malformed_ctm_operand_count']);
        $t->same([], $recovered['malformed_ctm_operands']);
        $t->same(null, $recovered['malformed_ctm_operand_policy']);
        $t->same(false, $recovered['malformed_ctm_operand_review_only']);
        $t->same(false, $recovered['payload_in_visible_text']);
        $t->same(hash('sha256', $recoveredPayload), $recovered['decoded_sha256']);

        $valid = $entriesByName['Valid Sibling'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[12.0, 0.0, 0.0, 6.0, 110.0, 690.0]], $valid['invocation_matrices']);
        $t->same([110.0, 690.0, 122.0, 696.0], $valid['image_unit_bbox']);
        $t->same(0, $valid['malformed_ctm_operand_count']);
        $t->same([], $valid['malformed_ctm_operands']);
        $t->same(null, $valid['malformed_ctm_operand_policy']);
        $t->same(false, $valid['malformed_ctm_operand_review_only']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);

        $t->same(['Before recovered CTM image', 'After recovered CTM image'], $extractor->extractTextLines($pdf));
        $t->same("Before recovered CTM image\nAfter recovered CTM image", $plainText);
        $t->true(!str_contains($plainText, 'Recovered CTM Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Sibling CTM Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $recoveredPayload));
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(str_contains($encoded, hash('sha256', $recoveredPayload)));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
    },
];
