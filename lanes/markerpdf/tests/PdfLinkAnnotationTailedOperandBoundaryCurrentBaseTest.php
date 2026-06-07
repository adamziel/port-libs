<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationTailedOperandBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Clean docs Tailed action Tailed destination) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current destination target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 30 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean direct link review) /A << /S /URI /URI (https://example.com/clean-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Tailed action review) /A 20 0 R 21 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 390 718] /Contents (Tailed destination review) /Dest (current-target) 22 0 R >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.com/tailed-action-promote) >>\nendobj\n"
        . "21 0 obj\n<< /S /JavaScript /JS (tailActionReview\\(\\)) >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (https://example.com/tailed-destination-promote) >>\nendobj\n"
        . "30 0 obj\n<< /Names [(current-target) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationTailedOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'spans' => [
                    ['text' => 'Clean docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed action', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed destination', 'bbox' => [270.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed Link annotation action and destination operands before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkAnnotationTailedOperandBoundaryPdf, $linkAnnotationTailedOperandBoundaryPages): void {
        $pdf = $linkAnnotationTailedOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same([], $annotations[0]['annotations'][1]['actions'], 'A tailed /A reference is malformed and must not donate a Link action.');
        $t->same([], $annotations[0]['annotations'][2]['actions'], 'A tailed /Dest string is malformed and must not donate a local destination.');
        $t->same(['A'], $annotations[0]['annotations'][1]['malformed_action_operand_keys'] ?? null);
        $t->same(['Dest'], $annotations[0]['annotations'][2]['malformed_action_operand_keys'] ?? null);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the clean direct action is promoted as a WordPress link.');
        $t->same('https://example.com/clean-docs', $links[0]['links'][0]['uri']);
        $t->same('review-uri', $links[0]['links'][0]['safety']);

        $pages = $extractor->applyLinksToPages($linkAnnotationTailedOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-docs', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_destination_page']));
        $t->true(!isset($spans[2]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Clean docs](https://example.com/clean-docs) Tailed action Tailed destination', $blocks[0]['text']);

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach ([
            'tailed-action-promote',
            'tailed-destination-promote',
            'tailActionReview',
            'current-target',
        ] as $tailedReviewText) {
            $t->same(false, str_contains($encodedReview, $tailedReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean docs Tailed action Tailed destination', $plainText);
        $t->contains('Current destination target page', $plainText);
        foreach ([
            'clean-docs',
            'tailed-action-promote',
            'tailed-destination-promote',
            'tailActionReview',
            'Clean direct link review',
            'Tailed action review',
            'Tailed destination review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
