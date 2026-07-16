<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'preserves marked-content image review across q Q graphics-state restore' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before marked q image) Tj ET\n"
            . "q /Figure << /MCID 7 /Alt (WordPress Figure Image Review) >> BDC 20 0 0 10 72 690 cm Q\n"
            . "/Marked#20Image Do EMC\n"
            . 'BT /F1 12 Tf 72 660 Td (After marked q image) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Marked q Image Payload Noise) Tj ET';
        $compressed = gzcompress($imagePayload);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress marked q/Q image XObject fixture payload.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Marked#20Image 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0] ?? null;

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
        $t->true(is_array($entry), 'Marked image review row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Marked Image', $entry['resource_name']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]], $entry['invocation_matrices']);
        $t->same([0.0, 0.0, 1.0, 1.0], $entry['image_unit_bbox']);
        $t->same(true, $entry['marked_content_review_only']);
        $t->same(['Figure'], $entry['invocation_marked_content'][0]['tags'] ?? null);
        $t->same([7], $entry['invocation_marked_content'][0]['mcids'] ?? null);
        $t->same('Figure', $entry['invocation_marked_content'][0]['stack'][0]['tag'] ?? null);
        $t->same(7, $entry['invocation_marked_content'][0]['stack'][0]['mcid'] ?? null);
        $t->same('WordPress Figure Image Review', $entry['invocation_marked_content'][0]['stack'][0]['alt_text'] ?? null);
        $t->same(true, $entry['invocation_marked_content'][0]['stack'][0]['review_only'] ?? null);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $entry['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->same(true, $entry['review_only']);

        $t->same(['Before marked q image', 'After marked q image'], $extractor->extractTextLines($pdf));
        $t->same("Before marked q image\nAfter marked q image", $plainText);
        $t->true(!str_contains($plainText, 'Marked q Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
    },
];
