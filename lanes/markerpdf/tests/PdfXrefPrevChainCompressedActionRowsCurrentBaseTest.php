<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

/**
 * @return array{bytes: string, current_uri: string, current_additional_uri: string, stale_uri: string, stale_javascript: string}
 */
function markerpdf_xref_prev_chain_compressed_action_rows_fixture_current_base(): array
{
    $previousObjects = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 320 240] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>",
        4 => "<< /Length 68 >>\nstream\nBT /F1 12 Tf 48 190 Td (Previous compressed action docs) Tj ET\nendstream",
        5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        7 => "<< /Type /Annot /Subtype /Link /Rect [48 180 210 202] /A 8 0 R /AA << /E 9 0 R >> >>",
        8 => "<< /S /URI /URI (https://example.com/stale-prev-action) >>",
        9 => "<< /S /JavaScript /JS (app.alert('stale-prev-action')) >>",
    ];

    $pdf = "%PDF-1.7\n";
    $previousOffsets = [];

    foreach ($previousObjects as $objectNumber => $body) {
        $previousOffsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    }

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 10\n";
    $pdf .= "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= 9; $objectNumber++) {
        $pdf .= isset($previousOffsets[$objectNumber])
            ? sprintf("%010d 00000 n \n", $previousOffsets[$objectNumber])
            : "0000000000 00000 f \n";
    }
    $pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentUri = 'https://example.com/current-compressed-prev-action';
    $currentAdditionalUri = 'mailto:current-compressed-prev-action@example.test';
    $compressedMembers = [
        8 => "<< /S /URI /URI ({$currentUri}) >>",
        9 => "<< /S /URI /URI ({$currentAdditionalUri}) >>",
    ];
    $objectStreamHeader = '';
    $objectStreamPayload = '';
    foreach ($compressedMembers as $objectNumber => $body) {
        $objectStreamHeader .= "{$objectNumber} " . strlen($objectStreamPayload) . ' ';
        $objectStreamPayload .= $body . "\n";
    }
    $objectStreamHeaderLength = strlen($objectStreamHeader);
    $objectStreamBytes = $objectStreamHeader . $objectStreamPayload;
    $objectStreamLength = strlen($objectStreamBytes);

    $currentObjects = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 320 240] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>",
        4 => "<< /Length 67 >>\nstream\nBT /F1 12 Tf 48 190 Td (Current compressed action docs) Tj ET\nendstream",
        5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        7 => "<< /Type /Annot /Subtype /Link /Rect [48 180 210 202] /Contents (Current compressed action docs) /A 8 0 R /AA << /E 9 0 R >> >>",
        20 => "<< /Type /ObjStm /N 2 /First {$objectStreamHeaderLength} /Length {$objectStreamLength} >>\nstream\n{$objectStreamBytes}\nendstream",
    ];

    $currentOffsets = [];
    foreach ($currentObjects as $objectNumber => $body) {
        $currentOffsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    }

    $xrefStreamRows = '';
    foreach ([1, 2, 3, 4, 5, 7, 20] as $objectNumber) {
        $xrefStreamRows .= chr(1) . pack('N', $currentOffsets[$objectNumber]) . chr(0);
    }

    $xrefStreamOffset = strlen($pdf);
    $xrefStreamLength = strlen($xrefStreamRows);
    $pdf .= "21 0 obj\n";
    $pdf .= "<< /Type /XRef /Size 21 /Root 1 0 R /Prev {$previousXrefOffset} /W [1 4 1] /Index [1 5 7 1 20 1] /Length {$xrefStreamLength} >>\n";
    $pdf .= "stream\n{$xrefStreamRows}\nendstream\nendobj\n";
    $pdf .= "startxref\n{$xrefStreamOffset}\n%%EOF\n";

    return [
        'bytes' => $pdf,
        'current_uri' => $currentUri,
        'current_additional_uri' => $currentAdditionalUri,
        'stale_uri' => 'https://example.com/stale-prev-action',
        'stale_javascript' => "app.alert('stale-prev-action')",
    ];
}

return [
    'prefers current compressed action objects omitted from latest xref rows over stale Prev rows' => static function (
        TestRunner $t
    ): void {
        $fixture = markerpdf_xref_prev_chain_compressed_action_rows_fixture_current_base();

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($fixture['bytes']);
        $t->same(1, count($annotationPages));
        $t->same(1, count($annotationPages[0]['annotations']));

        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(7, $annotation['annotation_object']);
        $t->same(1, count($annotation['actions']));
        $t->same('URI', $annotation['actions'][0]['action_type'] ?? null);
        $t->same($fixture['current_uri'], $annotation['actions'][0]['uri'] ?? null);
        $t->same(1, count($annotation['additional_actions']));
        $t->same('E', $annotation['additional_actions'][0]['event'] ?? null);
        $t->same('URI', $annotation['additional_actions'][0]['action_type'] ?? null);
        $t->same($fixture['current_additional_uri'], $annotation['additional_actions'][0]['uri'] ?? null);

        $annotationJson = json_encode($annotationPages, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($annotationJson, $fixture['stale_uri']));
        $t->true(!str_contains($annotationJson, $fixture['stale_javascript']));

        $links = (new PdfLinkAnnotationExtractor())->extractPageLinks($fixture['bytes']);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same($fixture['current_uri'], $links[0]['links'][0]['uri'] ?? null);
        $t->same(1, count($links[0]['links'][0]['additional_actions']));
        $t->same($fixture['current_additional_uri'], $links[0]['links'][0]['additional_actions'][0]['uri'] ?? null);

        $linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages(
            [
                [
                    'page' => 1,
                    'blocks' => [
                        [
                            'type' => 'Line',
                            'bbox' => [48, 180, 220, 202],
                            'lines' => [
                                [
                                    'spans' => [
                                        [
                                            'text' => 'Current compressed action docs',
                                            'bbox' => [48, 180, 220, 202],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $fixture['bytes']
        );
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same($fixture['current_uri'], $span['link_uri']);
        $t->same($fixture['current_additional_uri'], $span['link_additional_actions_review'][0]['uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current compressed action docs](https://example.com/current-compressed-prev-action)', $blocks[0]['text']);

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($fixture['bytes'], '/Prev '));
        $t->true(str_contains($encoded, $fixture['current_uri']));
        $t->true(str_contains($encoded, $fixture['current_additional_uri']));
        $t->true(!str_contains($encoded, $fixture['stale_uri']));
        $t->true(!str_contains($encoded, $fixture['stale_javascript']));
    },
];
