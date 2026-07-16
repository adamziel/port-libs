<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Predictor rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Predictor PDF Import) Tj T* ';
$rowTwo = '(Block Ready Content) Tj ET                         ';
$encoded = $pngPredictorEncode($rowOne . $rowTwo, strlen($rowOne));
$compressed = gzcompress($encoded);
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns " . strlen($rowOne) . " >> /Length " . strlen($compressed) . " >>\n"
    . "stream\n{$compressed}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-flate-predictor ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'stream_filters' => ['FlateDecode'],
    'decode_parms' => ['Predictor' => 12, 'Columns' => strlen($rowOne)],
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
