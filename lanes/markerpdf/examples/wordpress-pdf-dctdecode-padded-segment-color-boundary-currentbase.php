<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$segment = static function (int $marker, string $payload): string {
    return "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
};

$app14Payload = 'Adobe' . "\0\x64" . "\0\0" . "\0\0" . "\x02";
$sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x04"
    . "\x01\x11\x00"
    . "\x02\x11\x00"
    . "\x03\x11\x00"
    . "\x04\x11\x00";
$jpegBytes = "\xef\xbb\xbf\xff\xff\xd8"
    . $segment(0xee, $app14Payload)
    . $segment(0xc0, $sofPayload)
    . "\xff\xd9";

$before = 'BT /F1 12 Tf 72 720 Td (Before padded DCT color import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After padded DCT color import) Tj ET';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 0 >> /Length ' . strlen($jpegBytes) . ' >>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegBytes}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererImageDictionary = str_replace('/ColorSpace /DeviceCMYK', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
$rendererImage = "{$rendererImageDictionary}\nstream\n{$jpegBytes}\nendstream";
$rendererObjects = [
    30 => "<< /N 4 /Alternate /DeviceCMYK /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegBytes);
$preview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$boundary = $preview['image_stream']['dctdecode_stream_boundary'] ?? [];

$expectedLines = [
    'Before padded DCT color import',
    'After padded DCT color import',
];
$payloadExcluded = !str_contains($plainText, 'Adobe')
    && !str_contains($plainText, 'ColorTransform')
    && !str_contains($plainText, 'endstream');
$segmentMetadataRecovered = ($plan['components'] ?? null) === 4
    && ($plan['adobe_app14_transform'] ?? null) === 2
    && ($plan['decode_parms_color_transform'] ?? null) === 0
    && ($plan['effective_color_transform'] ?? null) === 2
    && ($plan['adobe_marker_overrides_decode_parms'] ?? false) === true
    && ($plan['uses_ycck_transform'] ?? false) === true;
$streamBoundaryRecovered = ($boundary['jpeg_soi_offset'] ?? null) === 3
    && ($boundary['jpeg_marker_fill_byte_count'] ?? null) === 1
    && ($boundary['jpeg_eoi_end_offset'] ?? null) === strlen($jpegBytes);

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || !$segmentMetadataRecovered
    || !$streamBoundaryRecovered
    || ($entry['native_raster_decode'] ?? null) !== false
    || ($preview['review_only_image_stream'] ?? null) !== true
) {
    throw new RuntimeException('Padded DCTDecode segment color boundary did not preserve WordPress-safe image review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-padded-segment-color-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-padded-segment-color-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image RGB conversion handoff',
    'stream_filters' => ['DCTDecode'],
    'jpeg_soi_offset' => $boundary['jpeg_soi_offset'] ?? null,
    'jpeg_marker_fill_byte_count' => $boundary['jpeg_marker_fill_byte_count'] ?? null,
    'jpeg_components' => $plan['components'] ?? null,
    'adobe_app14_transform' => $plan['adobe_app14_transform'] ?? null,
    'decodeparms_color_transform' => $plan['decode_parms_color_transform'] ?? null,
    'effective_color_transform' => $plan['effective_color_transform'] ?? null,
    'adobe_marker_overrides_decodeparms' => $plan['adobe_marker_overrides_decode_parms'] ?? null,
    'uses_ycck_transform' => $plan['uses_ycck_transform'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'renderer_preview_only_filters' => $preview['image_stream']['preview_only_filters'] ?? [],
    'renderer_decoded_with_current_filters' => $preview['image_stream']['decoded_with_current_filters'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
