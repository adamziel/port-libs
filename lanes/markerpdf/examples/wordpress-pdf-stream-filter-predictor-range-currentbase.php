<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
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

    return $encoded;
};

$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
    . "/CMapName /UnsupportedPredictorRange def\n"
    . "/CMapType 2 def\n"
    . "1 begincodespacerange\n"
    . "<01> <01>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<01> <0058>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end end\n";
$cMapCompressed = gzcompress($cMap);
if (!is_string($cMapCompressed)) {
    throw new RuntimeException('Unable to compress focused CMap predictor range smoke stream.');
}

$leakingContent = 'BT /F1 12 Tf 72 720 Td (Unsupported Predictor Range Leak) Tj ET';
$leakingCompressed = gzcompress($leakingContent);
if (!is_string($leakingCompressed)) {
    throw new RuntimeException('Unable to compress focused page predictor range smoke stream.');
}
$leakingStack = $ascii85Encode($leakingCompressed) . '~>';

$visibleAfter = 'BT /F1 12 Tf 72 700 Td (Visible After Unsupported Predictor Range) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /A85 /Fl ] /DecodeParms [ null << /Predictor 16 /Columns 1 >> ] /Length " . strlen($leakingStack) . " >>\nstream\n{$leakingStack}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /CMap /CMapName /UnsupportedPredictorRange /Filter /FlateDecode /DecodeParms << /Predictor 16 /Columns 1 >> /Length " . strlen($cMapCompressed) . " >>\n"
    . "stream\n{$cMapCompressed}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter unsupported predictor range fail-closed import',
    'line_count' => count($lines),
    'unsupported_predictor_rejected' => !str_contains($plainText, 'Unsupported Predictor Range Leak'),
    'visible_fallback_preserved' => in_array('Visible After Unsupported Predictor Range', $lines, true),
    'invalid_decodeparms_parameter_count' => $review['invalid_decodeparms_parameter_count'] ?? null,
    'filter_decode_error_count' => $review['filter_decode_error_count'] ?? null,
    'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
    'decodeparms_operand_policy' => $entry['decodeparms_operand_policy'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-predictor-range-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
