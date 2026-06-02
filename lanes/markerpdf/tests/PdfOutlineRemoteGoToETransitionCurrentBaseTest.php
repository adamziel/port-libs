<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRemoteGoToETransitionPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover page remains visible) Tj ET';
    $deckText = 'BT /F1 12 Tf 72 720 Td (Deck transition target remains visible) Tj ET';
    $attachmentPayload = 'BT /F1 12 Tf 72 720 Td (Embedded Appendix Payload Leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 7 /Trans 17 0 R /AA << /O 18 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Embedded Appendix Action) /Parent 5 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Named Embedded Appendix Action) /Parent 5 0 R /Dest /EmbeddedReview >>\nendobj\n"
        . "8 0 obj\n<< /Names [(DeckStart) [4 0 R /FitH 640] (EmbeddedReview) 13 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToE /F 21 0 R /D [2 /FitH 612] /NewWindow true /T << /R /C /N (review-pack.pdf) /P 0 /A 40 0 R >> /Next [14 0 R 15 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToE /D (named-appendix) /T 25 0 R /Next 16 0 R >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('embedded outline hidden script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /GoTo /D /DeckStart >>\nendobj\n"
        . "16 0 obj\n<< /S /URI /URI (https://example.com/embedded-outline-notes) >>\nendobj\n"
        . "17 0 obj\n<< /S /Push /D .6 /Dm /H /M /I /Di 0 /SS .75 /B false >>\nendobj\n"
        . "18 0 obj\n<< /S /URI /URI (https://example.com/deck-open-review) >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 3 >>] >>\nendobj\n"
        . "21 0 obj\n<< /Type /Filespec /F (fallback-pack.pdf) /UF <FEFF007200650076006900650077002D007000610063006B002E007000640066> /Desc (Embedded appendix packet) /AFRelationship /Data /EF << /F 22 0 R >> >>\nendobj\n"
        . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($attachmentPayload) . " >>\nstream\n{$attachmentPayload}\nendstream\nendobj\n"
        . "25 0 obj\n<< /R /C /N (named-review-pack.pdf) /P 3 /T << /R /P /N (root.pdf) >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($deckText) . " >>\nstream\n{$deckText}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 520 360 640] >>\nendobj\n"
        . "%%EOF";
};

return [
    'reviews outline GoToE actions without treating embedded destinations as local TOC rows' => static function (TestRunner $t) use ($outlineRemoteGoToETransitionPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineRemoteGoToETransitionPdf();
        $navigation = $extractor->getNavigationReviewMetadata($pdf);

        $t->same([], $extractor->getPdfToc($pdf), 'embedded go-to outline actions do not become same-document TOC rows.');
        $t->same(['outline_actions', 'page_presentations'], $navigation['source']);
        $t->same([], $navigation['outline']);

        $actions = $navigation['outline_action_review_actions'];
        $t->same(5, count($actions), 'direct and named GoToE action dictionaries plus bounded /Next rows are review-only.');
        $t->same(['GoToE', 'JavaScript', 'GoTo', 'GoToE', 'URI'], array_column($actions, 'action_type'));
        $t->same(['embedded-document-review', 'blocked-javascript', 'local-destination', 'embedded-document-review', 'review-uri'], array_column($actions, 'safety'));
        $t->same([false, false, false, false, false], array_column($actions, 'executes_on_import'));

        $embedded = $actions[0];
        $t->same('Embedded Appendix Action', $embedded['outline_title']);
        $t->same('review-pack.pdf', $embedded['file']);
        $t->same(21, $embedded['attachment']['file_spec_object']);
        $t->same('Embedded appendix packet', $embedded['attachment']['description']);
        $t->same('Data', $embedded['attachment']['relationship']);
        $t->same(true, $embedded['attachment']['has_embedded_file']);
        $t->same([22], $embedded['attachment']['embedded_file_objects']);
        $t->same(2, $embedded['destination_page']);
        $t->same('FitH', $embedded['view_mode']);
        $t->same([612.0], $embedded['view_position']);
        $t->same(['top' => 612.0], $embedded['view_parameters']);
        $t->same(true, $embedded['new_window']);
        $t->same(['relation' => 'C', 'relation_label' => 'child', 'name' => 'review-pack.pdf', 'page' => 0, 'annotation_object' => 40], $embedded['target']);
        $t->true(!array_key_exists('page_label', $embedded), 'embedded-document page indexes are not current-document page labels.');
        $t->true(!array_key_exists('target_page_transition', $embedded), 'embedded-document targets do not inherit current-page transitions.');

        $localFollowup = $actions[2];
        $t->same(true, $localFollowup['chained']);
        $t->same(1, $localFollowup['page']);
        $t->same('Deck 3', $localFollowup['page_label']);
        $t->same('DeckStart', $localFollowup['destination']);
        $t->same('Push', $localFollowup['target_page_transition']['style']);
        $t->same(7.0, $localFollowup['target_display_duration']);
        $t->same(['review-uri'], array_column($localFollowup['target_page_actions'], 'safety'));

        $namedEmbedded = $actions[3];
        $t->same('Named Embedded Appendix Action', $namedEmbedded['outline_title']);
        $t->same('EmbeddedReview', $namedEmbedded['destination_action_name']);
        $t->same('named-appendix', $namedEmbedded['destination']);
        $t->same(null, $namedEmbedded['file']);
        $t->same(['target_object' => 25, 'relation' => 'C', 'relation_label' => 'child', 'name' => 'named-review-pack.pdf', 'page' => 3, 'nested_target' => ['relation' => 'P', 'relation_label' => 'parent', 'name' => 'root.pdf']], $namedEmbedded['target']);
        $t->same('https://example.com/embedded-outline-notes', $actions[4]['uri']);
        $t->same([], $extractor->getRemoteGoToActions($pdf), 'GoToE is embedded-document review metadata, not a remote GoToR row.');
    },
    'keeps outline GoToE operands attachment payloads and transition actions out of visible text' => static function (TestRunner $t) use ($outlineRemoteGoToETransitionPdf): void {
        $pdf = $outlineRemoteGoToETransitionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $pages = (new PdfTextExtractor())->extractLabeledPageTexts($pdf);
        $presentations = (new PdfOutlineExtractor())->getPageTransitionActionMetadata($pdf);

        $t->same(['Cover 1', 'Deck 3'], array_column($pages, 'page_label'));
        $t->contains('Cover page remains visible', $plainText);
        $t->contains('Deck transition target remains visible', $plainText);
        $t->true(!str_contains($plainText, 'review-pack.pdf'));
        $t->true(!str_contains($plainText, 'named-review-pack.pdf'));
        $t->true(!str_contains($plainText, 'Embedded Appendix Payload Leak'));
        $t->true(!str_contains($plainText, 'embedded outline hidden script'));
        $t->true(!str_contains($plainText, 'embedded-outline-notes'));

        $t->same(1, count($presentations));
        $t->same('Deck 3', $presentations[0]['page_label']);
        $t->same('Push', $presentations[0]['transition']['style']);
        $t->same(['page_open'], array_column($presentations[0]['actions'], 'event_label'));
        $t->same([false], array_column($presentations[0]['actions'], 'executes_on_import'));
    },
];
