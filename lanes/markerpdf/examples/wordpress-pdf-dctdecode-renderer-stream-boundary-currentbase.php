<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Renderer DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (Clean Renderer DCT Paragraph) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (Renderer DCT Raster Noise) Tj ET';
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
$scanPayload = "WordPress renderer media review scan data \xff\x00\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "restart marker remains renderer image bytes \xff\xd0";
$jpegPayload = "\xff\xd8"
    . $segment(0xc0, $sofPayload)
    . $segment(0xda, $sosPayload)
    . $scanPayload
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCT renderer smoke fixture must expose a fake endstream inside scan data.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$preview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$imageStream = is_array($preview['image_stream'] ?? null) ? $preview['image_stream'] : [];
$rendererBoundary = is_array($imageStream['dctdecode_stream_boundary'] ?? null)
    ? $imageStream['dctdecode_stream_boundary']
    : [];
$noiseExcluded = !str_contains($plainText, 'Renderer DCT Raster Noise')
    && !str_contains($plainText, 'WordPress renderer media review scan data')
    && !str_contains($plainText, 'endstream');

if (
    $lines !== ['Renderer DCT Import', 'Clean Renderer DCT Paragraph']
    || !$noiseExcluded
    || ($entry['raw_length'] ?? null) !== strlen($jpegPayload)
    || ($imageStream['raw_length'] ?? null) !== strlen($jpegPayload)
    || ($rendererBoundary['jpeg_marker_framing_used'] ?? null) !== true
    || ($rendererBoundary['sos_marker_seen'] ?? null) !== true
    || ($rendererBoundary['byte_stuffed_ff00_seen'] ?? null) !== true
    || ($rendererBoundary['restart_marker_seen'] ?? null) !== true
    || ($rendererBoundary['payload_in_visible_text'] ?? true) !== false
) {
    throw new RuntimeException('Renderer DCTDecode stream boundary metadata did not stay review-only before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-renderer-stream-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-renderer-boundary',
    'upstream_boundary' => 'marker.pdf.images.render_image',
    'stream_filters' => $imageStream['filters'] ?? [],
    'raw_length_recovered_past_fake_endstream' => ($imageStream['raw_length'] ?? 0) > $fakeTerminatorOffset,
    'renderer_dctdecode_jpeg_marker_framing_used' => $rendererBoundary['jpeg_marker_framing_used'] ?? false,
    'renderer_dctdecode_sos_marker_seen' => $rendererBoundary['sos_marker_seen'] ?? false,
    'renderer_dctdecode_byte_stuffed_ff00_seen' => $rendererBoundary['byte_stuffed_ff00_seen'] ?? false,
    'renderer_dctdecode_restart_marker_seen' => $rendererBoundary['restart_marker_seen'] ?? false,
    'dctdecode_image_payload_excluded_from_text' => $noiseExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
