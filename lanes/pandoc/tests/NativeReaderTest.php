<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\LatexWriter;
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
    'normalizes legacy pandoc native json unMeta document arrays' => static function (TestRunner $t): void {
        $legacy = [
            [
                'unMeta' => [
                    'title' => [
                        't' => 'MetaInlines',
                        'c' => [
                            ['t' => 'Str', 'c' => 'Legacy'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'Native'],
                        ],
                    ],
                    'review' => [
                        't' => 'MetaMap',
                        'c' => [
                            'source' => ['t' => 'MetaString', 'c' => 'pre-1.18-filter'],
                        ],
                    ],
                ],
            ],
            [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Legacy'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'native'],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($legacy, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('document', $document->type);
        $t->same('pandoc-json', $document->attr('nativeFormat'));
        $t->same($legacy[0]['unMeta'], $document->attr('meta'));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Legacy native', $document->children[0]->attr('text'));
        $t->same($legacy[0]['unMeta'], $roundTrip['meta']);
        $t->same($legacy[1], $roundTrip['blocks']);
        $t->same([1, 23, 1], $roundTrip['pandoc-api-version']);
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
    'writes shared inline constructors as pandoc native ast constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('underline', [], [new AstNode('text', ['text' => 'under'])]),
                new AstNode('strikeout', [], [new AstNode('text', ['text' => 'old'])]),
                new AstNode('superscript', [], [new AstNode('text', ['text' => '2'])]),
                new AstNode('subscript', [], [new AstNode('text', ['text' => 'n'])]),
                new AstNode('small_caps', [], [new AstNode('text', ['text' => 'caps'])]),
                new AstNode('quoted', ['kind' => 'single'], [new AstNode('text', ['text' => 'quote'])]),
                new AstNode('math', ['display' => true, 'text' => 'E = mc^2']),
                new AstNode('raw_html_inline', ['html' => '<span data-review="raw">html</span>']),
                new AstNode('raw_tex', ['tex' => '\\alpha']),
                new AstNode('raw_markdown', ['format' => 'markdown+tex_math_dollars', 'markdown' => '$raw$']),
                new AstNode('raw_inline', ['format' => 'opml', 'text' => '<outline/>']),
                new AstNode('span', [
                    'id' => 'review-span',
                    'classes' => ['native-review'],
                    'attributes' => ['data-source' => 'shared-ast'],
                ], [new AstNode('text', ['text' => 'span'])]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $nativeInlines = $native['blocks'][0]['c'];
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTripChildren = $roundTrip->children[0]->children;

        $t->same([
            'Underline',
            'Strikeout',
            'Superscript',
            'Subscript',
            'SmallCaps',
            'Quoted',
            'Math',
            'RawInline',
            'RawInline',
            'RawInline',
            'RawInline',
            'Span',
        ], array_map(static fn (array $inline): string => $inline['t'], $nativeInlines));
        $t->same('SingleQuote', $nativeInlines[5]['c'][0]['t']);
        $t->same('DisplayMath', $nativeInlines[6]['c'][0]['t']);
        $t->same('E = mc^2', $nativeInlines[6]['c'][1]);
        $t->same('html', $nativeInlines[7]['c'][0]);
        $t->same('<span data-review="raw">html</span>', $nativeInlines[7]['c'][1]);
        $t->same('latex', $nativeInlines[8]['c'][0]);
        $t->same('\\alpha', $nativeInlines[8]['c'][1]);
        $t->same('markdown+tex_math_dollars', $nativeInlines[9]['c'][0]);
        $t->same('$raw$', $nativeInlines[9]['c'][1]);
        $t->same('opml', $nativeInlines[10]['c'][0]);
        $t->same(['review-span', ['native-review'], [['data-source', 'shared-ast']]], $nativeInlines[11]['c'][0]);
        $t->same([
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'math',
            'raw_html_inline',
            'raw_tex',
            'raw_markdown',
            'raw_inline',
            'span',
        ], array_map(static fn (AstNode $node): string => $node->type, $roundTripChildren));
        $t->same('single', $roundTripChildren[5]->attr('kind'));
        $t->same(true, $roundTripChildren[6]->attr('display'));
        $t->same('<span data-review="raw">html</span>', $roundTripChildren[7]->attr('html'));
        $t->same('\\alpha', $roundTripChildren[8]->attr('tex'));
        $t->same('$raw$', $roundTripChildren[9]->attr('markdown'));
        $t->same('opml', $roundTripChildren[10]->attr('format'));
        $t->same('review-span', $roundTripChildren[11]->attr('id'));
        $t->same(['native-review'], $roundTripChildren[11]->attr('classes'));
        $t->same(['data-source' => 'shared-ast'], $roundTripChildren[11]->attr('attributes'));
    },
    'round trips native image and note inline constructors through shared ast' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Screenshot'],
                        ['t' => 'Space'],
                        ['t' => 'Image', 'c' => [
                            ['native-image', ['asset'], [['data-source', 'media-bag']]],
                            [
                                ['t' => 'Str', 'c' => 'Alt'],
                                ['t' => 'Space'],
                                ['t' => 'Strong', 'c' => [
                                    ['t' => 'Str', 'c' => 'text'],
                                ]],
                            ],
                            ['media/diagram.png', 'Diagram title'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Note', 'c' => [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'Footnote'],
                                ['t' => 'Space'],
                                ['t' => 'Link', 'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Str', 'c' => 'source'],
                                    ],
                                    ['https://example.test/source', 'Source'],
                                ]],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];

        $reader = new NativeReader();
        $writer = new NativeWriter();
        $document = $reader->read(json_encode($native, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];
        $image = $paragraph->children[1];
        $note = $paragraph->children[3];
        $roundTrip = json_decode($writer->write($document), true, 512, JSON_THROW_ON_ERROR);
        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Generated']),
                new AstNode('space'),
                new AstNode('image', [
                    'id' => 'generated-image',
                    'classes' => ['review-asset'],
                    'attributes' => ['data-source' => 'writer'],
                    'url' => 'media/generated.png',
                    'title' => 'Generated title',
                ], [
                    new AstNode('text', ['text' => 'Generated']),
                    new AstNode('space'),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'alt'])]),
                ]),
                new AstNode('space'),
                new AstNode('note', [], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Generated']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'note']),
                    ]),
                ]),
            ]),
        ]);
        $generated = json_decode($writer->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = $reader->read(json_encode($generated, JSON_THROW_ON_ERROR));

        $t->same(['text', 'image', 'text', 'note'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('native-image', $image->attr('id'));
        $t->same(['asset'], $image->attr('classes'));
        $t->same(['data-source' => 'media-bag'], $image->attr('attributes'));
        $t->same('media/diagram.png', $image->attr('url'));
        $t->same('Diagram title', $image->attr('title'));
        $t->same('Alt text', $image->attr('alt'));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $image->children));
        $t->same('paragraph', $note->children[0]->type);
        $t->same('link', $note->children[0]->children[1]->type);
        $t->same('https://example.test/source', $note->children[0]->children[1]->attr('url'));
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->same('Image', $generated['blocks'][0]['c'][2]['t']);
        $t->same(['generated-image', ['review-asset'], [['data-source', 'writer']]], $generated['blocks'][0]['c'][2]['c'][0]);
        $t->same('Strong', $generated['blocks'][0]['c'][2]['c'][1][2]['t']);
        $t->same(['media/generated.png', 'Generated title'], $generated['blocks'][0]['c'][2]['c'][2]);
        $t->same('Note', $generated['blocks'][0]['c'][4]['t']);
        $t->same('Para', $generated['blocks'][0]['c'][4]['c'][0]['t']);
        $t->same('image', $generatedRoundTrip->children[0]->children[1]->type);
        $t->same('Generated alt', $generatedRoundTrip->children[0]->children[1]->attr('alt'));
        $t->same('note', $generatedRoundTrip->children[0]->children[3]->type);
    },
    'maps native raw block and citation constructors into shared ast' => static function (TestRunner $t): void {
        $rawBlock = ['t' => 'RawBlock', 'c' => ['html', '<section data-review="native-raw">Native raw</section>']];
        $citeInline = ['t' => 'Cite', 'c' => [
            [
                [
                    'citationId' => 'smith1899',
                    'citationPrefix' => [
                        ['t' => 'Str', 'c' => 'see'],
                    ],
                    'citationSuffix' => [
                        ['t' => 'Str', 'c' => 'p.'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => '7'],
                    ],
                    'citationMode' => ['t' => 'NormalCitation'],
                    'citationNoteNum' => 0,
                    'citationHash' => 1899,
                ],
                [
                    'citationId' => 'doe1901',
                    'citationPrefix' => [],
                    'citationSuffix' => [],
                    'citationMode' => ['t' => 'AuthorInText'],
                    'citationNoteNum' => 0,
                    'citationHash' => 1901,
                ],
            ],
            [
                ['t' => 'Str', 'c' => '[see'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '@smith1899,'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'p.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '7;'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '@doe1901]'],
            ],
        ]];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                $rawBlock,
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Archive'],
                    ['t' => 'Space'],
                    $citeInline,
                ]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $raw = $document->children[0];
        $paragraph = $document->children[1];
        $cluster = $paragraph->children[1];
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $processed = CitationCslProcessor::fromItems([
            [
                'id' => 'smith1899',
                'type' => 'book',
                'title' => 'Migration Patterns',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[1899]]],
            ],
            [
                'id' => 'doe1901',
                'type' => 'article-journal',
                'title' => 'Import Notes',
                'author' => [
                    ['family' => 'Doe', 'given' => 'Grace'],
                ],
                'issued' => ['date-parts' => [[1901]]],
            ],
        ])->apply($document);
        $blocks = (new WordPressBlockWriter())->write($processed);

        $t->same('raw_html', $raw->type);
        $t->same('<section data-review="native-raw">Native raw</section>', $raw->attr('html'));
        $t->same('citation_group', $cluster->type);
        $t->same('[see @smith1899, p. 7; @doe1901]', $cluster->attr('text'));
        $t->same(['smith1899', 'doe1901'], array_map(static fn (AstNode $node): string => $node->attr('id'), $cluster->children));
        $t->same('see', $cluster->children[0]->attr('prefix')[0]->attr('text'));
        $t->same('author_in_text', $cluster->children[1]->attr('mode'));
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->contains('<section data-review="native-raw">Native raw</section>', $blocks);
        $t->contains('(see Smith 1899, p. 7; Doe (1901))', $blocks);

        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('raw_html', ['html' => '<aside data-review="generated">Generated raw</aside>']),
            new AstNode('paragraph', [], [
                new AstNode('citation_group', [], [
                    new AstNode('citation', [
                        'id' => 'smith1899',
                        'prefix' => [new AstNode('text', ['text' => 'see'])],
                        'suffix' => [
                            new AstNode('text', ['text' => 'p.']),
                            new AstNode('space'),
                            new AstNode('text', ['text' => '8']),
                        ],
                        'citationHash' => 99,
                    ]),
                    new AstNode('citation', [
                        'id' => 'doe1901',
                        'mode' => 'author_in_text',
                    ]),
                ]),
                new AstNode('space'),
                new AstNode('image', [
                    'url' => 'media/generated.png',
                    'title' => 'Generated title',
                    'alt' => 'Generated image',
                    'classes' => ['generated-media'],
                ]),
            ]),
        ]);
        $generated = json_decode((new NativeWriter())->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = (new NativeReader())->read(json_encode($generated, JSON_THROW_ON_ERROR));
        $generatedInlines = $generated['blocks'][1]['c'];

        $t->same('RawBlock', $generated['blocks'][0]['t']);
        $t->same(['html', '<aside data-review="generated">Generated raw</aside>'], $generated['blocks'][0]['c']);
        $t->same(['Cite', 'Space', 'Image'], array_map(static fn (array $inline): string => $inline['t'], $generatedInlines));
        $t->same('see', $generatedInlines[0]['c'][0][0]['citationPrefix'][0]['c']);
        $t->same('p.', $generatedInlines[0]['c'][0][0]['citationSuffix'][0]['c']);
        $t->same('AuthorInText', $generatedInlines[0]['c'][0][1]['citationMode']['t']);
        $t->same('Generated', $generatedInlines[2]['c'][1][0]['c']);
        $t->same('citation_group', $generatedRoundTrip->children[1]->children[0]->type);
        $t->same('image', $generatedRoundTrip->children[1]->children[2]->type);
    },
    'maps core block constructors through pandoc native ast json' => static function (TestRunner $t): void {
        $nativeBlocks = [
            ['t' => 'Header', 'c' => [
                2,
                ['native-heading', ['review'], [['data-kind', 'header']]],
                [
                    ['t' => 'Str', 'c' => 'Review'],
                    ['t' => 'Space'],
                    ['t' => 'Strong', 'c' => [
                        ['t' => 'Str', 'c' => 'source'],
                    ]],
                ],
            ]],
            ['t' => 'CodeBlock', 'c' => [
                ['cli', ['bash'], [['data-review', 'code']]],
                "wp post get 42\nwp post meta list 42",
            ]],
            ['t' => 'BlockQuote', 'c' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Quoted'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'source'],
                ]],
            ]],
            ['t' => 'BulletList', 'c' => [
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Check'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'media'],
                    ]],
                ],
            ]],
            ['t' => 'OrderedList', 'c' => [
                [3, ['t' => 'UpperAlpha'], ['t' => 'OneParen']],
                [
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Review'],
                        ]],
                    ],
                ],
            ]],
            ['t' => 'DefinitionList', 'c' => [
                [
                    [
                        ['t' => 'Str', 'c' => 'source'],
                        ['t' => 'Space'],
                        ['t' => 'Code', 'c' => [
                            ['', ['term-code'], [['data-term', 'source']]],
                            'packet',
                        ]],
                    ],
                    [
                        [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'keeps'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'definition'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'text'],
                            ]],
                            ['t' => 'CodeBlock', 'c' => [
                                ['', ['bash'], []],
                                'wp post meta get 42 _source',
                            ]],
                        ],
                        [
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'alternate'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'definition'],
                            ]],
                        ],
                    ],
                ],
            ]],
            ['t' => 'LineBlock', 'c' => [
                [
                    ['t' => 'Str', 'c' => 'Address'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'line'],
                    ]],
                ],
                [
                    ['t' => 'Code', 'c' => [
                        ['', [], []],
                        'fallback',
                    ]],
                ],
            ]],
            ['t' => 'Div', 'c' => [
                ['packet', ['native-review'], [['data-source', 'core-blocks']]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                    ]],
                ],
            ]],
            ['t' => 'HorizontalRule'],
        ];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $nativeBlocks,
        ];

        $reader = new NativeReader();
        $writer = new NativeWriter();
        $document = $reader->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode($writer->write($document), true, 512, JSON_THROW_ON_ERROR);
        $definitionItem = $document->children[5]->children[0];
        $lineBlock = $document->children[6];

        $t->same([
            'heading',
            'code_block',
            'blockquote',
            'bullet_list',
            'ordered_list',
            'definition_list',
            'line_block',
            'div',
            'horizontal_rule',
        ], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(2, $document->children[0]->attr('level'));
        $t->same('native-heading', $document->children[0]->attr('id'));
        $t->same('Review source', $document->children[0]->attr('text'));
        $t->same(['bash'], $document->children[1]->attr('classes'));
        $t->same("wp post get 42\nwp post meta list 42", $document->children[1]->attr('text'));
        $t->same('Quoted source', $document->children[2]->children[0]->attr('text'));
        $t->same('Check media', $document->children[3]->children[0]->children[0]->attr('text'));
        $t->same(3, $document->children[4]->attr('start'));
        $t->same('upper_alpha', $document->children[4]->attr('style'));
        $t->same('one_paren', $document->children[4]->attr('delimiter'));
        $t->same('source packet', $definitionItem->children[0]->attr('text'));
        $t->same(['term-code'], $definitionItem->children[0]->children[1]->attr('classes'));
        $t->same('wp post meta get 42 _source', $definitionItem->children[1]->children[1]->attr('text'));
        $t->same('Address line', $lineBlock->children[0]->attr('text'));
        $t->same('fallback', $lineBlock->children[1]->attr('text'));
        $t->same('packet', $document->children[7]->attr('id'));
        $t->same(['data-source' => 'core-blocks'], $document->children[7]->attr('attributes'));
        $t->same($nativeBlocks, $roundTrip['blocks']);

        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('heading', ['level' => 3, 'id' => 'generated-heading', 'classes' => ['core-block']], [
                new AstNode('text', ['text' => 'Generated']),
                new AstNode('space'),
                new AstNode('emph', [], [new AstNode('text', ['text' => 'block'])]),
            ]),
            new AstNode('code_block', ['text' => 'wp media import file.png', 'classes' => ['bash']]),
            new AstNode('blockquote', [], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated quote'])]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated bullet'])])]),
            ]),
            new AstNode('ordered_list', ['start' => 5, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                new AstNode('list_item', [], [new AstNode('plain', [], [new AstNode('text', ['text' => 'Generated order'])])]),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('definition_term', [], [new AstNode('strong', [], [new AstNode('text', ['text' => 'term'])])]),
                    new AstNode('definition', [], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated definition'])]),
                        new AstNode('code_block', ['text' => 'wp option get siteurl']),
                    ]),
                ]),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [new AstNode('text', ['text' => 'First line'])]),
                new AstNode('line', ['text' => 'Fallback line']),
            ]),
            new AstNode('div', ['id' => 'generated-div', 'attributes' => ['data-review' => 'native-core']], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated div'])]),
            ]),
            new AstNode('horizontal_rule'),
        ]);
        $generated = json_decode($writer->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = $reader->read(json_encode($generated, JSON_THROW_ON_ERROR));

        $t->same([
            'Header',
            'CodeBlock',
            'BlockQuote',
            'BulletList',
            'OrderedList',
            'DefinitionList',
            'LineBlock',
            'Div',
            'HorizontalRule',
        ], array_map(static fn (array $block): string => $block['t'], $generated['blocks']));
        $t->same(['generated-heading', ['core-block'], []], $generated['blocks'][0]['c'][1]);
        $t->same('Emph', $generated['blocks'][0]['c'][2][2]['t']);
        $t->same([5, ['t' => 'LowerRoman'], ['t' => 'Period']], $generated['blocks'][4]['c'][0]);
        $t->same('Strong', $generated['blocks'][5]['c'][0][0][0]['t']);
        $t->same('CodeBlock', $generated['blocks'][5]['c'][0][1][0][1]['t']);
        $t->same('Fallback', $generated['blocks'][6]['c'][1][0]['c']);
        $t->same(['generated-div', [], [['data-review', 'native-core']]], $generated['blocks'][7]['c'][0]);
        $t->same('heading', $generatedRoundTrip->children[0]->type);
        $t->same('Generated block', $generatedRoundTrip->children[0]->attr('text'));
        $t->same('definition_list', $generatedRoundTrip->children[5]->type);
        $t->same('Fallback line', $generatedRoundTrip->children[6]->children[1]->attr('text'));
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
    'maps wrapped native ast short captions into shared table metadata' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', [], []],
                [
                    't' => 'Caption',
                    'c' => [
                        [
                            [
                                't' => 'ShortCaption',
                                'c' => [
                                    ['t' => 'Str', 'c' => 'Audit'],
                                    ['t' => 'Space'],
                                    ['t' => 'Code', 'c' => [
                                        ['', ['queue-code'], [['data-kind', 'short']]],
                                        'Q1',
                                    ]],
                                ],
                            ],
                        ],
                        [
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'Long'],
                                ['t' => 'Space'],
                                ['t' => 'Strong', 'c' => [
                                    ['t' => 'Str', 'c' => 'caption'],
                                ]],
                            ]],
                        ],
                    ],
                ],
                [
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                [
                    ['', [], []],
                    [],
                ],
                [
                    [
                        ['', [], []],
                        0,
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
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('Long caption', $table->attr('caption'));
        $t->same('Audit Q1', $table->attr('shortCaption'));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'code'], array_map(static fn ($node): string => $node->type, $shortCaptionInlines));
        $t->same(['queue-code'], $shortCaptionInlines[1]->attr('classes'));
        $t->same(['data-kind' => 'short'], $shortCaptionInlines[1]->attr('attributes'));
        $t->same($nativeTable, $roundTrip['blocks'][0]);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same(['text', 'code'], $packet['captions']['short']['inlineTypes'] ?? null);
    },
    'maps tuple native ast short caption constructors into shared table metadata' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', ['native-short-caption'], []],
                [
                    [
                        't' => 'ShortCaption',
                        'c' => [[
                            ['t' => 'Str', 'c' => 'Reviewer'],
                            ['t' => 'Space'],
                            ['t' => 'Strong', 'c' => [
                                ['t' => 'Str', 'c' => 'queue'],
                            ]],
                        ]],
                    ],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Detailed'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                [
                    ['', [], []],
                    [],
                ],
                [
                    [
                        ['', [], []],
                        0,
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

        $document = (new NativeReader())->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ], JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $blocks = (new WordPressBlockWriter())->write($document);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('table', $table->type);
        $t->same('Reviewer queue', $table->attr('shortCaption'));
        $t->same('Detailed caption', $table->attr('caption'));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $shortCaptionInlines));
        $t->same('ShortCaption', $roundTrip['blocks'][0]['c'][1][0]['t']);
        $t->same('Reviewer', $roundTrip['blocks'][0]['c'][1][0]['c'][0][0]['c']);
        $t->contains('data-pandoc-short-caption="Reviewer queue"', $blocks);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
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
    'writes shared ast table captions as native ast table constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('table', [
                'id' => 'native-writer-table',
                'classes' => ['wp-import-table'],
                'attributes' => ['data-source' => 'shared-ast'],
                'captionBlocks' => [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Shared']),
                        new AstNode('space'),
                        new AstNode('emph', [], [new AstNode('text', ['text' => 'caption'])]),
                        new AstNode('space'),
                        new AstNode('link', [
                            'url' => 'https://example.test/native',
                            'title' => 'Native review',
                        ], [new AstNode('text', ['text' => 'handoff'])]),
                    ]),
                ],
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Review']),
                    new AstNode('space'),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'slice'])]),
                ],
                'alignments' => ['left', 'right'],
                'widths' => [0.33, null],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Field'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'State'])]),
                    ]),
                ]),
                new AstNode('table_body', ['rowHeadColumns' => 1], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Posts']),
                        new AstNode('table_cell', ['align' => 'right', 'text' => 'Ready']),
                    ]),
                ]),
                new AstNode('table_foot', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['colspan' => 2], [new AstNode('text', ['text' => 'Reviewed'])]),
                    ]),
                ]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $tableBlock = $native['blocks'][0];
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $roundTrip->children[0];
        $blocks = (new WordPressBlockWriter())->write($roundTrip);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('Table', $tableBlock['t']);
        $t->same(['native-writer-table', ['wp-import-table'], [['data-source', 'shared-ast']]], $tableBlock['c'][0]);
        $t->same('Review', $tableBlock['c'][1][0][0]['c']);
        $t->same('Strong', $tableBlock['c'][1][0][2]['t']);
        $t->same('Shared', $tableBlock['c'][1][1][0]['c'][0]['c']);
        $t->same('Emph', $tableBlock['c'][1][1][0]['c'][2]['t']);
        $t->same('Link', $tableBlock['c'][1][1][0]['c'][4]['t']);
        $t->same('AlignLeft', $tableBlock['c'][2][0][0]['t']);
        $t->same('ColWidth', $tableBlock['c'][2][0][1]['t']);
        $t->same(0.33, $tableBlock['c'][2][0][1]['c']);
        $t->same('AlignRight', $tableBlock['c'][2][1][0]['t']);
        $t->same('ColWidthDefault', $tableBlock['c'][2][1][1]['t']);
        $t->same(1, $tableBlock['c'][4][0][1]);
        $t->same('Ready', $tableBlock['c'][4][0][3][0][1][1][4][0]['c'][0]['c']);
        $t->same('AlignRight', $tableBlock['c'][4][0][3][0][1][1][1]['t']);
        $t->same(2, $tableBlock['c'][5][1][0][1][0][3]);
        $t->same('Shared caption handoff', $table->attr('caption'));
        $t->same('Review slice', $table->attr('shortCaption'));
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children));
        $t->same('Ready', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Review slice">', $blocks);
        $t->contains('<table id="native-writer-table" class="wp-import-table" data-source="shared-ast">', $blocks);
        $t->contains('<figcaption class="wp-element-caption"><p>Shared <em>caption</em> <a href="https://example.test/native" title="Native review">handoff</a></p></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
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
    'maps native inline command constructors into latex writer output' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Review'],
                        ['t' => 'Space'],
                        ['t' => 'Underline', 'c' => [
                            ['t' => 'Str', 'c' => 'required'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Strikeout', 'c' => [
                            ['t' => 'Str', 'c' => 'stale'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Superscript', 'c' => [
                            ['t' => 'Str', 'c' => '2'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Subscript', 'c' => [
                            ['t' => 'Str', 'c' => 'n'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'SmallCaps', 'c' => [
                            ['t' => 'Str', 'c' => 'caps'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Quoted', 'c' => [
                            ['t' => 'SingleQuote'],
                            [
                                ['t' => 'Str', 'c' => 'quoted'],
                            ],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'RawInline', 'c' => ['latex', '\\LaTeX{}']],
                        ['t' => 'Space'],
                        ['t' => 'Math', 'c' => [
                            ['t' => 'InlineMath'],
                            'x^2',
                        ]],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];

        $t->same([
            'text',
            'underline',
            'text',
            'strikeout',
            'text',
            'superscript',
            'text',
            'subscript',
            'text',
            'small_caps',
            'text',
            'quoted',
            'text',
            'raw_tex',
            'text',
            'math',
        ], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(
            'Review \underline{required} \sout{stale} \textsuperscript{2} \textsubscript{n} \textsc{caps} `quoted\' \LaTeX{} $x^2$',
            (new LatexWriter())->write($document)
        );
    },
];
