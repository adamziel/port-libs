<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before DCT Stream) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After DCT Stream) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (JPEG Payload Object Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9\0\0";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCT stream terminator fixture must contain an embedded fake endstream marker.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
$ascii85WrappedPayload = "<~endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n~>";
$prefixPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/A85 /DCTDecode] >>\nstream\n{$ascii85WrappedPayload}\nendstream\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$prefixLines = $extractor->extractTextLines($prefixPdf);
$prefixPlainText = $extractor->extractPlainText($prefixPdf);
$payloadExcluded = !str_contains($plainText, 'JPEG Payload Object Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$prefixPayloadExcluded = !str_contains($prefixPlainText, 'JPEG Payload Object Leak')
    && !str_contains($prefixPlainText, 'endstream');

if (
    $lines !== ['Before DCT Stream', 'After DCT Stream']
    || $prefixLines !== ['Before DCT Stream', 'After DCT Stream']
    || !$payloadExcluded
    || !$prefixPayloadExcluded
) {
    throw new RuntimeException('DCTDecode stream terminator boundary leaked JPEG payload bytes into WordPress text.');
}

echo '<!-- markerpdf:pdf-dctdecode-stream-terminator-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-stream-terminator-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks',
    'stream_filters' => ['DCTDecode'],
    'jpeg_soi_eoi_delimiter_guard' => true,
    'nul_padded_jpeg_eoi_boundary' => true,
    'prefix_filter_eod_guard' => true,
    'stale_length_fake_endstream_rejected' => true,
    'embedded_fake_object_rejected' => $payloadExcluded && $prefixPayloadExcluded,
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
