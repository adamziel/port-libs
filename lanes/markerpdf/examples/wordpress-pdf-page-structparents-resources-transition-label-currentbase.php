<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /Fpage 12 Tf '
    . '/DeckBody << /MCID 1 >> BDC 72 700 Td (Deck body follows heading) Tj EMC '
    . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Deck title first) Tj EMC ET';
$pageTwoContent = 'BT /Fparent 12 Tf /DeckNote << /MCID 0 >> BDC 72 720 Td (Inherited resource body) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /PageLabels 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 40 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 4 /Resources << /Font << /Fpage 8 0 R >> /XObject << /Hero 9 0 R >> /Properties << /PActual 11 0 R >> >> /Contents 5 0 R /Dur 5 /Trans 15 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Contents 6 0 R /Dur 8 /Trans << /S /Dissolve /D 1.25 >> >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 120 40] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "11 0 obj\n<< /ActualText (Deck title actual review text) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "15 0 obj\n<< /S /Split /D 0.5 /Dm /H /M /O /Di 0 >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /DeckTitle /H1 /DeckBody /P /DeckNote /P >> /ParentTree 21 0 R /K [22 0 R 23 0 R 24 0 R 25 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Nums [4 [22 0 R 23 0 R] 5 [24 0 R null 25 0 R]] >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /Pg 3 0 R /K 0 >>\nendobj\n"
    . "23 0 obj\n<< /Type /StructElem /S /DeckBody /P 20 0 R /Pg 3 0 R /K 1 >>\nendobj\n"
    . "24 0 obj\n<< /Type /StructElem /S /DeckNote /P 20 0 R /Pg 4 0 R /K 0 >>\nendobj\n"
    . "25 0 obj\n<< /Type /StructElem /S /DeckBody /P 20 0 R /Pg 4 0 R /K 2 >>\nendobj\n"
    . "40 0 obj\n<< /Font << /Fparent 12 0 R >> /ColorSpace << /CS1 /DeviceRGB >> /Properties << /ParentActual 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Alt (Parent resource alt review text) >>\nendobj\n"
    . "50 0 obj\n<< /Nums [0 << /P (deck-) /S /D /St 7 >> 1 << /P (appendix-) /S /D /St 2 >>] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageBoundaries = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageBoundaries) !== 2) {
    throw new RuntimeException('Expected two page-boundary review rows.');
}
if (array_column($pageBoundaries, 'page_label') !== ['deck-7', 'appendix-2']) {
    throw new RuntimeException('Expected current PageLabels on page-boundary rows.');
}
if (($pageBoundaries[0]['resources']['font_names'] ?? []) !== ['Fpage']) {
    throw new RuntimeException('Expected leaf page resources to override inherited resources.');
}
if (($pageBoundaries[1]['resources']['font_names'] ?? []) !== ['Fparent']) {
    throw new RuntimeException('Expected inherited parent resources for the second page.');
}
if (($pageBoundaries[0]['page_presentation']['transition']['style'] ?? null) !== 'Split') {
    throw new RuntimeException('Expected first page transition metadata.');
}
if (($pageBoundaries[1]['page_presentation']['transition']['style'] ?? null) !== 'Dissolve') {
    throw new RuntimeException('Expected second page transition metadata.');
}
if ($lines !== ['Deck title first', 'Deck body follows heading', 'Inherited resource body']) {
    throw new RuntimeException('Expected StructParents ParentTree reading order.');
}
if (str_contains($plainText, 'deck-7') || str_contains($plainText, 'Dissolve') || str_contains($plainText, 'Parent resource alt review text')) {
    throw new RuntimeException('Expected labels, transitions, and resource review text to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-page-structparents-resources-transition-label-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-boundary-review-parser',
    'native_boundary' => 'page /StructParents, effective /Resources, /Dur /Trans, and current /PageLabels review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_labels' => array_column($pageBoundaries, 'page_label'),
    'struct_parent_keys' => array_column($pageBoundaries, 'struct_parents'),
    'parent_tree_mcids' => array_map(static fn (array $row): array => $row['parent_tree']['mcids'] ?? [], $pageBoundaries),
    'resource_inheritance' => array_map(static fn (array $row): bool => (bool) ($row['resources']['inherited'] ?? false), $pageBoundaries),
    'transition_styles' => array_map(static fn (array $row): ?string => $row['page_presentation']['transition']['style'] ?? null, $pageBoundaries),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'deck-7')
        && !str_contains($plainText, 'appendix-2')
        && !str_contains($plainText, 'Dissolve')
        && !str_contains($plainText, 'Parent resource alt review text'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-boundary-review ' . $htmlJson([
    'pages' => $pageBoundaries,
]) . " -->\n";
