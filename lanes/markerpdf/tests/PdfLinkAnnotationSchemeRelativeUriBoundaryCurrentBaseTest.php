<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkSchemeRelativeUriBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Safe docs Scheme docs Relative docs) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI << /Base (https://docs.example.com/import/base.pdf) >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe absolute review) /A << /S /URI /URI (https://example.com/safe-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Scheme relative review) /A << /S /URI /URI (//evil.example/protocol-relative.pdf) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 360 718] /Contents (Relative guide review) /A << /S /URI /URI (guide.html#setup) >> >>\nendobj\n"
        . "%%EOF";
};

$linkSchemeRelativeUriBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Scheme docs', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Relative docs', 'bbox' => [260.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'keeps scheme-relative Link annotation URIs review-only before WordPress span promotion' => static function (TestRunner $t) use (
        $linkSchemeRelativeUriBoundaryPdf,
        $linkSchemeRelativeUriBoundaryPages
    ): void {
        $pdf = $linkSchemeRelativeUriBoundaryPdf();
        $resolvedRelative = 'https://docs.example.com/import/guide.html#setup';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'blocked-unsafe-uri', 'review-uri'], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same('//evil.example/protocol-relative.pdf', $annotations[0]['annotations'][1]['actions'][0]['uri']);
        $t->same(false, $annotations[0]['annotations'][1]['actions'][0]['is_safe_uri']);
        $t->same(false, $annotations[0]['annotations'][1]['actions'][0]['uri_relative']);
        $t->same(false, $annotations[0]['annotations'][1]['actions'][0]['uri_resolved_from_base']);
        $t->same($resolvedRelative, $annotations[0]['annotations'][2]['actions'][0]['uri']);
        $t->same(true, $annotations[0]['annotations'][2]['actions'][0]['uri_resolved_from_base']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'), 'Scheme-relative network-path URI actions remain review-only.');
        $t->same('https://example.com/safe-docs', $links[0]['links'][0]['uri']);
        $t->same($resolvedRelative, $links[0]['links'][1]['uri']);

        $pages = $extractor->applyLinksToPages($linkSchemeRelativeUriBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/safe-docs', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->same($resolvedRelative, $spans[2]['link_uri']);
        $t->same('guide.html#setup', $spans[2]['link_raw_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Safe docs](https://example.com/safe-docs) Scheme docs [Relative docs](https://docs.example.com/import/guide.html\\#setup)',
            $blocks[0]['text']
        );
        $t->same(false, str_contains($blocks[0]['text'], '//evil.example'));

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, '//evil.example'));
        $t->same(false, str_contains($encodedReview, 'Scheme relative review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Safe docs Scheme docs Relative docs', $plainText);
        foreach ([
            'safe-docs',
            'evil.example',
            'guide.html',
            'Safe absolute review',
            'Scheme relative review',
            'Relative guide review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
