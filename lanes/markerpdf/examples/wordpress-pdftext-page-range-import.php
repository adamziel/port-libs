<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $text): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [
            [
                'lines' => [
                    [
                        'bbox' => [72.0, 96.0, 460.0, 110.0],
                        'spans' => [[
                            'text' => $text,
                            'bbox' => [72.0, 96.0, 460.0, 110.0],
                            'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                        ]],
                    ],
                ],
            ],
        ],
    ];
};

$document = (new PdfTextDocumentExtractor())->getTextBlocks(
    [
        $page(0, 'Cover sheet for internal review'),
        $page(1, "Data liberation page-range import keeps only requested docu-\nment text."),
        $page(2, 'Appendix material held for later review'),
    ],
    maxPages: 1,
    startPage: 1,
    toc: [
        ['title' => 'Cover', 'level' => 1, 'page_index' => 0],
        ['title' => 'Data liberation', 'level' => 1, 'page_index' => 1],
    ]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

echo '<!-- markerpdf:page-range ' . htmlspecialchars(json_encode($document['page_range'], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo '<!-- markerpdf:pdf-toc ' . htmlspecialchars(json_encode($document['metadata']['pdf_toc'], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) $block['pnum'] . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
