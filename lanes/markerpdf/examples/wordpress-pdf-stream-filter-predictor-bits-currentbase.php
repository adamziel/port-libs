<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngSubEncode = static function (string $bytes, int $bytesPerPixel): string {
    $encoded = "\x01";
    for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
        $left = $index >= $bytesPerPixel ? ord($bytes[$index - $bytesPerPixel]) : 0;
        $encoded .= chr((ord($bytes[$index]) - $left) & 0xff);
    }

    return $encoded;
};

$unsupported = 'BT /F1 12 Tf 72 720 Td (Unsupported Predictor Bits Leak) Tj ET';
while ((strlen($unsupported) % 4) !== 0) {
    $unsupported .= ' ';
}
$unsupportedEncoded = $pngSubEncode($unsupported, 4);
$unsupportedCompressed = gzcompress($unsupportedEncoded);

$valid = 'BT /F1 12 Tf 72 700 Td (Sixteen Bit Predictor Imports) Tj ET';
while ((strlen($valid) % 2) !== 0) {
    $valid .= ' ';
}
$validEncoded = $pngSubEncode($valid, 2);
$validCompressed = gzcompress($validEncoded);

if (!is_string($unsupportedCompressed) || !is_string($validCompressed)) {
    throw new RuntimeException('Unable to compress focused predictor BitsPerComponent smoke stream.');
}

$visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Predictor Bits Boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Colors 1 /BitsPerComponent 32 /Columns " . intdiv(strlen($unsupported), 4) . " >> /Length " . strlen($unsupportedCompressed) . " >>\nstream\n{$unsupportedCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Colors 1 /BitsPerComponent 16 /Columns " . intdiv(strlen($valid), 2) . " >> /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter unsupported predictor BitsPerComponent fail-closed import',
    'line_count' => count($lines),
    'unsupported_bits_rejected' => !str_contains($plainText, 'Unsupported Predictor Bits Leak'),
    'valid_16_bit_predictor_preserved' => in_array('Sixteen Bit Predictor Imports', $lines, true),
    'visible_fallback_preserved' => in_array('Visible After Predictor Bits Boundary', $lines, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-predictor-bits-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
