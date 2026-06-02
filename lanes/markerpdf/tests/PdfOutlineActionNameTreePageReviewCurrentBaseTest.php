<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineActionNameTreePageReviewPdf = static function (): string {
    $introText = 'BT /F1 12 Tf 72 720 Td (Intro name tree action page remains visible) Tj ET';
    $articleText = 'BT /F1 12 Tf 72 720 Td (Name tree thread target page remains visible) Tj ET';
    $reviewPayload = '<wp-outline-nametree-review target="thread-action"/>';
    $reviewChecksum = strtoupper(hash('md5', $reviewPayload));

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 4 /Trans 16 0 R /AA << /O 17 0 R >> /PieceInfo << /WPThreadAction << /LastModified (D:20260602211200Z) /Private << /ReviewState (nametree-thread-page-review) /NeedsReview true /Batch 48 >> >> >> /AF [12 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (GoTo NameTree Thread Action) /Parent 5 0 R /A 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ThreadAction) 10 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /ThreadAction /Next 13 0 R >>\nendobj\n"
        . "10 0 obj\n<< /S /Thread /D (NameTree Article Thread) /B 22 0 R /Next 11 0 R >>\nendobj\n"
        . "11 0 obj\n<< /S /URI /URI (https://example.com/name-tree-thread-review) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (thread-action-review.xml) /Desc (Name tree thread action source) /AFRelationship /Source /EF << /F 14 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outer goto followup'\\)) >>\nendobj\n"
        . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /S /Split /D .35 /Dm /V /M /O >>\nendobj\n"
        . "17 0 obj\n<< /S /URI /URI (https://example.com/name-tree-page-open) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (NameTree Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [64 684 280 734] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [292 684 548 734] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 48 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introText) . " >>\nstream\n{$introText}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($articleText) . " >>\nstream\n{$articleText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'propagates outline GoTo name-tree Thread action target page review to review rows' => static function (TestRunner $t) use ($outlineActionNameTreePageReviewPdf): void {
        $pdf = $outlineActionNameTreePageReviewPdf();
        $extractor = new PdfOutlineExtractor();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline_actions', 'page_presentations', 'article_threads', 'page_review'], $metadata['source']);
        $t->same([], $metadata['outline'], 'Thread action targets stay out of the upstream-style PDF TOC.');
        $t->same([], $extractor->getPdfToc($pdf));
        $t->same(['NameTree Article Thread'], array_column($metadata['article_threads'], 'title'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions), 'The named Thread action, its /Next row, and the outer GoTo /Next row are review metadata.');
        $t->same(['Thread', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['article-thread-review', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same([10, 11, 13], array_column($actions, 'action_object'));
        $t->same(['ThreadAction', 'ThreadAction'], array_column(array_slice($actions, 0, 2), 'destination_action_name'));
        $t->same([false, false, false], array_column($actions, 'executes_on_import'));
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);

        $threadAction = $actions[0];
        $t->same('GoTo NameTree Thread Action', $threadAction['outline_title']);
        $t->same(20, $threadAction['thread_object']);
        $t->same(0, $threadAction['thread_index']);
        $t->same('NameTree Article Thread', $threadAction['thread_title']);
        $t->same(22, $threadAction['thread_bead_object']);
        $t->same(1, $threadAction['thread_bead_index']);
        $t->same([292.0, 684.0, 548.0, 734.0], $threadAction['thread_bead_rect']);
        $t->same(1, $threadAction['page']);
        $t->same('Article 48', $threadAction['page_label']);
        $t->same(4.0, $threadAction['target_display_duration']);
        $t->same('Split', $threadAction['target_page_transition']['style']);
        $t->same(['page_open'], array_column($threadAction['target_page_actions'], 'event_label'));
        $t->same([21, 22], array_column($threadAction['target_article_beads'], 'bead_object'));

        $targetReview = $threadAction['target_page_review'];
        $t->same('nametree-thread-page-review', $targetReview['piece_info']['WPThreadAction']['private']['ReviewState'] ?? null);
        $t->same(48, $targetReview['piece_info']['WPThreadAction']['private']['Batch'] ?? null);
        $t->same('thread-action-review.xml', $targetReview['page_associated_files'][0]['filename'] ?? null);
        $t->same(true, $targetReview['page_associated_files'][0]['checksum_matches'] ?? null);
    },
    'carries the name-tree Thread target context onto chained action rows' => static function (TestRunner $t) use ($outlineActionNameTreePageReviewPdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineActionNameTreePageReviewPdf());
        $actions = $metadata['outline_action_review_actions'];

        foreach (array_slice($actions, 1) as $chainedAction) {
            $t->same(1, $chainedAction['destination_action_target_page']);
            $t->same('Article 48', $chainedAction['destination_action_target_page_label']);
            $t->same(4.0, $chainedAction['destination_action_target_display_duration']);
            $t->same('Split', $chainedAction['destination_action_target_page_transition']['style']);
            $t->same(['NameTree Article Thread'], $chainedAction['destination_action_target_article_thread_titles']);
            $t->same([21, 22], array_column($chainedAction['destination_action_target_article_beads'], 'bead_object'));
            $t->same('nametree-thread-page-review', $chainedAction['destination_action_target_page_review']['piece_info']['WPThreadAction']['private']['ReviewState'] ?? null);
            $t->same('thread-action-review.xml', $chainedAction['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null);
        }

        $t->same('https://example.com/name-tree-thread-review', $actions[1]['uri']);
        $t->same('JavaScript', $actions[2]['action_type']);
    },
    'keeps outline GoTo name-tree Thread action operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineActionNameTreePageReviewPdf): void {
        $pdf = $outlineActionNameTreePageReviewPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Intro name tree action page remains visible', $plainText);
        $t->contains('Name tree thread target page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'GoTo NameTree Thread Action'));
        $t->true(!str_contains($plainText, 'ThreadAction'));
        $t->true(!str_contains($plainText, 'NameTree Article Thread'));
        $t->true(!str_contains($plainText, 'name-tree-thread-review'));
        $t->true(!str_contains($plainText, 'name-tree-page-open'));
        $t->true(!str_contains($plainText, 'hidden outer goto followup'));
        $t->true(!str_contains($plainText, 'wp-outline-nametree-review'));
        $t->true(!str_contains($plainText, 'nametree-thread-page-review'));
    },
];
