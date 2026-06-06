<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 24,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'width' => 612.0,
    'height' => 792.0,
    'rotation' => 0,
    'refs' => [[
        'page' => 24,
        'idx' => 0,
        'coord' => [72.0, 96.0],
    ]],
    'blocks' => [[
        'bbox' => [72.0, 96.0, 460.0, 110.0],
        'lines' => [[
            'bbox' => [72.0, 96.0, 460.0, 110.0],
            'spans' => [
                [
                    'text' => 'Zero-worker ',
                    'bbox' => [72.0, 96.0, 150.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                ],
                [
                    'text' => 'pdftext dictionary',
                    'bbox' => [150.0, 96.0, 270.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'https://example.com/zero-workers',
                ],
                [
                    'text' => ' stays sequential.',
                    'bbox' => [270.0, 96.0, 460.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    'url' => 'javascript:zeroWorker()',
                ],
            ],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1, workers: 0);
$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$spans = $document['pages'][0]['blocks'][0]['lines'][0]['spans'];
$charSpans = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'];

$negativeWorkersRejected = false;
try {
    $extractor->getTextBlocks([$page], maxPages: 1, workers: -1);
} catch (InvalidArgumentException) {
    $negativeWorkersRejected = true;
}

$summary = [
    'scenario' => 'wordpress-pdftext-dictionary-zero-workers-currentbase',
    'source_truth' => 'pdftext.extraction._get_pages treats workers<=1 as the sequential dictionary_output path before markerPDF converts selected pages',
    'support_component' => 'pdf-text-dictionary-core',
    'page_range' => $document['page_range'],
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'zero_workers_recorded' => ($document['metadata']['pdftext_options']['workers'] ?? null) === 0,
    'sequential_page_imported' => ($document['pages'][0]['pnum'] ?? null) === 24,
    'span_ids_restart_after_selected_range' => ($spans[0]['span_id'] ?? null) === '0_0',
    'safe_pdftext_link_promoted' => ($spans[1]['url'] ?? null) === 'https://example.com/zero-workers',
    'unsafe_pdftext_link_review_only' => !array_key_exists('url', $spans[2])
        && ($spans[2]['pdftext_url'] ?? null) === 'javascript:zeroWorker()',
    'char_blocks_keep_source_urls' => ($charSpans[2]['url'] ?? null) === 'javascript:zeroWorker()',
    'negative_workers_rejected' => $negativeWorkersRejected,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv ?? [], true)) {
    foreach ([
        'zero_workers_recorded',
        'sequential_page_imported',
        'span_ids_restart_after_selected_range',
        'safe_pdftext_link_promoted',
        'unsafe_pdftext_link_review_only',
        'char_blocks_keep_source_urls',
        'negative_workers_rejected',
    ] as $flag) {
        if (($summary[$flag] ?? false) !== true) {
            throw new RuntimeException("Expected {$flag}=true for zero-worker pdftext dictionary smoke.");
        }
    }
}

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-zero-workers-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
