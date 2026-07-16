<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $prefix): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'refs' => [[
            'page' => 7,
            'idx' => 3,
            'coord' => [108.0, 156.0],
        ]],
        'blocks' => [[
            'bbox' => [72.0, 96.0, 520.0, 112.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 520.0, 112.0],
                'spans' => [
                    [
                        'text' => $prefix . ' ',
                        'bbox' => [72.0, 96.0, 188.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'dictionary output link',
                        'bbox' => [188.0, 96.0, 348.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/dictionary-output',
                    ],
                    [
                        'text' => ' import',
                        'bbox' => [348.0, 96.0, 520.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$decodedEnvelope = (array) json_decode(json_encode([
    'metadata' => [
        'source' => 'native adapter cache',
        'raw_private_payload' => 'dictionary_output envelope metadata payload must not cross',
    ],
    'dictionary_output' => [
        'metadata' => [
            'source' => 'pdftext.dictionary_output',
            'raw_private_payload' => 'nested dictionary_output metadata payload must not cross',
        ],
        'pages' => [
            80 => $page(80, 'Skipped cover'),
            81 => $page(81, 'WordPress selected'),
            82 => $page(82, 'Skipped appendix'),
        ],
        'raw_pdftext_payload' => 'dictionary_output adapter payload must not cross',
    ],
    'raw_adapter_payload' => 'top-level dictionary_output payload must not cross',
], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    $decodedEnvelope,
    maxPages: 1,
    startPage: 1,
    toc: [['title' => 'Dictionary output selected page', 'level' => 1, 'page_index' => 81]]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$selectedPage = $document['pages'][0] ?? [];

if (
    !($decodedEnvelope['dictionary_output'] instanceof stdClass)
    || !($decodedEnvelope['dictionary_output']->pages instanceof stdClass)
    || ($selectedPage['pnum'] ?? null) !== 81
    || ($document['metadata']['source_pages'] ?? null) !== 3
    || (($selectedPage['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) !== 'https://example.com/dictionary-output')
    || (($selectedPage['pdftext_source']['refs'][0]['url'] ?? null) !== '#page-7-3')
    || str_contains($encoded, 'Skipped cover')
    || str_contains($encoded, 'Skipped appendix')
    || str_contains($encoded, 'dictionary_output envelope metadata payload')
    || str_contains($encoded, 'nested dictionary_output metadata payload')
    || str_contains($encoded, 'dictionary_output adapter payload')
    || str_contains($encoded, 'top-level dictionary_output payload')
) {
    throw new RuntimeException('Expected named dictionary_output envelope to unwrap before WordPress pdftext import.');
}

echo '<!-- markerpdf:pdftext-dictionary-output-envelope ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_boundary' => 'cached-pdftext-dictionary_output-envelope',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'dictionary_output_envelope_unwrapped' => $decodedEnvelope['dictionary_output'] instanceof stdClass,
    'nested_pages_envelope_unwrapped' => $decodedEnvelope['dictionary_output']->pages instanceof stdClass,
    'selected_page_range' => $document['page_range'],
    'selected_pdftext_page' => $selectedPage['pnum'] ?? null,
    'source_pages' => $document['metadata']['source_pages'] ?? null,
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'page_ref_synthesized' => ($selectedPage['pdftext_source']['refs'][0]['url'] ?? null) === '#page-7-3',
    'safe_span_link_promoted' => ($selectedPage['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/dictionary-output',
    'dictionary_output_payload_excluded' => !str_contains($encoded, 'dictionary_output envelope metadata payload')
        && !str_contains($encoded, 'nested dictionary_output metadata payload')
        && !str_contains($encoded, 'dictionary_output adapter payload')
        && !str_contains($encoded, 'top-level dictionary_output payload'),
    'skipped_pages_excluded' => !str_contains($encoded, 'Skipped cover')
        && !str_contains($encoded, 'Skipped appendix'),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
