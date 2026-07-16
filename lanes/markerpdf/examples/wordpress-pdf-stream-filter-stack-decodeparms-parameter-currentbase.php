<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngSubPredictor = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Focused DecodeParms parameter smoke rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Duplicate Predictor Parameter Leak) Tj T* ';
$rowTwo = str_pad('(Duplicate Predictor Still Leaks) Tj ET', strlen($rowOne));
$predictorCompressed = gzcompress($pngSubPredictor($rowOne . $rowTwo, strlen($rowOne)));
if (!is_string($predictorCompressed)) {
    throw new RuntimeException('Unable to compress duplicate DecodeParms predictor smoke stream.');
}

$cryptContent = 'BT /F1 12 Tf 72 684 Td (Duplicate Crypt Name Leak) Tj ET';
$cryptCompressed = gzcompress($cryptContent);
if (!is_string($cryptCompressed)) {
    throw new RuntimeException('Unable to compress duplicate DecodeParms Crypt smoke stream.');
}

$visibleAfter = 'BT /F1 12 Tf 72 648 Td (Visible After Duplicate DecodeParms Parameters) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns " . strlen($rowOne) . " /Predictor 1 >> /Length " . strlen($predictorCompressed) . " >>\nstream\n{$predictorCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity /Name /PrivateCF >> null ] /Length " . strlen($cryptCompressed) . " >>\nstream\n{$cryptCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter DecodeParms duplicate-parameter fail-closed import',
    'line_count' => count($lines),
    'duplicate_predictor_parameter_rejected' => !str_contains($plainText, 'Duplicate Predictor Parameter Leak')
        && !str_contains($plainText, 'Duplicate Predictor Still Leaks'),
    'duplicate_crypt_name_rejected' => !str_contains($plainText, 'Duplicate Crypt Name Leak')
        && !str_contains($plainText, 'PrivateCF'),
    'visible_fallback_preserved' => in_array('Visible After Duplicate DecodeParms Parameters', $lines, true),
    'predictor_dictionary_excluded' => !str_contains($plainText, 'Predictor'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-decodeparms-parameter-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
