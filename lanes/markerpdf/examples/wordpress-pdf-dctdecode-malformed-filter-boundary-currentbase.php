<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Malformed DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Malformed DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Malformed DCT Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('Malformed DCT filter smoke must expose a fake endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [[/DCTDecode]] /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [ /ICCBased 30 0 R ] /BitsPerComponent 8 /Filter [[/DCTDecode]] /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$payloadExcluded = !str_contains($plainText, 'WordPress Malformed DCT Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$recoveredLength = ($photoReview['raw_length'] ?? null) === strlen($jpegPayload)
    && ($photoReview['raw_length'] ?? 0) > $fakeTerminatorOffset;
$failedClosed = ($photoReview['filters_resolved'] ?? true) === false
    && ($photoReview['native_raster_decode'] ?? true) === false
    && ($photoReview['decoded_with_current_filters'] ?? true) === false;
$rendererRawBoundary = ($rendererPreview['review_only_image_stream'] ?? false) === true
    && ($rendererPreview['image_stream']['raw_dct_preview_boundary'] ?? false) === true
    && ($rendererPreview['image_stream']['filters'] ?? []) === ['MalformedFilterOperand']
    && ($rendererPreview['image_stream']['raw_length'] ?? null) === strlen($jpegPayload)
    && ($rendererPreview['image_stream']['decoded_with_current_filters'] ?? true) === false;

if (
    $lines !== ['Before Malformed DCT Import', 'After Malformed DCT Import']
    || !$payloadExcluded
    || !$recoveredLength
    || !$failedClosed
    || !$rendererRawBoundary
) {
    throw new RuntimeException('Malformed DCTDecode filter boundary leaked image payload bytes or allowed native raster decode.');
}

echo '<!-- markerpdf:pdf-dctdecode-malformed-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-malformed-filter-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters_resolved' => $photoReview['filters_resolved'] ?? null,
    'malformed_filter_operand_fail_closed' => $failedClosed,
    'raw_jpeg_owner_boundary_used_for_review_only_stream' => true,
    'renderer_raw_dct_preview_boundary' => $rendererRawBoundary,
    'stale_length_fake_endstream_rejected' => $recoveredLength,
    'embedded_fake_object_rejected' => $payloadExcluded,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_filters' => $photoReview['filters'] ?? [],
    'xobject_preview_only_filters' => $photoReview['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $photoReview['native_raster_decode'] ?? null,
    'xobject_decoded_with_current_filters' => $photoReview['decoded_with_current_filters'] ?? null,
    'renderer_image_stream_filters' => $rendererPreview['image_stream']['filters'] ?? [],
    'renderer_image_stream_unsupported_filters' => $rendererPreview['image_stream']['unsupported_filters'] ?? [],
    'renderer_image_stream_raw_length' => $rendererPreview['image_stream']['raw_length'] ?? null,
    'renderer_image_stream_decode_failed' => $rendererPreview['image_stream']['decode_failed'] ?? null,
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
