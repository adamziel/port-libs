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

$safeText = 'AB';
$safeHex = $utf16beHex($safeText);
$sourceStart = substr($safeHex, 0, 4);
$sourceEnd = substr($safeHex, 4, 4);
$cMapName = 'WPBfrangeScalarTargetSequenceBoundary-H';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<{$sourceStart}> <{$sourceEnd}>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "<{$sourceStart}> <{$sourceEnd}> <D7FF>\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($toUnicode, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress malformed bfrange scalar target sequence CMap fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPBfrangeScalarTargetSequenceBoundary /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPBfrangeScalarTargetSequenceBoundary /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [65 66 1000] >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$evidence = [
    'scenario' => 'wordpress_pdf_malformed_cmap_bfrange_scalar_target_sequence_currentbase',
    'source' => 'native-pdf-malformed-cmap-bfrange-scalar-target-sequence-filter-boundary-currentbase',
    'safe_text_preserved' => $lines === [$safeText],
    'malformed_scalar_target_sequence_rejected' => $plainText === $safeText
        && !str_contains($plainText, "\0")
        && !str_contains($plainText, "\u{FFFD}")
        && !str_contains($plainText, $cMapName)
        && !str_contains($plainText, 'beginbfrange'),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'filters' => $entry['filters'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
    'decodeparms_operand_policy' => $entry['decodeparms_operand_policy'] ?? null,
    'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

$required = [
    $evidence['safe_text_preserved'],
    $evidence['malformed_scalar_target_sequence_rejected'],
    ($evidence['decoded_cmap_count'] ?? null) === 1,
    ($evidence['filters'] ?? null) === ['FlateDecode'],
    ($evidence['filter_operand_policy'] ?? null) === 'filters_resolved',
    ($evidence['filter_decode_policy'] ?? null) === 'filter_decoders_resolved',
    ($evidence['decodeparms_operand_policy'] ?? null) === 'decodeparms_resolved',
    ($evidence['decoded_with_current_operands'] ?? null) === true,
    ($evidence['executes_python_or_models'] ?? null) === false,
    ($evidence['executes_external_pdf_tools'] ?? null) === false,
];

if (in_array(false, $required, true)) {
    throw new RuntimeException('Expected malformed scalar bfrange target sequence CMap smoke flags to pass: ' . json_encode($evidence, JSON_UNESCAPED_SLASHES));
}

if (in_array('--self-test', $argv, true)) {
    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:port-libs/markerpdf-cmap-boundary "
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
