<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_escaped_form_subtype_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before escaped form image) Tj ET\n"
        . "q 20 0 0 10 72 690 cm /Escaped#20Form Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After escaped form image) Tj ET';
    $formContent = 'q 8 0 0 4 2 3 cm /Nested#20Escaped#20Image Do Q';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Escaped Form Image Payload Noise) Tj ET';
    $compressedImagePayload = gzcompress($imagePayload);
    if (!is_string($compressedImagePayload)) {
        throw new RuntimeException('Unable to compress escaped Form XObject image fixture.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Escaped#20Form 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Sub#74ype /F#6frm /BBox [0 0 20 10] /Resources << /XObject << /Nested#20Escaped#20Image 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload];
}

return [
    'resolves escaped Form XObject Subtype names before nested Image XObject review' => static function (TestRunner $t): void {
        [$pdf, $imagePayload] = markerpdf_image_xobject_escaped_form_subtype_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entry = $review['entries'][0] ?? null;
        $t->true(is_array($entry), 'Nested Image XObject review row should be present.');
        $t->same('Nested Escaped Image', $entry['resource_name'] ?? null);
        $t->same(['Escaped Form', 'Nested Escaped Image'], $entry['resource_path'] ?? null);
        $t->same(5, $entry['parent_form_xobject_object'] ?? null);
        $t->same(1, $entry['form_xobject_depth'] ?? null);
        $t->same(true, $entry['invoked'] ?? null);
        $t->same(1, $entry['invocation_count'] ?? null);
        $t->same([[160.0, 0.0, 0.0, 40.0, 112.0, 720.0]], $entry['invocation_matrices'] ?? null);
        $t->same([[112.0, 720.0, 272.0, 760.0]], $entry['invocation_bboxes'] ?? null);
        $t->same([112.0, 720.0, 272.0, 760.0], $entry['image_unit_bbox'] ?? null);
        $t->same(2, $entry['width'] ?? null);
        $t->same(1, $entry['height'] ?? null);
        $t->same('DeviceRGB', $entry['color_space'] ?? null);
        $t->same(true, $entry['decoded_with_current_filters'] ?? null);
        $t->same(strlen($imagePayload), $entry['decoded_length'] ?? null);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(true, $entry['review_only'] ?? null);

        $t->same(['Before escaped form image', 'After escaped form image'], $extractor->extractTextLines($pdf));
        $t->same("Before escaped form image\nAfter escaped form image", $plainText);
        $t->true(!str_contains($plainText, 'Escaped Form Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(str_contains($encoded, hash('sha256', $imagePayload)));
    },
];
