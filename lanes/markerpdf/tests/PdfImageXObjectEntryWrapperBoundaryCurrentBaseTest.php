<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'image XObject boundary review resolves indirect resource entry wrappers' => static function (TestRunner $t): void {
    $imagePayload = "wrapped-image-payload";
    $nestedPayload = "nested-wrapped-image";
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n"
        . "<< /Type /Catalog /Pages 2 0 R >>\n"
        . "endobj\n"
        . "2 0 obj\n"
        . "<< /Type /Pages /Kids [3 0 R] /Count 1 >>\n"
        . "endobj\n"
        . "3 0 obj\n"
        . "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Wrapped#20Image 5 0 R /Wrapped#20Form 7 0 R >> >> /Contents 11 0 R >>\n"
        . "endobj\n"
        . "4 0 obj\n"
        . "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\n"
        . "endobj\n"
        . "5 0 obj\n"
        . "6 0 R\n"
        . "endobj\n"
        . "6 0 obj\n"
        . "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($imagePayload) . " >>\n"
        . "stream\n"
        . $imagePayload . "\n"
        . "endstream\n"
        . "endobj\n"
        . "7 0 obj\n"
        . "8 0 R\n"
        . "endobj\n"
        . "8 0 obj\n"
        . "<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Resources << /XObject << /Nested#20Wrapped 9 0 R >> >> /Length 39 >>\n"
        . "stream\n"
        . "q 4 0 0 2 1 1 cm /Nested#20Wrapped Do Q\n"
        . "endstream\n"
        . "endobj\n"
        . "9 0 obj\n"
        . "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($nestedPayload) . " >>\n"
        . "stream\n"
        . $nestedPayload . "\n"
        . "endstream\n"
        . "endobj\n"
        . "11 0 obj\n"
        . "<< /Length 107 >>\n"
        . "stream\n"
        . "BT /F1 12 Tf 72 740 Td (Before) Tj ET q 20 0 0 10 72 690 cm /Wrapped#20Image Do Q q 30 0 0 15 110 690 cm /Wrapped#20Form Do Q BT /F1 12 Tf 72 660 Td (After) Tj ET\n"
        . "endstream\n"
        . "endobj\n"
        . "xref\n"
        . "0 12\n"
        . "0000000000 65535 f \n"
        . "trailer\n"
        . "<< /Root 1 0 R >>\n"
        . "%%EOF\n";

    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractImageXObjectBoundaryReview($pdf);

    $t->same("Before\nAfter", $plainText);
    $t->true(!str_contains($plainText, $imagePayload));
    $t->true(!str_contains($plainText, $nestedPayload));

    $t->same(2, $review['image_xobject_count']);
    $t->same(2, $review['invoked_image_xobject_count']);
    $t->same(0, $review['uninvoked_image_xobject_count']);
    $t->true(!str_contains(json_encode($review, JSON_THROW_ON_ERROR), $imagePayload));
    $t->true(!str_contains(json_encode($review, JSON_THROW_ON_ERROR), $nestedPayload));

    $pageImage = $review['entries'][0];
    $t->same('Wrapped Image', $pageImage['resource_name']);
    $t->same(6, $pageImage['object_number']);
    $t->same(0, $pageImage['object_generation']);
    $t->same([[20.0, 0.0, 0.0, 10.0, 72.0, 690.0]], $pageImage['invocation_matrices']);
    $t->same([72.0, 690.0, 92.0, 700.0], $pageImage['image_unit_bbox']);
    $t->same(hash('sha256', $imagePayload), $pageImage['decoded_sha256']);

    $nestedImage = $review['entries'][1];
    $t->same('Nested Wrapped', $nestedImage['resource_name']);
    $t->same(9, $nestedImage['object_number']);
    $t->same(['Wrapped Form', 'Nested Wrapped'], $nestedImage['resource_path']);
    $t->same(8, $nestedImage['parent_form_xobject_object']);
    $t->same([[120.0, 0.0, 0.0, 30.0, 140.0, 705.0]], $nestedImage['invocation_matrices']);
    $t->same([140.0, 705.0, 260.0, 735.0], $nestedImage['image_unit_bbox']);
    $t->same(hash('sha256', $nestedPayload), $nestedImage['decoded_sha256']);
    },

    'image XObject boundary review skips cyclic resource entry wrappers' => static function (TestRunner $t): void {
    $imagePayload = "valid-image-after-cycle";
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n"
        . "<< /Type /Catalog /Pages 2 0 R >>\n"
        . "endobj\n"
        . "2 0 obj\n"
        . "<< /Type /Pages /Kids [3 0 R] /Count 1 >>\n"
        . "endobj\n"
        . "3 0 obj\n"
        . "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << /Font << /F1 4 0 R >> /XObject << /Cycle#20Image 5 0 R /Valid#20Image 6 0 R >> >> /Contents 7 0 R >>\n"
        . "endobj\n"
        . "4 0 obj\n"
        . "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\n"
        . "endobj\n"
        . "5 0 obj\n"
        . "5 0 R\n"
        . "endobj\n"
        . "6 0 obj\n"
        . "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($imagePayload) . " >>\n"
        . "stream\n"
        . $imagePayload . "\n"
        . "endstream\n"
        . "endobj\n"
        . "7 0 obj\n"
        . "<< /Length 93 >>\n"
        . "stream\n"
        . "BT /F1 12 Tf 20 160 Td (Cycle guard) Tj ET q 5 0 0 5 20 100 cm /Cycle#20Image Do Q q 5 0 0 5 40 100 cm /Valid#20Image Do Q\n"
        . "endstream\n"
        . "endobj\n"
        . "xref\n"
        . "0 8\n"
        . "0000000000 65535 f \n"
        . "trailer\n"
        . "<< /Root 1 0 R >>\n"
        . "%%EOF\n";

    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractImageXObjectBoundaryReview($pdf);

    $t->same('Cycle guard', $plainText);

    $t->same(1, $review['image_xobject_count']);
    $t->same(1, $review['invoked_image_xobject_count']);
    $t->same(0, $review['uninvoked_image_xobject_count']);
    $t->same('Valid Image', $review['entries'][0]['resource_name']);
    $t->same(6, $review['entries'][0]['object_number']);
    $t->same(hash('sha256', $imagePayload), $review['entries'][0]['decoded_sha256']);
    },
];
