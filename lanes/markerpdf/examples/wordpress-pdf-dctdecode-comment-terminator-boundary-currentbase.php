<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Commented DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Commented DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Commented DCT Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0bad segment before false EOI "
    . "\xff\xd9\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "still image bytes before actual boundary \xff\xd9";
$falseEoiEndOffset = strpos($jpegPayload, "\xff\xd9\nendstream\n");
if ($falseEoiEndOffset === false) {
    throw new RuntimeException('DCTDecode comment-boundary fixture must contain a false EOI before fake endstream.');
}
$staleLength = $falseEoiEndOffset + 2;
$commentBeforeTerminator = "\n% PDF comment before the real DCT stream terminator\n";
$streamPayload = $jpegPayload . $commentBeforeTerminator;

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$staleLength} >>\nstream\n{$streamPayload}endstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$staleLength} >>\nstream\n{$streamPayload}endstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$expected = ['Before Commented DCT Import', 'After Commented DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Commented DCT Leak')
    && !str_contains($plainText, 'bad segment before false EOI')
    && !str_contains($plainText, 'PDF comment before the real DCT stream terminator')
    && !str_contains($plainText, 'endstream');
$reviewClippedToJpeg = ($photoReview['raw_length'] ?? null) === strlen($jpegPayload)
    && ($photoReview['raw_length'] ?? 0) > $staleLength;
$rendererRecoveredPastStaleLength = (($rendererPreview['image_stream']['raw_length'] ?? 0) > $staleLength)
    && (($rendererPreview['image_stream']['raw_length'] ?? 0) < strlen($streamPayload));

if ($lines !== $expected || !$payloadExcluded || !$reviewClippedToJpeg || !$rendererRecoveredPastStaleLength) {
    throw new RuntimeException('DCTDecode comment terminator boundary leaked image/comment payload bytes into WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-comment-terminator-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-comment-terminator-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['DCTDecode'],
    'false_eoi_stale_length_rejected' => true,
    'pdf_comment_before_real_endstream_skipped' => true,
    'declared_length_stopped_at_false_eoi' => $staleLength,
    'raw_length_after_boundary_recovery' => $photoReview['raw_length'] ?? null,
    'renderer_raw_length_after_boundary_recovery' => $rendererPreview['image_stream']['raw_length'] ?? null,
    'renderer_recovered_past_stale_length' => $rendererRecoveredPastStaleLength,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_preview_only_filters' => $photoReview['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $photoReview['native_raster_decode'] ?? null,
    'xobject_decoded_with_current_filters' => $photoReview['decoded_with_current_filters'] ?? null,
    'renderer_preview_only_filters' => $rendererPreview['image_stream']['preview_only_filters'] ?? [],
    'renderer_decoded_with_current_filters' => $rendererPreview['image_stream']['decoded_with_current_filters'] ?? null,
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
