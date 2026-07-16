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

$safeText = 'Vertical Tab Safe Import';
$leakingText = 'Vertical Tab CMap Leak';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMapName = 'WPVerticalTabFilterBoundary-H';
$verticalTab = "\v";

$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<{$sourceCode}>{$verticalTab}<" . $utf16beHex($leakingText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress vertical-tab CMap fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPVerticalTabFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

if ($lines !== [$safeText]) {
    throw new RuntimeException('Expected malformed vertical-tab CMap row to preserve safe fallback text.');
}
if (str_contains($plainText, $leakingText) || str_contains($plainText, $cMapName) || str_contains($plainText, 'beginbfchar')) {
    throw new RuntimeException('Expected malformed vertical-tab CMap program text to stay excluded.');
}
if (($review['decoded_cmap_count'] ?? null) !== 1 || ($entry['decoded_with_current_operands'] ?? null) !== true) {
    throw new RuntimeException('Expected filtered vertical-tab CMap stream to decode for review-only metadata.');
}
if (($entry['filter_operand_policy'] ?? null) !== 'filters_resolved') {
    throw new RuntimeException('Expected valid Flate filter operands for vertical-tab CMap boundary fixture.');
}

echo '<!-- markerpdf-malformed-cmap-vertical-tab-filter-boundary-currentbase-smoke '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'native_boundary' => 'vertical tab is not PDF whitespace inside filtered ToUnicode CMap rows',
        'safe_text_preserved' => true,
        'vertical_tab_cmap_row_rejected' => true,
        'payload_excluded' => true,
        'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
        'filters' => $entry['filters'] ?? [],
        'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
        'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
    ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
