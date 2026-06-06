<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$linkAnnotationFreedActionBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current action docs Freed action decoy) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Contents (Current direct link review) /A << /S /URI /URI (https://example.com/current-action-docs) >> >>');
    $addObject(8, '<< /Type /Annot /Subtype /Link /Rect [202 700 332 718] /Contents (Freed action review) /A 20 0 R >>');
    $addObject(20, '<< /S /URI /URI (https://stale.example.com/freed-action) /Next << /S /JavaScript /JS (freedActionReview\(\)) >> >>');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 21\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 20; $objectNumber++) {
        if ($objectNumber === 20) {
            $pdf .= $xrefRow(0, 1, 'f');
            continue;
        }

        $pdf .= isset($offsets[$objectNumber])
            ? $xrefRow($offsets[$objectNumber])
            : $xrefRow(0, 0, 'f');
    }

    $pdf .= "trailer\n<< /Size 21 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$linkAnnotationFreedActionBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 332.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 332.0, 718.0],
                'spans' => [
                    ['text' => 'Current action docs', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Freed action decoy', 'bbox' => [202.0, 700.0, 332.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'suppresses xref-free indirect Link action objects before WordPress span promotion' => static function (TestRunner $t) use (
        $linkAnnotationFreedActionBoundaryPdf,
        $linkAnnotationFreedActionBoundaryPages
    ): void {
        $pdf = $linkAnnotationFreedActionBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $t->true(isset($freeObjects[20]), 'The xref table marks the indirect action object as free/currently unused.');

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7, 8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same(['review-uri'], array_column($annotations[0]['annotations'][0]['actions'], 'safety'));
        $t->same([], $annotations[0]['annotations'][1]['actions'], 'Freed indirect action objects must not become review actions.');
        $t->same(false, str_contains($encoded($annotations), 'stale.example.com'));
        $t->same(false, str_contains($encoded($annotations), 'freedActionReview'));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'), 'Only the current direct action is promoted.');
        $t->same('https://example.com/current-action-docs', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'stale.example.com'));
        $t->same(false, str_contains($encoded($links), 'freedActionReview'));

        $pages = $extractor->applyLinksToPages($linkAnnotationFreedActionBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-action-docs', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_actions_review']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Current action docs](https://example.com/current-action-docs) Freed action decoy', $blocks[0]['text']);
        $t->same(false, str_contains($blocks[0]['text'], 'stale.example.com'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current action docs Freed action decoy', $plainText);
        foreach ([
            'current-action-docs',
            'freed-action',
            'freedActionReview',
            'Current direct link review',
            'Freed action review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
