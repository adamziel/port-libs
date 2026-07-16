<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'suppresses page-scope image aliases when the same stream is painted through a Form XObject alias' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before form alias image) Tj ET\n"
            . "q 24 0 0 12 72 690 cm /Logo#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After form alias image) Tj ET';
        $formContent = 'q 18 0 0 9 2 3 cm /Nested#20Alias Do Q';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Form Alias Image Payload Noise) Tj ET';
        $compressed = gzcompress($imagePayload);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress form alias image fixture.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Logo#20Form 5 0 R /Page#20Alias 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Resources << /XObject << /Nested#20Alias 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
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
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, isset($entriesByName['Page Alias']));

        $nested = $entriesByName['Nested Alias'] ?? null;
        $t->true(is_array($nested), 'Nested form image alias should be the only review row.');
        if (!is_array($nested)) {
            return;
        }

        $t->same('Nested Alias', $nested['resource_name']);
        $t->same(['Logo Form', 'Nested Alias'], $nested['resource_path']);
        $t->same(6, $nested['object_number']);
        $t->same(0, $nested['object_generation']);
        $t->same(5, $nested['parent_form_xobject_object']);
        $t->same(1, $nested['form_xobject_depth']);
        $t->same(true, $nested['invoked']);
        $t->same(1, $nested['invocation_count']);
        $t->same([[432.0, 0.0, 0.0, 108.0, 120.0, 726.0]], $nested['invocation_matrices']);
        $t->same([120.0, 726.0, 552.0, 834.0], $nested['image_unit_bbox']);
        $t->same(true, $nested['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $nested['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $nested['decoded_sha256']);
        $t->same(false, $nested['payload_in_visible_text']);
        $t->same(true, $nested['review_only']);

        $t->same(['Before form alias image', 'After form alias image'], $extractor->extractTextLines($pdf));
        $t->same("Before form alias image\nAfter form alias image", $plainText);
        $t->true(!str_contains($plainText, 'Form Alias Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Page Alias'));
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(str_contains($encoded, hash('sha256', $imagePayload)));
    },
];
