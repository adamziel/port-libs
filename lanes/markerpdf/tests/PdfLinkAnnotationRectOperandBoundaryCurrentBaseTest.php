<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkRectOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean rect Null rect Name rect Ref rect) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean rect review) /A << /S /URI /URI (https://example.com/clean-rect) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 null 250 718] /Contents (Null rect review) /A << /S /URI /URI (https://example.com/null-rect-decoy) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 /BadCoordinate 350 718] /Contents (Name rect review) /A << /S /URI /URI (https://example.com/name-rect-decoy) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 20 0 R 450 718] /Contents (Reference rect review) /A << /S /URI /URI (https://example.com/ref-rect-decoy) >> >>\nendobj\n"
        . "20 0 obj\n(not a numeric coordinate)\nendobj\n"
        . "%%EOF";
};

$linkRectOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 450.0, 718.0],
                'spans' => [
                    ['text' => 'Clean rect', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Null rect', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Name rect', 'bbox' => [260.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Ref rect', 'bbox' => [360.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects malformed Link annotation Rect operands before WordPress span promotion' => static function (
        TestRunner $t
    ) use ($linkRectOperandBoundaryPdf, $linkRectOperandBoundaryPages): void {
        $pdf = $linkRectOperandBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9, 10], array_column($annotationRows, 'annotation_object'));

        $annotationsByObject = [];
        foreach ($annotationRows as $row) {
            $annotationsByObject[$row['annotation_object']] = $row;
        }

        $t->same([72.0, 700.0, 150.0, 718.0], $annotationsByObject[7]['rect']);
        foreach ([8, 9, 10] as $malformedObject) {
            $t->same(
                null,
                $annotationsByObject[$malformedObject]['rect'],
                'Malformed /Rect operands remain annotation review metadata instead of donating shifted coordinates.'
            );
        }

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same([72.0, 700.0, 150.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same('https://example.com/clean-rect', $links[0]['links'][0]['uri']);

        $pages = $extractor->applyLinksToPages($linkRectOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-rect', $spans[0]['link_uri']);
        foreach ([1, 2, 3] as $spanIndex) {
            $t->true(!isset($spans[$spanIndex]['link_uri']), 'Malformed /Rect operands must not promote a WordPress link.');
        }

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Clean rect](https://example.com/clean-rect) Null rect Name rect Ref rect', $blocks[0]['text']);

        $encodedPages = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'null-rect-decoy',
            'name-rect-decoy',
            'ref-rect-decoy',
            'Null rect review',
            'Name rect review',
            'Reference rect review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedPages, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Clean rect Null rect Name rect Ref rect', $plainText);
        foreach ([
            'clean-rect',
            'null-rect-decoy',
            'name-rect-decoy',
            'ref-rect-decoy',
            'Clean rect review',
            'Null rect review',
            'Name rect review',
            'Reference rect review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
