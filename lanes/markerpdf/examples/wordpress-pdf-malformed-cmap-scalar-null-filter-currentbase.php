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

$safeText = 'Null Decoder Safe Import';
$leakingText = 'Null Decoder CMap Leak';
$cMapName = 'WPNullDecoderFilterBoundary-H';
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
$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPNullDecoderFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter null /FlateDecode /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

if ($lines !== [$safeText]) {
    throw new RuntimeException('Expected malformed scalar-null CMap filter to preserve safe fallback text.');
}
if (str_contains($plainText, $leakingText) || str_contains($plainText, $cMapName)) {
    throw new RuntimeException('Expected malformed scalar-null CMap payload to stay excluded.');
}
if (($review['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected malformed scalar-null CMap filter not to decode.');
}
if (($entry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands') {
    throw new RuntimeException('Expected malformed scalar-null CMap filter operand policy.');
}
if (($filterOperand['extra_filter_name'] ?? null) !== 'FlateDecode') {
    throw new RuntimeException('Expected scalar-null CMap filter extra operand metadata.');
}

echo '<!-- markerpdf-malformed-cmap-scalar-null-filter-boundary-currentbase-smoke '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'scalar null ToUnicode CMap Filter operands with trailing unkeyed decoder names fail closed before WordPress text import',
        'safe_text_preserved' => true,
        'payload_excluded' => true,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'invalid_filter_operand_count' => $review['invalid_filter_operand_count'] ?? null,
        'malformed_filter_operand_count' => $review['malformed_filter_operand_count'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'extra_filter_name' => $filterOperand['extra_filter_name'] ?? null,
        'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
    ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
