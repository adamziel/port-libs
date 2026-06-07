<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'records ExtGState overprint controls before Image XObject review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before overprint image) Tj ET\n"
            . "q /Print#20Overprint gs 24 0 0 12 72 690 cm /Spot#20Image Do Q\n"
            . "q /Knockout#20Overprint gs 16 0 0 8 108 690 cm /Process#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After overprint image) Tj ET';
        $spotPayload = 'BT /F1 12 Tf 72 720 Td (Spot Overprint Image Payload Noise) Tj ET';
        $processPayload = 'BT /F1 12 Tf 72 720 Td (Process Overprint Image Payload Noise) Tj ET';
        $spotCompressed = gzcompress($spotPayload);
        $processCompressed = gzcompress($processPayload);
        if (!is_string($spotCompressed) || !is_string($processCompressed)) {
            throw new RuntimeException('Unable to compress ExtGState overprint image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Print#20Overprint 20 0 R /Knockout#20Overprint 21 0 R >> /XObject << /Spot#20Image 5 0 R /Process#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($spotCompressed) . " >>\nstream\n{$spotCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($processCompressed) . " >>\nstream\n{$processCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /ExtGState /OP true /op true /OPM 1 /ca 0.75 /BM /Multiply >>\nendobj\n"
            . "21 0 obj\n<< /Type /ExtGState /OP false /op false /OPM 0 /ca 1 /BM /Normal >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $spot = $entriesByName['Spot Image'];
        $spotState = $spot['invocation_graphics_states'][0] ?? [];
        $t->same(true, $spot['invoked']);
        $t->same(1, $spot['invocation_count']);
        $t->same([['Print Overprint']], array_column($spot['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same('Print Overprint', $spotState['applied_extgstates'][0]['resource_name'] ?? null);
        $t->same(true, $spotState['stroking_overprint'] ?? null);
        $t->same(true, $spotState['nonstroking_overprint'] ?? null);
        $t->same(1, $spotState['overprint_mode'] ?? null);
        $t->same(0.75, $spotState['nonstroking_alpha'] ?? null);
        $t->same(['Multiply'], $spotState['blend_modes'] ?? null);
        $t->same([72.0, 690.0, 96.0, 702.0], $spot['image_visible_bbox']);
        $t->same(hash('sha256', $spotPayload), $spot['decoded_sha256']);
        $t->same(false, $spot['payload_in_visible_text']);

        $process = $entriesByName['Process Image'];
        $processState = $process['invocation_graphics_states'][0] ?? [];
        $t->same(true, $process['invoked']);
        $t->same(1, $process['invocation_count']);
        $t->same([['Knockout Overprint']], array_column($process['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same('Knockout Overprint', $processState['applied_extgstates'][0]['resource_name'] ?? null);
        $t->same(false, $processState['stroking_overprint'] ?? null);
        $t->same(false, $processState['nonstroking_overprint'] ?? null);
        $t->same(0, $processState['overprint_mode'] ?? null);
        $t->same(1.0, $processState['nonstroking_alpha'] ?? null);
        $t->same(['Normal'], $processState['blend_modes'] ?? null);
        $t->same([108.0, 690.0, 124.0, 698.0], $process['image_visible_bbox']);
        $t->same(hash('sha256', $processPayload), $process['decoded_sha256']);
        $t->same(false, $process['payload_in_visible_text']);

        $t->same(['Before overprint image', 'After overprint image'], $extractor->extractTextLines($pdf));
        $t->same("Before overprint image\nAfter overprint image", $plainText);
        $t->true(!str_contains($plainText, 'Spot Overprint Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Process Overprint Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $spotPayload));
        $t->true(!str_contains($encoded, $processPayload));
    },
];
