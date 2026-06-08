<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$actionObjectStreamOffsetBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Safe action link Malformed action stream) Tj ET';

    $decoyPrefix = '<< /Type /Annot /Subtype /Text /Contents (literal action prefix ';
    $decoyAction = '<< /S /URI /URI (https://malicious.example.com/object-stream-action) >>';
    $decoySuffix = ' literal action suffix) >>';
    $decoyMember = $decoyPrefix . $decoyAction . $decoySuffix;
    $badOffset = strpos($decoyMember, $decoyAction);
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate object-stream action offset decoy.');
    }

    $objectStreamHeader = '12 0 8 ' . $badOffset . ' ';
    $objectStreamPayload = $objectStreamHeader . $decoyMember . "\n";
    $objectStream = gzcompress($objectStreamPayload);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress action offset-boundary object stream.');
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
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [6 0 R 7 0 R] /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $addObject(6, '<< /Type /Annot /Subtype /Link /Rect [72 700 204 718] /Contents (Safe action boundary annotation) /A << /S /URI /URI (https://example.com/safe-action-boundary) >> >>');
    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [216 700 430 718] /Contents (Malformed action reference annotation) /A 8 0 R >>');
    $addObject(20, '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, '<< /S /URI /URI (https://stale.example.com/object-stream-action) >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if ($objectNumber === 8) {
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
        throw new RuntimeException('Unable to compress action offset-boundary xref stream.');
    }

    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$actionObjectStreamOffsetBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 430.0, 718.0],
                'spans' => [
                    ['text' => 'Safe action link', 'bbox' => [72.0, 700.0, 204.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Malformed action stream', 'bbox' => [216.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects action object-stream member offsets inside literal strings before WordPress link promotion' => static function (
        TestRunner $t
    ) use ($actionObjectStreamOffsetBoundaryPdf, $actionObjectStreamOffsetBoundaryPages): void {
        $pdf = $actionObjectStreamOffsetBoundaryPdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();

        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([6], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/safe-action-boundary', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 204.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same('Safe action boundary annotation', $links[0]['links'][0]['contents']);

        $pages = $linkExtractor->applyLinksToPages($actionObjectStreamOffsetBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/safe-action-boundary', $spans[0]['link_uri']);
        $t->same(6, $spans[0]['link_annotation_object']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Safe action link](https://example.com/safe-action-boundary) Malformed action stream', $blocks[0]['text']);

        $encodedReview = json_encode([$links, $pages], JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'malicious.example.com',
            'stale.example.com',
            'Malformed action reference annotation',
            'object-stream-action',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($encodedReview, $reviewOnlyText));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Safe action link Malformed action stream', $plainText);
        foreach ([
            'safe-action-boundary',
            'malicious.example.com',
            'stale.example.com',
            'Malformed action reference annotation',
        ] as $hiddenText) {
            $t->same(false, str_contains($plainText, $hiddenText));
        }
    },
];
