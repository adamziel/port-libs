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
            throw new RuntimeException('Unable to encode page-resource direct-entry CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceDirectEntryBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /Fvalid 12 Tf 72 720 Td <41> Tj T* /Span /GoodActual BDC <42> Tj EMC ET q /ValidForm Do Q';
$formContent = 'BT /Fvalid 12 Tf 12 24 Td <43> Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Valid inherited direct-entry font text',
    '42' => 'Valid inherited direct-entry actual text glyph',
    '43' => 'Valid inherited direct-entry form text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ValidInheritedEntryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /ActualText (Valid inherited direct-entry actual text) >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /BadArray [99 0 R] /BadName /Helvetica /BadString (Font decoy review leak) /Fvalid 5 0 R >> "
    . "/XObject << /BadArray [6 0 R] /BadName /Image /ValidForm 6 0 R >> "
    . "/Properties << /BadArray [7 0 R] /BadName /Artifact /GoodActual 7 0 R >> "
    . "/ColorSpace << /CS1 /DeviceRGB /CS2 [/Indexed /DeviceRGB 0 <00>] >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Valid inherited direct-entry font text',
    'Valid inherited direct-entry actual text',
    'Valid inherited direct-entry form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected valid inherited page resources to drive WordPress text import.');
}

if (($resources['font_names'] ?? null) !== ['Fvalid']
    || ($resources['xobject_names'] ?? null) !== ['ValidForm']
    || ($resources['properties_names'] ?? null) !== ['GoodActual']
    || ($resources['color_space_names'] ?? null) !== ['CS1', 'CS2']
) {
    throw new RuntimeException('Expected malformed direct resource entries to be excluded from review metadata.');
}

echo '<!-- markerpdf-page-resource-direct-entry-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'inherited page resource review metadata excludes malformed direct Font, XObject, and Properties entries while preserving valid resources and direct ColorSpace operands',
    'font_names' => $resources['font_names'] ?? [],
    'xobject_names' => $resources['xobject_names'] ?? [],
    'properties_names' => $resources['properties_names'] ?? [],
    'color_space_names' => $resources['color_space_names'] ?? [],
    'malformed_direct_entries_excluded' => !in_array('BadArray', $resources['font_names'] ?? [], true)
        && !in_array('BadName', $resources['xobject_names'] ?? [], true)
        && !in_array('BadString', $resources['font_names'] ?? [], true),
    'visible_text_excludes_review_decoys' => !str_contains($plainText, 'Font decoy review leak')
        && !str_contains($plainText, 'BadArray')
        && !str_contains($plainText, 'BadName'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
