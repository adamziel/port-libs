<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextBlockConverter;

$pdftextPage = static function (): array {
    return [
        'page' => 5,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 90,
        'blocks' => [
            [
                'lines' => [
                    [
                        'bbox' => [72.0, 96.0, 360.0, 110.0],
                        'spans' => [
                            [
                                'text' => "Migrat-\nion ",
                                'bbox' => [72.0, 96.0, 132.0, 110.0],
                                'font' => ['name' => 'TimesNewRomanPS', 'flags' => (1 << 1) | (1 << 5), 'weight' => 400, 'size' => 11.0],
                            ],
                            [
                                'text' => "Packet\r\n",
                                'bbox' => [132.0, 96.0, 198.0, 110.0],
                                'font' => ['name' => 'TimesNewRomanPS-Bold', 'flags' => (1 << 1) | (1 << 5) | (1 << 18), 'weight' => 700, 'size' => 11.0],
                            ],
                        ],
                    ],
                    [
                        'bbox' => [72.0, 120.0, 420.0, 134.0],
                        'spans' => [
                            [
                                'text' => 'WordPress keeps extracted spans reviewable.',
                                'bbox' => [72.0, 120.0, 420.0, 134.0],
                                'font' => ['name' => 'Helvetica', 'flags' => null, 'weight' => 400, 'size' => 10.0],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
};

return [
    'decomposes pdf font flags like marker pdf utils' => static function (TestRunner $t): void {
        $converter = new PdfTextBlockConverter();

        $flags = (1 << 0) | (1 << 1) | (1 << 5) | (1 << 6) | (1 << 18);
        $t->same('fixed_pitch_serif_non_symbolic_italic_bold', $converter->fontFlagsDecomposer($flags));
        $t->same('', $converter->fontFlagsDecomposer(null));
    },
    'converts pdftext dictionary pages to upstream marker page blocks' => static function (TestRunner $t) use ($pdftextPage): void {
        $page = (new PdfTextBlockConverter())->pdftextFormatToPage($pdftextPage(), 2);

        $t->same(5, $page['pnum']);
        $t->same([0.0, 0.0, 792.0, 612.0], $page['bbox']);
        $t->same(90, $page['rotation']);
        $t->same(2, count($page['blocks']));
        $t->same($pdftextPage()['blocks'], $page['char_blocks']);

        $firstSpan = $page['blocks'][0]['lines'][0]['spans'][0];
        $secondSpan = $page['blocks'][0]['lines'][0]['spans'][1];
        $t->same('Migration ', $firstSpan['text']);
        $t->same('Packet', $secondSpan['text']);
        $t->same('2_0', $firstSpan['span_id']);
        $t->same('2_1', $secondSpan['span_id']);
        $t->same('TimesNewRomanPS_serif_non_symbolic', $firstSpan['font']);
        $t->same('TimesNewRomanPS-Bold_serif_non_symbolic_bold', $secondSpan['font']);
        $t->same(700.0, $secondSpan['font_weight']);
        $t->same(11.0, $secondSpan['font_size']);
    },
    'keeps upstream span id sequencing when invalid line bboxes are skipped' => static function (TestRunner $t): void {
        $page = (new PdfTextBlockConverter())->pdftextFormatToPage([
            'page' => 0,
            'bbox' => [0.0, 0.0, 200.0, 200.0],
            'rotation' => 0,
            'blocks' => [
                [
                    'lines' => [
                        [
                            'bbox' => [40.0, 40.0, 10.0, 50.0],
                            'spans' => [
                                ['text' => 'Skipped', 'bbox' => [40.0, 40.0, 10.0, 50.0], 'font' => ['name' => 'Bad', 'flags' => null, 'weight' => 400, 'size' => 9]],
                            ],
                        ],
                        [
                            'bbox' => [40.0, 70.0, 140.0, 84.0],
                            'spans' => [
                                ['text' => 'Kept', 'bbox' => [40.0, 70.0, 140.0, 84.0], 'font' => ['name' => 'Good', 'flags' => null, 'weight' => 400, 'size' => 9]],
                            ],
                        ],
                    ],
                ],
            ],
        ], 3);

        $t->same(1, count($page['blocks']));
        $t->same('Kept', $page['blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->same('3_1', $page['blocks'][0]['lines'][0]['spans'][0]['span_id']);
    },
    'normalizes real pdftext dictionary font nulls and span metadata' => static function (TestRunner $t): void {
        $page = (new PdfTextBlockConverter())->pdftextFormatToPage([
            'page' => 4,
            'bbox' => [0.0, 0.0, 200.0, 300.0],
            'rotation' => 270,
            'blocks' => [[
                'lines' => [[
                    'bbox' => [20.0, 30.0, 180.0, 44.0],
                    'spans' => [[
                        'text' => "Dictionary core\r\n",
                        'bbox' => [20.0, 30.0, 180.0, 44.0],
                        'font' => ['name' => null, 'flags' => (1 << 6) | (1 << 18), 'weight' => 700, 'size' => 12.5],
                        'rotation' => 270,
                        'char_start_idx' => 31,
                        'char_end_idx' => 46,
                        'chars' => [['c' => 'D', 'bbox' => [20.0, 30.0, 26.0, 44.0]]],
                    ]],
                ]],
            ]],
        ], 0);

        $span = $page['blocks'][0]['lines'][0]['spans'][0];
        $t->same([0.0, 0.0, 300.0, 200.0], $page['bbox']);
        $t->same('Dictionary core', $span['text']);
        $t->same('None_italic_bold', $span['font']);
        $t->same(700.0, $span['font_weight']);
        $t->same(12.5, $span['font_size']);
        $t->same(270, $span['rotation']);
        $t->same(31, $span['char_start_idx']);
        $t->same(46, $span['char_end_idx']);
        $t->same([['c' => 'D', 'bbox' => [20.0, 30.0, 26.0, 44.0]]], $span['chars']);
        $t->same($page['char_blocks'][0]['lines'][0]['spans'][0]['chars'], $span['chars']);
    },
    'requires pdftext span text strings at the core dictionary boundary' => static function (TestRunner $t) use ($pdftextPage): void {
        $converter = new PdfTextBlockConverter();

        $missingText = $pdftextPage();
        unset($missingText['blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->throws(InvalidArgumentException::class, static fn () => $converter->pdftextFormatToPage($missingText, 0));

        $numericText = $pdftextPage();
        $numericText['blocks'][0]['lines'][0]['spans'][0]['text'] = 1234;
        $t->throws(InvalidArgumentException::class, static fn () => $converter->pdftextFormatToPage($numericText, 0));
    },
    'rejects malformed pdftext dictionaries at the core boundary' => static function (TestRunner $t): void {
        $converter = new PdfTextBlockConverter();

        $t->throws(InvalidArgumentException::class, static fn () => $converter->pdftextFormatToPage([
            'page' => 0,
            'bbox' => [0.0, 0.0, 200.0],
            'rotation' => 0,
            'blocks' => [],
        ], 0));

        $t->throws(InvalidArgumentException::class, static fn () => $converter->pdftextFormatToPage([
            'page' => 0,
            'bbox' => [0.0, 0.0, 200.0, 200.0],
            'rotation' => 0,
            'blocks' => [[
                'lines' => [[
                    'bbox' => [20.0, 30.0, 120.0, 44.0],
                    'spans' => [[
                        'text' => 'Missing font',
                        'bbox' => [20.0, 30.0, 120.0, 44.0],
                    ]],
                ]],
            ]],
        ], 0));

        $t->throws(InvalidArgumentException::class, static fn () => $converter->pdftextFormatToPage([
            'page' => 0,
            'bbox' => [0.0, 0.0, 200.0, 200.0],
            'rotation' => 0,
            'blocks' => [[
                'lines' => [[
                    'bbox' => [20.0, 30.0, 120.0, 44.0],
                    'spans' => [[
                        'text' => 'Bad span bbox',
                        'bbox' => [20.0, 'top', 120.0, 44.0],
                        'font' => ['name' => 'Bad', 'flags' => null, 'weight' => 400, 'size' => 9],
                    ]],
                ]],
            ]],
        ], 0));
    },
    'renders converted pdftext page spans as WordPress blocks' => static function (TestRunner $t) use ($pdftextPage): void {
        $page = (new PdfTextBlockConverter())->pdftextFormatToPage($pdftextPage(), 0);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans([$page]));
        $html = '';

        foreach ($blocks as $block) {
            $html .= "<!-- wp:paragraph -->\n";
            $html .= '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
            $html .= "<!-- /wp:paragraph -->\n";
        }

        $t->contains('<p>Migration Packet WordPress keeps extracted spans reviewable.</p>', $html);
        $t->true(!str_contains($html, "Migrat-\nion"), 'Hyphenated pdftext spans should be normalized before block rendering.');
    },
];
