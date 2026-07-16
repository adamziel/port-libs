<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before trailing null DCT filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After trailing null DCT filter) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress trailing null DCT payload leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCT trailing-null fixture must expose a fake endstream marker.');
}

$filterStack = '[/DCTDecode null]';
$decodeParms = '[<< /ColorTransform 2 >> 99 0 R]';
$content = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterStack} /DecodeParms {$decodeParms} /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$dctDecodeParms = $entry['filter_details'][0]['decode_parms'] ?? null;
$payloadExcluded = !str_contains($plainText, 'WordPress trailing null DCT payload leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$dctDecodeParmsPreserved = is_array($dctDecodeParms)
    && ($dctDecodeParms['type'] ?? null) === 'DCTDecode'
    && ($dctDecodeParms['color_transform'] ?? null) === 2
    && ($dctDecodeParms['valid_color_transform'] ?? false) === true;

if (
    $lines !== ['Before trailing null DCT filter', 'After trailing null DCT filter']
    || !$payloadExcluded
    || !$dctDecodeParmsPreserved
    || ($entry['native_raster_decode'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode trailing null filter boundary leaked payload bytes or lost DecodeParms review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-trailing-null-filter-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-trailing-null-filter-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'trailing_null_filter_slot_ignored' => true,
    'dct_decodeparms_color_transform' => $dctDecodeParms['color_transform'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'excluded_dctdecode_payload_noise' => $payloadExcluded,
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
