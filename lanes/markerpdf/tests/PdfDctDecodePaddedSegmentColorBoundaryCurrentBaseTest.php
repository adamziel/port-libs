<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

$pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseSegment = static function (int $marker, string $payload): string {
    return "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
};

$pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseJpeg = static function () use ($pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseSegment): string {
    $app14Payload = 'Adobe' . "\0\x64" . "\0\0" . "\0\0" . "\x02";
    $sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x04"
        . "\x01\x11\x00"
        . "\x02\x11\x00"
        . "\x03\x11\x00"
        . "\x04\x11\x00";

    return "\xef\xbb\xbf\xff\xff\xd8"
        . $pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseSegment(0xee, $app14Payload)
        . $pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseSegment(0xc0, $sofPayload)
        . "\xff\xd9";
};

return [
    'reads DCTDecode APP14 and SOF metadata after BOM and JPEG marker-fill bytes' => static function (
        TestRunner $t
    ) use ($pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseJpeg): void {
        $renderer = new PdfImageRenderer();
        $jpegBytes = $pdfDctDecodePaddedSegmentColorBoundaryCurrentBaseJpeg();
        $imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 0 >> >>';
        $rendererDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 0 >> >>';
        $imageObject = $rendererDictionary . "\nstream\n{$jpegBytes}\nendstream";

        $plan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegBytes);
        $preview = $renderer->iccBasedImageStreamPreviewRows($imageObject, [
            30 => "<< /N 4 /Alternate /DeviceCMYK /Length 7 >>\nstream\nPROFILE\nendstream",
        ]);
        $boundary = $preview['image_stream']['dctdecode_stream_boundary'] ?? null;

        $t->same('DCTDecode', $plan['filter']);
        $t->same('DeviceCMYK', $plan['source_color_space']);
        $t->same(4, $plan['components']);
        $t->same(2, $plan['adobe_app14_transform']);
        $t->same(0, $plan['decode_parms_color_transform']);
        $t->same(true, $plan['decode_parms_color_transform_valid']);
        $t->same(2, $plan['effective_color_transform']);
        $t->same(true, $plan['adobe_marker_overrides_decode_parms']);
        $t->same(true, $plan['needs_cmyk_to_rgb']);
        $t->same(true, $plan['uses_ycck_transform']);
        $t->contains('adobe_app14_transform_overrides_decodeparms', implode(',', $plan['notes']));
        $t->contains('apply_ycck_to_cmyk_before_rgb', implode(',', $plan['notes']));

        $t->same(true, $preview['review_only_image_stream']);
        $t->same(['DCTDecode'], $preview['image_stream']['filters']);
        $t->same(['DCTDecode'], $preview['image_stream']['preview_only_filters']);
        $t->same(strlen($jpegBytes), $preview['image_stream']['raw_length']);
        $t->same(false, $preview['image_stream']['decoded_with_current_filters']);
        $t->same(false, $preview['image_stream']['decode_failed']);
        $t->true(is_array($boundary), 'DCTDecode stream boundary should be present for padded JPEG bytes.');
        $t->same(3, $boundary['jpeg_soi_offset'] ?? null);
        $t->same(1, $boundary['jpeg_marker_fill_byte_count'] ?? null);
        $t->same(strlen($jpegBytes), $boundary['jpeg_eoi_end_offset'] ?? null);
        $t->same(false, $boundary['native_raster_decode'] ?? null);
        $t->same([], $preview['pixels']);
    },
];
