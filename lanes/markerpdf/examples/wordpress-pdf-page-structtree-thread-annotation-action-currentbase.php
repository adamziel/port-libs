<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf '
    . '/ArticleTitle << /MCID 0 >> BDC 72 720 Td (Thread action title visible) Tj EMC '
    . '/ArticleBody << /MCID 1 >> BDC 72 684 Td (Thread action body visible) Tj EMC ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Target page visible) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (review-) /S /D /St 4 >> 1 << /P (target-) /S /D /St 9 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 48 /Contents 30 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 9 /Trans 15 0 R /AA << /O 16 0 R /C << /S /URI /URI (javascript:targetClose\\(\\)) >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 17 /Rect [72 670 310 730] /Contents (Root action note review only) /T (Root QA) /NM (root-action-link) /A << /S /GoTo /D (target-page) /Next << /S /URI /URI (https://example.com/thread-followup) >> >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 18 /Rect [90 630 320 660] /Contents (Reply action note review only) /T (Reply QA) /NM (reply-action-note) /IRT 6 0 R /RT /R /State /Accepted /StateModel /Review >>\nendobj\n"
    . "13 0 obj\n<< /Names [(target-page) 14 0 R] >>\nendobj\n"
    . "14 0 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "15 0 obj\n<< /S /Dissolve /D 0.75 >>\nendobj\n"
    . "16 0 obj\n<< /S /URI /URI (https://example.com/target-open) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Annotation action article thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 660 340 740] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ArticleTitle /H1 /ArticleBody /P /ReviewLink /Link /ReviewReply /Span >> /ParentTree 41 0 R /K [42 0 R 43 0 R 44 0 R 45 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Nums [48 [42 0 R 43 0 R] 17 44 0 R 18 45 0 R] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /ArticleTitle /Pg 3 0 R /T (Thread action heading structure) /K 0 >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /ArticleBody /Pg 3 0 R /T (Thread action body structure) /K 1 >>\nendobj\n"
    . "44 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (Action annotation structure) /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
    . "45 0 obj\n<< /Type /StructElem /S /ReviewReply /Pg 3 0 R /T (Reply annotation structure) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$page = $pageReviews[0] ?? [];
$annotationRows = is_array($page['annotation_structure_parent_rows'] ?? null)
    ? $page['annotation_structure_parent_rows']
    : [];
$rootRow = $annotationRows[0] ?? [];
$rootActions = is_array($rootRow['actions'] ?? null) ? $rootRow['actions'] : [];
$targetActions = is_array($rootActions[0]['target_page_actions'] ?? null) ? $rootActions[0]['target_page_actions'] : [];
$replyThread = is_array($rootRow['reply_thread'] ?? null) ? $rootRow['reply_thread'] : [];

if (($page['struct_parents'] ?? null) !== 48 || ($page['article_thread_titles'] ?? []) !== ['Annotation action article thread']) {
    throw new RuntimeException('Expected page StructTree and catalog thread context.');
}
if (count($annotationRows) !== 2 || array_column($annotationRows, 'struct_parent') !== [17, 18]) {
    throw new RuntimeException('Expected two annotation StructParent review rows.');
}
if (($rootActions[0]['destination_page_label'] ?? null) !== 'target-9'
    || ($rootActions[0]['target_page_transition']['style'] ?? null) !== 'Dissolve'
    || array_column($targetActions, 'event_label') !== ['page_open', 'page_close']
) {
    throw new RuntimeException('Expected annotation action target page transition/action context.');
}
if (($replyThread['reply_annotation_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected annotation reply-thread context on the root StructParent row.');
}
if (str_contains($plainText, 'Root action note review only')
    || str_contains($plainText, 'Reply action note review only')
    || str_contains($plainText, 'Action annotation structure')
    || str_contains($plainText, 'Reply annotation structure')
    || str_contains($plainText, 'https://example.com/thread-followup')
    || str_contains($plainText, 'https://example.com/target-open')
    || str_contains($plainText, 'javascript:targetClose')
) {
    throw new RuntimeException('Expected annotation, action, and structure metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structtree-thread-annotation-action-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-review-parser',
    'native_boundary' => 'page StructTree, catalog Threads, annotation StructParent, reply-thread, and target page-action metadata stay review-only before WordPress rendering',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'renders_annotations' => false,
    'page_label' => $page['page_label'] ?? null,
    'struct_parents' => $page['struct_parents'] ?? null,
    'article_thread_titles' => $page['article_thread_titles'] ?? [],
    'annotation_struct_parents' => array_column($annotationRows, 'struct_parent'),
    'root_action_safety' => array_column($rootActions, 'safety'),
    'target_page_label' => $rootActions[0]['destination_page_label'] ?? null,
    'target_page_action_labels' => array_column($targetActions, 'event_label'),
    'reply_thread_objects' => $replyThread['reply_annotation_objects'] ?? [],
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Root action note review only')
        && !str_contains($plainText, 'Action annotation structure')
        && !str_contains($plainText, 'https://example.com/thread-followup')
        && !str_contains($plainText, 'javascript:targetClose'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structtree-thread-annotation-action-review ' . $htmlJson([
    'page_review' => [
        'pnum' => $page['pnum'] ?? null,
        'page_object' => $page['page_object'] ?? null,
        'page_label' => $page['page_label'] ?? null,
        'article_thread_titles' => $page['article_thread_titles'] ?? [],
        'structure_roles' => array_column($page['structure_marked_content'] ?? [], 'role'),
        'annotation_structure_parent_rows' => array_map(static fn (array $row): array => [
            'annotation_object' => $row['annotation_object'] ?? null,
            'struct_parent' => $row['struct_parent'] ?? null,
            'role' => $row['structure_parent']['role'] ?? null,
            'action_safety' => array_column($row['actions'] ?? [], 'safety'),
            'reply_thread' => $row['reply_thread'] ?? [],
            'target_page_action_context_review_only' => $row['target_page_action_context_review_only'] ?? false,
        ], $annotationRows),
    ],
]) . " -->\n";
