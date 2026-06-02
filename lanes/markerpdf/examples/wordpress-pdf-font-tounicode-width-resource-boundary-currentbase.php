<?php

declare(strict_types=1);

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
            throw new RuntimeException('Unable to encode CMap fixture text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$ancestorCMap = $toUnicodeCMap([
    '41' => 'Ancestor',
    '42' => 'Leak',
]);
$localCMap = $toUnicodeCMap([
    '43' => 'Local Resource',
]);
$content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm <41> Tj 1 0 0 1 92 720 Tm <42> Tj '
    . 'T* /F2 12 Tf <43> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F2 6 0 R >> >> /Contents 8 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /AncestorSubset /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 9 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /AncestorSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 2000 /W [65 66 2000] >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LocalSubset /Encoding /Identity-H /ToUnicode 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($localCMap) . " >>\nstream\n{$localCMap}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($ancestorCMap) . " >>\nstream\n{$ancestorCMap}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-font-tounicode-width-resource-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-font-tounicode-width-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'nearest_resource_dictionary_wins' => true,
    'ancestor_font_tounicode_excluded' => !str_contains($plainText, 'Ancestor') && !str_contains($plainText, 'Leak'),
    'ancestor_font_widths_excluded' => ($lines[0] ?? null) === 'A B',
    'page_local_font_resource_preserved' => in_array('Local Resource', $lines, true),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
