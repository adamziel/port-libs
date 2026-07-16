<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$jsonDuplicateKeyPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 11,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "duplicate-key JSON ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "duplicate-key JSON page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 500.0, 158.0],
            'raw_block_payload' => "duplicate-key JSON block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 500.0, 158.0],
                'raw_line_payload' => "duplicate-key JSON line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 190.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'duplicate-key link',
                        'bbox' => [190.0, 144.0, 340.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "duplicate-key JSON span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [340.0, 144.0, 500.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$jsonValue = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

return [
    'rejects exact duplicate raw JSON pdftext page-map keys before stale adapter pages' => static function (
        TestRunner $t
    ) use ($jsonDuplicateKeyPage, $jsonValue): void {
        $stale = $jsonDuplicateKeyPage(8100, 'Stale duplicate-key adapter ', 'https://example.com/duplicate-json-stale', ' should not import');
        $first = $jsonDuplicateKeyPage(9100, 'First duplicate JSON page ', 'https://example.com/duplicate-json-first', ' must fail closed');
        $second = $jsonDuplicateKeyPage(9101, 'Second duplicate JSON page ', 'https://example.com/duplicate-json-second', ' must fail closed');
        $json = '{"metadata":{"source":"pdftext --json duplicate page map","raw_private_payload":"duplicate JSON metadata payload must stay hidden"},'
            . '"pages":{"17":' . $jsonValue($first) . ',"\u0031\u0037":' . $jsonValue($second) . '},'
            . '"raw_pdftext_payload":"duplicate JSON page-map payload must stay hidden"}';

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                8100 => $stale,
            ],
            'pdftext' => $json,
            'raw_adapter_payload' => 'duplicate JSON stale adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'rejects exact duplicate raw JSON one-page entry envelopes before stale wrapper text' => static function (
        TestRunner $t
    ) use ($jsonDuplicateKeyPage, $jsonValue): void {
        $cover = $jsonDuplicateKeyPage(8200, 'Skipped duplicate-entry cover ', 'https://example.com/duplicate-entry-cover', ' should not import');
        $staleSelected = $jsonDuplicateKeyPage(8201, 'Stale duplicate-entry wrapper ', 'https://example.com/duplicate-entry-stale', ' should not import');
        $first = $jsonDuplicateKeyPage(9201, 'First duplicate-entry page ', 'https://example.com/duplicate-entry-first', ' must fail closed');
        $second = $jsonDuplicateKeyPage(9202, 'Second duplicate-entry page ', 'https://example.com/duplicate-entry-second', ' must fail closed');
        $appendix = $jsonDuplicateKeyPage(8202, 'Skipped duplicate-entry appendix ', 'https://example.com/duplicate-entry-appendix', ' should not import');
        $staleSelected['dictionary_output'] = '{"metadata":{"source":"jsonl one-page dictionary_output duplicate","raw_private_payload":"duplicate entry metadata payload must stay hidden"},'
            . '"page_map":{"29":' . $jsonValue($first) . ',"\u0032\u0039":' . $jsonValue($second) . '},'
            . '"raw_pdftext_payload":"duplicate entry pdftext payload must stay hidden"}';
        $staleSelected['raw_wrapper_payload'] = 'duplicate entry stale wrapper payload must stay hidden';

        $extractor = new PdfTextDocumentExtractor();
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks(
            [$cover, $staleSelected, $appendix],
            maxPages: 1,
            startPage: 1
        ));
    },
    'continues importing unique raw JSON pdftext page maps at the core boundary' => static function (
        TestRunner $t
    ) use ($jsonDuplicateKeyPage): void {
        $cover = $jsonDuplicateKeyPage(8300, 'Skipped unique JSON cover ', 'https://example.com/unique-json-cover', ' should not import');
        $selected = $jsonDuplicateKeyPage(8301, 'Unique raw JSON pdftext ', 'https://example.com/unique-json-current', ' stays selected');
        $appendix = $jsonDuplicateKeyPage(8302, 'Skipped unique JSON appendix ', 'https://example.com/unique-json-appendix', ' should not import');
        $json = json_encode([
            'metadata' => [
                'source' => 'pdftext --json unique page map',
                'raw_private_payload' => 'unique JSON metadata payload must stay hidden',
            ],
            'page_map' => [
                '8300' => $cover,
                '8301' => $selected,
                '8302' => $appendix,
            ],
            'raw_pdftext_payload' => 'unique JSON page-map payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'pdftext' => $json,
            'raw_adapter_payload' => 'unique JSON adapter payload must stay hidden',
        ], maxPages: 1, startPage: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $document['page_range']);
        $t->same(3, $document['metadata']['source_pages']);
        $t->same(8301, $page['pnum']);
        $t->same('Unique raw JSON pdftext [duplicate-key link](https://example.com/unique-json-current) stays selected', $blocks[0]['text']);
        $t->same('https://example.com/unique-json-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-8301-11', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'Skipped unique JSON cover'));
        $t->true(!str_contains($encoded, 'Skipped unique JSON appendix'));
        $t->true(!str_contains($encoded, 'unique JSON metadata payload'));
        $t->true(!str_contains($encoded, 'unique JSON page-map payload'));
        $t->true(!str_contains($encoded, 'unique JSON adapter payload'));
        $t->true(!str_contains($encoded, 'duplicate-key JSON page payload 8301'));
        $t->true(!str_contains($encoded, 'duplicate-key JSON span payload 8301'));
    },
];
