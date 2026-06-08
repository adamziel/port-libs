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

$pdfWithUnclosedArrayCMap = static function (
    string $kind,
    string $safeText,
    string $leakingText,
    string $cMapName,
    string $baseFont
) use ($utf16beHex): array {
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $targetHex = $utf16beHex($leakingText);
    $mappingBlock = $kind === 'range'
        ? "1 beginbfrange\n<{$sourceCode}> <{$sourceCode}> <{$targetHex}>\nendbfrange\n"
        : "1 beginbfchar\n<{$sourceCode}> <{$targetHex}>\nendbfchar\n";

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "[ /MalformedArray (unterminated array owns later tokens)\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress WordPress unclosed-array CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

$extractor = new PdfTextExtractor();
$fixtures = [
    $pdfWithUnclosedArrayCMap(
        'char',
        'Unclosed Array Char Safe Import',
        'Unclosed Array Char CMap Leak',
        'WPUnclosedArrayCharBoundary-H',
        'WPUnclosedArrayCharBoundary'
    ),
    $pdfWithUnclosedArrayCMap(
        'range',
        'Unclosed Array Range Safe Import',
        'Unclosed Array Range CMap Leak',
        'WPUnclosedArrayRangeBoundary-H',
        'WPUnclosedArrayRangeBoundary'
    ),
];

$paragraphs = [];
$decodedCounts = [];
foreach ($fixtures as [$pdf, $safeText, $leakingText, $cMapName]) {
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    if ($lines !== [$safeText]) {
        throw new RuntimeException('Malformed unclosed-array CMap boundary did not preserve safe WordPress text.');
    }
    if (str_contains($plainText, $leakingText) || str_contains($plainText, $cMapName) || str_contains($plainText, 'beginbf')) {
        throw new RuntimeException('Malformed unclosed-array CMap boundary leaked filtered CMap program text.');
    }
    if (($entry['filter_operand_policy'] ?? null) !== 'filters_resolved') {
        throw new RuntimeException('Expected resolved stream filters for malformed unclosed-array CMap boundary.');
    }

    $paragraphs[] = $safeText;
    $decodedCounts[] = $review['decoded_cmap_count'] ?? null;
}

echo '<!-- markerpdf-malformed-cmap-unclosed-array-filter-boundary-currentbase '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'filtered ToUnicode CMap operators inside unterminated arrays stay non-top-level before WordPress text import',
        'safe_text_preserved' => $paragraphs === [
            'Unclosed Array Char Safe Import',
            'Unclosed Array Range Safe Import',
        ],
        'unclosed_array_bfchar_rejected' => true,
        'unclosed_array_bfrange_rejected' => true,
        'payload_excluded' => true,
        'decoded_cmap_counts' => $decodedCounts,
        'paragraphs' => $paragraphs,
    ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($paragraphs as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
