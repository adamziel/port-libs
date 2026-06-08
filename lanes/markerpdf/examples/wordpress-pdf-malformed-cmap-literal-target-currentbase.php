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

$buildPdf = static function (string $targetShape) use ($utf16beHex): array {
    $safeText = 'Literal Surrogate Safe Import';
    $safeHex = $utf16beHex($safeText);
    $sourceStart = substr($safeHex, 0, 4);
    $sourceNext = substr($safeHex, 4, 4);
    $literalSurrogateTarget = '(\\330\\000)';
    $cMapName = match ($targetShape) {
        'bfchar' => 'WPLiteralTargetBfcharSurrogateBoundary-H',
        'bfrange-scalar' => 'WPLiteralTargetBfrangeScalarSurrogateBoundary-H',
        'bfrange-array' => 'WPLiteralTargetBfrangeArraySurrogateBoundary-H',
    };

    $mappingBlock = match ($targetShape) {
        'bfchar' => "1 beginbfchar\n"
            . "<{$sourceStart}> {$literalSurrogateTarget}\n"
            . "endbfchar\n",
        'bfrange-scalar' => "1 beginbfrange\n"
            . "<{$sourceStart}> <{$sourceStart}> {$literalSurrogateTarget}\n"
            . "endbfrange\n",
        'bfrange-array' => "1 beginbfrange\n"
            . "<{$sourceStart}> <{$sourceNext}> [ {$literalSurrogateTarget} <{$sourceNext}> ]\n"
            . "endbfrange\n",
    };

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($toUnicode, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress malformed literal-target CMap smoke fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLiteralTargetSurrogateBoundary /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPLiteralTargetSurrogateBoundary /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $cMapName];
};

$extractor = new PdfTextExtractor();
$cases = [];
$paragraphs = [];

foreach (['bfchar', 'bfrange-scalar', 'bfrange-array'] as $targetShape) {
    [$pdf, $safeText, $cMapName] = $buildPdf($targetShape);
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $summary = [
        'safe_text_preserved' => $lines === [$safeText],
        'plain_text' => $plainText,
        'malformed_literal_target_rejected' => $plainText === $safeText
            && !str_contains($plainText, "\0")
            && !str_contains($plainText, "\u{FFFD}")
            && !str_contains($plainText, "\xD8"),
        'cmap_payload_excluded' => !str_contains($plainText, $cMapName)
            && !str_contains($plainText, 'beginbfchar')
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
        $summary['safe_text_preserved'],
        $summary['malformed_literal_target_rejected'],
        $summary['cmap_payload_excluded'],
        ($summary['decoded_cmap_count'] ?? null) === 1,
        ($summary['filters'] ?? null) === ['FlateDecode'],
        ($summary['filter_operand_policy'] ?? null) === 'filters_resolved',
        ($summary['filter_decode_policy'] ?? null) === 'filter_decoders_resolved',
        ($summary['decodeparms_operand_policy'] ?? null) === 'decodeparms_resolved',
        ($summary['decoded_with_current_operands'] ?? null) === true,
        ($summary['executes_python_or_models'] ?? null) === false,
        ($summary['executes_external_pdf_tools'] ?? null) === false,
    ];
    if (in_array(false, $required, true)) {
        throw new RuntimeException('Expected malformed literal-target CMap smoke flags to pass: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    $cases[$targetShape] = $summary;
    foreach ($lines as $line) {
        $paragraphs[] = $line;
    }
}

$evidence = [
    'scenario' => 'wordpress_pdf_malformed_cmap_literal_target_currentbase',
    'source' => 'native-pdf-malformed-cmap-literal-target-filter-boundary-currentbase',
    'support_component' => 'pdf-cmap-core',
    'cases' => $cases,
    'malformed_literal_targets_rejected' => array_reduce(
        $cases,
        static fn (bool $carry, array $case): bool => $carry && ($case['malformed_literal_target_rejected'] ?? false) === true,
        true
    ),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_malformed_cmap_literal_target\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($paragraphs as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
