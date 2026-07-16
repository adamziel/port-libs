<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_indirect_name_operand_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect name operand image) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Tailed#20Indirect#20Subtype#20Image Do Q\n"
        . "q 10 0 0 5 96 690 cm /Tailed#20Indirect#20Type#20Image Do Q\n"
        . "q 8 0 0 4 120 690 cm /Valid#20Indirect#20Name#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After indirect name operand image) Tj ET';
    $tailedSubtypePayload = 'BT /F1 12 Tf 72 720 Td (Tailed Indirect Subtype Image Payload Noise) Tj ET';
    $tailedTypePayload = 'BT /F1 12 Tf 72 720 Td (Tailed Indirect Type Image Payload Noise) Tj ET';
    $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Indirect Name Image Payload Noise) Tj ET';
    $tailedSubtypeCompressed = gzcompress($tailedSubtypePayload);
    $tailedTypeCompressed = gzcompress($tailedTypePayload);
    $validCompressed = gzcompress($validPayload);
    if (
        !is_string($tailedSubtypeCompressed)
        || !is_string($tailedTypeCompressed)
        || !is_string($validCompressed)
    ) {
        throw new RuntimeException('Unable to compress indirect Image XObject name operand fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Tailed#20Indirect#20Subtype#20Image 5 0 R /Tailed#20Indirect#20Type#20Image 6 0 R /Valid#20Indirect#20Name#20Image 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype 20 0 R /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tailedSubtypeCompressed) . " >>\nstream\n{$tailedSubtypeCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type 21 0 R /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tailedTypeCompressed) . " >>\nstream\n{$tailedTypeCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type 22 0 R /Subtype 23 0 R /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
        . "20 0 obj\n/Image 99 0 R\nendobj\n"
        . "21 0 obj\n/XObject 99 0 R\nendobj\n"
        . "22 0 obj\n/XObject\nendobj\n"
        . "23 0 obj\n/Image\nendobj\n"
        . "99 0 obj\n<< /S /JavaScript /JS (decoy) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $tailedSubtypePayload, $tailedTypePayload, $validPayload];
}

return [
    'rejects tailed indirect Image XObject Type and Subtype name operands' => static function (TestRunner $t): void {
        [$pdf, $tailedSubtypePayload, $tailedTypePayload, $validPayload] =
            markerpdf_image_xobject_indirect_name_operand_boundary_pdf();
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
        $t->same(false, isset($entriesByName['Tailed Indirect Subtype Image']));
        $t->same(false, isset($entriesByName['Tailed Indirect Type Image']));

        $valid = $entriesByName['Valid Indirect Name Image'] ?? null;
        $t->true(is_array($valid), 'Valid indirect-name Image XObject row should remain present.');
        if (!is_array($valid)) {
            return;
        }

        $t->same(7, $valid['object_number']);
        $t->same(0, $valid['object_generation']);
        $t->same('Image', $valid['subtype']);
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[8.0, 0.0, 0.0, 4.0, 120.0, 690.0]], $valid['invocation_matrices']);
        $t->same([120.0, 690.0, 128.0, 694.0], $valid['image_unit_bbox']);
        $t->same(3, $valid['width']);
        $t->same(1, $valid['height']);
        $t->same('DeviceGray', $valid['color_space']);
        $t->same(8, $valid['bits_per_component']);
        $t->same(true, $valid['decoded_with_current_filters']);
        $t->same(strlen($validPayload), $valid['decoded_length']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before indirect name operand image', 'After indirect name operand image'], $extractor->extractTextLines($pdf));
        $t->same("Before indirect name operand image\nAfter indirect name operand image", $plainText);
        $t->true(!str_contains($plainText, 'Tailed Indirect Subtype Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Tailed Indirect Type Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Indirect Name Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Tailed Indirect Subtype Image'));
        $t->true(!str_contains($encoded, 'Tailed Indirect Type Image'));
        $t->true(!str_contains($encoded, hash('sha256', $tailedSubtypePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $tailedTypePayload)));
        $t->true(!str_contains($encoded, $tailedSubtypePayload));
        $t->true(!str_contains($encoded, $tailedTypePayload));
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
    },
];
