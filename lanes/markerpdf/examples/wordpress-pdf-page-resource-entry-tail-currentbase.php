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
            throw new RuntimeException('Unable to encode page-resource entry-tail CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceEntryTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /Ftailed 12 Tf 72 720 Td <41> Tj T* '
    . '/Fvalid 12 Tf <42> Tj T* '
    . '/Span /TailedActual BDC <43> Tj EMC T* '
    . '/Span /ValidActual BDC <44> Tj EMC ET '
    . 'q /TailedForm Do Q q /ValidForm Do Q';
$tailedForm = 'BT /Fvalid 12 Tf 12 24 Td (Tailed resource form leak) Tj ET';
$validForm = 'BT /Fvalid 12 Tf 12 24 Td (Valid inherited entry-tail form text) Tj ET';
$tailedCMap = $toUnicodeCMap([
    '41' => 'Tailed resource font leak',
]);
$validCMap = $toUnicodeCMap([
    '42' => 'Valid inherited entry-tail font text',
    '43' => 'Property entry-tail glyph text',
    '44' => 'Valid inherited entry-tail actual glyph',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TailedEntryFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($tailedCMap) . " >>\nstream\n{$tailedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ValidEntryTailFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /ActualText (Tailed resource ActualText leak) >>\nendobj\n"
    . "10 0 obj\n<< "
    . "/Font << /Ftailed 5 0 R 99 0 R /Fvalid 7 0 R >> "
    . "/XObject << /TailedForm 12 0 R 99 0 R /ValidForm 13 0 R >> "
    . "/Properties << /TailedActual 9 0 R 99 0 R /ValidActual 11 0 R >> "
    . ">>\nendobj\n"
    . "11 0 obj\n<< /ActualText (Valid inherited entry-tail ActualText) >>\nendobj\n"
    . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($tailedForm) . " >>\nstream\n{$tailedForm}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($validForm) . " >>\nstream\n{$validForm}\nendstream\nendobj\n"
    . "99 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'A',
    'Valid inherited entry-tail font text',
    'Property entry-tail glyph text',
    'Valid inherited entry-tail ActualText',
    'Valid inherited entry-tail form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected tailed inherited resource entries to fail closed before WordPress text import.');
}

if (($resources['font_names'] ?? null) !== ['Fvalid']
    || ($resources['xobject_names'] ?? null) !== ['ValidForm']
    || ($resources['properties_names'] ?? null) !== ['ValidActual']
) {
    throw new RuntimeException('Expected page-resource review metadata to exclude tailed resource entry references.');
}

echo '<!-- markerpdf-page-resource-entry-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-entry-tail-currentbase',
    'native_boundary' => 'inherited page resource Font, XObject, and Properties entries reject indirect references with non-name tail tokens before WordPress paragraph rendering',
    'inherited_resource_object' => $resources['resource_object'] ?? null,
    'font_names' => $resources['font_names'] ?? [],
    'xobject_names' => $resources['xobject_names'] ?? [],
    'properties_names' => $resources['properties_names'] ?? [],
    'tailed_font_reference_rejected' => !str_contains($plainText, 'Tailed resource font leak'),
    'tailed_actual_text_rejected' => !str_contains($plainText, 'Tailed resource ActualText leak'),
    'tailed_form_reference_rejected' => !str_contains($plainText, 'Tailed resource form leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
