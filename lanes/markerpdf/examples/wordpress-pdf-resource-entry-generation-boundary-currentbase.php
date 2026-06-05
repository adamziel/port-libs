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
            throw new RuntimeException('Unable to encode resource-entry CMap fixture text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressResourceEntryGenerationCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* '
    . '/P /Actual BDC <42> Tj EMC ET q /StaleForm Do Q q /ValidForm Do Q';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Stale generation form leak) Tj ET';
$validForm = 'BT /F1 12 Tf 12 24 Td (Valid generation form text) Tj ET';
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale generation font leak',
    '42' => 'Stale generation property glyph leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleGenerationFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /ActualText (Stale generation ActualText leak) >>\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Length " . strlen($validForm) . " >>\nstream\n{$validForm}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 1 R >> /XObject << /StaleForm 6 1 R /ValidForm 8 0 R >> /Properties << /Actual 7 1 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$expected = [
    'A',
    'B',
    'Valid generation form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected stale generation resource entries to be excluded before WordPress import.');
}

$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-resource-entry-generation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-entry-generation-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'native_boundary' => 'inherited page resource dictionary entries resolve exact object generations before font maps, Form XObject expansion, or ActualText replacement',
    'inherited_resource_dictionary_selected' => ($resourceMetadata['inherited'] ?? null) === true,
    'resource_owner_object' => $resourceMetadata['resource_owner_object'] ?? null,
    'resource_object' => $resourceMetadata['resource_object'] ?? null,
    'valid_xobject_resource_preserved' => ($resourceMetadata['xobject_names'] ?? []) === ['ValidForm'],
    'stale_generation_resources_excluded' => !str_contains($plainText, 'Stale generation'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
