<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$content = 'BT /F1 12 Tf '
    . '/Body /BodyProp BDC 72 700 Td (Body physical first) Tj EMC '
    . '/Title /TitleProp BDC 72 720 Td (Title structure first) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /Properties << /TitleProp 21 0 R /BodyProp 23 0 R >> >>\nendobj\n"
    . "21 0 obj\n22 0 R\nendobj\n"
    . "22 0 obj\n<< /MCID 0 >>\nendobj\n"
    . "23 0 obj\n24 0 R\nendobj\n"
    . "24 0 obj\n<< /MCID 1 >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /ParentTree 30 0 R /K [25 0 R 26 0 R] >>\nendobj\n"
    . "25 0 obj\n<< /Type /StructElem /S /H1 /P 20 0 R /Pg 3 0 R /K 0 >>\nendobj\n"
    . "26 0 obj\n<< /Type /StructElem /S /P /P 20 0 R /Pg 3 0 R /K 1 >>\nendobj\n"
    . "30 0 obj\n<< /Nums [0 [25 0 R 26 0 R]] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'Title structure first',
    'Body physical first',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected inherited wrapped /Properties entries to drive StructTree MCID reading order.');
}
if (($resources['resource_owner_object'] ?? null) !== 2 || ($resources['resource_object'] ?? null) !== 10) {
    throw new RuntimeException('Expected page review metadata to preserve the inherited resource owner and resource dictionary object.');
}
if (($resources['properties_names'] ?? null) !== ['TitleProp', 'BodyProp']
    || ($boundary[0]['parent_tree']['mcids'] ?? null) !== [0, 1]
) {
    throw new RuntimeException('Expected inherited property names and ParentTree MCIDs in page review metadata.');
}
if (str_contains($plainText, 'TitleProp') || str_contains($plainText, 'BodyProp')) {
    throw new RuntimeException('Expected marked-content property names to stay out of WordPress paragraph text.');
}

echo '<!-- markerpdf-page-resource-struct-property-wrapper-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-page-resource-struct-property-wrapper-currentbase',
    'support_component' => 'native-pdf-page-resource-inheritance',
    'native_boundary' => 'inherited page /Resources /Properties entries may resolve through indirect wrappers before StructTree MCID reading order is applied',
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'properties_names' => $resources['properties_names'] ?? [],
    'parent_tree_mcids' => $boundary[0]['parent_tree']['mcids'] ?? [],
    'wrapped_properties_reordered_text' => $lines === $expectedLines,
    'property_names_excluded_from_paragraphs' => !str_contains($plainText, 'TitleProp') && !str_contains($plainText, 'BodyProp'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
