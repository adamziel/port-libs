<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'round trips pandoc native json metadata constructors without loss' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => [
                    't' => 'MetaInlines',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Quarterly'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Review'],
                    ],
                ],
                'draft' => ['t' => 'MetaBool', 'c' => false],
                'tags' => [
                    't' => 'MetaList',
                    'c' => [
                        ['t' => 'MetaString', 'c' => 'wp-import'],
                        ['t' => 'MetaString', 'c' => 'native-ast'],
                    ],
                ],
                'review' => [
                    't' => 'MetaMap',
                    'c' => [
                        'source' => ['t' => 'MetaString', 'c' => 'native-json'],
                        'priority' => ['t' => 'MetaString', 'c' => 'high'],
                    ],
                ],
            ],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Body'],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('document', $document->type);
        $t->same('pandoc-json', $document->attr('nativeFormat'));
        $t->same($native['pandoc-api-version'], $document->attr('pandocApiVersion'));
        $t->same($native['meta'], $document->attr('meta'));
        $t->same('Para', $document->children[0]->attr('constructor'));
        $t->same($native['meta'], $roundTrip['meta']);
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->same($native, $roundTrip);
    },
    'round trips markdown paragraph inlines through pandoc native ast json' => static function (TestRunner $t): void {
        $markdown = "Native *AST* **roundtrip** with `code` and [link](https://example.test/source)\nnext line.";
        $document = (new MarkdownReader())->read($markdown);

        $nativeJson = (new NativeWriter())->write($document);
        $native = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        $nativeInlineTypes = array_map(
            static fn (array $inline): string => $inline['t'],
            $native['blocks'][0]['c']
        );

        $roundTrip = (new NativeReader())->read($nativeJson);
        $roundTripMarkdown = (new MarkdownWriter())->write($roundTrip);

        $t->same('Para', $native['blocks'][0]['t']);
        $t->same([
            'Str',
            'Space',
            'Emph',
            'Space',
            'Strong',
            'Space',
            'Str',
            'Space',
            'Code',
            'Space',
            'Str',
            'Space',
            'Link',
            'SoftBreak',
            'Str',
            'Space',
            'Str',
        ], $nativeInlineTypes);
        $t->same('paragraph', $roundTrip->children[0]->type);
        $t->same('Native AST roundtrip with code and link next line.', $roundTrip->children[0]->attr('text'));
        $t->same($markdown, $roundTripMarkdown);
    },
    'maps native ast table captions into shared table metadata' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', ['native-review'], [['data-source', 'batch-52']]],
                [
                    [
                        ['t' => 'Str', 'c' => 'Short'],
                        ['t' => 'Space'],
                        ['t' => 'Strong', 'c' => [
                            ['t' => 'Str', 'c' => 'queue'],
                        ]],
                    ],
                    [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Long'],
                            ['t' => 'Space'],
                            ['t' => 'Emph', 'c' => [
                                ['t' => 'Str', 'c' => 'caption'],
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Link', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Str', 'c' => 'reviewer'],
                                ],
                                ['https://example.test/review', 'Review'],
                            ]],
                        ]],
                    ],
                ],
                [
                    [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => 0.25]],
                    [['t' => 'AlignLeft'], ['t' => 'ColWidthDefault']],
                ],
                [
                    ['', [], []],
                    [
                        [
                            ['', [], []],
                            [
                                [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    1,
                                    1,
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'Metric'],
                                        ]],
                                    ],
                                ],
                                [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    1,
                                    1,
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'State'],
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    [
                        ['', ['body-source'], []],
                        1,
                        [],
                        [
                            [
                                ['', [], []],
                                [
                                    [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        1,
                                        1,
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Posts'],
                                            ]],
                                        ],
                                    ],
                                    [
                                        ['', [], []],
                                        ['t' => 'AlignCenter'],
                                        1,
                                        1,
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Ready'],
                                            ]],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    ['', [], []],
                    [],
                ],
            ],
        ];

        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $body = $table->children[1];
        $captionBlocks = $table->attr('captionBlocks');
        $captionInlines = $table->attr('captionInlines');
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('table', $table->type);
        $t->same('Table', $table->attr('constructor'));
        $t->same(['native-review'], $table->attr('classes'));
        $t->same(['data-source' => 'batch-52'], $table->attr('attributes'));
        $t->same('Long caption reviewer', $table->attr('caption'));
        $t->same('Short queue', $table->attr('shortCaption'));
        $t->same(['right', 'left'], $table->attr('alignments'));
        $t->same([0.25, null], $table->attr('widths'));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same('center', $body->children[0]->children[1]->attr('align'));
        $t->same(true, is_array($captionBlocks));
        $t->same('paragraph', $captionBlocks[0]->type);
        $t->same(true, is_array($captionInlines));
        $t->same(['text', 'emph', 'text', 'link'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $shortCaptionInlines));
        $t->same($nativeTable, $roundTrip['blocks'][0]);
        $t->contains(': [Short **queue**] Long *caption* [reviewer](https://example.test/review "Review")', $markdown);
        $t->contains('<figcaption class="wp-element-caption"><p>Long <em>caption</em> <a href="https://example.test/review" title="Review">reviewer</a></p></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
    },
    'accepts constructor wrapped native table captions for review handoff' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', ['constructor-caption'], []],
                [
                    't' => 'Caption',
                    'c' => [
                        [
                            't' => 'Just',
                            'c' => [
                                't' => 'ShortCaption',
                                'c' => [[
                                    ['t' => 'Str', 'c' => 'Queue'],
                                    ['t' => 'Space'],
                                    ['t' => 'Emph', 'c' => [
                                        ['t' => 'Str', 'c' => 'short'],
                                    ]],
                                ]],
                            ],
                        ],
                        [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'Wrapped'],
                                ['t' => 'Space'],
                                ['t' => 'Strong', 'c' => [
                                    ['t' => 'Str', 'c' => 'caption'],
                                ]],
                            ]],
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'Second'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'line'],
                            ]],
                        ],
                    ],
                ],
                [
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [
                    ['', [], []],
                    [],
                ]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Cell'],
                                            ]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [
                    ['', [], []],
                    [],
                ]],
            ],
        ];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('table', $table->type);
        $t->same(['constructor-caption'], $table->attr('classes'));
        $t->same('Queue short', $table->attr('shortCaption'));
        $t->same('Wrapped caption' . "\n" . 'Second line', $table->attr('caption'));
        $t->same(['paragraph', 'plain'], array_map(static fn (AstNode $node): string => $node->type, $table->attr('captionBlocks')));
        $t->same([], $table->attr('captionInlines', []));
        $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $table->attr('shortCaptionInlines')));
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same(2, $packet['captions']['long']['blockCount'] ?? null);
        $t->same(['paragraph', 'plain'], $packet['captions']['long']['blockTypes'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same(['text', 'emph'], $packet['captions']['short']['inlineTypes'] ?? null);
        $t->contains('data-pandoc-short-caption="Queue short"', $blocks);
        $t->contains('<strong>caption</strong>', $blocks);
        $t->same($nativeTable, $roundTrip['blocks'][0]);
    },
    'writes shared table captions as pandoc native ast json' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('table', [
                'id' => 'writer-table',
                'classes' => ['native-generated'],
                'attributes' => ['data-source' => 'writer'],
                'captionInlines' => [
                    new AstNode('text', ['text' => 'Generated']),
                    new AstNode('space'),
                    new AstNode('emph', [], [
                        new AstNode('text', ['text' => 'caption']),
                    ]),
                ],
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Short']),
                    new AstNode('space'),
                    new AstNode('strong', [], [
                        new AstNode('text', ['text' => 'view']),
                    ]),
                ],
                'alignments' => ['left', 'right'],
                'widths' => [0.3, null],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'Metric']),
                        ]),
                        new AstNode('table_cell', ['align' => 'right'], [
                            new AstNode('text', ['text' => 'Value']),
                        ]),
                    ]),
                ]),
                new AstNode('table_body', ['rowHeadColumns' => 1], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('strong', [], [
                                new AstNode('text', ['text' => 'Posts']),
                            ]),
                        ]),
                        new AstNode('table_cell', ['align' => 'center'], [
                            new AstNode('text', ['text' => 'Ready']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $roundTrip->children[0];

        $t->same('Table', $native['blocks'][0]['t']);
        $t->same(['writer-table', ['native-generated'], [['data-source', 'writer']]], $native['blocks'][0]['c'][0]);
        $t->same('Short', $native['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Generated', $native['blocks'][0]['c'][1][1][0]['c'][0]['c']);
        $t->same('AlignLeft', $native['blocks'][0]['c'][2][0][0]['t']);
        $t->same('ColWidth', $native['blocks'][0]['c'][2][0][1]['t']);
        $t->same(0.3, $native['blocks'][0]['c'][2][0][1]['c']);
        $t->same(1, $native['blocks'][0]['c'][4][0][1]);
        $t->same('table', $table->type);
        $t->same('Generated caption', $table->attr('caption'));
        $t->same('Short view', $table->attr('shortCaption'));
        $t->same(['left', 'right'], $table->attr('alignments'));
        $t->same([0.3, null], $table->attr('widths'));
        $t->same('center', $table->children[1]->children[0]->children[1]->attr('align'));
    },
    'writes shared table short caption blocks as pandoc native ast inlines' => static function (TestRunner $t): void {
        $sourceTable = new AstNode('table', [
            'captionBlocks' => [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Block']),
                    new AstNode('space'),
                    new AstNode('strong', [], [
                        new AstNode('text', ['text' => 'long']),
                    ]),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'caption']),
                ]),
            ],
            'shortCaptionBlocks' => [
                new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Queue']),
                    new AstNode('space'),
                    new AstNode('emph', [], [
                        new AstNode('text', ['text' => 'short']),
                    ]),
                ]),
            ],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [], [
                        new AstNode('text', ['text' => 'Cell']),
                    ]),
                ]),
            ]),
        ]);
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [$sourceTable]);

        $sourcePacket = TableGeometry::reviewPacket($sourceTable, ['accessibility' => false]);
        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $roundTrip->children[0];
        $roundTripPacket = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('shortCaptionBlocks', $sourcePacket['captions']['short']['source'] ?? null);
        $t->same('Queue', $native['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Space', $native['blocks'][0]['c'][1][0][1]['t']);
        $t->same('Emph', $native['blocks'][0]['c'][1][0][2]['t']);
        $t->same('Para', $native['blocks'][0]['c'][1][1][0]['t']);
        $t->same('Block long caption', $table->attr('caption'));
        $t->same('Queue short', $table->attr('shortCaption'));
        $t->same('shortCaptionInlines', $roundTripPacket['captions']['short']['source'] ?? null);
        $t->same(['text', 'emph'], $roundTripPacket['captions']['short']['inlineTypes'] ?? null);
    },
];
