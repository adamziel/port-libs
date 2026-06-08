<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkIndirectObjectTailBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Clean docs Tailed action object Tailed destination object) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 40 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean docs review) /A << /S /URI /URI (https://example.com/clean-docs-indirect-tail-boundary) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 292 718] /Contents (Tailed action object review) /A 20 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [302 700 465 718] /Contents (Tailed destination object review) /Dest 30 0 R >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.com/tailed-action-object-promote) >> 21 0 R\nendobj\n"
        . "21 0 obj\n<< /S /JavaScript /JS (tailActionObjectReview\\(\\)) >>\nendobj\n"
        . "30 0 obj\n[4 0 R /XYZ 36 700 null] 22 0 R\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (https://example.com/tailed-destination-object-promote) >>\nendobj\n"
        . "40 0 obj\n<< /Names [(safe-target) [4 0 R /FitH 700]] >>\nendobj\n"
        . "%%EOF";
};

$linkIndirectObjectTailBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 465.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 465.0, 718.0],
                'spans' => [
                    ['text' => 'Clean docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed action object', 'bbox' => [160.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed destination object', 'bbox' => [302.0, 700.0, 465.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects indirect Link action and destination object tails before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkIndirectObjectTailBoundaryPdf, $linkIndirectObjectTailBoundaryPages): void {
        $pdf = $linkIndirectObjectTailBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same(['malformed-action-dictionary'], array_column($annotations[0]['annotations'][1]['actions'], 'safety'));
        $t->same('action_object_trailing_operands', $annotations[0]['annotations'][1]['actions'][0]['object_trailing_operand_review']['source'] ?? null);
        $t->same([], $annotations[0]['annotations'][2]['actions'], 'A tailed indirect destination array must not donate a local destination.');

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the clean direct action is promoted as a WordPress link.');
        $t->same('https://example.com/clean-docs-indirect-tail-boundary', $links[0]['links'][0]['uri']);
        $t->same('review-uri', $links[0]['links'][0]['safety']);

        $pages = $extractor->applyLinksToPages($linkIndirectObjectTailBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-docs-indirect-tail-boundary', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_destination_page']));
        $t->true(!isset($spans[2]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Clean docs](https://example.com/clean-docs-indirect-tail-boundary) Tailed action object Tailed destination object', $blocks[0]['text']);

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach ([
            'tailed-action-object-promote',
            'tailed-destination-object-promote',
            'tailActionObjectReview',
        ] as $tailedReviewText) {
            $t->same(false, str_contains($encodedReview, $tailedReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean docs Tailed action object Tailed destination object', $plainText);
        $t->contains('Current destination body', $plainText);
        foreach ([
            'clean-docs-indirect-tail-boundary',
            'tailed-action-object-promote',
            'tailed-destination-object-promote',
            'tailActionObjectReview',
            'Clean docs review',
            'Tailed action object review',
            'Tailed destination object review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
