<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructParentsThreadMarkupPdf = static function (): string {
    $content = 'BT /F1 12 Tf '
        . '/Headline << /MCID 0 >> BDC 72 720 Td (Markup title visible) Tj EMC '
        . '/Body << /MCID 1 >> BDC 72 684 Td (Markup body visible) Tj EMC ET';

    return "%PDF-2.0\n"
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
};

return [
    'composes page StructParents article threads and structured text-markup annotation review' => static function (TestRunner $t) use ($pageStructParentsThreadMarkupPdf): void {
        $pdf = $pageStructParentsThreadMarkupPdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same(0, $page['pnum']);
        $t->same(1, $page['page_number']);
        $t->same(3, $page['page_object']);
        $t->same('markup-21', $page['page_label']);
        $t->same(5, $page['struct_parents']);
        $t->same(5, $page['parent_tree']['key']);
        $t->same([0, 1], $page['parent_tree']['mcids']);
        $t->same(['Headline', 'Body'], array_column($page['parent_tree']['entries'], 'raw_role'));
        $t->same(['H1', 'P'], array_column($page['parent_tree']['entries'], 'role'));

        $t->same(['Markup Article Thread'], $page['article_thread_titles']);
        $t->same([21], array_column($page['article_thread_beads'], 'bead_object'));
        $t->same(['markup-21'], array_column($page['article_thread_beads'], 'page_label'));

        $mcrRows = $page['structure_marked_content'];
        $t->same([0, 1], array_column($mcrRows, 'mcid'));
        $t->same(['H1', 'P'], array_column($mcrRows, 'role'));
        $t->same(['markup-21', 'markup-21'], array_column($mcrRows, 'page_label'));

        $markups = $page['text_markup_annotations'];
        $t->same(1, count($markups));
        $markup = $markups[0];
        $t->same('page_text_markup_annotation', $markup['source']);
        $t->same(7, $markup['annotation_object']);
        $t->same('Highlight', $markup['subtype']);
        $t->same(12, $markup['struct_parent']);
        $t->same('Private markup comment', $markup['contents']);
        $t->same('Import Editor', $markup['author']);
        $t->same('Editorial highlight', $markup['subject']);
        $t->same('markup-annotation-1', $markup['name']);
        $t->same([72.0, 700.0, 260.0, 720.0], $markup['rect']);
        $t->same([[72.0, 700.0, 260.0, 720.0]], $markup['quad_rects']);
        $t->same('markup-21', $markup['page_label']);
        $t->same(true, $markup['review_only']);
        $t->same(false, $markup['visible_text_source']);
        $t->same(false, $markup['renders_markup_on_import']);
        $t->same(false, $markup['executes_actions_on_import']);

        $t->same('struct_tree_parent_tree_object', $markup['parent_tree']['source']);
        $t->same(12, $markup['parent_tree']['key']);
        $t->same(44, $markup['struct_object']);
        $t->same('ReviewMarkup', $markup['raw_role']);
        $t->same('Span', $markup['role']);
        $t->same(true, $markup['role_mapped']);
        $t->same('Markup annotation structure', $markup['title']);
        $t->same('Accessible markup note', $markup['alternate_text']);
        $t->same('Actual markup review', $markup['actual_text']);
        $t->same('markup-struct-12', $markup['id']);
        $t->same(['editorial', 'highlight'], $markup['classes']);

        $t->same(['Markup title visible', 'Markup body visible'], $textExtractor->extractTextLines($pdf));
        $t->contains('Markup title visible', $plainText);
        $t->contains('Markup body visible', $plainText);
        $t->same(false, str_contains($plainText, 'Private markup comment'));
        $t->same(false, str_contains($plainText, 'Markup Article Thread'));
        $t->same(false, str_contains($plainText, 'Markup annotation structure'));
        $t->same(false, str_contains($plainText, 'Accessible markup note'));
        $t->same(false, str_contains($plainText, 'Actual markup review'));
        $t->same(false, str_contains($plainText, 'markup-struct-12'));
        $t->same(false, str_contains($plainText, 'markup-21'));
    },
];
