<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNamedDestinationActionThreadReviewPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Intro article page remains visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Threaded action target text remains visible) Tj ET';
    $reviewPayload = '<wp-outline-review action="ArticleAction"/>';
    $reviewChecksum = strtoupper(hash('md5', $reviewPayload));

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /PieceInfo << /WPThread << /LastModified (D:20260602173100Z) /Private << /ReviewState (action-thread-review) /NeedsReview true >> >> >> /AF [12 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Threaded Named Action) /Parent 5 0 R /Dest /ArticleAction >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ArticleAction) 9 0 R (ArticleStory) [4 0 R /FitH 690]] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /ArticleStory /Next [10 0 R 11 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/thread-review) >>\nendobj\n"
        . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden thread named action script'\\)) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (article-review.xml) /Desc (Article action review source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Named Action Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [58 690 280 732] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 690 540 732] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 5 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'reviews named destination action chains with article thread target context' => static function (TestRunner $t) use ($outlineNamedDestinationActionThreadReviewPdf): void {
        $pdf = $outlineNamedDestinationActionThreadReviewPdf();
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'outline_actions', 'article_threads', 'page_review'], $metadata['source']);
        $t->same([['title' => 'Threaded Named Action', 'level' => 1, 'page' => 1, 'destination' => 'ArticleAction']], (new PdfOutlineExtractor())->getPdfToc($pdf));
        $t->same('Threaded Named Action', $metadata['outline'][0]['title']);
        $t->same('ArticleAction', $metadata['outline'][0]['destination']);
        $t->same('Article 5', $metadata['outline'][0]['page_label']);
        $t->same(['Named Action Article Thread'], $metadata['outline'][0]['target_article_thread_titles']);
        $t->same([21, 22], array_column($metadata['outline'][0]['target_article_beads'], 'bead_object'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['GoTo', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same([9, 10, 11], array_column($actions, 'action_object'));
        $t->same(['ArticleAction', 'ArticleAction', 'ArticleAction'], array_column($actions, 'destination_action_name'));
        $t->same(['ArticleStory', null, null], array_column($actions, 'destination'));
        $t->same([1, 1, 1], array_column($actions, 'destination_action_target_page'));
        $t->same(['Article 5', 'Article 5', 'Article 5'], array_column($actions, 'destination_action_target_page_label'));
        $t->same([21, 22], array_column($actions[0]['target_article_beads'], 'bead_object'));
        $t->same([21, 22], array_column($actions[0]['destination_action_target_article_beads'], 'bead_object'));
        $t->same([21, 22], array_column($actions[1]['destination_action_target_article_beads'], 'bead_object'));
        $t->same([21, 22], array_column($actions[2]['destination_action_target_article_beads'], 'bead_object'));
        $t->same(['Named Action Article Thread'], $actions[1]['destination_action_target_article_thread_titles']);
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);
        $t->same([false, false, false], array_column($actions, 'executes_on_import'));
    },
    'propagates named destination action target page-review context to chained rows' => static function (TestRunner $t) use ($outlineNamedDestinationActionThreadReviewPdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineNamedDestinationActionThreadReviewPdf());
        $actions = $metadata['outline_action_review_actions'];

        $gotoReview = $actions[0]['target_page_review'] ?? [];
        $t->same('action-thread-review', $gotoReview['piece_info']['WPThread']['private']['ReviewState'] ?? null);
        $t->same('article-review.xml', $gotoReview['page_associated_files'][0]['filename'] ?? null);
        $t->same(true, $gotoReview['page_associated_files'][0]['checksum_matches'] ?? null);

        $uriTargetReview = $actions[1]['destination_action_target_page_review'] ?? [];
        $jsTargetReview = $actions[2]['destination_action_target_page_review'] ?? [];
        $t->same('action-thread-review', $uriTargetReview['piece_info']['WPThread']['private']['ReviewState'] ?? null);
        $t->same('article-review.xml', $uriTargetReview['page_associated_files'][0]['filename'] ?? null);
        $t->same(true, $uriTargetReview['page_associated_files'][0]['checksum_matches'] ?? null);
        $t->same('action-thread-review', $jsTargetReview['piece_info']['WPThread']['private']['ReviewState'] ?? null);
        $t->same([21, 22], array_column($actions[2]['destination_action_target_article_beads'] ?? [], 'bead_object'));
    },
    'keeps named destination action thread operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineNamedDestinationActionThreadReviewPdf): void {
        $pdf = $outlineNamedDestinationActionThreadReviewPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Intro article page remains visible', $plainText);
        $t->contains('Threaded action target text remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Threaded Named Action'));
        $t->true(!str_contains($plainText, 'ArticleAction'));
        $t->true(!str_contains($plainText, 'ArticleStory'));
        $t->true(!str_contains($plainText, 'thread-review'));
        $t->true(!str_contains($plainText, 'hidden thread named action script'));
        $t->true(!str_contains($plainText, 'Named Action Article Thread'));
        $t->true(!str_contains($plainText, 'wp-outline-review'));
        $t->true(!str_contains($plainText, 'action-thread-review'));
    },
];
