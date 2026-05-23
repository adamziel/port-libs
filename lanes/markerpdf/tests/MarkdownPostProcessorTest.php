<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'joins hyphenated text lines like marker markdown postprocessing' => static function (TestRunner $t): void {
        $markdown = (new MarkdownPostProcessor())->mergeLines([
            'Clean hyphen-',
            'ated PDF lines continue',
            'into one paragraph.',
        ]);

        $t->same('Clean hyphenated PDF lines continue into one paragraph.', $markdown);
    },
    'keeps sentence boundaries as markdown paragraphs' => static function (TestRunner $t): void {
        $markdown = (new MarkdownPostProcessor())->mergeLines([
            'First imported sentence.',
            'Second imported sentence.',
        ]);

        $t->same("First imported sentence.\n\nSecond imported sentence.", $markdown);
    },
    'surrounds headings and escapes markdown-sensitive hash characters' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();

        $t->same("\n## Data Liberation Notes\n", $processor->surroundBlock('data liberation notes', 'Section-header', 2));
        $t->same(
            '# Perspective-Free Counting' . "\n",
            $processor->surroundBlock('Perspective-Free Counting', 'Title')
        );
        $t->same('Use \#tags in imported captions', $processor->surroundBlock('Use #tags in imported captions', 'Text'));
        $t->same("Item with \\#anchor\n", $processor->surroundBlock('Item with #anchor', 'List-item'));
    },
    'merges upstream spans into block-ready lines with fonts and emphasis markers' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();
        $merged = $processor->mergeSpans([
            [
                'pnum' => 6,
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'blocks' => [
                    [
                        'block_type' => 'Text',
                        'bbox' => [72.0, 96.0, 420.0, 126.0],
                        'lines' => [
                            [
                                'bbox' => [72.0, 96.0, 420.0, 110.0],
                                'spans' => [
                                    ['text' => 'Keep ', 'font' => 'Helvetica'],
                                    ['text' => 'media captions', 'font' => 'Helvetica-Bold', 'bold' => true],
                                    ['text' => ' reviewable', 'font' => 'Helvetica'],
                                ],
                            ],
                            [
                                'bbox' => [72.0, 112.0, 420.0, 126.0],
                                'spans' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $t->same('Keep **media captions** reviewable', $merged[0][0]['lines'][0]['text']);
        $t->same(['helvetica', 'helvetica-bold', 'helvetica'], $merged[0][0]['lines'][0]['fonts']);
        $t->same(6, $merged[0][0]['pnum']);
        $t->same('Text', $merged[0][0]['block_type']);
        $t->same([72.0, 96.0, 420.0, 126.0], $merged[0][0]['bbox']);
        $t->same(1, count($merged[0][0]['lines']));
    },
    'keeps upstream merge spans first last and next-span guard behavior' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();
        $merged = $processor->mergeSpans([
            [
                'pnum' => 1,
                'bbox' => [0.0, 0.0, 200.0, 200.0],
                'blocks' => [
                    [
                        'type' => 'Text',
                        'lines' => [
                            [
                                'bbox' => [10.0, 10.0, 190.0, 22.0],
                                'spans' => [
                                    ['text' => 'Bold start', 'font' => 'Serif-Bold', 'bold' => true],
                                    ['text' => ' with ', 'font' => 'Serif'],
                                    ['text' => 'two ', 'font' => 'Serif-Bold', 'bold' => true],
                                    ['text' => '!', 'font' => 'Serif-Bold', 'bold' => true],
                                    ['text' => 'words', 'font' => 'Serif-Bold', 'bold' => true],
                                    ['text' => ' normal', 'font' => 'Serif'],
                                    ['text' => ' Italic end', 'font' => 'Serif-Italic', 'italic' => true],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $t->same('Bold start with two !**words** normal Italic end', $merged[0][0]['lines'][0]['text']);
    },
    'creates an upstream empty-page fallback merged text block' => static function (TestRunner $t): void {
        $merged = (new MarkdownPostProcessor())->mergeSpans([
            [
                'pnum' => 9,
                'bbox' => [0.0, 0.0, 612.0, 792.0],
                'blocks' => [
                    ['type' => 'Text', 'lines' => [['bbox' => [72.0, 72.0, 140.0, 84.0], 'spans' => []]]],
                ],
            ],
        ]);

        $t->same(
            [[
                [
                    'lines' => [],
                    'pnum' => 9,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                    'block_type' => 'Text',
                    'heading_level' => null,
                ],
            ]],
            $merged
        );
    },
    'renders merged styled spans as a WordPress paragraph import' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();
        $pages = $processor->mergeSpans([
            [
                'pnum' => 0,
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'blocks' => [
                    [
                        'type' => 'Text',
                        'lines' => [
                            [
                                'bbox' => [72.0, 96.0, 460.0, 112.0],
                                'spans' => [
                                    ['text' => 'Imported ', 'font' => 'Helvetica'],
                                    ['text' => 'media captions', 'font' => 'Helvetica-Bold', 'bold' => true],
                                    ['text' => ' stay ', 'font' => 'Helvetica'],
                                    ['text' => 'reviewable', 'font' => 'Helvetica-Italic', 'italic' => true],
                                    ['text' => ' during import.', 'font' => 'Helvetica'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $blocks = $processor->mergeBlocks($pages);
        $html = htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html) ?? $html;

        $t->same(
            "<!-- wp:paragraph -->\n<p>Imported <strong>media captions</strong> stay <em>reviewable</em> during import.</p>\n<!-- /wp:paragraph -->\n",
            "<!-- wp:paragraph -->\n<p>{$html}</p>\n<!-- /wp:paragraph -->\n"
        );
    },
    'dewraps extracted PDF lines for WordPress import paragraphs' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Clean hyphen-) Tj T* (ated paragraphs keep) Tj T* (WordPress imports readable.) Tj ET';
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $markdown = (new MarkdownPostProcessor())->mergeLines($lines);

        $t->same('Clean hyphenated paragraphs keep WordPress imports readable.', $markdown);
    },
    'merges upstream page blocks across block type transitions' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();
        $merged = $processor->mergeBlocks([
            [
                'pnum' => 0,
                'blocks' => [
                    [
                        'type' => 'Title',
                        'heading_level' => 1,
                        'lines' => [
                            ['text' => 'migration guide', 'bbox' => [72.0, 40.0, 240.0, 62.0]],
                        ],
                    ],
                    [
                        'type' => 'Text',
                        'lines' => [
                            ['text' => 'First imported sentence.', 'bbox' => [72.0, 84.0, 230.0, 96.0]],
                            ['text' => 'Second imported sentence.', 'bbox' => [72.0, 112.0, 248.0, 124.0]],
                        ],
                    ],
                    [
                        'type' => 'List-item',
                        'lines' => [
                            ['text' => '- Review media', 'bbox' => [90.0, 148.0, 220.0, 160.0]],
                        ],
                    ],
                ],
            ],
        ]);

        $t->same(['Title', 'Text', 'List-item'], array_column($merged, 'block_type'));
        $t->same("# Migration Guide\n", $merged[0]['text']);
        $t->same("First imported sentence.\n\nSecond imported sentence.", $merged[1]['text']);
        $t->same("- Review media\n", $merged[2]['text']);
        $t->same(
            "# Migration Guide\n\nFirst imported sentence.\n\nSecond imported sentence.\n\n- Review media\n",
            $processor->getFullText($merged)
        );
    },
    'uses upstream continuation geometry when merging ambiguous text lines' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();
        $merged = $processor->mergeBlocks([
            [
                [
                    'type' => 'Text',
                    'pnum' => 4,
                    'lines' => [
                        ['text' => 'wp-content/', 'bbox' => [72.0, 100.0, 148.0, 112.0]],
                        ['text' => 'uploads import path', 'bbox' => [72.0, 122.0, 210.0, 134.0]],
                    ],
                ],
            ],
        ]);

        $t->same('wp-content/ uploads import path', $merged[0]['text']);
        $t->same(4, $merged[0]['pnum']);
    },
    'emits page-start blocks and full text pagination markers' => static function (TestRunner $t): void {
        $processor = new MarkdownPostProcessor();
        $merged = $processor->mergeBlocks([
            [
                'pnum' => 3,
                'blocks' => [
                    [
                        'type' => 'Text',
                        'lines' => [
                            ['text' => 'Page one summary.', 'bbox' => [72.0, 72.0, 180.0, 84.0]],
                        ],
                    ],
                ],
            ],
            [
                'pnum' => 4,
                'blocks' => [
                    [
                        'type' => 'Text',
                        'lines' => [
                            ['text' => 'Page two summary.', 'bbox' => [72.0, 72.0, 180.0, 84.0]],
                        ],
                    ],
                ],
            ],
        ], paginateOutput: true);

        $t->same([true, false, true, false], array_column($merged, 'page_start'));
        $t->same([3, 3, 4, 4], array_column($merged, 'pnum'));
        $fullText = $processor->getFullText($merged, "\n--- page ---\n");
        $t->contains("{3}\n--- page ---", $fullText);
        $t->contains("{4}\n--- page ---", $fullText);
        $t->contains('Page one summary.', $fullText);
        $t->contains('Page two summary.', $fullText);
    },
];
