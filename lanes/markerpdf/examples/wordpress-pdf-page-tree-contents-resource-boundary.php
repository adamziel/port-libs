<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode CMap fixture text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$parentLeak = 'BT /F1 12 Tf 72 720 Td (Parent Contents Leak) Tj ET';
$orphanLeak = 'BT /F1 12 Tf 72 720 Td (Orphan Stream Leak) Tj ET';
$blankLeafPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($parentLeak) . " >>\nstream\n{$parentLeak}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($orphanLeak) . " >>\nstream\n{$orphanLeak}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$cmap = $toUnicodeCMap([
    '41' => 'Child Page Uses Inherited Resources',
]);
$childPage = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$resourcePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($parentLeak) . " >>\nstream\n{$parentLeak}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($childPage) . " >>\nstream\n{$childPage}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedFont /Encoding /Identity-H /ToUnicode 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($orphanLeak) . " >>\nstream\n{$orphanLeak}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$blankPlainText = $extractor->extractPlainText($blankLeafPdf);
$lines = $extractor->extractTextLines($resourcePdf);
$plainText = $extractor->extractPlainText($resourcePdf);

echo '<!-- markerpdf-page-tree-contents-resource-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page tree present with non-inheritable /Contents and inherited /Resources before Gutenberg paragraph rendering',
    'page_tree_without_leaf_contents_blocks_fallback_stream_scan' => $blankPlainText === '',
    'parent_pages_contents_not_inherited' => !str_contains($blankPlainText, 'Parent Contents Leak')
        && !str_contains($plainText, 'Parent Contents Leak'),
    'orphan_stream_text_excluded' => !str_contains($blankPlainText, 'Orphan Stream Leak')
        && !str_contains($plainText, 'Orphan Stream Leak'),
    'inherited_resources_preserved' => $lines === ['Child Page Uses Inherited Resources'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
