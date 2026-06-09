<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

function markerpdf_image_xobject_name_intent_boundary_pdf(): array
{
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before image metadata boundary) Tj ET\n"
        . "q 10 0 0 5 72 690 cm /Private#20Metadata#20Image Do Q\n"
        . "q 10 0 0 5 88 690 cm /Tailed#20Metadata#20Image Do Q\n"
        . "q 10 0 0 5 104 690 cm /Duplicate#20Metadata#20Image Do Q\n"
        . "q 10 0 0 5 120 690 cm /Valid#20Metadata#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After image metadata boundary) Tj ET';

    $privatePayload = 'BT /F1 12 Tf 72 720 Td (Private Image Metadata Payload Noise) Tj ET';
    $tailedPayload = 'BT /F1 12 Tf 72 720 Td (Tailed Image Metadata Payload Noise) Tj ET';
    $duplicatePayload = 'BT /F1 12 Tf 72 720 Td (Duplicate Image Metadata Payload Noise) Tj ET';
    $validPayload = 'BT /F1 12 Tf 72 720 Td (Valid Image Metadata Payload Noise) Tj ET';

    $compressedPrivatePayload = gzcompress($privatePayload);
    $compressedTailedPayload = gzcompress($tailedPayload);
    $compressedDuplicatePayload = gzcompress($duplicatePayload);
    $compressedValidPayload = gzcompress($validPayload);
    if (
        !is_string($compressedPrivatePayload)
        || !is_string($compressedTailedPayload)
        || !is_string($compressedDuplicatePayload)
        || !is_string($compressedValidPayload)
    ) {
        throw new RuntimeException('Unable to compress Image XObject name/intent boundary fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Private#20Metadata#20Image 5 0 R /Tailed#20Metadata#20Image 6 0 R /Duplicate#20Metadata#20Image 7 0 R /Valid#20Metadata#20Image 8 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Intent /AbsoluteColorimetric /Name /Private#20Decoy#20Review#20Name >> /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedPrivatePayload) . " >>\nstream\n{$compressedPrivatePayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Intent /RelativeColorimetric 99 0 R /Name /Tailed#20Review#20Name 99 0 R /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedTailedPayload) . " >>\nstream\n{$compressedTailedPayload}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Image /Intent /Perceptual /Intent /Saturation /Name /First#20Duplicate#20Review#20Name /Name /Second#20Duplicate#20Review#20Name /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedDuplicatePayload) . " >>\nstream\n{$compressedDuplicatePayload}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /XObject /Subtype /Image /Intent /Saturation /Name /Valid#20Review#20Name /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedValidPayload) . " >>\nstream\n{$compressedValidPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $privatePayload, $tailedPayload, $duplicatePayload, $validPayload];
}

return [
    'keeps image XObject Intent and Name metadata on strict top-level operands only' => static function (TestRunner $t): void {
        [$pdf, $privatePayload, $tailedPayload, $duplicatePayload, $validPayload] = markerpdf_image_xobject_name_intent_boundary_pdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(4, $review['image_xobject_count']);
        $t->same(4, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        foreach (['Private Metadata Image', 'Tailed Metadata Image', 'Duplicate Metadata Image', 'Valid Metadata Image'] as $resourceName) {
            $t->true(isset($entriesByName[$resourceName]), "Review row should exist for {$resourceName}.");
            $t->same(true, $entriesByName[$resourceName]['invoked'] ?? null);
            $t->same(1, $entriesByName[$resourceName]['invocation_count'] ?? null);
            $t->same('Image', $entriesByName[$resourceName]['subtype'] ?? null);
            $t->same(1, $entriesByName[$resourceName]['width'] ?? null);
            $t->same(1, $entriesByName[$resourceName]['height'] ?? null);
            $t->same('DeviceRGB', $entriesByName[$resourceName]['color_space'] ?? null);
            $t->same(8, $entriesByName[$resourceName]['bits_per_component'] ?? null);
            $t->same(true, $entriesByName[$resourceName]['decoded_with_current_filters'] ?? null);
            $t->same(true, $entriesByName[$resourceName]['native_raster_decode'] ?? null);
            $t->same(false, $entriesByName[$resourceName]['payload_in_visible_text'] ?? null);
        }

        $private = $entriesByName['Private Metadata Image'];
        $t->same(null, $private['rendering_intent']);
        $t->same(null, $private['image_name']);
        $t->same(hash('sha256', $privatePayload), $private['decoded_sha256']);

        $tailed = $entriesByName['Tailed Metadata Image'];
        $t->same(null, $tailed['rendering_intent']);
        $t->same(null, $tailed['image_name']);
        $t->same(hash('sha256', $tailedPayload), $tailed['decoded_sha256']);

        $duplicate = $entriesByName['Duplicate Metadata Image'];
        $t->same(null, $duplicate['rendering_intent']);
        $t->same(null, $duplicate['image_name']);
        $t->same(hash('sha256', $duplicatePayload), $duplicate['decoded_sha256']);

        $valid = $entriesByName['Valid Metadata Image'];
        $t->same('Saturation', $valid['rendering_intent']);
        $t->same('Valid Review Name', $valid['image_name']);
        $t->same(hash('sha256', $validPayload), $valid['decoded_sha256']);

        $t->same(['Before image metadata boundary', 'After image metadata boundary'], $extractor->extractTextLines($pdf));
        $t->same("Before image metadata boundary\nAfter image metadata boundary", $plainText);

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([$privatePayload, $tailedPayload, $duplicatePayload, $validPayload] as $payload) {
            $t->true(!str_contains($plainText, $payload));
            $t->true(!str_contains($encoded, $payload));
        }
        foreach (
            [
                'AbsoluteColorimetric',
                'Private Decoy Review Name',
                'RelativeColorimetric',
                'Tailed Review Name',
                'Perceptual',
                'First Duplicate Review Name',
                'Second Duplicate Review Name',
            ] as $blockedMetadata
        ) {
            $t->true(!str_contains($encoded, $blockedMetadata), "{$blockedMetadata} should not leak into review metadata.");
        }
        $t->true(str_contains($encoded, 'Saturation'));
        $t->true(str_contains($encoded, 'Valid Review Name'));
    },
];
