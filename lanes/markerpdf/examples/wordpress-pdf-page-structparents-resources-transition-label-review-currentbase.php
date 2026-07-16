<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf '
    . '/BodyAlias /BodyProp BDC 72 700 Td (Body glyph noise) Tj EMC '
    . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Deck heading visible) Tj EMC ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /PageLabels << /Nums [0 << /P (deck-) /S /D /St 3 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 7 0 R >> /Properties << /BodyProp 8 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Contents 5 0 R /Dur 8 /Trans 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /S /Dissolve /D 0.5 >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "8 0 obj\n<< /MCID 1 /ActualText (Inherited resource body) >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 40 0 R /ParentTree 30 0 R /K [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /BodyAlias /P 20 0 R /K 1 >>\nendobj\n"
    . "30 0 obj\n<< /Nums [0 [21 0 R 22 0 R]] >>\nendobj\n"
    . "40 0 obj\n<< /DeckTitle /H2 /BodyAlias /P >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$pageTexts = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$taggedRows = $textExtractor->extractTaggedContent($pdf);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review row.');
}

$pageReview = $pageReviews[0];
$structureRows = $pageReview['structure_marked_content'] ?? [];
$presentation = $pageReview['page_presentation'] ?? [];

if (array_column($structureRows, 'mcid') !== [0, 1]) {
    throw new RuntimeException('Expected ParentTree MCID review rows.');
}
if (array_column($structureRows, 'role') !== ['H2', 'P']) {
    throw new RuntimeException('Expected RoleMap-resolved review roles.');
}
if (($presentation['page_label'] ?? null) !== 'deck-3') {
    throw new RuntimeException('Expected PageLabels metadata on the page review row.');
}
if (($presentation['transition']['style'] ?? null) !== 'Dissolve') {
    throw new RuntimeException('Expected transition metadata on the page review row.');
}
if (!str_contains($plainText, 'Inherited resource body') || str_contains($plainText, 'Body glyph noise')) {
    throw new RuntimeException('Expected inherited Resources ActualText replacement.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structparents-resources-transition-label-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-structparents-resources-transition-label-review-parser',
    'native_boundary' => 'page /StructParents ParentTree rows composed with inherited /Resources text replacement, PageLabels, and page transition review metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'page_review_count' => count($pageReviews),
    'page_label' => $presentation['page_label'] ?? null,
    'transition_style' => $presentation['transition']['style'] ?? null,
    'parent_tree_mcids' => array_column($structureRows, 'mcid'),
    'parent_tree_roles' => array_column($structureRows, 'role'),
    'resources_resolved_for_tagged_text' => array_column($structureRows, 'resources_resolved_for_tagged_text'),
    'tagged_text' => array_column($taggedRows, 'text'),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'deck-3')
        && !str_contains($plainText, 'Dissolve')
        && !str_contains($plainText, 'Body glyph noise'),
]) . " -->\n";

foreach ($pageTexts as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structparents-resources-transition-label-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_object' => $pageReview['page_object'],
    'page_presentation' => [
        'page_label' => $presentation['page_label'] ?? null,
        'display_duration' => $presentation['display_duration'] ?? null,
        'transition' => $presentation['transition'] ?? null,
    ],
    'structure_marked_content' => array_map(static fn (array $row): array => [
        'source' => $row['source'] ?? null,
        'mcid' => $row['mcid'] ?? null,
        'raw_role' => $row['raw_role'] ?? null,
        'role' => $row['role'] ?? null,
        'page_label' => $row['page_label'] ?? null,
        'content_tags' => $row['content_tags'] ?? [],
        'resources_resolved_for_tagged_text' => $row['resources_resolved_for_tagged_text'] ?? null,
        'review_only' => $row['review_only'] ?? null,
    ], $structureRows),
]) . " -->\n";
