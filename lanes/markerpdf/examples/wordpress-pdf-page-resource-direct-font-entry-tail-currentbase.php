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
            throw new RuntimeException('Unable to encode direct font resource entry-tail smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceDirectFontEntryTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /Ftailed 12 Tf 72 720 Td <41> Tj T* /Fvalid 12 Tf <42> Tj ET';
$tailedCMap = $toUnicodeCMap([
    '41' => 'Direct tailed font dictionary leak',
]);
$validCMap = $toUnicodeCMap([
    '42' => 'Valid direct font dictionary text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($tailedCMap) . " >>\nstream\n{$tailedCMap}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << "
    . "/Ftailed << /Type /Font /Subtype /Type0 /BaseFont /DirectTailedFont /Encoding /Identity-H /ToUnicode 5 0 R >> 99 0 R "
    . "/Fvalid << /Type /Font /Subtype /Type0 /BaseFont /ValidDirectFont /Encoding /Identity-H /ToUnicode 6 0 R >> "
    . ">> >>\nendobj\n"
    . "99 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = ['A', 'Valid direct font dictionary text'];

$flags = [
    'source' => 'native-pdf-page-resource-direct-font-entry-tail-currentbase',
    'native_boundary' => 'direct Font resource dictionary entries reject non-name tail operands before ToUnicode lookup',
    'tailed_direct_font_dictionary_rejected' => !str_contains($plainText, 'Direct tailed font dictionary leak')
        && ($resources['font_names'] ?? null) === ['Fvalid'],
    'valid_direct_font_dictionary_preserved' => in_array('Valid direct font dictionary text', $lines, true),
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'font_names' => $resources['font_names'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($lines !== $expected || $flags['tailed_direct_font_dictionary_rejected'] !== true) {
    throw new RuntimeException('Expected direct Font resource entry-tail boundary to pass before WordPress import.');
}

echo '<!-- markerpdf-page-resource-direct-font-entry-tail-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
