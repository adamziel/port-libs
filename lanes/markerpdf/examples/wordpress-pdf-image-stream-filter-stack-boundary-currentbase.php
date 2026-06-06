<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before image filter tail review) Tj ET\n"
    . "q 16 0 0 8 72 690 cm /Unsafe#20Image Do Q\n"
    . "q 12 0 0 6 104 690 cm /Clean#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After image filter tail review) Tj ET';
$unsafePayload = 'RGBTAIL';
$cleanPayload = 'RGBOK';
$unsafeCompressed = gzcompress($unsafePayload);
$cleanCompressed = gzcompress($cleanPayload);

if (!is_string($unsafeCompressed) || !is_string($cleanCompressed)) {
    throw new RuntimeException('Unable to compress focused image stream-filter smoke fixture.');
}

$unsafeTail = 'BT /F1 12 Tf 72 680 Td (Unsafe Image Tail Payload Leak) Tj ET';
$unsafeStream = $unsafeCompressed . $unsafeTail;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Unsafe#20Image 5 0 R /Clean#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unsafeStream) . " >>\nstream\n{$unsafeStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($cleanCompressed) . " >>\nstream\n{$cleanCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$unsafe = $entriesByName['Unsafe Image'] ?? null;
$clean = $entriesByName['Clean Image'] ?? null;
if (!is_array($unsafe) || !is_array($clean)) {
    throw new RuntimeException('Expected both image resources in image stream-filter stack smoke.');
}

$unsafeRejected = ($unsafe['decoded_with_current_filters'] ?? null) === false
    && ($unsafe['native_raster_decode'] ?? null) === false
    && ($unsafe['decoded_sha256'] ?? null) === null;
$cleanPreserved = ($clean['decoded_with_current_filters'] ?? null) === true
    && ($clean['native_raster_decode'] ?? null) === true
    && ($clean['decoded_sha256'] ?? null) === hash('sha256', $cleanPayload);
$visibleTextPreserved = $plainText === "Before image filter tail review\nAfter image filter tail review";
$payloadExcluded = !str_contains($plainText, 'Unsafe Image Tail Payload Leak')
    && !str_contains($plainText, $unsafePayload)
    && !str_contains($plainText, $cleanPayload);

if (!$unsafeRejected || !$cleanPreserved || !$visibleTextPreserved || !$payloadExcluded) {
    throw new RuntimeException('Image stream-filter stack boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-stream-filter-stack-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-image-stream-filter-stack-boundary',
    'native_boundary' => 'Image XObject review requires Flate stream data to end at the compressed member before native raster decode can be considered.',
    'unsafe_image_filter_tail_rejected' => $unsafeRejected,
    'clean_image_filter_preserved' => $cleanPreserved,
    'native_raster_decode_blocked_for_unsafe_tail' => ($unsafe['native_raster_decode'] ?? null) === false,
    'visible_text_preserved' => $visibleTextPreserved,
    'payload_excluded_from_text' => $payloadExcluded,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'unsafe_raw_length' => $unsafe['raw_length'] ?? null,
    'unsafe_decoded_length' => $unsafe['decoded_length'] ?? null,
    'clean_decoded_length' => $clean['decoded_length'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (explode("\n", $plainText) as $line) {
    echo '<!-- wp:paragraph -->' . "\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo '<!-- /wp:paragraph -->' . "\n";
}
