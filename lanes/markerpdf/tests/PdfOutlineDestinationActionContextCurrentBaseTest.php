<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationActionContextPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover action context page remains visible) Tj ET';
    $deckText = 'BT /F1 12 Tf 72 720 Td (Deck action target page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 4.5 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Destination Action Context) /Parent 5 0 R /Dest /DeckAction >>\nendobj\n"
        . "8 0 obj\n<< /Names [(DeckAction) 9 0 R (DeckView) [4 0 R /XYZ 120 640 0]] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /DeckView /Next [13 0 R 14 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/deck-context-review) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden action context script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-context) >>\nendobj\n"
        . "16 0 obj\n<< /S /Push /D .25 /M /I /Di 90 >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 8 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($deckText) . " >>\nstream\n{$deckText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'propagates destination action target view and presentation context to chained rows' => static function (TestRunner $t) use ($outlineDestinationActionContextPdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineDestinationActionContextPdf());

        $t->same(['outline', 'outline_actions', 'page_presentations'], $metadata['source']);
        $t->same(1, count($metadata['outline']));
        $t->same('Destination Action Context', $metadata['outline'][0]['title']);
        $t->same('DeckAction', $metadata['outline'][0]['destination']);
        $t->same('Deck 8', $metadata['outline'][0]['page_label']);
        $t->same('XYZ', $metadata['outline'][0]['view_mode']);
        $t->same(['left' => 120.0, 'top' => 640.0, 'zoom' => null], $metadata['outline'][0]['view_parameters']);
        $t->same('Push', $metadata['outline'][0]['target_page_transition']['style']);

        $actions = $metadata['outline_action_review_actions'];
        $t->same(3, count($actions));
        $t->same(['GoTo', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same(['DeckAction', 'DeckAction', 'DeckAction'], array_column($actions, 'destination_action_name'));
        $t->same(['DeckView', null, null], array_column($actions, 'destination'));
        $t->same([1, 1, 1], array_column($actions, 'destination_action_target_page'));
        $t->same(['Deck 8', 'Deck 8', 'Deck 8'], array_column($actions, 'destination_action_target_page_label'));

        foreach ($actions as $action) {
            $t->same('XYZ', $action['destination_action_target_view_mode']);
            $t->same([120.0, 640.0, null], $action['destination_action_target_view_position']);
            $t->same(['left' => 120.0, 'top' => 640.0, 'zoom' => null], $action['destination_action_target_view_parameters']);
            $t->same(4.5, $action['destination_action_target_display_duration']);
            $t->same('Push', $action['destination_action_target_page_transition']['style']);
            $t->same(0.25, $action['destination_action_target_page_transition']['duration']);
            $t->same('I', $action['destination_action_target_page_transition']['motion']);
            $t->same(90.0, $action['destination_action_target_page_transition']['direction']);
            $t->same(['page_open'], array_column($action['destination_action_target_page_actions'], 'event_label'));
            $t->same(['review-uri'], array_column($action['destination_action_target_page_actions'], 'safety'));
            $t->same([false], array_column($action['destination_action_target_page_actions'], 'executes_on_import'));
        }

        $t->same('Deck 8', $actions[0]['page_label']);
        $t->same('Push', $actions[0]['target_page_transition']['style']);
        $t->same('https://example.com/deck-context-review', $actions[1]['uri']);
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);
        $t->same([false, false, false], array_column($actions, 'executes_on_import'));
    },
    'keeps destination action context operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineDestinationActionContextPdf): void {
        $pdf = $outlineDestinationActionContextPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Cover action context page remains visible', $plainText);
        $t->contains('Deck action target page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'DeckAction'));
        $t->true(!str_contains($plainText, 'DeckView'));
        $t->true(!str_contains($plainText, 'deck-context-review'));
        $t->true(!str_contains($plainText, 'page-open-context'));
        $t->true(!str_contains($plainText, 'hidden action context script'));
    },
];
