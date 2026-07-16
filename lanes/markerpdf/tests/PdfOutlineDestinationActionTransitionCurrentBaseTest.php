<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationActionTransitionPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Cover destination page stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck destination action page stays visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 7 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Named Destination Action) /Parent 5 0 R /Dest /DeckAction /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Direct Destination Action) /Parent 5 0 R /Dest 18 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(DeckAction) 9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D [4 0 R /FitH 620] /Next [13 0 R 14 0 R 14 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/deck-followup) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden named destination script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-review) >>\nendobj\n"
        . "16 0 obj\n<< /S /Wipe /D .4 /Di 180 >>\nendobj\n"
        . "18 0 obj\n<< /S /GoTo /D /DeckAction /Next 19 0 R >>\nendobj\n"
        . "19 0 obj\n<< /S /URI /URI (javascript:alert\\(2\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 4 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'reviews outline destination action dictionaries with target page transitions' => static function (TestRunner $t) use ($outlineDestinationActionTransitionPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineDestinationActionTransitionPdf();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'outline_actions', 'page_presentations'], $metadata['source']);
        $t->same(['Named Destination Action', 'Direct Destination Action'], array_column($metadata['outline'], 'title'));
        $t->same(['DeckAction', 'DeckAction'], array_column($metadata['outline'], 'destination'));
        $t->same(['Deck 4', 'Deck 4'], array_column($metadata['outline'], 'page_label'));

        foreach ($metadata['outline'] as $outline) {
            $t->same(1, $outline['page']);
            $t->same('FitH', $outline['view_mode']);
            $t->same(['top' => 620.0], $outline['view_parameters']);
            $t->same(7.0, $outline['target_display_duration']);
            $t->same('Wipe', $outline['target_page_transition']['style']);
            $t->same(0.4, $outline['target_page_transition']['duration']);
            $t->same(180.0, $outline['target_page_transition']['direction']);
            $t->same(['page_open'], array_column($outline['target_page_actions'], 'event_label'));
            $t->same(['review-uri'], array_column($outline['target_page_actions'], 'safety'));
        }

        $actions = $metadata['outline_action_review_actions'];
        $t->same(5, count($actions), 'destination action dictionaries expose GoTo rows plus bounded /Next review rows.');
        $t->same(
            ['Named Destination Action', 'Named Destination Action', 'Named Destination Action', 'Direct Destination Action', 'Direct Destination Action'],
            array_column($actions, 'outline_title')
        );
        $t->same(['GoTo', 'URI', 'JavaScript', 'GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript', 'local-destination', 'blocked-unsafe-uri'], array_column($actions, 'safety'));
        $t->same([9, 13, 14, 18, 19], array_column($actions, 'action_object'));
        $t->same([null, true, true, null, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
            $actions[4]['chained'] ?? null,
        ]);
        $t->same([false, false, false, false, false], array_column($actions, 'executes_on_import'));
        $t->same('DeckAction', $actions[0]['destination']);
        $t->same('Deck 4', $actions[0]['page_label']);
        $t->same('Wipe', $actions[0]['target_page_transition']['style']);
        $t->same(7.0, $actions[0]['target_display_duration']);
        $t->same(['review-uri'], array_column($actions[0]['target_page_actions'], 'safety'));
        $t->same('https://example.com/deck-followup', $actions[1]['uri']);
        $t->same('DeckAction', $actions[3]['destination']);
        $t->same(false, $actions[4]['is_safe_uri']);
    },
    'keeps outline destination action operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineDestinationActionTransitionPdf): void {
        $pdf = $outlineDestinationActionTransitionPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfToc($pdf);

        $t->contains('Cover destination page stays visible', $plainText);
        $t->contains('Deck destination action page stays visible', $plainText);
        $t->true(!str_contains($plainText, 'https://example.com/deck-followup'));
        $t->true(!str_contains($plainText, 'hidden named destination script'));
        $t->true(!str_contains($plainText, 'javascript:alert'));
        $t->same([
            ['title' => 'Named Destination Action', 'level' => 1, 'page' => 1, 'destination' => 'DeckAction'],
            ['title' => 'Direct Destination Action', 'level' => 1, 'page' => 1, 'destination' => null],
        ], $toc);
    },
];
