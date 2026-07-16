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
            throw new RuntimeException('Unable to encode inherited ProcSet smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceProcSetCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageOneContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$pageTwoContent = 'BT /F2 12 Tf 72 720 Td <42> Tj ET';
$cMap = $toUnicodeCMap([
    '41' => 'Direct inherited font text',
    '42' => 'Indirect inherited font text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 2 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [4 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ProcSetSmokeFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "30 0 obj\n<< /ProcSet [/PDF /Text /ImageB /Image#43 /Text] /Font << /F1 7 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /ProcSet 41 0 R /Font << /F2 7 0 R >> >>\nendobj\n"
    . "41 0 obj\n42 0 R\nendobj\n"
    . "42 0 obj\n[/PDF /ImageI /Text]\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$firstResources = $boundary[0]['resources'] ?? [];
$secondResources = $boundary[1]['resources'] ?? [];
$expectedLines = [
    'Direct inherited font text',
    'Indirect inherited font text',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected inherited page resources to render WordPress paragraphs.');
}

if (($firstResources['procset_names'] ?? null) !== ['PDF', 'Text', 'ImageB', 'ImageC']
    || ($secondResources['procset_names'] ?? null) !== ['PDF', 'ImageI', 'Text']
) {
    throw new RuntimeException('Expected inherited direct and indirect ProcSet arrays in page review metadata.');
}

echo '<!-- markerpdf-page-resource-procset-inheritance-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-procset-inheritance-currentbase',
    'native_boundary' => 'inherited page /Resources /ProcSet arrays are preserved as review metadata while font resources drive visible WordPress text',
    'direct_procset_names' => $firstResources['procset_names'] ?? [],
    'indirect_procset_names' => $secondResources['procset_names'] ?? [],
    'direct_procset_array_inherited' => ($firstResources['procset_names'] ?? []) === ['PDF', 'Text', 'ImageB', 'ImageC'],
    'indirect_procset_array_inherited' => ($secondResources['procset_names'] ?? []) === ['PDF', 'ImageI', 'Text'],
    'resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'ProcSet')
        && !str_contains($plainText, 'ImageB')
        && !str_contains($plainText, 'ImageI'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
