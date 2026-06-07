<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_duplicate_subtype_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before duplicate subtype image) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Duplicate#20Subtype#20Image Do Q\n"
        . "q 10 0 0 5 104 690 cm /Valid#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After duplicate subtype image) Tj ET';
    $duplicatePayload = 'BT /F1 12 Tf 72 720 Td (Duplicate Subtype Image Payload Noise) Tj ET';
    $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Duplicate Subtype Sibling Image Payload Noise) Tj ET';
    $duplicateCompressed = gzcompress($duplicatePayload);
    $validCompressed = gzcompress($validPayload);
    if (!is_string($duplicateCompressed) || !is_string($validCompressed)) {
        throw new RuntimeException('Unable to compress duplicate subtype Image XObject fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Duplicate#20Subtype#20Image 5 0 R /Valid#20Image 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Subtype /Form /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($duplicateCompressed) . " >>\nstream\n{$duplicateCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $duplicatePayload, $validPayload];
}

return [
    'rejects duplicate Image XObject Subtype declarations before image review' => static function (TestRunner $t): void {
        [$pdf, $duplicatePayload, $validPayload] = markerpdf_image_xobject_duplicate_subtype_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, isset($entriesByName['Duplicate Subtype Image']));

        $valid = $entriesByName['Valid Image'] ?? null;
        $t->true(is_array($valid), 'Valid sibling image review row should remain present.');
        if (!is_array($valid)) {
            return;
        }

        $t->same(6, $valid['object_number']);
        $t->same(0, $valid['object_generation']);
        $t->same('Image', $valid['subtype']);
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[10.0, 0.0, 0.0, 5.0, 104.0, 690.0]], $valid['invocation_matrices']);
        $t->same([104.0, 690.0, 114.0, 695.0], $valid['image_unit_bbox']);
        $t->same(2, $valid['width']);
        $t->same(1, $valid['height']);
        $t->same('DeviceGray', $valid['color_space']);
        $t->same(8, $valid['bits_per_component']);
        $t->same(true, $valid['decoded_with_current_filters']);
        $t->same(strlen($validPayload), $valid['decoded_length']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before duplicate subtype image', 'After duplicate subtype image'], $extractor->extractTextLines($pdf));
        $t->same("Before duplicate subtype image\nAfter duplicate subtype image", $plainText);
        $t->true(!str_contains($plainText, 'Duplicate Subtype Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Duplicate Subtype Sibling Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Duplicate Subtype Image'));
        $t->true(!str_contains($encoded, hash('sha256', $duplicatePayload)));
        $t->true(!str_contains($encoded, $duplicatePayload));
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
    },
];
