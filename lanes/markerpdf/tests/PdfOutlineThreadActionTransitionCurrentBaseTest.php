<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineThreadActionTransitionPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Intro thread action page remains visible) Tj ET';
    $articleContent = 'BT /F1 12 Tf 72 720 Td (Thread action article target remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 9 /Trans 16 0 R /AA << /O 17 0 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Read Article Thread) /Parent 5 0 R /A 9 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Read Article Thread By Title) /Parent 5 0 R /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /S /Thread /D 20 0 R /B 22 0 R /Next [10 0 R 11 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/thread-action-review) >>\nendobj\n"
        . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden thread action script'\\)) >>\nendobj\n"
        . "12 0 obj\n<< /S /Thread /D (Feature Article Thread) /B 0 >>\nendobj\n"
        . "16 0 obj\n<< /S /Push /D .75 /Di 0 >>\nendobj\n"
        . "17 0 obj\n<< /S /URI /URI (https://example.com/thread-page-open) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Feature Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 690 270 735] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 690 540 735] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 42 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($articleContent) . " >>\nstream\n{$articleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'reviews outline Thread actions with selected bead transition context' => static function (TestRunner $t) use ($outlineThreadActionTransitionPdf): void {
        $pdf = $outlineThreadActionTransitionPdf();
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);

        $t->same(['outline_actions', 'page_presentations', 'article_threads', 'page_review'], $metadata['source']);
        $t->same([], $metadata['outline']);
        $t->same(['Feature Article Thread'], array_column($metadata['article_threads'], 'title'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(4, count($actions), 'Thread actions and bounded /Next rows should be review metadata.');
        $t->same(['Thread', 'URI', 'JavaScript', 'Thread'], array_column($actions, 'action_type'));
        $t->same(['article-thread-review', 'review-uri', 'blocked-javascript', 'article-thread-review'], array_column($actions, 'safety'));
        $t->same([9, 10, 11, 12], array_column($actions, 'action_object'));
        $t->same([false, false, false, false], array_column($actions, 'executes_on_import'));
        $t->same([null, true, true, null], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
        ]);

        $threadAction = $actions[0];
        $t->same(20, $threadAction['thread_object']);
        $t->same(0, $threadAction['thread_index']);
        $t->same('Feature Article Thread', $threadAction['thread_title']);
        $t->same(22, $threadAction['thread_bead_object']);
        $t->same(1, $threadAction['thread_bead_index']);
        $t->same([300.0, 690.0, 540.0, 735.0], $threadAction['thread_bead_rect']);
        $t->same(1, $threadAction['page']);
        $t->same('Article 42', $threadAction['page_label']);
        $t->same(9.0, $threadAction['target_display_duration']);
        $t->same('Push', $threadAction['target_page_transition']['style']);
        $t->same(0.75, $threadAction['target_page_transition']['duration']);
        $t->same(['page_open'], array_column($threadAction['target_page_actions'], 'event_label'));
        $t->same(['review-uri'], array_column($threadAction['target_page_actions'], 'safety'));
        $t->same([21, 22], array_column($threadAction['target_article_beads'], 'bead_object'));

        foreach (array_slice($actions, 1) as $chainedAction) {
            if (($chainedAction['chained'] ?? false) !== true) {
                continue;
            }

            $t->same(1, $chainedAction['destination_action_target_page']);
            $t->same('Article 42', $chainedAction['destination_action_target_page_label']);
            $t->same(9.0, $chainedAction['destination_action_target_display_duration']);
            $t->same('Push', $chainedAction['destination_action_target_page_transition']['style']);
            $t->same(['Feature Article Thread'], $chainedAction['destination_action_target_article_thread_titles']);
            $t->same([21, 22], array_column($chainedAction['destination_action_target_article_beads'], 'bead_object'));
        }

        $titleThreadAction = $actions[3];
        $t->same('title', $titleThreadAction['thread_destination_type']);
        $t->same('Feature Article Thread', $titleThreadAction['thread_destination']);
        $t->same(20, $titleThreadAction['thread_object']);
        $t->same(21, $titleThreadAction['thread_bead_object']);
        $t->same(0, $titleThreadAction['thread_bead_index']);
        $t->same([60.0, 690.0, 270.0, 735.0], $titleThreadAction['thread_bead_rect']);
        $t->same('Article 42', $titleThreadAction['page_label']);
        $t->same('Push', $titleThreadAction['target_page_transition']['style']);

        $t->same('https://example.com/thread-action-review', $actions[1]['uri']);
    },
    'keeps Thread action operands out of TOC rows and visible WordPress text' => static function (TestRunner $t) use ($outlineThreadActionTransitionPdf): void {
        $pdf = $outlineThreadActionTransitionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfToc($pdf);

        $t->same([], $toc);
        $t->contains('Intro thread action page remains visible', $plainText);
        $t->contains('Thread action article target remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Read Article Thread'));
        $t->true(!str_contains($plainText, 'Read Article Thread By Title'));
        $t->true(!str_contains($plainText, 'Feature Article Thread'));
        $t->true(!str_contains($plainText, 'thread-action-review'));
        $t->true(!str_contains($plainText, 'thread-page-open'));
        $t->true(!str_contains($plainText, 'hidden thread action script'));
    },
];
