<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAnnotationThreadPdf = static function (): string {
    $pageStream = 'BT /F1 12 Tf 72 720 Td (Visible page text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n{$pageStream}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 676 280 724] /Contents (Root note review only) /T (Editor) /NM /root-note >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [92 636 300 668] /Contents (Accepted reply review only) /T (Reviewer A) /NM /reply-accepted /IRT 6 0 R /RT /R /State /Accepted /StateModel /Review /Popup 10 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [92 596 300 628] /Contents (Grouped reply review only) /T (Reviewer B) /NM /reply-group /IRT 6 0 R /RT /Group /State /Marked /StateModel /Marked >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [92 556 300 588] /Contents (Detached reply review only) /T (Reviewer C) /NM /reply-detached /IRT 90 0 R /RT /R /State /Rejected /StateModel /Review >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [320 620 500 690] /Parent 7 0 R /Open true /Contents (Reply popup review only) >>\nendobj\n"
        . "90 0 obj\n<< /Type /Annot /Subtype /Text /Rect [20 20 40 40] /Contents (Detached stale root must not promote) /T (Detached root) >>\nendobj\n"
        . "%%EOF";
};

return [
    'extracts page annotation reply thread state metadata without promoting detached targets' => static function (TestRunner $t) use ($pageAnnotationThreadPdf): void {
        $pages = (new PdfAnnotationExtractor())->extractPageAnnotations($pageAnnotationThreadPdf());

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same(4, count($page['annotations']), 'Popup reply annotations are nested instead of duplicated.');

        $t->same(1, count($page['annotation_threads']));
        $thread = $page['annotation_threads'][0];
        $t->same('page_annotation_reply_thread', $thread['source']);
        $t->same(6, $thread['root_annotation_object']);
        $t->same('Text', $thread['root_subtype']);
        $t->same('root-note', $thread['root_name']);
        $t->same('Editor', $thread['root_title']);
        $t->same(2, $thread['reply_count']);
        $t->same([7, 8], $thread['reply_annotation_objects']);
        $t->same(['reply', 'group'], $thread['reply_type_labels']);
        $t->same(['Accepted', 'Marked'], $thread['states']);
        $t->same(['Review', 'Marked'], $thread['state_models']);
        $t->same(true, $thread['current_page_thread']);
        $t->same(true, $thread['review_only']);
        $t->same(false, $thread['visible_text_source']);
        $t->same(false, $thread['executes_actions_on_import']);
        $t->same(false, $thread['renders_annotation_thread']);

        $root = $page['annotations'][0];
        $t->same('Text', $root['subtype']);
        $t->same(6, $root['annotation_object']);
        $t->same(6, $root['reply_thread']['root_annotation_object']);
        $t->same(2, $root['reply_thread']['reply_count']);
        $t->same([7, 8], $root['reply_thread']['reply_annotation_objects']);
        $t->same(false, array_key_exists('in_reply_to_object', $root['reply_thread']));

        $reply = $page['annotations'][1];
        $t->same(7, $reply['annotation_object']);
        $t->same('Accepted reply review only', $reply['contents']);
        $t->same(10, $reply['popup']['object']);
        $t->same('Reply popup review only', $reply['popup']['contents']);
        $t->same(6, $reply['reply_thread']['root_annotation_object']);
        $t->same(6, $reply['reply_thread']['in_reply_to_object']);
        $t->same(true, $reply['reply_thread']['in_reply_to_current_page']);
        $t->same(false, $reply['reply_thread']['detached_in_reply_to']);
        $t->same('R', $reply['reply_thread']['reply_type']);
        $t->same('reply', $reply['reply_thread']['reply_type_label']);
        $t->same('Accepted', $reply['reply_thread']['state']);
        $t->same('Review', $reply['reply_thread']['state_model']);
        $t->same(false, $reply['reply_thread']['visible_text_source']);

        $grouped = $page['annotations'][2];
        $t->same(8, $grouped['annotation_object']);
        $t->same('Group', $grouped['reply_thread']['reply_type']);
        $t->same('group', $grouped['reply_thread']['reply_type_label']);
        $t->same('Marked', $grouped['reply_thread']['state']);
        $t->same('Marked', $grouped['reply_thread']['state_model']);

        $detached = $page['annotations'][3];
        $t->same(9, $detached['annotation_object']);
        $t->same(90, $detached['reply_thread']['in_reply_to_object']);
        $t->same(false, $detached['reply_thread']['in_reply_to_current_page']);
        $t->same(true, $detached['reply_thread']['detached_in_reply_to']);
        $t->same(false, $detached['reply_thread']['current_page_thread']);
        $t->same(false, array_key_exists('root_annotation_object', $detached['reply_thread']));
        $t->same('Rejected', $detached['reply_thread']['state']);

        $t->same(1, count($page['detached_annotation_thread_replies']));
        $t->same(9, $page['detached_annotation_thread_replies'][0]['annotation_object']);
        $t->same(90, $page['detached_annotation_thread_replies'][0]['in_reply_to_object']);
    },
    'keeps annotation reply thread dictionaries out of visible WordPress text' => static function (TestRunner $t) use ($pageAnnotationThreadPdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($pageAnnotationThreadPdf());

        $t->contains('Visible page text', $plainText);
        $t->true(!str_contains($plainText, 'Root note review only'));
        $t->true(!str_contains($plainText, 'Accepted reply review only'));
        $t->true(!str_contains($plainText, 'Grouped reply review only'));
        $t->true(!str_contains($plainText, 'Detached reply review only'));
        $t->true(!str_contains($plainText, 'Reply popup review only'));
        $t->true(!str_contains($plainText, 'Detached stale root must not promote'));
        $t->true(!str_contains($plainText, 'Reviewer A'));
        $t->true(!str_contains($plainText, 'Reviewer B'));
        $t->true(!str_contains($plainText, 'Reviewer C'));
        $t->true(!str_contains($plainText, 'Accepted'));
        $t->true(!str_contains($plainText, 'Rejected'));
    },
];
