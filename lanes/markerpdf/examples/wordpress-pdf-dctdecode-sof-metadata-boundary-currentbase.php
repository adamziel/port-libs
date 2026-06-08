<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$segment = static function (int $marker, string $payload): string {
    return "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
};

$before = 'BT /F1 12 Tf 72 720 Td (DCT SOF Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (Clean DCT SOF Paragraph) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (DCT SOF Raster Metadata Noise) Tj ET';
$fakeSofPayload = "\x08" . pack('n', 99) . pack('n', 99) . "\x01" . "\x01\x11\x00";
$appPayload = "JFIF\0\1\1\0\0\1\0\1\0\0"
    . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . $segment(0xc0, $fakeSofPayload);
$sofPayload = "\x08" . pack('n', 23) . pack('n', 37) . "\x03"
    . "\x01\x11\x00"
    . "\x02\x11\x00"
    . "\x03\x11\x00";
$sosPayload = "\x03"
    . "\x01\x00"
    . "\x02\x11"
    . "\x03\x11"
    . "\x00\x3f\x00";
$jpegPayload = "\xff\xd8"
    . $segment(0xe0, $appPayload)
    . $segment(0xc0, $sofPayload)
    . $segment(0xda, $sosPayload)
    . "WordPress media entropy stays image only"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCT SOF smoke fixture must expose a fake endstream inside APP metadata.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$rendererImageObject = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? []) : [];
$preview = $renderer->iccBasedImageStreamPreviewRows($rendererImageObject, $rendererObjects);
$rendererBoundary = $preview['image_stream']['dctdecode_stream_boundary'] ?? [];
$noiseExcluded = !str_contains($plainText, 'DCT SOF Raster Metadata Noise')
    && !str_contains($plainText, 'WordPress media entropy')
    && !str_contains($plainText, 'endstream');
$sofMetadataRecovered = ($boundary['sof_marker_seen'] ?? null) === true
    && ($boundary['jpeg_sof_marker'] ?? null) === 'SOF0'
    && ($boundary['jpeg_precision'] ?? null) === 8
    && ($boundary['jpeg_width'] ?? null) === 37
    && ($boundary['jpeg_height'] ?? null) === 23
    && ($boundary['jpeg_component_count'] ?? null) === 3
    && ($rendererBoundary['jpeg_width'] ?? null) === 37
    && ($rendererBoundary['jpeg_height'] ?? null) === 23;

if (
    $lines !== ['DCT SOF Import', 'Clean DCT SOF Paragraph']
    || !$noiseExcluded
    || !$sofMetadataRecovered
    || ($entry['raw_length'] ?? null) !== strlen($jpegPayload)
    || (($entry['raw_length'] ?? 0) <= $fakeTerminatorOffset)
    || ($entry['native_raster_decode'] ?? null) !== false
    || ($preview['image_stream']['decoded_with_current_filters'] ?? null) !== false
) {
    throw new RuntimeException('DCTDecode SOF metadata boundary did not stay review-only before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-sof-metadata-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-sof-metadata-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image image-size handoff',
    'stream_filters' => $entry['filters'] ?? [],
    'raw_length_recovered_past_fake_endstream' => ($entry['raw_length'] ?? 0) > $fakeTerminatorOffset,
    'dctdecode_sof_marker_seen' => $boundary['sof_marker_seen'] ?? false,
    'dctdecode_sof_marker' => $boundary['jpeg_sof_marker'] ?? null,
    'dctdecode_jpeg_precision' => $boundary['jpeg_precision'] ?? null,
    'dctdecode_jpeg_width' => $boundary['jpeg_width'] ?? null,
    'dctdecode_jpeg_height' => $boundary['jpeg_height'] ?? null,
    'dctdecode_jpeg_component_count' => $boundary['jpeg_component_count'] ?? null,
    'renderer_jpeg_width' => $rendererBoundary['jpeg_width'] ?? null,
    'renderer_jpeg_height' => $rendererBoundary['jpeg_height'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $noiseExcluded,
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'renderer_decoded_with_current_filters' => $preview['image_stream']['decoded_with_current_filters'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
