<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkPageReferenceOperandBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Valid link Bad P link Valid highlight Bad P highlight) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Other page text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 11 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 11 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [72 700 150 718] /Contents (Valid P link review) /A << /S /URI /URI (https://example.com/valid-p-link) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /P 3 0 R 4 0 R /Rect [160 700 250 718] /Contents (Malformed P link review) /A << /S /URI /URI (https://malicious.example.com/bad-p-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 0 R /Rect [260 700 360 718] /QuadPoints [260 718 360 718 260 700 360 700] /Contents (Valid P highlight review) /T (Import QA) /C [0.1 0.7 0.2] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /P 3 0 R 4 0 R /Rect [370 700 490 718] /QuadPoints [370 718 490 718 370 700 490 700] /Contents (Malformed P highlight review) /T (Bad QA) /C [1 0 0] >>\nendobj\n"
        . "11 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$annotationLinkPageReferenceOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 490.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 490.0, 718.0],
                'spans' => [
                    ['text' => 'Valid link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bad P link', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Valid highlight', 'bbox' => [260.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Bad P highlight', 'bbox' => [370.0, 700.0, 490.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects link and markup annotations whose page P reference has trailing operands before WordPress promotion' => static function (TestRunner $t) use (
        $annotationLinkPageReferenceOperandBoundaryPdf,
        $annotationLinkPageReferenceOperandBoundaryPages
    ): void {
        $pdf = $annotationLinkPageReferenceOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 9], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['Valid P link review', 'Valid P highlight review'], array_column($annotations[0]['annotations'], 'contents'));
        $t->same(false, str_contains($encoded($annotations), 'Malformed P link review'));
        $t->same(false, str_contains($encoded($annotations), 'Malformed P highlight review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/valid-p-link', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'bad-p-link'));
        $t->same(false, str_contains($encoded($links), 'Malformed P link review'));

        $markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
        $t->same(1, count($markups));
        $t->same([9], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same('Valid P highlight review', $markups[0]['markups'][0]['contents']);
        $t->same(false, str_contains($encoded($markups), 'Malformed P highlight review'));

        $linkedPages = $linkExtractor->applyLinksToPages($annotationLinkPageReferenceOperandBoundaryPages(), $pdf);
        $reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/valid-p-link', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->same('Valid P highlight review', $spans[2]['review_annotations'][0]['contents']);
        $t->true(!isset($spans[3]['review_annotations']));
        $t->same(false, str_contains($encoded($reviewPages), 'malicious.example.com'));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Valid link](https://example.com/valid-p-link) Bad P link Valid highlight Bad P highlight', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Valid link Bad P link Valid highlight Bad P highlight', $plainText);
        foreach ([
            'Valid P link review',
            'Malformed P link review',
            'Valid P highlight review',
            'Malformed P highlight review',
            'malicious.example.com',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
