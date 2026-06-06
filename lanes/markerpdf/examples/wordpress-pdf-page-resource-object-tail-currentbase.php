<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode page-resource object-tail CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . '<41> <' . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceObjectTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /TailForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td (Trailing token resource form leak) Tj ET';
$cmap = $toUnicodeCMap('Trailing token resource font leak');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TrailingTokenResourceFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> >> 99 0 R\nendobj\n"
    . "99 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /TailForm 7 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];

if ($lines !== ['A'] || ($resources['status'] ?? null) !== 'unresolved_or_malformed') {
    throw new RuntimeException('Expected malformed page Resources object tails to fail closed before WordPress import.');
}

echo '<!-- markerpdf-page-resource-object-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-object-tail-currentbase',
    'native_boundary' => 'indirect page /Resources objects must contain one dictionary token before inherited font and Form XObject lookup',
    'trailing_resource_object_rejected' => ($resources['status'] ?? null) === 'unresolved_or_malformed',
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'fallback_visible_text' => $lines,
    'resource_font_text_excluded' => !str_contains($plainText, 'Trailing token resource font leak'),
    'resource_form_text_excluded' => !str_contains($plainText, 'Trailing token resource form leak'),
    'resource_name_text_excluded' => !str_contains($plainText, 'TailForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
