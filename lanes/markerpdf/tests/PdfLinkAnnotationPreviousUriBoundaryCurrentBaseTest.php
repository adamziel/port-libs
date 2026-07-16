<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$previousUriBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Imported jump Current docs Previous only) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Saved destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Imported jump review) /A << /S /GoTo /D (saved-target) >> /PA << /S /URI /URI (https://archive.example.com/original-guide) /Next << /S /URI /URI (https://archive.example.com/original-followup) >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 260 718] /Contents (Current docs review) /A << /S /URI /URI (https://example.com/current-docs) >> /PA 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 365 718] /Contents (Previous only review) /PA << /S /URI /URI (https://archive.example.com/previous-only) >> >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://archive.example.com/old-current-docs) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(saved-target) [4 0 R /FitH 720]] >>\nendobj\n"
        . "%%EOF";
};

$previousUriBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 365.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 365.0, 718.0],
                'spans' => [
                    ['text' => 'Imported jump', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current docs', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Previous only', 'bbox' => [270.0, 700.0, 365.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps Link annotation previous URI actions review-only before WordPress span promotion' => static function (TestRunner $t) use (
        $previousUriBoundaryPdf,
        $previousUriBoundaryPages
    ): void {
        $pdf = $previousUriBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));

        $jumpAnnotation = $annotations[0]['annotations'][0];
        $t->same(['local-destination'], array_column($jumpAnnotation['actions'], 'safety'));
        $t->same(1, $jumpAnnotation['actions'][0]['destination_page']);
        $t->same(['review-uri', 'review-uri'], array_column($jumpAnnotation['previous_uri_actions'], 'safety'));
        $t->same('https://archive.example.com/original-guide', $jumpAnnotation['previous_uri_actions'][0]['uri']);
        $t->same('https://archive.example.com/original-followup', $jumpAnnotation['previous_uri_actions'][1]['uri']);
        $t->same(true, $jumpAnnotation['previous_uri_actions'][1]['chained']);

        $currentAnnotation = $annotations[0]['annotations'][1];
        $t->same('https://example.com/current-docs', $currentAnnotation['actions'][0]['uri']);
        $t->same('https://archive.example.com/old-current-docs', $currentAnnotation['previous_uri_actions'][0]['uri']);

        $previousOnlyAnnotation = $annotations[0]['annotations'][2];
        $t->same([], $previousOnlyAnnotation['actions']);
        $t->same('https://archive.example.com/previous-only', $previousOnlyAnnotation['previous_uri_actions'][0]['uri']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'), 'A /PA-only annotation remains review metadata and is not promoted as a clickable WordPress link.');

        $jumpLink = $links[0]['links'][0];
        $t->same('local-destination', $jumpLink['safety']);
        $t->same(1, $jumpLink['destination_page']);
        $t->same(null, $jumpLink['uri']);
        $t->same('https://archive.example.com/original-guide', $jumpLink['previous_uri_actions'][0]['uri']);
        $t->same('https://archive.example.com/original-followup', $jumpLink['previous_uri_actions'][1]['uri']);

        $currentLink = $links[0]['links'][1];
        $t->same('https://example.com/current-docs', $currentLink['uri']);
        $t->same('https://archive.example.com/old-current-docs', $currentLink['previous_uri_actions'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'previous-only'));

        $pages = $extractor->applyLinksToPages($previousUriBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('https://archive.example.com/original-guide', $spans[0]['link_previous_uri_actions'][0]['uri']);
        $t->true(!isset($spans[0]['link_uri']));
        $t->same('https://example.com/current-docs', $spans[1]['link_uri']);
        $t->same('https://archive.example.com/old-current-docs', $spans[1]['link_previous_uri_actions'][0]['uri']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_previous_uri_actions']));
        $t->true(!isset($spans[2]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Imported jump [Current docs](https://example.com/current-docs) Previous only', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'archive.example.com'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Imported jump Current docs Previous only', $plainText);
        $t->contains('Saved destination body', $plainText);
        foreach ([
            'archive.example.com',
            'current-docs',
            'Imported jump review',
            'Current docs review',
            'Previous only review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
