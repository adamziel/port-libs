<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$malformedAliasSiblingPage = static function (int $page, string $lead, string $url, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => $page,
            'idx' => 12,
            'coord' => [72.0, 144.0],
            'raw_private_payload' => "malformed alias sibling ref payload {$page} must stay hidden",
        ]],
        'raw_page_payload' => "malformed alias sibling page payload {$page} must stay hidden",
        'blocks' => [[
            'bbox' => [72.0, 144.0, 480.0, 158.0],
            'raw_block_payload' => "malformed alias sibling block payload {$page} must stay hidden",
            'lines' => [[
                'bbox' => [72.0, 144.0, 480.0, 158.0],
                'raw_line_payload' => "malformed alias sibling line payload {$page} must stay hidden",
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 144.0, 180.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'alias sibling link',
                        'bbox' => [180.0, 144.0, 320.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => $url,
                        'raw_span_payload' => "malformed alias sibling span payload {$page} must stay hidden",
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [320.0, 144.0, 480.0, 158.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

return [
    'rejects malformed pages alias before valid page_map sibling in explicit dictionary_output envelopes' => static function (
        TestRunner $t
    ) use ($malformedAliasSiblingPage): void {
        $stale = $malformedAliasSiblingPage(9200, 'Stale malformed alias adapter ', 'https://example.com/alias-stale', ' should not import');
        $current = $malformedAliasSiblingPage(9201, 'Valid page_map sibling ', 'https://example.com/alias-current', ' must not mask malformed pages');
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                9100 => $stale,
            ],
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'corrupt native dictionary_output alias cache',
                    'raw_private_payload' => 'malformed alias dictionary_output metadata must stay hidden',
                ],
                'pages' => 'not an ordered pdftext page list',
                'page_map' => [
                    9201 => $current,
                ],
                'raw_pdftext_payload' => 'malformed alias dictionary_output payload must stay hidden',
            ],
            'raw_adapter_payload' => 'malformed alias stale adapter payload must stay hidden',
        ], maxPages: 1));
    },
    'rejects malformed page_map alias before valid pageMap sibling in raw pdftext JSON envelopes' => static function (
        TestRunner $t
    ) use ($malformedAliasSiblingPage): void {
        $stale = $malformedAliasSiblingPage(9300, 'Stale raw JSON alias adapter ', 'https://example.com/json-alias-stale', ' should not import');
        $current = $malformedAliasSiblingPage(9301, 'Valid pageMap sibling ', 'https://example.com/json-alias-current', ' must not mask malformed page_map');
        $json = json_encode([
            'metadata' => [
                'source' => 'corrupt pdftext --json alias cache',
                'raw_private_payload' => 'malformed alias pdftext metadata must stay hidden',
            ],
            'page_map' => 'not a pdftext page map',
            'pageMap' => [
                9301 => $current,
            ],
            'raw_pdftext_payload' => 'malformed alias pdftext payload must stay hidden',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([
            'pages' => [
                9300 => $stale,
            ],
            'pdftext' => $json,
            'raw_adapter_payload' => 'malformed alias pdftext outer payload must stay hidden',
        ], maxPages: 1));
    },
    'keeps valid later page-map aliases importable when earlier malformed aliases are absent' => static function (
        TestRunner $t
    ) use ($malformedAliasSiblingPage): void {
        $selected = $malformedAliasSiblingPage(9401, 'Valid alias-only pageMap ', 'https://example.com/alias-only-current', ' imports without Python');

        $document = (new PdfTextDocumentExtractor())->getTextBlocks([
            'dictionary_output' => [
                'metadata' => [
                    'source' => 'native dictionary_output alias cache',
                    'raw_private_payload' => 'valid alias-only metadata must stay hidden',
                ],
                'pageMap' => [
                    9401 => $selected,
                ],
                'raw_pdftext_payload' => 'valid alias-only payload must stay hidden',
            ],
        ], maxPages: 1);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(1, $document['metadata']['source_pages']);
        $t->same(9401, $page['pnum']);
        $t->same('Valid alias-only pageMap [alias sibling link](https://example.com/alias-only-current) imports without Python', $blocks[0]['text']);
        $t->same('https://example.com/alias-only-current', $page['blocks'][0]['lines'][0]['spans'][1]['url']);
        $t->same('#page-9401-12', $page['pdftext_source']['refs'][0]['url'] ?? null);
        $t->true(!str_contains($encoded, 'valid alias-only metadata'));
        $t->true(!str_contains($encoded, 'valid alias-only payload'));
        $t->true(!str_contains($encoded, 'malformed alias sibling page payload 9401'));
        $t->true(!str_contains($encoded, 'malformed alias sibling span payload 9401'));
    },
];
