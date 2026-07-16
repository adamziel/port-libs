<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$ascii85Encode = static function (string $bytes): string {
    $encoded = '<~';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $padding = 4 - strlen($chunk);
        $padded = $chunk . str_repeat("\0", $padding);
        $value = 0;
        for ($index = 0; $index < 4; $index++) {
            $value = ($value << 8) | ord($padded[$index]);
        }

        if ($value === 0 && $padding === 0) {
            $encoded .= 'z';
            continue;
        }

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($digits, 0, 5 - $padding);
    }

    return $encoded . '~>';
};

$extractor = new PdfTextExtractor();
$eofb = "\x00\x10\x01";

$before = 'BT /F1 12 Tf 72 720 Td (Before ASCII85 CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After ASCII85 CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (ASCII85 CCITT import leak) Tj ET';
$encodedPayload = $ascii85Encode("\x11\x22\x33")
    . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . $ascii85Encode("\x44\x55{$eofb}");
$staleTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('ASCII85 CCITT boundary smoke must expose a stale early endstream marker.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxA85 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/A85 /CCF] /DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length {$staleTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];

if (
    $plainText !== "Before ASCII85 CCITT import\nAfter ASCII85 CCITT import"
    || str_contains($plainText, 'ASCII85 CCITT import leak')
    || ($entry['raw_length'] ?? null) !== strlen($encodedPayload)
    || ($entry['filters'] ?? null) !== ['A85', 'CCF']
    || ($entry['preview_only_filters'] ?? null) !== ['CCF']
) {
    throw new RuntimeException('ASCII85 CCITT boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-ascii85-prefix-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_prefix_filters' => $entry['ccitt_fax_filter_boundary']['native_prefix_filters'] ?? [],
    'declared_filter' => $entry['ccitt_fax_filter_boundary']['declared_filter'] ?? null,
    'canonical_filter' => $entry['ccitt_fax_filter_boundary']['canonical_filter'] ?? null,
    'ascii85_prefix_owned_until_ccitt_eofb' => ($entry['raw_length'] ?? null) === strlen($encodedPayload),
    'stale_owner_payload_excluded_from_visible_text' => !str_contains($plainText, 'ASCII85 CCITT import leak'),
    'stale_owner_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'ASCII85 CCITT import leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-ascii85-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
