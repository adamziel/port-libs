<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85 = static function (string $bytes): string {
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

$pngSub = static function (string $bytes): string {
    $encoded = "\x01";
    for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
        $left = $index > 0 ? ord($bytes[$index - 1]) : 0;
        $encoded .= chr((ord($bytes[$index]) - $left) & 0xff);
    }

    return $encoded;
};

$badContent = 'BT /F1 12 Tf 72 720 Td (WordPress ASCII85 Predictor Params Leak) Tj ET';
$badCompressed = gzcompress($badContent, 0);

$goodContent = 'BT /F1 12 Tf 72 700 Td (WordPress Flate Predictor Params Import) Tj ET';
$goodPredicted = $pngSub($goodContent);
$goodCompressed = gzcompress($goodPredicted, 0);

if (!is_string($badCompressed) || !is_string($goodCompressed)) {
    throw new RuntimeException('Unable to compress DecodeParms applicability smoke streams.');
}

$badEncoded = $ascii85($badCompressed);
$goodEncoded = $ascii85($goodCompressed);
$visibleAfter = 'BT /F1 12 Tf 72 680 Td (WordPress Visible After Filter Applicability) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Columns " . strlen($badContent) . " /Colors 1 /BitsPerComponent 8 >> null ] /Length " . strlen($badEncoded) . " >>\nstream\n{$badEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 12 /Columns " . strlen($goodContent) . " /Colors 1 /BitsPerComponent 8 >> ] /Length " . strlen($goodEncoded) . " >>\nstream\n{$goodEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = [
    'WordPress Flate Predictor Params Import',
    'WordPress Visible After Filter Applicability',
];

if ($lines !== $expected || str_contains($plainText, 'WordPress ASCII85 Predictor Params Leak')) {
    throw new RuntimeException('Expected ASCII85-aligned predictor DecodeParms to fail closed before WordPress import.');
}

$metadata = [
    'scenario' => 'wordpress-pdf-stream-filter-decodeparms-applicability-currentbase',
    'support_component' => 'native-pdf-stream-filter-decodeparms-applicability',
    'native_boundary' => 'predictor DecodeParms keys are filter-local to Flate/LZW stages before WordPress paragraph extraction',
    'ascii85_predictor_params_rejected' => !str_contains($plainText, 'WordPress ASCII85 Predictor Params Leak'),
    'flate_predictor_params_preserved' => in_array('WordPress Flate Predictor Params Import', $lines, true),
    'visible_fallback_preserved' => in_array('WordPress Visible After Filter Applicability', $lines, true),
    'filter_tokens_excluded' => !str_contains($plainText, 'ASCII85Decode')
        && !str_contains($plainText, 'FlateDecode')
        && !str_contains($plainText, 'DecodeParms')
        && !str_contains($plainText, 'Columns'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'self_test_passed' => true,
];

echo '<!-- markerpdf:stream-filter-decodeparms-applicability-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
