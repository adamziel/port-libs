<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects extra-operand cm transforms before Image XObject placement' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before Image XObject cm boundary) Tj ET\n"
            . "q 777 20 0 0 10 72 690 cm /Malformed#20Cm#20Image Do Q\n"
            . "q 14 0 0 7 110 690 cm /Valid#20Cm#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After Image XObject cm boundary) Tj ET';
        $malformedPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Cm Image Payload Noise) Tj ET';
        $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Cm Image Payload Noise) Tj ET';
        $malformedCompressed = gzcompress($malformedPayload);
        $validCompressed = gzcompress($validPayload);
        if (!is_string($malformedCompressed) || !is_string($validCompressed)) {
            throw new RuntimeException('Unable to compress Image XObject cm operand fixture.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Malformed#20Cm#20Image 5 0 R /Valid#20Cm#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedCompressed) . " >>\nstream\n{$malformedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
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

        $malformed = $entriesByName['Malformed Cm Image'];
        $t->same(true, $malformed['invoked']);
        $t->same(1, $malformed['invocation_count']);
        $t->same([[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]], $malformed['invocation_matrices']);
        $t->same([0.0, 0.0, 1.0, 1.0], $malformed['image_unit_bbox']);
        $t->same(hash('sha256', $malformedPayload), $malformed['decoded_sha256']);
        $t->same(false, $malformed['payload_in_visible_text']);

        $valid = $entriesByName['Valid Cm Image'];
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[14.0, 0.0, 0.0, 7.0, 110.0, 690.0]], $valid['invocation_matrices']);
        $t->same([110.0, 690.0, 124.0, 697.0], $valid['image_unit_bbox']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before Image XObject cm boundary', 'After Image XObject cm boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before Image XObject cm boundary\nAfter Image XObject cm boundary", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Cm Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Cm Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $malformedPayload));
        $t->true(!str_contains($encoded, $validPayload));
    },
];
