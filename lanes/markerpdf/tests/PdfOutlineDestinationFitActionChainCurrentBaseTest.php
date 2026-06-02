<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationFitActionChainPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover fit action chain page remains visible) Tj ET';
    $targetText = 'BT /F1 12 Tf 72 720 Td (Target fit action chain page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 5.25 /Trans 18 0 R /AA << /O 19 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Direct FitR Destination Action) /Parent 5 0 R /Dest 12 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Outline FitBH Action) /Parent 5 0 R /A 15 0 R /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Named FitB Destination Action) /Parent 5 0 R /Dest /BoxAction >>\nendobj\n"
        . "8 0 obj\n<< /Names [(BoxAction) 21 0 R (TopFit) [4 0 R /FitBH null 999] (BoxFit) [3 0 R /FitB 111 222]] >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D [4 0 R /FitR 36 120 420 760] /Next [13 0 R 14 0 R 14 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/fitr-followup) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden fitr action chain script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /GoTo /D /TopFit /Next 16 0 R >>\nendobj\n"
        . "16 0 obj\n<< /S /URI /URI (https://example.com/topfit-followup) >>\nendobj\n"
        . "18 0 obj\n<< /S /Glitter /D .6 /Di 270 >>\nendobj\n"
        . "19 0 obj\n<< /S /URI /URI (https://example.com/page-open-fit-review) >>\nendobj\n"
        . "21 0 obj\n<< /S /GoTo /D /BoxFit /Next 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /S /Launch /F (fit-review-helper.exe) >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Target ) /St 3 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'propagates explicit Fit destination parameters from direct outline destination action chains' => static function (TestRunner $t) use ($outlineDestinationFitActionChainPdf): void {
        $pdf = $outlineDestinationFitActionChainPdf();
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'outline_actions', 'page_presentations'], $metadata['source']);
        $t->same(
            ['Direct FitR Destination Action', 'Outline FitBH Action', 'Named FitB Destination Action'],
            array_column($metadata['outline'], 'title')
        );
        $t->same([1, 1, 0], array_column($metadata['outline'], 'page'));
        $t->same([null, 'TopFit', 'BoxAction'], array_column($metadata['outline'], 'destination'));
        $t->same(['FitR', 'FitBH', 'FitB'], array_column($metadata['outline'], 'view_mode'));
        $t->same(
            [
                ['left' => 36.0, 'bottom' => 120.0, 'right' => 420.0, 'top' => 760.0],
                ['top' => null],
                [],
            ],
            array_column($metadata['outline'], 'view_parameters')
        );

        $actions = $metadata['outline_action_review_actions'];
        $t->same(7, count($actions), 'direct and named destination action chains expose bounded review rows once.');
        $t->same(['GoTo', 'URI', 'JavaScript', 'GoTo', 'URI', 'GoTo', 'Launch'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript', 'local-destination', 'review-uri', 'local-destination', 'blocked-launch'], array_column($actions, 'safety'));
        $t->same([12, 13, 14, 15, 16, 21, 22], array_column($actions, 'action_object'));
        $t->same([null, true, true, null, true, null, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
            $actions[4]['chained'] ?? null,
            $actions[5]['chained'] ?? null,
            $actions[6]['chained'] ?? null,
        ]);

        foreach (array_slice($actions, 0, 3) as $action) {
            $t->same(1, $action['destination_action_target_page']);
            $t->same('Target 3', $action['destination_action_target_page_label']);
            $t->same('FitR', $action['destination_action_target_view_mode']);
            $t->same([36.0, 120.0, 420.0, 760.0], $action['destination_action_target_view_position']);
            $t->same(['left' => 36.0, 'bottom' => 120.0, 'right' => 420.0, 'top' => 760.0], $action['destination_action_target_view_parameters']);
            $t->same(5.25, $action['destination_action_target_display_duration']);
            $t->same('Glitter', $action['destination_action_target_page_transition']['style']);
            $t->same(['page_open'], array_column($action['destination_action_target_page_actions'], 'event_label'));
        }

        foreach (array_slice($actions, 3, 2) as $action) {
            $t->same(1, $action['destination_action_target_page']);
            $t->same('Target 3', $action['destination_action_target_page_label']);
            $t->same('FitBH', $action['destination_action_target_view_mode']);
            $t->same([null], $action['destination_action_target_view_position']);
            $t->same(['top' => null], $action['destination_action_target_view_parameters']);
        }

        foreach (array_slice($actions, 5, 2) as $action) {
            $t->same(0, $action['destination_action_target_page']);
            $t->same('Cover 1', $action['destination_action_target_page_label']);
            $t->same('FitB', $action['destination_action_target_view_mode']);
            $t->same([], $action['destination_action_target_view_position']);
            $t->same([], $action['destination_action_target_view_parameters']);
            $t->same('BoxAction', $action['destination_action_name']);
        }

        $t->same([false, false, false, false, false, false, false], array_column($actions, 'executes_on_import'));
    },
    'keeps fit destination action chain operands out of visible WordPress text' => static function (TestRunner $t) use ($outlineDestinationFitActionChainPdf): void {
        $pdf = $outlineDestinationFitActionChainPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Cover fit action chain page remains visible', $plainText);
        $t->contains('Target fit action chain page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Direct FitR Destination Action'));
        $t->true(!str_contains($plainText, 'TopFit'));
        $t->true(!str_contains($plainText, 'BoxAction'));
        $t->true(!str_contains($plainText, 'fitr-followup'));
        $t->true(!str_contains($plainText, 'topfit-followup'));
        $t->true(!str_contains($plainText, 'page-open-fit-review'));
        $t->true(!str_contains($plainText, 'hidden fitr action chain script'));
        $t->true(!str_contains($plainText, 'fit-review-helper.exe'));
    },
];
