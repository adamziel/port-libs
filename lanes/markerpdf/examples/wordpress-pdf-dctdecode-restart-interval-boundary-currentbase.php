<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (DCT Restart Interval Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (Clean DCT Restart Interval Paragraph) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (DCT Restart Interval Raster Noise) Tj ET';
$segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker)
    . pack('n', strlen($payload) + 2)
    . $payload;
$appPayload = "WordPress media APP bytes with marker-looking restart \xff\xd0 and stuffed \xff\x00 data\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . 'still inside the DCT image payload';
$jpegPayload = "\xff\xd8"
    . $segment(0xe0, $appPayload)
    . "\xff\xdd\x00\x04\x00\x04"
    . $segment(0xda, "\x01\x01\x00\x00\x3f\x00")
    . 'scan data without restart markers'
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCT restart-interval smoke fixture must expose a fake endstream marker.');
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
$entry = is_array($review['entries'][0] ?? null) ? $review['entries'][0] : [];
$extractorBoundary = is_array($entry['dctdecode_stream_boundary'] ?? null)
    ? $entry['dctdecode_stream_boundary']
    : [];
$preview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$imageStream = is_array($preview['image_stream'] ?? null) ? $preview['image_stream'] : [];
$rendererBoundary = is_array($imageStream['dctdecode_stream_boundary'] ?? null)
    ? $imageStream['dctdecode_stream_boundary']
    : [];
$payloadExcluded = !str_contains($plainText, 'DCT Restart Interval Raster Noise')
    && !str_contains($plainText, 'WordPress media APP bytes')
    && !str_contains($plainText, 'endstream');
$expectedLines = ['DCT Restart Interval Import', 'Clean DCT Restart Interval Paragraph'];

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || ($entry['raw_length'] ?? null) !== strlen($jpegPayload)
    || ($imageStream['raw_length'] ?? null) !== strlen($jpegPayload)
    || ($extractorBoundary['dri_marker_seen'] ?? null) !== true
    || ($rendererBoundary['dri_marker_seen'] ?? null) !== true
    || ($extractorBoundary['jpeg_restart_interval'] ?? null) !== 4
    || ($rendererBoundary['jpeg_restart_interval'] ?? null) !== 4
    || ($extractorBoundary['restart_marker_seen'] ?? null) !== false
    || ($rendererBoundary['restart_marker_seen'] ?? null) !== false
    || ($extractorBoundary['byte_stuffed_ff00_seen'] ?? null) !== false
    || ($rendererBoundary['byte_stuffed_ff00_seen'] ?? null) !== false
    || ($extractorBoundary['payload_in_visible_text'] ?? true) !== false
    || ($rendererBoundary['payload_in_visible_text'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode restart-interval boundary smoke failed before WordPress import handoff.');
}

echo '<!-- markerpdf:pdf-dctdecode-restart-interval-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-restart-interval-boundary',
    'upstream_boundary' => 'marker.pdf.images.render_image',
    'stream_filters' => $imageStream['filters'] ?? [],
    'dri_marker_seen' => ($extractorBoundary['dri_marker_seen'] ?? null) === true
        && ($rendererBoundary['dri_marker_seen'] ?? null) === true,
    'jpeg_restart_interval' => $extractorBoundary['jpeg_restart_interval'] ?? null,
    'app_payload_marker_bytes_rejected' => ($extractorBoundary['restart_marker_seen'] ?? null) === false
        && ($rendererBoundary['restart_marker_seen'] ?? null) === false
        && ($extractorBoundary['byte_stuffed_ff00_seen'] ?? null) === false
        && ($rendererBoundary['byte_stuffed_ff00_seen'] ?? null) === false,
    'stale_length_fake_endstream_rejected' => ($entry['raw_length'] ?? 0) > $fakeTerminatorOffset
        && ($imageStream['raw_length'] ?? 0) > $fakeTerminatorOffset,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
