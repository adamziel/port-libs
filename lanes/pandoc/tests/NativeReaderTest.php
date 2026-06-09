<?php

declare(strict_types=1);

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
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
];
