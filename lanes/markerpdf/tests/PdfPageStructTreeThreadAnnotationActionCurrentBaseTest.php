<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructTreeThreadAnnotationActionPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf '
        . '/ArticleTitle << /MCID 0 >> BDC 72 720 Td (Thread action title visible) Tj EMC '
        . '/ArticleBody << /MCID 1 >> BDC 72 684 Td (Thread action body visible) Tj EMC ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Target page visible) Tj ET';

    return "%PDF-2.0\n"
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
};

return [
    'preserves page StructTree thread annotation action context in page review rows' => static function (TestRunner $t) use ($pageStructTreeThreadAnnotationActionPdf): void {
        $pdf = $pageStructTreeThreadAnnotationActionPdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same('review-4', $page['page_label']);
        $t->same(48, $page['struct_parents']);
        $t->same(['Annotation action article thread'], $page['article_thread_titles']);
        $t->same([21], array_column($page['article_thread_beads'], 'bead_object'));
        $t->same([0, 1], array_column($page['structure_marked_content'], 'mcid'));
        $t->same(['H1', 'P'], array_column($page['structure_marked_content'], 'role'));

        $rows = $page['annotation_structure_parent_rows'];
        $t->same(2, count($rows));
        $t->same([6, 7], array_column($rows, 'annotation_object'));
        $t->same([17, 18], array_column($rows, 'struct_parent'));
        $t->same(['Link', 'Span'], array_map(
            static fn (array $row): ?string => $row['structure_parent']['role'] ?? null,
            $rows
        ));

        $root = $rows[0];
        $actions = is_array($root['actions'] ?? null) ? $root['actions'] : [];
        $t->same(['local-destination', 'review-uri'], array_column($actions, 'safety'));
        $t->same('target-page', $actions[0]['destination']);
        $t->same(1, $actions[0]['destination_page']);
        $t->same('target-9', $actions[0]['destination_page_label']);
        $t->same(9.0, $actions[0]['target_display_duration']);
        $t->same('Dissolve', $actions[0]['target_page_transition']['style']);
        $t->same(['page_open', 'page_close'], array_column($actions[0]['target_page_actions'], 'event_label'));
        $t->same(['review-uri', 'blocked-unsafe-uri'], array_column($actions[0]['target_page_actions'], 'safety'));
        $t->same(true, $root['target_page_action_context_review_only']);
        $t->same(true, $root['annotation_actions_review_only']);

        $thread = $root['reply_thread'];
        $t->same(6, $thread['root_annotation_object']);
        $t->same(1, $thread['reply_count']);
        $t->same([7], $thread['reply_annotation_objects']);
        $t->same(false, $thread['visible_text_source']);

        $reply = $rows[1];
        $replyThread = $reply['reply_thread'];
        $t->same(6, $replyThread['root_annotation_object']);
        $t->same(6, $replyThread['in_reply_to_object']);
        $t->same('Accepted', $replyThread['state']);
        $t->same('Review', $replyThread['state_model']);
        $t->same(false, $replyThread['visible_text_source']);

        $t->contains('Thread action title visible', $plainText);
        $t->contains('Thread action body visible', $plainText);
        $t->contains('Target page visible', $plainText);
        $t->same(false, str_contains($plainText, 'Root action note review only'));
        $t->same(false, str_contains($plainText, 'Reply action note review only'));
        $t->same(false, str_contains($plainText, 'Action annotation structure'));
        $t->same(false, str_contains($plainText, 'Reply annotation structure'));
        $t->same(false, str_contains($plainText, 'Annotation action article thread'));
        $t->same(false, str_contains($plainText, 'https://example.com/thread-followup'));
        $t->same(false, str_contains($plainText, 'https://example.com/target-open'));
        $t->same(false, str_contains($plainText, 'javascript:targetClose'));
    },
];
