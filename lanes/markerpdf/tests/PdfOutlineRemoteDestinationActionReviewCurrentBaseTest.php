<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRemoteDestinationActionReviewPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Cover page remains visible) Tj ET';
    $localText = 'BT /F1 12 Tf 72 720 Td (Local appendix page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Remote Named Destination Action) /Parent 5 0 R /Dest /RemoteReview /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Remote Direct Destination Action) /Parent 5 0 R /Dest 10 0 R /Next 14 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(RemoteReview) 9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoToR /F << /F (fallback-guide.pdf) /UF <FEFF00650078007400650072006E0061006C002D00670075006900640065002E007000640066> >> /D [3 /FitH 720] /NewWindow true /Next [11 0 R 12 0 R 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /GoToR /F (appendix.pdf) /D /Chapter#202 /Next 13 0 R >>\nendobj\n"
        . "11 0 obj\n<< /S /URI /URI (https://example.com/remote-notes) >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('remote destination hidden script'\\)) >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D [4 0 R /Fit] >>\nendobj\n"
        . "14 0 obj\n<< /Title (Local Appendix) /Parent 5 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Appendix ) /St 2 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($localText) . " >>\nstream\n{$localText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'reviews outline Dest values that resolve to remote GoToR action dictionaries' => static function (TestRunner $t) use ($outlineRemoteDestinationActionReviewPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineRemoteDestinationActionReviewPdf();
        $navigation = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'outline_actions'], $navigation['source']);
        $t->same(
            [['title' => 'Local Appendix', 'level' => 1, 'page' => 1, 'destination' => null]],
            $extractor->getPdfToc($pdf)
        );
        $t->same(['Local Appendix'], array_column($navigation['outline'], 'title'));
        $t->same('Appendix 2', $navigation['outline'][0]['page_label']);

        $actions = $navigation['outline_action_review_actions'];
        $t->same(5, count($actions), 'remote destination action dictionaries and their bounded /Next rows are review-only.');
        $t->same(
            [
                'Remote Named Destination Action',
                'Remote Named Destination Action',
                'Remote Named Destination Action',
                'Remote Direct Destination Action',
                'Remote Direct Destination Action',
            ],
            array_column($actions, 'outline_title')
        );
        $t->same(['GoToR', 'URI', 'JavaScript', 'GoToR', 'GoTo'], array_column($actions, 'action_type'));
        $t->same(['remote-document-review', 'review-uri', 'blocked-javascript', 'remote-document-review', 'local-destination'], array_column($actions, 'safety'));
        $t->same([9, 11, 12, 10, 13], array_column($actions, 'action_object'));
        $t->same([null, true, true, null, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
            $actions[4]['chained'] ?? null,
        ]);
        $t->same([false, false, false, false, false], array_column($actions, 'executes_on_import'));

        $t->same('external-guide.pdf', $actions[0]['file']);
        $t->same(3, $actions[0]['page']);
        $t->same(null, $actions[0]['destination']);
        $t->same(true, $actions[0]['new_window']);
        $t->same('https://example.com/remote-notes', $actions[1]['uri']);
        $t->same('appendix.pdf', $actions[3]['file']);
        $t->same('Chapter 2', $actions[3]['destination']);
        $t->same(null, $actions[3]['page']);
        $t->same(1, $actions[4]['page']);
        $t->same('Appendix 2', $actions[4]['page_label']);
    },
    'keeps remote destination action operands out of local TOC and visible WordPress text' => static function (TestRunner $t) use ($outlineRemoteDestinationActionReviewPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineRemoteDestinationActionReviewPdf();
        $remoteActions = $extractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(2, count($remoteActions));
        $t->same(['Remote Named Destination Action', 'Remote Direct Destination Action'], array_column($remoteActions, 'title'));
        $t->same(['external-guide.pdf', 'appendix.pdf'], array_column($remoteActions, 'file'));
        $t->same([3, null], array_column($remoteActions, 'page'));
        $t->same([null, 'Chapter 2'], array_column($remoteActions, 'destination'));

        $t->contains('Cover page remains visible', $plainText);
        $t->contains('Local appendix page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'external-guide.pdf'));
        $t->true(!str_contains($plainText, 'appendix.pdf'));
        $t->true(!str_contains($plainText, 'remote-notes'));
        $t->true(!str_contains($plainText, 'remote destination hidden script'));
    },
];
