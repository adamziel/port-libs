<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_duplicate_image_xobject_resource_name_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before duplicate image resource) Tj ET\n"
        . "q 10 0 0 5 72 690 cm /Dup#20Image Do Q\n"
        . "q 8 0 0 4 100 690 cm /Unique#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After duplicate image resource) Tj ET';
    $staleDuplicatePayload = 'BT /F1 12 Tf 72 720 Td (Stale Duplicate Image Payload Noise) Tj ET';
    $currentDuplicatePayload = 'BT /F1 12 Tf 72 720 Td (Current Duplicate Image Payload Noise) Tj ET';
    $uniquePayload = 'BT /F1 12 Tf 72 720 Td (Unique Image Payload Noise) Tj ET';
    $staleCompressed = gzcompress($staleDuplicatePayload);
    $currentCompressed = gzcompress($currentDuplicatePayload);
    $uniqueCompressed = gzcompress($uniquePayload);
    if (!is_string($staleCompressed) || !is_string($currentCompressed) || !is_string($uniqueCompressed)) {
        throw new RuntimeException('Unable to compress duplicate Image XObject resource-name fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Dup#20Image 5 0 R /Dup#20Image 6 0 R /Unique#20Image 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($uniqueCompressed) . " >>\nstream\n{$uniqueCompressed}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $staleDuplicatePayload, $currentDuplicatePayload, $uniquePayload];
}

return [
    'rejects duplicate Image XObject resource names before placement review' => static function (TestRunner $t): void {
        [$pdf, $staleDuplicatePayload, $currentDuplicatePayload, $uniquePayload] = markerpdf_duplicate_image_xobject_resource_name_pdf();
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
        $t->same(false, isset($entriesByName['Dup Image']));

        $unique = $entriesByName['Unique Image'];
        $t->same(7, $unique['object_number']);
        $t->same(true, $unique['invoked']);
        $t->same(1, $unique['invocation_count']);
        $t->same([[8.0, 0.0, 0.0, 4.0, 100.0, 690.0]], $unique['invocation_matrices']);
        $t->same([100.0, 690.0, 108.0, 694.0], $unique['image_unit_bbox']);
        $t->same(true, $unique['decoded_with_current_filters']);
        $t->same(strlen($uniquePayload), $unique['decoded_length']);
        $t->same(hash('sha256', $uniquePayload), $unique['decoded_sha256']);
        $t->same(false, $unique['payload_in_visible_text']);
        $t->same(true, $unique['review_only']);

        $t->same(['Before duplicate image resource', 'After duplicate image resource'], $extractor->extractTextLines($pdf));
        $t->same("Before duplicate image resource\nAfter duplicate image resource", $plainText);
        $t->true(!str_contains($plainText, 'Stale Duplicate Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Current Duplicate Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Unique Image Payload Noise'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'Dup Image'));
        $t->true(!str_contains($encoded, hash('sha256', $staleDuplicatePayload)));
        $t->true(!str_contains($encoded, hash('sha256', $currentDuplicatePayload)));
        $t->true(!str_contains($encoded, $staleDuplicatePayload));
        $t->true(!str_contains($encoded, $currentDuplicatePayload));
        $t->true(!str_contains($encoded, $uniquePayload));
        $t->true(str_contains($encoded, hash('sha256', $uniquePayload)));
    },
];
