<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineOpenActionThreadPieceInfoPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Intro page remains visible) Tj ET';
    $articleContent = 'BT /F1 12 Tf 72 720 Td (Open action article target remains visible) Tj ET';
    $reviewPayload = '<wp-openaction-review target="article"/>';
    $reviewChecksum = strtoupper(hash('md5', $reviewPayload));

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction /ArticleOpen /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /PieceInfo << /WPOpenAction << /LastModified (D:20260602175420Z) /Private << /ReviewState (openaction-thread-pieceinfo) /NeedsReview true /ImportBatch 33 >> >> >> /AF [12 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Article Outline Target) /Parent 5 0 R /Dest /ArticleTarget >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ArticleOpen) 9 0 R (ArticleTarget) [4 0 R /FitH 690]] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /ArticleTarget /Next [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/open-action-review) >>\nendobj\n"
        . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden open action script'\\)) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (open-action-review.xml) /Desc (OpenAction review source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (OpenAction Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [58 690 280 732] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 690 540 732] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 12 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($articleContent) . " >>\nstream\n{$articleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'propagates catalog OpenAction name-tree action target page review through chained rows' => static function (TestRunner $t) use ($outlineOpenActionThreadPieceInfoPdf): void {
        $pdf = $outlineOpenActionThreadPieceInfoPdf();
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'open_action', 'article_threads', 'page_review'], $metadata['source']);
        $t->same([['title' => 'Article Outline Target', 'level' => 1, 'page' => 1, 'destination' => 'ArticleTarget']], (new PdfOutlineExtractor())->getPdfToc($pdf));

        $outline = $metadata['outline'][0];
        $t->same('Article 12', $outline['page_label']);
        $t->same(['OpenAction Article Thread'], $outline['target_article_thread_titles']);
        $t->same([21, 22], array_column($outline['target_article_beads'], 'bead_object'));
        $t->same('openaction-thread-pieceinfo', $outline['target_page_review']['piece_info']['WPOpenAction']['private']['ReviewState'] ?? null);
        $t->same('open-action-review.xml', $outline['target_page_review']['page_associated_files'][0]['filename'] ?? null);

        $actions = $metadata['open_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['GoTo', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same([9, 10, 11], array_column($actions, 'action_object'));
        $t->same(['ArticleOpen', 'ArticleOpen', 'ArticleOpen'], array_column($actions, 'destination_action_name'));
        $t->same(['ArticleTarget', null, null], array_column($actions, 'destination'));
        $t->same([1, 1, 1], array_column($actions, 'destination_action_target_page'));
        $t->same(['Article 12', 'Article 12', 'Article 12'], array_column($actions, 'destination_action_target_page_label'));
        $t->same([21, 22], array_column($actions[0]['target_article_beads'], 'bead_object'));
        $t->same([21, 22], array_column($actions[1]['destination_action_target_article_beads'], 'bead_object'));
        $t->same(['OpenAction Article Thread'], $actions[2]['destination_action_target_article_thread_titles']);
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);
        $t->same([false, false, false], array_column($actions, 'executes_on_import'));

        $uriTargetReview = $actions[1]['destination_action_target_page_review'] ?? [];
        $javascriptTargetReview = $actions[2]['destination_action_target_page_review'] ?? [];
        $t->same('openaction-thread-pieceinfo', $uriTargetReview['piece_info']['WPOpenAction']['private']['ReviewState'] ?? null);
        $t->same(33, $uriTargetReview['piece_info']['WPOpenAction']['private']['ImportBatch'] ?? null);
        $t->same(true, $uriTargetReview['page_associated_files'][0]['checksum_matches'] ?? null);
        $t->same('open-action-review.xml', $javascriptTargetReview['page_associated_files'][0]['filename'] ?? null);

        $openDestination = $metadata['open_action_destination'];
        $t->same('ArticleOpen', $openDestination['destination']);
        $t->same('Article 12', $openDestination['page_label']);
        $t->same('openaction-thread-pieceinfo', $openDestination['target_page_review']['piece_info']['WPOpenAction']['private']['ReviewState'] ?? null);
        $t->same([21, 22], array_column($openDestination['target_article_beads'], 'bead_object'));
    },
    'keeps OpenAction action chains page PieceInfo and article thread dictionaries out of visible text' => static function (TestRunner $t) use ($outlineOpenActionThreadPieceInfoPdf): void {
        $pdf = $outlineOpenActionThreadPieceInfoPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Intro page remains visible', $plainText);
        $t->contains('Open action article target remains visible', $plainText);
        $t->true(!str_contains($plainText, 'ArticleOpen'));
        $t->true(!str_contains($plainText, 'ArticleTarget'));
        $t->true(!str_contains($plainText, 'open-action-review'));
        $t->true(!str_contains($plainText, 'hidden open action script'));
        $t->true(!str_contains($plainText, 'OpenAction Article Thread'));
        $t->true(!str_contains($plainText, 'openaction-thread-pieceinfo'));
    },
];
