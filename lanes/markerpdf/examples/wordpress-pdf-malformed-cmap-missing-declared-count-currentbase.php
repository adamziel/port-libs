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
    string $countOperand,
    string $kind,
    string $safeText,
    string $leakingText,
    string $baseFont,
    string $cMapName
) use ($utf16beHex): string {
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $targetHex = $utf16beHex($leakingText);
    $beginOperator = $kind === 'range' ? 'beginbfrange' : 'beginbfchar';
    $endOperator = $kind === 'range' ? 'endbfrange' : 'endbfchar';
    $rows = $kind === 'range'
        ? "<{$sourceCode}> <{$sourceCode}> <{$targetHex}>\n"
        : "<{$sourceCode}> <{$targetHex}>\n";

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "{$countOperand}{$beginOperator}\n"
        . $rows
        . "{$endOperator}\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress WordPress missing declared-count CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$fixtures = [
    [
        'pdf' => $buildPdf(
            '',
            'char',
            'Missing Count Char Safe Import',
            'Missing Count Char CMap Leak',
            'WPMissingDeclaredBfcharCountBoundary',
            'WPMissingBfcharCountBoundary-H'
        ),
        'safe' => 'Missing Count Char Safe Import',
        'leak' => 'Missing Count Char CMap Leak',
        'kind' => 'bfchar',
    ],
    [
        'pdf' => $buildPdf(
            '/Rows ',
            'range',
            'Missing Count Range Safe Import',
            'Missing Count Range CMap Leak',
            'WPMissingDeclaredBfrangeCountBoundary',
            'WPMissingBfrangeCountBoundary-H'
        ),
        'safe' => 'Missing Count Range Safe Import',
        'leak' => 'Missing Count Range CMap Leak',
        'kind' => 'bfrange',
    ],
];

$paragraphs = [];
$reviews = [];
foreach ($fixtures as $fixture) {
    $lines = $extractor->extractTextLines($fixture['pdf']);
    $plainText = $extractor->extractPlainText($fixture['pdf']);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($fixture['pdf']);

    if ($lines !== [$fixture['safe']]) {
        throw new RuntimeException('Malformed missing-count CMap boundary did not preserve safe WordPress text.');
    }
    if (
        str_contains($plainText, $fixture['leak'])
        || str_contains($plainText, 'beginbfchar')
        || str_contains($plainText, 'beginbfrange')
    ) {
        throw new RuntimeException('Malformed missing-count CMap boundary leaked rejected ToUnicode mapping data.');
    }

    $paragraphs[] = $fixture['safe'];
    $reviews[$fixture['kind']] = [
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'filters' => $review['entries'][0]['filters'] ?? [],
        'decoded_with_current_operands' => $review['entries'][0]['decoded_with_current_operands'] ?? null,
    ];
}

echo "<!-- wp:port-libs/markerpdf-cmap-boundary "
    . json_encode([
        'scenario' => 'filtered ToUnicode missing declared count boundary',
        'safe_text_preserved' => true,
        'missing_bfchar_count_rejected' => true,
        'non_integer_bfrange_count_rejected' => true,
        'reviews' => $reviews,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_UNESCAPED_SLASHES)
    . " -->\n";

foreach ($paragraphs as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
