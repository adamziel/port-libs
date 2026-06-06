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

$buildPdf = static function () use ($utf16beHex): string {
    $safeText = 'Length Extra Safe Import';
    $leakingText = 'Length Extra CMap Leak';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'WPLengthExtraFilterBoundary-H';
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
        throw new RuntimeException('Unable to compress WordPress malformed CMap Length fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLengthExtraFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter [ /FlateDecode ] /Length " . strlen($compressedCMap) . " /ASCIIHexDecode >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$pdf = $buildPdf();
$lines = $extractor->extractTextLines($pdf);
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);

$evidence = [
    'scenario' => 'wordpress_pdf_malformed_cmap_length_filter_boundary_currentbase',
    'safe_lines' => $lines,
    'plain_text' => $text,
    'malformed_cmap_stream_rejected_before_decode' => ($review['cmap_stream_count'] ?? null) === 0
        && ($review['decoded_cmap_count'] ?? null) === 0,
    'leaking_cmap_text_excluded' => !str_contains($text, 'Length Extra CMap Leak')
        && !str_contains($text, 'WPLengthExtraFilterBoundary-H')
        && !str_contains($text, 'ASCIIHexDecode'),
    'review_source' => $review['source'] ?? null,
    'cmap_stream_count' => $review['cmap_stream_count'] ?? null,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'malformed_cmap_stream_rejected_before_decode',
        'leaking_cmap_text_excluded',
    ] as $key) {
        if (($evidence[$key] ?? false) !== true) {
            throw new RuntimeException("Self-test failed: {$key}");
        }
    }
    if ($lines !== ['Length Extra Safe Import']) {
        throw new RuntimeException('Self-test failed: safe text line mismatch');
    }

    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_malformed_cmap_length_filter_boundary\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
