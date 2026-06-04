<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'page' => 6,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [[
        'pdfium_block_type' => 'text',
        'lines' => [
            [
                'bbox' => [72.0, 96.0, 420.0, 110.0],
                'baseline' => [72.0, 103.0, 420.0, 103.0],
                'spans' => [[
                    'text' => "Shared\u{00A0}hosting \u{FB01}xture docu\x02ment\r\n",
                    'bbox' => [72.0, 96.0, 250.0, 110.0],
                    'font' => ['name' => null, 'flags' => (1 << 5), 'weight' => 400, 'size' => 11.0],
                    'char_start_idx' => 0,
                    'char_end_idx' => 26,
                    'raw_image_bytes' => "\xFF\xD8decoy span payload",
                    'debug_payload' => ['private_stream' => 'decoy span debug payload'],
                    'chars' => [
                        ['c' => 'S', 'bbox' => [72.0, 96.0, 78.0, 110.0]],
                    ],
                ]],
            ],
            [
                'bbox' => [72.0, 122.0, 500.0, 136.0],
                'spans' => [[
                    'text' => "keeps supplied pdftext char offsets reviewable.",
                    'bbox' => [72.0, 122.0, 500.0, 136.0],
                    'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 10.0],
                    'char_start_idx' => 27,
                    'char_end_idx' => 73,
                ]],
            ],
        ],
    ]],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [$page],
    maxPages: 1,
    startPage: 0,
    toc: [['title' => 'Dictionary core', 'level' => 1, 'page_index' => 6]],
    flattenPdf: true,
    workers: 2
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$firstSpan = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0];
$firstCharBlock = $document['pages'][0]['char_blocks'][0];
$firstCharSpan = $firstCharBlock['lines'][0]['spans'][0];

echo '<!-- markerpdf:pdftext-dictionary-core ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_pdftext' => false,
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'font' => $firstSpan['font'],
    'normalized_span_text' => $firstSpan['text'],
    'char_blocks_dictionary_text' => $firstCharSpan['text'],
    'ligature_expanded' => !str_contains($firstSpan['text'], "\u{FB01}"),
    'special_spaces_normalized' => !str_contains($firstSpan['text'], "\u{00A0}") && !str_contains($firstSpan['text'], "\u{FEFF}"),
    'hyphen_sentinel_removed_from_visible_text' => !str_contains($firstSpan['text'], "\x02"),
    'unsafe_controls_removed' => !str_contains($firstSpan['text'], "\x00"),
    'char_start_idx' => $firstSpan['char_start_idx'],
    'char_end_idx' => $firstSpan['char_end_idx'],
    'raw_chars_present' => array_key_exists('chars', $firstSpan),
    'char_blocks_raw_chars_present' => array_key_exists('chars', $firstCharSpan),
    'char_blocks_non_core_span_payload_excluded' => !array_key_exists('raw_image_bytes', $firstCharSpan)
        && !array_key_exists('debug_payload', $firstCharSpan),
    'non_core_span_payload_text_excluded' => !str_contains(json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '', 'decoy span payload'),
    'char_blocks_core_keys' => array_keys($firstCharBlock),
    'span_core_keys' => array_keys($firstCharSpan),
    'line_core_keys' => array_keys($firstCharBlock['lines'][0]),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
