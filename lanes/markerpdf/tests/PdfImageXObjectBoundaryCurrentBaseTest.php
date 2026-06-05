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
    'maps image XObjects invoked by resource-less Form XObjects through inherited page resources' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before inherited form image) Tj ET\n"
            . "q 36 0 0 18 72 680 cm /Logo#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 652 Td (After inherited form image) Tj ET';
        $formContent = 'q 18 0 0 9 3 3 cm /Shared#20Image Do Q';
        $sharedPayload = 'BT /F1 12 Tf 72 720 Td (Inherited Resource Image Payload Noise) Tj ET';
        $compressedSharedPayload = gzcompress($sharedPayload);
        if (!is_string($compressedSharedPayload)) {
            throw new RuntimeException('Unable to compress inherited image payload.');
        }
        $encodedSharedPayload = strtoupper(bin2hex($compressedSharedPayload)) . '>';
        $unusedPayload = 'BT /F1 12 Tf 72 720 Td (Unused Page Image Payload Noise) Tj ET';

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Logo#20Form 5 0 R /Shared#20Image 6 0 R /UnusedPageImage 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Length " . strlen($encodedSharedPayload) . " >>\nstream\n{$encodedSharedPayload}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($unusedPayload) . " >>\nstream\n{$unusedPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(2, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(1, $review['uninvoked_image_xobject_count']);

        $shared = $review['entries'][0];
        $t->same('Shared Image', $shared['resource_name']);
        $t->same(['Logo Form', 'Shared Image'], $shared['resource_path']);
        $t->same(5, $shared['parent_form_xobject_object']);
        $t->same(1, $shared['form_xobject_depth']);
        $t->same(6, $shared['object_number']);
        $t->same(true, $shared['invoked']);
        $t->same(1, $shared['invocation_count']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $shared['filters']);
        $t->same(true, $shared['decoded_with_current_filters']);
        $t->same(strlen($sharedPayload), $shared['decoded_length']);
        $t->same(hash('sha256', $sharedPayload), $shared['decoded_sha256']);
        $t->same(false, $shared['payload_in_visible_text']);

        $unused = $review['entries'][1];
        $t->same('UnusedPageImage', $unused['resource_name']);
        $t->same(['UnusedPageImage'], $unused['resource_path']);
        $t->same(null, $unused['parent_form_xobject_object']);
        $t->same(false, $unused['invoked']);
        $t->same(0, $unused['invocation_count']);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $sharedPayload));
        $t->true(!str_contains($plainText, 'Inherited Resource Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Unused Page Image Payload Noise'));
        $t->same(['Before inherited form image', 'After inherited form image'], $extractor->extractTextLines($pdf));
    },
    'counts optional-content-hidden image XObject invocations as unpainted review metadata' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Current Optional Content Images) Tj ET\n"
            . "/OC /LayerOff BDC q 12 0 0 12 72 690 cm /HiddenMarked Do Q EMC\n"
            . "/OC /LayerOn BDC q 12 0 0 12 92 690 cm /Visible#20Image Do Q EMC\n"
            . "q 12 0 0 12 112 690 cm /HiddenObject Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After Optional Content Images) Tj ET';
        $visiblePayload = 'BT /F1 12 Tf 72 720 Td (Visible Optional Image Noise) Tj ET';
        $hiddenMarkedPayload = 'BT /F1 12 Tf 72 720 Td (Hidden Marked Image Noise) Tj ET';
        $hiddenObjectPayload = 'BT /F1 12 Tf 72 720 Td (Hidden Object Image Noise) Tj ET';
        $visibleCompressed = gzcompress($visiblePayload);
        $hiddenMarkedCompressed = gzcompress($hiddenMarkedPayload);
        $hiddenObjectCompressed = gzcompress($hiddenObjectPayload);
        if (!is_string($visibleCompressed) || !is_string($hiddenMarkedCompressed) || !is_string($hiddenObjectCompressed)) {
            throw new RuntimeException('Unable to compress optional content image fixture payloads.');
        }

        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /Properties << /LayerOn 20 0 R /LayerOff 21 0 R >> /XObject << /Visible#20Image 5 0 R /HiddenMarked 6 0 R /HiddenObject 7 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /OC 20 0 R /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($hiddenMarkedCompressed) . " >>\nstream\n{$hiddenMarkedCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /OC 21 0 R /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($hiddenObjectCompressed) . " >>\nstream\n{$hiddenObjectCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /OCG /Name (Visible Image Layer) >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (Hidden Image Layer) >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(3, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(2, $review['uninvoked_image_xobject_count']);
        $t->same(['Current Optional Content Images', 'After Optional Content Images'], $extractor->extractTextLines($pdf));
        $t->same("Current Optional Content Images\nAfter Optional Content Images", $plainText);

        $visible = $entriesByName['Visible Image'];
        $t->same(true, $visible['optional_content_visible']);
        $t->same(true, $visible['invoked']);
        $t->same(1, $visible['invocation_count']);
        $t->same(true, $visible['decoded_with_current_filters']);
        $t->same(hash('sha256', $visiblePayload), $visible['decoded_sha256']);

        $hiddenMarked = $entriesByName['HiddenMarked'];
        $t->same(true, $hiddenMarked['optional_content_visible']);
        $t->same(false, $hiddenMarked['invoked']);
        $t->same(0, $hiddenMarked['invocation_count']);
        $t->same(true, $hiddenMarked['decoded_with_current_filters']);
        $t->same(hash('sha256', $hiddenMarkedPayload), $hiddenMarked['decoded_sha256']);

        $hiddenObject = $entriesByName['HiddenObject'];
        $t->same(false, $hiddenObject['optional_content_visible']);
        $t->same(false, $hiddenObject['invoked']);
        $t->same(0, $hiddenObject['invocation_count']);
        $t->same(true, $hiddenObject['decoded_with_current_filters']);
        $t->same(hash('sha256', $hiddenObjectPayload), $hiddenObject['decoded_sha256']);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $visiblePayload));
        $t->true(!str_contains($encoded, $hiddenMarkedPayload));
        $t->true(!str_contains($encoded, $hiddenObjectPayload));
        $t->true(!str_contains($plainText, 'Visible Optional Image Noise'));
        $t->true(!str_contains($plainText, 'Hidden Marked Image Noise'));
        $t->true(!str_contains($plainText, 'Hidden Object Image Noise'));
    },
    'honors inline OCMD dictionaries before counting image XObject invocations' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before inline OCMD images) Tj ET\n"
            . "/OC << /Type /OCMD /OCGs [20 0 R 21 0 R] /P /AllOn >> BDC q 16 0 0 8 72 690 cm /Inline#20Hidden Do Q EMC\n"
            . "/OC << /Type /OCMD /OCGs [20 0 R 22 0 R] /P /AllOn >> BDC q 16 0 0 8 96 690 cm /Inline#20Visible Do Q EMC\n"
            . "q 8 0 0 8 120 690 cm /Plain#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After inline OCMD images) Tj ET';
        $hiddenPayload = 'BT /F1 12 Tf 72 720 Td (Hidden Inline OCMD Image Payload Noise) Tj ET';
        $visiblePayload = 'BT /F1 12 Tf 72 720 Td (Visible Inline OCMD Image Payload Noise) Tj ET';
        $plainPayload = 'BT /F1 12 Tf 72 720 Td (Plain Inline OCMD Image Payload Noise) Tj ET';
        $hiddenCompressed = gzcompress($hiddenPayload);
        $visibleCompressed = gzcompress($visiblePayload);
        $plainCompressed = gzcompress($plainPayload);
        if (!is_string($hiddenCompressed) || !is_string($visibleCompressed) || !is_string($plainCompressed)) {
            throw new RuntimeException('Unable to compress inline OCMD image fixture payloads.');
        }

        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R 22 0 R] /D << /BaseState /OFF /ON [20 0 R 22 0 R] /Order [20 0 R 21 0 R 22 0 R] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /XObject << /Inline#20Hidden 5 0 R /Inline#20Visible 6 0 R /Plain#20Image 7 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($hiddenCompressed) . " >>\nstream\n{$hiddenCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($plainCompressed) . " >>\nstream\n{$plainCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /OCG /Name (Visible Shared Layer) >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (Hidden Inline Layer) >>\nendobj\n"
            . "22 0 obj\n<< /Type /OCG /Name (Visible Inline Layer) >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(3, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(1, $review['uninvoked_image_xobject_count']);
        $t->same(['Before inline OCMD images', 'After inline OCMD images'], $extractor->extractTextLines($pdf));
        $t->same("Before inline OCMD images\nAfter inline OCMD images", $plainText);

        $hidden = $entriesByName['Inline Hidden'];
        $t->same(true, $hidden['optional_content_visible']);
        $t->same(false, $hidden['invoked']);
        $t->same(0, $hidden['invocation_count']);
        $t->same(true, $hidden['decoded_with_current_filters']);
        $t->same(hash('sha256', $hiddenPayload), $hidden['decoded_sha256']);

        $visible = $entriesByName['Inline Visible'];
        $t->same(true, $visible['optional_content_visible']);
        $t->same(true, $visible['invoked']);
        $t->same(1, $visible['invocation_count']);
        $t->same([[16.0, 0.0, 0.0, 8.0, 96.0, 690.0]], $visible['invocation_matrices']);
        $t->same([96.0, 690.0, 112.0, 698.0], $visible['image_unit_bbox']);
        $t->same(true, $visible['decoded_with_current_filters']);
        $t->same(hash('sha256', $visiblePayload), $visible['decoded_sha256']);

        $plain = $entriesByName['Plain Image'];
        $t->same(true, $plain['optional_content_visible']);
        $t->same(true, $plain['invoked']);
        $t->same(1, $plain['invocation_count']);
        $t->same([[8.0, 0.0, 0.0, 8.0, 120.0, 690.0]], $plain['invocation_matrices']);
        $t->same([120.0, 690.0, 128.0, 698.0], $plain['image_unit_bbox']);
        $t->same(true, $plain['decoded_with_current_filters']);
        $t->same(hash('sha256', $plainPayload), $plain['decoded_sha256']);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $hiddenPayload));
        $t->true(!str_contains($encoded, $visiblePayload));
        $t->true(!str_contains($encoded, $plainPayload));
        $t->true(!str_contains($plainText, 'Hidden Inline OCMD Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Visible Inline OCMD Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Plain Inline OCMD Image Payload Noise'));
    },
    'records image XObject invocation CTM placement for WordPress media review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before placed images) Tj ET\n"
            . "q 2 0 0 2 10 20 cm q 12 0 0 8 5 6 cm /Placed#20Image Do Q Q\n"
            . "q 0 10 -20 0 100 200 cm /RotatedImage Do Q\n"
            . "q 30 0 0 15 150 300 cm /Logo#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After placed images) Tj ET';
        $formContent = 'q 4 0 0 2 6 8 cm /Nested#20Placed Do Q';
        $placedPayload = 'BT /F1 12 Tf 72 720 Td (Placed Image Payload Noise) Tj ET';
        $rotatedPayload = 'BT /F1 12 Tf 72 720 Td (Rotated Image Payload Noise) Tj ET';
        $nestedPayload = 'BT /F1 12 Tf 72 720 Td (Nested Placed Image Payload Noise) Tj ET';
        $placedCompressed = gzcompress($placedPayload);
        $rotatedCompressed = gzcompress($rotatedPayload);
        $nestedCompressed = gzcompress($nestedPayload);
        if (!is_string($placedCompressed) || !is_string($rotatedCompressed) || !is_string($nestedCompressed)) {
            throw new RuntimeException('Unable to compress placed image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Placed#20Image 5 0 R /RotatedImage 6 0 R /Logo#20Form 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 12 /Height 8 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($placedCompressed) . " >>\nstream\n{$placedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 20 /Height 10 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($rotatedCompressed) . " >>\nstream\n{$rotatedCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 30 15] /Matrix [1 0 0 1 3 4] /Resources << /XObject << /Nested#20Placed 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($nestedCompressed) . " >>\nstream\n{$nestedCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $placed = $entriesByName['Placed Image'];
        $t->same(1, $placed['invocation_count']);
        $t->same([[24.0, 0.0, 0.0, 16.0, 20.0, 32.0]], $placed['invocation_matrices']);
        $t->same([[20.0, 32.0, 44.0, 48.0]], $placed['invocation_bboxes']);
        $t->same([20.0, 32.0, 44.0, 48.0], $placed['image_unit_bbox']);
        $t->same(true, $placed['placement_review_only']);

        $rotated = $entriesByName['RotatedImage'];
        $t->same([[0.0, 10.0, -20.0, 0.0, 100.0, 200.0]], $rotated['invocation_matrices']);
        $t->same([[80.0, 200.0, 100.0, 210.0]], $rotated['invocation_bboxes']);
        $t->same([80.0, 200.0, 100.0, 210.0], $rotated['image_unit_bbox']);

        $nested = $entriesByName['Nested Placed'];
        $t->same(['Logo Form', 'Nested Placed'], $nested['resource_path']);
        $t->same(7, $nested['parent_form_xobject_object']);
        $t->same([[120.0, 0.0, 0.0, 30.0, 420.0, 480.0]], $nested['invocation_matrices']);
        $t->same([[420.0, 480.0, 540.0, 510.0]], $nested['invocation_bboxes']);
        $t->same([420.0, 480.0, 540.0, 510.0], $nested['image_unit_bbox']);

        $t->same(['Before placed images', 'After placed images'], $extractor->extractTextLines($pdf));
        $t->same("Before placed images\nAfter placed images", $plainText);
        $t->true(!str_contains($plainText, 'Placed Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Rotated Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Nested Placed Image Payload Noise'));
    },
    'preserves graphics state across page Contents arrays for image XObject placement review' => static function (TestRunner $t): void {
        $firstContent = "BT /F1 12 Tf 72 720 Td (Before split image state) Tj ET\n"
            . 'q 10 0 0 5 100 200 cm ';
        $secondContent = "/Split#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After split image state) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Split Contents Image Payload Noise) Tj ET';
        $compressedImagePayload = gzcompress($imagePayload);
        if (!is_string($compressedImagePayload)) {
            throw new RuntimeException('Unable to compress split content image payload.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Split#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($firstContent) . " >>\nstream\n{$firstContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($secondContent) . " >>\nstream\n{$secondContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 10 /Height 5 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $entry = $review['entries'][0];
        $t->same('Split Image', $entry['resource_name']);
        $t->same([[10.0, 0.0, 0.0, 5.0, 100.0, 200.0]], $entry['invocation_matrices']);
        $t->same([[100.0, 200.0, 110.0, 205.0]], $entry['invocation_bboxes']);
        $t->same([100.0, 200.0, 110.0, 205.0], $entry['image_unit_bbox']);
        $t->same([100.0, 200.0, 110.0, 205.0], $entry['image_visible_bbox']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $entry['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);

        $t->same(['Before split image state', 'After split image state'], $extractor->extractTextLines($pdf));
        $t->same("Before split image state\nAfter split image state", $plainText);
        $t->true(!str_contains($plainText, 'Split Contents Image Payload Noise'));
    },
    'applies rectangular clipping paths to image XObject placement review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before clipped images) Tj ET\n"
            . "q 10 10 30 20 re W n 50 0 0 40 0 0 cm /Clipped#20Image Do Q\n"
            . "q 100 100 15 10 re W n 20 0 0 20 0 0 cm /OutsideClip Do Q\n"
            . "q 40 0 0 20 100 200 cm /Clip#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After clipped images) Tj ET';
        $formContent = 'q 8 4 12 8 re W n 16 0 0 12 4 2 cm /Nested#20Clipped Do Q';
        $clippedPayload = 'BT /F1 12 Tf 72 720 Td (Clipped Image Payload Noise) Tj ET';
        $outsidePayload = 'BT /F1 12 Tf 72 720 Td (Outside Clip Image Payload Noise) Tj ET';
        $nestedPayload = 'BT /F1 12 Tf 72 720 Td (Nested Clipped Image Payload Noise) Tj ET';
        $clippedCompressed = gzcompress($clippedPayload);
        $outsideCompressed = gzcompress($outsidePayload);
        $nestedCompressed = gzcompress($nestedPayload);
        if (!is_string($clippedCompressed) || !is_string($outsideCompressed) || !is_string($nestedCompressed)) {
            throw new RuntimeException('Unable to compress clipped image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Clipped#20Image 5 0 R /OutsideClip 6 0 R /Clip#20Form 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($clippedCompressed) . " >>\nstream\n{$clippedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 20 /Height 20 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($outsideCompressed) . " >>\nstream\n{$outsideCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 40 20] /Resources << /XObject << /Nested#20Clipped 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 12 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($nestedCompressed) . " >>\nstream\n{$nestedCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(3, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);

        $clipped = $entriesByName['Clipped Image'];
        $t->same([[50.0, 0.0, 0.0, 40.0, 0.0, 0.0]], $clipped['invocation_matrices']);
        $t->same([[0.0, 0.0, 50.0, 40.0]], $clipped['invocation_bboxes']);
        $t->same([[10.0, 10.0, 40.0, 30.0]], $clipped['invocation_clip_bboxes'] ?? null);
        $t->same([[10.0, 10.0, 40.0, 30.0]], $clipped['invocation_visible_bboxes'] ?? null);
        $t->same([0.0, 0.0, 50.0, 40.0], $clipped['image_unit_bbox']);
        $t->same([10.0, 10.0, 40.0, 30.0], $clipped['image_visible_bbox'] ?? null);
        $t->same(true, $clipped['clip_applied'] ?? null);
        $t->same(true, $clipped['clip_reduces_painted_bbox'] ?? null);
        $t->same(false, $clipped['clip_excludes_image'] ?? null);
        $t->same(1, $clipped['painted_invocation_count'] ?? null);
        $t->same(0, $clipped['clip_excluded_invocation_count'] ?? null);

        $outside = $entriesByName['OutsideClip'];
        $t->same([[0.0, 0.0, 20.0, 20.0]], $outside['invocation_bboxes']);
        $t->same([[100.0, 100.0, 115.0, 110.0]], $outside['invocation_clip_bboxes'] ?? null);
        $t->same([], $outside['invocation_visible_bboxes'] ?? null);
        $t->same(null, $outside['image_visible_bbox'] ?? null);
        $t->same(true, $outside['clip_applied'] ?? null);
        $t->same(true, $outside['clip_reduces_painted_bbox'] ?? null);
        $t->same(true, $outside['clip_excludes_image'] ?? null);
        $t->same(0, $outside['painted_invocation_count'] ?? null);
        $t->same(1, $outside['clip_excluded_invocation_count'] ?? null);

        $nested = $entriesByName['Nested Clipped'];
        $t->same(['Clip Form', 'Nested Clipped'], $nested['resource_path']);
        $t->same(7, $nested['parent_form_xobject_object']);
        $t->same([[640.0, 0.0, 0.0, 240.0, 260.0, 240.0]], $nested['invocation_matrices']);
        $t->same([[260.0, 240.0, 900.0, 480.0]], $nested['invocation_bboxes']);
        $t->same([[420.0, 280.0, 900.0, 440.0]], $nested['invocation_clip_bboxes'] ?? null);
        $t->same([[420.0, 280.0, 900.0, 440.0]], $nested['invocation_visible_bboxes'] ?? null);
        $t->same([420.0, 280.0, 900.0, 440.0], $nested['image_visible_bbox'] ?? null);
        $t->same(true, $nested['clip_reduces_painted_bbox'] ?? null);

        $t->same(['Before clipped images', 'After clipped images'], $extractor->extractTextLines($pdf));
        $t->same("Before clipped images\nAfter clipped images", $plainText);
        $t->true(!str_contains($plainText, 'Clipped Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Outside Clip Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Nested Clipped Image Payload Noise'));
    },
    'records image XObject dictionary metadata without leaking metadata streams into text' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before image metadata) Tj ET\n"
            . "q 18 0 0 9 72 690 cm /Meta#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After image metadata) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Image Metadata Payload Noise) Tj ET';
        $metadataPayload = '<x:xmpmeta>Image XObject Metadata Stream Noise</x:xmpmeta>';
        $compressedImagePayload = gzcompress($imagePayload);
        $compressedMetadataPayload = gzcompress($metadataPayload);
        if (!is_string($compressedImagePayload) || !is_string($compressedMetadataPayload)) {
            throw new RuntimeException('Unable to compress image metadata fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Meta#20Image 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Interpolate true /Intent /RelativeColorimetric /Name /Hero#20Image /StructParent 12 /StructParents 34 /Metadata 9 0 R /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadataPayload) . " >>\nstream\n{$compressedMetadataPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0];

        $t->same('Meta Image', $entry['resource_name']);
        $t->same(true, $entry['interpolate']);
        $t->same('RelativeColorimetric', $entry['rendering_intent']);
        $t->same('Hero Image', $entry['image_name']);
        $t->same(12, $entry['struct_parent']);
        $t->same(34, $entry['struct_parents']);
        $t->same([
            'object_number' => 9,
            'object_generation' => 0,
            'subtype' => 'XML',
            'filters' => ['FlateDecode'],
            'preview_only_filters' => [],
            'raw_length' => strlen($compressedMetadataPayload),
            'decoded_with_current_filters' => true,
            'decoded_length' => strlen($metadataPayload),
            'decoded_sha256' => hash('sha256', $metadataPayload),
            'payload_in_visible_text' => false,
            'review_only' => true,
        ], $entry['metadata_stream']);
        $t->same(['Before image metadata', 'After image metadata'], $extractor->extractTextLines($pdf));
        $t->same("Before image metadata\nAfter image metadata", $plainText);
        $t->true(!str_contains($plainText, 'Image Metadata Payload Noise'));
        $t->true(!str_contains($plainText, 'Image XObject Metadata Stream Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $metadataPayload));
    },
    'records alternate image XObject streams as review-only metadata without extra painted invocations' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before alternate image) Tj ET\n"
            . "q 24 0 0 12 72 690 cm /Alt#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After alternate image) Tj ET';
        $basePayload = 'BT /F1 12 Tf 72 720 Td (Base Alternate Image Payload Noise) Tj ET';
        $printPayload = 'BT /F1 12 Tf 72 720 Td (Print Alternate Image Payload Noise) Tj ET';
        $screenPayload = "\xff\x4fBT /F1 12 Tf 72 720 Td (Screen Alternate JPX Noise) Tj ET\xff\xd9";
        $baseCompressed = gzcompress($basePayload);
        $printCompressed = gzcompress($printPayload);
        if (!is_string($baseCompressed) || !is_string($printCompressed)) {
            throw new RuntimeException('Unable to compress alternate image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Alt#20Image 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Alternates [<< /Image 6 0 R /DefaultForPrinting true >> << /Image 7 0 R /DefaultForPrinting false >>] /Length " . strlen($baseCompressed) . " >>\nstream\n{$baseCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 8 /Height 4 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($printCompressed) . " >>\nstream\n{$printCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Length " . strlen($screenPayload) . " >>\nstream\n{$screenPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0];

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same('Alt Image', $entry['resource_name']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same(2, $entry['alternate_image_count']);
        $t->same(true, $entry['alternates_review_only']);
        $t->same([
            [
                'object_number' => 6,
                'object_generation' => 0,
                'default_for_printing' => true,
                'subtype' => 'Image',
                'width' => 8,
                'height' => 4,
                'color_space' => 'DeviceCMYK',
                'bits_per_component' => 8,
                'image_mask' => false,
                'filters' => ['FlateDecode'],
                'preview_only_filters' => [],
                'native_raster_decode' => true,
                'raw_length' => strlen($printCompressed),
                'decoded_with_current_filters' => true,
                'decoded_length' => strlen($printPayload),
                'decoded_sha256' => hash('sha256', $printPayload),
                'payload_in_visible_text' => false,
                'review_only' => true,
            ],
            [
                'object_number' => 7,
                'object_generation' => 0,
                'default_for_printing' => false,
                'subtype' => 'Image',
                'width' => 2,
                'height' => 1,
                'color_space' => 'DeviceRGB',
                'bits_per_component' => 8,
                'image_mask' => false,
                'filters' => ['JPXDecode'],
                'preview_only_filters' => ['JPXDecode'],
                'native_raster_decode' => false,
                'raw_length' => strlen($screenPayload),
                'decoded_with_current_filters' => false,
                'decoded_length' => null,
                'decoded_sha256' => null,
                'payload_in_visible_text' => false,
                'review_only' => true,
            ],
        ], $entry['alternate_images']);
        $t->same(['Before alternate image', 'After alternate image'], $extractor->extractTextLines($pdf));
        $t->same("Before alternate image\nAfter alternate image", $plainText);
        $t->true(!str_contains($plainText, 'Base Alternate Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Print Alternate Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Screen Alternate JPX Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $printPayload));
        $t->true(!str_contains($encoded, $screenPayload));
    },
    'records image XObject Mask streams and ColorKey arrays as review-only alpha metadata' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before masked images) Tj ET\n"
            . "q 24 0 0 12 72 690 cm /Masked#20Logo Do Q\n"
            . "q 24 0 0 12 108 690 cm /ColorKey#20Logo Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After masked images) Tj ET';
        $maskedPayload = 'BT /F1 12 Tf 72 720 Td (Masked Image Payload Noise) Tj ET';
        $maskPayload = 'BT /F1 12 Tf 72 720 Td (Explicit Mask Payload Noise) Tj ET';
        $colorKeyPayload = 'BT /F1 12 Tf 72 720 Td (ColorKey Image Payload Noise) Tj ET';
        $maskedCompressed = gzcompress($maskedPayload);
        $maskCompressed = gzcompress($maskPayload);
        $colorKeyCompressed = gzcompress($colorKeyPayload);
        if (!is_string($maskedCompressed) || !is_string($maskCompressed) || !is_string($colorKeyCompressed)) {
            throw new RuntimeException('Unable to compress mask fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Masked#20Logo 5 0 R /ColorKey#20Logo 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Mask 6 0 R /Length " . strlen($maskedCompressed) . " >>\nstream\n{$maskedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($maskCompressed) . " >>\nstream\n{$maskCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1 0 1] /Mask [0 0 120 140 200 255] /Length " . strlen($colorKeyCompressed) . " >>\nstream\n{$colorKeyCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $masked = $entriesByName['Masked Logo'];
        $t->same(6, $masked['mask_object']);
        $t->same([
            'type' => 'image_mask_stream',
            'object_number' => 6,
            'object_generation' => 0,
            'subtype' => 'Image',
            'width' => 2,
            'height' => 1,
            'color_space' => null,
            'bits_per_component' => 1,
            'image_mask' => true,
            'decode' => [
                'ranges' => [
                    ['min' => 1.0, 'max' => 0.0],
                ],
                'component_count' => 1,
                'expected_components' => 1,
                'valid_for_components' => true,
                'identity' => false,
                'inverted_components' => [0],
                'source' => 'explicit',
            ],
            'opacity_for_zero' => 1.0,
            'opacity_for_one' => 0.0,
            'filters' => ['FlateDecode'],
            'preview_only_filters' => [],
            'native_raster_decode' => true,
            'raw_length' => strlen($maskCompressed),
            'decoded_with_current_filters' => true,
            'decoded_length' => strlen($maskPayload),
            'decoded_sha256' => hash('sha256', $maskPayload),
            'payload_in_visible_text' => false,
            'review_only' => true,
        ], $masked['mask_review']);
        $t->same(false, $masked['mask_payload_in_visible_text']);
        $t->same(true, $masked['mask_review_only']);

        $colorKey = $entriesByName['ColorKey Logo'];
        $t->same(null, $colorKey['mask_object']);
        $t->same([
            'type' => 'color_key_mask_array',
            'ranges' => [
                ['min' => 0.0, 'max' => 0.0],
                ['min' => 120.0, 'max' => 140.0],
                ['min' => 200.0, 'max' => 255.0],
            ],
            'component_count' => 3,
            'expected_components' => 3,
            'valid_for_components' => true,
            'compares_before_decode' => true,
            'transparent_when_all_components_match' => true,
            'suppressed_by_soft_mask' => false,
            'review_only' => true,
        ], $colorKey['mask_review']);
        $t->same(false, $colorKey['mask_payload_in_visible_text']);
        $t->same(true, $colorKey['mask_review_only']);

        $t->same(['Before masked images', 'After masked images'], $extractor->extractTextLines($pdf));
        $t->same("Before masked images\nAfter masked images", $plainText);
        $t->true(!str_contains($plainText, 'Masked Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Explicit Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'ColorKey Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $maskedPayload));
        $t->true(!str_contains($encoded, $maskPayload));
        $t->true(!str_contains($encoded, $colorKeyPayload));
    },
    'records image XObject SMask stream metadata by exact generation without leaking alpha bytes' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before soft mask image) Tj ET\n"
            . "q 36 0 0 12 72 690 cm /Soft#20Mask#20Logo Do Q\n"
            . "q 12 0 0 12 120 690 cm /Opaque#20Logo Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After soft mask image) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Soft Mask Image Payload Noise) Tj ET';
        $currentSoftMaskPayload = 'BT /F1 12 Tf 72 720 Td (Current Soft Mask Payload Noise) Tj ET';
        $staleSoftMaskPayload = 'BT /F1 12 Tf 72 720 Td (Stale Soft Mask Payload Noise) Tj ET';
        $opaquePayload = 'BT /F1 12 Tf 72 720 Td (Opaque Image Payload Noise) Tj ET';
        $imageCompressed = gzcompress($imagePayload);
        $currentSoftMaskCompressed = gzcompress($currentSoftMaskPayload);
        $staleSoftMaskCompressed = gzcompress($staleSoftMaskPayload);
        $opaqueCompressed = gzcompress($opaquePayload);
        if (
            !is_string($imageCompressed)
            || !is_string($currentSoftMaskCompressed)
            || !is_string($staleSoftMaskCompressed)
            || !is_string($opaqueCompressed)
        ) {
            throw new RuntimeException('Unable to compress SMask fixture payloads.');
        }

        $currentSoftMaskHex = strtoupper(bin2hex($currentSoftMaskCompressed)) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Soft#20Mask#20Logo 5 0 R /Opaque#20Logo 8 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /SMask 6 1 R /Length " . strlen($imageCompressed) . " >>\nstream\n{$imageCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($staleSoftMaskCompressed) . " >>\nstream\n{$staleSoftMaskCompressed}\nendstream\nendobj\n"
            . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($currentSoftMaskHex) . " >>\nstream\n{$currentSoftMaskHex}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /SMask /None /Filter /FlateDecode /Length " . strlen($opaqueCompressed) . " >>\nstream\n{$opaqueCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $softMasked = $entriesByName['Soft Mask Logo'];
        $t->same(6, $softMasked['soft_mask_object']);
        $t->same(1, $softMasked['soft_mask_generation']);
        $t->same([
            'type' => 'soft_mask_stream',
            'present' => true,
            'object_number' => 6,
            'object_generation' => 1,
            'subtype' => 'Image',
            'width' => 3,
            'height' => 1,
            'color_space' => 'DeviceGray',
            'bits_per_component' => 8,
            'image_mask' => false,
            'decode' => [
                'ranges' => [
                    ['min' => 1.0, 'max' => 0.0],
                ],
                'component_count' => 1,
                'expected_components' => 1,
                'valid_for_components' => true,
                'identity' => false,
                'inverted_components' => [0],
                'source' => 'explicit',
            ],
            'opacity_for_zero' => 1.0,
            'opacity_for_max' => 0.0,
            'filters' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters' => [],
            'native_raster_decode' => true,
            'raw_length' => strlen($currentSoftMaskHex),
            'decoded_with_current_filters' => true,
            'decoded_length' => strlen($currentSoftMaskPayload),
            'decoded_sha256' => hash('sha256', $currentSoftMaskPayload),
            'payload_in_visible_text' => false,
            'review_only' => true,
        ], $softMasked['soft_mask_review']);
        $t->same(false, $softMasked['soft_mask_payload_in_visible_text']);
        $t->same(true, $softMasked['soft_mask_review_only']);

        $opaque = $entriesByName['Opaque Logo'];
        $t->same(null, $opaque['soft_mask_object']);
        $t->same(null, $opaque['soft_mask_generation']);
        $t->same([
            'type' => 'soft_mask_none',
            'present' => false,
            'object_number' => null,
            'object_generation' => null,
            'payload_in_visible_text' => false,
            'review_only' => true,
        ], $opaque['soft_mask_review']);
        $t->same(true, $opaque['soft_mask_review_only']);

        $t->same(['Before soft mask image', 'After soft mask image'], $extractor->extractTextLines($pdf));
        $t->same("Before soft mask image\nAfter soft mask image", $plainText);
        $t->true(!str_contains($plainText, 'Soft Mask Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Current Soft Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Stale Soft Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Opaque Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $currentSoftMaskPayload));
        $t->true(!str_contains($encoded, $staleSoftMaskPayload));
        $t->true(str_contains($encoded, hash('sha256', $currentSoftMaskPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $staleSoftMaskPayload)));
    },
    'resolves image XObject auxiliary streams by exact object generation' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before auxiliary generation image) Tj ET\n"
            . "q 28 0 0 14 72 690 cm /Aux#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After auxiliary generation image) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Auxiliary Generation Image Payload Noise) Tj ET';
        $maskCurrentPayload = 'BT /F1 12 Tf 72 720 Td (Current Generation Mask Payload Noise) Tj ET';
        $maskStalePayload = 'BT /F1 12 Tf 72 720 Td (Stale Generation Mask Payload Noise) Tj ET';
        $metadataCurrentPayload = '<x:xmpmeta>Current Generation Image Metadata</x:xmpmeta>';
        $metadataStalePayload = '<x:xmpmeta>Stale Generation Image Metadata</x:xmpmeta>';
        $alternateCurrentPayload = 'BT /F1 12 Tf 72 720 Td (Current Generation Alternate Image Noise) Tj ET';
        $alternateStalePayload = 'BT /F1 12 Tf 72 720 Td (Stale Generation Alternate Image Noise) Tj ET';
        $imageCompressed = gzcompress($imagePayload);
        $maskCurrentCompressed = gzcompress($maskCurrentPayload);
        $maskStaleCompressed = gzcompress($maskStalePayload);
        $metadataCurrentCompressed = gzcompress($metadataCurrentPayload);
        $metadataStaleCompressed = gzcompress($metadataStalePayload);
        $alternateCurrentCompressed = gzcompress($alternateCurrentPayload);
        $alternateStaleCompressed = gzcompress($alternateStalePayload);
        if (
            !is_string($imageCompressed)
            || !is_string($maskCurrentCompressed)
            || !is_string($maskStaleCompressed)
            || !is_string($metadataCurrentCompressed)
            || !is_string($metadataStaleCompressed)
            || !is_string($alternateCurrentCompressed)
            || !is_string($alternateStaleCompressed)
        ) {
            throw new RuntimeException('Unable to compress auxiliary generation image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 9 1 R /Names << /AuxDecoys [6 1 R 12 1 R] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Aux#20Image 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Mask 6 0 R /Metadata 9 0 R /Alternates [<< /Image 12 0 R /DefaultForPrinting true >>] /Length " . strlen($imageCompressed) . " >>\nstream\n{$imageCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($maskCurrentCompressed) . " >>\nstream\n{$maskCurrentCompressed}\nendstream\nendobj\n"
            . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($maskStaleCompressed) . " >>\nstream\n{$maskStaleCompressed}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataCurrentCompressed) . " >>\nstream\n{$metadataCurrentCompressed}\nendstream\nendobj\n"
            . "9 1 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStaleCompressed) . " >>\nstream\n{$metadataStaleCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "12 0 obj\n<< /Type /XObject /Subtype /Image /Width 8 /Height 4 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateCurrentCompressed) . " >>\nstream\n{$alternateCurrentCompressed}\nendstream\nendobj\n"
            . "12 1 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateStaleCompressed) . " >>\nstream\n{$alternateStaleCompressed}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0];

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same('Aux Image', $entry['resource_name']);
        $t->same(5, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same(6, $entry['mask_object']);
        $t->same(4, $entry['mask_review']['width']);
        $t->same(2, $entry['mask_review']['height']);
        $t->same(hash('sha256', $maskCurrentPayload), $entry['mask_review']['decoded_sha256']);
        $t->true(!str_contains(json_encode($entry['mask_review'], JSON_UNESCAPED_SLASHES) ?: '', hash('sha256', $maskStalePayload)));
        $t->same(9, $entry['metadata_stream']['object_number']);
        $t->same(strlen($metadataCurrentPayload), $entry['metadata_stream']['decoded_length']);
        $t->same(hash('sha256', $metadataCurrentPayload), $entry['metadata_stream']['decoded_sha256']);
        $t->same(1, $entry['alternate_image_count']);
        $t->same(12, $entry['alternate_images'][0]['object_number']);
        $t->same(8, $entry['alternate_images'][0]['width']);
        $t->same(4, $entry['alternate_images'][0]['height']);
        $t->same('DeviceCMYK', $entry['alternate_images'][0]['color_space']);
        $t->same(hash('sha256', $alternateCurrentPayload), $entry['alternate_images'][0]['decoded_sha256']);
        $t->same(['Before auxiliary generation image', 'After auxiliary generation image'], $extractor->extractTextLines($pdf));
        $t->same("Before auxiliary generation image\nAfter auxiliary generation image", $plainText);
        $t->true(!str_contains($plainText, 'Auxiliary Generation Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Current Generation Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Stale Generation Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Current Generation Image Metadata'));
        $t->true(!str_contains($plainText, 'Stale Generation Image Metadata'));
        $t->true(!str_contains($plainText, 'Current Generation Alternate Image Noise'));
        $t->true(!str_contains($plainText, 'Stale Generation Alternate Image Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encoded, hash('sha256', $maskCurrentPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $maskStalePayload)));
        $t->true(str_contains($encoded, hash('sha256', $metadataCurrentPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $metadataStalePayload)));
        $t->true(str_contains($encoded, hash('sha256', $alternateCurrentPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $alternateStalePayload)));
    },
    'exposes exact object generations for image XObject auxiliary review rows' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before auxiliary review generations) Tj ET\n"
            . "q 28 0 0 14 72 690 cm /Aux#20Generation#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After auxiliary review generations) Tj ET';
        $imagePayload = 'BT /F1 12 Tf 72 720 Td (Auxiliary Review Generation Image Payload Noise) Tj ET';
        $maskCurrentPayload = 'BT /F1 12 Tf 72 720 Td (Current Review Generation Mask Payload Noise) Tj ET';
        $maskStalePayload = 'BT /F1 12 Tf 72 720 Td (Stale Review Generation Mask Payload Noise) Tj ET';
        $metadataCurrentPayload = '<x:xmpmeta>Current Review Generation Image Metadata</x:xmpmeta>';
        $metadataStalePayload = '<x:xmpmeta>Stale Review Generation Image Metadata</x:xmpmeta>';
        $alternateCurrentPayload = 'BT /F1 12 Tf 72 720 Td (Current Review Generation Alternate Image Noise) Tj ET';
        $alternateStalePayload = 'BT /F1 12 Tf 72 720 Td (Stale Review Generation Alternate Image Noise) Tj ET';
        $screenAlternatePayload = 'BT /F1 12 Tf 72 720 Td (Screen Review Generation Alternate Noise) Tj ET';
        $imageCompressed = gzcompress($imagePayload);
        $maskCurrentCompressed = gzcompress($maskCurrentPayload);
        $maskStaleCompressed = gzcompress($maskStalePayload);
        $metadataCurrentCompressed = gzcompress($metadataCurrentPayload);
        $metadataStaleCompressed = gzcompress($metadataStalePayload);
        $alternateCurrentCompressed = gzcompress($alternateCurrentPayload);
        $alternateStaleCompressed = gzcompress($alternateStalePayload);
        $screenAlternateCompressed = gzcompress($screenAlternatePayload);
        if (
            !is_string($imageCompressed)
            || !is_string($maskCurrentCompressed)
            || !is_string($maskStaleCompressed)
            || !is_string($metadataCurrentCompressed)
            || !is_string($metadataStaleCompressed)
            || !is_string($alternateCurrentCompressed)
            || !is_string($alternateStaleCompressed)
            || !is_string($screenAlternateCompressed)
        ) {
            throw new RuntimeException('Unable to compress auxiliary generation review fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 9 0 R /Names << /AuxDecoys [6 0 R 9 0 R 12 0 R] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Aux#20Generation#20Image 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Mask 6 1 R /Metadata 9 1 R /Alternates [<< /Image 12 1 R /DefaultForPrinting true >> << /Image 13 0 R /DefaultForPrinting false >>] /Length " . strlen($imageCompressed) . " >>\nstream\n{$imageCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($maskStaleCompressed) . " >>\nstream\n{$maskStaleCompressed}\nendstream\nendobj\n"
            . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($maskCurrentCompressed) . " >>\nstream\n{$maskCurrentCompressed}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStaleCompressed) . " >>\nstream\n{$metadataStaleCompressed}\nendstream\nendobj\n"
            . "9 1 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataCurrentCompressed) . " >>\nstream\n{$metadataCurrentCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "12 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateStaleCompressed) . " >>\nstream\n{$alternateStaleCompressed}\nendstream\nendobj\n"
            . "12 1 obj\n<< /Type /XObject /Subtype /Image /Width 8 /Height 4 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateCurrentCompressed) . " >>\nstream\n{$alternateCurrentCompressed}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($screenAlternateCompressed) . " >>\nstream\n{$screenAlternateCompressed}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0];

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same('Aux Generation Image', $entry['resource_name']);
        $t->same(6, $entry['mask_object']);
        $t->same(1, $entry['mask_generation']);
        $t->same(1, $entry['mask_review']['object_generation']);
        $t->same(hash('sha256', $maskCurrentPayload), $entry['mask_review']['decoded_sha256']);
        $t->same(9, $entry['metadata_stream']['object_number']);
        $t->same(1, $entry['metadata_stream']['object_generation']);
        $t->same(hash('sha256', $metadataCurrentPayload), $entry['metadata_stream']['decoded_sha256']);
        $t->same(2, $entry['alternate_image_count']);
        $t->same(12, $entry['alternate_images'][0]['object_number']);
        $t->same(1, $entry['alternate_images'][0]['object_generation']);
        $t->same(hash('sha256', $alternateCurrentPayload), $entry['alternate_images'][0]['decoded_sha256']);
        $t->same(13, $entry['alternate_images'][1]['object_number']);
        $t->same(0, $entry['alternate_images'][1]['object_generation']);
        $t->same(hash('sha256', $screenAlternatePayload), $entry['alternate_images'][1]['decoded_sha256']);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, hash('sha256', $maskStalePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $metadataStalePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $alternateStalePayload)));
        $t->true(!str_contains($plainText, 'Auxiliary Review Generation Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Current Review Generation Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Stale Review Generation Mask Payload Noise'));
        $t->true(!str_contains($plainText, 'Current Review Generation Image Metadata'));
        $t->true(!str_contains($plainText, 'Stale Review Generation Image Metadata'));
        $t->true(!str_contains($plainText, 'Current Review Generation Alternate Image Noise'));
        $t->true(!str_contains($plainText, 'Stale Review Generation Alternate Image Noise'));
        $t->true(!str_contains($plainText, 'Screen Review Generation Alternate Noise'));
        $t->same(['Before auxiliary review generations', 'After auxiliary review generations'], $extractor->extractTextLines($pdf));
    },
    'resolves image XObject resource references by exact object generation' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before exact generation image) Tj ET\n"
            . "q 30 0 0 10 72 690 cm /Exact#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After exact generation image) Tj ET';
        $currentPayload = 'BT /F1 12 Tf 72 720 Td (Current Generation Image Payload Noise) Tj ET';
        $stalePayload = 'BT /F1 12 Tf 72 720 Td (Stale Generation Image Payload Noise) Tj ET';
        $currentCompressed = gzcompress($currentPayload);
        $staleCompressed = gzcompress($stalePayload);
        if (!is_string($currentCompressed) || !is_string($staleCompressed)) {
            throw new RuntimeException('Unable to compress exact-generation image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Exact#20Image 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
            . "5 1 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0];

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same('Exact Image', $entry['resource_name']);
        $t->same(5, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same(3, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same('DeviceRGB', $entry['color_space']);
        $t->same(strlen($currentCompressed), $entry['raw_length']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($currentPayload), $entry['decoded_length']);
        $t->same(hash('sha256', $currentPayload), $entry['decoded_sha256']);
        $t->same([[30.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $entry['invocation_matrices']);
        $t->same([72.0, 690.0, 102.0, 700.0], $entry['image_unit_bbox']);
        $t->same(['Before exact generation image', 'After exact generation image'], $extractor->extractTextLines($pdf));
        $t->same("Before exact generation image\nAfter exact generation image", $plainText);
        $t->true(!str_contains($plainText, 'Current Generation Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Stale Generation Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $currentPayload));
        $t->true(!str_contains($encoded, $stalePayload));
        $t->true(str_contains($encoded, hash('sha256', $currentPayload)));
        $t->true(!str_contains($encoded, hash('sha256', $stalePayload)));
    },
    'resolves named ColorSpace resources for image XObject and Form XObject alpha review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before named color image) Tj ET\n"
            . "q 24 0 0 12 72 690 cm /Named#20Image Do Q\n"
            . "q 20 0 0 10 110 690 cm /Named#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After named color image) Tj ET';
        $formContent = 'q 10 0 0 5 2 2 cm /Nested#20Named Do Q';
        $pageImagePayload = 'BT /F1 12 Tf 72 720 Td (Page Named ColorSpace Image Payload Noise) Tj ET';
        $nestedImagePayload = 'BT /F1 12 Tf 72 720 Td (Nested Named ColorSpace Image Payload Noise) Tj ET';
        $pageCompressed = gzcompress($pageImagePayload);
        $nestedCompressed = gzcompress($nestedImagePayload);
        if (!is_string($pageCompressed) || !is_string($nestedCompressed)) {
            throw new RuntimeException('Unable to compress named ColorSpace image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ColorSpace << /CSHero 12 0 R >> /XObject << /Named#20Image 5 0 R /Named#20Form 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /CSHero /BitsPerComponent 8 /Filter /FlateDecode /Mask [0 0 120 140 200 255] /Length " . strlen($pageCompressed) . " >>\nstream\n{$pageCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 10] /Resources << /ColorSpace << /CSNested /DeviceCMYK >> /XObject << /Nested#20Named 8 0 R >> /Font << /F1 10 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /CSNested /BitsPerComponent 8 /Filter /FlateDecode /Mask [0 0 40 80 120 160 200 255] /Length " . strlen($nestedCompressed) . " >>\nstream\n{$nestedCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "12 0 obj\n[/DeviceRGB]\nendobj\n"
            . "12 1 obj\n[/DeviceGray]\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $pageImage = $entriesByName['Named Image'];
        $t->same('DeviceRGB', $pageImage['color_space']);
        $t->same('CSHero', $pageImage['color_space_resource_name']);
        $t->same(true, $pageImage['color_space_resolved_from_resources']);
        $t->same('Resources.ColorSpace', $pageImage['color_space_resource_source']);
        $t->same(3, $pageImage['mask_review']['expected_components']);
        $t->same(true, $pageImage['mask_review']['valid_for_components']);
        $t->same(hash('sha256', $pageImagePayload), $pageImage['decoded_sha256']);
        $t->same([72.0, 690.0, 96.0, 702.0], $pageImage['image_unit_bbox']);

        $nested = $entriesByName['Nested Named'];
        $t->same(['Named Form', 'Nested Named'], $nested['resource_path']);
        $t->same(6, $nested['parent_form_xobject_object']);
        $t->same('DeviceCMYK', $nested['color_space']);
        $t->same('CSNested', $nested['color_space_resource_name']);
        $t->same(true, $nested['color_space_resolved_from_resources']);
        $t->same('Resources.ColorSpace', $nested['color_space_resource_source']);
        $t->same(4, $nested['mask_review']['expected_components']);
        $t->same(true, $nested['mask_review']['valid_for_components']);
        $t->same(hash('sha256', $nestedImagePayload), $nested['decoded_sha256']);
        $t->same([[200.0, 0.0, 0.0, 50.0, 150.0, 710.0]], $nested['invocation_matrices']);
        $t->same([150.0, 710.0, 350.0, 760.0], $nested['image_unit_bbox']);

        $t->same(['Before named color image', 'After named color image'], $extractor->extractTextLines($pdf));
        $t->same("Before named color image\nAfter named color image", $plainText);
        $t->true(!str_contains($plainText, 'Page Named ColorSpace Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Nested Named ColorSpace Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encoded, 'CSHero'));
        $t->true(str_contains($encoded, 'CSNested'));
        $t->true(!str_contains($encoded, $pageImagePayload));
        $t->true(!str_contains($encoded, $nestedImagePayload));
    },
    'ignores nested private XObject resource dictionary entries before image review and form text expansion' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before nested private XObject) Tj ET\n"
            . "q 20 0 0 10 72 690 cm /Hero#20Image Do Q\n"
            . "q 10 0 0 10 108 690 cm /Private#20Image Do Q\n"
            . "q 10 0 0 10 132 690 cm /Private#20Form Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After nested private XObject) Tj ET';
        $heroPayload = 'BT /F1 12 Tf 72 720 Td (Hero Image Payload Noise) Tj ET';
        $privateImagePayload = 'BT /F1 12 Tf 72 720 Td (Private Image Payload Noise) Tj ET';
        $privateFormPayload = 'BT /F1 12 Tf 72 720 Td (Private Form Text Leak) Tj ET';
        $heroCompressed = gzcompress($heroPayload);
        $privateImageCompressed = gzcompress($privateImagePayload);
        if (!is_string($heroCompressed) || !is_string($privateImageCompressed)) {
            throw new RuntimeException('Unable to compress nested private XObject fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Hero#20Image 5 0 R /Private << /Private#20Image 6 0 R /Private#20Form 7 0 R >> >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($heroCompressed) . " >>\nstream\n{$heroCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 9 /Height 9 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($privateImageCompressed) . " >>\nstream\n{$privateImageCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 40 20] /Resources << /Font << /F1 10 0 R >> >> /Length " . strlen($privateFormPayload) . " >>\nstream\n{$privateFormPayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same('Hero Image', $review['entries'][0]['resource_name']);
        $t->same(['Hero Image'], $review['entries'][0]['resource_path']);
        $t->same(5, $review['entries'][0]['object_number']);
        $t->same([[20.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $review['entries'][0]['invocation_matrices']);
        $t->same([72.0, 690.0, 92.0, 700.0], $review['entries'][0]['image_unit_bbox']);
        $t->same(true, $review['entries'][0]['decoded_with_current_filters']);
        $t->same(hash('sha256', $heroPayload), $review['entries'][0]['decoded_sha256']);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Private Image'));
        $t->true(!str_contains($encoded, hash('sha256', $privateImagePayload)));
        $t->true(!str_contains($encoded, $privateImagePayload));
        $t->true(!str_contains($encoded, 'Private Form'));

        $t->same(['Before nested private XObject', 'After nested private XObject'], $extractor->extractTextLines($pdf));
        $t->same("Before nested private XObject\nAfter nested private XObject", $plainText);
        $t->true(!str_contains($plainText, 'Hero Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Private Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Private Form Text Leak'));
    },
    'keeps optional-content Image XObject invocations generation-specific' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before generation layers) Tj ET\n"
            . "q 12 0 0 12 72 690 cm /Hidden#20Generation Do Q\n"
            . "q 12 0 0 12 96 690 cm /Visible#20Generation Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After generation layers) Tj ET';
        $hiddenPayload = 'BT /F1 12 Tf 72 720 Td (Hidden OCG Generation Image Noise) Tj ET';
        $visiblePayload = 'BT /F1 12 Tf 72 720 Td (Visible OCG Generation Image Noise) Tj ET';
        $hiddenCompressed = gzcompress($hiddenPayload);
        $visibleCompressed = gzcompress($visiblePayload);
        if (!is_string($hiddenCompressed) || !is_string($visibleCompressed)) {
            throw new RuntimeException('Unable to compress optional-content generation fixture payloads.');
        }

        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [21 0 R 21 1 R] /D << /BaseState /OFF /ON [21 1 R] /Order [21 0 R 21 1 R] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /XObject << /Hidden#20Generation 5 0 R /Visible#20Generation 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /OC 21 0 R /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($hiddenCompressed) . " >>\nstream\n{$hiddenCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /OC 21 1 R /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (Hidden Generation Layer) >>\nendobj\n"
            . "21 1 obj\n<< /Type /OCG /Name (Visible Generation Layer) >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(2, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(1, $review['uninvoked_image_xobject_count']);

        $hidden = $entriesByName['Hidden Generation'];
        $t->same(false, $hidden['optional_content_visible']);
        $t->same(false, $hidden['invoked']);
        $t->same(0, $hidden['invocation_count']);
        $t->same([], $hidden['invocation_matrices']);
        $t->same(true, $hidden['decoded_with_current_filters']);
        $t->same(hash('sha256', $hiddenPayload), $hidden['decoded_sha256']);

        $visible = $entriesByName['Visible Generation'];
        $t->same(true, $visible['optional_content_visible']);
        $t->same(true, $visible['invoked']);
        $t->same(1, $visible['invocation_count']);
        $t->same([[12.0, 0.0, 0.0, 12.0, 96.0, 690.0]], $visible['invocation_matrices']);
        $t->same([96.0, 690.0, 108.0, 702.0], $visible['image_unit_bbox']);
        $t->same(true, $visible['decoded_with_current_filters']);
        $t->same(hash('sha256', $visiblePayload), $visible['decoded_sha256']);

        $t->same(['Before generation layers', 'After generation layers'], $extractor->extractTextLines($pdf));
        $t->same("Before generation layers\nAfter generation layers", $plainText);
        $t->true(!str_contains($plainText, 'Hidden OCG Generation Image Noise'));
        $t->true(!str_contains($plainText, 'Visible OCG Generation Image Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($encoded, hash('sha256', $hiddenPayload)));
        $t->true(str_contains($encoded, hash('sha256', $visiblePayload)));
        $t->true(!str_contains($encoded, $hiddenPayload));
        $t->true(!str_contains($encoded, $visiblePayload));
    },
    'records ExtGState transparency at image XObject invocation boundaries' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before ExtGState images) Tj ET\n"
            . "q /Alpha#20State gs 20 0 0 10 72 690 cm /Alpha#20Image Do Q\n"
            . "q /Soft#20Mask#20State gs 12 0 0 12 110 690 cm /Soft#20Image Do Q\n"
            . "q /Private#20State gs 8 0 0 8 140 690 cm /PlainImage Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After ExtGState images) Tj ET';
        $alphaPayload = 'BT /F1 12 Tf 72 720 Td (Alpha ExtGState Image Noise) Tj ET';
        $softPayload = 'BT /F1 12 Tf 72 720 Td (Soft Mask ExtGState Image Noise) Tj ET';
        $plainPayload = 'BT /F1 12 Tf 72 720 Td (Plain ExtGState Image Noise) Tj ET';
        $alphaCompressed = gzcompress($alphaPayload);
        $softCompressed = gzcompress($softPayload);
        $plainCompressed = gzcompress($plainPayload);
        if (!is_string($alphaCompressed) || !is_string($softCompressed) || !is_string($plainCompressed)) {
            throw new RuntimeException('Unable to compress ExtGState image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Alpha#20State 20 0 R /Soft#20Mask#20State 21 0 R /Private << /Private#20State 24 0 R >> >> /XObject << /Alpha#20Image 5 0 R /Soft#20Image 6 0 R /PlainImage 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alphaCompressed) . " >>\nstream\n{$alphaCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($softCompressed) . " >>\nstream\n{$softCompressed}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($plainCompressed) . " >>\nstream\n{$plainCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /ExtGState /CA 0.75 /ca 0.42 /BM /Multiply /AIS true >>\nendobj\n"
            . "21 0 obj\n<< /Type /ExtGState /ca 0.5 /BM [/Screen /Normal] /SMask 22 0 R >>\nendobj\n"
            . "22 0 obj\n<< /Type /Mask /S /Luminosity /G 23 0 R /TR /Identity >>\nendobj\n"
            . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Length 0 >>\nstream\n\nendstream\nendobj\n"
            . "24 0 obj\n<< /Type /ExtGState /ca 0.1 /BM /Difference >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(3, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);

        $alpha = $entriesByName['Alpha Image'];
        $t->same([['Alpha State']], array_column($alpha['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same(0.75, $alpha['invocation_graphics_states'][0]['stroking_alpha']);
        $t->same(0.42, $alpha['invocation_graphics_states'][0]['nonstroking_alpha']);
        $t->same(true, $alpha['invocation_graphics_states'][0]['alpha_source']);
        $t->same(['Multiply'], $alpha['invocation_graphics_states'][0]['blend_modes']);
        $t->same(null, $alpha['invocation_graphics_states'][0]['soft_mask']);
        $t->same(true, $alpha['graphics_state_review_only']);
        $t->same(false, $alpha['payload_in_visible_text']);

        $soft = $entriesByName['Soft Image'];
        $t->same([['Soft Mask State']], array_column($soft['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same(null, $soft['invocation_graphics_states'][0]['stroking_alpha']);
        $t->same(0.5, $soft['invocation_graphics_states'][0]['nonstroking_alpha']);
        $t->same(['Screen', 'Normal'], $soft['invocation_graphics_states'][0]['blend_modes']);
        $t->same([
            'type' => 'graphics_state_soft_mask',
            'object_number' => 22,
            'object_generation' => 0,
            'subtype' => 'Luminosity',
            'group_object' => 23,
            'group_generation' => 0,
            'transfer_function' => 'Identity',
            'transfer_function_object' => null,
            'transfer_function_generation' => null,
            'payload_in_visible_text' => false,
            'review_only' => true,
        ], $soft['invocation_graphics_states'][0]['soft_mask']);

        $plain = $entriesByName['PlainImage'];
        $t->same([], $plain['invocation_graphics_states']);
        $t->same(false, $plain['graphics_state_review_only']);
        $t->same(true, $plain['decoded_with_current_filters']);
        $t->same(hash('sha256', $plainPayload), $plain['decoded_sha256']);

        $t->same(['Before ExtGState images', 'After ExtGState images'], $extractor->extractTextLines($pdf));
        $t->same("Before ExtGState images\nAfter ExtGState images", $plainText);
        $t->true(!str_contains($plainText, 'Alpha ExtGState Image Noise'));
        $t->true(!str_contains($plainText, 'Soft Mask ExtGState Image Noise'));
        $t->true(!str_contains($plainText, 'Plain ExtGState Image Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Private State'));
        $t->true(!str_contains($encoded, 'Difference'));
        $t->true(!str_contains($encoded, $alphaPayload));
        $t->true(!str_contains($encoded, $softPayload));
        $t->true(!str_contains($encoded, $plainPayload));
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
