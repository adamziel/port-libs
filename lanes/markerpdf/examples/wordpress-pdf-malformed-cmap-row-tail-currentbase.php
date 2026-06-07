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

$makePdf = static function (
    string $cMapRows,
    string $safeText,
    string $leakingText,
    string $cMapName,
    string $baseFont
) use ($utf16beHex): array {
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMap = str_replace(
        ['{{SOURCE}}', '{{LEAK_HEX}}', '{{CMAP_NAME}}'],
        [$sourceCode, $utf16beHex($leakingText), $cMapName],
        $cMapRows
    );
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress malformed CMap row-tail smoke fixture.');
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

$codespaceTailCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{{CMAP_NAME}} def\n"
    . "2 begincodespacerange\n"
    . "<0000> <FFFF> [<DECO>]\n"
    . "<00AA> <00AA>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "<{{SOURCE}}> <{{SOURCE}}> <{{LEAK_HEX}}>\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$bfrangeTailCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{{CMAP_NAME}} def\n"
    . "1 begincodespacerange\n"
    . "<{{SOURCE}}> <{{SOURCE}}>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "<{{SOURCE}}> <{{SOURCE}}> <{{LEAK_HEX}}> [<DECO>]\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$fixtures = [
    'codespace_tail' => $makePdf(
        $codespaceTailCMap,
        'Codespace Tail Safe Import',
        'Codespace Tail CMap Leak',
        'WPCodespaceRowTailBoundary-H',
        'WPCodespaceRowTailBoundary'
    ),
    'bfrange_tail' => $makePdf(
        $bfrangeTailCMap,
        'Bfrange Tail Safe Import',
        'Bfrange Tail CMap Leak',
        'WPBfrangeRowTailBoundary-H',
        'WPBfrangeRowTailBoundary'
    ),
];

$extractor = new PdfTextExtractor();
$lines = [];
$flags = [
    'source' => 'markerpdf-malformed-cmap-row-tail-filter-boundary-currentbase',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'decoded_cmap_count' => 0,
    'safe_text_preserved' => true,
    'payload_excluded' => true,
    'codespace_tail_rejected' => true,
    'bfrange_tail_rejected' => true,
];

foreach ($fixtures as $key => [$pdf, $safeText, $leakingText, $cMapName]) {
    $fixtureLines = $extractor->extractTextLines($pdf);
    $plainText = implode("\n", $fixtureLines);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    if ($fixtureLines !== [$safeText]) {
        throw new RuntimeException("Expected {$key} malformed CMap row-tail fixture to preserve safe fallback text.");
    }
    if (str_contains($plainText, $leakingText) || str_contains($plainText, $cMapName)) {
        throw new RuntimeException("Expected {$key} malformed CMap row-tail payload to stay excluded.");
    }
    if (($entry['decoded_with_current_operands'] ?? null) !== true) {
        throw new RuntimeException("Expected {$key} CMap filter operands to remain valid and decoded.");
    }

    $flags['decoded_cmap_count'] += (int) ($review['decoded_cmap_count'] ?? 0);
    $lines[] = $safeText;
}

echo '<!-- markerpdf-malformed-cmap-row-tail-filter-boundary-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
