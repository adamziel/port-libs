<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkNameTreeLimitsBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current named jump Stale named jump Direct URI) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current named destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 184 718] /Contents (Current named destination review) /Dest (Current Link) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [194 700 300 718] /Contents (Stale named destination review) /Dest (Stale Link) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 386 718] /Contents (Direct URI review) /A << /S /URI /URI (https://example.com/direct-current) >> >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current Link) (Current Summary)] /Kids [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Current Link) (Current Summary)] /Names [(Current Link) [4 0 R /FitH 700] (Current Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [(Stale Link) (Stale Link)] /Names [(Stale Link) [4 0 R /FitH 111] (zz-stale-link) [3 0 R /Fit]] >>\nendobj\n"
        . "%%EOF";
};

$linkNameTreeLimitsBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 386.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 386.0, 718.0],
                'spans' => [
                    ['text' => 'Current named jump', 'bbox' => [72.0, 700.0, 184.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale named jump', 'bbox' => [194.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Direct URI', 'bbox' => [310.0, 700.0, 386.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'honors named-destination name-tree Limits before Link annotation span promotion' => static function (
        TestRunner $t
    ) use ($linkNameTreeLimitsBoundaryPdf, $linkNameTreeLimitsBoundaryPages): void {
        $pdf = $linkNameTreeLimitsBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            ),
            'The out-of-limits named destination remains annotation metadata without a resolved action.'
        );

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 9], array_column($links[0]['links'], 'annotation_object'));

        $currentJump = $links[0]['links'][0];
        $t->same('local-destination', $currentJump['safety']);
        $t->same('Current Link', $currentJump['destination']);
        $t->same(1, $currentJump['destination_page']);
        $t->same('FitH', $currentJump['view_mode']);
        $t->same(['top' => 700.0], $currentJump['view_parameters']);

        $uriLink = $links[0]['links'][1];
        $t->same('https://example.com/direct-current', $uriLink['uri']);

        $linkedPages = $extractor->applyLinksToPages($linkNameTreeLimitsBoundaryPages(), $pdf);
        $spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, $spans[0]['link_destination_page']);
        $t->same('Current Link', $spans[0]['link_destination']);
        $t->true(!isset($spans[0]['link_uri']));
        $t->true(!isset($spans[1]['link_destination_page']));
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->same('https://example.com/direct-current', $spans[2]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('Current named jump Stale named jump [Direct URI](https://example.com/direct-current)', $blocks[0]['text']);

        $encodedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Stale Link', 'zz-stale-link', 'Stale named destination review', 'FitH 111'] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedLinks, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current named jump Stale named jump Direct URI', $plainText);
        $t->contains('Current named destination body', $plainText);
        foreach (['Current Link', 'Stale Link', 'zz-stale-link', 'direct-current'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
