<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkQuadPointsTailedOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Direct rect Indirect rect Valid quad Direct decoy Indirect decoy) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /QuadPoints [350 718 450 718 350 700 450 700] 20 0 R /Contents (Direct tailed quad review) /A << /S /URI /URI (https://example.com/direct-rect) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /QuadPoints 21 0 R /Contents (Indirect tailed quad review) /A << /S /URI /URI (https://example.com/indirect-rect) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 680 560 718] /QuadPoints [260 718 340 718 260 700 340 700] /Contents (Valid quad review) /A << /S /URI /URI (https://example.com/valid-quad) >> >>\nendobj\n"
        . "20 0 obj\n<< /S /JavaScript /JS (directQuadTailReview\\(\\)) >>\nendobj\n"
        . "21 0 obj\n[460 718 560 718 460 700 560 700] 22 0 R\nendobj\n"
        . "22 0 obj\n<< /S /JavaScript /JS (indirectQuadTailReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

$linkQuadPointsTailedOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 560.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 560.0, 718.0],
                'spans' => [
                    ['text' => 'Direct rect', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect rect', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Valid quad', 'bbox' => [260.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct decoy', 'bbox' => [350.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect decoy', 'bbox' => [460.0, 700.0, 560.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed Link QuadPoints operands before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkQuadPointsTailedOperandBoundaryPdf, $linkQuadPointsTailedOperandBoundaryPages): void {
        $pdf = $linkQuadPointsTailedOperandBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same([7, 8, 9], array_column($links[0]['links'], 'annotation_object'));

        $linksByObject = [];
        foreach ($links[0]['links'] as $link) {
            $linksByObject[$link['annotation_object']] = $link;
        }

        $t->same(false, array_key_exists('quad_points', $linksByObject[7]), 'A direct tailed /QuadPoints array must not donate clickable geometry.');
        $t->same(false, array_key_exists('quad_points', $linksByObject[8]), 'An indirect tailed /QuadPoints helper must not donate clickable geometry.');
        $t->same([[260.0, 718.0, 340.0, 718.0, 260.0, 700.0, 340.0, 700.0]], $linksByObject[9]['quad_points']);
        $t->same([72.0, 700.0, 150.0, 718.0], $linksByObject[7]['rect']);
        $t->same([160.0, 700.0, 250.0, 718.0], $linksByObject[8]['rect']);
        $t->same('https://example.com/direct-rect', $linksByObject[7]['uri']);
        $t->same('https://example.com/indirect-rect', $linksByObject[8]['uri']);
        $t->same('https://example.com/valid-quad', $linksByObject[9]['uri']);

        $pages = $extractor->applyLinksToPages($linkQuadPointsTailedOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/direct-rect', $spans[0]['link_uri']);
        $t->same('https://example.com/indirect-rect', $spans[1]['link_uri']);
        $t->same('https://example.com/valid-quad', $spans[2]['link_uri']);
        $t->same(0, $spans[2]['link_quad_index']);
        $t->true(!isset($spans[3]['link_uri']), 'The direct tailed QuadPoints decoy span must remain unlinked.');
        $t->true(!isset($spans[4]['link_uri']), 'The indirect tailed QuadPoints decoy span must remain unlinked.');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Direct rect](https://example.com/direct-rect) [Indirect rect](https://example.com/indirect-rect) [Valid quad](https://example.com/valid-quad) Direct decoy Indirect decoy',
            $blocks[0]['text']
        );

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'directQuadTailReview',
            'indirectQuadTailReview',
        ] as $tailPayload) {
            $t->same(false, str_contains($encodedReview, $tailPayload));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Direct rect Indirect rect Valid quad Direct decoy Indirect decoy', $plainText);
        foreach ([
            'direct-rect',
            'indirect-rect',
            'valid-quad',
            'Direct tailed quad review',
            'Indirect tailed quad review',
            'Valid quad review',
            'directQuadTailReview',
            'indirectQuadTailReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
