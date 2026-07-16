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

$buildPdf = static function (bool $escapedDuplicateLengthKey) use ($utf16beHex): array {
    $safeText = $escapedDuplicateLengthKey
        ? 'Escaped Length Safe Import'
        : 'Duplicate Length Safe Import';
    $leakingText = $escapedDuplicateLengthKey
        ? 'Escaped Length CMap Leak'
        : 'Duplicate Length CMap Leak';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = $escapedDuplicateLengthKey
        ? 'WPEscapedDuplicateLengthBoundary-H'
        : 'WPDuplicateLengthBoundary-H';
    $duplicateLengthOperand = $escapedDuplicateLengthKey
        ? '/L#65ngth 1'
        : '/Length 1';

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
        throw new RuntimeException('Unable to compress duplicate-Length CMap smoke fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPDuplicateLengthBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " {$duplicateLengthOperand} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

$extractor = new PdfTextExtractor();
$cases = [];
$paragraphs = [];

foreach (['direct' => false, 'escaped' => true] as $caseName => $escapedDuplicateLengthKey) {
    [$pdf, $safeText, $leakingText, $cMapName] = $buildPdf($escapedDuplicateLengthKey);
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $summary = [
        'safe_text_preserved' => $lines === [$safeText],
        'plain_text' => $plainText,
        'duplicate_length_rejected' => ($review['decoded_cmap_count'] ?? null) === 0
            && ($review['duplicate_length_declaration_count'] ?? null) === 1
            && ($entry['length_operand_policy'] ?? null) === 'reject_duplicate_length_declarations',
        'cmap_payload_excluded' => !str_contains($plainText, $leakingText)
            && !str_contains($plainText, $cMapName)
            && !str_contains($plainText, 'beginbfchar')
            && !str_contains($plainText, 'FlateDecode')
            && !str_contains($plainText, "\0"),
        'filters' => $entry['filters'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
        'decodeparms_operand_policy' => $entry['decodeparms_operand_policy'] ?? null,
        'length_operand_policy' => $entry['length_operand_policy'] ?? null,
        'escaped_length_key_count' => $entry['escaped_length_key_count'] ?? null,
        'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
        'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
    ];

    $required = [
        $summary['safe_text_preserved'],
        $summary['duplicate_length_rejected'],
        $summary['cmap_payload_excluded'],
        ($summary['filters'] ?? null) === ['FlateDecode'],
        ($summary['filter_operand_policy'] ?? null) === 'filters_resolved',
        ($summary['filter_decode_policy'] ?? null) === 'filter_decoders_resolved',
        ($summary['decodeparms_operand_policy'] ?? null) === 'decodeparms_resolved',
        ($summary['length_operand_policy'] ?? null) === 'reject_duplicate_length_declarations',
        ($summary['escaped_length_key_count'] ?? null) === ($escapedDuplicateLengthKey ? 1 : 0),
        ($summary['executes_python_or_models'] ?? null) === false,
        ($summary['executes_external_pdf_tools'] ?? null) === false,
    ];
    if (in_array(false, $required, true)) {
        throw new RuntimeException('Expected duplicate-Length CMap smoke flags to pass: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    $cases[$caseName] = $summary;
    foreach ($lines as $line) {
        $paragraphs[] = $line;
    }
}

$evidence = [
    'scenario' => 'wordpress_pdf_malformed_cmap_duplicate_length_currentbase',
    'source' => 'native-pdf-malformed-cmap-duplicate-length-filter-boundary-currentbase',
    'support_component' => 'pdf-cmap-stream-filter-length-owner-review',
    'cases' => $cases,
    'duplicate_length_declarations_rejected' => array_reduce(
        $cases,
        static fn (bool $carry, array $case): bool => $carry && ($case['duplicate_length_rejected'] ?? false) === true,
        true
    ),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_malformed_cmap_duplicate_length\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($paragraphs as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
