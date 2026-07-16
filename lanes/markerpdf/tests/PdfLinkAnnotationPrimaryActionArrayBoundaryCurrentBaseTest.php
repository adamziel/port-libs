<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkPrimaryActionArrayBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Direct docs Array spoof Indirect array) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Direct docs review) /A << /S /URI /URI (https://example.com/direct-docs-array-boundary) /Next [10 0 R << /S /JavaScript /JS (directFollowupReview\\(\\)) >>] >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Array spoof review) /A [<< /S /URI /URI (https://example.com/array-spoof-link) >> << /S /JavaScript /JS (arraySpoofReview\\(\\)) >>] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 370 718] /Contents (Indirect array review) /A 12 0 R >>\nendobj\n"
        . "10 0 obj\n<< /S /URI /URI (https://example.com/direct-chained-review) >>\nendobj\n"
        . "12 0 obj\n[<< /S /URI /URI (https://example.com/indirect-array-spoof) >> << /S /Launch /F (review-helper.exe) >>]\nendobj\n"
        . "%%EOF";
};

$linkPrimaryActionArrayBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 370.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 370.0, 718.0],
                'spans' => [
                    ['text' => 'Direct docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Array spoof', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect array', 'bbox' => [260.0, 700.0, 370.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects primary Link annotation A action arrays before WordPress span promotion' => static function (TestRunner $t) use (
        $linkPrimaryActionArrayBoundaryPdf,
        $linkPrimaryActionArrayBoundaryPages
    ): void {
        $pdf = $linkPrimaryActionArrayBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'review-uri', 'blocked-javascript'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same([], $annotations[0]['annotations'][1]['actions'], 'A direct primary action array is malformed and must not be treated as a primary Link action.');
        $t->same([], $annotations[0]['annotations'][2]['actions'], 'An indirect primary action array is malformed and must not be treated as a primary Link action.');

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the single action dictionary under /A is promoted.');
        $t->same('https://example.com/direct-docs-array-boundary', $links[0]['links'][0]['uri']);
        $t->same(['URI', 'URI', 'JavaScript'], array_column($links[0]['links'][0]['actions'], 'action_type'));
        $t->same(['review-uri', 'review-uri', 'blocked-javascript'], array_column($links[0]['links'][0]['actions'], 'safety'));
        $t->same(false, str_contains($encoded($links), 'array-spoof-link'));
        $t->same(false, str_contains($encoded($links), 'indirect-array-spoof'));
        $t->same(false, str_contains($encoded($links), 'review-helper.exe'));

        $pages = $extractor->applyLinksToPages($linkPrimaryActionArrayBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/direct-docs-array-boundary', $spans[0]['link_uri']);
        $t->same(['review-uri', 'review-uri', 'blocked-javascript'], array_column($spans[0]['link_actions_review'], 'safety'));
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Direct docs](https://example.com/direct-docs-array-boundary) Array spoof Indirect array', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'array-spoof-link'));
        $t->same(false, str_contains($blocks[0]['text'], 'indirect-array-spoof'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Direct docs Array spoof Indirect array', $plainText);
        foreach ([
            'direct-docs-array-boundary',
            'array-spoof-link',
            'indirect-array-spoof',
            'review-helper.exe',
            'Direct docs review',
            'Array spoof review',
            'Indirect array review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
