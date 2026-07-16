<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_malformed_subtype_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before malformed subtype image) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /String#20Subtype#20Image Do Q\n"
        . "q 10 0 0 5 96 690 cm /Unresolved#20Subtype#20Image Do Q\n"
        . "q 8 0 0 4 120 690 cm /Fallback#20Dimension#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After malformed subtype image) Tj ET';
    $stringSubtypePayload = 'BT /F1 12 Tf 72 720 Td (String Subtype Image Payload Noise) Tj ET';
    $unresolvedSubtypePayload = 'BT /F1 12 Tf 72 720 Td (Unresolved Subtype Image Payload Noise) Tj ET';
    $fallbackPayload = 'BT /F1 12 Tf 72 720 Td (Fallback Dimension Image Payload Noise) Tj ET';
    $stringSubtypeCompressed = gzcompress($stringSubtypePayload);
    $unresolvedSubtypeCompressed = gzcompress($unresolvedSubtypePayload);
    $fallbackCompressed = gzcompress($fallbackPayload);
    if (
        !is_string($stringSubtypeCompressed)
        || !is_string($unresolvedSubtypeCompressed)
        || !is_string($fallbackCompressed)
    ) {
        throw new RuntimeException('Unable to compress malformed Image XObject subtype fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /String#20Subtype#20Image 5 0 R /Unresolved#20Subtype#20Image 6 0 R /Fallback#20Dimension#20Image 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype (Image) /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($stringSubtypeCompressed) . " >>\nstream\n{$stringSubtypeCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype 99 0 R /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unresolvedSubtypeCompressed) . " >>\nstream\n{$unresolvedSubtypeCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($fallbackCompressed) . " >>\nstream\n{$fallbackCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $stringSubtypePayload, $unresolvedSubtypePayload, $fallbackPayload];
}

function markerpdf_image_xobject_tailed_subtype_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before subtype tail image) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Tailed#20Subtype#20Image Do Q\n"
        . "q 10 0 0 5 96 690 cm /Valid#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After subtype tail image) Tj ET';
    $tailedSubtypePayload = 'BT /F1 12 Tf 72 720 Td (Tailed Subtype Image Payload Noise) Tj ET';
    $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Image Payload Noise) Tj ET';
    $tailedSubtypeCompressed = gzcompress($tailedSubtypePayload);
    $validCompressed = gzcompress($validPayload);
    if (!is_string($tailedSubtypeCompressed) || !is_string($validCompressed)) {
        throw new RuntimeException('Unable to compress tailed Image XObject subtype fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Tailed#20Subtype#20Image 5 0 R /Valid#20Image 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image 99 0 R /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($tailedSubtypeCompressed) . " >>\nstream\n{$tailedSubtypeCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $tailedSubtypePayload, $validPayload];
}

return [
    'rejects explicit malformed Image XObject subtype values before dimension fallback review' => static function (TestRunner $t): void {
        [$pdf, $stringSubtypePayload, $unresolvedSubtypePayload, $fallbackPayload] = markerpdf_image_xobject_malformed_subtype_boundary_pdf();
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
        $t->same(false, isset($entriesByName['String Subtype Image']));
        $t->same(false, isset($entriesByName['Unresolved Subtype Image']));

        $fallback = $entriesByName['Fallback Dimension Image'] ?? null;
        $t->true(is_array($fallback), 'Missing-subtype dimension fallback image row should remain present.');
        if (!is_array($fallback)) {
            return;
        }

        $t->same(7, $fallback['object_number']);
        $t->same('Image', $fallback['subtype']);
        $t->same(true, $fallback['invoked']);
        $t->same(1, $fallback['invocation_count']);
        $t->same([[8.0, 0.0, 0.0, 4.0, 120.0, 690.0]], $fallback['invocation_matrices']);
        $t->same([120.0, 690.0, 128.0, 694.0], $fallback['image_unit_bbox']);
        $t->same(3, $fallback['width']);
        $t->same(1, $fallback['height']);
        $t->same(true, $fallback['decoded_with_current_filters']);
        $t->same(strlen($fallbackPayload), $fallback['decoded_length']);
        $t->same(hash('sha256', $fallbackPayload), $fallback['decoded_sha256']);
        $t->same(false, $fallback['payload_in_visible_text']);

        $t->same(['Before malformed subtype image', 'After malformed subtype image'], $extractor->extractTextLines($pdf));
        $t->same("Before malformed subtype image\nAfter malformed subtype image", $plainText);
        $t->true(!str_contains($plainText, 'String Subtype Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Unresolved Subtype Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Fallback Dimension Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'String Subtype Image'));
        $t->true(!str_contains($encoded, 'Unresolved Subtype Image'));
        $t->true(!str_contains($encoded, hash('sha256', $stringSubtypePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $unresolvedSubtypePayload)));
        $t->true(!str_contains($encoded, $stringSubtypePayload));
        $t->true(!str_contains($encoded, $unresolvedSubtypePayload));
        $t->true(!str_contains($encoded, $fallbackPayload));
        $t->true(str_contains($encoded, hash('sha256', $fallbackPayload)));
    },
    'rejects tailed explicit Image XObject subtype operands before image review' => static function (TestRunner $t): void {
        [$pdf, $tailedSubtypePayload, $validPayload] = markerpdf_image_xobject_tailed_subtype_boundary_pdf();
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
        $t->same(false, isset($entriesByName['Tailed Subtype Image']));

        $valid = $entriesByName['Valid Image'] ?? null;
        $t->true(is_array($valid), 'Valid sibling Image XObject row should remain present.');
        if (!is_array($valid)) {
            return;
        }

        $t->same(6, $valid['object_number']);
        $t->same('Image', $valid['subtype']);
        $t->same(true, $valid['invoked']);
        $t->same(1, $valid['invocation_count']);
        $t->same([[10.0, 0.0, 0.0, 5.0, 96.0, 690.0]], $valid['invocation_matrices']);
        $t->same([96.0, 690.0, 106.0, 695.0], $valid['image_unit_bbox']);
        $t->same(2, $valid['width']);
        $t->same(1, $valid['height']);
        $t->same(true, $valid['decoded_with_current_filters']);
        $t->same(strlen($validPayload), $valid['decoded_length']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);
        $t->same(false, $valid['payload_in_visible_text']);

        $t->same(['Before subtype tail image', 'After subtype tail image'], $extractor->extractTextLines($pdf));
        $t->same("Before subtype tail image\nAfter subtype tail image", $plainText);
        $t->true(!str_contains($plainText, 'Tailed Subtype Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Valid Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Tailed Subtype Image'));
        $t->true(!str_contains($encoded, hash('sha256', $tailedSubtypePayload)));
        $t->true(!str_contains($encoded, $tailedSubtypePayload));
        $t->true(!str_contains($encoded, $validPayload));
        $t->true(str_contains($encoded, hash('sha256', $validPayload)));
    },
];
