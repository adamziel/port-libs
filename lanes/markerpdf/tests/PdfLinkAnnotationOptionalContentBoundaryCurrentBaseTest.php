<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationOptionalContentBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Visible layer Off layer Membership gated) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R 22 0 R] /D << /BaseState /OFF /ON [20 0 R 22 0 R] /OFF [21 0 R] /Order [20 0 R 21 0 R 22 0 R] >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /OC 20 0 R /Rect [72 700 164 718] /Contents (Visible optional-content link review) /A << /S /URI /URI (https://example.com/visible-layer-docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /OC 21 0 R /Rect [174 700 250 718] /Contents (Off optional-content link review) /A << /S /URI /URI (https://example.com/off-layer-docs) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /OC << /Type /OCMD /OCGs [20 0 R 21 0 R] /P /AllOn >> /Rect [260 700 386 718] /Contents (Membership optional-content link review) /A << /S /URI /URI (https://example.com/membership-gated-docs) >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /OCG /Name (Visible Import Links) >>\nendobj\n"
        . "21 0 obj\n<< /Type /OCG /Name (Hidden Review Links) >>\nendobj\n"
        . "22 0 obj\n<< /Type /OCG /Name (Unused Visible Layer) >>\nendobj\n"
        . "%%EOF";
};

$linkAnnotationOptionalContentBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 386.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 386.0, 718.0],
                'spans' => [
                    ['text' => 'Visible layer', 'bbox' => [72.0, 700.0, 164.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Off layer', 'bbox' => [174.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Membership gated', 'bbox' => [260.0, 700.0, 386.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'honors annotation optional-content visibility before WordPress Link span promotion' => static function (TestRunner $t) use (
        $linkAnnotationOptionalContentBoundaryPdf,
        $linkAnnotationOptionalContentBoundaryPages
    ): void {
        $pdf = $linkAnnotationOptionalContentBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same('Off optional-content link review', $annotations[0]['annotations'][1]['contents']);
        $t->same('Membership optional-content link review', $annotations[0]['annotations'][2]['contents']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'OFF OCG and AllOn OCMD links stay out of WordPress-visible link promotion.');
        $t->same('https://example.com/visible-layer-docs', $links[0]['links'][0]['uri']);
        $t->same(true, $links[0]['links'][0]['optional_content_visible']);
        $t->same('reference', $links[0]['links'][0]['optional_content_source']);
        $t->same([['object' => 20, 'generation' => 0, 'visible' => true]], $links[0]['links'][0]['optional_content_references']);

        $encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'off-layer-docs',
            'membership-gated-docs',
            'Off optional-content link review',
            'Membership optional-content link review',
            'Hidden Review Links',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedLinks, $reviewOnlyText));
        }

        $pages = $extractor->applyLinksToPages($linkAnnotationOptionalContentBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/visible-layer-docs', $spans[0]['link_uri']);
        $t->same(true, $spans[0]['link_optional_content_visible']);
        foreach ([1, 2] as $spanIndex) {
            $t->same(false, isset($spans[$spanIndex]['link_uri']));
            $t->same(false, isset($spans[$spanIndex]['link_optional_content_visible']));
        }

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Visible layer](https://example.com/visible-layer-docs) Off layer Membership gated', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'off-layer-docs'));
        $t->same(false, str_contains($blocks[0]['text'], 'membership-gated-docs'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Visible layer Off layer Membership gated', $plainText);
        foreach ([
            'Visible optional-content link review',
            'Off optional-content link review',
            'Membership optional-content link review',
            'visible-layer-docs',
            'off-layer-docs',
            'membership-gated-docs',
            'Visible Import Links',
            'Hidden Review Links',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
