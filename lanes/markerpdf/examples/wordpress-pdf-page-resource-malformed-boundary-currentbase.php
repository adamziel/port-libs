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
            throw new RuntimeException('Unable to encode page resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressMalformedPageResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* '
    . '/P /ParentActual BDC <42> Tj EMC ET q /ParentForm Do Q';
$parentForm = 'BT /F1 12 Tf 12 24 Td (Parent form resource leak) Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Parent font resource leak',
    '42' => 'Parent actual resource glyph',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> /XObject << /ParentForm 5 0 R >> /Properties << /ParentActual << /ActualText (Parent actual resource leak) >> >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /XObject << /ParentForm 5 0 R >> >> /ReviewOnly true >> >> /Resources 99 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentResourceFont /Encoding /Identity-H /ToUnicode 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = ['A', 'B'];
if ($lines !== $expected || str_contains($plainText, 'Parent ')) {
    throw new RuntimeException('Expected malformed page /Resources to fail closed before parent resource inheritance.');
}

$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];

echo '<!-- markerpdf-page-resource-malformed-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'declared but unresolved page /Resources references fail closed before inheriting parent page-tree Font, XObject, or Properties resources',
    'resource_status' => $resourceMetadata['status'] ?? null,
    'resource_resolved' => $resourceMetadata['resolved'] ?? null,
    'resource_owner_object' => $resourceMetadata['resource_owner_object'] ?? null,
    'resource_object' => $resourceMetadata['resource_object'] ?? null,
    'inherits_parent_resources' => ($resourceMetadata['inherited'] ?? null) === true,
    'parent_font_resource_promoted' => str_contains($plainText, 'Parent font resource leak'),
    'parent_form_resource_promoted' => str_contains($plainText, 'Parent form resource leak'),
    'parent_actual_resource_promoted' => str_contains($plainText, 'Parent actual resource leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
