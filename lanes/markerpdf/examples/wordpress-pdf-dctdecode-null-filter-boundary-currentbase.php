<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode null-filter smoke payload must fit one stored deflate block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before Null DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Null DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Null DCT Payload Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$compressedPayload = $zlibStored($jpegPayload);
$fakeTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode null-filter smoke must expose a fake endstream marker.');
}

$filterStack = '[ null /FlateDecode null /DCTDecode ]';
$decodeParms = '[ 99 0 R null 100 0 R << /ColorTransform 1 >> ]';
$content = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress Null DCT Payload Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$expected = ['Before Null DCT Import', 'After Null DCT Import'];
$boundaryRecovered = ($entry['raw_length'] ?? null) === strlen($compressedPayload)
    && ($entry['raw_length'] ?? 0) > $fakeTerminatorOffset
    && ($entry['filters'] ?? []) === ['FlateDecode', 'DCTDecode']
    && ($entry['preview_only_filters'] ?? []) === ['DCTDecode']
    && ($entry['native_raster_decode'] ?? true) === false;

if ($lines !== $expected || !$payloadExcluded || !$boundaryRecovered) {
    throw new RuntimeException('Null-filter DCTDecode image payload leaked into WordPress text.');
}

echo '<!-- markerpdf:pdf-dctdecode-null-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-null-filter-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['FlateDecode', 'DCTDecode'],
    'filter_stack_has_null_slots' => true,
    'decodeparms_null_slots_ignored_before_dct_boundary' => true,
    'stale_decodeparms_operands_on_null_slots' => ['99 0 R', '100 0 R'],
    'declared_length_stopped_at_fake_endstream' => $fakeTerminatorOffset,
    'raw_length_after_boundary_recovery' => $entry['raw_length'] ?? null,
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
