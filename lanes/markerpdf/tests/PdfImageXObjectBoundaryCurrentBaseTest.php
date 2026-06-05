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
