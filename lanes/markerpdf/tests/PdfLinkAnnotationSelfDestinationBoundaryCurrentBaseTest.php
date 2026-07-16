<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$selfDestinationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Self fit Self xyz Other page Direct docs) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Other page destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 11 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 11 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 140 718] /Contents (Self page Fit review) /Dest [3 0 R /Fit] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [150 700 222 718] /Contents (Self page XYZ review) /Dest [3 0 R /XYZ 72 720 0] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [232 700 310 718] /Contents (Other page Fit review) /Dest [4 0 R /Fit] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [320 700 400 718] /Contents (Direct docs review) /A << /S /URI /URI (https://example.com/direct-docs) >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$selfDestinationBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 400.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 400.0, 718.0],
                'spans' => [
                    ['text' => 'Self fit', 'bbox' => [72.0, 700.0, 140.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Self xyz', 'bbox' => [150.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Other page', 'bbox' => [232.0, 700.0, 310.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct docs', 'bbox' => [320.0, 700.0, 400.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps same-page positionless Link destinations review-only before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($selfDestinationBoundaryPdf, $selfDestinationBoundaryPages): void {
        $pdf = $selfDestinationBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], ['local-destination'], ['review-uri']],
            array_map(static fn (array $annotation): array => array_column($annotation['actions'], 'safety'), $annotations[0]['annotations']),
            'All Link annotation actions remain reviewable annotation metadata.'
        );

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same('Fit', $links[0]['links'][0]['view_mode']);
        $t->same(0, $links[0]['links'][0]['destination_page']);
        $t->same([], $links[0]['links'][0]['view_position']);
        $t->same('XYZ', $links[0]['links'][1]['view_mode']);
        $t->same(['left' => 72.0, 'top' => 720.0, 'zoom' => null], $links[0]['links'][1]['view_parameters']);
        $t->same(1, $links[0]['links'][2]['destination_page']);
        $t->same('https://example.com/direct-docs', $links[0]['links'][3]['uri']);

        $pages = $extractor->applyLinksToPages($selfDestinationBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->true(!isset($spans[0]['link_destination_page']), 'A same-page /Fit destination without coordinates is not attached to a WordPress span.');
        $t->true(!isset($spans[0]['link_actions_review']));
        $t->same(0, $spans[1]['link_destination_page']);
        $t->same('XYZ', $spans[1]['link_view_mode']);
        $t->same(['left' => 72.0, 'top' => 720.0, 'zoom' => null], $spans[1]['link_view_parameters']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->same(1, $spans[2]['link_destination_page']);
        $t->same('Fit', $spans[2]['link_view_mode']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->same('https://example.com/direct-docs', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Self fit Self xyz Other page [Direct docs](https://example.com/direct-docs)', $blocks[0]['text']);
        $t->contains('Self page Fit review', $encoded($pages));
        $t->same(false, str_contains($encoded($spans[0]), 'Self page Fit review'));
        $t->same(false, str_contains($blocks[0]['text'], 'Self page Fit review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Self fit Self xyz Other page Direct docs', $plainText);
        $t->contains('Other page destination body', $plainText);
        foreach ([
            'Self page Fit review',
            'Self page XYZ review',
            'Other page Fit review',
            'Direct docs review',
            'direct-docs',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
