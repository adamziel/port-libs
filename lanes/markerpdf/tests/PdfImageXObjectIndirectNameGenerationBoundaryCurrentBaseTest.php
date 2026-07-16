<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_indirect_name_generation_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before exact indirect names) Tj ET\n"
        . "q 16 0 0 8 72 690 cm /Exact#20Indirect#20Name#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After exact indirect names) Tj ET';
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Exact Indirect Name Image Payload Noise) Tj ET';
    $compressedPayload = gzcompress($imagePayload);
    if (!is_string($compressedPayload)) {
        throw new RuntimeException('Unable to compress exact indirect Image XObject name payload.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Exact#20Indirect#20Name#20Image 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type 30 0 R /Subtype 31 0 R /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
        . "30 0 obj\n/XObject\nendobj\n"
        . "30 1 obj\n/NotXObject\nendobj\n"
        . "31 0 obj\n/Image\nendobj\n"
        . "31 1 obj\n/Form\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $imagePayload];
}

return [
    'resolves Image XObject Type and Subtype indirect names by exact object generation' => static function (TestRunner $t): void {
        [$pdf, $imagePayload] = markerpdf_image_xobject_indirect_name_generation_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);

        $entry = $review['entries'][0] ?? null;
        $t->true(is_array($entry), 'Exact indirect-name Image XObject row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Exact Indirect Name Image', $entry['resource_name']);
        $t->same(5, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same('Image', $entry['subtype']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[16.0, 0.0, 0.0, 8.0, 72.0, 690.0]], $entry['invocation_matrices']);
        $t->same([72.0, 690.0, 88.0, 698.0], $entry['image_unit_bbox']);
        $t->same(2, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same('DeviceRGB', $entry['color_space']);
        $t->same(8, $entry['bits_per_component']);
        $t->same(true, $entry['image_dimensions_valid']);
        $t->same(true, $entry['native_raster_decode']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($imagePayload), $entry['decoded_length']);
        $t->same(hash('sha256', $imagePayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);

        $t->same(['Before exact indirect names', 'After exact indirect names'], $extractor->extractTextLines($pdf));
        $t->same("Before exact indirect names\nAfter exact indirect names", $plainText);
        $t->true(!str_contains($plainText, 'Exact Indirect Name Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(!str_contains($encoded, 'NotXObject'));
        $t->true(!str_contains($encoded, '/Form'));
        $t->true(str_contains($encoded, hash('sha256', $imagePayload)));
    },
];
