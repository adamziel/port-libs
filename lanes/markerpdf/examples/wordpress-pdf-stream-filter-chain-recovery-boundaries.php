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

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('PDF stream predictor rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Recovered Filter Chain) Tj T* ';
$rowTwo = str_pad('(DecodeParms Tail) Tj ET', strlen($rowOne));
$predicted = $pngSubPredictorEncode($rowOne . $rowTwo, strlen($rowOne));
$compressed = gzcompress($predicted);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress recovered filter-chain PDF fixture.');
}
$encoded = $ascii85Encode($compressed);

$missingDecodeParmsLeak = 'BT /F1 12 Tf 72 704 Td (Missing Chain DecodeParms Leak) Tj ET';
$missingCompressed = gzcompress("\0" . $missingDecodeParmsLeak);
if (!is_string($missingCompressed)) {
    throw new RuntimeException('Unable to compress missing DecodeParms PDF fixture.');
}
$missingEncoded = $ascii85Encode($missingCompressed);

$malformedDecodeParmsLeak = 'BT /F1 12 Tf 72 688 Td (Malformed Chain DecodeParms Leak) Tj ET';
$malformedCompressed = gzcompress("\0" . $malformedDecodeParmsLeak);
if (!is_string($malformedCompressed)) {
    throw new RuntimeException('Unable to compress malformed DecodeParms PDF fixture.');
}
$malformedEncoded = $ascii85Encode($malformedCompressed);

$visibleAfter = 'BT /F1 12 Tf 72 672 Td (Visible After Recovery) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null % parser comment\n20 0 R ] /Length " . (strlen($encoded) - 5) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null 99 0 R ] /Length " . strlen($missingEncoded) . " >>\nstream\n{$missingEncoded}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null 42 ] /Length " . strlen($malformedEncoded) . " >>\nstream\n{$malformedEncoded}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Predictor 12 /Columns " . strlen($rowOne) . " >>\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$joined = implode("\n", $lines);

echo '<!-- markerpdf:pdf-stream-filter-chain-recovery-boundaries ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-chain',
    'recovered_stale_length_chain' => str_contains($joined, 'Recovered Filter Chain')
        && str_contains($joined, 'DecodeParms Tail'),
    'missing_decodeparms_excluded' => !str_contains($joined, 'Missing Chain DecodeParms Leak'),
    'malformed_decodeparms_excluded' => !str_contains($joined, 'Malformed Chain DecodeParms Leak'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
