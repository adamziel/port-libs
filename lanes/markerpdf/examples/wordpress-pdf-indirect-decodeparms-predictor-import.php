<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngUpPredictorEncode = static function (array $rows): string {
    $encoded = '';
    $rowLength = null;
    $previous = null;
    foreach ($rows as $row) {
        if (!is_string($row)) {
            throw new RuntimeException('Predictor rows must be strings.');
        }
        $rowLength ??= strlen($row);
        if (strlen($row) !== $rowLength) {
            throw new RuntimeException('Predictor rows must be fixed-width.');
        }

        $previous ??= str_repeat("\0", $rowLength);
        $encoded .= "\x02";
        for ($index = 0; $index < $rowLength; $index++) {
            $encoded .= chr((ord($row[$index]) - ord($previous[$index])) & 0xff);
        }
        $previous = $row;
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Indirect Predictor Import) Tj T* ';
if (strlen($rowOne) % 2 !== 0) {
    $rowOne .= ' ';
}
$rowTwo = str_pad('(Object DecodeParms) Tj ET', strlen($rowOne));
$columns = intdiv(strlen($rowOne), 2);
$encoded = $pngUpPredictorEncode([$rowOne, $rowTwo]);
$compressed = gzcompress($encoded);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to build compressed fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 2 0 R /Columns 3 0 R /Colors 4 0 R /BitsPerComponent 5 0 R >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "2 0 obj\n12\nendobj\n"
    . "3 0 obj\n{$columns}\nendobj\n"
    . "4 0 obj\n1\nendobj\n"
    . "5 0 obj\n16\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-indirect-decodeparms-predictor ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'stream_filters' => ['FlateDecode'],
    'decode_parms' => [
        'Predictor' => '2 0 R',
        'Columns' => '3 0 R',
        'Colors' => '4 0 R',
        'BitsPerComponent' => '5 0 R',
    ],
    'resolved_decode_parms' => [
        'Predictor' => 12,
        'Columns' => $columns,
        'Colors' => 1,
        'BitsPerComponent' => 16,
    ],
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
