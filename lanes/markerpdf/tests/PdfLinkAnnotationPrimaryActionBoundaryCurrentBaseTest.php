<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkPrimaryActionBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Safe docs Script chain Launch chain) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Chained local destination) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe docs primary link) /A << /S /URI /URI (https://example.com/direct-safe) /Next << /S /JavaScript /JS (safeFollowupReview\\(\\)) >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 255 718] /Contents (Script chain review) /A << /S /JavaScript /JS (primaryScriptReview\\(\\)) /Next [10 0 R << /S /GoTo /D (safe-local) >>] >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [265 700 370 718] /Contents (Launch chain review) /A << /S /Launch /F (review-helper.exe) /Next 11 0 R >> >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/chained-safe) >>\nendobj\n"
        . "11 0 obj\n<< /S /GoToR /F 12 0 R /D [3 /FitH 720] /NewWindow false >>\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (remote-review.pdf) /UF <FEFF00720065006D006F00740065002D007200650076006900650077002E007000640066> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(safe-local) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
        . "%%EOF";
};

$linkPrimaryActionBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 370.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 370.0, 718.0],
                'spans' => [
                    ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Script chain', 'bbox' => [160.0, 700.0, 255.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Launch chain', 'bbox' => [265.0, 700.0, 370.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'promotes only direct primary Link actions while keeping chained safe actions review-only' => static function (TestRunner $t) use (
        $linkPrimaryActionBoundaryPdf,
        $linkPrimaryActionBoundaryPages
    ): void {
        $pdf = $linkPrimaryActionBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same(['blocked-javascript', 'review-uri', 'local-destination'], array_column($annotations[0]['annotations'][1]['actions'], 'safety'));
        $t->same(['blocked-launch', 'remote-document-review'], array_column($annotations[0]['annotations'][2]['actions'], 'safety'));
        $t->same('https://example.com/chained-safe', $annotations[0]['annotations'][1]['actions'][1]['uri']);
        $t->same(1, $annotations[0]['annotations'][1]['actions'][2]['destination_page']);
        $t->same('remote-review.pdf', $annotations[0]['annotations'][2]['actions'][1]['file']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the safe direct primary action is promoted as a WordPress link.');
        $t->same('https://example.com/direct-safe', $links[0]['links'][0]['uri']);
        $t->same(['URI', 'JavaScript'], array_column($links[0]['links'][0]['actions'], 'action_type'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($links[0]['links'][0]['actions'], 'safety'));

        $linkedPages = $extractor->applyLinksToPages($linkPrimaryActionBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/direct-safe', $spans[0]['link_uri']);
        $t->same(['review-uri', 'blocked-javascript'], array_column($spans[0]['link_actions_review'], 'safety'));
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_remote_file']));
        $t->true(!isset($spans[2]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Safe docs](https://example.com/direct-safe) Script chain Launch chain', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'chained-safe'));
        $t->same(false, str_contains($blocks[0]['text'], 'remote-review.pdf'));

        $encodedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedLinks, 'chained-safe'));
        $t->same(false, str_contains($encodedLinks, 'remote-review.pdf'));
        $t->same(false, str_contains($encodedLinks, 'primaryScriptReview'));
        $t->same(false, str_contains($encodedLinks, 'review-helper.exe'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Safe docs Script chain Launch chain', $plainText);
        $t->contains('Chained local destination', $plainText);
        foreach ([
            'direct-safe',
            'chained-safe',
            'remote-review.pdf',
            'primaryScriptReview',
            'safeFollowupReview',
            'review-helper.exe',
            'Safe docs primary link',
            'Script chain review',
            'Launch chain review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
