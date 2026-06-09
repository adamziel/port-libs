<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 96.0, 420.0, 110.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 420.0, 110.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 96.0, 420.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'raw_worker_payload' => "hidden worker payload {$page}",
                ]],
            ]],
        ]],
    ];
};

$pages = [];
for ($i = 0; $i < 25; $i++) {
    $pages[] = $page($i, "Worker threshold page {$i}\n");
}

$extractor = new PdfTextDocumentExtractor();
$parallelDocument = $extractor->getTextBlocks($pages, maxPages: 21, startPage: 2, workers: 8);
$smallDocument = $extractor->getTextBlocks($pages, maxPages: 1, workers: 8);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($parallelDocument['pages']));
$encoded = json_encode($parallelDocument, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

$summary = [
    'scenario' => 'wordpress-pdftext-dictionary-worker-threshold-currentbase',
    'source_truth' => 'pdftext.extraction._get_pages clamps workers to floor(selected_pages / WORKER_PAGE_THRESHOLD) and uses the sequential path for effective worker counts <= 1.',
    'support_component' => 'pdf-text-dictionary-core',
    'requested_workers_preserved' => ($parallelDocument['metadata']['pdftext_options']['workers'] ?? null) === 8,
    'selected_range_preserved' => $parallelDocument['page_range'] === range(2, 22),
    'effective_workers' => $parallelDocument['metadata']['pdftext_worker_plan']['effective_workers'] ?? null,
    'parallel_threshold_applied' => ($parallelDocument['metadata']['pdftext_worker_plan']['effective_workers'] ?? null) === 2,
    'small_range_sequential_fallback' => ($smallDocument['metadata']['pdftext_worker_plan']['sequential_fallback'] ?? null) === true,
    'source_pages_preserved' => ($parallelDocument['metadata']['source_pages'] ?? null) === 25,
    'selected_pages_preserved' => ($parallelDocument['metadata']['pages'] ?? null) === 21,
    'first_selected_page' => $parallelDocument['pages'][0]['pnum'] ?? null,
    'last_selected_page' => $parallelDocument['pages'][20]['pnum'] ?? null,
    'span_ids_restart_after_selected_range' => ($parallelDocument['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['span_id'] ?? null) === '0_0'
        && ($parallelDocument['pages'][20]['blocks'][0]['lines'][0]['spans'][0]['span_id'] ?? null) === '20_0',
    'hidden_worker_payload_excluded' => !str_contains($encoded, 'hidden worker payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv ?? [], true)) {
    foreach ([
        'requested_workers_preserved',
        'selected_range_preserved',
        'parallel_threshold_applied',
        'small_range_sequential_fallback',
        'source_pages_preserved',
        'selected_pages_preserved',
        'span_ids_restart_after_selected_range',
        'hidden_worker_payload_excluded',
    ] as $flag) {
        if (($summary[$flag] ?? false) !== true) {
            throw new RuntimeException("Expected {$flag}=true for pdftext worker-threshold smoke.");
        }
    }
    if (($summary['first_selected_page'] ?? null) !== 2 || ($summary['last_selected_page'] ?? null) !== 22) {
        throw new RuntimeException('Expected selected WordPress import pages 2 through 22.');
    }
}

echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($parallelDocument['pages'][0]['pnum'] ?? 0) . '}} -->' . "\n";
echo '<p>' . htmlspecialchars($blocks[0]['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";
echo '<!-- markerpdf-pdftext-dictionary-worker-threshold-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
