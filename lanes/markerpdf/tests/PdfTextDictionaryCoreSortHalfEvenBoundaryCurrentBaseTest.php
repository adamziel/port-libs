<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$sortHalfEvenPage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
    $span = static fn (string $text, array $bbox): array => [
        'text' => $text,
        'bbox' => $bbox,
        'font' => $font,
        'raw_span_payload' => "hidden {$text} payload",
    ];

    return [
        'page' => 17,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'raw_page_payload' => 'sort half-even page payload must stay hidden',
        'blocks' => [
            [
                'bbox' => [300.0, 0.0, 430.0, 14.0],
                'raw_block_payload' => 'right-column block payload must stay hidden',
                'lines' => [[
                    'bbox' => [300.0, 0.0, 430.0, 14.0],
                    'spans' => [$span("Right column first in source\n", [300.0, 0.0, 430.0, 14.0])],
                ]],
            ],
            [
                'bbox' => [72.0, 0.625, 220.0, 14.625],
                'raw_block_payload' => 'left half-even tie block payload must stay hidden',
                'lines' => [[
                    'bbox' => [72.0, 0.625, 220.0, 14.625],
                    'spans' => [$span("Left half-even tie\n", [72.0, 0.625, 220.0, 14.625])],
                ]],
            ],
            [
                'bbox' => [72.0, 18.0, 260.0, 32.0],
                'raw_block_payload' => 'second row block payload must stay hidden',
                'lines' => [[
                    'bbox' => [72.0, 18.0, 260.0, 32.0],
                    'spans' => [$span("Second row after tie group\n", [72.0, 18.0, 260.0, 32.0])],
                ]],
            ],
        ],
    ];
};

return [
    'uses upstream half-even row grouping when sorting pdftext dictionaries' => static function (
        TestRunner $t
    ) use ($sortHalfEvenPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$sortHalfEvenPage()], maxPages: 1, sort: true);
        $page = $document['pages'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(true, $document['metadata']['pdftext_options']['sort']);
        $t->same([0], $document['page_range']);
        $t->same(17, $page['pnum']);
        $t->same('Left half-even tie', $page['blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->same('Right column first in source', $page['blocks'][1]['lines'][0]['spans'][0]['text']);
        $t->same('Second row after tie group', $page['blocks'][2]['lines'][0]['spans'][0]['text']);
        $t->same("Left half-even tie\n", $page['char_blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->same("Right column first in source\n", $page['char_blocks'][1]['lines'][0]['spans'][0]['text']);
        $t->same("Second row after tie group\n", $page['char_blocks'][2]['lines'][0]['spans'][0]['text']);
        $t->same('Left half-even tie Right column first in source Second row after tie group', $blocks[0]['text']);
        $t->true(!str_contains($encoded, 'sort half-even page payload'));
        $t->true(!str_contains($encoded, 'right-column block payload'));
        $t->true(!str_contains($encoded, 'left half-even tie block payload'));
        $t->true(!str_contains($encoded, 'hidden Left half-even tie'));
    },
];
