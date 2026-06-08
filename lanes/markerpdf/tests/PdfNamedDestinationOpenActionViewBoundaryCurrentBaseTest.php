<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationOpenActionViewBoundaryCurrentBasePdf = static function (): string {
    $introPageContent = 'BT /F1 12 Tf 72 720 Td (Open action intro page stays visible) Tj ET';
    $asciiTargetContent = 'BT /F1 12 Tf 72 720 Td (ASCII collision target body stays visible) Tj ET';
    $utf16TargetContent = 'BT /F1 12 Tf 72 720 Td (UTF16 collision open action target stays visible) Tj ET';
    $utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /OpenAction {$utf16Collision} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Collision) {$utf16Collision}] /Names [(Collision) [4 0 R /FitH 700] {$utf16Collision} [5 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introPageContent) . " >>\nstream\n{$introPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($asciiTargetContent) . " >>\nstream\n{$asciiTargetContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($utf16TargetContent) . " >>\nstream\n{$utf16TargetContent}\nendstream\nendobj\n"
        . "%%EOF\n";
};

return [
    'preserves decoded-collision named destination view metadata on catalog OpenAction review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationOpenActionViewBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationOpenActionViewBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $outline = new PdfOutlineExtractor();
        $actions = $outline->getOpenActionReviewActions($pdf);
        $catalogView = $outline->getCatalogPageViewMetadata($pdf);
        $navigation = $outline->getNavigationReviewMetadata($pdf);

        $t->same(['Collision', 'Collision'], array_column($destinations, 'name'));
        $t->same([1, 2], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($destinations, 'fit'));
        $t->same('436f6c6c6973696f6e', $destinations[0]['name_bytes_hex'] ?? null);
        $t->same('feff0043006f006c006c006900730069006f006e', $destinations[1]['name_bytes_hex'] ?? null);

        $t->same(1, count($actions));
        $t->same('GoTo', $actions[0]['action_type']);
        $t->same('local-destination', $actions[0]['safety']);
        $t->same(2, $actions[0]['page']);
        $t->same('Collision', $actions[0]['destination']);
        $t->same('XYZ', $actions[0]['view_mode']);
        $t->same([72.0, 640.0, null], $actions[0]['view_position']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $actions[0]['view_parameters']);
        $t->same(false, $actions[0]['executes_on_import']);

        $t->same(['open_action'], $catalogView['source']);
        $t->same(2, $catalogView['open_action']['page']);
        $t->same('Collision', $catalogView['open_action']['destination']);
        $t->same('XYZ', $catalogView['open_action']['view_mode']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $catalogView['open_action']['view_parameters']);

        $t->same(['open_action'], $navigation['source']);
        $t->same('XYZ', $navigation['open_action_destination']['view_mode']);
        $t->same('XYZ', $navigation['open_action_review_actions'][0]['view_mode']);
        $t->same($catalogView['open_action']['view_parameters'], $navigation['open_action_review_actions'][0]['view_parameters']);
    },
    'keeps catalog OpenAction named destination operands out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationOpenActionViewBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationOpenActionViewBoundaryCurrentBasePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $actions = (new PdfOutlineExtractor())->getOpenActionReviewActions($pdf);
        $encodedActions = json_encode($actions, JSON_UNESCAPED_SLASHES) ?: '';

        $t->contains('Open action intro page stays visible', $plainText);
        $t->contains('ASCII collision target body stays visible', $plainText);
        $t->contains('UTF16 collision open action target stays visible', $plainText);
        foreach (['Collision', 'OpenAction', 'FitH', 'XYZ'] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }
        $t->same(true, str_contains($encodedActions, '"view_mode":"XYZ"'));
        $t->same(true, str_contains($encodedActions, '"view_parameters":{"left":72,"top":640,"zoom":null}'));
    },
];
