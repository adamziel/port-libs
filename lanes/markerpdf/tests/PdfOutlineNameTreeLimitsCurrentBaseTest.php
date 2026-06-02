<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNameTreeLimitsPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current outline destination page stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Stale destination page stays visible but is not the TOC target) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /OpenAction /DeckStart /PageLabels 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 4 /Trans << /S /Wipe /D .25 /Di 90 >> /AA << /O 14 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Current Deck Start) /Parent 5 0 R /Dest /DeckStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Valid Review Target) /Parent 5 0 R /Dest /DeckReview >>\nendobj\n"
        . "8 0 obj\n<< /Kids [9 0 R 10 0 R 11 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(A) (M)] /Names [(DeckStart) [3 0 R /FitH 700] (DeckReview) 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(N) (Z)] /Names [(DeckStart) 13 0 R (ZedTarget) [4 0 R /Fit]] >>\nendobj\n"
        . "11 0 obj\n<< /Limits [(Z) (Z)] /Names [(ZedTarget) [4 0 R /Fit]] >>\nendobj\n"
        . "12 0 obj\n<< /D [3 0 R /XYZ 72 690 0] >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D [4 0 R /FitH 640] /Next 14 0 R >>\nendobj\n"
        . "14 0 obj\n<< /S /URI /URI (https://example.com/stale-outline-action) >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Current ) /St 1 >> 1 << /S /D /P (Stale ) /St 9 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'honors destination name-tree Limits before resolving outline rows' => static function (TestRunner $t) use ($outlineNameTreeLimitsPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineNameTreeLimitsPdf();
        $toc = $extractor->getPdfTocWithDestinationViews($pdf);

        $t->same(['Current Deck Start', 'Valid Review Target'], array_column($toc, 'title'));
        $t->same([0, 0], array_column($toc, 'page'));
        $t->same(['DeckStart', 'DeckReview'], array_column($toc, 'destination'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same([['top' => 700.0], ['left' => 72.0, 'top' => 690.0, 'zoom' => null]], array_column($toc, 'view_parameters'));

        $basicToc = $extractor->getPdfToc($pdf);
        $t->same([0, 0], array_column($basicToc, 'page'));
        $t->same(['DeckStart', 'DeckReview'], array_column($basicToc, 'destination'));
    },
    'keeps out-of-limits stale name-tree action rows out of navigation review metadata' => static function (TestRunner $t) use ($outlineNameTreeLimitsPdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineNameTreeLimitsPdf());

        $t->same(['outline', 'open_action', 'page_presentations'], $metadata['source']);
        $t->same(['Current 1', 'Current 1'], array_column($metadata['outline'], 'page_label'));
        $t->same([0, 0], array_column($metadata['outline'], 'page'));
        $t->same(['FitH', 'XYZ'], array_column($metadata['outline'], 'view_mode'));
        $t->same([], $metadata['outline_action_review_actions']);

        $t->same('DeckStart', $metadata['open_action_review_actions'][0]['destination']);
        $t->same(0, $metadata['open_action_review_actions'][0]['page']);
        $t->same('Current 1', $metadata['open_action_review_actions'][0]['page_label']);
        $t->same(0, $metadata['open_action_destination']['page']);
        $t->same('Current 1', $metadata['open_action_destination']['page_label']);
        $t->same('FitH', $metadata['open_action_destination']['view_mode']);
    },
    'does not leak stale out-of-limits action operands into WordPress text' => static function (TestRunner $t) use ($outlineNameTreeLimitsPdf): void {
        $pdf = $outlineNameTreeLimitsPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Current outline destination page stays visible', $plainText);
        $t->contains('Stale destination page stays visible but is not the TOC target', $plainText);
        $t->true(!str_contains($plainText, 'Current Deck Start'));
        $t->true(!str_contains($plainText, 'DeckStart'));
        $t->true(!str_contains($plainText, 'DeckReview'));
        $t->true(!str_contains($plainText, 'https://example.com/stale-outline-action'));
    },
];
