<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$textPage = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => [[
                'bbox' => [72.0, 96.0, 420.0, 110.0],
                'spans' => [[
                    'text' => $text,
                    'bbox' => [72.0, 96.0, 420.0, 110.0],
                    'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                ]],
            ]],
        ]],
    ];
};

$blankPage = [
    'page' => 12,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'width' => 612,
    'height' => 792,
    'total_chars' => 0,
    'blocks' => [],
];

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [
        $textPage(11, 'Skipped cover text'),
        $blankPage,
        $textPage(13, 'Skipped appendix text'),
    ],
    maxPages: 1,
    startPage: 1,
    toc: [['title' => 'Blank selected page', 'level' => 1, 'page_index' => 12]]
);

$processor = new MarkdownPostProcessor();
$mergedPages = $processor->mergeSpans($document['pages']);
$blocks = $processor->mergeBlocks($mergedPages);
$paginatedBlocks = $processor->mergeBlocks($mergedPages, paginateOutput: true);

echo '<!-- markerpdf:pdftext-dictionary-blank-page ' . htmlspecialchars(json_encode([
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_pdftext' => false,
    'selected_page_range' => $document['page_range'],
    'selected_pdftext_page' => $document['pages'][0]['pnum'],
    'selected_blocks' => count($document['pages'][0]['blocks']),
    'selected_char_blocks' => count($document['pages'][0]['char_blocks']),
    'visible_wordpress_blocks' => count($blocks),
    'paginated_page_start' => $paginatedBlocks[0]['page_start'] ?? false,
    'paginated_page_number' => $paginatedBlocks[0]['pnum'] ?? null,
    'skipped_cover_excluded' => !str_contains(json_encode($document, JSON_UNESCAPED_SLASHES) ?: '', 'Skipped cover text'),
    'skipped_appendix_excluded' => !str_contains(json_encode($document, JSON_UNESCAPED_SLASHES) ?: '', 'Skipped appendix text'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
