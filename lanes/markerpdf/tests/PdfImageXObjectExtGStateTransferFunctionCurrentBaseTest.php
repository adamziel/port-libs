<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'records ExtGState transfer functions before Image XObject review' => static function (TestRunner $t): void {
        $pageContent = "BT /F1 12 Tf 72 720 Td (Before transfer function images) Tj ET\n"
            . "q /TR2#20Identity gs 24 0 0 12 72 690 cm /TR2#20Identity#20Image Do Q\n"
            . "q /TR#20Function gs 16 0 0 8 108 690 cm /TR#20Function#20Image Do Q\n"
            . 'BT /F1 12 Tf 72 660 Td (After transfer function images) Tj ET';
        $identityPayload = 'BT /F1 12 Tf 72 720 Td (TR2 Identity Image Payload Noise) Tj ET';
        $functionPayload = 'BT /F1 12 Tf 72 720 Td (TR Function Image Payload Noise) Tj ET';
        $identityCompressed = gzcompress($identityPayload);
        $functionCompressed = gzcompress($functionPayload);
        if (!is_string($identityCompressed) || !is_string($functionCompressed)) {
            throw new RuntimeException('Unable to compress ExtGState transfer-function image fixture payloads.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /TR2#20Identity 20 0 R /TR#20Function 21 0 R >> /XObject << /TR2#20Identity#20Image 5 0 R /TR#20Function#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($identityCompressed) . " >>\nstream\n{$identityCompressed}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($functionCompressed) . " >>\nstream\n{$functionCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "20 0 obj\n<< /Type /ExtGState /TR2 /Identity /ca 0.9 /BM /Normal >>\nendobj\n"
            . "21 0 obj\n<< /Type /ExtGState /TR 22 0 R /ca 0.85 /BM /Multiply >>\nendobj\n"
            . "22 0 obj\n<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0] /C1 [1] /N 1 >>\nendobj\n%%EOF";

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
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $identity = $entriesByName['TR2 Identity Image'];
        $identityState = $identity['invocation_graphics_states'][0] ?? [];
        $identityTransfer = $identityState['transfer_functions'][0] ?? [];
        $t->same(true, $identity['invoked']);
        $t->same(1, $identity['invocation_count']);
        $t->same([['TR2 Identity']], array_column($identity['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same('TR2 Identity', $identityState['applied_extgstates'][0]['resource_name'] ?? null);
        $t->same(0.9, $identityState['nonstroking_alpha'] ?? null);
        $t->same(['Normal'], $identityState['blend_modes'] ?? null);
        $t->same('TR2', $identityTransfer['name'] ?? null);
        $t->same('name', $identityTransfer['value_type'] ?? null);
        $t->same('Identity', $identityTransfer['transfer_function'] ?? null);
        $t->same(null, $identityTransfer['transfer_function_object'] ?? null);
        $t->same(null, $identityTransfer['transfer_function_generation'] ?? null);
        $t->same(null, $identityTransfer['function_type'] ?? null);
        $t->same(null, $identityTransfer['domain'] ?? null);
        $t->same(null, $identityTransfer['range'] ?? null);
        $t->same(true, $identityTransfer['resolved'] ?? null);
        $t->same(false, $identityTransfer['payload_in_visible_text'] ?? null);
        $t->same(true, $identityTransfer['review_only'] ?? null);
        $t->same(false, $identity['payload_in_visible_text']);
        $t->same(hash('sha256', $identityPayload), $identity['decoded_sha256']);

        $function = $entriesByName['TR Function Image'];
        $functionState = $function['invocation_graphics_states'][0] ?? [];
        $functionTransfer = $functionState['transfer_functions'][0] ?? [];
        $t->same(true, $function['invoked']);
        $t->same(1, $function['invocation_count']);
        $t->same([['TR Function']], array_column($function['invocation_graphics_states'], 'ext_gstate_resources'));
        $t->same('TR Function', $functionState['applied_extgstates'][0]['resource_name'] ?? null);
        $t->same(0.85, $functionState['nonstroking_alpha'] ?? null);
        $t->same(['Multiply'], $functionState['blend_modes'] ?? null);
        $t->same('TR', $functionTransfer['name'] ?? null);
        $t->same('reference', $functionTransfer['value_type'] ?? null);
        $t->same(null, $functionTransfer['transfer_function'] ?? null);
        $t->same(22, $functionTransfer['transfer_function_object'] ?? null);
        $t->same(0, $functionTransfer['transfer_function_generation'] ?? null);
        $t->same(2, $functionTransfer['function_type'] ?? null);
        $t->same([0.0, 1.0], $functionTransfer['domain'] ?? null);
        $t->same([0.0, 1.0], $functionTransfer['range'] ?? null);
        $t->same(true, $functionTransfer['resolved'] ?? null);
        $t->same(false, $functionTransfer['payload_in_visible_text'] ?? null);
        $t->same(true, $functionTransfer['review_only'] ?? null);
        $t->same(false, $function['payload_in_visible_text']);
        $t->same(hash('sha256', $functionPayload), $function['decoded_sha256']);

        $t->same(['Before transfer function images', 'After transfer function images'], $extractor->extractTextLines($pdf));
        $t->same("Before transfer function images\nAfter transfer function images", $plainText);
        $t->true(!str_contains($plainText, 'TR2 Identity Image Payload Noise'));
        $t->true(!str_contains($plainText, 'TR Function Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $identityPayload));
        $t->true(!str_contains($encoded, $functionPayload));
    },
];
