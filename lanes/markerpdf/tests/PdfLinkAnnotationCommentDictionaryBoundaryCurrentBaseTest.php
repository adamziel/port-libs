<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationCommentDictionaryBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Commented docs Comment decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot % fake dictionary close >> /Subtype /Text /Rect [250 700 340 718] /A << /S /URI /URI (https://example.com/comment-decoy-link) >>\n"
        . " /Subtype /Link /Rect [72 700 182 718] /Contents (Commented direct link review) /A 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [200 700 302 718] /F 2 /Contents (Hidden comment decoy review) /A << /S /URI /URI (https://example.com/hidden-comment-decoy) >> >>\nendobj\n"
        . "9 0 obj\n<< /S /URI % fake action close >> /URI (https://example.com/comment-decoy-action)\n"
        . " /URI (https://example.com/commented-link) >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationCommentDictionaryBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 302.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 302.0, 718.0],
                'spans' => [
                    ['text' => 'Commented docs', 'bbox' => [72.0, 700.0, 182.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Comment decoy', 'bbox' => [200.0, 700.0, 302.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'skips PDF comments inside Link annotation dictionaries before WordPress span promotion' => static function (TestRunner $t) use (
        $linkAnnotationCommentDictionaryBoundaryPdf,
        $linkAnnotationCommentDictionaryBoundaryPages
    ): void {
        $pdf = $linkAnnotationCommentDictionaryBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Link', 'Link'], array_column($annotations[0]['annotations'], 'subtype'));
        $t->same('Commented direct link review', $annotations[0]['annotations'][0]['contents']);
        $t->same('https://example.com/commented-link', $annotations[0]['annotations'][0]['actions'][0]['uri']);
        $t->same('review-uri', $annotations[0]['annotations'][0]['actions'][0]['safety']);
        $t->same(false, str_contains($encoded($annotations), 'comment-decoy-link'));
        $t->same(false, str_contains($encoded($annotations), 'comment-decoy-action'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'The hidden decoy remains review-only and the commented dictionary still promotes.');
        $t->same('https://example.com/commented-link', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 182.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same('Commented direct link review', $links[0]['links'][0]['contents']);
        $t->same(false, str_contains($encoded($links), 'hidden-comment-decoy'));
        $t->same(false, str_contains($encoded($links), 'comment-decoy-action'));

        $pages = $extractor->applyLinksToPages($linkAnnotationCommentDictionaryBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/commented-link', $spans[0]['link_uri']);
        $t->same(7, $spans[0]['link_annotation_object']);
        $t->same('Commented direct link review', $spans[0]['link_annotation_contents']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Commented docs](https://example.com/commented-link) Comment decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Commented docs Comment decoy', $plainText);
        foreach ([
            'commented-link',
            'comment-decoy-link',
            'comment-decoy-action',
            'hidden-comment-decoy',
            'Commented direct link review',
            'Hidden comment decoy review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
