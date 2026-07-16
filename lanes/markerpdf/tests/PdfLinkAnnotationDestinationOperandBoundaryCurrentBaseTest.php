<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkDestinationOperandBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Self indirect Other indirect Tailed coordinate Safe URI) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Indirect destination target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 11 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Contents (Self indirect destination review) /Dest [3 0 R /XYZ 20 0 R 21 0 R 22 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [184 700 300 718] /Contents (Other indirect destination review) /Dest [4 0 R /FitH 23 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 430 718] /Contents (Tailed coordinate destination review) /Dest [4 0 R /FitH 24 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [440 700 510 718] /Contents (Safe URI review) /A << /S /URI /URI (https://example.com/safe-link) >> >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "20 0 obj\n72\nendobj\n"
        . "21 0 obj\n720\nendobj\n"
        . "22 0 obj\nnull\nendobj\n"
        . "23 0 obj\n700\nendobj\n"
        . "24 0 obj\n640 /PrivateTail\nendobj\n"
        . "%%EOF";
};

$linkDestinationOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 510.0, 718.0],
                'spans' => [
                    ['text' => 'Self indirect', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Other indirect', 'bbox' => [184.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed coordinate', 'bbox' => [310.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Safe URI', 'bbox' => [440.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect destination coordinates but rejects scalar tailed coordinate operands before WordPress promotion' => static function (
        TestRunner $t
    ) use ($linkDestinationOperandBoundaryPdf, $linkDestinationOperandBoundaryPages): void {
        $pdf = $linkDestinationOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8, 9, 10], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(
            [['local-destination'], ['local-destination'], [], ['review-uri']],
            array_map(
                static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
                $annotations[0]['annotations']
            ),
            'A scalar indirect destination coordinate with a top-level tail is malformed and must not donate a local destination.'
        );

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7, 8, 10], array_column($links[0]['links'], 'annotation_object'));

        $self = $links[0]['links'][0];
        $t->same('local-destination', $self['safety']);
        $t->same(0, $self['destination_page']);
        $t->same('XYZ', $self['view_mode']);
        $t->same([72.0, 720.0, null], $self['view_position']);
        $t->same(['left' => 72.0, 'top' => 720.0, 'zoom' => null], $self['view_parameters']);

        $other = $links[0]['links'][1];
        $t->same('local-destination', $other['safety']);
        $t->same(1, $other['destination_page']);
        $t->same('FitH', $other['view_mode']);
        $t->same(['top' => 700.0], $other['view_parameters']);
        $t->same('https://example.com/safe-link', $links[0]['links'][2]['uri']);

        $pages = $extractor->applyLinksToPages($linkDestinationOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(0, $spans[0]['link_destination_page']);
        $t->same('XYZ', $spans[0]['link_view_mode']);
        $t->same(['left' => 72.0, 'top' => 720.0, 'zoom' => null], $spans[0]['link_view_parameters']);
        $t->same(1, $spans[1]['link_destination_page']);
        $t->same('FitH', $spans[1]['link_view_mode']);
        $t->true(!isset($spans[2]['link_destination_page']));
        $t->true(!isset($spans[2]['link_actions_review']));
        $t->same('https://example.com/safe-link', $spans[3]['link_uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('Self indirect Other indirect Tailed coordinate [Safe URI](https://example.com/safe-link)', $blocks[0]['text']);

        $encodedPromotedRows = $encoded([$links, $pages]);
        foreach (['PrivateTail', 'Tailed coordinate destination review'] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedPromotedRows, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Self indirect Other indirect Tailed coordinate Safe URI', $plainText);
        $t->contains('Indirect destination target body', $plainText);
        foreach ([
            'Self indirect destination review',
            'Other indirect destination review',
            'Tailed coordinate destination review',
            'Safe URI review',
            'safe-link',
            'PrivateTail',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
