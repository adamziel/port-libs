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
            throw new RuntimeException('Unable to encode duplicate ExtGState WordPress CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressDuplicateExtGStateFontCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = '/Dup#20Text gs BT 72 720 Td <41> Tj ET '
    . '/Valid#20Text gs BT 72 700 Td <42> Tj ET';
$staleCMap = $toUnicodeCMap([
    '41' => 'Stale duplicate ExtGState font leak',
]);
$currentCMap = $toUnicodeCMap([
    '41' => 'Current duplicate ExtGState font leak',
]);
$validCMap = $toUnicodeCMap([
    '42' => 'Valid inherited ExtGState font text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateExtGStateFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateExtGStateFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ValidExtGStateFont /Encoding /Identity-H /ToUnicode 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /ExtGState /Font [/Fstale 12] >>\nendobj\n"
    . "12 0 obj\n<< /Type /ExtGState /Font [/Fcurrent 13] >>\nendobj\n"
    . "13 0 obj\n<< /Type /ExtGState /Font [/Fvalid 14] >>\nendobj\n"
    . "20 0 obj\n<< /Font << /Fstale 5 0 R /Fcurrent 6 0 R /Fvalid 7 0 R >> /ExtGState << /Dup#20Text 11 0 R /Dup#20Text 12 0 R /Valid#20Text 13 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$resources = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)[0]['resources'] ?? [];

if ($lines !== ['A', 'Valid inherited ExtGState font text']) {
    throw new RuntimeException('Expected duplicate ExtGState resource names to be ignored while valid gs text imports.');
}

if (($resources['extgstate_names'] ?? null) !== ['Valid Text']) {
    throw new RuntimeException('Expected duplicate ExtGState names to stay out of page resource review metadata.');
}

echo '<!-- markerpdf-page-resource-duplicate-extgstate-font-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-duplicate-extgstate-font-currentbase',
    'native_boundary' => 'duplicate inherited /ExtGState resource names are ignored before gs font text-state rewrites while valid inherited ExtGState fonts still import',
    'duplicate_extgstate_names_rejected' => !str_contains($plainText, 'Stale duplicate ExtGState font leak')
        && !str_contains($plainText, 'Current duplicate ExtGState font leak'),
    'valid_extgstate_font_preserved' => in_array('Valid inherited ExtGState font text', $lines, true),
    'resource_review_extgstate_names' => $resources['extgstate_names'] ?? [],
    'resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'Dup Text')
        && !str_contains($plainText, 'Valid Text'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
