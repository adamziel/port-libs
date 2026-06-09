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
            throw new RuntimeException('Unable to encode parent-cycle resource smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressParentCycleResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /PrefixForm Do Q q /CycleForm Do Q q /RootForm Do Q';
$prefixForm = 'BT /F1 12 Tf 12 24 Td (WordPress parent-cycle prefix form) Tj ET';
$cycleForm = 'BT /F1 12 Tf 12 24 Td (WordPress parent-cycle decoy form leak) Tj ET';
$rootForm = 'BT /F1 12 Tf 12 24 Td (WordPress root fallback decoy form leak) Tj ET';
$prefixCMap = $toUnicodeCMap([
    '41' => 'WordPress parent-cycle prefix text',
]);
$cycleCMap = $toUnicodeCMap([
    '41' => 'WordPress parent-cycle decoy text leak',
]);
$rootCMap = $toUnicodeCMap([
    '41' => 'WordPress root fallback decoy text leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 /Resources 50 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 11 0 R /Kids [3 0 R 11 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Type /Pages /Parent 10 0 R /Kids [10 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressParentCyclePrefix /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($prefixCMap) . " >>\nstream\n{$prefixCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($prefixForm) . " >>\nstream\n{$prefixForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressParentCycleDecoy /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($cycleCMap) . " >>\nstream\n{$cycleCMap}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($cycleForm) . " >>\nstream\n{$cycleForm}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressRootFallbackDecoy /Encoding /Identity-H /ToUnicode 14 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Length " . strlen($rootCMap) . " >>\nstream\n{$rootCMap}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($rootForm) . " >>\nstream\n{$rootForm}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /PrefixForm 7 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /CycleForm 12 0 R >> >>\nendobj\n"
    . "50 0 obj\n<< /Font << /F1 13 0 R >> /XObject << /RootForm 15 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

if ($lines !== ['WordPress parent-cycle prefix text', 'WordPress parent-cycle prefix form']) {
    throw new RuntimeException('Expected parent-cycle resource smoke text to stay on the selected catalog prefix.');
}

if (
    str_contains($plainText, 'decoy')
    || str_contains($plainText, 'CycleForm')
    || str_contains($plainText, 'RootForm')
) {
    throw new RuntimeException('Expected parent-cycle and root fallback resource decoys to stay out of WordPress text.');
}

echo '<!-- markerpdf:pdf-page-resource-parent-cycle-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-parent-cycle-currentbase',
    'native_boundary' => 'cyclic page Parent chains preserve selected catalog-prefix Resources and exclude cycle-only or root-fallback resources before Gutenberg paragraphs',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'selected_prefix_resource_object' => $resources['resource_object'] ?? null,
    'selected_prefix_resource_owner' => $resources['resource_owner_object'] ?? null,
    'resource_lookup_objects' => $resources['resource_lookup_objects'] ?? [],
    'cycle_resource_decoy_excluded' => !str_contains($plainText, 'WordPress parent-cycle decoy'),
    'root_fallback_resource_excluded' => !str_contains($plainText, 'WordPress root fallback'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
