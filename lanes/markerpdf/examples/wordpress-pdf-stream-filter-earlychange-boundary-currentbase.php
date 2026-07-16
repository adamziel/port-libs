<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded . '~>';
};

$lzwLiteralEncode = static function (string $bytes): string {
    if (strlen($bytes) > 240) {
        throw new RuntimeException('Focused EarlyChange smoke fixture must keep 9-bit literal codes.');
    }

    $codes = array_merge([256], array_map('ord', str_split($bytes)), [257]);
    $bits = '';
    foreach ($codes as $code) {
        for ($shift = 8; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0');
        }

        $encoded .= chr(bindec($byte));
    }

    return $encoded;
};

$nonLzwLeak = 'BT /F1 12 Tf 72 720 Td (WordPress EarlyChange Non LZW Leak) Tj ET';
$nonLzwCompressed = gzcompress($nonLzwLeak);
$validLzwContent = 'BT /F1 12 Tf 72 700 Td (WordPress LZW EarlyChange Import) Tj ET';
$validLzwCompressed = gzcompress($validLzwContent);
if (!is_string($nonLzwCompressed) || !is_string($validLzwCompressed)) {
    throw new RuntimeException('Unable to compress focused EarlyChange smoke streams.');
}

$nonLzwEncoded = $ascii85Encode($nonLzwCompressed);
$validLzwEncoded = $lzwLiteralEncode($validLzwCompressed);
$visibleAfter = 'BT /F1 12 Tf 72 680 Td (WordPress Visible After EarlyChange Boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ << /EarlyChange 0 >> null ] /Length " . strlen($nonLzwEncoded) . " >>\nstream\n{$nonLzwEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /LZWDecode /FlateDecode ] /DecodeParms [ << /EarlyChange 0 >> null ] /Length " . strlen($validLzwEncoded) . " >>\nstream\n{$validLzwEncoded}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'WordPress LZW EarlyChange Import',
    'WordPress Visible After EarlyChange Boundary',
];
if ($lines !== $expected || str_contains($plainText, 'WordPress EarlyChange Non LZW Leak')) {
    throw new RuntimeException('Expected non-LZW EarlyChange stream to fail closed before WordPress import.');
}

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter EarlyChange DecodeParms fail-closed import',
    'line_count' => count($lines),
    'non_lzw_earlychange_rejected' => true,
    'lzw_earlychange_preserved' => in_array('WordPress LZW EarlyChange Import', $lines, true),
    'visible_fallback_preserved' => in_array('WordPress Visible After EarlyChange Boundary', $lines, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-earlychange-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
