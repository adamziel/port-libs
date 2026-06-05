<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode native-prefix unsupported smoke payload must fit one stored deflate block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before Native Prefix DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Native Prefix DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Native Prefix Unsupported DCT Payload Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$compressedPayload = $zlibStored($jpegPayload);
$fakeCompressedTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
if ($fakeCompressedTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode native-prefix unsupported smoke must expose a fake compressed endstream marker.');
}

$filterStack = '[/FlateDecode /Crypt /DCTDecode]';
$decodeParms = '[null null null]';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeCompressedTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$imageObject = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeCompressedTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$preview = $renderer->iccBasedImageStreamPreviewRows($imageObject, $rendererObjects);

$expected = ['Before Native Prefix DCT Import', 'After Native Prefix DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Native Prefix Unsupported DCT Payload Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$xobjectRecovered = ($entry['raw_length'] ?? null) === strlen($compressedPayload)
    && (($entry['raw_length'] ?? 0) > $fakeCompressedTerminatorOffset);
$rendererRecovered = ($preview['image_stream']['raw_length'] ?? null) === strlen($compressedPayload)
    && (($preview['image_stream']['raw_length'] ?? 0) > $fakeCompressedTerminatorOffset);
$unsupportedFailClosed = ($preview['image_stream']['unsupported_filters'] ?? []) === ['Crypt', 'DCTDecode']
    && ($preview['image_stream']['decode_failed'] ?? false) === true
    && ($preview['image_stream']['decoded_with_current_filters'] ?? true) === false
    && ($preview['image_stream']['stopped_before_filter'] ?? null) === 'Crypt'
    && ($preview['review_only_image_stream'] ?? false) === true
    && ($preview['pixels'] ?? []) === [];

if (
    $lines !== $expected
    || !$payloadExcluded
    || !$xobjectRecovered
    || !$rendererRecovered
    || !$unsupportedFailClosed
    || ($preview['image_stream']['native_prefix_decoded'] ?? false) !== true
    || ($preview['image_stream']['native_prefix_decoded_length'] ?? null) !== strlen($jpegPayload)
) {
    throw new RuntimeException('DCTDecode native-prefix unsupported boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-dctdecode-native-prefix-unsupported-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-native-prefix-unsupported-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image direct image stream metadata',
    'stream_filters' => ['FlateDecode', 'Crypt', 'DCTDecode'],
    'text_paragraphs' => $lines,
    'xobject_raw_length_recovered' => $xobjectRecovered,
    'renderer_raw_length_recovered' => $rendererRecovered,
    'native_prefix_decoded_before_unsupported_filter' => true,
    'unsupported_middle_filter_fail_closed' => $unsupportedFailClosed,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'renderer_preview_only_filters' => $preview['image_stream']['preview_only_filters'] ?? [],
    'renderer_unsupported_filters' => $preview['image_stream']['unsupported_filters'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
