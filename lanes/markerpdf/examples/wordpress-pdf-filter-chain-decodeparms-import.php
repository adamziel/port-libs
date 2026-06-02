<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
    $encoded = '<~';
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

$rowOne = 'BT /F1 12 Tf 72 720 Td (Filter Chain Import) Tj T* ';
$rowTwo = str_pad('(DecodeParms Array) Tj ET', strlen($rowOne));
$predictorEncoded = $pngPredictorEncode($rowOne . $rowTwo, strlen($rowOne));
$compressed = gzcompress($predictorEncoded);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to build compressed fixture.');
}

$encoded = $ascii85Encode($compressed);
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms 2 0 R /Length " . strlen($encoded) . " >>\n"
    . "stream\n{$encoded}\nendstream\nendobj\n"
    . "2 0 obj\n[ null << /Predictor 12 /Columns " . strlen($rowOne) . " >> ]\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-filter-chain-decodeparms ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-chain',
    'stream_filters' => ['ASCII85Decode', 'FlateDecode'],
    'decode_parms_array' => [null, ['Predictor' => 12, 'Columns' => strlen($rowOne)]],
    'decode_parms_is_indirect' => true,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
