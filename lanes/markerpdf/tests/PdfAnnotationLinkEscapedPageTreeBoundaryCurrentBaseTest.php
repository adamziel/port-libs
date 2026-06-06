<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkEscapedPageTreeBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Escaped tree docs Escaped tree highlight Fallback stale) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale fallback page body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /T#79pe /#43atalog /P#61ges 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /T#79pe /P#61ges /K#69ds [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /T#79pe /P#61ge /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Contents (Escaped page-tree link review) /A << /S /URI /URI (https://example.com/escaped-page-tree-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [184 700 328 718] /QuadPoints [184 718 328 718 184 700 328 700] /Contents (Escaped page-tree highlight review) /T (Import QA) /C [0.7 0.85 1] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [338 700 426 718] /Contents (Escaped page-tree sticky review) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 12 0 R /Annots [11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Contents (Stale fallback link review) /A << /S /URI /URI (https://example.com/stale-fallback-link) >> >>\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$annotationLinkEscapedPageTreeBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 426.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 426.0, 718.0],
                'spans' => [
                    ['text' => 'Escaped tree docs', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Escaped tree highlight', 'bbox' => [184.0, 700.0, 328.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Fallback stale', 'bbox' => [338.0, 700.0, 426.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'decodes escaped page tree names before promoting annotation links and markups' => static function (
        TestRunner $t
    ) use ($annotationLinkEscapedPageTreeBoundaryPdf, $annotationLinkEscapedPageTreeBoundaryPages): void {
        $pdf = $annotationLinkEscapedPageTreeBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same(0, $annotations[0]['pnum']);
        $t->same(3, $annotations[0]['page_object']);
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Highlight', 'Text'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same(false, str_contains($encoded($annotations), 'stale-fallback-link'));
        $t->same(false, str_contains($encoded($annotations), 'Stale fallback link review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/escaped-page-tree-link', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'stale-fallback-link'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same(0, $markups[0]['pnum']);
        $t->same(3, $markups[0]['page_object']);
        $t->same([8], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Escaped page-tree highlight review', $markups[0]['markups'][0]['contents']);

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkEscapedPageTreeBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/escaped-page-tree-link', $spans[0]['link_uri']);
        $t->same('Escaped page-tree highlight review', $spans[1]['review_annotations'][0]['contents']);
        $t->true(!isset($spans[2]['link_uri']));
        $t->true(!isset($spans[2]['review_annotations']));
        $t->same(false, str_contains($encoded($reviewPages), 'stale-fallback-link'));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Escaped tree docs](https://example.com/escaped-page-tree-link) Escaped tree highlight Fallback stale', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Escaped tree docs Escaped tree highlight Fallback stale', $plainText);
        foreach ([
            'escaped-page-tree-link',
            'stale-fallback-link',
            'Escaped page-tree link review',
            'Escaped page-tree highlight review',
            'Escaped page-tree sticky review',
            'Stale fallback link review',
            'Stale fallback page body',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
