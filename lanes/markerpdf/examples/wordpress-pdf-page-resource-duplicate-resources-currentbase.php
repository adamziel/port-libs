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
            throw new RuntimeException('Unable to encode duplicate page resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDuplicateResourcesCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /DupActual BDC <42> Tj EMC ET q /CurrentForm Do Q q /StaleForm Do Q';
$currentForm = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
$staleForm = 'BT /F1 12 Tf 12 24 Td <44> Tj ET';
$nestedDecoyForm = 'BT /F1 12 Tf 12 24 Td (Nested duplicate resource decoy leak) Tj ET';
$currentCMap = $toUnicodeCMap([
    '41' => 'Current duplicate resource font text',
    '42' => 'Current duplicate resource actual glyph',
    '43' => 'Current duplicate resource form text',
]);
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale duplicate resource font leak',
    '42' => 'Stale duplicate resource actual glyph leak',
    '44' => 'Stale duplicate resource form leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 50 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 20 0 R /PieceInfo << /WPReview << /Private << /Resources 40 0 R >> >> >> /Resources 30 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateResourceFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText (Stale duplicate resource ActualText leak) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateResourceFont /Encoding /Identity-H /ToUnicode 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "12 0 obj\n<< /ActualText (Current duplicate resource ActualText) >>\nendobj\n"
    . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleForm 7 0 R >> /Properties << /DupActual 8 0 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 9 0 R >> /XObject << /CurrentForm 11 0 R >> /Properties << /DupActual 12 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /CurrentForm 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($nestedDecoyForm) . " >>\nstream\n{$nestedDecoyForm}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleForm 7 0 R >> /Properties << /DupActual 8 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Current duplicate resource font text',
    'Current duplicate resource ActualText',
    'Current duplicate resource form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected the last top-level page /Resources entry to drive WordPress paragraphs.');
}

echo '<!-- markerpdf-page-resource-duplicate-resources-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-resources-currentbase',
    'native_boundary' => 'duplicate top-level page /Resources selects the last resource dictionary before nested decoys or stale entries',
    'duplicate_resources_last_wins' => ($resources['resource_object'] ?? null) === 30,
    'page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'page_resource_inherited' => $resources['inherited'] ?? null,
    'current_font_entry_resolved' => ($resources['font_names'] ?? []) === ['F1'],
    'current_xobject_entry_resolved' => ($resources['xobject_names'] ?? []) === ['CurrentForm'],
    'current_properties_entry_resolved' => ($resources['properties_names'] ?? []) === ['DupActual'],
    'stale_resource_text_excluded' => !str_contains($plainText, 'Stale duplicate resource'),
    'nested_decoy_excluded' => !str_contains($plainText, 'Nested duplicate resource decoy leak'),
    'raw_glyph_fallback_excluded' => !str_contains($plainText, 'Current duplicate resource actual glyph'),
    'raw_resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'CurrentForm')
        && !str_contains($plainText, 'StaleForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
