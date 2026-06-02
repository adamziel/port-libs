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

$pageOne = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$pageTwo = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$cmapOne = $toUnicodeCMap(['41' => 'Kids First Branch']);
$cmapTwo = $toUnicodeCMap(['41' => 'Kids Second Branch']);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids 30 0 R /Count 99 >>\nendobj\n"
    . "30 0 obj\n[20 0 R 10 0 R]\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 77 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids 31 0 R /Count 88 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
    . "31 0 obj\n[8 0 R]\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 7 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 5 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidsFirst /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidsSecond /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);

echo '<!-- markerpdf-page-tree-kids-count-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'indirect page-tree /Kids arrays and leaf-derived page count before Gutenberg paragraph rendering',
    'derived_page_count_from_kids' => count($extractor->extractPageLabels($pdf)),
    'ignored_stale_count_values' => $lines === ['Kids First Branch', 'Kids Second Branch'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
