<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStructureDestinationActionContextPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover structure action context page remains visible) Tj ET';
    $deckText = 'BT /F1 12 Tf 72 720 Td (Deck structure action target page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 4.25 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Structured Destination Action) /Parent 5 0 R /Dest /DeckAction /First 7 0 R /Last 7 0 R /Count -1 /C [0.8 0 0.2] /F 2 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Direct Child Destination) /Parent 6 0 R /Dest [4 0 R /FitH 610] /Count 0 /F 1 >>\nendobj\n"
        . "8 0 obj\n<< /Names [(DeckAction) 9 0 R (DeckView) [4 0 R /XYZ 120 640 0]] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /DeckView /Next [13 0 R 14 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/structured-action-context) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden structured action context script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/structured-page-open) >>\nendobj\n"
        . "16 0 obj\n<< /S /Push /D .25 /M /I /Di 90 >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 8 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($deckText) . " >>\nstream\n{$deckText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'adds destination action review context to structured outline rows' => static function (TestRunner $t) use ($outlineStructureDestinationActionContextPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineStructureDestinationActionContextPdf();
        $rows = $extractor->getOutlineStructureDestinationPageContext($pdf);

        $t->same(2, count($rows));
        $t->same(['Structured Destination Action', 'Direct Child Destination'], array_column($rows, 'title'));
        $t->same(['collapsed', 'leaf'], array_column($rows, 'structure_state'));
        $t->same('#cc0033', $rows[0]['text_color_hex']);
        $t->same(true, $rows[0]['is_bold']);
        $t->same('DeckAction', $rows[0]['destination']);
        $t->same('Deck 8', $rows[0]['page_label']);
        $t->same('XYZ', $rows[0]['view_mode']);
        $t->same(['left' => 120.0, 'top' => 640.0, 'zoom' => null], $rows[0]['view_parameters']);

        $t->same('DeckAction', $rows[0]['destination_action_name']);
        $t->same(9, $rows[0]['destination_action_object']);
        $t->same('GoTo', $rows[0]['destination_action_type']);
        $t->same(['GoTo', 'URI', 'JavaScript'], $rows[0]['destination_action_types']);
        $t->same(['local-destination', 'review-uri', 'blocked-javascript'], $rows[0]['destination_action_safeties']);
        $t->same(2, $rows[0]['destination_action_chained_count']);
        $t->same(true, $rows[0]['destination_action_all_review_only']);
        $t->same([false, false, false], array_column($rows[0]['destination_action_review_actions'], 'executes_on_import'));

        $t->same(1, $rows[0]['destination_action_target_page']);
        $t->same('Deck 8', $rows[0]['destination_action_target_page_label']);
        $t->same('XYZ', $rows[0]['destination_action_target_view_mode']);
        $t->same([120.0, 640.0, null], $rows[0]['destination_action_target_view_position']);
        $t->same(['left' => 120.0, 'top' => 640.0, 'zoom' => null], $rows[0]['destination_action_target_view_parameters']);
        $t->same(4.25, $rows[0]['destination_action_target_display_duration']);
        $t->same('Push', $rows[0]['destination_action_target_page_transition']['style']);
        $t->same(['page_open'], array_column($rows[0]['destination_action_target_page_actions'], 'event_label'));
        $t->same(['review-uri'], array_column($rows[0]['destination_action_target_page_actions'], 'safety'));

        $actionRows = $rows[0]['destination_action_review_actions'];
        $t->same(3, count($actionRows));
        $t->same(['DeckView', null, null], array_column($actionRows, 'destination'));
        $t->same('https://example.com/structured-action-context', $actionRows[1]['uri']);
        $t->same([null, true, true], [
            $actionRows[0]['chained'] ?? null,
            $actionRows[1]['chained'] ?? null,
            $actionRows[2]['chained'] ?? null,
        ]);

        $t->true(!array_key_exists('destination_action_name', $rows[1]));
        $t->same('FitH', $rows[1]['view_mode']);
        $t->same(['top' => 610.0], $rows[1]['view_parameters']);
    },
    'carries structured destination action context through composite navigation without visible text leakage' => static function (TestRunner $t) use ($outlineStructureDestinationActionContextPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineStructureDestinationActionContextPdf();
        $navigation = $extractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['outline', 'outline_actions', 'page_presentations'], $navigation['source']);
        $t->same(2, count($navigation['outline']));
        $t->same('DeckAction', $navigation['outline'][0]['destination_action_name']);
        $t->same(['GoTo', 'URI', 'JavaScript'], $navigation['outline'][0]['destination_action_types']);
        $t->same('XYZ', $navigation['outline'][0]['destination_action_target_view_mode']);
        $t->same('Push', $navigation['outline'][0]['destination_action_target_page_transition']['style']);

        $actions = $navigation['outline_action_review_actions'];
        $t->same(['DeckAction', 'DeckAction', 'DeckAction'], array_column($actions, 'destination_action_name'));
        $t->same(['GoTo', 'URI', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript'], array_column($actions, 'safety'));

        $t->contains('Cover structure action context page remains visible', $plainText);
        $t->contains('Deck structure action target page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Structured Destination Action'));
        $t->true(!str_contains($plainText, 'DeckAction'));
        $t->true(!str_contains($plainText, 'DeckView'));
        $t->true(!str_contains($plainText, 'structured-action-context'));
        $t->true(!str_contains($plainText, 'structured-page-open'));
        $t->true(!str_contains($plainText, 'hidden structured action context script'));
    },
];
