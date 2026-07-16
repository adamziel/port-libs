<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationEscapedDictionaryBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Escaped link Hidden stale) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [8 0 R 7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Sub#74ype /Link /Re#63t [72 700 158 718] /#41 << /S /URI /URI (https://example.com/escaped-annotation-keys) >> /#46 4 >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Sub#74ype /Link /Re#63t [168 700 250 718] /#41 << /S /URI /URI (https://example.com/private-hidden-stale) >> /#46 2 >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationEscapedDictionaryBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'spans' => [
                    ['text' => 'Escaped link', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden stale', 'bbox' => [168.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'decodes escaped Link annotation keys before WordPress span promotion' => static function (TestRunner $t) use (
        $linkAnnotationEscapedDictionaryBoundaryPdf,
        $linkAnnotationEscapedDictionaryBoundaryPages
    ): void {
        $pdf = $linkAnnotationEscapedDictionaryBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();

        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(1, count($links[0]['links']));

        $link = $links[0]['links'][0];
        $t->same(7, $link['annotation_object']);
        $t->same('Link', $link['annotation_subtype']);
        $t->same([72.0, 700.0, 158.0, 718.0], $link['rect']);
        $t->same('https://example.com/escaped-annotation-keys', $link['uri']);
        $t->same('review-uri', $link['safety']);
        $t->same(false, $link['executes_on_import']);
        $t->same(false, str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'private-hidden-stale'));

        $pages = $extractor->applyLinksToPages($linkAnnotationEscapedDictionaryBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/escaped-annotation-keys', $spans[0]['link_uri']);
        $t->same([72.0, 700.0, 158.0, 718.0], $spans[0]['link_rect']);
        $t->same(7, $spans[0]['link_annotation_object']);
        $t->same(false, isset($spans[1]['link_uri']));
        $t->same(false, str_contains(json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '', 'private-hidden-stale'));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Escaped link](https://example.com/escaped-annotation-keys) Hidden stale', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Escaped link Hidden stale', $plainText);
        $t->same(false, str_contains($plainText, 'escaped-annotation-keys'));
        $t->same(false, str_contains($plainText, 'private-hidden-stale'));
    },
];
