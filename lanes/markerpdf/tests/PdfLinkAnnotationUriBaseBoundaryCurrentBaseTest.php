<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkUriBaseBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Relative guide Query only Fragment only Absolute ftp) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI << /Base (https://example.com/imports/2026/base.pdf?keep=1) >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Contents (Relative guide review) /A << /S /URI /URI (docs/../guides/import.html?source=pdf#section) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 252 718] /Contents (Query only review) /A << /S /URI /URI (?download=1) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 356 718] /Contents (Fragment only review) /A << /S /URI /URI (#fragment-only) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [366 700 456 718] /Contents (Absolute ftp review) /A << /S /URI /URI (ftp://files.example.com/archive.zip) >> >>\nendobj\n"
        . "%%EOF";
};

$linkUriBaseBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 456.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 456.0, 718.0],
                'spans' => [
                    ['text' => 'Relative guide', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Query only', 'bbox' => [168.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Fragment only', 'bbox' => [262.0, 700.0, 356.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Absolute ftp', 'bbox' => [366.0, 700.0, 456.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves catalog URI Base relative Link annotation URIs and preserves raw span review metadata' => static function (TestRunner $t) use (
        $linkUriBaseBoundaryPdf,
        $linkUriBaseBoundaryPages
    ): void {
        $pdf = $linkUriBaseBoundaryPdf();
        $base = 'https://example.com/imports/2026/base.pdf?keep=1';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri', 'review-uri', 'review-uri', 'review-uri'], array_map(
            static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
            $annotations[0]['annotations']
        ));
        $t->same([true, true, true, false], array_map(
            static fn (array $annotation): bool => (bool) ($annotation['actions'][0]['uri_relative'] ?? false),
            $annotations[0]['annotations']
        ));
        $t->same([true, true, true, false], array_map(
            static fn (array $annotation): bool => (bool) ($annotation['actions'][0]['uri_resolved_from_base'] ?? false),
            $annotations[0]['annotations']
        ));
        $t->same($base, $annotations[0]['annotations'][0]['actions'][0]['uri_base']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 9, 10], array_column($links[0]['links'], 'annotation_object'));
        $t->same([
            'https://example.com/imports/2026/guides/import.html?source=pdf#section',
            'https://example.com/imports/2026/base.pdf?download=1',
            'https://example.com/imports/2026/base.pdf?keep=1#fragment-only',
            'ftp://files.example.com/archive.zip',
        ], array_column($links[0]['links'], 'uri'));
        $t->same('docs/../guides/import.html?source=pdf#section', $links[0]['links'][0]['raw_uri']);
        $t->same($base, $links[0]['links'][0]['uri_base']);
        $t->same(false, array_key_exists('raw_uri', $links[0]['links'][3]));

        $pages = $extractor->applyLinksToPages($linkUriBaseBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/imports/2026/guides/import.html?source=pdf#section', $spans[0]['link_uri']);
        $t->same('docs/../guides/import.html?source=pdf#section', $spans[0]['link_raw_uri']);
        $t->same($base, $spans[0]['link_uri_base']);
        $t->same(true, $spans[0]['link_uri_relative']);
        $t->same(true, $spans[0]['link_uri_resolved_from_base']);
        $t->same('https://example.com/imports/2026/base.pdf?download=1', $spans[1]['link_uri']);
        $t->same('?download=1', $spans[1]['link_raw_uri']);
        $t->same('https://example.com/imports/2026/base.pdf?keep=1#fragment-only', $spans[2]['link_uri']);
        $t->same('#fragment-only', $spans[2]['link_raw_uri']);
        $t->same('ftp://files.example.com/archive.zip', $spans[3]['link_uri']);
        $t->same(false, isset($spans[3]['link_raw_uri']));
        $t->same(false, (bool) ($spans[3]['link_uri_relative'] ?? false));
        $t->same(false, (bool) ($spans[3]['link_uri_resolved_from_base'] ?? false));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Relative guide](https://example.com/imports/2026/guides/import.html?source=pdf\\#section) '
                . '[Query only](https://example.com/imports/2026/base.pdf?download=1) '
                . '[Fragment only](https://example.com/imports/2026/base.pdf?keep=1\\#fragment-only) '
                . '[Absolute ftp](ftp://files.example.com/archive.zip)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Relative guide Query only Fragment only Absolute ftp', $plainText);
        foreach ([
            'Relative guide review',
            'Query only review',
            'Fragment only review',
            'Absolute ftp review',
            'imports/2026/base.pdf',
            'files.example.com',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
