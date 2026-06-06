<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode Flate-prefix marker smoke payload must fit one stored deflate block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
$before = 'BT /F1 12 Tf 72 720 Td (Before Flate DCT Marker Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Flate DCT Marker Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Flate DCT Marker Leak) Tj ET';
$sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x03"
    . "\x01\x11\x00"
    . "\x02\x11\x00"
    . "\x03\x11\x00";
$sosPayload = "\x03"
    . "\x01\x00"
    . "\x02\x11"
    . "\x03\x11"
    . "\x00\x3f\x00";
$scanPayload = "flate-prefix DCT JPEG scan bytes \xff\x00\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "restart marker remains image-owned \xff\xd0";
$jpegPayload = "\xff\xd8"
    . $segment(0xc0, $sofPayload)
    . $segment(0xda, $sosPayload)
    . $scanPayload
    . "\xff\xd9";
$compressedPayload = $zlibStored($jpegPayload);
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode] /Length ' . strlen($compressedPayload) . ' >>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? []) : [];
$rendererImage = str_replace('/DeviceRGB', '30 0 R', $imageDictionary)
    . "\nstream\n{$compressedPayload}\nendstream";
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
]);
$rendererBoundary = $rendererPreview['image_stream']['dctdecode_stream_boundary'] ?? [];

$expected = ['Before Flate DCT Marker Import', 'After Flate DCT Marker Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Flate DCT Marker Leak')
    && !str_contains($plainText, 'flate-prefix DCT JPEG scan bytes')
    && !str_contains($plainText, 'endstream');
$prefixBoundaryRecorded = is_array($boundary)
    && ($boundary['review_stream_decoded_from_native_prefix'] ?? false) === true
    && ($boundary['native_prefix_filters'] ?? []) === ['FlateDecode']
    && ($boundary['stopped_before_filter'] ?? null) === 'DCTDecode'
    && ($boundary['review_stream_length'] ?? null) === strlen($jpegPayload)
    && ($boundary['sos_marker_seen'] ?? false) === true
    && ($boundary['byte_stuffed_ff00_seen'] ?? false) === true
    && ($boundary['restart_marker_seen'] ?? false) === true;
$rendererBoundaryRecorded = is_array($rendererBoundary)
    && ($rendererBoundary['review_stream_decoded_from_native_prefix'] ?? false) === true
    && ($rendererBoundary['native_prefix_filters'] ?? []) === ['FlateDecode']
    && ($rendererBoundary['review_stream_length'] ?? null) === strlen($jpegPayload);

if ($lines !== $expected || !$payloadExcluded || !$prefixBoundaryRecorded || !$rendererBoundaryRecorded) {
    throw new RuntimeException('Flate-prefix DCTDecode marker boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-flate-prefix-marker-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-flate-prefix-marker-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['FlateDecode', 'DCTDecode'],
    'native_prefix_filters' => $boundary['native_prefix_filters'] ?? [],
    'stopped_before_filter' => $boundary['stopped_before_filter'] ?? null,
    'review_stream_decoded_from_native_prefix' => $boundary['review_stream_decoded_from_native_prefix'] ?? false,
    'jpeg_marker_framing_used' => $boundary['jpeg_marker_framing_used'] ?? false,
    'sos_marker_seen' => $boundary['sos_marker_seen'] ?? false,
    'byte_stuffed_ff00_seen' => $boundary['byte_stuffed_ff00_seen'] ?? false,
    'restart_marker_seen' => $boundary['restart_marker_seen'] ?? false,
    'renderer_boundary_recorded' => $rendererBoundaryRecorded,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
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
