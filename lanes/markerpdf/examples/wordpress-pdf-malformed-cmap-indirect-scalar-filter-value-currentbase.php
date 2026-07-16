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

$buildPdf = static function (
    string $helperBody,
    string $safeText,
    string $leakingText,
    string $cMapName,
    string $baseFont
) use ($utf16beHex): string {
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
        throw new RuntimeException('Unable to compress indirect scalar filter-value smoke fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /' . $baseFont . ' /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, $helperBody);

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber . ':0'] ?? null);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$extractor = new PdfTextExtractor();
$cases = [
    'boolean' => [
        'helper_body' => 'true',
        'safe_text' => 'Indirect Boolean Filter Safe Import',
        'leaking_text' => 'Indirect Boolean Filter CMap Leak',
        'cmap_name' => 'WPIndirectBooleanScalarFilterBoundary-H',
        'base_font' => 'WPIndirectBooleanScalarFilterBoundary',
        'expected_value' => true,
    ],
    'number' => [
        'helper_body' => '1.5',
        'safe_text' => 'Indirect Number Filter Safe Import',
        'leaking_text' => 'Indirect Number Filter CMap Leak',
        'cmap_name' => 'WPIndirectNumberScalarFilterBoundary-H',
        'base_font' => 'WPIndirectNumberScalarFilterBoundary',
        'expected_value' => 1.5,
    ],
];

$paragraphs = [];
$summaries = [];
foreach ($cases as $caseName => $case) {
    $pdf = $buildPdf(
        $case['helper_body'],
        $case['safe_text'],
        $case['leaking_text'],
        $case['cmap_name'],
        $case['base_font']
    );
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];
    $filterOperand = $entry['filter_operands'][0] ?? [];

    $accepted = $lines === [$case['safe_text']]
        && !str_contains($plainText, $case['leaking_text'])
        && !str_contains($plainText, $case['cmap_name'])
        && (($review['decoded_cmap_count'] ?? null) === 0)
        && (($review['invalid_filter_operand_count'] ?? null) === 1)
        && (($review['malformed_filter_operand_count'] ?? null) === 1)
        && (($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands')
        && (($filterOperand['kind'] ?? null) === 'indirect')
        && (($filterOperand['xref_selected'] ?? null) === true)
        && (($filterOperand['token_type'] ?? null) === $caseName)
        && (($filterOperand['value'] ?? null) === $case['expected_value'])
        && (($review['executes_python_or_models'] ?? null) === false)
        && (($review['executes_external_pdf_tools'] ?? null) === false);

    if (!$accepted) {
        throw new RuntimeException("Indirect {$caseName} scalar CMap filter helper was not rejected before WordPress import.");
    }

    $paragraphs[] = $case['safe_text'];
    $summaries[$caseName] = [
        'safe_text_preserved' => true,
        'leaking_cmap_text_excluded' => true,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'invalid_filter_operand_count' => $review['invalid_filter_operand_count'] ?? null,
        'malformed_filter_operand_count' => $review['malformed_filter_operand_count'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'token_type' => $filterOperand['token_type'] ?? null,
        'typed_value_matches' => true,
    ];
}

echo '<!-- markerpdf-malformed-cmap-indirect-scalar-filter-value-currentbase-smoke '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'xref-selected indirect scalar ToUnicode CMap Filter helper values are typed in review metadata and rejected before WordPress text import',
        'cases' => $summaries,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
