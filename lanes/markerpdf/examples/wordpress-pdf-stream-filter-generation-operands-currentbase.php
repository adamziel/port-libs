<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused stream filter generation fixture must fit one deflate block.');
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

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Focused stream filter generation rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Shared Generation Filter Params) Tj T* ';
$rowTwo = str_pad('(Exact DecodeParms Same Object) Tj ET', strlen($rowOne));
$encodedRows = $pngSubPredictorEncode($rowOne . $rowTwo, strlen($rowOne));
$compressed = $zlibStored($encodedRows);
$visibleAfter = 'BT /F1 12 Tf 72 684 Td (Visible After Shared Operand Generations) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter 20 0 R /DecodeParms 20 1 R /Length 21 0 R >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "20 0 obj\n/FlateDecode\nendobj\n"
    . "20 1 obj\n<< /Predictor 12 /Columns 21 1 R >>\nendobj\n"
    . "21 0 obj\n" . strlen($compressed) . "\nendobj\n"
    . "21 1 obj\n" . strlen($rowOne) . "\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$joined = implode("\n", $lines);
$expected = [
    'Shared Generation Filter Params',
    'Exact DecodeParms Same Object',
    'Visible After Shared Operand Generations',
];

echo '<!-- markerpdf:stream-filter-generation-operands '
    . htmlspecialchars(json_encode([
        'filter_generation_zero_selected' => in_array('Shared Generation Filter Params', $lines, true),
        'decodeparms_generation_one_selected' => in_array('Exact DecodeParms Same Object', $lines, true),
        'length_generation_zero_selected' => $lines === $expected,
        'columns_generation_one_selected' => $lines === $expected,
        'same_object_number_generations_coexist' => $lines === $expected,
        'helper_payload_excluded' => !str_contains($joined, 'FlateDecode')
            && !str_contains($joined, 'Predictor')
            && !str_contains($joined, '20 0 obj')
            && !str_contains($joined, '20 1 obj')
            && !str_contains($joined, '21 0 obj')
            && !str_contains($joined, '21 1 obj'),
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'paragraphs' => $lines,
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
