<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused zlib stored-block fixture must fit one deflate block.');
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

$pngSubPredictor = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Focused DecodeParms rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$scalarLeak = 'BT /F1 12 Tf 72 720 Td (Indirect Scalar DecodeParms Leak) Tj ET';
$arrayLeak = 'BT /F1 12 Tf 72 704 Td (Indirect Array DecodeParms Leak) Tj ET';
$rowOne = 'BT /F1 12 Tf 72 688 Td (Valid Indirect DecodeParms Predictor) Tj T* ';
$rowTwo = str_pad('(Valid Helper Still Decodes) Tj ET', strlen($rowOne));
$encodedRows = $pngSubPredictor($rowOne . $rowTwo, strlen($rowOne));
$visibleAfter = 'BT /F1 12 Tf 72 652 Td (Visible After Indirect DecodeParms Boundary) Tj ET';

$scalarCompressed = $zlibStored($scalarLeak);
$arrayCompressed = $zlibStored($arrayLeak);
$validCompressed = $zlibStored($encodedRows);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms 10 0 R /Length " . strlen($scalarCompressed) . " >>\nstream\n{$scalarCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms [ 11 0 R ] /Length " . strlen($arrayCompressed) . " >>\nstream\n{$arrayCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter /FlateDecode /DecodeParms 14 0 R /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Predictor 1 >> << /Predictor 12 /Columns 64 >>\nendobj\n"
    . "11 0 obj\n<< /Predictor 1 >> null\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Predictor 12 /Columns " . strlen($rowOne) . " >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (str_contains($plainText, 'Indirect Scalar DecodeParms Leak') || str_contains($plainText, 'Indirect Array DecodeParms Leak')) {
    throw new RuntimeException('Malformed indirect DecodeParms helper payload leaked into WordPress import.');
}
if (!in_array('Valid Indirect DecodeParms Predictor', $lines, true) || !in_array('Visible After Indirect DecodeParms Boundary', $lines, true)) {
    throw new RuntimeException('Expected valid indirect DecodeParms helper and fallback text to remain visible.');
}

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter stack indirect DecodeParms single-value boundary',
    'line_count' => count($lines),
    'indirect_scalar_decodeparms_rejected' => !str_contains($plainText, 'Indirect Scalar DecodeParms Leak'),
    'indirect_array_decodeparms_rejected' => !str_contains($plainText, 'Indirect Array DecodeParms Leak'),
    'valid_indirect_decodeparms_preserved' => in_array('Valid Helper Still Decodes', $lines, true),
    'visible_fallback_preserved' => in_array('Visible After Indirect DecodeParms Boundary', $lines, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-indirect-decodeparms-value-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
