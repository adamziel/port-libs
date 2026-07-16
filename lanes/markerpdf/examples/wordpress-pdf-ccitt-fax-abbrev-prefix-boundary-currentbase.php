<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$before = 'BT /F1 12 Tf 72 720 Td (Before abbreviated CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After abbreviated CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (Abbreviated CCITT import leak) Tj ET';
$eofb = "\x00\x10\x01";
$nativeBytes = "\x11\x22\x33\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x44\x55{$eofb}";
$encodedPayload = $runLengthEncode($nativeBytes);
$staleTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('Abbreviated CCITT boundary smoke must expose a stale early endstream marker.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /AbbrevFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/RL /CCF] /DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length {$staleTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = $entry['ccitt_fax_filter_boundary'] ?? [];

$metadata = [
    'source' => 'native-pdf-ccitt-fax-abbrev-prefix-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_prefix_filters' => $boundary['native_prefix_filters'] ?? [],
    'canonical_native_prefix_filters' => $boundary['canonical_native_prefix_filters'] ?? [],
    'declared_filter' => $boundary['declared_filter'] ?? null,
    'canonical_filter' => $boundary['canonical_filter'] ?? null,
    'native_prefix_decoded' => ($entry['native_prefix_decoded'] ?? false) === true,
    'native_prefix_decoded_length' => $entry['native_prefix_decoded_length'] ?? null,
    'stopped_before_filter' => $entry['stopped_before_filter'] ?? null,
    'stale_owner_payload_excluded_from_visible_text' => !str_contains($plainText, 'Abbreviated CCITT import leak'),
    'stale_owner_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Abbreviated CCITT import leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($plainText !== "Before abbreviated CCITT import\nAfter abbreviated CCITT import") {
    throw new RuntimeException('Abbreviated CCITT smoke emitted unexpected visible text.');
}
if (($metadata['stream_filters'] ?? null) !== ['RL', 'CCF']) {
    throw new RuntimeException('Abbreviated CCITT smoke failed stream filter review.');
}
if (($metadata['preview_only_filters'] ?? null) !== ['CCF']) {
    throw new RuntimeException('Abbreviated CCITT smoke failed preview-only filter review.');
}
if (($metadata['native_prefix_filters'] ?? null) !== ['RL']) {
    throw new RuntimeException('Abbreviated CCITT smoke failed declared native-prefix review.');
}
if (($metadata['canonical_native_prefix_filters'] ?? null) !== ['RunLengthDecode']) {
    throw new RuntimeException('Abbreviated CCITT smoke failed canonical native-prefix review.');
}
foreach (
    [
        'native_prefix_decoded',
        'stale_owner_payload_excluded_from_visible_text',
        'stale_owner_payload_excluded_from_review',
    ] as $name
) {
    if (($metadata[$name] ?? false) !== true) {
        throw new RuntimeException("Abbreviated CCITT smoke failed {$name}.");
    }
}
foreach (['decoded_with_current_filters', 'native_raster_decode'] as $name) {
    if (($metadata[$name] ?? true) !== false) {
        throw new RuntimeException("Abbreviated CCITT smoke failed {$name}.");
    }
}
if (($metadata['native_prefix_decoded_length'] ?? null) !== strlen($nativeBytes)) {
    throw new RuntimeException('Abbreviated CCITT smoke failed native_prefix_decoded_length.');
}
if (($metadata['stopped_before_filter'] ?? null) !== 'CCF') {
    throw new RuntimeException('Abbreviated CCITT smoke failed stopped_before_filter.');
}
foreach (['executes_python_or_models', 'executes_external_pdf_tools'] as $name) {
    if (($metadata[$name] ?? true) !== false) {
        throw new RuntimeException("Abbreviated CCITT smoke failed {$name}.");
    }
}

echo '<!-- markerpdf:pdf-ccitt-fax-abbrev-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
