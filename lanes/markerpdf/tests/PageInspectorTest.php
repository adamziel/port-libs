<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PageInspector;
use PortLibs\MarkerPDF\PdfTextBlockConverter;

$samplePage = static function (): array {
    return [
        'pnum' => 3,
        'blocks' => [
            [
                'block_type' => 'Title',
                'lines' => [
                    [
                        'bbox' => [72.0, 48.0, 300.0, 68.0],
                        'spans' => [
                            ['text' => 'Migration ', 'font_size' => 18.0],
                            ['text' => 'Packet', 'font_size' => 18.0],
                        ],
                    ],
                    [
                        'bbox' => [72.0, 76.0, 120.0, 88.0],
                        'spans' => [
                            ['text' => '   ', 'font_size' => 12.0],
                        ],
                    ],
                ],
            ],
            [
                'block_type' => 'Text',
                'lines' => [
                    [
                        'bbox' => [72.0, 112.0, 460.0, 126.0],
                        'spans' => [
                            ['text' => 'Imported page text.', 'font_size' => 11.0],
                            ['text' => '', 'font_size' => 11.0],
                        ],
                    ],
                ],
            ],
        ],
    ];
};

return [
    'mirrors marker page helpers for lines spans fonts heights and prelim text' => static function (TestRunner $t) use ($samplePage): void {
        $inspector = new PageInspector();
        $page = $samplePage();

        $t->same(3, count($inspector->getAllLines($page)));
        $t->same(2, count($inspector->getNonblankLines($page)));
        $t->same(['Migration ', 'Packet', 'Imported page text.'], array_column($inspector->getNonblankSpans($page), 'text'));
        $t->same([18.0, 18.0, 11.0], $inspector->getFontSizes($page));
        $t->same([20.0, 14.0], $inspector->getLineHeights($page));
        $t->same("Migration Packet\n   \nImported page text.", $inspector->prelimText($page));
    },
    'uses explicit prelim text fallbacks for supplied OCR pages' => static function (TestRunner $t): void {
        $page = [
            'blocks' => [
                ['prelim_text' => 'Recovered OCR paragraph.'],
                ['lines' => [
                    ['prelim_text' => '  '],
                    ['prelim_text' => 'Second OCR line.', 'bbox' => [20.0, 30.0, 220.0, 44.0]],
                ]],
            ],
        ];

        $inspector = new PageInspector();

        $t->same("Recovered OCR paragraph.\n  \nSecond OCR line.", $inspector->prelimText($page));
        $t->same(1, count($inspector->getNonblankLines($page)));
        $t->same([14.0], $inspector->getLineHeights($page));
        $t->same([], $inspector->getFontSizes($page));
    },
    'inspects converted pdftext pages before WordPress block rendering' => static function (TestRunner $t): void {
        $pdftextPage = [
            'page' => 5,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [[
                'lines' => [
                    [
                        'bbox' => [72.0, 90.0, 420.0, 106.0],
                        'spans' => [[
                            'text' => 'Data liberation inspection',
                            'bbox' => [72.0, 90.0, 420.0, 106.0],
                            'font' => ['name' => 'Heading-Bold', 'flags' => 1 << 18, 'weight' => 700, 'size' => 16.0],
                        ]],
                    ],
                    [
                        'bbox' => [72.0, 126.0, 420.0, 138.0],
                        'spans' => [[
                            'text' => "reviewable para-\ngraph text",
                            'bbox' => [72.0, 126.0, 420.0, 138.0],
                            'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                        ]],
                    ],
                ],
            ]],
        ];

        $page = (new PdfTextBlockConverter())->pdftextFormatToPage($pdftextPage, 0);
        $inspector = new PageInspector();
        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans([$page]));

        $t->same('Data liberation inspection', $inspector->getNonblankLines($page)[0]['spans'][0]['text']);
        $t->same([16.0, 11.0], $inspector->getFontSizes($page));
        $t->same([16.0, 12.0], $inspector->getLineHeights($page));
        $t->same('Data liberation inspection reviewable paragraph text', $blocks[0]['text']);
    },
    'builds WordPress review metadata from page helper metrics' => static function (TestRunner $t) use ($samplePage): void {
        $inspector = new PageInspector();
        $page = $samplePage();

        $metadata = [
            'markerpdfPage' => $page['pnum'],
            'nonblankLines' => count($inspector->getNonblankLines($page)),
            'nonblankSpans' => count($inspector->getNonblankSpans($page)),
            'fontSizes' => array_values(array_unique($inspector->getFontSizes($page))),
            'lineHeights' => array_values(array_unique($inspector->getLineHeights($page))),
            'prelimText' => $inspector->prelimText($page),
        ];

        $html = '<!-- wp:paragraph {"metadata":' . json_encode($metadata, JSON_UNESCAPED_SLASHES) . '} -->'
            . '<p>' . htmlspecialchars($metadata['prelimText'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<!-- /wp:paragraph -->';

        $t->contains('"nonblankLines":2', $html);
        $t->contains('"fontSizes":[18,11]', $html);
        $t->contains('Migration Packet', $html);
        $t->contains('Imported page text.', $html);
    },
];
