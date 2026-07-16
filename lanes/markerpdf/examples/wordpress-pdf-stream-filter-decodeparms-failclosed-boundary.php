<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lzwPackCodes = static function (array $codes): string {
    $bitString = '';
    foreach ($codes as $code) {
        $bitString .= str_pad(decbin($code), 9, '0', STR_PAD_LEFT);
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bitString); $offset < $length; $offset += 8) {
        $byteBits = substr($bitString, $offset, 8);
        $encoded .= chr(bindec(str_pad($byteBits, 8, '0')));
    }

    return $encoded;
};

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('DecodeParms smoke rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Valid DecodeParms Boundary) Tj T* ';
$rowTwo = str_pad('(Recovered Predictor Rows) Tj ET', strlen($rowOne));
$predicted = $pngSubPredictorEncode($rowOne . $rowTwo, strlen($rowOne));
$validCompressed = gzcompress($predicted);
if (!is_string($validCompressed)) {
    throw new RuntimeException('Unable to compress valid DecodeParms fixture.');
}

$malformedPredictorLeak = 'BT /F1 12 Tf 72 704 Td (Malformed Predictor Leak) Tj ET';
$malformedPredictorCompressed = gzcompress($malformedPredictorLeak);
if (!is_string($malformedPredictorCompressed)) {
    throw new RuntimeException('Unable to compress malformed Predictor fixture.');
}

$unresolvedColumnsLeak = 'BT /F1 12 Tf 72 688 Td (Unresolved Columns Leak) Tj ET';
$unresolvedColumnsCompressed = gzcompress($unresolvedColumnsLeak);
if (!is_string($unresolvedColumnsCompressed)) {
    throw new RuntimeException('Unable to compress unresolved Columns fixture.');
}

$unresolvedEarlyChangeLeak = 'BT /F1 12 Tf 72 672 Td (Unresolved EarlyChange Leak) Tj ET';
$unresolvedEarlyChangeEncoded = $lzwPackCodes([
    256,
    ...array_map('ord', str_split($unresolvedEarlyChangeLeak)),
    257,
]);

$visibleAfter = 'BT /F1 12 Tf 72 656 Td (Visible After DecodeParms Boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns " . strlen($rowOne) . " >> /Length " . strlen($validCompressed) . " >>\nstream\n{$validCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor /Twelve >> /Length " . strlen($malformedPredictorCompressed) . " >>\nstream\n{$malformedPredictorCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Columns 99 0 R >> /Length " . strlen($unresolvedColumnsCompressed) . " >>\nstream\n{$unresolvedColumnsCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter /LZWDecode /DecodeParms << /EarlyChange 99 0 R >> /Length " . strlen($unresolvedEarlyChangeEncoded) . " >>\nstream\n{$unresolvedEarlyChangeEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$joined = implode("\n", $lines);

echo '<!-- markerpdf:pdf-stream-filter-decodeparms-failclosed-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-decodeparms',
    'valid_predictor_recovered' => str_contains($joined, 'Valid DecodeParms Boundary')
        && str_contains($joined, 'Recovered Predictor Rows'),
    'malformed_predictor_excluded' => !str_contains($joined, 'Malformed Predictor Leak'),
    'unresolved_columns_excluded' => !str_contains($joined, 'Unresolved Columns Leak'),
    'unresolved_earlychange_excluded' => !str_contains($joined, 'Unresolved EarlyChange Leak'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
