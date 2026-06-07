<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_form_matrix_operand_tail_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before form matrix boundary image) Tj ET\n"
        . "q 10 0 0 5 72 690 cm /Bad#20Matrix#20Form Do Q\n"
        . "q 10 0 0 5 120 690 cm /Good#20Matrix#20Form Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After form matrix boundary image) Tj ET';
    $formContent = 'q 2 0 0 2 1 1 cm /Nested#20Matrix#20Image Do Q';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Form Matrix Operand Tail Image Payload Noise) Tj ET';
    $compressed = gzcompress($imagePayload);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress Form Matrix operand-tail image fixture payload.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Bad#20Matrix#20Form 5 0 R /Good#20Matrix#20Form 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Matrix [1 0 0 1 5 5] 99 /Resources << /XObject << /Nested#20Matrix#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Matrix [1 0 0 1 5 5] /Resources << /XObject << /Nested#20Matrix#20Image 6 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload];
}

return [
    'rejects Form XObject Matrix arrays with trailing operands before nested image placement' => static function (TestRunner $t): void {
        [$pdf, $imagePayload] = markerpdf_image_xobject_form_matrix_operand_tail_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entries = [];
        foreach ($review['entries'] as $entry) {
            $entries[implode('/', $entry['resource_path'])] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $bad = $entries['Bad Matrix Form/Nested Matrix Image'] ?? null;
        $good = $entries['Good Matrix Form/Nested Matrix Image'] ?? null;
        $t->true(is_array($bad), 'Malformed Matrix Form nested image review row should be present.');
        $t->true(is_array($good), 'Valid Matrix Form nested image review row should be present.');
        if (!is_array($bad) || !is_array($good)) {
            return;
        }

        $t->same('Nested Matrix Image', $bad['resource_name']);
        $t->same(['Bad Matrix Form', 'Nested Matrix Image'], $bad['resource_path']);
        $t->same(5, $bad['parent_form_xobject_object']);
        $t->same(1, $bad['form_xobject_depth']);
        $t->same(true, $bad['invoked']);
        $t->same(1, $bad['invocation_count']);
        $t->same([[20.0, 0.0, 0.0, 10.0, 82.0, 695.0]], $bad['invocation_matrices']);
        $t->same([[82.0, 695.0, 102.0, 705.0]], $bad['invocation_bboxes']);
        $t->same([82.0, 695.0, 102.0, 705.0], $bad['image_unit_bbox']);
        $t->same(true, $bad['placement_review_only']);
        $t->same(hash('sha256', $imagePayload), $bad['decoded_sha256']);
        $t->same(false, $bad['payload_in_visible_text']);

        $t->same('Nested Matrix Image', $good['resource_name']);
        $t->same(['Good Matrix Form', 'Nested Matrix Image'], $good['resource_path']);
        $t->same(7, $good['parent_form_xobject_object']);
        $t->same(1, $good['form_xobject_depth']);
        $t->same(true, $good['invoked']);
        $t->same(1, $good['invocation_count']);
        $t->same([[20.0, 0.0, 0.0, 10.0, 180.0, 720.0]], $good['invocation_matrices']);
        $t->same([[180.0, 720.0, 200.0, 730.0]], $good['invocation_bboxes']);
        $t->same([180.0, 720.0, 200.0, 730.0], $good['image_unit_bbox']);
        $t->same(true, $good['placement_review_only']);
        $t->same(hash('sha256', $imagePayload), $good['decoded_sha256']);
        $t->same(false, $good['payload_in_visible_text']);

        $t->same(['Before form matrix boundary image', 'After form matrix boundary image'], $extractor->extractTextLines($pdf));
        $t->same("Before form matrix boundary image\nAfter form matrix boundary image", $plainText);
        $t->true(!str_contains($plainText, 'Form Matrix Operand Tail Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(str_contains($encoded, hash('sha256', $imagePayload)));
    },
];
