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

$safeText = 'WP Nested Indirect Dictionary Safe Import';
$leakingText = 'WP Nested Indirect Dictionary CMap Leak';
$cMapName = 'WPNestedIndirectDictionaryFilterBoundary-H';
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
    throw new RuntimeException('Unable to compress nested indirect dictionary CMap smoke fixture.');
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
$addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPNestedIndirectDictionaryFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter [ 8 0 R /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
$addObject(8, 0, '9 0 R');
$addObject(9, 0, '<< /Owner (nested indirect dictionary is not a decoder) /Fake [ /Nested ] >>');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 10\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 9; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber . ':0'] ?? null);
}
$pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-nested-indirect-filter-boundary-currentbase',
    'fallback_text_preserved' => $plainText === $safeText,
    'nested_indirect_dictionary_filter_rejected' => ($entry['filter_operand_policy'] ?? null) === 'reject_dictionary_filter_operands',
    'dictionary_operand_classified' => ($filterOperand['dictionary_filter_operand'] ?? false) === true,
    'generic_malformed_operand_not_used' => ($entry['malformed_filter_operand_count'] ?? null) === 0,
    'decoded_cmap_suppressed' => ($review['decoded_cmap_count'] ?? null) === 0,
    'cmap_payload_excluded' => !str_contains($plainText, $leakingText)
        && !str_contains($plainText, $cMapName)
        && !str_contains($plainText, 'nested indirect dictionary is not a decoder'),
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

foreach ($flags as $key => $value) {
    if ($key === 'source') {
        continue;
    }
    if ($value !== false && $value !== true) {
        throw new RuntimeException("Expected boolean flag for {$key}.");
    }
}

if (
    !$flags['fallback_text_preserved']
    || !$flags['nested_indirect_dictionary_filter_rejected']
    || !$flags['dictionary_operand_classified']
    || !$flags['generic_malformed_operand_not_used']
    || !$flags['decoded_cmap_suppressed']
    || !$flags['cmap_payload_excluded']
    || $flags['executes_python_or_models']
    || $flags['executes_external_pdf_tools']
) {
    throw new RuntimeException('Expected nested indirect dictionary CMap filter smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-malformed-cmap-nested-indirect-filter-boundary-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
