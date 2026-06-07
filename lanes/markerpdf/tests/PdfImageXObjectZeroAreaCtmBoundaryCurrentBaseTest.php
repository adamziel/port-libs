<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'keeps zero-area CTM image XObject invocations reviewable but unpainted' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before zero area images) Tj ET\n"
            . "q 0 0 0 10 72 690 cm /Zero#20Width#20Image Do Q\n"
            . "q 10 0 0 0 90 690 cm /Zero#20Height#20Image Do Q\n"
            . "q 12 0 0 6 110 690 cm /Visible#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After zero area images) Tj ET';
        $zeroWidthPayload = 'BT /F1 12 Tf 72 720 Td (Zero Width CTM Image Payload Noise) Tj ET';
        $zeroHeightPayload = 'BT /F1 12 Tf 72 720 Td (Zero Height CTM Image Payload Noise) Tj ET';
        $visiblePayload = 'BT /F1 12 Tf 72 720 Td (Visible CTM Image Payload Noise) Tj ET';
        $zeroWidthCompressed = gzcompress($zeroWidthPayload);
        $zeroHeightCompressed = gzcompress($zeroHeightPayload);
        $visibleCompressed = gzcompress($visiblePayload);
        if (!is_string($zeroWidthCompressed) || !is_string($zeroHeightCompressed) || !is_string($visibleCompressed)) {
            throw new RuntimeException('Unable to compress zero-area CTM image XObject fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Zero#20Width#20Image 5 0 R /Zero#20Height#20Image 6 0 R /Visible#20Image 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($zeroWidthCompressed) . " >>\nstream\n{$zeroWidthCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($zeroHeightCompressed) . " >>\nstream\n{$zeroHeightCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(3, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $zeroWidth = $entriesByName['Zero Width Image'];
        $t->same(true, $zeroWidth['invoked']);
        $t->same(1, $zeroWidth['invocation_count']);
        $t->same(0, $zeroWidth['painted_invocation_count']);
        $t->same([[0.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $zeroWidth['invocation_matrices']);
        $t->same([72.0, 690.0, 72.0, 700.0], $zeroWidth['image_unit_bbox']);
        $t->same(null, $zeroWidth['image_visible_bbox']);
        $t->same(true, $zeroWidth['geometry_paint_suppressed']);
        $t->same(1, $zeroWidth['geometry_paint_suppressed_invocation_count']);
        $t->same(['zero_area_ctm'], $zeroWidth['geometry_paint_suppression_reasons']);
        $t->same(true, $zeroWidth['decoded_with_current_filters']);
        $t->same(hash('sha256', $zeroWidthPayload), $zeroWidth['decoded_sha256']);
        $t->same(false, $zeroWidth['payload_in_visible_text']);

        $zeroHeight = $entriesByName['Zero Height Image'];
        $t->same(true, $zeroHeight['invoked']);
        $t->same(1, $zeroHeight['invocation_count']);
        $t->same(0, $zeroHeight['painted_invocation_count']);
        $t->same([[10.0, 0.0, 0.0, 0.0, 90.0, 690.0]], $zeroHeight['invocation_matrices']);
        $t->same([90.0, 690.0, 100.0, 690.0], $zeroHeight['image_unit_bbox']);
        $t->same(null, $zeroHeight['image_visible_bbox']);
        $t->same(true, $zeroHeight['geometry_paint_suppressed']);
        $t->same(1, $zeroHeight['geometry_paint_suppressed_invocation_count']);
        $t->same(['zero_area_ctm'], $zeroHeight['geometry_paint_suppression_reasons']);
        $t->same(true, $zeroHeight['decoded_with_current_filters']);
        $t->same(hash('sha256', $zeroHeightPayload), $zeroHeight['decoded_sha256']);
        $t->same(false, $zeroHeight['payload_in_visible_text']);

        $visible = $entriesByName['Visible Image'];
        $t->same(true, $visible['invoked']);
        $t->same(1, $visible['invocation_count']);
        $t->same(1, $visible['painted_invocation_count']);
        $t->same([[12.0, 0.0, 0.0, 6.0, 110.0, 690.0]], $visible['invocation_matrices']);
        $t->same([110.0, 690.0, 122.0, 696.0], $visible['image_visible_bbox']);
        $t->same(false, $visible['geometry_paint_suppressed']);
        $t->same(0, $visible['geometry_paint_suppressed_invocation_count']);
        $t->same([], $visible['geometry_paint_suppression_reasons']);
        $t->same(hash('sha256', $visiblePayload), $visible['decoded_sha256']);

        $t->same(['Before zero area images', 'After zero area images'], $extractor->extractTextLines($pdf));
        $t->same("Before zero area images\nAfter zero area images", $plainText);
        $t->true(!str_contains($plainText, 'Zero Width CTM Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Zero Height CTM Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Visible CTM Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $zeroWidthPayload));
        $t->true(!str_contains($encoded, $zeroHeightPayload));
        $t->true(!str_contains($encoded, $visiblePayload));
    },
];
