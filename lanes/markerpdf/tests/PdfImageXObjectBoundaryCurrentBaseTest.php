<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$imageXObjectBoundaryPdf = static function (): array {
    $visibleContent = "BT /F1 12 Tf 72 720 Td (Before Image XObject) Tj ET\n"
        . "q 24 0 0 12 72 690 cm /Im#20A Do Q\n"
        . "q 12 0 0 12 96 690 cm /UnusedMask Do Q\n"
        . 'BT /F1 12 Tf 72 672 Td (After Image XObject) Tj ET';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Current Image XObject Noise) Tj ET';
    $compressedImagePayload = gzcompress($imagePayload);
    if (!is_string($compressedImagePayload)) {
        throw new RuntimeException('Unable to compress image XObject fixture.');
    }
    $encodedImagePayload = strtoupper(bin2hex($compressedImagePayload)) . '>';
    $jpxMaskPayload = "\xff\x4fBT /F1 12 Tf 72 700 Td (Preview Only Mask Noise) Tj ET\xff\xd9";

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Im#20A 5 0 R /UnusedMask 6 0 R /DecoyForm 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /SMask 6 0 R /Length " . strlen($encodedImagePayload) . " >>\nstream\n{$encodedImagePayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /JPXDecode /Length " . strlen($jpxMaskPayload) . " >>\nstream\n{$jpxMaskPayload}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Length 36 >>\nstream\nBT /F1 9 Tf (Decoy Form Text) Tj ET\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload, $encodedImagePayload, $jpxMaskPayload];
};

return [
    'maps inherited page image XObject resources as review-only current-base metadata' => static function (TestRunner $t) use ($imageXObjectBoundaryPdf): void {
        [$pdf, $imagePayload, $encodedImagePayload, $jpxMaskPayload] = $imageXObjectBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $rgb = $review['entries'][0];
        $t->same(0, $rgb['page_index']);
        $t->same(3, $rgb['page_object']);
        $t->same('Im A', $rgb['resource_name']);
        $t->same(5, $rgb['object_number']);
        $t->same(true, $rgb['invoked']);
        $t->same(1, $rgb['invocation_count']);
        $t->same('Image', $rgb['subtype']);
        $t->same(2, $rgb['width']);
        $t->same(1, $rgb['height']);
        $t->same('DeviceRGB', $rgb['color_space']);
        $t->same(8, $rgb['bits_per_component']);
        $t->same(false, $rgb['image_mask']);
        $t->same(6, $rgb['soft_mask_object']);
        $t->same(true, $rgb['filters_resolved']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $rgb['filters']);
        $t->same([], $rgb['preview_only_filters']);
        $t->same(true, $rgb['native_raster_decode']);
        $t->same(strlen($encodedImagePayload), $rgb['raw_length']);
        $t->same(true, $rgb['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $rgb['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $rgb['decoded_sha256']);
        $t->same(false, $rgb['payload_in_visible_text']);
        $t->same('marker.pdf.images.render_image', $rgb['rgb_preview_boundary']);
        $t->same(true, $rgb['review_only']);

        $mask = $review['entries'][1];
        $t->same('UnusedMask', $mask['resource_name']);
        $t->same(6, $mask['object_number']);
        $t->same(true, $mask['invoked']);
        $t->same(1, $mask['invocation_count']);
        $t->same(true, $mask['image_mask']);
        $t->same(1, $mask['bits_per_component']);
        $t->same(null, $mask['color_space']);
        $t->same(['JPXDecode'], $mask['filters']);
        $t->same(['JPXDecode'], $mask['preview_only_filters']);
        $t->same(false, $mask['native_raster_decode']);
        $t->same(strlen($jpxMaskPayload), $mask['raw_length']);
        $t->same(false, $mask['decoded_with_current_filters']);
        $t->same(null, $mask['decoded_length']);
        $t->same(null, $mask['decoded_sha256']);
        $t->same(false, $mask['payload_in_visible_text']);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(!str_contains($encoded, $jpxMaskPayload));
        $t->true(!str_contains($encoded, 'Decoy Form Text'));
    },
    'keeps invoked image XObject payload bytes out of WordPress text extraction' => static function (TestRunner $t) use ($imageXObjectBoundaryPdf): void {
        [$pdf] = $imageXObjectBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before Image XObject', 'After Image XObject'], $extractor->extractTextLines($pdf));
        $t->same("Before Image XObject\nAfter Image XObject", $plainText);
        $t->same("Before Image XObject\nAfter Image XObject\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Current Image XObject Noise'));
        $t->true(!str_contains($plainText, 'Preview Only Mask Noise'));
        $t->true(!str_contains($plainText, 'Decoy Form Text'));
    },
    'maps image XObjects invoked inside Form XObject resources as review-only metadata' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before nested form image) Tj ET\n"
            . "q 48 0 0 24 72 680 cm /Logo#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 652 Td (After nested form image) Tj ET';
        $formContent = 'q 20 0 0 10 4 4 cm /Nested#20Image Do Q';
        $nestedPayload = 'BT /F1 12 Tf 72 720 Td (Nested Form Image Payload Noise) Tj ET';
        $compressedNestedPayload = gzcompress($nestedPayload);
        if (!is_string($compressedNestedPayload)) {
            throw new RuntimeException('Unable to compress nested image payload.');
        }
        $encodedNestedPayload = strtoupper(bin2hex($compressedNestedPayload)) . '>';
        $unusedPayload = 'BT /F1 12 Tf 72 720 Td (Unused Nested Image Noise) Tj ET';

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Logo#20Form 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 48 24] /Resources << /XObject << /Nested#20Image 6 0 R /UnusedNested 7 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Length " . strlen($encodedNestedPayload) . " >>\nstream\n{$encodedNestedPayload}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($unusedPayload) . " >>\nstream\n{$unusedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(2, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(1, $review['uninvoked_image_xobject_count']);

        $nested = $review['entries'][0];
        $t->same('Nested Image', $nested['resource_name']);
        $t->same(['Logo Form', 'Nested Image'], $nested['resource_path']);
        $t->same(5, $nested['parent_form_xobject_object']);
        $t->same(1, $nested['form_xobject_depth']);
        $t->same(true, $nested['invoked']);
        $t->same(1, $nested['invocation_count']);
        $t->same(4, $nested['width']);
        $t->same(2, $nested['height']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $nested['filters']);
        $t->same(true, $nested['decoded_with_current_filters']);
        $t->same(strlen($nestedPayload), $nested['decoded_length']);
        $t->same(hash('sha256', $nestedPayload), $nested['decoded_sha256']);
        $t->same(false, $nested['payload_in_visible_text']);

        $unused = $review['entries'][1];
        $t->same('UnusedNested', $unused['resource_name']);
        $t->same(['Logo Form', 'UnusedNested'], $unused['resource_path']);
        $t->same(false, $unused['invoked']);
        $t->same(0, $unused['invocation_count']);
        $t->same(5, $unused['parent_form_xobject_object']);

        $t->same(['Before nested form image', 'After nested form image'], $extractor->extractTextLines($pdf));
        $t->same("Before nested form image\nAfter nested form image", $plainText);
        $t->true(!str_contains($plainText, 'Nested Form Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Unused Nested Image Noise'));
    },
    'reports encrypted image XObject documents as fail-closed empty reviews' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R /Encrypt 9 0 R >>\n%%EOF";
        $review = (new PdfTextExtractor())->extractImageXObjectBoundaryReview($pdf);

        $t->same(true, $review['encrypted']);
        $t->same(0, $review['page_count']);
        $t->same(0, $review['image_xobject_count']);
        $t->same([], $review['entries']);
    },
];
