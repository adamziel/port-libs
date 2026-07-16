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
    . "/CMapName /WordPressLiteralOperatorBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex('WordPress Literal Safe Import') . ">\n"
    . "endbfchar\n"
    . "(1 beginbfchar\n"
    . "<0001> <" . $utf16beHex('WordPress Literal CMap Leak') . ">\n"
    . "endbfchar)\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress literal-operator CMap fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressLiteralOperatorBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WordPressLiteralOperatorBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

echo '<!-- markerpdf-malformed-cmap-literal-operator-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-filtered-tounicode-cmap-token-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'cmap_name' => $entry['cmap_name'] ?? null,
    'filters' => $entry['filters'] ?? [],
    'literal_operator_decoy_excluded' => !str_contains($plainText, 'WordPress Literal CMap Leak')
        && !str_contains($plainText, 'beginbfchar'),
    'safe_import_text_preserved' => $plainText === 'WordPress Literal Safe Import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
