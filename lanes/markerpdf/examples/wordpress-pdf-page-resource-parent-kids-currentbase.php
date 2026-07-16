<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
            throw new RuntimeException('Unable to encode page resource parent Kids smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentKidsSmokeCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /SiblingForm Do Q';
$siblingForm = 'BT /F1 12 Tf 12 24 Td (Sibling parent form leak) Tj ET';
$siblingCMap = $toUnicodeCMap([
    '41' => 'Sibling parent font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 1 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [] /Count 0 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SiblingParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($siblingCMap) . " >>\nstream\n{$siblingCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($siblingForm) . " >>\nstream\n{$siblingForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 8 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /SiblingForm 7 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);

if ($lines !== ['A'] || $boundary !== []) {
    throw new RuntimeException('Expected page-resource inheritance to fail closed when /Parent is not listed in parent /Kids.');
}

echo '<!-- markerpdf-page-resource-parent-kids-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-parent-kids-currentbase',
    'native_boundary' => 'page /Parent must list the exact child in /Kids before inherited /Resources font or Form XObject lookup',
    'mismatched_parent_kids_blocks_resource_inheritance' => $lines === ['A'],
    'sibling_parent_font_excluded' => !str_contains($plainText, 'Sibling parent font leak'),
    'sibling_parent_form_excluded' => !str_contains($plainText, 'Sibling parent form leak'),
    'page_boundary_resource_review_blocked' => $boundary === [],
    'visible_paragraph_count' => count($lines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
