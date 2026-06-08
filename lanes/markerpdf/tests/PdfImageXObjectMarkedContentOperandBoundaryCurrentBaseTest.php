<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'rejects malformed marked-content operands before image XObject artifact and figure review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before marked-content operand images) Tj ET\n"
            . "/Artifact 99 << /Subtype /Background /MCID 3 >> BDC q 12 0 0 6 72 690 cm /Malformed#20Artifact Do Q EMC\n"
            . "/Artifact << /Subtype /Background /MCID 4 >> BDC q 10 0 0 5 96 690 cm /Valid#20Artifact Do Q EMC\n"
            . "/Figure 99 << /MCID 5 /Alt (Malformed Figure Alt Review) >> BDC q 14 0 0 7 120 690 cm /Malformed#20Figure Do Q EMC\n"
            . "777 /OC /LayerOff BDC q 8 0 0 4 150 690 cm /Malformed#20OC Do Q EMC\n"
            . 'BT /F1 12 Tf 72 660 Td (After marked-content operand images) Tj ET';
        $malformedArtifactPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Artifact Image Payload Noise) Tj ET';
        $validArtifactPayload = 'BT /F1 12 Tf 72 720 Td (Valid Artifact Image Payload Noise) Tj ET';
        $malformedFigurePayload = 'BT /F1 12 Tf 72 720 Td (Malformed Figure Image Payload Noise) Tj ET';
        $malformedOptionalContentPayload = 'BT /F1 12 Tf 72 720 Td (Malformed Optional Content Image Payload Noise) Tj ET';
        $malformedArtifactCompressed = gzcompress($malformedArtifactPayload);
        $validArtifactCompressed = gzcompress($validArtifactPayload);
        $malformedFigureCompressed = gzcompress($malformedFigurePayload);
        $malformedOptionalContentCompressed = gzcompress($malformedOptionalContentPayload);
        if (
            !is_string($malformedArtifactCompressed)
            || !is_string($validArtifactCompressed)
            || !is_string($malformedFigureCompressed)
            || !is_string($malformedOptionalContentCompressed)
        ) {
            throw new RuntimeException('Unable to compress marked-content image XObject fixture payloads.');
        }

        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R] /D << /BaseState /OFF /Order [20 0 R] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Malformed#20Artifact 5 0 R /Valid#20Artifact 6 0 R /Malformed#20Figure 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /Properties << /LayerOff 20 0 R >> /XObject << /Malformed#20Artifact 5 0 R /Valid#20Artifact 6 0 R /Malformed#20Figure 7 0 R /Malformed#20OC 8 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedArtifactCompressed) . " >>\nstream\n{$malformedArtifactCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validArtifactCompressed) . " >>\nstream\n{$validArtifactCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedFigureCompressed) . " >>\nstream\n{$malformedFigureCompressed}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedOptionalContentCompressed) . " >>\nstream\n{$malformedOptionalContentCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /OCG /Name (Hidden Image Layer) >>\nendobj\n%%EOF";

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
        $t->same(4, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);
        $t->same(1, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $malformedArtifact = $entriesByName['Malformed Artifact'];
        $t->same(true, $malformedArtifact['invoked']);
        $t->same(1, $malformedArtifact['invocation_count']);
        $t->same(1, $malformedArtifact['painted_invocation_count']);
        $t->same([[12.0, 0.0, 0.0, 6.0, 72.0, 690.0]], $malformedArtifact['invocation_matrices']);
        $t->same([72.0, 690.0, 84.0, 696.0], $malformedArtifact['image_unit_bbox']);
        $t->same(false, $malformedArtifact['marked_content_review_only']);
        $t->same([], $malformedArtifact['invocation_marked_content']);
        $t->same(hash('sha256', $malformedArtifactPayload), $malformedArtifact['decoded_sha256']);
        $t->same(false, $malformedArtifact['payload_in_visible_text']);

        $validArtifact = $entriesByName['Valid Artifact'];
        $t->same(false, $validArtifact['invoked']);
        $t->same(0, $validArtifact['invocation_count']);
        $t->same(0, $validArtifact['painted_invocation_count']);
        $t->same([], $validArtifact['invocation_matrices']);
        $t->same(false, $validArtifact['marked_content_review_only']);
        $t->same(hash('sha256', $validArtifactPayload), $validArtifact['decoded_sha256']);
        $t->same(false, $validArtifact['payload_in_visible_text']);

        $malformedFigure = $entriesByName['Malformed Figure'];
        $t->same(true, $malformedFigure['invoked']);
        $t->same(1, $malformedFigure['invocation_count']);
        $t->same(1, $malformedFigure['painted_invocation_count']);
        $t->same([[14.0, 0.0, 0.0, 7.0, 120.0, 690.0]], $malformedFigure['invocation_matrices']);
        $t->same([120.0, 690.0, 134.0, 697.0], $malformedFigure['image_unit_bbox']);
        $t->same(false, $malformedFigure['marked_content_review_only']);
        $t->same([], $malformedFigure['invocation_marked_content']);
        $t->same(hash('sha256', $malformedFigurePayload), $malformedFigure['decoded_sha256']);
        $t->same(false, $malformedFigure['payload_in_visible_text']);

        $malformedOptionalContent = $entriesByName['Malformed OC'];
        $t->same(true, $malformedOptionalContent['optional_content_visible']);
        $t->same(true, $malformedOptionalContent['invoked']);
        $t->same(1, $malformedOptionalContent['invocation_count']);
        $t->same(1, $malformedOptionalContent['painted_invocation_count']);
        $t->same([[8.0, 0.0, 0.0, 4.0, 150.0, 690.0]], $malformedOptionalContent['invocation_matrices']);
        $t->same([150.0, 690.0, 158.0, 694.0], $malformedOptionalContent['image_unit_bbox']);
        $t->same(hash('sha256', $malformedOptionalContentPayload), $malformedOptionalContent['decoded_sha256']);
        $t->same(false, $malformedOptionalContent['payload_in_visible_text']);

        $t->same(['Before marked-content operand images', 'After marked-content operand images'], $extractor->extractTextLines($pdf));
        $t->same("Before marked-content operand images\nAfter marked-content operand images", $plainText);
        $t->true(!str_contains($plainText, 'Malformed Artifact Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Artifact Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Malformed Figure Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Malformed Optional Content Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Malformed Figure Alt Review'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $malformedArtifactPayload));
        $t->true(!str_contains($encoded, $validArtifactPayload));
        $t->true(!str_contains($encoded, $malformedFigurePayload));
        $t->true(!str_contains($encoded, $malformedOptionalContentPayload));
        $t->true(!str_contains($encoded, 'Malformed Figure Alt Review'));
    },
];
