<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkCatalogUriBaseDuplicateBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Relative docs Fragment docs Absolute docs Unsafe docs) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI << /Base (https://docs.example.com/import/current/guide.pdf) /Base (https://evil.example.com/rewrite/base.pdf) >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Relative duplicate-base review) /A << /S /URI /URI (articles/import.html#setup) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 258 718] /Contents (Fragment duplicate-base review) /A << /S /URI /URI (#field-reference) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [268 700 360 718] /Contents (Absolute duplicate-base review) /A << /S /URI /URI (https://cdn.example.com/absolute.pdf) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 456 718] /Contents (Unsafe duplicate-base review) /A << /S /URI /URI (javascript:duplicateBase\\(\\)) >> >>\nendobj\n"
        . "%%EOF";
};

$linkCatalogUriBaseDuplicateBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 456.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 456.0, 718.0],
                'spans' => [
                    ['text' => 'Relative docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Fragment docs', 'bbox' => [170.0, 700.0, 258.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Absolute docs', 'bbox' => [268.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Unsafe docs', 'bbox' => [370.0, 700.0, 456.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects duplicate catalog URI Base keys before resolving Link annotation hrefs' => static function (
        TestRunner $t
    ) use ($linkCatalogUriBaseDuplicateBoundaryPdf, $linkCatalogUriBaseDuplicateBoundaryPages): void {
        $pdf = $linkCatalogUriBaseDuplicateBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'review-uri', 'review-uri', 'blocked-unsafe-uri'], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));

        $relativeAction = $annotations[0]['annotations'][0]['actions'][0];
        $t->same('articles/import.html#setup', $relativeAction['uri']);
        $t->same(true, $relativeAction['uri_relative']);
        $t->same(false, $relativeAction['uri_resolved_from_base']);
        $t->same(false, array_key_exists('raw_uri', $relativeAction));
        $t->same(false, array_key_exists('uri_base', $relativeAction));

        $fragmentAction = $annotations[0]['annotations'][1]['actions'][0];
        $t->same('#field-reference', $fragmentAction['uri']);
        $t->same(true, $fragmentAction['uri_relative']);
        $t->same(false, $fragmentAction['uri_resolved_from_base']);
        $t->same(false, array_key_exists('uri_base', $fragmentAction));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'));
        $t->same('articles/import.html#setup', $links[0]['links'][0]['uri']);
        $t->same(false, $links[0]['links'][0]['uri_resolved_from_base']);
        $t->same(false, array_key_exists('uri_base', $links[0]['links'][0]));
        $t->same('#field-reference', $links[0]['links'][1]['uri']);
        $t->same(false, $links[0]['links'][1]['uri_resolved_from_base']);
        $t->same('https://cdn.example.com/absolute.pdf', $links[0]['links'][2]['uri']);

        $pages = $extractor->applyLinksToPages($linkCatalogUriBaseDuplicateBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('articles/import.html#setup', $spans[0]['link_uri']);
        $t->same(true, $spans[0]['link_uri_relative']);
        $t->same(false, $spans[0]['link_uri_resolved_from_base']);
        $t->same(false, isset($spans[0]['link_raw_uri']));
        $t->same(false, isset($spans[0]['link_uri_base']));
        $t->same('#field-reference', $spans[1]['link_uri']);
        $t->same(false, isset($spans[1]['link_uri_base']));
        $t->same('https://cdn.example.com/absolute.pdf', $spans[2]['link_uri']);
        $t->same(false, isset($spans[3]['link_uri']));
        $t->same(false, isset($spans[3]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Relative docs](articles/import.html\\#setup) [Fragment docs](\\#field-reference) [Absolute docs](https://cdn.example.com/absolute.pdf) Unsafe docs',
            $blocks[0]['text']
        );

        $promotedReview = $encoded([$links, $pages]);
        foreach ([
            'docs.example.com',
            'evil.example.com',
            'javascript:duplicateBase',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($promotedReview, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Relative docs Fragment docs Absolute docs Unsafe docs', $plainText);
        foreach ([
            'articles/import.html',
            'field-reference',
            'cdn.example.com',
            'docs.example.com',
            'evil.example.com',
            'duplicateBase',
            'Relative duplicate-base review',
            'Fragment duplicate-base review',
            'Absolute duplicate-base review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
