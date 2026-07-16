<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$metadataSiblingPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'raw_page_payload' => "page-shaped metadata sibling payload {$page} must stay hidden",
        'refs' => [[
            'page' => $page,
            'idx' => 2,
            'coord' => [72.0, 144.0],
            'raw_ref_payload' => "page-shaped metadata sibling ref {$page} must stay hidden",
        ]],
        'blocks' => [[
            'bbox' => [72.0, 144.0, 460.0, 158.0],
            'raw_block_payload' => "page-shaped metadata sibling block {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 460.0, 158.0],
                'raw_line_payload' => "page-shaped metadata sibling line {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 188.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'current import link',
                        'bbox' => [188.0, 144.0, 312.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "page-shaped metadata sibling span {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [312.0, 144.0, 460.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'ignores page-shaped metadata siblings in pdftext dictionary maps before WordPress text import' => static function (
        TestRunner $t
    ) use ($metadataSiblingPage): void {
        $metadataDecoy = $metadataSiblingPage(
            8800,
            'Metadata sibling stale text ',
            'https://example.com/metadata-sibling-stale',
            ' should not import'
        );
        $payloadDecoy = $metadataSiblingPage(
            8801,
            'Raw payload sibling stale text ',
            'https://example.com/raw-payload-stale',
            ' should not import'
        );
        $cover = $metadataSiblingPage(
            8802,
            'Skipped numeric cover ',
            'https://example.com/metadata-sibling-cover',
            ' should stay outside selected range'
        );
        $selected = $metadataSiblingPage(
            8803,
            'Selected dictionary map ',
            'https://example.com/metadata-sibling-current',
            ' stays visible'
        );

        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                'dictionary_output' => [
                    'metadata' => $metadataDecoy,
                    'raw_payload' => $payloadDecoy,
                    '8802' => $cover,
                    '8803' => $selected,
                ],
            ],
            maxPages: 1,
            startPage: 1,
            toc: [['title' => 'Selected dictionary map', 'level' => 1, 'page_index' => 8803]]
        );

        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(2, $document['metadata']['source_pages']);
        $t->same(1, $document['metadata']['pages']);
        $t->same(8803, $page['pnum']);
        $t->same('Selected dictionary map [current import link](https://example.com/metadata-sibling-current) stays visible', $blocks[0]['text']);
        $t->same('https://example.com/metadata-sibling-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-8803-2', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Metadata sibling stale text'));
        $t->true(!str_contains($encoded, 'Raw payload sibling stale text'));
        $t->true(!str_contains($encoded, 'Skipped numeric cover'));
        $t->true(!str_contains($encoded, 'metadata-sibling-stale'));
        $t->true(!str_contains($encoded, 'raw-payload-stale'));
        $t->true(!str_contains($encoded, 'page-shaped metadata sibling payload'));
        $t->true(!str_contains($encoded, 'page-shaped metadata sibling ref'));
        $t->true(!str_contains($encoded, 'page-shaped metadata sibling block'));
        $t->true(!str_contains($encoded, 'page-shaped metadata sibling line'));
        $t->true(!str_contains($encoded, 'page-shaped metadata sibling span'));
    },
];
