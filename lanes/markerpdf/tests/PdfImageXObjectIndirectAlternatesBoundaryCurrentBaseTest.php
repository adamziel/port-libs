<?php
declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$indirectAlternateFixture = static function (): array {
    $primaryPayload = 'PRIMARY-INDIRECT-ALTERNATE-IMAGE-BYTES-MUST-STAY-REVIEW-ONLY';
    $currentAlternatePayload = 'CURRENT-GENERATION-ALTERNATE-IMAGE-BYTES-MUST-STAY-REVIEW-ONLY';
    $staleAlternatePayload = 'STALE-GENERATION-ALTERNATE-IMAGE-BYTES-MUST-NOT-BE-SELECTED';

    $primaryCompressed = gzcompress($primaryPayload);
    $currentAlternateCompressed = gzcompress($currentAlternatePayload);
    $staleAlternateCompressed = gzcompress($staleAlternatePayload);

    $pageContent = "BT /F1 12 Tf 72 720 Td (Before indirect alternate image) Tj ET\n"
        . "q 12 0 0 6 72 690 cm /Hero#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After indirect alternate image) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
        . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
        . "3 0 obj << /Type /Page /Parent 2 0 R /Resources << /XObject << /Hero#20Image 5 0 R >> /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 10 0 R >> endobj\n"
        . "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Alternates 8 0 R /Length " . strlen($primaryCompressed) . " >>\nstream\n{$primaryCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($staleAlternateCompressed) . " >>\nstream\n{$staleAlternateCompressed}\nendstream\nendobj\n"
        . "6 1 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($currentAlternateCompressed) . " >>\nstream\n{$currentAlternateCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n[<< /Image 6 1 R /DefaultForPrinting true >>]\nendobj\n"
        . "10 0 obj << /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "trailer << /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $primaryPayload,
        $currentAlternatePayload,
        $staleAlternatePayload,
    ];
};

return [
    'resolves indirect Image XObject Alternates arrays before review-only media handoff' => static function (TestRunner $test) use ($indirectAlternateFixture): void {
        [
            $pdf,
            $primaryPayload,
            $currentAlternatePayload,
            $staleAlternatePayload,
        ] = $indirectAlternateFixture();

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);

        $test->same(
            "Before indirect alternate image\nAfter indirect alternate image",
            trim($plainText),
            'Visible text extraction ignores primary and indirect alternate image streams.'
        );
        $test->true(!str_contains($plainText, $primaryPayload), 'Primary image bytes stay out of visible text.');
        $test->true(!str_contains($plainText, $currentAlternatePayload), 'Current alternate image bytes stay out of visible text.');
        $test->true(!str_contains($plainText, $staleAlternatePayload), 'Stale alternate image bytes stay out of visible text.');

        $test->same(1, $review['image_xobject_count'], 'One image XObject is reviewed.');
        $test->same(1, $review['invoked_image_xobject_count'], 'The page invokes the image XObject once.');

        $image = $review['entries'][0] ?? null;
        $test->true(is_array($image), 'Primary image boundary entry is present.');
        $test->same('Hero Image', $image['resource_name'] ?? null, 'Escaped XObject resource names are decoded for review.');
        $test->same(false, $image['payload_in_visible_text'] ?? null, 'Primary image payload is excluded from text.');
        $test->same(1, $image['alternate_image_count'] ?? null, 'Indirect Alternates array resolves to one alternate image.');
        $test->same(true, $image['alternates_review_only'] ?? null, 'Alternate images remain review-only metadata.');
        $test->same(1, count($image['alternate_images'] ?? []), 'Exactly one alternate image is reported.');

        $alternate = $image['alternate_images'][0];
        $test->same(6, $alternate['object_number'], 'The alternate image object number is preserved.');
        $test->same(1, $alternate['object_generation'], 'The alternate image generation is exact.');
        $test->same(true, $alternate['default_for_printing'], 'DefaultForPrinting is preserved.');
        $test->same(['FlateDecode'], $alternate['filters'], 'Alternate image filters are preserved.');
        $test->same(true, $alternate['decoded_with_current_filters'], 'Flate alternate payload is decoded only for review metadata.');
        $test->same(strlen($currentAlternatePayload), $alternate['decoded_length'], 'Current-generation alternate decoded length is reported.');
        $test->same(hash('sha256', $currentAlternatePayload), $alternate['decoded_sha256'], 'Current-generation alternate decoded hash is reported.');
        $test->same(true, $alternate['native_raster_decode'], 'Flate alternate is native-decodable for metadata.');
        $test->same([], $alternate['preview_only_filters'], 'Flate alternate has no preview-only filters.');
        $test->same(false, $alternate['payload_in_visible_text'], 'Alternate payload is not visible text.');

        $encodedReview = json_encode($review, JSON_THROW_ON_ERROR);
        $test->true(str_contains($encodedReview, hash('sha256', $currentAlternatePayload)), 'Review includes the current-generation alternate hash.');
        $test->true(!str_contains($encodedReview, hash('sha256', $staleAlternatePayload)), 'Review excludes the stale-generation alternate hash.');
        $test->true(!str_contains($encodedReview, $primaryPayload), 'Review does not embed primary image bytes.');
        $test->true(!str_contains($encodedReview, $currentAlternatePayload), 'Review does not embed current alternate image bytes.');
        $test->true(!str_contains($encodedReview, $staleAlternatePayload), 'Review does not embed stale alternate image bytes.');
    },
];
