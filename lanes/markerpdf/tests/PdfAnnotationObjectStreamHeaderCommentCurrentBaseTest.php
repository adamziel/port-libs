<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationObjectStreamHeaderCommentCurrentBasePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Commented object stream link Stale direct comment link) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');

    $compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 242 718] /Contents (Commented object-stream annotation) /T (Header comment reviewer) /NM (commented-object-stream-link) /A << /S /URI /URI (https://example.com/commented-object-stream-link) >> >>';
    $objectStreamHeader = "% ignored annotation reviewer digits 7 9999\r\n7 0 ";
    $objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
    $objectStream = gzcompress($objectStreamPayload);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress commented annotation object stream.');
    }
    $addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [258 700 420 718] /Contents (Stale direct header comment annotation) /T (Stale comment reviewer) /NM (stale-comment-link) /A << /S /URI /URI (https://stale.example.com/comment-header) >> >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if ($objectNumber === 7) {
            $rows .= $xrefRow(2, 20, 0);
            continue;
        }
        if ($objectNumber === 30) {
            $rows .= $xrefRow(1, $xrefOffset);
            continue;
        }
        if (isset($offsets[$objectNumber])) {
            $rows .= $xrefRow(1, $offsets[$objectNumber]);
            continue;
        }

        $rows .= $xrefRow(0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress commented annotation xref stream.');
    }

    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$annotationObjectStreamHeaderCommentCurrentBasePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 420.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 420.0, 718.0],
                'spans' => [
                    ['text' => 'Commented object stream link', 'bbox' => [72.0, 700.0, 242.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale direct comment link', 'bbox' => [258.0, 700.0, 420.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'parses annotation object-stream header comments before WordPress link promotion' => static function (
        TestRunner $t
    ) use ($annotationObjectStreamHeaderCommentCurrentBasePdf, $annotationObjectStreamHeaderCommentCurrentBasePages): void {
        $pdf = $annotationObjectStreamHeaderCommentCurrentBasePdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7], array_column($annotations[0]['annotations'], 'annotation_object'));
        $annotation = $annotations[0]['annotations'][0];
        $t->same('Link', $annotation['subtype']);
        $t->same([72.0, 700.0, 242.0, 718.0], $annotation['rect']);
        $t->same('Commented object-stream annotation', $annotation['contents']);
        $t->same('Header comment reviewer', $annotation['title']);
        $t->same('commented-object-stream-link', $annotation['name']);
        $t->same('https://example.com/commented-object-stream-link', $annotation['actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/commented-object-stream-link', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 242.0, 718.0], $links[0]['links'][0]['rect']);

        $pages = $linkExtractor->applyLinksToPages($annotationObjectStreamHeaderCommentCurrentBasePages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/commented-object-stream-link', $spans[0]['link_uri']);
        $t->same(7, $spans[0]['link_annotation_object']);
        $t->same('Commented object-stream annotation', $spans[0]['link_annotation_contents']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Commented object stream link](https://example.com/commented-object-stream-link) Stale direct comment link', $blocks[0]['text']);

        $encodedReview = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'stale.example.com',
            'Stale direct header comment annotation',
            'Stale comment reviewer',
            'stale-comment-link',
        ] as $staleReviewText) {
            $t->same(false, str_contains($encodedReview, $staleReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Commented object stream link Stale direct comment link', $plainText);
        foreach ([
            'commented-object-stream-link',
            'stale.example.com',
            'Commented object-stream annotation',
            'Stale direct header comment annotation',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
