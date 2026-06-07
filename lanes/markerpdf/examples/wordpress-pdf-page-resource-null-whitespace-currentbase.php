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
            throw new RuntimeException('Unable to encode null-whitespace WordPress CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceNullWhitespaceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$nul = "\0";
$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET '
    . 'q /NullWsForm Do Q '
    . '/Span /NullActual BDC BT /F1 12 Tf 72 680 Td (Actual glyph leak) Tj ET EMC';
$form = 'BT /F1 12 Tf 12 24 Td (Null whitespace inherited form text) Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Null whitespace inherited font text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10{$nul}0{$nul}R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullWhitespaceResource /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText (Null whitespace inherited actual text) >>\nendobj\n"
    . "10 0 obj\n<< "
    . "/Font << /F1 5{$nul}0{$nul}R >> "
    . "/XObject << /NullWsForm 7{$nul}0{$nul}R >> "
    . "/Properties << /NullActual 8{$nul}0{$nul}R >> "
    . ">>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Null whitespace inherited font text',
    'Null whitespace inherited form text',
    'Null whitespace inherited actual text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected PDF null-byte whitespace in page resources to preserve WordPress text.');
}

if (($resources['resource_object'] ?? null) !== 10
    || ($resources['font_names'] ?? null) !== ['F1']
    || ($resources['xobject_names'] ?? null) !== ['NullWsForm']
    || ($resources['properties_names'] ?? null) !== ['NullActual']
) {
    throw new RuntimeException('Expected page-resource review metadata to resolve null-whitespace references.');
}

echo '<!-- markerpdf-page-resource-null-whitespace-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-null-whitespace-currentbase',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF null bytes are treated as whitespace inside inherited /Resources and resource-entry indirect references before WordPress paragraph rendering',
    'resource_object' => $resources['resource_object'] ?? null,
    'font_names' => $resources['font_names'] ?? [],
    'xobject_names' => $resources['xobject_names'] ?? [],
    'properties_names' => $resources['properties_names'] ?? [],
    'actual_text_glyph_suppressed' => !str_contains($plainText, 'Actual glyph leak'),
    'resource_names_not_visible_text' => !str_contains($plainText, 'NullWsForm'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
