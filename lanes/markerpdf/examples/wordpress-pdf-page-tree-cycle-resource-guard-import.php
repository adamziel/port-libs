<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BboxGeometry;
use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/BboxGeometry.php';
require_once __DIR__ . '/../src/PdfImageRenderer.php';
require_once __DIR__ . '/../src/MarkerAppPreview.php';
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

$pageOne = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$pageTwo = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$orphan = 'BT /F1 12 Tf 72 720 Td (Orphan fallback leak) Tj ET';
$cmapOne = $toUnicodeCMap(['41' => 'Cycle Resource First']);
$cmapTwo = $toUnicodeCMap(['41' => 'Cycle Resource Second']);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [10 0 R 2 0 R 10 0 R 20 0 R] /Count 99 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R 10 0 R 3 0 R] /Count 77 /MediaBox [0 0 300 400] /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [8 0 R 20 0 R] /Count 88 /MediaBox [0 0 500 600] /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 9 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CycleFirst /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CycleSecond /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($orphan) . " >>\nstream\n{$orphan}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview(new PdfImageRenderer(new BboxGeometry()));
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$summary = $preview->openPdfSummary($pdf);

echo '<!-- markerpdf-page-tree-cycle-resource-guard-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'cyclic page-tree /Kids guard plus inherited /Resources lookup before Gutenberg paragraph rendering',
    'derived_page_count_from_reachable_leaves' => $summary['page_count'],
    'ignored_stale_count_values' => $summary['page_count'] === 2,
    'duplicate_page_leaves_blocked' => $lines === ['Cycle Resource First', 'Cycle Resource Second'],
    'inherited_resources_preserved' => str_contains($plainText, 'Cycle Resource First') && str_contains($plainText, 'Cycle Resource Second'),
    'orphan_fallback_stream_excluded' => !str_contains($plainText, 'Orphan fallback leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
