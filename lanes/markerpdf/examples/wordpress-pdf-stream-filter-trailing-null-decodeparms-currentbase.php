<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$compress = static function (string $content): string {
    $compressed = gzcompress($content);
    if ($compressed === false) {
        throw new RuntimeException('Unable to compress stream-filter DecodeParms boundary smoke content.');
    }

    return $compressed;
};

$safeContent = 'BT /F1 12 Tf 72 720 Td (Fallback Trailing Null DecodeParms Applies) Tj ET';
$safeCompressed = $compress($safeContent);
$leakingContent = 'BT /F1 12 Tf 72 704 Td (Trailing Null DecodeParms Leak) Tj ET';
$leakingCompressed = $compress($leakingContent);
$visibleAfter = 'BT /F1 12 Tf 72 688 Td (Visible After Fallback Boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "4 0 obj\n<< /Filter [ /FlateDecode null ] /DecodeParms [ << /Predictor 1 >> 99 0 R ] /Length " . strlen($safeCompressed) . " >>\nstream\n{$safeCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter [ /FlateDecode null ] /DecodeParms [ 99 0 R null ] /Length " . strlen($leakingCompressed) . " >>\nstream\n{$leakingCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Fallback Trailing Null DecodeParms Applies',
    'Visible After Fallback Boundary',
];
$trailingNullDecodeParmsPreserved = $lines === $expected
    && !str_contains($plainText, 'Trailing Null DecodeParms Leak')
    && !str_contains($plainText, '99 0 R')
    && !str_contains($plainText, 'FlateDecode');

if (!$trailingNullDecodeParmsPreserved) {
    throw new RuntimeException('Trailing null DecodeParms fallback boundary leaked payload bytes or dropped safe searchable text.');
}

echo '<!-- markerpdf:stream-filter-trailing-null-decodeparms-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-trailing-null-decodeparms-boundary',
    'native_boundary' => 'catalogless fallback stream scan preserves DecodeParms aligned to trailing null filters while rejecting unresolved DecodeParms on real filters',
    'paragraphs' => $lines,
    'fallback_trailing_null_decodeparms_preserved' => true,
    'unresolved_real_filter_decodeparms_fail_closed' => !str_contains($plainText, 'Trailing Null DecodeParms Leak'),
    'trailing_null_helper_excluded' => !str_contains($plainText, '99 0 R') && !str_contains($plainText, 'FlateDecode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
