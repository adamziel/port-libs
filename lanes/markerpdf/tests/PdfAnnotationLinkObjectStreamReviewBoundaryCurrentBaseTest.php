<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationLinkObjectStreamReviewBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Compressed review link Stale direct review) Tj ET';

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

    $compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 222 718] /Contents (Compressed review annotation) /T (Current reviewer) /NM (compressed-review-link) /A << /S /URI /URI (https://example.com/current-compressed-review) >> >>';
    $objectStreamHeader = '7 0 ';
    $objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
    $objectStream = gzcompress($objectStreamPayload);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress annotation review object-stream fixture.');
    }
    $addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [238 700 380 718] /Contents (Stale direct annotation review) /T (Stale reviewer) /NM (stale-direct-review-link) /A << /S /URI /URI (https://stale.example.com/direct-review) >> >>');

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
        throw new RuntimeException('Unable to compress annotation review xref stream.');
    }

    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$annotationLinkObjectStreamReviewBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 380.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 380.0, 718.0],
                'spans' => [
                    ['text' => 'Compressed review link', 'bbox' => [72.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale direct review', 'bbox' => [238.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

$annotationLinkObjectStreamIndirectOperandsPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect object-stream link Stale indirect link) Tj ET';

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

    $compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 240 718] /Contents (Indirect object-stream annotation) /T (Indirect reviewer) /NM (indirect-object-stream-link) /A << /S /URI /URI (https://example.com/indirect-object-stream-link) >> >>';
    $objectStreamHeader = '7 0 ';
    $objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
    $objectStream = gzcompress($objectStreamPayload);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress indirect operand annotation object-stream fixture.');
    }
    $addObject(20, '<< /Type /ObjStm /N 32 0 R /First 33 0 R /Filter 31 0 R /Length 30 0 R >>' . "\nstream\n{$objectStream}\nendstream");

    $addObject(30, (string) strlen($objectStream));
    $addObject(31, '/FlateDecode');
    $addObject(32, '1');
    $addObject(33, (string) strlen($objectStreamHeader));
    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [246 700 374 718] /Contents (Stale direct indirect annotation) /T (Stale indirect reviewer) /NM (stale-indirect-link) /A << /S /URI /URI (https://stale.example.com/indirect-object-stream-link) >> >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if ($objectNumber === 7) {
            $rows .= $xrefRow(2, 20, 0);
            continue;
        }
        if ($objectNumber === 40) {
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
        throw new RuntimeException('Unable to compress indirect operand annotation xref stream.');
    }

    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$annotationLinkObjectStreamIndirectOperandsPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 374.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 374.0, 718.0],
                'spans' => [
                    ['text' => 'Indirect object-stream link', 'bbox' => [72.0, 700.0, 240.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale indirect link', 'bbox' => [246.0, 700.0, 374.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

$annotationLinkObjectStreamOffsetBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Safe annotation link Malformed object-stream link) Tj ET';

    $decoyPrefix = '<< /Type /Annot /Subtype /Text /Contents (literal prefix ';
    $decoyAnnotation = '<< /Type /Annot /Subtype /Link /Rect [210 700 410 718] /Contents (Malformed object-stream annotation) /T (Offset decoy reviewer) /NM (offset-decoy-link) /A << /S /URI /URI (https://malicious.example.com/object-stream-offset) >> >>';
    $decoySuffix = ' literal suffix) >>';
    $decoyMember = $decoyPrefix . $decoyAnnotation . $decoySuffix;
    $badOffset = strpos($decoyMember, $decoyAnnotation);
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate object-stream annotation offset decoy.');
    }

    $objectStreamHeader = '12 0 7 ' . $badOffset . ' ';
    $objectStreamPayload = $objectStreamHeader . $decoyMember . "\n";
    $objectStream = gzcompress($objectStreamPayload);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress annotation offset-boundary object stream.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [8 0 R 7 0 R] /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $addObject(8, '<< /Type /Annot /Subtype /Link /Rect [72 700 205 718] /Contents (Safe compressed-boundary annotation) /T (Safe reviewer) /NM (safe-link) /A << /S /URI /URI (https://example.com/safe-annotation-link) >> >>');
    $addObject(20, '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [210 700 410 718] /Contents (Stale direct offset annotation) /A << /S /URI /URI (https://stale.example.com/object-stream-offset) >> >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if ($objectNumber === 7) {
            $rows .= $xrefRow(2, 20, 1);
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
        throw new RuntimeException('Unable to compress annotation offset-boundary xref stream.');
    }

    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$annotationLinkObjectStreamOffsetBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 410.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 410.0, 718.0],
                'spans' => [
                    ['text' => 'Safe annotation link', 'bbox' => [72.0, 700.0, 205.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Malformed object-stream link', 'bbox' => [210.0, 700.0, 410.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses xref-stream object-stream Link annotation bodies for annotation review before stale direct bodies' => static function (
        TestRunner $t
    ) use ($annotationLinkObjectStreamReviewBoundaryPdf, $annotationLinkObjectStreamReviewBoundaryPages): void {
        $pdf = $annotationLinkObjectStreamReviewBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7], array_column($annotations[0]['annotations'], 'annotation_object'));
        $annotation = $annotations[0]['annotations'][0];
        $t->same('Link', $annotation['subtype']);
        $t->same([72.0, 700.0, 222.0, 718.0], $annotation['rect']);
        $t->same('Compressed review annotation', $annotation['contents']);
        $t->same('Current reviewer', $annotation['title']);
        $t->same('compressed-review-link', $annotation['name']);
        $t->same(['URI'], array_column($annotation['actions'], 'action_type'));
        $t->same('https://example.com/current-compressed-review', $annotation['actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/current-compressed-review', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 222.0, 718.0], $links[0]['links'][0]['rect']);

        $pages = $linkExtractor->applyLinksToPages($annotationLinkObjectStreamReviewBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-compressed-review', $spans[0]['link_uri']);
        $t->same('Compressed review annotation', $spans[0]['link_annotation_contents']);
        $t->true(!isset($spans[1]['link_uri']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Compressed review link](https://example.com/current-compressed-review) Stale direct review', $blocks[0]['text']);

        $encodedReview = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'stale.example.com',
            'Stale direct annotation review',
            'Stale reviewer',
            'stale-direct-review-link',
        ] as $staleReviewText) {
            $t->same(false, str_contains($encodedReview, $staleReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Compressed review link Stale direct review', $plainText);
        foreach ([
            'current-compressed-review',
            'stale.example.com',
            'Compressed review annotation',
            'Stale direct annotation review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },

    'resolves indirect object-stream decode operands before WordPress link annotation review' => static function (
        TestRunner $t
    ) use ($annotationLinkObjectStreamIndirectOperandsPdf, $annotationLinkObjectStreamIndirectOperandsPages): void {
        $pdf = $annotationLinkObjectStreamIndirectOperandsPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7], array_column($annotations[0]['annotations'], 'annotation_object'));
        $annotation = $annotations[0]['annotations'][0];
        $t->same('Link', $annotation['subtype']);
        $t->same([72.0, 700.0, 240.0, 718.0], $annotation['rect']);
        $t->same('Indirect object-stream annotation', $annotation['contents']);
        $t->same('Indirect reviewer', $annotation['title']);
        $t->same('indirect-object-stream-link', $annotation['name']);
        $t->same(['URI'], array_column($annotation['actions'], 'action_type'));
        $t->same('https://example.com/indirect-object-stream-link', $annotation['actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/indirect-object-stream-link', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 240.0, 718.0], $links[0]['links'][0]['rect']);

        $pages = $linkExtractor->applyLinksToPages($annotationLinkObjectStreamIndirectOperandsPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/indirect-object-stream-link', $spans[0]['link_uri']);
        $t->same(7, $spans[0]['link_annotation_object']);
        $t->same('Indirect object-stream annotation', $spans[0]['link_annotation_contents']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Indirect object-stream link](https://example.com/indirect-object-stream-link) Stale indirect link', $blocks[0]['text']);

        $encodedReview = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'stale.example.com',
            'Stale direct indirect annotation',
            'Stale indirect reviewer',
            'stale-indirect-link',
        ] as $staleReviewText) {
            $t->same(false, str_contains($encodedReview, $staleReviewText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Indirect object-stream link Stale indirect link', $plainText);
        foreach ([
            'indirect-object-stream-link',
            'stale.example.com',
            'Indirect object-stream annotation',
            'Stale direct indirect annotation',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },

    'rejects annotation object-stream member offsets inside literal strings before WordPress link promotion' => static function (
        TestRunner $t
    ) use ($annotationLinkObjectStreamOffsetBoundaryPdf, $annotationLinkObjectStreamOffsetBoundaryPages): void {
        $pdf = $annotationLinkObjectStreamOffsetBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([8], array_column($annotations[0]['annotations'], 'annotation_object'));
        $t->same('Safe compressed-boundary annotation', $annotations[0]['annotations'][0]['contents']);
        $t->same('Safe reviewer', $annotations[0]['annotations'][0]['title']);
        $t->same('https://example.com/safe-annotation-link', $annotations[0]['annotations'][0]['actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([8], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/safe-annotation-link', $links[0]['links'][0]['uri']);
        $t->same('Safe compressed-boundary annotation', $links[0]['links'][0]['contents']);

        $pages = $linkExtractor->applyLinksToPages($annotationLinkObjectStreamOffsetBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/safe-annotation-link', $spans[0]['link_uri']);
        $t->same(8, $spans[0]['link_annotation_object']);
        $t->same('Safe compressed-boundary annotation', $spans[0]['link_annotation_contents']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Safe annotation link](https://example.com/safe-annotation-link) Malformed object-stream link', $blocks[0]['text']);

        $encodedReview = json_encode([$annotations, $links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'malicious.example.com',
            'stale.example.com',
            'Malformed object-stream annotation',
            'Stale direct offset annotation',
            'Offset decoy reviewer',
            'offset-decoy-link',
        ] as $hidden) {
            $t->same(false, str_contains($encodedReview, $hidden));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Safe annotation link Malformed object-stream link', $plainText);
        foreach ([
            'malicious.example.com',
            'stale.example.com',
            'Safe compressed-boundary annotation',
            'Malformed object-stream annotation',
            'Stale direct offset annotation',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
