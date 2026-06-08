<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_type_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before image type boundary) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Metadata#20Type#20Image Do Q\n"
        . "q 10 0 0 5 94 690 cm /Literal#20Type#20Image Do Q\n"
        . "q 8 0 0 4 114 690 cm /Tailed#20Type#20Image Do Q\n"
        . "q 6 0 0 3 130 690 cm /Duplicate#20Type#20Image Do Q\n"
        . "q 14 0 0 7 134 690 cm /Typeless#20Image Do Q\n"
        . "q 16 0 0 8 160 690 cm /Valid#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After image type boundary) Tj ET';

    $payloads = [
        'Metadata Type Image' => 'BT /F1 12 Tf 72 720 Td (Metadata Type Image Payload Noise) Tj ET',
        'Literal Type Image' => 'BT /F1 12 Tf 72 720 Td (Literal Type Image Payload Noise) Tj ET',
        'Tailed Type Image' => 'BT /F1 12 Tf 72 720 Td (Tailed Type Image Payload Noise) Tj ET',
        'Duplicate Type Image' => 'BT /F1 12 Tf 72 720 Td (Duplicate Type Image Payload Noise) Tj ET',
        'Typeless Image' => 'BT /F1 12 Tf 72 720 Td (Typeless Image Payload Noise) Tj ET',
        'Valid Image' => 'BT /F1 12 Tf 72 720 Td (Valid Type Boundary Image Payload Noise) Tj ET',
    ];

    $compressed = [];
    foreach ($payloads as $name => $payload) {
        $bytes = gzcompress($payload);
        if (!is_string($bytes)) {
            throw new RuntimeException("Unable to compress {$name} fixture payload.");
        }
        $compressed[$name] = $bytes;
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Metadata#20Type#20Image 5 0 R /Literal#20Type#20Image 6 0 R /Tailed#20Type#20Image 7 0 R /Duplicate#20Type#20Image 11 0 R /Typeless#20Image 8 0 R /Valid#20Image 9 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Metadata Type Image']) . " >>\nstream\n{$compressed['Metadata Type Image']}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type (XObject) /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Literal Type Image']) . " >>\nstream\n{$compressed['Literal Type Image']}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject 99 0 R /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Tailed Type Image']) . " >>\nstream\n{$compressed['Tailed Type Image']}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Typeless Image']) . " >>\nstream\n{$compressed['Typeless Image']}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Valid Image']) . " >>\nstream\n{$compressed['Valid Image']}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Type /Metadata /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressed['Duplicate Type Image']) . " >>\nstream\n{$compressed['Duplicate Type Image']}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $payloads];
}

return [
    'rejects explicit non-XObject image stream Type values before image review' => static function (TestRunner $t): void {
        [$pdf, $payloads] = markerpdf_image_xobject_type_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        foreach (['Metadata Type Image', 'Literal Type Image', 'Tailed Type Image', 'Duplicate Type Image'] as $rejectedName) {
            $t->same(false, isset($entriesByName[$rejectedName]));
        }

        $typeless = $entriesByName['Typeless Image'] ?? null;
        $t->true(is_array($typeless), 'Type-less image fallback row should remain present.');
        if (!is_array($typeless)) {
            return;
        }

        $t->same(8, $typeless['object_number']);
        $t->same(0, $typeless['object_generation']);
        $t->same('Image', $typeless['subtype']);
        $t->same(true, $typeless['invoked']);
        $t->same(1, $typeless['invocation_count']);
        $t->same([[14.0, 0.0, 0.0, 7.0, 134.0, 690.0]], $typeless['invocation_matrices']);
        $t->same([134.0, 690.0, 148.0, 697.0], $typeless['image_unit_bbox']);
        $t->same(3, $typeless['width']);
        $t->same(2, $typeless['height']);
        $t->same('DeviceRGB', $typeless['color_space']);
        $t->same(8, $typeless['bits_per_component']);
        $t->same(true, $typeless['decoded_with_current_filters']);
        $t->same(strlen($payloads['Typeless Image']), $typeless['decoded_length']);
        $t->same(hash('sha256', $payloads['Typeless Image']), $typeless['decoded_sha256']);
        $t->same(false, $typeless['payload_in_visible_text']);

        $valid = $entriesByName['Valid Image'] ?? null;
        $t->true(is_array($valid), 'Valid /Type /XObject image row should remain present.');
        if (!is_array($valid)) {
            return;
        }

        $t->same(9, $valid['object_number']);
        $t->same(0, $valid['object_generation']);
        $t->same('Image', $valid['subtype']);
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[16.0, 0.0, 0.0, 8.0, 160.0, 690.0]], $valid['invocation_matrices']);
        $t->same([160.0, 690.0, 176.0, 698.0], $valid['image_unit_bbox']);
        $t->same(4, $valid['width']);
        $t->same(2, $valid['height']);
        $t->same('DeviceRGB', $valid['color_space']);
        $t->same(8, $valid['bits_per_component']);
        $t->same(true, $valid['decoded_with_current_filters']);
        $t->same(strlen($payloads['Valid Image']), $valid['decoded_length']);
        $t->same(hash('sha256', $payloads['Valid Image']), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before image type boundary', 'After image type boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before image type boundary\nAfter image type boundary", $plainText);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Metadata Type Image', 'Literal Type Image', 'Tailed Type Image', 'Duplicate Type Image'] as $rejectedName) {
            $t->true(!str_contains($plainText, $payloads[$rejectedName]));
            $t->true(!str_contains($encoded, $rejectedName));
            $t->true(!str_contains($encoded, hash('sha256', $payloads[$rejectedName])));
        }
        $t->true(!str_contains($encoded, $payloads['Typeless Image']));
        $t->true(!str_contains($encoded, $payloads['Valid Image']));
        $t->true(str_contains($encoded, hash('sha256', $payloads['Typeless Image'])));
        $t->true(str_contains($encoded, hash('sha256', $payloads['Valid Image'])));
    },
];
