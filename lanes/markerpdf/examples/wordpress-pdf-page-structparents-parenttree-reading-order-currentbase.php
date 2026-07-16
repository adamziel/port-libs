<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf '
    . '/BodyAlias << /MCID 1 >> BDC 72 704 Td (Page one body second) Tj EMC '
    . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Page one heading first) Tj EMC '
    . '/Artifact << /MCID 2 >> BDC 72 680 Td (Page one artifact noise) Tj EMC ET';
$pageTwoContent = 'BT /F1 12 Tf '
    . '/BodyAlias << /MCID 1 >> BDC 72 704 Td (Page two body second) Tj EMC '
    . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Page two heading first) Tj EMC '
    . '/Artifact << /MCID 2 >> BDC 72 680 Td (Page two artifact noise) Tj EMC ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 1 /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 40 0 R /ParentTree 30 0 R /K [21 0 R 22 0 R 24 0 R 25 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /BodyAlias /P 20 0 R /K 1 >>\nendobj\n"
    . "24 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /K 0 >>\nendobj\n"
    . "25 0 obj\n<< /Type /StructElem /S /BodyAlias /P 20 0 R /K 1 >>\nendobj\n"
    . "30 0 obj\n<< /Kids [31 0 R 32 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Limits [0 0] /Nums [0 [21 0 R 22 0 R]] >>\nendobj\n"
    . "32 0 obj\n<< /Limits [1 1] /Nums [1 [24 0 R 25 0 R]] >>\nendobj\n"
    . "40 0 obj\n<< /DeckTitle /H2 /BodyAlias /P >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$tagged = $extractor->extractTaggedContent($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Page one heading first',
    'Page one body second',
    'Page two heading first',
    'Page two body second',
];

echo '<!-- markerpdf-page-structparents-parenttree-reading-order ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-structtree-parenttree-page-structparents',
    'native_boundary' => 'page /StructParents indexes /StructTreeRoot /ParentTree number-tree arrays before Gutenberg block order',
    'uses_parent_tree_order' => $lines === $expected,
    'number_tree_kids_resolved' => count($tagged) === 4,
    'page_local_mcid_binding' => array_column($tagged, 'page_number') === [1, 1, 2, 2]
        && array_column($tagged, 'mcid') === [0, 1, 0, 1],
    'rolemap_resolved' => array_column($tagged, 'role') === ['H2', 'P', 'H2', 'P'],
    'excluded_unlisted_artifacts' => !str_contains($plainText, 'artifact noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($tagged as $row) {
    $text = htmlspecialchars((string) $row['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (($row['role'] ?? null) === 'H2') {
        echo "<!-- wp:heading {\"level\":2} -->\n";
        echo "<h2>{$text}</h2>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$text}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
