<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Pre-SOI DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Pre-SOI DCT Import) Tj ET';
$preSoiLeak = 'BT /F1 12 Tf 72 700 Td (WordPress Pre-SOI DCT Leak) Tj ET';
$appPayload = "JFIF\0pre-soi!";
$jpegPayload = $preSoiLeak
    . "\n% fake PDF text before JPEG SOI stays image-owned\n"
    . "\xff\xd8"
    . "\xff\xe0" . pack('n', strlen($appPayload) + 2) . $appPayload
    . "\xff\xd9";
$soiOffset = strpos($jpegPayload, "\xff\xd8");
if ($soiOffset === false) {
    throw new RuntimeException('Pre-SOI DCT smoke fixture must contain a JPEG SOI marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegPayload) . " >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegPayload) . " >>\nstream\n{$jpegPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry['dctdecode_stream_boundary'] ?? null) ? $entry['dctdecode_stream_boundary'] : [];
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$rendererBoundary = is_array($rendererPreview['image_stream']['dctdecode_stream_boundary'] ?? null)
    ? $rendererPreview['image_stream']['dctdecode_stream_boundary']
    : [];

$expected = ['Before Pre-SOI DCT Import', 'After Pre-SOI DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Pre-SOI DCT Leak')
    && !str_contains($plainText, 'fake PDF text before JPEG SOI')
    && !str_contains($plainText, 'JFIF');
$preSoiBoundaryRecorded = ($boundary['source'] ?? null) === 'dctdecode_jpeg_marker_boundary_unverified'
    && ($boundary['invalid_reason'] ?? null) === 'pre_jpeg_soi_non_padding_bytes'
    && ($boundary['jpeg_soi_offset'] ?? null) === $soiOffset
    && ($boundary['pre_jpeg_soi_byte_count'] ?? null) === $soiOffset
    && ($boundary['pre_jpeg_soi_payload_in_visible_text'] ?? true) === false;
$rendererPreSoiBoundaryRecorded = ($rendererBoundary['invalid_reason'] ?? null) === 'pre_jpeg_soi_non_padding_bytes'
    && ($rendererBoundary['pre_jpeg_soi_byte_count'] ?? null) === $soiOffset
    && ($rendererBoundary['pre_jpeg_soi_payload_in_visible_text'] ?? true) === false;

if (
    $lines !== $expected
    || !$payloadExcluded
    || !$preSoiBoundaryRecorded
    || !$rendererPreSoiBoundaryRecorded
    || ($entry['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($entry['native_raster_decode'] ?? true) !== false
    || ($rendererPreview['image_stream']['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode pre-SOI boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-pre-soi-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-pre-soi-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image review-only image payloads',
    'stream_filters' => ['DCTDecode'],
    'paragraphs' => $lines,
    'pre_jpeg_soi_byte_count' => $boundary['pre_jpeg_soi_byte_count'] ?? null,
    'pre_jpeg_soi_invalid_reason' => $boundary['invalid_reason'] ?? null,
    'renderer_pre_jpeg_soi_invalid_reason' => $rendererBoundary['invalid_reason'] ?? null,
    'pre_jpeg_soi_payload_excluded_from_text' => $payloadExcluded,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
