<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$remoteGoToRViewBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Valid remote Invalid view Missing top Named remote) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Valid remote review) /A << /S /GoToR /F (valid-remote.pdf) /D [2 /FitH 720] /NewWindow true >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 260 718] /Contents (Invalid remote view review) /A << /S /GoToR /F (invalid-view.pdf) /D [4 /Launch 720] /NewWindow true >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Missing remote top review) /A << /S /GoToR /F (missing-top.pdf) /D [5 /FitH] /NewWindow false >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 470 718] /Contents (Named remote review) /A << /S /GoToR /F (named-remote.pdf) /D (Remote Appendix) /NewWindow false >> >>\nendobj\n"
        . "%%EOF";
};

$remoteGoToRViewBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 470.0, 718.0],
                'spans' => [
                    ['text' => 'Valid remote', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Invalid view', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Missing top', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Named remote', 'bbox' => [370.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects malformed remote GoToR destination arrays before WordPress link promotion' => static function (
        TestRunner $t
    ) use ($remoteGoToRViewBoundaryPdf, $remoteGoToRViewBoundaryPages): void {
        $pdf = $remoteGoToRViewBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same([
            'remote-document-review',
            'unsupported-action-review',
            'unsupported-action-review',
            'remote-document-review',
        ], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same('valid-remote.pdf', $annotations[0]['annotations'][0]['actions'][0]['file']);
        $t->same(null, $annotations[0]['annotations'][1]['actions'][0]['file']);
        $t->same(null, $annotations[0]['annotations'][2]['actions'][0]['file']);
        $t->same('named-remote.pdf', $annotations[0]['annotations'][3]['actions'][0]['file']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 10], array_column($links[0]['links'], 'annotation_object'), 'Malformed remote destination arrays remain review-only.');
        $t->same(['valid-remote.pdf', 'named-remote.pdf'], array_column($links[0]['links'], 'file'));
        $t->same([2, null], array_column($links[0]['links'], 'destination_page'));
        $t->same(['FitH', null], array_column($links[0]['links'], 'view_mode'));
        $t->same('Remote Appendix', $links[0]['links'][1]['destination']);

        $pages = $extractor->applyLinksToPages($remoteGoToRViewBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('valid-remote.pdf', $spans[0]['link_remote_file']);
        $t->same(2, $spans[0]['link_remote_destination_page']);
        $t->same('FitH', $spans[0]['link_remote_view_mode']);
        $t->same(['top' => 720.0], $spans[0]['link_remote_view_parameters']);
        $t->true(!isset($spans[1]['link_remote_file']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_remote_file']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same('named-remote.pdf', $spans[3]['link_remote_file']);
        $t->same('Remote Appendix', $spans[3]['link_remote_destination']);
        $t->same(false, isset($spans[3]['link_remote_destination_page']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Valid remote Invalid view Missing top Named remote', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'invalid-view.pdf'));
        $t->same(false, str_contains($encodedReview, 'missing-top.pdf'));
        $t->same(false, str_contains($encodedReview, 'Invalid remote view review'));
        $t->same(false, str_contains($encodedReview, 'Missing remote top review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid remote Invalid view Missing top Named remote', $plainText);
        foreach ([
            'valid-remote.pdf',
            'invalid-view.pdf',
            'missing-top.pdf',
            'named-remote.pdf',
            'Remote Appendix',
            'Valid remote review',
            'Invalid remote view review',
            'Missing remote top review',
            'Named remote review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
