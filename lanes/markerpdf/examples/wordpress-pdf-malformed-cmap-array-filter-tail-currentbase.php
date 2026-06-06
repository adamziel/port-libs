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
    string $extraOperand,
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
        throw new RuntimeException('Unable to compress malformed CMap array-filter-tail fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter [ /FlateDecode ] {$extraOperand} /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$fixtures = [
    'decoder' => [
        'pdf' => $buildPdf(
            '/ASCIIHexDecode',
            'Array Decoder Safe Import',
            'Array Decoder CMap Leak',
            'WPArrayDecoderTailBoundary-H',
            'WPArrayDecoderTailBoundary'
        ),
        'safe_text' => 'Array Decoder Safe Import',
        'leaking_text' => 'Array Decoder CMap Leak',
        'extra_name' => 'ASCIIHexDecode',
    ],
    'unknown' => [
        'pdf' => $buildPdf(
            '/UnknownArrayFilterTail',
            'Array Unknown Safe Import',
            'Array Unknown CMap Leak',
            'WPArrayUnknownTailBoundary-H',
            'WPArrayUnknownTailBoundary'
        ),
        'safe_text' => 'Array Unknown Safe Import',
        'leaking_text' => 'Array Unknown CMap Leak',
        'extra_name' => 'UnknownArrayFilterTail',
    ],
];

$extractor = new PdfTextExtractor();
$summary = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'array-valued ToUnicode CMap Filter operands with trailing unkeyed names fail closed before WordPress text import',
];
$paragraphs = [];

foreach ($fixtures as $name => $fixture) {
    $lines = $extractor->extractTextLines($fixture['pdf']);
    $plainText = implode("\n", $lines);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($fixture['pdf']);
    $entry = $review['entries'][0] ?? [];
    $filterOperand = $entry['filter_operands'][0] ?? [];

    if ($lines !== [$fixture['safe_text']]) {
        throw new RuntimeException('Expected malformed CMap array filter tail to preserve safe fallback text.');
    }
    if (str_contains($plainText, $fixture['leaking_text'])) {
        throw new RuntimeException('Expected malformed CMap array filter tail payload to stay excluded.');
    }
    if (($review['decoded_cmap_count'] ?? null) !== 0) {
        throw new RuntimeException('Expected malformed CMap array filter tail not to decode.');
    }
    if (($entry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands') {
        throw new RuntimeException('Expected malformed CMap array filter operand policy.');
    }
    if (($filterOperand['extra_filter_name'] ?? null) !== $fixture['extra_name']) {
        throw new RuntimeException('Expected extra CMap array filter name metadata.');
    }

    $summary[$name . '_safe_text_preserved'] = true;
    $summary[$name . '_payload_excluded'] = true;
    $summary[$name . '_decoded_cmap_count'] = $review['decoded_cmap_count'] ?? null;
    $summary[$name . '_invalid_filter_operand_count'] = $review['invalid_filter_operand_count'] ?? null;
    $summary[$name . '_malformed_filter_operand_count'] = $review['malformed_filter_operand_count'] ?? null;
    $summary[$name . '_filter_operand_policy'] = $entry['filter_operand_policy'] ?? null;
    $summary[$name . '_extra_filter_name'] = $filterOperand['extra_filter_name'] ?? null;
    $summary[$name . '_decoded_with_current_operands'] = $entry['decoded_with_current_operands'] ?? null;
    $paragraphs = array_merge($paragraphs, $lines);
}

echo '<!-- markerpdf-malformed-cmap-array-filter-tail-boundary-currentbase-smoke '
    . htmlspecialchars(json_encode($summary, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
