<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf '
    . '/Headline << /MCID 0 >> BDC 72 720 Td (Markup title visible) Tj EMC '
    . '/Body << /MCID 1 >> BDC 72 684 Td (Markup body visible) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (markup-) /S /D /St 21 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Contents 30 0 R /Annots [7 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Highlight /StructParent 12 /Rect [72 700 260 720] /QuadPoints [72 720 260 720 72 700 260 700] /Contents (Private markup comment) /T (Import Editor) /Subj (Editorial highlight) /NM (markup-annotation-1) /C [1 0.9 0] /CA 0.35 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Markup Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 676 280 732] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Headline /H1 /Body /P /ReviewMarkup /Span >> /ParentTree 41 0 R /K [42 0 R 43 0 R 44 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Nums [5 [42 0 R 43 0 R] 12 44 0 R] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /Headline /Pg 3 0 R /T (Markup heading structure) /K 0 >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Markup body structure) /K 1 >>\nendobj\n"
    . "44 0 obj\n<< /Type /StructElem /S /ReviewMarkup /Pg 3 0 R /T (Markup annotation structure) /Alt (Accessible markup note) /ActualText (Actual markup review) /ID (markup-struct-12) /C [/editorial /highlight] /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page StructParents/thread/markup review row.');
}

$pageReview = $pageReviews[0];
$markupReview = $pageReview['text_markup_annotations'][0] ?? [];

if (($pageReview['struct_parents'] ?? null) !== 5) {
    throw new RuntimeException('Expected page StructParents key.');
}
if (($pageReview['article_thread_titles'] ?? []) !== ['Markup Article Thread']) {
    throw new RuntimeException('Expected page article-thread review metadata.');
}
if (($markupReview['struct_parent'] ?? null) !== 12 || ($markupReview['struct_object'] ?? null) !== 44) {
    throw new RuntimeException('Expected markup annotation StructParent ParentTree metadata.');
}
if (($markupReview['role'] ?? null) !== 'Span' || ($markupReview['title'] ?? null) !== 'Markup annotation structure') {
    throw new RuntimeException('Expected markup annotation StructElem review fields.');
}
if ($lines !== ['Markup title visible', 'Markup body visible']) {
    throw new RuntimeException('Expected StructParents visible text order.');
}
if (str_contains($plainText, 'Private markup comment')
    || str_contains($plainText, 'Markup Article Thread')
    || str_contains($plainText, 'Markup annotation structure')
    || str_contains($plainText, 'Accessible markup note')
    || str_contains($plainText, 'Actual markup review')
    || str_contains($plainText, 'markup-struct-12')
) {
    throw new RuntimeException('Expected markup and structure review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structparents-thread-markup-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-structparents-thread-markup-review-parser',
    'native_boundary' => 'page /StructParents ParentTree MCID rows compose with catalog /Threads and text-markup annotation /StructParent object rows before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'page_label' => $pageReview['page_label'] ?? null,
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'parent_tree_mcids' => $pageReview['parent_tree']['mcids'] ?? [],
    'article_thread_titles' => $pageReview['article_thread_titles'] ?? [],
    'markup_struct_parent' => $markupReview['struct_parent'] ?? null,
    'markup_struct_object' => $markupReview['struct_object'] ?? null,
    'markup_role' => $markupReview['role'] ?? null,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Private markup comment')
        && !str_contains($plainText, 'Markup Article Thread')
        && !str_contains($plainText, 'Markup annotation structure')
        && !str_contains($plainText, 'Accessible markup note')
        && !str_contains($plainText, 'Actual markup review')
        && !str_contains($plainText, 'markup-struct-12'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structparents-thread-markup-review ' . $htmlJson([
    'page_object' => $pageReview['page_object'] ?? null,
    'page_label' => $pageReview['page_label'] ?? null,
    'parent_tree' => $pageReview['parent_tree'] ?? [],
    'article_thread_beads' => array_map(static fn (array $bead): array => [
        'thread_title' => $bead['thread_title'] ?? null,
        'bead_object' => $bead['bead_object'] ?? null,
        'page_label' => $bead['page_label'] ?? null,
        'rect' => $bead['rect'] ?? null,
    ], $pageReview['article_thread_beads'] ?? []),
    'text_markup_annotations' => array_map(static fn (array $markup): array => [
        'annotation_object' => $markup['annotation_object'] ?? null,
        'subtype' => $markup['subtype'] ?? null,
        'struct_parent' => $markup['struct_parent'] ?? null,
        'struct_object' => $markup['struct_object'] ?? null,
        'role' => $markup['role'] ?? null,
        'title' => $markup['title'] ?? null,
        'alternate_text' => $markup['alternate_text'] ?? null,
        'actual_text' => $markup['actual_text'] ?? null,
        'review_only' => $markup['review_only'] ?? null,
        'visible_text_source' => $markup['visible_text_source'] ?? null,
        'renders_markup_on_import' => $markup['renders_markup_on_import'] ?? null,
    ], $pageReview['text_markup_annotations'] ?? []),
]) . " -->\n";
