<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode Flate-prefix smoke payload must fit one stored deflate block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before Flate DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Flate DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Flate DCT Payload Leak) Tj ET';
$jpegPayload = "\0\0\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9\0\0";
$compressedPayload = $zlibStored($jpegPayload);
$fakeTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode Flate-prefix smoke must expose a fake endstream marker.');
}

$buildPdf = static function (?int $declaredLength) use ($before, $after, $compressedPayload): string {
    $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode]{$lengthOperand} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$missingLengthPdf = $buildPdf(null);
$staleLengthPdf = $buildPdf($fakeTerminatorOffset);
$missingLengthLines = $extractor->extractTextLines($missingLengthPdf);
$staleLengthLines = $extractor->extractTextLines($staleLengthPdf);
$missingLengthText = $extractor->extractPlainText($missingLengthPdf);
$staleLengthText = $extractor->extractPlainText($staleLengthPdf);
$expected = ['Before Flate DCT Import', 'After Flate DCT Import'];
$missingLengthPayloadExcluded = !str_contains($missingLengthText, 'WordPress Flate DCT Payload Leak')
    && !str_contains($missingLengthText, 'JFIF')
    && !str_contains($missingLengthText, 'endstream');
$staleLengthPayloadExcluded = !str_contains($staleLengthText, 'WordPress Flate DCT Payload Leak')
    && !str_contains($staleLengthText, 'JFIF')
    && !str_contains($staleLengthText, 'endstream');

if (
    $missingLengthLines !== $expected
    || $staleLengthLines !== $expected
    || !$missingLengthPayloadExcluded
    || !$staleLengthPayloadExcluded
) {
    throw new RuntimeException('Flate-wrapped DCTDecode image payload leaked into WordPress text.');
}

echo '<!-- markerpdf:pdf-dctdecode-flate-prefix-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-flate-prefix-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['FlateDecode', 'DCTDecode'],
    'prefix_filter_decoded_before_dct_boundary' => true,
    'prefix_decoded_nul_padded_jpeg_boundary' => true,
    'missing_length_fake_endstream_rejected' => $missingLengthPayloadExcluded,
    'stale_length_fake_endstream_rejected' => $staleLengthPayloadExcluded,
    'dctdecode_image_payload_excluded_from_text' => $missingLengthPayloadExcluded && $staleLengthPayloadExcluded,
    'paragraphs' => $missingLengthLines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($missingLengthLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
