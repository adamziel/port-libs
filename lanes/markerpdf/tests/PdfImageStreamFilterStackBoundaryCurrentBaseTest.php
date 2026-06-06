<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$imageStreamFilterStackBoundaryCurrentBasePdf = static function (): array {
    $pageContent = "BT /F1 12 Tf 72 720 Td (Before image filter tail review) Tj ET\n"
        . "q 16 0 0 8 72 690 cm /Unsafe#20Image Do Q\n"
        . "q 12 0 0 6 104 690 cm /Clean#20Image Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After image filter tail review) Tj ET';
    $unsafePayload = "RGBTAIL";
    $cleanPayload = "RGBOK";
    $unsafeCompressed = gzcompress($unsafePayload);
    $cleanCompressed = gzcompress($cleanPayload);
    if (!is_string($unsafeCompressed) || !is_string($cleanCompressed)) {
        throw new RuntimeException('Unable to compress focused image stream-filter fixture.');
    }

    $unsafeTail = 'BT /F1 12 Tf 72 680 Td (Unsafe Image Tail Payload Leak) Tj ET';
    $unsafeStream = $unsafeCompressed . $unsafeTail;

    return [
        'unsafePayload' => $unsafePayload,
        'cleanPayload' => $cleanPayload,
        'unsafeStream' => $unsafeStream,
        'cleanStream' => $cleanCompressed,
        'pdf' => "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Unsafe#20Image 5 0 R /Clean#20Image 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unsafeStream) . " >>\nstream\n{$unsafeStream}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($cleanCompressed) . " >>\nstream\n{$cleanCompressed}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "%%EOF",
    ];
};

return [
    'fails closed on Flate image review streams with non-whitespace bytes after the compressed member' => static function (TestRunner $t) use ($imageStreamFilterStackBoundaryCurrentBasePdf): void {
        $fixture = $imageStreamFilterStackBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $pdf = $fixture['pdf'];
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $entriesByName = [];
        foreach ($review['entries'] as $entry) {
            $entriesByName[$entry['resource_name']] = $entry;
        }

        $t->same(['Before image filter tail review', 'After image filter tail review'], $extractor->extractTextLines($pdf));
        $t->same("Before image filter tail review\nAfter image filter tail review", $plainText);
        $t->true(!str_contains($plainText, 'Unsafe Image Tail Payload Leak'));
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);

        $unsafe = $entriesByName['Unsafe Image'] ?? null;
        $clean = $entriesByName['Clean Image'] ?? null;
        $t->true(is_array($unsafe));
        $t->true(is_array($clean));

        $t->same(['FlateDecode'], $unsafe['filters'] ?? null);
        $t->same(strlen($fixture['unsafeStream']), $unsafe['raw_length'] ?? null);
        $t->same(false, $unsafe['decoded_with_current_filters'] ?? null);
        $t->same(null, $unsafe['decoded_length'] ?? null);
        $t->same(null, $unsafe['decoded_sha256'] ?? null);
        $t->same(false, $unsafe['native_raster_decode'] ?? null);
        $t->same(false, $unsafe['payload_in_visible_text'] ?? null);

        $t->same(['FlateDecode'], $clean['filters'] ?? null);
        $t->same(strlen($fixture['cleanStream']), $clean['raw_length'] ?? null);
        $t->same(true, $clean['decoded_with_current_filters'] ?? null);
        $t->same(strlen($fixture['cleanPayload']), $clean['decoded_length'] ?? null);
        $t->same(hash('sha256', $fixture['cleanPayload']), $clean['decoded_sha256'] ?? null);
        $t->same(true, $clean['native_raster_decode'] ?? null);
        $t->same(false, $clean['payload_in_visible_text'] ?? null);
    },
];
