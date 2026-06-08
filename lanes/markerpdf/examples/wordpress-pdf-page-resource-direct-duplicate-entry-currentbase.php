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
            throw new RuntimeException('Unable to encode direct duplicate resource smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressDirectDuplicateResourceEntryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /Fdup 12 Tf 72 720 Td <41> Tj T* '
    . '/Fvalid 12 Tf <42> Tj T* '
    . '/Span /DupActual BDC <43> Tj EMC T* '
    . '/Span /ValidActual BDC <44> Tj EMC ET';
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale direct duplicate font leak',
]);
$currentCMap = $toUnicodeCMap([
    '41' => 'Current direct duplicate font leak',
]);
$validCMap = $toUnicodeCMap([
    '42' => 'Valid direct duplicate boundary font text',
    '43' => 'Direct duplicate property raw glyph',
    '44' => 'Valid direct duplicate boundary actual glyph',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< "
    . "/Font << "
    . "/Fdup << /Type /Font /Subtype /Type0 /BaseFont /StaleDirectDuplicate /Encoding /Identity-H /ToUnicode 5 0 R >> "
    . "/Fdup << /Type /Font /Subtype /Type0 /BaseFont /CurrentDirectDuplicate /Encoding /Identity-H /ToUnicode 6 0 R >> "
    . "/Fvalid << /Type /Font /Subtype /Type0 /BaseFont /ValidDirectDuplicate /Encoding /Identity-H /ToUnicode 7 0 R >> "
    . ">> "
    . "/Properties << "
    . "/DupActual << /ActualText (Stale direct duplicate ActualText leak) >> "
    . "/DupActual << /ActualText (Current direct duplicate ActualText leak) >> "
    . "/ValidActual << /ActualText (Valid direct duplicate boundary ActualText) >> "
    . ">> "
    . ">>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

$expected = [
    'A',
    'Valid direct duplicate boundary font text',
    'Direct duplicate property raw glyph',
    'Valid direct duplicate boundary ActualText',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected duplicate direct resource names to fail closed while valid siblings import.');
}

if (($resources['font_names'] ?? null) !== ['Fvalid'] || ($resources['properties_names'] ?? null) !== ['ValidActual']) {
    throw new RuntimeException('Expected duplicate direct resource names to be absent from page review metadata.');
}

echo '<!-- markerpdf-page-resource-direct-duplicate-entry-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-direct-duplicate-entry-currentbase',
    'native_boundary' => 'duplicate direct inherited /Font and /Properties entries are suppressed before WordPress text import',
    'duplicate_font_suppressed' => !str_contains($plainText, 'direct duplicate font leak'),
    'duplicate_actual_text_suppressed' => !str_contains($plainText, 'direct duplicate ActualText leak'),
    'valid_font_preserved' => in_array('Valid direct duplicate boundary font text', $lines, true),
    'valid_actual_text_preserved' => in_array('Valid direct duplicate boundary ActualText', $lines, true),
    'review_font_names' => $resources['font_names'] ?? [],
    'review_properties_names' => $resources['properties_names'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
