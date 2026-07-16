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
            throw new RuntimeException('Unable to encode direct resource dictionary tail smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressDirectResourceDictionaryTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /TailForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td (Direct dictionary tail form leak) Tj ET';
$cMap = $toUnicodeCMap([
    '41' => 'Direct dictionary tail font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> >> 99 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DirectResourceTail /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "99 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

if ($lines !== ['A']) {
    throw new RuntimeException('Expected malformed direct page resources to fall back to raw searchable text.');
}

if (($resources['status'] ?? null) !== 'unresolved_or_malformed' || ($resources['categories'] ?? null) !== []) {
    throw new RuntimeException('Expected malformed direct page resources to be review-only metadata.');
}

echo '<!-- markerpdf-page-resource-direct-dictionary-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-direct-dictionary-tail-currentbase',
    'native_boundary' => 'direct page /Resources dictionaries with non-name trailing tokens fail closed before inherited lookup and form expansion',
    'direct_resource_dictionary_tail_rejected' => ($resources['status'] ?? null) === 'unresolved_or_malformed',
    'resource_categories_rejected' => ($resources['categories'] ?? null) === [],
    'raw_searchable_text_preserved' => $lines === ['A'],
    'resource_font_text_excluded' => !str_contains($plainText, 'Direct dictionary tail font leak'),
    'resource_form_text_excluded' => !str_contains($plainText, 'Direct dictionary tail form leak'),
    'resource_name_text_excluded' => !str_contains($plainText, 'TailForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
