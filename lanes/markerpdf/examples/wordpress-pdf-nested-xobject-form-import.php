<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

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

$parentCMap = $toUnicodeCMap(['41' => 'Parent Form']);
$childCMap = $toUnicodeCMap(['42' => 'Child Form']);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Page Before Nested Form) Tj ET q /ParentForm Do Q BT /F1 12 Tf 72 672 Td (Page After Nested Form) Tj ET';
$parentFormContent = 'BT /F1 12 Tf 12 24 Td <41> Tj ET q /ChildForm Do Q';
$childFormContent = 'BT /F1 12 Tf 12 12 Td <42> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /XObject << /ParentForm 5 0 R >> >> /Contents 7 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 8 0 R >> /XObject << /ChildForm 6 0 R >> >> /Length " . strlen($parentFormContent) . " >>\nstream\n{$parentFormContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 10 0 R >> >> /Length " . strlen($childFormContent) . " >>\nstream\n{$childFormContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentFormSubset /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ChildFormSubset /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($childCMap) . " >>\nstream\n{$childCMap}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-nested-xobject-form-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'nested /Subtype /Form XObject resource fonts before Gutenberg paragraph rendering',
    'parent_form_resource_scope_preserved' => str_contains($plainText, 'Parent Form'),
    'child_form_resource_scope_preserved' => str_contains($plainText, 'Child Form'),
    'page_font_did_not_decode_form_glyphs' => !str_contains($plainText, "\nA\n") && !str_contains($plainText, "\nB\n"),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
