<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode native-prefix alias smoke payload is too large.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before native-prefix alias DCT image) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After native-prefix alias DCT image) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0WordPress native-prefix alias review bytes "
    . 'BT /F1 12 Tf 72 700 Td (WordPress native-prefix alias DCT payload leak) Tj ET'
    . "\xff\xd9";
$encodedPayload = $zlibStored($jpegPayload);
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB '
    . '/BitsPerComponent 8 /Filter [/Fl /DCT] /DecodeParms [null << /ColorTransform 1 >>] '
    . '/Length ' . strlen($encodedPayload) . ' >>';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$boundary = is_array($entry) ? ($entry['dctdecode_filter_boundary'] ?? []) : [];
$streamBoundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? []) : [];

$expectedLines = [
    'Before native-prefix alias DCT image',
    'After native-prefix alias DCT image',
];
$payloadExcluded = !str_contains($plainText, 'WordPress native-prefix alias DCT payload leak')
    && !str_contains($plainText, 'WordPress native-prefix alias review bytes')
    && !str_contains($plainText, 'JFIF');

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || (($boundary['native_prefix_filters'] ?? []) !== ['Fl'])
    || (($boundary['canonical_native_prefix_filters'] ?? []) !== ['FlateDecode'])
    || (($boundary['declared_filter'] ?? null) !== 'DCT')
    || (($boundary['canonical_filter'] ?? null) !== 'DCTDecode')
    || (($entry['filters'] ?? []) !== ['Fl', 'DCTDecode'])
    || (($entry['preview_only_filters'] ?? []) !== ['DCTDecode'])
    || (($entry['native_raster_decode'] ?? null) !== false)
    || (($entry['decoded_with_current_filters'] ?? null) !== false)
    || (($streamBoundary['review_stream_decoded_from_native_prefix'] ?? null) !== true)
    || (($streamBoundary['stopped_before_filter'] ?? null) !== 'DCT')
    || (($plan['dctdecode_filter_boundary']['canonical_native_prefix_filters'] ?? []) !== ['FlateDecode'])
) {
    throw new RuntimeException('DCTDecode native-prefix alias boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-dctdecode-native-prefix-alias-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-native-prefix-alias-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only DCT image handoff',
    'paragraphs' => $lines,
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_prefix_filters' => $boundary['native_prefix_filters'] ?? [],
    'canonical_native_prefix_filters' => $boundary['canonical_native_prefix_filters'] ?? [],
    'dct_alias_preserved' => ($boundary['declared_filter'] ?? null) === 'DCT',
    'dct_canonical_filter' => $boundary['canonical_filter'] ?? null,
    'review_stream_decoded_from_native_prefix' => $streamBoundary['review_stream_decoded_from_native_prefix'] ?? null,
    'stopped_before_filter' => $streamBoundary['stopped_before_filter'] ?? null,
    'dctdecode_payload_excluded_from_text' => $payloadExcluded,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
