<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_parent_form_generation_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before parent form generation image) Tj ET\n"
        . "q 24 0 0 12 72 690 cm /Generated#20Form Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After parent form generation image) Tj ET';
    $currentFormContent = 'q 4 0 0 2 1 1 cm /Nested#20Generated#20Image Do Q';
    $staleFormContent = 'q 9 0 0 9 2 2 cm /Nested#20Generated#20Image Do Q';
    $currentPayload = 'BT /F1 12 Tf 72 720 Td (Current Parent Form Generation Image Payload Noise) Tj ET';
    $stalePayload = 'BT /F1 12 Tf 72 720 Td (Stale Parent Form Generation Image Payload Noise) Tj ET';
    $currentCompressed = gzcompress($currentPayload);
    $staleCompressed = gzcompress($stalePayload);
    if (!is_string($currentCompressed) || !is_string($staleCompressed)) {
        throw new RuntimeException('Unable to compress parent Form XObject generation fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Generated#20Form 5 1 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Resources << /XObject << /Nested#20Generated#20Image 6 0 R >> >> /Length " . strlen($staleFormContent) . " >>\nstream\n{$staleFormContent}\nendstream\nendobj\n"
        . "5 1 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Resources << /XObject << /Nested#20Generated#20Image 6 1 R >> >> /Length " . strlen($currentFormContent) . " >>\nstream\n{$currentFormContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
        . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload];
}

return [
    'reports generation-specific parent Form XObject metadata for nested image review rows' => static function (TestRunner $t): void {
        [$pdf, $currentPayload, $stalePayload] = markerpdf_image_xobject_parent_form_generation_boundary_pdf();
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
        $t->true(is_array($entry), 'Nested current-generation Image XObject row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Nested Generated Image', $entry['resource_name']);
        $t->same(['Generated Form', 'Nested Generated Image'], $entry['resource_path']);
        $t->same(6, $entry['object_number']);
        $t->same(1, $entry['object_generation']);
        $t->same(5, $entry['parent_form_xobject_object']);
        $t->same(1, $entry['parent_form_xobject_generation']);
        $t->same(1, $entry['form_xobject_depth']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[96.0, 0.0, 0.0, 24.0, 96.0, 702.0]], $entry['invocation_matrices']);
        $t->same([96.0, 702.0, 192.0, 726.0], $entry['image_unit_bbox']);
        $t->same(4, $entry['width']);
        $t->same(2, $entry['height']);
        $t->same('DeviceRGB', $entry['color_space']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($currentPayload), $entry['decoded_length']);
        $t->same(hash('sha256', $currentPayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->same(true, $entry['review_only']);

        $t->same(['Before parent form generation image', 'After parent form generation image'], $extractor->extractTextLines($pdf));
        $t->same("Before parent form generation image\nAfter parent form generation image", $plainText);
        $t->true(!str_contains($plainText, 'Current Parent Form Generation Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Stale Parent Form Generation Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $currentPayload));
        $t->true(!str_contains($encoded, $stalePayload));
        $t->true(str_contains($encoded, hash('sha256', $currentPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $stalePayload)));
    },
];
