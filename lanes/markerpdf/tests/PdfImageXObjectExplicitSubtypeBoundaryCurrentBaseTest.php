<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_explicit_non_image_subtype_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before explicit subtype image) Tj ET\n"
        . "q 20 0 0 10 72 690 cm /Dimensioned#20Form Do Q\n"
        . "q 9 0 0 9 96 690 cm /PostScript#20Decoy Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After explicit subtype image) Tj ET';
    $formContent = 'q 5 0 0 5 2 2 cm /Nested#20Image Do Q';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Explicit Subtype Nested Image Payload Noise) Tj ET';
    $postScriptPayload = 'BT /F1 12 Tf 72 720 Td (Explicit Subtype PostScript Decoy Noise) Tj ET';
    $compressedImagePayload = gzcompress($imagePayload);
    if (!is_string($compressedImagePayload)) {
        throw new RuntimeException('Unable to compress explicit subtype image fixture.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Dimensioned#20Form 5 0 R /PostScript#20Decoy 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Resources << /XObject << /Nested#20Image 6 0 R >> >> /Width 99 /Height 99 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /PS /Width 88 /Height 77 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($postScriptPayload) . " >>\nstream\n{$postScriptPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload, $postScriptPayload];
}

return [
    'keeps explicit non-Image XObject subtypes out of image-key fallback review' => static function (TestRunner $t): void {
        [$pdf, $imagePayload, $postScriptPayload] = markerpdf_image_xobject_explicit_non_image_subtype_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(false, isset($entriesByName['Dimensioned Form']));
        $t->same(false, isset($entriesByName['PostScript Decoy']));

        $entry = $entriesByName['Nested Image'] ?? null;
        $t->true(is_array($entry), 'Nested Image review row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Nested Image', $entry['resource_name']);
        $t->same(['Dimensioned Form', 'Nested Image'], $entry['resource_path']);
        $t->same(6, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same('Image', $entry['subtype']);
        $t->same(5, $entry['parent_form_xobject_object']);
        $t->same(1, $entry['form_xobject_depth']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[100.0, 0.0, 0.0, 50.0, 112.0, 710.0]], $entry['invocation_matrices']);
        $t->same([[112.0, 710.0, 212.0, 760.0]], $entry['invocation_bboxes']);
        $t->same([112.0, 710.0, 212.0, 760.0], $entry['image_unit_bbox']);
        $t->same(2, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same('DeviceRGB', $entry['color_space']);
        $t->same(8, $entry['bits_per_component']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $entry['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->same(true, $entry['review_only']);

        $t->same(['Before explicit subtype image', 'After explicit subtype image'], $extractor->extractTextLines($pdf));
        $t->same("Before explicit subtype image\nAfter explicit subtype image", $plainText);
        $t->true(!str_contains($plainText, 'Explicit Subtype Nested Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Explicit Subtype PostScript Decoy Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(!str_contains($encoded, $postScriptPayload));
        $t->true(str_contains($encoded, hash('sha256', $imagePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $postScriptPayload)));
    },
];
