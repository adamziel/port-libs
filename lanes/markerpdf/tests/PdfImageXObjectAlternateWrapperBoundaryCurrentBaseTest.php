<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$imageXObjectAlternateWrapperFixture = static function (): array {
    $primaryPayload = 'PRIMARY-ALTERNATE-WRAPPER-IMAGE-BYTES-MUST-STAY-REVIEW-ONLY';
    $currentAlternatePayload = 'CURRENT-WRAPPED-ALTERNATE-IMAGE-BYTES-MUST-STAY-REVIEW-ONLY';
    $staleAlternatePayload = 'STALE-WRAPPED-ALTERNATE-IMAGE-BYTES-MUST-NOT-BE-SELECTED';

    $primaryCompressed = gzcompress($primaryPayload);
    $currentAlternateCompressed = gzcompress($currentAlternatePayload);
    $staleAlternateCompressed = gzcompress($staleAlternatePayload);

    $pageContent = "BT /F1 12 Tf 72 720 Td (Before wrapped alternate image) Tj ET\n"
        . "q 16 0 0 8 72 690 cm /Hero#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After wrapped alternate image) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
        . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
        . "3 0 obj << /Type /Page /Parent 2 0 R /Resources << /XObject << /Hero#20Image 5 0 R >> /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 20 0 R >> endobj\n"
        . "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Alternates 8 0 R /Length " . strlen($primaryCompressed) . " >>\nstream\n{$primaryCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleAlternateCompressed) . " >>\nstream\n{$staleAlternateCompressed}\nendstream\nendobj\n"
        . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentAlternateCompressed) . " >>\nstream\n{$currentAlternateCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n[<< /Image 9 0 R /DefaultForPrinting 12 0 R >> << /Image 13 0 R /DefaultForPrinting false >>]\nendobj\n"
        . "9 0 obj\n6 1 R\nendobj\n"
        . "12 0 obj\ntrue\nendobj\n"
        . "13 0 obj\n13 0 R\nendobj\n"
        . "20 0 obj << /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "trailer << /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $primaryPayload,
        $currentAlternatePayload,
        $staleAlternatePayload,
    ];
};

return [
    'resolves alternate image wrapper operands before review-only media handoff' => static function (TestRunner $test) use ($imageXObjectAlternateWrapperFixture): void {
        [
            $pdf,
            $primaryPayload,
            $currentAlternatePayload,
            $staleAlternatePayload,
        ] = $imageXObjectAlternateWrapperFixture();

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);

        $test->same(
            "Before wrapped alternate image\nAfter wrapped alternate image",
            trim($plainText),
            'Visible text extraction ignores primary and wrapped alternate image streams.'
        );
        $test->true(!str_contains($plainText, $primaryPayload), 'Primary image bytes stay out of visible text.');
        $test->true(!str_contains($plainText, $currentAlternatePayload), 'Current wrapped alternate bytes stay out of visible text.');
        $test->true(!str_contains($plainText, $staleAlternatePayload), 'Stale wrapped alternate bytes stay out of visible text.');

        $test->same(1, $review['image_xobject_count'], 'One primary image XObject is reviewed.');
        $test->same(1, $review['invoked_image_xobject_count'], 'The primary image XObject is invoked once.');

        $image = $review['entries'][0] ?? null;
        $test->true(is_array($image), 'Primary image boundary entry is present.');
        $test->same('Hero Image', $image['resource_name'] ?? null, 'Escaped XObject resource names are decoded for review.');
        $test->same(1, $image['alternate_image_count'] ?? null, 'Wrapped alternate image references resolve to one review row.');
        $test->same(true, $image['alternates_review_only'] ?? null, 'Alternate images remain review-only metadata.');
        $test->same(1, count($image['alternate_images'] ?? []), 'Cyclic alternate image wrappers are skipped.');

        $alternate = $image['alternate_images'][0] ?? [];
        $test->same(6, $alternate['object_number'] ?? null, 'The resolved alternate image object number is preserved.');
        $test->same(1, $alternate['object_generation'] ?? null, 'The resolved alternate image generation is exact.');
        $test->same(true, $alternate['default_for_printing'] ?? null, 'Indirect DefaultForPrinting booleans are preserved.');
        $test->same(['FlateDecode'], $alternate['filters'] ?? null, 'Alternate image filters are preserved.');
        $test->same(true, $alternate['decoded_with_current_filters'] ?? null, 'Flate alternate payload is decoded only for review metadata.');
        $test->same(strlen($currentAlternatePayload), $alternate['decoded_length'] ?? null, 'Current alternate decoded length is reported.');
        $test->same(hash('sha256', $currentAlternatePayload), $alternate['decoded_sha256'] ?? null, 'Current alternate decoded hash is reported.');
        $test->same(true, $alternate['native_raster_decode'] ?? null, 'Flate alternate is native-decodable for metadata.');
        $test->same(false, $alternate['payload_in_visible_text'] ?? null, 'Alternate payload is not visible text.');

        $encodedReview = json_encode($review, JSON_THROW_ON_ERROR);
        $test->true(str_contains($encodedReview, hash('sha256', $currentAlternatePayload)), 'Review includes the current-generation alternate hash.');
        $test->true(!str_contains($encodedReview, hash('sha256', $staleAlternatePayload)), 'Review excludes the stale-generation alternate hash.');
        $test->true(!str_contains($encodedReview, $primaryPayload), 'Review does not embed primary image bytes.');
        $test->true(!str_contains($encodedReview, $currentAlternatePayload), 'Review does not embed current alternate image bytes.');
        $test->true(!str_contains($encodedReview, $staleAlternatePayload), 'Review does not embed stale alternate image bytes.');
    },
];
