<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationFlagsBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Printable docs Plain docs Hidden docs) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 168 718] /F 220 /Contents (Printable link flag review) /A << /S /URI /URI (https://example.com/printable-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [178 700 260 718] /Contents (Plain link flag review) /A << /S /URI /URI (https://example.com/plain-docs) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /F 36 /Contents (Hidden flag review) /A << /S /URI /URI (https://example.com/hidden-flag-docs) >> >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationFlagsBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Printable docs', 'bbox' => [72.0, 700.0, 168.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Plain docs', 'bbox' => [178.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden docs', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'carries visible Link annotation flags as review metadata before WordPress span promotion' => static function (TestRunner $t) use (
        $linkAnnotationFlagsBoundaryPdf,
        $linkAnnotationFlagsBoundaryPages
    ): void {
        $pdf = $linkAnnotationFlagsBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(220, $annotations[0]['annotations'][0]['annotation_flags']);
        $t->same(['print', 'no_zoom', 'no_rotate', 'read_only', 'locked'], $annotations[0]['annotations'][0]['annotation_flag_names']);
        $t->same('visible', $annotations[0]['annotations'][0]['annotation_visibility']);
        $t->same(0, $annotations[0]['annotations'][1]['annotation_flags']);
        $t->same([], $annotations[0]['annotations'][1]['annotation_flag_names']);
        $t->same('visible', $annotations[0]['annotations'][1]['annotation_visibility']);
        $t->same(36, $annotations[0]['annotations'][2]['annotation_flags']);
        $t->same(['print', 'no_view'], $annotations[0]['annotations'][2]['annotation_flag_names']);
        $t->same('no_view', $annotations[0]['annotations'][2]['annotation_visibility']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(2, count($links[0]['links']), 'No-view links stay out of WordPress-visible link promotion.');
        $t->same([7, 8], array_column($links[0]['links'], 'annotation_object'));
        $t->same(220, $links[0]['links'][0]['annotation_flags']);
        $t->same(['print', 'no_zoom', 'no_rotate', 'read_only', 'locked'], $links[0]['links'][0]['annotation_flag_names']);
        $t->same('visible', $links[0]['links'][0]['annotation_visibility']);
        $t->same(0, $links[0]['links'][1]['annotation_flags']);
        $t->same([], $links[0]['links'][1]['annotation_flag_names']);

        $pages = $extractor->applyLinksToPages($linkAnnotationFlagsBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/printable-docs', $spans[0]['link_uri']);
        $t->same(220, $spans[0]['link_annotation_flags']);
        $t->same(['print', 'no_zoom', 'no_rotate', 'read_only', 'locked'], $spans[0]['link_annotation_flag_names']);
        $t->same('visible', $spans[0]['link_annotation_visibility']);
        $t->same('https://example.com/plain-docs', $spans[1]['link_uri']);
        $t->same(0, $spans[1]['link_annotation_flags']);
        $t->same([], $spans[1]['link_annotation_flag_names']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['link_annotation_flags']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Printable docs](https://example.com/printable-docs) [Plain docs](https://example.com/plain-docs) Hidden docs', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'hidden-flag-docs'));
        $t->same(false, str_contains($encodedReview, 'Hidden flag review'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Printable docs Plain docs Hidden docs', $plainText);
        foreach ([
            'Printable link flag review',
            'Plain link flag review',
            'Hidden flag review',
            'printable-docs',
            'plain-docs',
            'hidden-flag-docs',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
