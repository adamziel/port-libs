<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf '
    . '/Caption << /MCID 1 >> BDC 72 684 Td (ParentTree caption visible) Tj EMC '
    . '/Figure << /MCID 0 >> BDC 72 720 Td (ParentTree figure visible) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true >> /StructTreeRoot 20 0 R /PageLabels << /Nums [0 << /P (asset-) /S /D /St 44 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 44 /Contents 5 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Figure /Figure /Caption /Caption >> /ParentTree 30 0 R /K [21 0 R 22 0 R] >>\nendobj\n"
    . "30 0 obj\n<< /Kids [31 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Limits [44 44] /Nums [44 [21 0 R 22 0 R null]] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Figure /T (Hero figure structure) /A 23 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /Caption /T (Hero caption structure) /A [24 0 R << /O /Layout /SpaceBefore 12 >>] /K 1 >>\nendobj\n"
    . "23 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/image) /F (Image block) >> << /N (Migration Stage) /V /review /H true >>] >>\nendobj\n"
    . "24 0 obj\n<< /O /UserProperties /P [<< /N (Alt Source) /V (PDF structure tree) >> << /N (Confidence) /V 0.94 /F (94%) >>] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review metadata row.');
}

$page = $pageReviews[0];
$properties = $page['user_properties'] ?? [];
if (($page['struct_parents'] ?? null) !== 44 || count($properties) !== 4) {
    throw new RuntimeException('Expected page StructParents user-property review metadata.');
}
if (str_contains($plainText, 'core/image') || str_contains($plainText, 'Hero figure structure')) {
    throw new RuntimeException('Expected StructElem UserProperties to stay out of visible text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-page-structparent-userproperty-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-structparents-userproperties-parser',
    'native_boundary' => 'page /StructParents ParentTree StructElem /A /UserProperties review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_label' => $page['page_label'] ?? null,
    'struct_parents' => $page['struct_parents'] ?? null,
    'parent_tree_mcids' => $page['parent_tree']['mcids'] ?? [],
    'user_property_names' => array_column($properties, 'name'),
    'user_property_sources' => array_values(array_unique(array_column($properties, 'source'))),
    'hidden_user_property_count' => count(array_filter(
        $properties,
        static fn (array $property): bool => ($property['hidden'] ?? false) === true
    )),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'core/image')
        && !str_contains($plainText, 'Hero figure structure')
        && !str_contains($plainText, 'PDF structure tree'),
]) . " -->\n";

foreach (explode("\n", $plainText) as $line) {
    $text = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$text}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-review ' . $htmlJson([
    'pnum' => $page['pnum'] ?? null,
    'page_object' => $page['page_object'] ?? null,
    'page_label' => $page['page_label'] ?? null,
    'struct_parents' => $page['struct_parents'] ?? null,
    'parent_tree' => $page['parent_tree'] ?? [],
    'user_properties' => $properties,
]) . " -->\n";
