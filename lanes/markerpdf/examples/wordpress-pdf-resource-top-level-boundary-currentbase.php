<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode focused CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressResourceBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET '
    . 'q /CurrentForm Do Q '
    . '/P /ParentActual BDC BT /F1 12 Tf 72 680 Td (Parent actual glyph noise) Tj ET EMC';
$parentForm = 'BT /F1 12 Tf 12 24 Td (Parent inherited form text) Tj ET';
$privateForm = 'BT /F1 12 Tf 12 24 Td (Private PieceInfo form leak) Tj ET';
$parentCMap = $toUnicodeCMap('Parent inherited resources text');
$privateCMap = $toUnicodeCMap('Private PieceInfo resource leak');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R "
    . "/PieceInfo << /WPReview << /Private << /Resources << /Font << /F1 8 0 R >> /XObject << /CurrentForm 12 0 R >> /Properties << /ParentActual << /ActualText (Private PieceInfo actual leak) >> >> >> /ReviewOnly true >> >> >> "
    . "/Resources null /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentInherited /Encoding /Identity-H /ToUnicode 13 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PrivatePieceInfo /Encoding /Identity-H /ToUnicode 14 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /CurrentForm 11 0 R >> /Properties << /ParentActual << /ActualText (Parent actual resource text) >> >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$expected = [
    'Parent inherited resources text',
    'Parent inherited form text',
    'Parent actual resource text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected parent page-tree resources to win over nested private PieceInfo resources.');
}

$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-page-resource-top-level-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'top-level page /Resources null inherits parent /Pages resources while nested PieceInfo private /Resources remains review-only',
    'inherits_parent_resources' => ($resourceMetadata['inherited'] ?? null) === true,
    'resource_owner_object' => $resourceMetadata['resource_owner_object'] ?? null,
    'resource_object' => $resourceMetadata['resource_object'] ?? null,
    'private_pieceinfo_resources_promoted' => str_contains($plainText, 'Private PieceInfo'),
    'actual_text_uses_parent_properties' => in_array('Parent actual resource text', $lines, true),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
