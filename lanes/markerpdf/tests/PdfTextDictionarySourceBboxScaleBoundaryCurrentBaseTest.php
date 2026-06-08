<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$sourceBboxOnlyNormalizedPage = static function (): array {
    $font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];

    return [
        'page' => 5,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'rotation' => 0,
        'raw_page_payload' => 'source bbox only page payload must stay hidden',
        'blocks' => [[
            'bbox' => [0.10, 0.20, 0.50, 0.25],
            'raw_block_payload' => 'source bbox only block payload must stay hidden',
            'lines' => [[
                'bbox' => [0.10, 0.20, 0.50, 0.25],
                'baseline' => [0.10, 0.24, 0.50, 0.24],
                'spans' => [[
                    'text' => "Source bbox scaled import\n",
                    'bbox' => [0.12, 0.205, 0.32, 0.225],
                    'font' => $font,
                    'char_start_idx' => 3,
                    'char_end_idx' => 3,
                    'chars' => [[
                        'char' => 'S',
                        'bbox' => [0.12, 0.205, 0.14, 0.225],
                        'font' => $font,
                        'char_idx' => 3,
                        'raw_char_payload' => 'source bbox only char payload must stay hidden',
                    ]],
                    'raw_span_payload' => 'source bbox only span payload must stay hidden',
                ]],
            ]],
        ]],
    ];
};

return [
    'scales normalized pdftext child bboxes from source page bbox when width and height are absent' => static function (
        TestRunner $t
    ) use ($sourceBboxOnlyNormalizedPage): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks([$sourceBboxOnlyNormalizedPage()], maxPages: 1, keepChars: true);
        $page = $document['pages'][0];
        $block = $page['blocks'][0];
        $line = $block['lines'][0];
        $span = $line['spans'][0];
        $charBlock = $page['char_blocks'][0];
        $charSpan = $charBlock['lines'][0]['spans'][0];
        $char = $span['chars'][0];
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($document['pages']));
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $document['page_range']);
        $t->same(5, $page['pnum']);
        $t->same([0.0, 0.0, 600.0, 800.0], $page['bbox']);
        $t->same([
            'page' => 5,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'rotation' => 0,
        ], $page['pdftext_source']);
        $t->true(!array_key_exists('width', $page['pdftext_source']));
        $t->true(!array_key_exists('height', $page['pdftext_source']));
        $t->same([60.0, 160.0, 300.0, 200.0], $block['bbox']);
        $t->same([60.0, 160.0, 300.0, 200.0], $line['bbox']);
        $t->same([72.0, 164.0, 192.0, 180.0], $span['bbox']);
        $t->same([72.0, 164.0, 84.0, 180.0], $char['bbox']);
        $t->same([60.0, 160.0, 300.0, 200.0], $charBlock['bbox']);
        $t->same([72.0, 164.0, 192.0, 180.0], $charSpan['bbox']);
        $t->same('Source bbox scaled import', $blocks[0]['text']);
        $t->same(3, $span['char_start_idx']);
        $t->same(3, $span['char_end_idx']);
        $t->true(!str_contains($encoded, 'source bbox only page payload'));
        $t->true(!str_contains($encoded, 'source bbox only block payload'));
        $t->true(!str_contains($encoded, 'source bbox only span payload'));
        $t->true(!str_contains($encoded, 'source bbox only char payload'));
        $t->true(!array_key_exists('baseline', $charBlock['lines'][0]));
    },
];
