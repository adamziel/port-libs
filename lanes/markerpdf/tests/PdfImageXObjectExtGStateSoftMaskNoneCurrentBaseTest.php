<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'clears ExtGState soft mask with SMask None before Image XObject review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before ExtGState SMask None images) Tj ET\n"
            . "q /Soft#20Mask#20State gs /No#20Soft#20Mask#20State gs 20 0 0 10 72 690 cm /Cleared#20Soft#20Image Do Q\n"
            . "q /Soft#20Mask#20State gs 12 0 0 6 120 690 cm /Masked#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After ExtGState SMask None images) Tj ET';
        $clearedPayload = 'BT /F1 12 Tf 72 720 Td (Cleared ExtGState SMask None Image Noise) Tj ET';
        $maskedPayload = 'BT /F1 12 Tf 72 720 Td (Masked ExtGState SMask Image Noise) Tj ET';
        $clearedCompressed = gzcompress($clearedPayload);
        $maskedCompressed = gzcompress($maskedPayload);
        if (!is_string($clearedCompressed) || !is_string($maskedCompressed)) {
            throw new RuntimeException('Unable to compress ExtGState SMask None image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Soft#20Mask#20State 20 0 R /No#20Soft#20Mask#20State 21 0 R >> /XObject << /Cleared#20Soft#20Image 5 0 R /Masked#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($clearedCompressed) . " >>\nstream\n{$clearedCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($maskedCompressed) . " >>\nstream\n{$maskedCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /ExtGState /ca 0.8 /BM /Screen /SMask 22 0 R >>\nendobj\n"
            . "21 0 obj\n<< /Type /ExtGState /ca 0.65 /BM /Normal /SMask /None >>\nendobj\n"
            . "22 0 obj\n<< /Type /Mask /S /Luminosity /G 23 0 R /TR /Identity >>\nendobj\n"
            . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $cleared = $entriesByName['Cleared Soft Image'];
        $t->same(true, $cleared['invoked']);
        $t->same(1, $cleared['invocation_count']);
        $t->same([['Soft Mask State', 'No Soft Mask State']], array_column($cleared['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same(['Soft Mask State', 'No Soft Mask State'], array_column($cleared['invocation_graphics_states'][0]['applied_extgstates'], 'resource_name'));
        $t->same(0.65, $cleared['invocation_graphics_states'][0]['nonstroking_alpha']);
        $t->same(['Normal'], $cleared['invocation_graphics_states'][0]['blend_modes']);
        $t->same(null, $cleared['invocation_graphics_states'][0]['soft_mask']);
        $t->same(false, $cleared['payload_in_visible_text']);
        $t->same(hash('sha256', $clearedPayload), $cleared['decoded_sha256']);

        $masked = $entriesByName['Masked Image'];
        $t->same([['Soft Mask State']], array_column($masked['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same(0.8, $masked['invocation_graphics_states'][0]['nonstroking_alpha']);
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
        ], $masked['invocation_graphics_states'][0]['soft_mask']);
        $t->same(false, $masked['payload_in_visible_text']);
        $t->same(hash('sha256', $maskedPayload), $masked['decoded_sha256']);

        $t->same(['Before ExtGState SMask None images', 'After ExtGState SMask None images'], $extractor->extractTextLines($pdf));
        $t->same("Before ExtGState SMask None images\nAfter ExtGState SMask None images", $plainText);
        $t->true(!str_contains($plainText, 'Cleared ExtGState SMask None Image Noise'));
        $t->true(!str_contains($plainText, 'Masked ExtGState SMask Image Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $clearedPayload));
        $t->true(!str_contains($encoded, $maskedPayload));
    },
];
