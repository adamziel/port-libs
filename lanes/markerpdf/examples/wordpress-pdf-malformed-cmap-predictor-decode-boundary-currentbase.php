<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$safeText = 'Predictor Decode Safe Import';
$safeHex = $utf16beHex($safeText);
$leakingText = 'Predictor Decode CMap Leak';
$leakingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPPredictorDecodeBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex($leakingText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($leakingCMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress predictor DecodeParms CMap smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPPredictorDecodeBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPPredictorDecodeBoundary-H /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 127 /Colors 1 /BitsPerComponent 8 >> /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$joined = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

echo '<!-- markerpdf:malformed-cmap-predictor-decode-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cmap-filter-review',
    'safe_text_imported' => $lines === [$safeText],
    'cmap_leak_excluded' => !str_contains($joined, $leakingText),
    'filter_end_marker_policy' => $entry['filter_end_marker_policy'] ?? null,
    'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
    'filter_decode_error_count' => $entry['filter_decode_error_count'] ?? null,
    'review_filter_decode_error_count' => $review['filter_decode_error_count'] ?? null,
    'invalid_decodeparms_parameter_count' => $entry['invalid_decodeparms_parameter_count'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
