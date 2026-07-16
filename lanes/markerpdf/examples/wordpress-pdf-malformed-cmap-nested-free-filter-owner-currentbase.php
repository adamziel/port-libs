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

$buildNestedFreeFilterOwnerPdf = static function () use ($utf16beHex): array {
    $safeText = 'Nested Free Safe Import';
    $leakingText = 'Nested Free CMap Leak';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'WPNestedFreeFilterHelperBoundary-H';
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
        throw new RuntimeException('Unable to compress WordPress nested free-helper CMap fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPNestedFreeFilterHelperBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 8 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, 'null');
    $addObject(8, 0, '[ 7 0 R /FlateDecode ]');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        $pdf .= $objectNumber === 7
            ? $xrefRow(0, 1, 'f')
            : $xrefRow($offsets[$objectNumber . ':0']);
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

[$pdf, $safeText, $leakingText, $cMapName] = $buildNestedFreeFilterOwnerPdf();

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

$flags = [
    'native_boundary' => 'selected CMap Filter helpers with nested free references are rejected before WordPress paragraph import',
    'safe_text_preserved' => $lines === [$safeText] && $plainText === $safeText,
    'payload_excluded' => !str_contains($plainText, $leakingText)
        && !str_contains($plainText, $cMapName)
        && !str_contains($plainText, 'FlateDecode')
        && !str_contains($plainText, 'beginbfchar'),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'invalid_filter_operand_count' => $review['invalid_filter_operand_count'] ?? null,
    'unresolved_operand_count' => $review['unresolved_operand_count'] ?? null,
    'nested_unresolved_filter_operand_count' => $review['nested_unresolved_filter_operand_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_owner_policy' => $entry['owner_policy'] ?? null,
    'helper_owner_policy' => $filterOperand['owner_policy'] ?? null,
    'helper_valid_filter_operand' => $filterOperand['valid_filter_operand'] ?? null,
    'helper_nested_unresolved_filter_operand_count' => $filterOperand['nested_unresolved_filter_operand_count'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$expected = [
    'safe_text_preserved' => true,
    'payload_excluded' => true,
    'decoded_cmap_count' => 0,
    'invalid_filter_operand_count' => 1,
    'unresolved_operand_count' => 1,
    'nested_unresolved_filter_operand_count' => 1,
    'filter_operand_policy' => 'reject_unresolved_filter_operands',
    'filter_owner_policy' => 'unresolved_or_unselected_indirect_operands',
    'helper_owner_policy' => 'xref_selected_direct_object',
    'helper_valid_filter_operand' => false,
    'helper_nested_unresolved_filter_operand_count' => 1,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($expected as $key => $value) {
    if (($flags[$key] ?? null) !== $value) {
        throw new RuntimeException('Expected nested free-helper CMap filter smoke flag ' . $key . ' to be ' . var_export($value, true));
    }
}

echo "<!-- markerpdf-malformed-cmap-nested-free-filter-owner-currentbase "
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
