<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRemoteThreadActionStackPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover page remains visible) Tj ET';
    $threadText = 'BT /F1 12 Tf 72 720 Td (Thread destination page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /Threads [30 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 40 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 7 /Trans 17 0 R /AA << /O 18 0 R >> /Contents 41 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Remote Thread Stack) /Parent 5 0 R /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ArticleStart) [4 0 R /FitH 690]] >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F << /UF <FEFF00720065006D006F00740065002D00610072007400690063006C0065002E007000640066> /F (fallback-remote.pdf) >> /D (RemoteThread) /NewWindow true /Next [13 0 R 15 0 R 15 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D /ArticleStart /Next 14 0 R >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden remote thread stack script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/remote-thread-review) >>\nendobj\n"
        . "17 0 obj\n<< /S /Blinds /D .5 /Dm /H >>\nendobj\n"
        . "18 0 obj\n<< /S /URI /URI (https://example.com/thread-page-open) >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Article ) /St 8 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Thread /F 31 0 R /I << /Title (Remote Stack Article Thread) >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /Bead /T 30 0 R /P 4 0 R /R [60 682 260 730] /N 32 0 R /V 32 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /Bead /T 30 0 R /P 4 0 R /R [280 682 540 730] /N 31 0 R /V 31 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "41 0 obj\n<< /Length " . strlen($threadText) . " >>\nstream\n{$threadText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'propagates local article-thread target context onto a remote-first outline action stack' => static function (TestRunner $t) use ($outlineRemoteThreadActionStackPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineRemoteThreadActionStackPdf();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline_actions', 'page_presentations', 'article_threads', 'page_review'], $metadata['source']);
        $t->same([], $metadata['outline']);
        $t->same([], $extractor->getPdfToc($pdf));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(4, count($actions), 'remote action, local fallback, and bounded /Next followups are all review rows.');
        $t->same(['GoToR', 'GoTo', 'JavaScript', 'URI'], array_column($actions, 'action_type'));
        $t->same(['remote-document-review', 'local-destination', 'blocked-javascript', 'review-uri'], array_column($actions, 'safety'));
        $t->same([12, 13, 14, 15], array_column($actions, 'action_object'));
        $t->same([null, true, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
        ]);
        $t->same([false, false, false, false], array_column($actions, 'executes_on_import'));

        $t->same('remote-article.pdf', $actions[0]['file']);
        $t->same('RemoteThread', $actions[0]['destination']);
        $t->same(null, $actions[0]['page']);
        $t->same(true, $actions[0]['new_window']);
        $t->same('ArticleStart', $actions[1]['destination']);
        $t->same(1, $actions[1]['page']);
        $t->same('Article 8', $actions[1]['page_label']);
        $t->same('https://example.com/remote-thread-review', $actions[3]['uri']);

        foreach ($actions as $action) {
            $t->same(1, $action['destination_action_target_page'] ?? null);
            $t->same('Article 8', $action['destination_action_target_page_label'] ?? null);
            $t->same('FitH', $action['destination_action_target_view_mode'] ?? null);
            $t->same(['top' => 690.0], $action['destination_action_target_view_parameters'] ?? null);
            $t->same(7.0, $action['destination_action_target_display_duration'] ?? null);
            $t->same('Blinds', $action['destination_action_target_page_transition']['style'] ?? null);
            $t->same(['Remote Stack Article Thread'], $action['destination_action_target_article_thread_titles'] ?? null);
            $t->same([31, 32], array_column($action['destination_action_target_article_beads'] ?? [], 'bead_object'));
            $t->same(['review-uri'], array_column($action['destination_action_target_page_actions'] ?? [], 'safety'));
        }
    },
    'keeps remote action stack operands out of local TOC and visible WordPress text' => static function (TestRunner $t) use ($outlineRemoteThreadActionStackPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineRemoteThreadActionStackPdf();
        $remoteActions = $extractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($remoteActions));
        $t->same('Remote Thread Stack', $remoteActions[0]['title']);
        $t->same('remote-article.pdf', $remoteActions[0]['file']);
        $t->same('RemoteThread', $remoteActions[0]['destination']);
        $t->same(null, $remoteActions[0]['page']);

        $t->contains('Cover page remains visible', $plainText);
        $t->contains('Thread destination page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'remote-article.pdf'));
        $t->true(!str_contains($plainText, 'RemoteThread'));
        $t->true(!str_contains($plainText, 'ArticleStart'));
        $t->true(!str_contains($plainText, 'remote-thread-review'));
        $t->true(!str_contains($plainText, 'hidden remote thread stack script'));
        $t->true(!str_contains($plainText, 'Remote Stack Article Thread'));
    },
];
