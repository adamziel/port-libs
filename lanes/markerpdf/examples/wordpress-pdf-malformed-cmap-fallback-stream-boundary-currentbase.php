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

$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WordPressFallbackStreamBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex('Fallback Visible Import') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n"
    . "BT /Fcid 12 Tf 72 650 Td (CMap Payload Leak) Tj ET\n";
$content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressFallbackStreamBoundary /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /WordPressFallbackStreamBoundary-H /Filter [ null ] /DecodeParms 99 0 R /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-fallback-stream-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'stream_only_fallback' => true,
    'all_null_cmap_filter_stack' => $entry['filters'] ?? null,
    'cmap_skipped_from_visible_fallback' => $lines === ['Fallback Visible Import']
        && !str_contains($text, 'CMap Payload Leak')
        && !str_contains($text, 'WordPressFallbackStreamBoundary-H')
        && !str_contains($text, 'beginbfchar'),
    'cmap_review_still_decodes' => ($review['cmap_stream_count'] ?? null) === 1
        && ($review['decoded_cmap_count'] ?? null) === 1
        && ($review['filter_decode_error_count'] ?? null) === 0
        && ($entry['decoded_with_current_operands'] ?? null) === true,
    'post_endcmap_payload_excluded_by_cmap_parser' => ($entry['post_endcmap_bytes_excluded'] ?? null) === true
        && (($entry['post_endcmap_byte_count'] ?? 0) > 0),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    foreach (['cmap_skipped_from_visible_fallback', 'cmap_review_still_decodes', 'post_endcmap_payload_excluded_by_cmap_parser'] as $flag) {
        if (($flags[$flag] ?? false) !== true) {
            throw new RuntimeException('Failed markerPDF CMap fallback stream boundary smoke: ' . $flag);
        }
    }

    echo "OK markerpdf-malformed-cmap-fallback-stream-boundary-currentbase\n";
}

echo '<!-- markerpdf-malformed-cmap-fallback-stream-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
