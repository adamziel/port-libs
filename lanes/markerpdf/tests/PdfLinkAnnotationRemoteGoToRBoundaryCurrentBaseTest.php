<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$remoteGoToRLinkBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Remote appendix Local fallback Hidden remote) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Local fallback target) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 14 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Contents (Remote appendix review) /A 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /S /GoToR /F 20 0 R /D [3 /FitH 720] /NewWindow true /Next [9 0 R 12 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D (local-fallback) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [300 700 400 718] /F 2 /Contents (Hidden remote review) /A << /S /GoToR /F (hidden-remote.pdf) /D (hidden-target) >> >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (remoteLinkDownReview\\(\\)) >>\nendobj\n"
        . "14 0 obj\n<< /Names [(local-fallback) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (fallback-appendix.pdf) /UF <FEFF00720065006D006F00740065002D0061007000700065006E006400690078002E007000640066> >>\nendobj\n"
        . "%%EOF";
};

$remoteGoToRLinkBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 400.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 400.0, 718.0],
                'spans' => [
                    ['text' => 'Remote appendix', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Local fallback', 'bbox' => [200.0, 700.0, 290.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden remote', 'bbox' => [300.0, 700.0, 400.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps remote GoToR Link annotations as review metadata without local page promotion' => static function (TestRunner $t) use (
        $remoteGoToRLinkBoundaryPdf,
        $remoteGoToRLinkBoundaryPages
    ): void {
        $pdf = $remoteGoToRLinkBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(1, count($links[0]['links']), 'Hidden remote GoToR annotations are not promoted.');

        $remote = $links[0]['links'][0];
        $t->same(7, $remote['annotation_object']);
        $t->same('GoToR', $remote['action_type']);
        $t->same('remote-document-review', $remote['safety']);
        $t->same('remote-appendix.pdf', $remote['file']);
        $t->same(3, $remote['destination_page']);
        $t->same(null, $remote['destination']);
        $t->same('FitH', $remote['view_mode']);
        $t->same([720.0], $remote['view_position']);
        $t->same(['top' => 720.0], $remote['view_parameters']);
        $t->same(true, $remote['new_window']);
        $t->same(false, $remote['executes_on_import']);
        $t->same(['GoToR', 'GoTo', 'JavaScript'], array_column($remote['actions'], 'action_type'));
        $t->same(['remote-document-review', 'local-destination', 'blocked-javascript'], array_column($remote['actions'], 'safety'));
        $t->same('local-fallback', $remote['actions'][1]['destination']);
        $t->same(1, $remote['actions'][1]['destination_page']);
        $t->same('XYZ', $remote['actions'][1]['view_mode']);

        $linkedPages = $extractor->applyLinksToPages($remoteGoToRLinkBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('GoToR', $spans[0]['link_action_type']);
        $t->same('remote-document-review', $spans[0]['link_safety']);
        $t->same('remote-appendix.pdf', $spans[0]['link_remote_file']);
        $t->same(3, $spans[0]['link_remote_destination_page']);
        $t->same('FitH', $spans[0]['link_remote_view_mode']);
        $t->same(['top' => 720.0], $spans[0]['link_remote_view_parameters']);
        $t->same(true, $spans[0]['link_remote_new_window']);
        $t->same(false, isset($spans[0]['link_destination_page']), 'Remote document page numbers are not same-document link destinations.');
        $t->same(false, isset($spans[0]['link_uri']));
        $t->same('local-fallback', $spans[0]['link_actions_review'][1]['destination']);
        $t->same(1, $spans[0]['link_actions_review'][1]['destination_page']);
        $t->same(false, isset($spans[1]['link_remote_file']));
        $t->same(false, isset($spans[2]['link_remote_file']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Remote appendix Local fallback Hidden remote', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'hidden-remote.pdf'));
        $t->same(false, str_contains($encodedReview, 'hidden-target'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Remote appendix Local fallback Hidden remote', $plainText);
        $t->contains('Local fallback target', $plainText);
        foreach ([
            'remote-appendix.pdf',
            'fallback-appendix.pdf',
            'hidden-remote.pdf',
            'remoteLinkDownReview',
            'Remote appendix review',
            'local-fallback',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
