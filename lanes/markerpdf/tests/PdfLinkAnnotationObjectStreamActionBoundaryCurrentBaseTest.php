<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkAnnotationObjectStreamActionBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Compressed action link Stale action decoy) Tj ET';

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

    $compressedObjects = [
        7 => '<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Contents (Compressed action review) /A 30 0 R /AA << /E 31 0 R >> /PA 32 0 R >>',
        30 => '<< /S /URI /URI (https://example.com/current-compressed-action) /Next << /S /JavaScript /JS (currentFollowupReview\(\)) >> >>',
        31 => '<< /S /URI /URI (mailto:compressed-action@example.test) >>',
        32 => '<< /S /URI /URI (https://archive.example.com/current-previous-action) >>',
    ];
    $headerParts = [];
    $payload = '';
    foreach ($compressedObjects as $objectNumber => $body) {
        $headerParts[] = (string) $objectNumber;
        $headerParts[] = (string) strlen($payload);
        $payload .= $body . "\n";
    }
    $objectStreamHeader = implode(' ', $headerParts) . ' ';
    $objectStreamPayload = $objectStreamHeader . $payload;
    $objectStream = gzcompress($objectStreamPayload);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress link-action object stream fixture.');
    }
    $addObject(20, '<< /Type /ObjStm /N ' . count($compressedObjects) . ' /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [236 700 360 718] /Contents (Stale direct annotation review) /A 30 0 R >>');
    $addObject(30, '<< /S /URI /URI (https://stale.example.com/direct-action) /Next 31 0 R >>');
    $addObject(31, '<< /S /Launch /F (stale-action-helper.exe) >>');
    $addObject(32, '<< /S /URI /URI (https://archive.example.com/stale-previous-action) >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if (array_key_exists($objectNumber, $compressedObjects)) {
            $rows .= $xrefRow(2, 20, array_search($objectNumber, array_keys($compressedObjects), true));
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
        throw new RuntimeException('Unable to compress link-action xref stream fixture.');
    }

    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$linkAnnotationObjectStreamActionBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'spans' => [
                    ['text' => 'Compressed action link', 'bbox' => [72.0, 700.0, 220.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale action decoy', 'bbox' => [236.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'uses xref-stream object-stream action dictionaries before stale direct Link action objects' => static function (
        TestRunner $t
    ) use ($linkAnnotationObjectStreamActionBoundaryPdf, $linkAnnotationObjectStreamActionBoundaryPages): void {
        $pdf = $linkAnnotationObjectStreamActionBoundaryPdf();
        $extractor = new PdfLinkAnnotationExtractor();

        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));

        $link = $links[0]['links'][0];
        $t->same('https://example.com/current-compressed-action', $link['uri']);
        $t->same('Compressed action review', $link['contents']);
        $t->same(['URI', 'JavaScript'], array_column($link['actions'], 'action_type'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($link['actions'], 'safety'));
        $t->same(['URI'], array_column($link['additional_actions'], 'action_type'));
        $t->same('mailto:compressed-action@example.test', $link['additional_actions'][0]['uri']);
        $t->same('https://archive.example.com/current-previous-action', $link['previous_uri_actions'][0]['uri']);

        $encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
        foreach ([
            'stale.example.com',
            'stale-action-helper.exe',
            'Stale direct annotation review',
            'stale-previous-action',
        ] as $staleReviewText) {
            $t->same(false, str_contains($encodedLinks, $staleReviewText));
        }

        $pages = $extractor->applyLinksToPages($linkAnnotationObjectStreamActionBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-compressed-action', $spans[0]['link_uri']);
        $t->same('mailto:compressed-action@example.test', $spans[0]['link_additional_actions_review'][0]['uri']);
        $t->same('https://archive.example.com/current-previous-action', $spans[0]['link_previous_uri_actions'][0]['uri']);
        $t->true(!isset($spans[1]['link_uri']));
        $t->true(!isset($spans[1]['link_annotation_object']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same('[Compressed action link](https://example.com/current-compressed-action) Stale action decoy', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Compressed action link Stale action decoy', $plainText);
        foreach ([
            'current-compressed-action',
            'compressed-action@example.test',
            'current-previous-action',
            'stale.example.com',
            'stale-action-helper.exe',
            'Compressed action review',
            'Stale direct annotation review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
