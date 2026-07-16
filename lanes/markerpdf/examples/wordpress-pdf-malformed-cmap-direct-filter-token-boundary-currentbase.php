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

$leakingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPDirectExtraFilterNameBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<01> <" . $utf16beHex('WP Direct Extra Filter Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($leakingCMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress direct extra filter-name CMap smoke fixture.');
}

$safeText = 'WP Direct Extra Safe Import';
$safeHex = $utf16beHex($safeText);
$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPDirectExtraFilterNameBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPDirectExtraFilterNameBoundary-H /Filter /FlateDecode /ASCIIHexDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];
$allText = implode("\n", $lines);

$flags = [
    'source' => 'native-pdf-cmap-direct-extra-filter-name-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'direct_extra_filter_name_rejected' => $lines === [$safeText]
        && ($review['decoded_cmap_count'] ?? null) === 0
        && ($review['invalid_filter_operand_count'] ?? null) === 1
        && ($review['malformed_filter_operand_count'] ?? null) === 1
        && (($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands')
        && (($filterOperand['extra_filter_name_operand'] ?? null) === true)
        && (($filterOperand['extra_filter_name'] ?? null) === 'ASCIIHexDecode'),
    'filter_resolution_failed' => ($entry['filter_resolution_failed'] ?? null) === true
        && ($entry['filters'] ?? null) === [],
    'visible_text_excludes_cmap_program' => !str_contains($allText, 'WP Direct Extra Filter Leak')
        && !str_contains($allText, 'WPDirectExtraFilterNameBoundary-H')
        && !str_contains($allText, 'beginbfchar')
        && !str_contains($allText, "\0"),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'paragraphs' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected direct extra filter-name CMap smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-direct-extra-filter-name-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($flags['paragraphs'] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
