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

$safeText = 'Nested Bfrange Row Safe Import';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPNestedBfrangeRowBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "[ <{$sourceCode}> <{$sourceCode}> <" . $utf16beHex('Nested Bfrange Row CMap Leak') . "> ]\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cmap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress nested bfrange-row CMap smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPNestedBfrangeRowBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPNestedBfrangeRowBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-nested-bfrange-row-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'safe_text_imported' => $lines === [$safeText] && $runs === [$safeText] && $plainText === $safeText,
    'nested_bfrange_row_decoy_excluded' => !str_contains($plainText, 'Nested Bfrange Row CMap Leak'),
    'cmap_name_not_imported_as_text' => !str_contains($plainText, 'WPNestedBfrangeRowBoundary-H'),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'cmap_stream_count' => $review['cmap_stream_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_end_marker_policy' => $entry['filter_end_marker_policy'] ?? null,
    'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
    'unsupported_filter_count' => $review['unsupported_filter_count'] ?? null,
];

$behaviorFlags = [
    $flags['safe_text_imported'],
    $flags['nested_bfrange_row_decoy_excluded'],
    $flags['cmap_name_not_imported_as_text'],
    $flags['decoded_cmap_count'] === 1,
    $flags['cmap_stream_count'] === 1,
    $flags['filter_operand_policy'] === 'filters_resolved',
    $flags['filter_end_marker_policy'] === 'filter_end_markers_resolved',
    $flags['decoded_with_current_operands'] === true,
    $flags['unsupported_filter_count'] === 0,
];

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected nested bfrange-row CMap smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-malformed-cmap-nested-bfrange-row-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
