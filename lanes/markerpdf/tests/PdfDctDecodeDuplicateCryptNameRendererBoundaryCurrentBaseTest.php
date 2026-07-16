<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$pdfDctDecodeDuplicateCryptNameRendererBoundaryJpeg = static function (): string {
    $segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
    $sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x03"
        . "\x01\x11\x00"
        . "\x02\x11\x00"
        . "\x03\x11\x00";
    $sosPayload = "\x03"
        . "\x01\x00"
        . "\x02\x11"
        . "\x03\x11"
        . "\x00\x3f\x00";
    $scanPayload = "duplicate crypt name renderer scan bytes before stuffed ff \xff\x00 "
        . "restart marker remains image bytes \xff\xd0";

    return "\xff\xd8"
        . $segment(0xc0, $sofPayload)
        . $segment(0xda, $sosPayload)
        . $scanPayload
        . "\xff\xd9";
};

$pdfDctDecodeDuplicateCryptNameRendererBoundaryImage = static function (string $cryptDecodeParms) use (
    $pdfDctDecodeDuplicateCryptNameRendererBoundaryJpeg
): array {
    $jpeg = $pdfDctDecodeDuplicateCryptNameRendererBoundaryJpeg();
    $dictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter [/Crypt /DCTDecode]'
        . ' /DecodeParms [' . $cryptDecodeParms . ' null]'
        . ' /Length ' . strlen($jpeg) . ' >>';

    return [
        'image' => $dictionary . "\nstream\n{$jpeg}\nendstream",
        'dictionary' => $dictionary,
        'jpeg' => $jpeg,
        'objects' => [
            30 => '[ /ICCBased 31 0 R ]',
            31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
        ],
    ];
};

return [
    'fails closed on duplicate Crypt DecodeParms Name entries before renderer DCTDecode review boundaries' => static function (TestRunner $t) use ($pdfDctDecodeDuplicateCryptNameRendererBoundaryImage): void {
        $renderer = new PdfImageRenderer();
        $identityFixture = $pdfDctDecodeDuplicateCryptNameRendererBoundaryImage('<< /Name /Identity >>');
        $identityPreview = $renderer->iccBasedImageStreamPreviewRows($identityFixture['image'], $identityFixture['objects']);
        $identityStream = $identityPreview['image_stream'] ?? null;
        $identityBoundary = is_array($identityStream) ? ($identityStream['dctdecode_stream_boundary'] ?? null) : null;

        $t->true(is_array($identityStream), 'Identity Crypt renderer image stream metadata should be present.');
        $t->true(is_array($identityBoundary), 'Identity Crypt DCT marker boundary should be present.');
        $t->same(['Crypt', 'DCTDecode'], $identityStream['filters'] ?? null);
        $t->same(['DCTDecode'], $identityStream['preview_only_filters'] ?? null);
        $t->same(['DCTDecode'], $identityStream['unsupported_filters'] ?? null);
        $t->same(false, $identityStream['decoded_with_current_filters'] ?? null);
        $t->same(false, $identityStream['decode_failed'] ?? null);
        $t->same(true, $identityStream['native_prefix_decoded'] ?? null);
        $t->same(strlen($identityFixture['jpeg']), $identityStream['native_prefix_decoded_length'] ?? null);
        $t->same('DCTDecode', $identityStream['stopped_before_filter'] ?? null);
        $t->same(true, $identityPreview['review_only_image_stream']);
        $t->same([], $identityPreview['pixels']);
        $t->same('dctdecode_jpeg_marker_boundary', $identityBoundary['source'] ?? null);
        $t->same(strlen($identityFixture['jpeg']), $identityBoundary['raw_stream_length'] ?? null);
        $t->same(strlen($identityFixture['jpeg']), $identityBoundary['review_stream_length'] ?? null);
        $t->same(true, $identityBoundary['sos_marker_seen'] ?? null);
        $t->same(true, $identityBoundary['byte_stuffed_ff00_seen'] ?? null);
        $t->same(true, $identityBoundary['restart_marker_seen'] ?? null);
        $t->same(false, $identityBoundary['payload_in_visible_text'] ?? null);
        $t->same(true, $identityBoundary['review_only'] ?? null);
        $t->same(false, $identityBoundary['native_raster_decode'] ?? null);

        $duplicateCases = [
            'identity then private' => '<< /Name /Identity /Name /PrivateCF >>',
            'private then identity' => '<< /Name /PrivateCF /Name /Identity >>',
            'duplicate identity' => '<< /Name /Identity /Name /Identity >>',
        ];
        foreach ($duplicateCases as $label => $decodeParms) {
            $fixture = $pdfDctDecodeDuplicateCryptNameRendererBoundaryImage($decodeParms);
            $preview = $renderer->iccBasedImageStreamPreviewRows($fixture['image'], $fixture['objects']);
            $imageStream = $preview['image_stream'] ?? null;
            $boundary = is_array($imageStream) ? ($imageStream['dctdecode_stream_boundary'] ?? null) : null;

            $t->true(is_array($imageStream), "{$label}: renderer image stream metadata should be present.");
            $t->true(is_array($boundary), "{$label}: DCT marker boundary remains review metadata.");
            $t->same(['Crypt', 'DCTDecode'], $imageStream['filters'] ?? null);
            $t->same(['DCTDecode'], $imageStream['preview_only_filters'] ?? null);
            $t->same(['Crypt', 'DCTDecode'], $imageStream['unsupported_filters'] ?? null);
            $t->same(false, $imageStream['decoded_with_current_filters'] ?? null);
            $t->same(true, $imageStream['decode_failed'] ?? null);
            $t->same(null, $imageStream['native_prefix_decoded'] ?? null);
            $t->same(null, $imageStream['native_prefix_decoded_length'] ?? null);
            $t->same(null, $imageStream['stopped_before_filter'] ?? null);
            $t->same(true, $preview['review_only_image_stream']);
            $t->same([], $preview['pixels']);
            $t->same('dctdecode_jpeg_marker_boundary', $boundary['source'] ?? null);
            $t->same(strlen($fixture['jpeg']), $boundary['raw_stream_length'] ?? null);
            $t->same(strlen($fixture['jpeg']), $boundary['review_stream_length'] ?? null);
            $t->same(true, $boundary['sos_marker_seen'] ?? null);
            $t->same(true, $boundary['byte_stuffed_ff00_seen'] ?? null);
            $t->same(true, $boundary['restart_marker_seen'] ?? null);
            $t->same(false, $boundary['payload_in_visible_text'] ?? null);
            $t->same(true, $boundary['review_only'] ?? null);
            $t->same(false, $boundary['native_raster_decode'] ?? null);
        }
    },
];
