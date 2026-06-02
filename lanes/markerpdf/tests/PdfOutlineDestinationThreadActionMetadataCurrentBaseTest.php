<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationThreadActionMetadataPdf = static function (): string {
    $introText = 'BT /F1 12 Tf 72 720 Td (Intro destination thread page remains visible) Tj ET';
    $articleText = 'BT /F1 12 Tf 72 720 Td (Destination thread action target remains visible) Tj ET';
    $reviewPayload = '<wp-outline-destination-thread action="direct-dest-thread"/>';
    $reviewChecksum = strtoupper(hash('md5', $reviewPayload));

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Threads [20 0 R] /PageLabels 25 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 7 /Trans 16 0 R /AA << /O 17 0 R >> /PieceInfo << /WPDestinationThread << /Private << /ReviewState (destination-thread-action-review) /NeedsReview true >> >> >> /AF [12 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Destination Thread Action Metadata) /Parent 5 0 R /Dest 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /S /Thread /D 0 /B 22 0 R /Next [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/destination-thread-review) >>\nendobj\n"
        . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden destination thread script'\\)) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (destination-thread-review.xml) /Desc (Destination thread action review source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /S /Push /D .65 /Di 180 >>\nendobj\n"
        . "17 0 obj\n<< /S /URI /URI (https://example.com/destination-thread-page-open) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Destination Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [64 680 280 735] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 680 548 735] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 9 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introText) . " >>\nstream\n{$introText}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($articleText) . " >>\nstream\n{$articleText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'normalizes outline destination Thread action target metadata on primary and chained rows' => static function (TestRunner $t) use ($outlineDestinationThreadActionMetadataPdf): void {
        $pdf = $outlineDestinationThreadActionMetadataPdf();
        $extractor = new PdfOutlineExtractor();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->true(in_array('outline_actions', $metadata['source'], true));
        $t->true(in_array('page_presentations', $metadata['source'], true));
        $t->true(in_array('article_threads', $metadata['source'], true));
        $t->true(in_array('page_review', $metadata['source'], true));
        $t->same([], $metadata['outline'], 'Thread action destinations remain review-only instead of upstream-style TOC rows.');
        $t->same([], $extractor->getPdfToc($pdf));
        $t->same(['Destination Article Thread'], array_column($metadata['article_threads'], 'title'));
        $t->same([21, 22], array_column($metadata['article_threads'][0]['beads'], 'bead_object'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['Thread', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['article-thread-review', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same([9, 10, 11], array_column($actions, 'action_object'));
        $t->same([false, false, false], array_column($actions, 'executes_on_import'));
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);

        $threadAction = $actions[0];
        $t->same('Destination Thread Action Metadata', $threadAction['outline_title']);
        $t->same('index', $threadAction['thread_destination_type']);
        $t->same('0', $threadAction['thread_destination']);
        $t->same(20, $threadAction['thread_object']);
        $t->same(0, $threadAction['thread_index']);
        $t->same('Destination Article Thread', $threadAction['thread_title']);
        $t->same(22, $threadAction['thread_bead_object']);
        $t->same(1, $threadAction['thread_bead_index']);
        $t->same([300.0, 680.0, 548.0, 735.0], $threadAction['thread_bead_rect']);
        $t->same(4, $threadAction['thread_page_object']);
        $t->same(1, $threadAction['page']);
        $t->same('Article 9', $threadAction['page_label']);
        $t->same('Push', $threadAction['target_page_transition']['style']);
        $t->same(0.65, $threadAction['target_page_transition']['duration']);

        foreach ($actions as $action) {
            $t->same(1, $action['destination_action_target_page']);
            $t->same(2, $action['destination_action_target_page_number']);
            $t->same(4, $action['destination_action_target_page_object']);
            $t->same('Article 9', $action['destination_action_target_page_label']);
            $t->same('index', $action['destination_action_target_thread_destination_type']);
            $t->same('0', $action['destination_action_target_thread_destination']);
            $t->same(4, $action['destination_action_target_thread_page_object']);
            $t->same(22, $action['destination_action_target_thread_bead_object']);
            $t->same(1, $action['destination_action_target_thread_bead_index']);
            $t->same([300.0, 680.0, 548.0, 735.0], $action['destination_action_target_thread_bead_rect']);
            $t->same('Destination Article Thread', $action['destination_action_target_article_thread_titles'][0] ?? null);
            $t->same([22], array_column($action['destination_action_target_article_beads'], 'bead_object'));
            $t->same('Push', $action['destination_action_target_page_transition']['style']);
            $t->same(['page_open'], array_column($action['destination_action_target_page_actions'], 'event_label'));
            $t->same('destination-thread-action-review', $action['destination_action_target_page_review']['piece_info']['WPDestinationThread']['private']['ReviewState'] ?? null);
            $t->same('destination-thread-review.xml', $action['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null);
            $t->same(true, $action['destination_action_target_page_review']['page_associated_files'][0]['checksum_matches'] ?? null);
        }

        $t->same('https://example.com/destination-thread-review', $actions[1]['uri']);
    },
    'keeps outline destination Thread action operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineDestinationThreadActionMetadataPdf): void {
        $pdf = $outlineDestinationThreadActionMetadataPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Intro destination thread page remains visible', $plainText);
        $t->contains('Destination thread action target remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Destination Thread Action Metadata'));
        $t->true(!str_contains($plainText, 'Destination Article Thread'));
        $t->true(!str_contains($plainText, 'destination-thread-review'));
        $t->true(!str_contains($plainText, 'destination-thread-page-open'));
        $t->true(!str_contains($plainText, 'hidden destination thread script'));
        $t->true(!str_contains($plainText, 'wp-outline-destination-thread'));
        $t->true(!str_contains($plainText, 'destination-thread-action-review'));
    },
];
