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

$safeText = 'Indirect Helper Keyed Safe Import';
$leakingText = 'Indirect Helper Keyed CMap Leak';
$cMapName = 'WPIndirectHelperDecodeParmsFilterBoundary-H';
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
    throw new RuntimeException('Unable to compress indirect helper DecodeParms CMap smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset ?? 0,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPIndirectHelperDecodeParmsFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
$addObject(7, 0, '/FlateDecode /DecodeParms << /Predictor 1 >> /ASCIIHexDecode');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber . ':0'] ?? null);
}
$pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

if ($lines !== [$safeText]) {
    throw new RuntimeException('Expected indirect helper DecodeParms CMap filter boundary to preserve safe fallback text.');
}
if (str_contains($plainText, $leakingText) || str_contains($plainText, $cMapName)) {
    throw new RuntimeException('Expected indirect helper DecodeParms CMap payload to stay excluded.');
}
if (($review['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected indirect helper DecodeParms CMap filter not to decode.');
}
if (($entry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands') {
    throw new RuntimeException('Expected malformed indirect helper CMap filter operand policy.');
}
if (($filterOperand['extra_filter_name'] ?? null) !== 'ASCIIHexDecode') {
    throw new RuntimeException('Expected indirect helper extra decoder metadata to report the trailing decoder name.');
}

echo '<!-- markerpdf-malformed-cmap-indirect-helper-decodeparms-filter-boundary-currentbase-smoke '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'selected indirect ToUnicode CMap Filter helper objects skip keyed DecodeParms pairs before reporting trailing unkeyed decoder names',
        'safe_text_preserved' => true,
        'payload_excluded' => true,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'invalid_filter_operand_count' => $review['invalid_filter_operand_count'] ?? null,
        'malformed_filter_operand_count' => $review['malformed_filter_operand_count'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'helper_token_type' => $filterOperand['token_type'] ?? null,
        'extra_filter_name' => $filterOperand['extra_filter_name'] ?? null,
        'extra_filter_operand_preview' => $filterOperand['extra_filter_operand_preview'] ?? null,
        'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
    ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
