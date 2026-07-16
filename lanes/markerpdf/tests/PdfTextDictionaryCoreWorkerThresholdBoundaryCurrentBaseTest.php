<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

$workerThresholdPage = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 96.0, 360.0, 110.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 360.0, 110.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 96.0, 360.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'raw_worker_payload' => "hidden worker payload {$page}",
                ]],
            ]],
        ]],
    ];
};

return [
    'records upstream pdftext worker threshold decisions at the dictionary core boundary' => static function (
        TestRunner $t
    ) use ($workerThresholdPage): void {
        $pages = [];
        for ($i = 0; $i < 25; $i++) {
            $pages[] = $workerThresholdPage($i, "Page {$i} worker threshold text\n");
        }

        $extractor = new PdfTextDocumentExtractor();
        $singlePage = $extractor->getTextBlocks($pages, maxPages: 1, workers: 8);
        $thresholdOnly = $extractor->getTextBlocks($pages, maxPages: 12, workers: 8);
        $parallel = $extractor->getTextBlocks($pages, maxPages: 21, startPage: 2, workers: 8);
        $empty = $extractor->getTextBlocks([], workers: 8);

        $parallelBlocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($parallel['pages']));
        $encodedParallel = json_encode($parallel, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same(8, $singlePage['metadata']['pdftext_options']['workers']);
        $t->same([
            'requested_workers' => 8,
            'selected_pages' => 1,
            'worker_page_threshold' => 10,
            'effective_workers' => 0,
            'uses_multiprocessing' => false,
            'sequential_fallback' => true,
        ], $singlePage['metadata']['pdftext_worker_plan']);
        $t->same([
            'requested_workers' => 8,
            'selected_pages' => 12,
            'worker_page_threshold' => 10,
            'effective_workers' => 1,
            'uses_multiprocessing' => false,
            'sequential_fallback' => true,
        ], $thresholdOnly['metadata']['pdftext_worker_plan']);
        $t->same([2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22], $parallel['page_range']);
        $t->same(21, $parallel['metadata']['pages']);
        $t->same(25, $parallel['metadata']['source_pages']);
        $t->same(8, $parallel['metadata']['pdftext_options']['workers']);
        $t->same([
            'requested_workers' => 8,
            'selected_pages' => 21,
            'worker_page_threshold' => 10,
            'effective_workers' => 2,
            'uses_multiprocessing' => true,
            'sequential_fallback' => false,
        ], $parallel['metadata']['pdftext_worker_plan']);
        $t->same(2, $parallel['pages'][0]['pnum']);
        $t->same(22, $parallel['pages'][20]['pnum']);
        $t->same('0_0', $parallel['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
        $t->same('20_0', $parallel['pages'][20]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
        $t->contains('Page 2 worker threshold text', $parallelBlocks[0]['text']);
        $t->contains('Page 22 worker threshold text', $parallelBlocks[0]['text']);
        $t->true(!str_contains($encodedParallel, 'hidden worker payload'), 'Worker threshold metadata must not leak hidden span payloads.');
        $t->same(0, $empty['metadata']['pages']);
        $t->same([], $empty['page_range']);
        $t->same([
            'requested_workers' => 8,
            'selected_pages' => 0,
            'worker_page_threshold' => 10,
            'effective_workers' => 0,
            'uses_multiprocessing' => false,
            'sequential_fallback' => true,
        ], $empty['metadata']['pdftext_worker_plan']);
    },
];
