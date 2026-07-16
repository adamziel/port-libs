<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (DCT Marker Fill Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (Clean DCT Marker Fill Paragraph) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (DCT Marker Fill Raster Noise) Tj ET';
$appPayload = "WordPress media import APP bytes before fake stream boundaries\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . 'still owned by the DCT image payload';
$jpegPayload = "\xff\xff\xd8"
    . "\xff\xe0" . pack('n', strlen($appPayload) + 2) . $appPayload
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCT marker-fill smoke fixture must expose a fake endstream marker.');
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
$payloadExcluded = !str_contains($plainText, 'DCT Marker Fill Raster Noise')
    && !str_contains($plainText, 'WordPress media import APP bytes')
    && !str_contains($plainText, 'endstream');
$expectedLines = ['DCT Marker Fill Import', 'Clean DCT Marker Fill Paragraph'];

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || ($entry['raw_length'] ?? null) !== strlen($jpegPayload)
    || ($imageStream['raw_length'] ?? null) !== strlen($jpegPayload)
    || ($extractorBoundary['jpeg_marker_fill_byte_count'] ?? null) !== 1
    || ($rendererBoundary['jpeg_marker_fill_byte_count'] ?? null) !== 1
    || ($extractorBoundary['jpeg_marker_framing_used'] ?? null) !== true
    || ($rendererBoundary['jpeg_marker_framing_used'] ?? null) !== true
    || ($extractorBoundary['payload_in_visible_text'] ?? true) !== false
    || ($rendererBoundary['payload_in_visible_text'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode marker-fill boundary smoke failed before WordPress import handoff.');
}

echo '<!-- markerpdf:pdf-dctdecode-marker-fill-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-marker-fill-boundary',
    'upstream_boundary' => 'marker.pdf.images.render_image',
    'stream_filters' => $imageStream['filters'] ?? [],
    'jpeg_marker_fill_soi_accepted' => ($extractorBoundary['jpeg_marker_fill_byte_count'] ?? null) === 1
        && ($rendererBoundary['jpeg_marker_fill_byte_count'] ?? null) === 1,
    'stale_length_fake_endstream_rejected' => ($entry['raw_length'] ?? 0) > $fakeTerminatorOffset
        && ($imageStream['raw_length'] ?? 0) > $fakeTerminatorOffset,
    'xobject_raw_length_recovered' => ($entry['raw_length'] ?? null) === strlen($jpegPayload),
    'renderer_raw_length_recovered' => ($imageStream['raw_length'] ?? null) === strlen($jpegPayload),
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
