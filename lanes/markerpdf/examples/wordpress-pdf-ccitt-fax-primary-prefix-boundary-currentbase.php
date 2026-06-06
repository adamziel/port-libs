<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused CCITT primary-prefix fixture must fit one deflate stored block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before primary-prefix CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After primary-prefix CCITT import) Tj ET';
$faxBytes = "\x00\x10\x01";
$compressedFaxBytes = $zlibStored($faxBytes);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /PrefixFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/FlateDecode /CCITTFaxDecode] /DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length " . strlen($compressedFaxBytes) . " >>\nstream\n{$compressedFaxBytes}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];

$metadata = [
    'source' => 'native-pdf-ccitt-fax-primary-prefix-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_prefix_filters' => $entry['ccitt_fax_filter_boundary']['native_prefix_filters'] ?? [],
    'primary_prefix_native_decoded' => ($entry['native_prefix_decoded'] ?? false) === true,
    'primary_prefix_native_decoded_length' => $entry['native_prefix_decoded_length'] ?? null,
    'primary_prefix_stopped_before_filter' => $entry['stopped_before_filter'] ?? null,
    'ccitt_image_stream_review_only' => ($entry['native_raster_decode'] ?? true) === false,
    'ccitt_payload_excluded_from_visible_text' => !str_contains($plainText, 'CCITTFaxDecode'),
    'compressed_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $compressedFaxBytes),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach (['stream_filters', 'preview_only_filters', 'native_prefix_filters'] as $name) {
    if (($metadata[$name] ?? []) === []) {
        throw new RuntimeException("Failed primary-prefix CCITT smoke check: {$name}");
    }
}
foreach (
    [
        'primary_prefix_native_decoded',
        'ccitt_image_stream_review_only',
        'ccitt_payload_excluded_from_visible_text',
        'compressed_payload_excluded_from_review',
    ] as $name
) {
    if (($metadata[$name] ?? false) !== true) {
        throw new RuntimeException("Failed primary-prefix CCITT smoke check: {$name}");
    }
}
foreach (['executes_python_or_models', 'executes_external_pdf_tools'] as $name) {
    if (($metadata[$name] ?? true) !== false) {
        throw new RuntimeException("Failed primary-prefix CCITT smoke check: {$name}");
    }
}
if (($metadata['primary_prefix_native_decoded_length'] ?? null) !== strlen($faxBytes)) {
    throw new RuntimeException('Failed primary-prefix CCITT smoke check: primary_prefix_native_decoded_length');
}
if (($metadata['primary_prefix_stopped_before_filter'] ?? null) !== 'CCITTFaxDecode') {
    throw new RuntimeException('Failed primary-prefix CCITT smoke check: primary_prefix_stopped_before_filter');
}
if ($plainText !== "Before primary-prefix CCITT import\nAfter primary-prefix CCITT import") {
    throw new RuntimeException('Primary-prefix CCITT smoke emitted unexpected visible text.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-primary-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
