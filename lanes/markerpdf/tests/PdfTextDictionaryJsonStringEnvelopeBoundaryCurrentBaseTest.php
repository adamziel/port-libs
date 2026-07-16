<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pdftextJsonStringPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 2,
            'coord' => [72.0, 96.0],
            'raw_private_payload' => "raw ref payload for {$page} must stay hidden",
        ]],
        'raw_page_payload' => "raw page payload for {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 96.0, 430.0, 110.0],
            'raw_block_payload' => "raw block payload for {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 96.0, 430.0, 110.0],
                'raw_line_payload' => "raw line payload for {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 96.0, 160.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'cached JSON link',
                        'bbox' => [160.0, 96.0, 260.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "raw span payload for {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [260.0, 96.0, 430.0, 110.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'unwraps raw JSON dictionary_output page strings before pdftext core conversion' => static function (TestRunner $t) use ($pdftextJsonStringPage): void {
        $stale = $pdftextJsonStringPage(300, 'Stale adapter ', 'https://example.com/stale-json-cache', ' should not import');
        $cover = $pdftextJsonStringPage(400, 'Skipped raw JSON cover ', 'https://example.com/raw-json-cover', ' should not import');
        $selected = $pdftextJsonStringPage(401, 'Raw JSON dictionary_output ', 'https://example.com/raw-json-current', ' stays visible');
        $appendix = $pdftextJsonStringPage(402, 'Skipped raw JSON appendix ', 'https://example.com/raw-json-appendix', ' should not import');

        $json = json_encode([
            'metadata' => [
                'source' => 'pdftext --json cache',
                'raw_private_payload' => 'raw JSON metadata payload must stay hidden',
            ],
            'pages' => [
                400 => $cover,
                401 => $selected,
                402 => $appendix,
            ],
            'raw_pdftext_payload' => 'raw JSON dictionary_output payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pages' => [
                300 => $stale,
            ],
            'dictionary_output' => $json,
            'raw_adapter_payload' => 'stale adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(401, $page['pnum']);
        $t->same('Raw JSON dictionary_output [cached JSON link](https://example.com/raw-json-current) stays visible', $blocks[0]['text']);
        $t->same('https://example.com/raw-json-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-401-2', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Stale adapter'));
        $t->true(!str_contains($encoded, 'Skipped raw JSON cover'));
        $t->true(!str_contains($encoded, 'Skipped raw JSON appendix'));
        $t->true(!str_contains($encoded, 'raw JSON metadata payload'));
        $t->true(!str_contains($encoded, 'raw JSON dictionary_output payload'));
        $t->true(!str_contains($encoded, 'stale adapter payload'));
        $t->true(!str_contains($encoded, 'raw page payload for 401'));
        $t->true(!str_contains($encoded, 'raw span payload for 401'));
    },
    'unwraps raw JSON pdftext page-list cache strings at the core boundary' => static function (TestRunner $t) use ($pdftextJsonStringPage): void {
        $selected = $pdftextJsonStringPage(501, 'Raw JSON pdftext cache ', 'https://example.com/raw-json-pdftext', ' stays selected');
        $json = json_encode([$selected], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pdftext' => $json,
            'raw_adapter_payload' => 'raw JSON pdftext adapter payload must stay hidden',
        ], maxPages: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(501, $page['pnum']);
        $t->same('Raw JSON pdftext cache [cached JSON link](https://example.com/raw-json-pdftext) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/raw-json-pdftext', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-501-2', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'raw JSON pdftext adapter payload'));
        $t->true(!str_contains($encoded, 'raw page payload for 501'));
        $t->true(!str_contains($encoded, 'raw span payload for 501'));
    },
    'rejects malformed raw JSON explicit pdftext strings before stale adapter pages' => static function (TestRunner $t) use ($pdftextJsonStringPage): void {
        $stale = $pdftextJsonStringPage(601, 'Malformed raw JSON stale ', 'https://example.com/malformed-json-stale', ' should not import');
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [601 => $stale],
            'dictionary_output' => '{"pages":[{"page":602',
            'raw_adapter_payload' => 'malformed raw JSON adapter payload must not cross',
        ], maxPages: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [601 => $stale],
            'pdftext' => '[{"page":603',
            'raw_adapter_payload' => 'malformed raw JSON pdftext payload must not cross',
        ], maxPages: 1));
    },
];
