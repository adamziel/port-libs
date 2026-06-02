<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$pdftextPage = static function (int $page, string $text, float $top = 72.0): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [
            [
                'lines' => [
                    [
                        'bbox' => [72.0, $top, 460.0, $top + 12.0],
                        'spans' => [
                            [
                                'text' => $text,
                                'bbox' => [72.0, $top, 460.0, $top + 12.0],
                                'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
};

return [
    'slices supplied pdftext dictionary pages like upstream get_text_blocks' => static function (TestRunner $t) use ($pdftextPage): void {
        $toc = [
            ['title' => 'Front matter', 'level' => 1, 'page_index' => 0],
            ['title' => 'Import range', 'level' => 1, 'page_index' => 8],
        ];

        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(7, 'Skipped cover page'),
                $pdftextPage(8, 'Selected WordPress import page'),
                $pdftextPage(9, 'Second selected page'),
            ],
            maxPages: 5,
            startPage: 1,
            toc: $toc
        );

        $t->same([1, 2], $result['page_range']);
        $t->same(2, $result['metadata']['pages']);
        $t->same(1, $result['metadata']['start_page']);
        $t->same(2, $result['metadata']['max_pages']);
        $t->same($toc, $result['metadata']['pdf_toc']);
        $t->same(8, $result['pages'][0]['pnum']);
        $t->same(9, $result['pages'][1]['pnum']);
        $t->same('0_0', $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
        $t->same('1_0', $result['pages'][1]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
    },
    'treats zero max_pages like upstream falsey max page input' => static function (TestRunner $t) use ($pdftextPage): void {
        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [$pdftextPage(0, 'First'), $pdftextPage(1, 'Second')],
            maxPages: 0
        );

        $t->same([0, 1], $result['page_range']);
        $t->same(2, $result['metadata']['pages']);
    },
    'records pdftext dictionary output options for the selected page range' => static function (TestRunner $t) use ($pdftextPage): void {
        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(3, 'Skipped front matter'),
                $pdftextPage(4, 'Dictionary core boundary'),
                $pdftextPage(5, 'Deferred appendix'),
            ],
            maxPages: 1,
            startPage: 1,
            flattenPdf: true,
            workers: 2
        );

        $t->same([1], $result['page_range']);
        $t->same([
            'page_range' => [1],
            'keep_chars' => false,
            'flatten_pdf' => true,
            'workers' => 2,
        ], $result['metadata']['pdftext_options']);
        $t->same(4, $result['pages'][0]['pnum']);
    },
    'rejects out of range page slices before WordPress import' => static function (TestRunner $t) use ($pdftextPage): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$pdftextPage(0, 'Only page')], startPage: 1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$pdftextPage(0, 'Only page')], maxPages: -1));
        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getTextBlocks([$pdftextPage(0, 'Only page')], workers: 0));
    },
    'feeds selected pdftext pages into Gutenberg-ready paragraph text' => static function (TestRunner $t) use ($pdftextPage): void {
        $result = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextPage(0, 'Editorial cover page'),
                $pdftextPage(1, "Shared-hosting docu-\nment conversion"),
            ],
            maxPages: 1,
            startPage: 1
        );

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($result['pages']));

        $t->same(1, count($blocks));
        $t->same('Shared-hosting document conversion', $blocks[0]['text']);
        $t->same(1, $blocks[0]['pnum']);
    },
];
