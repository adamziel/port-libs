<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'separates clip metadata from transparent ExtGState image XObject suppression' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before clipped transparent image) Tj ET\n"
            . "q 0 0 30 20 re W n /Transparent#20State gs 24 0 0 12 4 4 cm /Transparent#20Clipped Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After clipped transparent image) Tj ET';
        $payload = 'BT /F1 12 Tf 72 720 Td (Transparent Clipped Image Payload Noise) Tj ET';
        $compressed = gzcompress($payload);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress clipped transparent image XObject fixture payload.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Transparent#20State 20 0 R >> /XObject << /Transparent#20Clipped 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /ExtGState /ca 0 /BM /Normal >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $t->same('Transparent Clipped', $entry['resource_name'] ?? null);
        $t->same(true, $entry['invoked'] ?? null);
        $t->same(1, $entry['invocation_count'] ?? null);
        $t->same(0, $entry['painted_invocation_count'] ?? null);
        $t->same([[24.0, 0.0, 0.0, 12.0, 4.0, 4.0]], $entry['invocation_matrices'] ?? null);
        $t->same([[4.0, 4.0, 28.0, 16.0]], $entry['invocation_bboxes'] ?? null);
        $t->same([[0.0, 0.0, 30.0, 20.0]], $entry['invocation_clip_bboxes'] ?? null);
        $t->same([], $entry['invocation_visible_bboxes'] ?? null);
        $t->same([4.0, 4.0, 28.0, 16.0], $entry['image_unit_bbox'] ?? null);
        $t->same(null, $entry['image_visible_bbox'] ?? null);
        $t->same(true, $entry['clip_applied'] ?? null);
        $t->same(false, $entry['clip_reduces_painted_bbox'] ?? null);
        $t->same(false, $entry['clip_excludes_image'] ?? null);
        $t->same(0, $entry['clip_excluded_invocation_count'] ?? null);
        $t->same(true, $entry['graphics_state_paint_suppressed'] ?? null);
        $t->same(1, $entry['graphics_state_paint_suppressed_invocation_count'] ?? null);
        $t->same(['nonstroking_alpha_zero'], $entry['graphics_state_paint_suppression_reasons'] ?? null);
        $t->same(false, $entry['geometry_paint_suppressed'] ?? null);
        $t->same(0, $entry['geometry_paint_suppressed_invocation_count'] ?? null);
        $t->same([], $entry['geometry_paint_suppression_reasons'] ?? null);
        $t->same([['Transparent State']], array_column($entry['invocation_graphics_states'] ?? [], 'ext_gstate_resources'));
        $t->same(0.0, $entry['invocation_graphics_states'][0]['nonstroking_alpha'] ?? null);
        $t->same(true, $entry['decoded_with_current_filters'] ?? null);
        $t->same(strlen($payload), $entry['decoded_length'] ?? null);
        $t->same(hash('sha256', $payload), $entry['decoded_sha256'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);

        $t->same(['Before clipped transparent image', 'After clipped transparent image'], $extractor->extractTextLines($pdf));
        $t->same("Before clipped transparent image\nAfter clipped transparent image", $plainText);
        $t->true(!str_contains($plainText, 'Transparent Clipped Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $payload));
        $t->true(str_contains($encoded, hash('sha256', $payload)));
    },
];
