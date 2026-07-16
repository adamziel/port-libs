<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineLaunchThreadTransitionContextPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Intro launch review page remains visible) Tj ET';
    $threadContent = 'BT /F1 12 Tf 72 720 Td (Launch thread target page remains visible) Tj ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 8 /Trans 16 0 R /AA << /O 17 0 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Launch Before Article Target) /Parent 5 0 R /A 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ArticleThreadTarget) [4 0 R /XYZ 144 680 1.25]] >>\nendobj\n"
        . "9 0 obj\n<< /S /Launch /F (post-import-helper.exe) /Win << /F (post-import-helper.exe) /O (open) /P (/review-only) >> /NewWindow false /Next [10 0 R 11 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /GoTo /D /ArticleThreadTarget >>\nendobj\n"
        . "11 0 obj\n<< /S /URI /URI (https://example.com/thread-launch-review) >>\nendobj\n"
        . "16 0 obj\n<< /S /Fly /D .6 /M /I /Di 270 /SS .75 /B false >>\nendobj\n"
        . "17 0 obj\n<< /S /URI /URI (https://example.com/page-open-launch-review) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Launch Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [66 680 300 742] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [310 680 540 742] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 18 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($threadContent) . " >>\nstream\n{$threadContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'propagates launch action target thread and transition context from chained local destinations' => static function (TestRunner $t) use ($outlineLaunchThreadTransitionContextPdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineLaunchThreadTransitionContextPdf());

        $t->same(['outline_actions', 'page_presentations', 'article_threads', 'page_review'], $metadata['source']);
        $t->same([], $metadata['outline']);
        $t->same(['Launch Article Thread'], array_column($metadata['article_threads'], 'title'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['Launch', 'GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same(['blocked-launch', 'local-destination', 'review-uri'], array_column($actions, 'safety'));
        $t->same([9, 10, 11], array_column($actions, 'action_object'));
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);
        $t->same(false, $actions[0]['new_window']);
        $t->same('post-import-helper.exe', $actions[0]['file']);
        $t->same('open', $actions[0]['operation']);

        foreach ($actions as $action) {
            $t->same(1, $action['destination_action_target_page']);
            $t->same('Article 18', $action['destination_action_target_page_label']);
            $t->same('XYZ', $action['destination_action_target_view_mode']);
            $t->same([144.0, 680.0, 1.25], $action['destination_action_target_view_position']);
            $t->same(['left' => 144.0, 'top' => 680.0, 'zoom' => 1.25], $action['destination_action_target_view_parameters']);
            $t->same(8.0, $action['destination_action_target_display_duration']);
            $t->same('Fly', $action['destination_action_target_page_transition']['style']);
            $t->same(0.6, $action['destination_action_target_page_transition']['duration']);
            $t->same('I', $action['destination_action_target_page_transition']['motion']);
            $t->same(270.0, $action['destination_action_target_page_transition']['direction']);
            $t->same(0.75, $action['destination_action_target_page_transition']['scale']);
            $t->same(false, $action['destination_action_target_page_transition']['opaque_background']);
            $t->same(['page_open'], array_column($action['destination_action_target_page_actions'], 'event_label'));
            $t->same(['review-uri'], array_column($action['destination_action_target_page_actions'], 'safety'));
            $t->same([21, 22], array_column($action['destination_action_target_article_beads'], 'bead_object'));
            $t->same(['Launch Article Thread'], $action['destination_action_target_article_thread_titles']);
            $t->same(false, $action['executes_on_import']);
        }

        $t->same(1, $actions[1]['page']);
        $t->same('Article 18', $actions[1]['page_label']);
        $t->same('ArticleThreadTarget', $actions[1]['destination']);
        $t->same('Fly', $actions[1]['target_page_transition']['style']);
        $t->same([21, 22], array_column($actions[1]['target_article_beads'], 'bead_object'));
        $t->same('https://example.com/thread-launch-review', $actions[2]['uri']);
    },
    'keeps launch action thread and transition operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineLaunchThreadTransitionContextPdf): void {
        $pdf = $outlineLaunchThreadTransitionContextPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Intro launch review page remains visible', $plainText);
        $t->contains('Launch thread target page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Launch Before Article Target'));
        $t->true(!str_contains($plainText, 'post-import-helper.exe'));
        $t->true(!str_contains($plainText, 'ArticleThreadTarget'));
        $t->true(!str_contains($plainText, 'thread-launch-review'));
        $t->true(!str_contains($plainText, 'page-open-launch-review'));
        $t->true(!str_contains($plainText, 'Launch Article Thread'));
    },
];
