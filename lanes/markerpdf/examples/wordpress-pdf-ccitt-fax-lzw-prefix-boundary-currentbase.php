<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$lzwPackCodes = static function (array $codes, int $earlyChange = 1): string {
    $dictSize = 258;
    $codeSize = 9;
    $bits = '';
    foreach ($codes as $code) {
        for ($bit = $codeSize - 1; $bit >= 0; $bit--) {
            $bits .= (($code >> $bit) & 1) === 1 ? '1' : '0';
        }
        if ($code === 256) {
            $dictSize = 258;
            $codeSize = 9;
            continue;
        }
        if ($code !== 257) {
            $dictSize++;
            if ($codeSize < 12 && $dictSize + $earlyChange >= (1 << $codeSize)) {
                $codeSize++;
            }
        }
    }

    $out = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $out .= chr(bindec(str_pad(substr($bits, $offset, 8), 8, '0')));
    }

    return $out;
};

$lzwLiteralEncode = static function (string $bytes) use ($lzwPackCodes): string {
    return $lzwPackCodes([
        256,
        ...array_map('ord', str_split($bytes)),
        257,
    ]);
};

$before = 'BT /F1 12 Tf 72 720 Td (Before LZW CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After LZW CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress LZW CCITT prefix leak) Tj ET';
$ccittEofb = "\x00\x10\x01";
$encodedPayload = $lzwLiteralEncode("\x11\x22\x33")
    . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . $lzwLiteralEncode("\x44\x55{$ccittEofb}");
$staleTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('LZW CCITT smoke must expose a stale endstream marker.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxLzw 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/LZWDecode /CCITTFaxDecode] /DecodeParms [<< /EarlyChange 1 >> << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length {$staleTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry) ? ($entry['ccitt_fax_filter_boundary'] ?? []) : [];

if (
    $plainText !== "Before LZW CCITT import\nAfter LZW CCITT import"
    || str_contains($plainText, 'WordPress LZW CCITT prefix leak')
    || ($entry['raw_length'] ?? null) !== strlen($encodedPayload)
    || ($entry['decoded_with_current_filters'] ?? true) !== false
    || ($entry['native_raster_decode'] ?? true) !== false
    || ($boundary['native_prefix_filters'] ?? []) !== ['LZWDecode']
) {
    throw new RuntimeException('LZW-prefixed CCITT boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-lzw-prefix-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_prefix_filters' => $boundary['native_prefix_filters'] ?? [],
    'lzw_member_eod_ignored_until_ccitt_boundary' => ($entry['raw_length'] ?? null) === strlen($encodedPayload),
    'stale_owner_payload_excluded_from_visible_text' => !str_contains($plainText, 'WordPress LZW CCITT prefix leak'),
    'stale_owner_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'WordPress LZW CCITT prefix leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-lzw-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
