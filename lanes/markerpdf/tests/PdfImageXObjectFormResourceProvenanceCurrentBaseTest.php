<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$formResourceProvenancePdf = static function (): array {
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before nested form image provenance) Tj ET\n"
        . "q 20 0 0 10 72 690 cm /Logo#20Form Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After nested form image provenance) Tj ET';
    $formContent = 'q 8 0 0 4 1 2 cm /Nested#20Logo Do Q';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Nested Form Provenance Image Payload Noise) Tj ET';
    $compressed = gzcompress($imagePayload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress nested Form image provenance fixture.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Resources << /XObject << /Nested#20Logo 6 0 R >> /Font << /F1 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /Logo#20Form 5 0 R >> >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload];
};

return [
    'preserves page resource provenance for images painted from Form XObject resources' => static function (TestRunner $t) use ($formResourceProvenancePdf): void {
        [$pdf, $imagePayload] = $formResourceProvenancePdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entry = $review['entries'][0] ?? null;
        $t->true(is_array($entry), 'Nested Form image review row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Nested Logo', $entry['resource_name']);
        $t->same(['Logo Form', 'Nested Logo'], $entry['resource_path']);
        $t->same(1, $entry['form_xobject_depth']);
        $t->same(5, $entry['parent_form_xobject_object']);
        $t->same(6, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[160.0, 0.0, 0.0, 40.0, 92.0, 710.0]], $entry['invocation_matrices']);
        $t->same([92.0, 710.0, 252.0, 750.0], $entry['image_unit_bbox']);
        $t->same('Image', $entry['subtype']);
        $t->same(1, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same('DeviceGray', $entry['color_space']);
        $t->same(['FlateDecode'], $entry['filters']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $entry['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->same(true, $entry['page_resource_inherited']);
        $t->same(2, $entry['page_resource_owner_object']);
        $t->same(10, $entry['page_resource_object']);
        $t->same(0, $entry['page_resource_generation']);
        $t->same(true, $entry['page_resource_review_only']);
        $t->same(true, $entry['placement_review_only']);
        $t->same(true, $entry['review_only']);

        $t->same(
            ['Before nested form image provenance', 'After nested form image provenance'],
            $extractor->extractTextLines($pdf)
        );
        $t->same("Before nested form image provenance\nAfter nested form image provenance", $plainText);
        $t->true(!str_contains($plainText, 'Nested Form Provenance Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encoded, hash('sha256', $imagePayload)));
        $t->true(!str_contains($encoded, $imagePayload));
    },
];
