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
            'page' => 5,
            'idx' => 1,
            'coord' => [72.0, 108.0],
        ]],
        'blocks' => [[
            'bbox' => [72.0, 96.0, 500.0, 112.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 500.0, 112.0],
                'spans' => [
                    [
                        'text' => $prefix . ' ',
                        'bbox' => [72.0, 96.0, 166.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'object envelope link',
                        'bbox' => [166.0, 96.0, 306.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/object-envelope',
                    ],
                    [
                        'text' => ' import',
                        'bbox' => [306.0, 96.0, 500.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$decodedEnvelope = (array) json_decode(json_encode([
    'metadata' => [
        'source' => 'cached pdftext.dictionary_output JSON object',
        'raw_private_payload' => 'object envelope metadata payload must not cross dictionary_output',
    ],
    'pages' => [
        70 => $page(70, 'Skipped cover'),
        71 => $page(71, 'WordPress selected'),
        72 => $page(72, 'Skipped appendix'),
    ],
    'raw_adapter_payload' => 'object envelope top-level payload must not cross dictionary_output',
], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    $decodedEnvelope,
    maxPages: 1,
    startPage: 1,
    toc: [['title' => 'Object envelope selected page', 'level' => 1, 'page_index' => 71]]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$selectedPage = $document['pages'][0] ?? [];

if (
    !($decodedEnvelope['pages'] instanceof stdClass)
    || ($selectedPage['pnum'] ?? null) !== 71
    || (($selectedPage['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) !== 'https://example.com/object-envelope')
    || (($selectedPage['pdftext_source']['refs'][0]['url'] ?? null) !== '#page-5-1')
    || str_contains($encoded, 'Skipped cover')
    || str_contains($encoded, 'Skipped appendix')
    || str_contains($encoded, 'object envelope metadata payload')
    || str_contains($encoded, 'object envelope top-level payload')
) {
    throw new RuntimeException('Expected JSON object-valued pages envelope to unwrap before WordPress pdftext import.');
}

echo '<!-- markerpdf:pdftext-dictionary-object-envelope ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'source_boundary' => 'pdftext.dictionary_output-pages-object-envelope',
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'object_pages_envelope_unwrapped' => $decodedEnvelope['pages'] instanceof stdClass,
    'selected_page_range' => $document['page_range'],
    'selected_pdftext_page' => $selectedPage['pnum'] ?? null,
    'pdftext_options' => $document['metadata']['pdftext_options'],
    'page_ref_synthesized' => ($selectedPage['pdftext_source']['refs'][0]['url'] ?? null) === '#page-5-1',
    'safe_span_link_promoted' => ($selectedPage['blocks'][0]['lines'][0]['spans'][1]['url'] ?? null) === 'https://example.com/object-envelope',
    'object_envelope_payload_excluded' => !str_contains($encoded, 'object envelope metadata payload')
        && !str_contains($encoded, 'object envelope top-level payload'),
    'skipped_pages_excluded' => !str_contains($encoded, 'Skipped cover')
        && !str_contains($encoded, 'Skipped appendix'),
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
