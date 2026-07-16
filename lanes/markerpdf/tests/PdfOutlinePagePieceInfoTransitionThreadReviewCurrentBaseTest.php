<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlinePagePieceInfoTransitionThreadReviewPdf = static function (): string {
    $sourcePayload = '<wp-export><post id="95"/></wp-export>';
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Intro navigation review text) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck review body text) Tj ET';
    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction /DeckTarget /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] /MarkInfo << /Marked true /UserProperties true /Suspects false >> /StructTreeRoot 40 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 31 0 R /Dur 6 /Trans 16 0 R /AA << /O 15 0 R >> /PieceInfo << /WPImport << /LastModified (D:20260602165300Z) /Private << /ReviewState (needs-page-review) /OutlineLinked true /Priority 4 >> >> >> /AF [10 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Deck Review Target) /Parent 5 0 R /Dest /DeckTarget /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Deck Review Action) /Parent 5 0 R /A 18 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(DeckTarget) [4 0 R /FitH 640]] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (deck-source.xml) /Desc (Original migration source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /CreationDate (D:20260602165200Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/deck-open-review) >>\nendobj\n"
        . "16 0 obj\n<< /S /Dissolve /D 0.5 /B true >>\nendobj\n"
        . "18 0 obj\n<< /S /GoTo /D /DeckTarget /Next 19 0 R >>\nendobj\n"
        . "19 0 obj\n<< /S /URI /URI (javascript:alert\\(95\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Deck Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [70 700 340 742] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Deck ) /St 95 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /StructTreeRoot /K 41 0 R >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /Sect /T (Review section) /Pg 4 0 R /A 42 0 R /K << /Type /MCR /Pg 4 0 R /MCID 0 >> >>\nendobj\n"
        . "42 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/group) /F (Grouped deck section) >> << /N (Needs Manual Review) /V true /H true >>] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'attaches target page PieceInfo review metadata to outline navigation targets with transitions and article beads' => static function (TestRunner $t) use ($outlinePagePieceInfoTransitionThreadReviewPdf): void {
        $pdf = $outlinePagePieceInfoTransitionThreadReviewPdf();
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'outline_actions', 'open_action', 'page_presentations', 'article_threads', 'page_review'], $metadata['source']);
        $t->same(1, count($metadata['page_review']));
        $t->same(1, $metadata['page_review'][0]['pnum']);
        $t->same(4, $metadata['page_review'][0]['page_object']);

        $outline = $metadata['outline'][0];
        $t->same('Deck Review Target', $outline['title']);
        $t->same(1, $outline['page']);
        $t->same('Deck 95', $outline['page_label']);
        $t->same('DeckTarget', $outline['destination']);
        $t->same('FitH', $outline['view_mode']);
        $t->same(['top' => 640.0], $outline['view_parameters']);
        $t->same(6.0, $outline['target_display_duration']);
        $t->same('Dissolve', $outline['target_page_transition']['style']);
        $t->same(0.5, $outline['target_page_transition']['duration']);
        $t->same(['page_open'], array_column($outline['target_page_actions'], 'event_label'));
        $t->same(['Deck Article Thread'], $outline['target_article_thread_titles']);
        $t->same([21], array_column($outline['target_article_beads'], 'bead_object'));

        $pageReview = $outline['target_page_review'];
        $t->same(1, $pageReview['pnum']);
        $t->same(4, $pageReview['page_object']);
        $t->same('D:20260602165300Z', $pageReview['piece_info']['WPImport']['last_modified']);
        $t->same('needs-page-review', $pageReview['piece_info']['WPImport']['private']['ReviewState']);
        $t->same(true, $pageReview['piece_info']['WPImport']['private']['OutlineLinked']);
        $t->same(4, $pageReview['piece_info']['WPImport']['private']['Priority']);
        $t->same(['Source'], array_column($pageReview['page_associated_files'], 'relationship'));
        $t->same('deck-source.xml', $pageReview['page_associated_files'][0]['filename']);
        $t->same(true, $pageReview['page_associated_files'][0]['checksum_matches']);
        $t->same(['WP Block', 'Needs Manual Review'], array_column($pageReview['user_properties'], 'name'));
        $t->same('core/group', $pageReview['user_properties'][0]['value']);
        $t->same(true, $pageReview['user_properties'][1]['hidden']);

        $actionRows = $metadata['outline_action_review_actions'];
        $t->same(['GoTo', 'URI'], array_column($actionRows, 'action_type'));
        $t->same(['local-destination', 'blocked-unsafe-uri'], array_column($actionRows, 'safety'));
        $t->same('needs-page-review', $actionRows[0]['target_page_review']['piece_info']['WPImport']['private']['ReviewState']);
        $t->same(['Deck Article Thread'], $actionRows[0]['target_article_thread_titles']);

        $openAction = $metadata['open_action_review_actions'][0];
        $t->same('GoTo', $openAction['action_type']);
        $t->same('DeckTarget', $openAction['destination']);
        $t->same('Deck 95', $openAction['page_label']);
        $t->same('Dissolve', $openAction['target_page_transition']['style']);
        $t->same('needs-page-review', $openAction['target_page_review']['piece_info']['WPImport']['private']['ReviewState']);

        $openDestination = $metadata['open_action_destination'];
        $t->same('DeckTarget', $openDestination['destination']);
        $t->same('Deck 95', $openDestination['page_label']);
        $t->same('deck-source.xml', $openDestination['target_page_review']['page_associated_files'][0]['filename']);
        $t->same([21], array_column($openDestination['target_article_beads'], 'bead_object'));
    },
    'keeps target page PieceInfo attachments actions and article thread dictionaries out of visible WordPress text' => static function (TestRunner $t) use ($outlinePagePieceInfoTransitionThreadReviewPdf): void {
        $pdf = $outlinePagePieceInfoTransitionThreadReviewPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfToc($pdf);

        $t->contains('Intro navigation review text', $plainText);
        $t->contains('Deck review body text', $plainText);
        $t->true(!str_contains($plainText, 'wp-export'));
        $t->true(!str_contains($plainText, 'needs-page-review'));
        $t->true(!str_contains($plainText, 'Deck Article Thread'));
        $t->true(!str_contains($plainText, 'javascript:alert'));
        $t->true(!str_contains($plainText, 'deck-open-review'));
        $t->same([
            ['title' => 'Deck Review Target', 'level' => 1, 'page' => 1, 'destination' => 'DeckTarget'],
            ['title' => 'Deck Review Action', 'level' => 1, 'page' => 1, 'destination' => 'DeckTarget'],
        ], $toc);
    },
];
