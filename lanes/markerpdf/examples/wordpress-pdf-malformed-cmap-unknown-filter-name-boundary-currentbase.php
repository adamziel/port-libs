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

$safeText = 'WP Unknown Filter Name Safe Import';
$leakingText = 'WP Unknown Filter Name CMap Leak';
$cMapName = 'WPUnknownNameFilterBoundary-H';
$extraName = 'WPUnknownFilterName';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<{$sourceCode}> <" . $utf16beHex($leakingText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress malformed unknown CMap filter-name smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPUnknownNameFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /{$extraName} /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

$flags = [
    'source' => 'native-pdf-cmap-unknown-filter-name-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'native_boundary' => 'scalar CMap Filter values followed by an unknown unkeyed name before Length fail closed before ToUnicode import',
    'safe_text_imported' => $lines === [$safeText],
    'cmap_payload_excluded' => !str_contains($plainText, $leakingText)
        && !str_contains($plainText, $cMapName)
        && !str_contains($plainText, $extraName)
        && !str_contains($plainText, 'beginbfchar'),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_resolution_failed' => $entry['filter_resolution_failed'] ?? null,
    'malformed_filter_operand_count' => $entry['malformed_filter_operand_count'] ?? null,
    'extra_filter_operand_type' => $filterOperand['extra_filter_operand_type'] ?? null,
    'extra_filter_name' => $filterOperand['extra_filter_name'] ?? null,
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
    'filter_resolution_failed' => true,
    'malformed_filter_operand_count' => true,
    'extra_filter_operand_type' => true,
    'extra_filter_name' => true,
    'paragraphs' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);
if (
    in_array(false, $behaviorFlags, true)
    || ($flags['decoded_cmap_count'] ?? null) !== 0
    || ($flags['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands'
    || ($flags['filter_resolution_failed'] ?? null) !== true
    || ($flags['malformed_filter_operand_count'] ?? null) !== 1
    || ($flags['extra_filter_operand_type'] ?? null) !== 'name'
    || ($flags['extra_filter_name'] ?? null) !== $extraName
) {
    throw new RuntimeException('Expected malformed unknown CMap filter-name smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-malformed-cmap-unknown-filter-name-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
