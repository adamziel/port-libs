<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$parentGenerationPdf = static function (): string {
    $content = 'BT /F1 12 Tf 60 210 Td (Current parent docs Stale parent decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 1 R >>\nendobj\n"
        . "2 1 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 50 250 250] /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [60 200 174 218] /Contents (Current parent link review) /A << /S /URI /URI (https://example.com/current-parent-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [10 10 38 38] /Contents (Stale parent geometry review) /A << /S /URI /URI (https://example.com/stale-parent-link) >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 40 40] /CropBox [0 0 40 40] /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "%%EOF";
};

$parentGenerationPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [10.0, 10.0, 246.0, 218.0],
            'lines' => [[
                'bbox' => [10.0, 10.0, 246.0, 218.0],
                'spans' => [
                    ['text' => 'Current parent docs', 'bbox' => [60.0, 200.0, 174.0, 218.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale parent decoy', 'bbox' => [10.0, 10.0, 38.0, 38.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses exact page Parent generations before link annotation CropBox boundaries' => static function (TestRunner $t) use (
        $parentGenerationPdf,
        $parentGenerationPages
    ): void {
        $pdf = $parentGenerationPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(1, count($links[0]['links']), 'Only the link inside the exact-generation parent CropBox is promoted.');

        $link = $links[0]['links'][0];
        $t->same(7, $link['annotation_object']);
        $t->same([60.0, 200.0, 174.0, 218.0], $link['rect']);
        $t->same([60.0, 200.0, 174.0, 218.0], $link['visible_rect']);
        $t->same([50.0, 50.0, 250.0, 250.0], $link['page_bbox']);
        $t->same(true, $link['rect_inside_page_bbox']);
        $t->same(false, $link['rect_clipped_to_page']);
        $t->same('https://example.com/current-parent-link', $link['uri']);
        $t->same('Current parent link review', $link['contents']);

        $encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedLinks, 'stale-parent-link'));
        $t->same(false, str_contains($encodedLinks, 'Stale parent geometry review'));
        $t->same(false, str_contains($encodedLinks, '[0,0,40,40]'), 'The stale generation-zero parent box does not drive link visibility.');

        $pages = $extractor->applyLinksToPages($parentGenerationPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/current-parent-link', $spans[0]['link_uri']);
        $t->same([60.0, 200.0, 174.0, 218.0], $spans[0]['link_visible_page_rect']);
        $t->same(true, $spans[0]['link_page_rect_inside_page_bbox']);
        $t->true(!isset($spans[1]['link_uri']), 'A span only inside the stale parent generation CropBox is not linked.');
        $t->true(!isset($spans[1]['link_annotation_contents']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Current parent docs](https://example.com/current-parent-link) Stale parent decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current parent docs Stale parent decoy', $plainText);
        foreach ([
            'current-parent-link',
            'stale-parent-link',
            'Current parent link review',
            'Stale parent geometry review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
