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

$buildIndirectScalarCMapFilterPdf = static function () use ($utf16beHex): string {
    $safeText = 'Indirect Scalar Safe Import';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $leakingText = 'Indirect Scalar CMap Leak';
    $cMapName = 'WPIndirectScalarFilterBoundary-H';
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
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
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress indirect scalar CMap filter-boundary fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPIndirectScalarFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '/FlateDecode /ASCIIHexDecode');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$extractor = new PdfTextExtractor();
$pdf = $buildIndirectScalarCMapFilterPdf();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

$accepted = $lines === ['Indirect Scalar Safe Import']
    && !str_contains($plainText, 'Indirect Scalar CMap Leak')
    && !str_contains($plainText, 'WPIndirectScalarFilterBoundary-H')
    && (($review['decoded_cmap_count'] ?? null) === 0)
    && (($review['invalid_filter_operand_count'] ?? null) === 1)
    && (($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands')
    && (($entry['owner_policy'] ?? null) === 'xref_selected_indirect_operands')
    && (($filterOperand['kind'] ?? null) === 'indirect')
    && (($filterOperand['xref_selected'] ?? null) === true)
    && (($filterOperand['extra_filter_operand'] ?? null) === true)
    && (($filterOperand['extra_filter_name'] ?? null) === 'ASCIIHexDecode')
    && (($review['executes_python_or_models'] ?? null) === false)
    && (($review['executes_external_pdf_tools'] ?? null) === false);

if (!$accepted) {
    throw new RuntimeException('Malformed indirect scalar CMap filter boundary was not rejected before WordPress import.');
}

echo '<!-- markerpdf-malformed-cmap-indirect-scalar-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-selected indirect scalar ToUnicode CMap Filter helpers with a trailing decoder-name token are rejected before WordPress text import',
    'fallback_text' => implode(' | ', $lines),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'invalid_filter_operand_count' => $review['invalid_filter_operand_count'] ?? null,
    'malformed_filter_operand_count' => $review['malformed_filter_operand_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'owner_policy' => $entry['owner_policy'] ?? null,
    'filter_operand_kind' => $filterOperand['kind'] ?? null,
    'filter_operand_xref_selected' => $filterOperand['xref_selected'] ?? null,
    'extra_filter_operand_type' => $filterOperand['extra_filter_operand_type'] ?? null,
    'extra_filter_name' => $filterOperand['extra_filter_name'] ?? null,
    'leaking_cmap_text_excluded' => !str_contains($plainText, 'Indirect Scalar CMap Leak')
        && !str_contains($plainText, 'WPIndirectScalarFilterBoundary-H'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
