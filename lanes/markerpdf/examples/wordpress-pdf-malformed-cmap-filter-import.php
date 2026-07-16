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

$safeText = 'WP Duplicate DecodeParms Import';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPDuplicateDecodeParmsBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<{$sourceCode}> <" . $utf16beHex('WP Duplicate DecodeParms CMap Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress duplicate DecodeParms CMap fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPDuplicateDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPDuplicateDecodeParmsBoundary-H /Filter /FlateDecode /DecodeParms << /Predictor 1 >> /Decode#50arms << /Predictor 12 /Columns 1 >> /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$evidence = [
    'scenario' => 'wordpress_pdf_malformed_cmap_duplicate_decodeparms_filter_import',
    'paragraphs' => $lines,
    'safe_fallback_text' => $lines === [$safeText]
        && $text === $safeText
        && !str_contains($text, 'WP Duplicate DecodeParms CMap Leak')
        && !str_contains($text, 'WPDuplicateDecodeParmsBoundary-H'),
    'duplicate_decodeparms_rejected' => ($review['duplicate_decodeparms_declaration_count'] ?? null) === 1
        && (($entry['duplicate_decodeparms_declaration_count'] ?? null) === 1)
        && (($entry['decodeparms_operand_policy'] ?? null) === 'reject_duplicate_decodeparms_declarations')
        && (($entry['filter_decode_policy'] ?? null) === 'reject_duplicate_decodeparms_declarations'),
    'escaped_decodeparms_key_count' => $review['escaped_decodeparms_key_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filter_decode_policy' => $entry['filter_decode_policy'] ?? null,
    'decodeparms_operand_policy' => $entry['decodeparms_operand_policy'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    foreach (['safe_fallback_text', 'duplicate_decodeparms_rejected'] as $flag) {
        if (($evidence[$flag] ?? false) !== true) {
            throw new RuntimeException('Failed markerPDF malformed CMap duplicate DecodeParms smoke: ' . $flag);
        }
    }

    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_malformed_cmap_filter_import\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
