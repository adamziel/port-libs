<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkTailedReferenceBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean link Tailed decoy Direct link) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 11 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 152 718] /Contents (Clean link review) /A << /S /URI /URI (https://example.com/clean-link) >> >>\nendobj\n"
        . "11 0 obj\n8 0 R 9 0 R\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [164 700 254 718] /Contents (Tailed first decoy review) /A << /S /URI /URI (https://example.com/tailed-first-decoy) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [164 700 254 718] /Contents (Tailed second decoy review) /A << /S /URI /URI (https://example.com/tailed-second-decoy) >> >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [266 700 348 718] /Contents (Direct link review) /A << /S /URI /URI (https://example.com/direct-link) >> >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkTailedReferenceBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 348.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 348.0, 718.0],
                'spans' => [
                    ['text' => 'Clean link', 'bbox' => [72.0, 700.0, 152.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed decoy', 'bbox' => [164.0, 700.0, 254.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct link', 'bbox' => [266.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects Annots references whose indirect object body has trailing top-level operands before link promotion' => static function (TestRunner $t) use (
        $annotationLinkTailedReferenceBoundaryPdf,
        $annotationLinkTailedReferenceBoundaryPages
    ): void {
        $pdf = $annotationLinkTailedReferenceBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 12], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Clean link review', 'Direct link review'], array_column($annotations[0]['annotations'], 'contents'));
        $t->same(false, str_contains($encoded($annotations), 'Tailed first decoy review'));
        $t->same(false, str_contains($encoded($annotations), 'Tailed second decoy review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 12], array_column($links[0]['links'], 'annotation_object'));
        $t->same([
            'https://example.com/clean-link',
            'https://example.com/direct-link',
        ], array_column($links[0]['links'], 'uri'));
        $t->same(false, str_contains($encoded($links), 'tailed-first-decoy'));
        $t->same(false, str_contains($encoded($links), 'tailed-second-decoy'));

        $pages = $linkExtractor->applyLinksToPages($annotationLinkTailedReferenceBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-link', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->same('https://example.com/direct-link', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Clean link](https://example.com/clean-link) Tailed decoy [Direct link](https://example.com/direct-link)',
            $blocks[0]['text']
        );
        $t->same(false, str_contains($blocks[0]['text'], 'tailed-first-decoy'));
        $t->same(false, str_contains($blocks[0]['text'], 'tailed-second-decoy'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean link Tailed decoy Direct link', $plainText);
        foreach ([
            'Clean link review',
            'Direct link review',
            'Tailed first decoy review',
            'Tailed second decoy review',
            'tailed-first-decoy',
            'tailed-second-decoy',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
