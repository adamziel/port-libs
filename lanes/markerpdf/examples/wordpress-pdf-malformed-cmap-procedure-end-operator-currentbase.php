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

$safeText = 'WP Procedure End Safe Import';
$leakingText = 'WP Procedure End CMap Leak';
$cMapName = 'WPProcedureEndOperatorBoundary-H';

$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "{ endcmap /DecoyText (" . $leakingText . ") } bind pop\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex($safeText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress procedure end-operator CMap fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPProcedureEndOperatorBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-procedure-end-operator-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'native_boundary' => 'endcmap tokens inside filtered ToUnicode CMap procedure bodies are ignored until the real top-level endcmap',
    'safe_text_imported' => $lines === [$safeText],
    'procedure_end_operator_decoy_excluded' => !str_contains($plainText, $leakingText)
        && !str_contains($plainText, $cMapName)
        && !str_contains($plainText, 'beginbfchar')
        && !str_contains($plainText, 'endcmap'),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
    'post_endcmap_bytes_excluded' => $entry['post_endcmap_bytes_excluded'] ?? null,
    'parser_bounded_cmap_bytes_excluded' => $entry['parser_bounded_cmap_bytes_excluded'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'native_boundary' => true,
    'decoded_cmap_count' => true,
    'filter_operand_policy' => true,
    'filter_decode_policy' => true,
    'post_endcmap_bytes_excluded' => true,
    'parser_bounded_cmap_bytes_excluded' => true,
    'paragraphs' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (
    in_array(false, $behaviorFlags, true)
    || ($flags['decoded_cmap_count'] ?? null) !== 1
    || ($flags['filter_operand_policy'] ?? null) !== 'filters_resolved'
    || ($flags['filter_decode_policy'] ?? null) !== 'filter_decoders_resolved'
    || ($flags['post_endcmap_bytes_excluded'] ?? null) !== true
    || ($flags['parser_bounded_cmap_bytes_excluded'] ?? null) !== true
) {
    throw new RuntimeException('Expected malformed CMap procedure end-operator smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

if (in_array('--self-test', $argv, true)) {
    echo "OK markerpdf-malformed-cmap-procedure-end-operator-currentbase\n";
}

echo '<!-- markerpdf-malformed-cmap-procedure-end-operator-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
