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
    string $mappingBlock,
    string $cMapName,
    string $baseFont
) use ($utf16beHex): string {
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0001>\n"
        . "<0001>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress WordPress split-row CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$fixtures = [
    'bfchar' => [
        'text' => 'WordPress Split Bfchar Import',
        'cmap' => 'WPSplitBfcharBoundary-H',
        'font' => 'WPSplitBfcharBoundary',
        'block' => static fn (string $mappedText): string => "1 beginbfchar\n"
            . "<0001>\n"
            . "<" . $utf16beHex($mappedText) . ">\n"
            . "endbfchar\n",
    ],
    'bfrange' => [
        'text' => 'WordPress Split Bfrange Import',
        'cmap' => 'WPSplitBfrangeBoundary-H',
        'font' => 'WPSplitBfrangeBoundary',
        'block' => static fn (string $mappedText): string => "1 beginbfrange\n"
            . "<0001>\n"
            . "<0001>\n"
            . "<" . $utf16beHex($mappedText) . ">\n"
            . "endbfrange\n",
    ],
];

$extractor = new PdfTextExtractor();
$summary = [
    'scenario' => 'wordpress_pdf_cmap_split_row_filter_boundary_currentbase',
    'native_boundary' => 'filtered ToUnicode CMap row operands split across PDF whitespace decode before WordPress paragraph import',
    'cases' => [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];
$paragraphs = [];

foreach ($fixtures as $kind => $fixture) {
    $mappedText = $fixture['text'];
    $pdf = $buildPdf(
        $fixture['block']($mappedText),
        $fixture['cmap'],
        $fixture['font']
    );
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $case = [
        'kind' => $kind,
        'mapped_text_preserved' => $plainText === $mappedText,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
        'cmap_program_excluded' => !str_contains($plainText, 'begincmap') && !str_contains($plainText, (string) $fixture['cmap']),
    ];
    $summary['cases'][] = $case;
    $paragraphs[] = $plainText;

    if (
        $case['mapped_text_preserved'] !== true
        || $case['decoded_cmap_count'] !== 1
        || $case['filter_operand_policy'] !== 'filters_resolved'
        || $case['filter_decode_policy'] !== 'filter_decoders_resolved'
        || $case['cmap_program_excluded'] !== true
    ) {
        throw new RuntimeException('Expected split-row filtered CMap import smoke to preserve mapped text: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
    }
}

echo '<!-- markerpdf-cmap-split-row-filter-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($paragraphs as $paragraph) {
    echo '<!-- wp:paragraph -->' . "\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' . "\n";
    echo '<!-- /wp:paragraph -->' . "\n";
}
