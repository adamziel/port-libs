<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkCatalogUriBaseBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Relative docs Absolute docs Unsafe docs Fragment docs) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI 12 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Relative base link review) /A << /S /URI /URI (articles/plugin-guide.pdf?from=pdf#setup) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 244 718] /Contents (Absolute link review) /A << /S /URI /URI (https://cdn.example.com/absolute.pdf) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [254 700 332 718] /Contents (Unsafe JavaScript link review) /A << /S /URI /URI (javascript:relativeBase\\(\\)) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [342 700 430 718] /Contents (Fragment base link review) /A << /S /URI /URI (#field-reference) >> >>\nendobj\n"
        . "12 0 obj\n<< /Base (https://docs.example.com/import/current/guide.pdf) >>\nendobj\n"
        . "%%EOF";
};

$linkCatalogUriBaseBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 430.0, 718.0],
                'spans' => [
                    ['text' => 'Relative docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Absolute docs', 'bbox' => [160.0, 700.0, 244.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Unsafe docs', 'bbox' => [254.0, 700.0, 332.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Fragment docs', 'bbox' => [342.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves catalog URI Base for relative Link annotations before WordPress span promotion' => static function (TestRunner $t) use (
        $linkCatalogUriBaseBoundaryPdf,
        $linkCatalogUriBaseBoundaryPages
    ): void {
        $pdf = $linkCatalogUriBaseBoundaryPdf();
        $relativeUri = 'https://docs.example.com/import/current/articles/plugin-guide.pdf?from=pdf#setup';
        $fragmentUri = 'https://docs.example.com/import/current/guide.pdf#field-reference';
        $relativeMarkdownUri = str_replace('#', '\\#', $relativeUri);
        $fragmentMarkdownUri = str_replace('#', '\\#', $fragmentUri);

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'review-uri', 'blocked-unsafe-uri', 'review-uri'], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));

        $relativeAction = $annotations[0]['annotations'][0]['actions'][0];
        $t->same($relativeUri, $relativeAction['uri']);
        $t->same('articles/plugin-guide.pdf?from=pdf#setup', $relativeAction['raw_uri']);
        $t->same('https://docs.example.com/import/current/guide.pdf', $relativeAction['uri_base']);
        $t->same(true, $relativeAction['uri_resolved_from_base']);
        $t->same(true, $relativeAction['uri_relative']);

        $absoluteAction = $annotations[0]['annotations'][1]['actions'][0];
        $t->same('https://cdn.example.com/absolute.pdf', $absoluteAction['uri']);
        $t->same(false, $absoluteAction['uri_resolved_from_base']);
        $t->same(false, $absoluteAction['uri_relative']);

        $unsafeAction = $annotations[0]['annotations'][2]['actions'][0];
        $t->same('javascript:relativeBase()', $unsafeAction['uri']);
        $t->same(false, $unsafeAction['is_safe_uri']);
        $t->same(false, $unsafeAction['uri_resolved_from_base']);

        $fragmentAction = $annotations[0]['annotations'][3]['actions'][0];
        $t->same($fragmentUri, $fragmentAction['uri']);
        $t->same('#field-reference', $fragmentAction['raw_uri']);
        $t->same(true, $fragmentAction['uri_resolved_from_base']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 10], array_column($links[0]['links'], 'annotation_object'), 'Unsafe JavaScript URI actions remain review-only and are not promoted.');
        $t->same($relativeUri, $links[0]['links'][0]['uri']);
        $t->same('articles/plugin-guide.pdf?from=pdf#setup', $links[0]['links'][0]['raw_uri']);
        $t->same('https://cdn.example.com/absolute.pdf', $links[0]['links'][1]['uri']);
        $t->same($fragmentUri, $links[0]['links'][2]['uri']);

        $pages = $extractor->applyLinksToPages($linkCatalogUriBaseBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same($relativeUri, $spans[0]['link_uri']);
        $t->same('articles/plugin-guide.pdf?from=pdf#setup', $spans[0]['link_actions_review'][0]['raw_uri']);
        $t->same('https://cdn.example.com/absolute.pdf', $spans[1]['link_uri']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same($fragmentUri, $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Relative docs](' . $relativeMarkdownUri . ') [Absolute docs](https://cdn.example.com/absolute.pdf) Unsafe docs [Fragment docs](' . $fragmentMarkdownUri . ')', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'javascript:relativeBase'));
        $t->same(false, str_contains($encodedReview, 'Unsafe JavaScript link review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Relative docs Absolute docs Unsafe docs Fragment docs', $plainText);
        foreach ([
            'plugin-guide.pdf',
            'cdn.example.com',
            'relativeBase',
            'field-reference',
            'Relative base link review',
            'Absolute link review',
            'Unsafe JavaScript link review',
            'Fragment base link review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
