<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_decode_native_raster_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before decode native raster boundary) Tj ET\n"
        . "q 20 0 0 10 72 690 cm /Valid#20Decode#20Image Do Q\n"
        . "q 20 0 0 10 108 690 cm /Mismatch#20Decode#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After decode native raster boundary) Tj ET';
    $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Decode Native Raster Payload Noise) Tj ET';
    $mismatchPayload = 'BT /F1 12 Tf 72 720 Td (Mismatch Decode Native Raster Payload Noise) Tj ET';
    $validCompressed = gzcompress($validPayload);
    $mismatchCompressed = gzcompress($mismatchPayload);
    if (!is_string($validCompressed) || !is_string($mismatchCompressed)) {
        throw new RuntimeException('Unable to compress Image XObject Decode native-raster fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Valid#20Decode#20Image 5 0 R /Mismatch#20Decode#20Image 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1 0 1] /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /Length " . strlen($mismatchCompressed) . " >>\nstream\n{$mismatchCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $validPayload, $mismatchPayload];
}

return [
    'blocks native raster handoff for Image XObject Decode component mismatches' => static function (TestRunner $t): void {
        [$pdf, $validPayload, $mismatchPayload] = markerpdf_image_xobject_decode_native_raster_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $valid = $entriesByName['Valid Decode Image'];
        $t->same(true, $valid['image_decode_applied_before_rgb']);
        $t->same(false, $valid['image_decode_component_mismatch']);
        $t->same(false, $valid['image_decode_native_raster_blocked']);
        $t->same(null, $valid['image_decode_boundary_policy']);
        $t->same(true, $valid['native_raster_decode']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $mismatch = $entriesByName['Mismatch Decode Image'];
        $t->same(false, $mismatch['image_decode_applied_before_rgb']);
        $t->same(true, $mismatch['image_decode_component_mismatch']);
        $t->same(true, $mismatch['image_decode_native_raster_blocked']);
        $t->same('reject_image_decode_component_mismatch_for_native_raster', $mismatch['image_decode_boundary_policy']);
        $t->same(false, $mismatch['native_raster_decode']);
        $t->same([
            'ranges' => [
                ['min' => 0.0, 'max' => 1.0],
                ['min' => 1.0, 'max' => 0.0],
            ],
            'component_count' => 2,
            'expected_components' => 4,
            'valid_for_components' => false,
            'identity' => false,
            'inverted_components' => [1],
            'source' => 'explicit',
        ], $mismatch['image_decode']);
        $t->same(hash('sha256', $mismatchPayload), $mismatch['decoded_sha256']);
        $t->same(false, $mismatch['payload_in_visible_text']);

        $t->same(
            ['Before decode native raster boundary', 'After decode native raster boundary'],
            $extractor->extractTextLines($pdf)
        );
        $t->same("Before decode native raster boundary\nAfter decode native raster boundary", $plainText);
        $t->true(!str_contains($plainText, 'Valid Decode Native Raster Payload Noise'));
        $t->true(!str_contains($plainText, 'Mismatch Decode Native Raster Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(!str_contains($encoded, $mismatchPayload));
    },
];
