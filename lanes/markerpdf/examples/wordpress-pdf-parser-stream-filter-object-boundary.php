<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale filtered fallback leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current filtered object boundary) Tj T* (Current base fallback) Tj ET';
$staleCompressed = gzcompress($staleContent);
$currentCompressed = gzcompress($currentContent);
if (!is_string($staleCompressed) || !is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress parser stream-filter boundary smoke fixture.');
}

$nestedPayload = 'BT /F1 12 Tf 72 680 Td (Nested fake stream leak) Tj ET';
$inlineImagePayload = "<< /Length " . strlen($nestedPayload) . " >>\n"
    . "stream\n{$nestedPayload}\nendstream\n";
$currentInlineContent = "BI /W 1 /H 1 /CS /RGB /BPC 8 ID\n{$inlineImagePayload}EI\n"
    . $currentContent;

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /NeedsRendering false >>');
$addObject(2, 0, "<< /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");
$addObject(3, 0, "<< /Length " . strlen($currentInlineContent) . " >>\nstream\n{$currentInlineContent}\nendstream");
$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 4\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow(0, 1, 'f')
    . $xrefRow($offsets[3])
    . "trailer\n<< /Size 4 /Root 1 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
if ($lines !== ['Current filtered object boundary', 'Current base fallback']) {
    throw new RuntimeException('Expected current xref-selected stream text only.');
}
if (str_contains($plainText, 'Stale filtered fallback leak') || str_contains($plainText, 'Nested fake stream leak')) {
    throw new RuntimeException('Expected stale and nested stream-looking payload text to stay excluded.');
}

echo '<!-- markerpdf-parser-stream-filter-object-boundary-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-live-direct-stream-parser',
    'native_boundary' => 'fallback stream decoding uses xref-selected direct object bodies and top-level stream dictionaries',
    'current_xref_streams_only' => true,
    'stale_filtered_stream_excluded' => !str_contains($plainText, 'Stale filtered fallback leak'),
    'nested_stream_tokens_excluded' => !str_contains($plainText, 'Nested fake stream leak'),
    'page_labels' => $extractor->extractPageLabels($pdf),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
