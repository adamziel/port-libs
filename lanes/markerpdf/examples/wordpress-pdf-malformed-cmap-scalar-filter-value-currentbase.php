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
    string $filterValue,
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
        throw new RuntimeException('Unable to compress malformed scalar CMap filter-value smoke fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter {$filterValue} /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$cases = [
    'boolean' => [
        'pdf' => $buildPdf(
            'true',
            'WP Boolean Filter Safe Import',
            'WP Boolean Filter CMap Leak',
            'WPBooleanScalarFilterValueBoundary-H',
            'WPBooleanScalarFilterValueBoundary'
        ),
        'safe_text' => 'WP Boolean Filter Safe Import',
        'leaking_text' => 'WP Boolean Filter CMap Leak',
        'cmap_name' => 'WPBooleanScalarFilterValueBoundary-H',
        'expected_token_type' => 'boolean',
        'expected_value' => true,
    ],
    'number' => [
        'pdf' => $buildPdf(
            '1.5',
            'WP Number Filter Safe Import',
            'WP Number Filter CMap Leak',
            'WPNumberScalarFilterValueBoundary-H',
            'WPNumberScalarFilterValueBoundary'
        ),
        'safe_text' => 'WP Number Filter Safe Import',
        'leaking_text' => 'WP Number Filter CMap Leak',
        'cmap_name' => 'WPNumberScalarFilterValueBoundary-H',
        'expected_token_type' => 'number',
        'expected_value' => 1.5,
    ],
];

$extractor = new PdfTextExtractor();
$summaries = [];
$paragraphs = [];
foreach ($cases as $name => $case) {
    $pdf = $case['pdf'];
    $lines = $extractor->extractTextLines($pdf);
    $plainText = implode("\n", $lines);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];
    $filterOperand = $entry['filter_operands'][0] ?? [];
    $summary = [
        'safe_text_imported' => $lines === [$case['safe_text']],
        'cmap_payload_excluded' => !str_contains($plainText, $case['leaking_text'])
            && !str_contains($plainText, $case['cmap_name'])
            && !str_contains($plainText, 'beginbfchar'),
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'filter_resolution_failed' => $entry['filter_resolution_failed'] ?? null,
        'malformed_filter_operand_count' => $entry['malformed_filter_operand_count'] ?? null,
        'token_type' => $filterOperand['token_type'] ?? null,
        'typed_value_matches' => ($filterOperand['value'] ?? null) === $case['expected_value'],
        'valid_filter_operand' => $filterOperand['valid_filter_operand'] ?? null,
    ];

    if (
        in_array(false, [
            $summary['safe_text_imported'],
            $summary['cmap_payload_excluded'],
            $summary['typed_value_matches'],
        ], true)
        || ($summary['decoded_cmap_count'] ?? null) !== 0
        || ($summary['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands'
        || ($summary['filter_resolution_failed'] ?? null) !== true
        || ($summary['malformed_filter_operand_count'] ?? null) !== 1
        || ($summary['token_type'] ?? null) !== $case['expected_token_type']
        || ($summary['valid_filter_operand'] ?? null) !== false
    ) {
        throw new RuntimeException('Expected malformed scalar CMap filter-value smoke flags to pass: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    $summaries[$name] = $summary;
    foreach ($lines as $line) {
        $paragraphs[] = $line;
    }
}

$flags = [
    'source' => 'native-pdf-cmap-scalar-filter-value-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'native_boundary' => 'direct boolean and real-number CMap Filter values fail closed before ToUnicode import',
    'cases' => $summaries,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    echo json_encode(['self_test_passed' => true] + $flags, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo '<!-- markerpdf-malformed-cmap-scalar-filter-value-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
