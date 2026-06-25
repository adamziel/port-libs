<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'reads current pandoc json into the shared ast' => static function (TestRunner $t): void {
        $json = json_encode([
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'JSON'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Title'],
                ]],
                'published' => ['t' => 'MetaBool', 'c' => true],
            ],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    ['intro', ['lead'], [['data-x', '1']]],
                    [['t' => 'Str', 'c' => 'Intro']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'A'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [['t' => 'Str', 'c' => 'portable']]],
                    ['t' => 'Space'],
                    ['t' => 'Link', 'c' => [
                        ['', [], []],
                        [['t' => 'Str', 'c' => 'link']],
                        ['https://example.test', 'Example'],
                    ]],
                    ['t' => 'Str', 'c' => '.'],
                ]],
                ['t' => 'BulletList', 'c' => [
                    [
                        ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'One']]],
                    ],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        $document = (new JsonReader())->read($json);

        $t->same('document', $document->type);
        $t->same('JSON Title', $document->attr('meta')['title']);
        $t->same(true, $document->attr('meta')['published']);
        $t->same('heading', $document->children[0]->type);
        $t->same(2, $document->children[0]->attr('level'));
        $t->same('intro', $document->children[0]->attr('id'));
        $t->same(['lead'], $document->children[0]->attr('classes'));
        $t->same(['data-x' => '1'], $document->children[0]->attr('attributes'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('emph', $document->children[1]->children[2]->type);
        $t->same('link', $document->children[1]->children[4]->type);
        $t->same('bullet_list', $document->children[2]->type);
    },
    'writes the shared ast as current pandoc json' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'JSON Title',
                'author' => ['Ada Lovelace'],
                'published' => true,
            ],
        ], [
            new AstNode('heading', [
                'level' => 1,
                'id' => 'json-title',
                'classes' => ['lead'],
            ], [
                new AstNode('text', ['text' => 'JSON Title']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'A portable ']),
                new AstNode('strong', [], [new AstNode('text', ['text' => 'AST'])]),
                new AstNode('text', ['text' => ' shape.']),
            ]),
        ]);

        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same([1, 23, 1, 2], $decoded['pandoc-api-version']);
        $t->same('MetaList', $decoded['meta']['author']['t']);
        $t->same('MetaBool', $decoded['meta']['published']['t']);
        $t->same('Header', $decoded['blocks'][0]['t']);
        $t->same(1, $decoded['blocks'][0]['c'][0]);
        $t->same('json-title', $decoded['blocks'][0]['c'][1][0]);
        $t->same('Para', $decoded['blocks'][1]['t']);
        $t->same('Space', $decoded['blocks'][1]['c'][1]['t']);
        $t->same('Strong', $decoded['blocks'][1]['c'][4]['t']);
    },
    'round trips table math citation and raw pandoc json constructors' => static function (TestRunner $t): void {
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'Table', 'c' => [
                    ['', [], []],
                    ['t' => 'Caption', 'c' => [null, []]],
                    [
                        [['t' => 'AlignLeft'], ['t' => 'ColWidth', 'c' => 0.5]],
                        [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                    ],
                    ['t' => 'TableHead', 'c' => [
                        ['', [], []],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignLeft'],
                                        1,
                                        1,
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'H']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                    [],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Math', 'c' => [['t' => 'DisplayMath'], 'x^2']],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => ['html', '<span>raw</span>']],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [[
                            'citationId' => 'doe2026',
                            'citationPrefix' => [],
                            'citationSuffix' => [],
                            'citationMode' => ['t' => 'NormalCitation'],
                            'citationNoteNum' => 1,
                            'citationHash' => 0,
                        ]],
                        [['t' => 'Str', 'c' => '@doe2026']],
                    ]],
                ]],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('table', $document->children[0]->type);
        $t->same(['left', 'default'], $document->children[0]->attr('alignments'));
        $t->same([0.5, 0.0], $document->children[0]->attr('widths'));
        $t->same('math', $document->children[1]->children[0]->type);
        $t->same(true, $document->children[1]->children[0]->attr('display'));
        $t->same('raw_html_inline', $document->children[1]->children[2]->type);
        $t->same('citation', $document->children[1]->children[4]->type);
        $t->same('Table', $decoded['blocks'][0]['t']);
        $t->same('ColWidth', $decoded['blocks'][0]['c'][2][0][1]['t']);
        $t->same('Math', $decoded['blocks'][1]['c'][0]['t']);
        $t->same('Cite', $decoded['blocks'][1]['c'][4]['t']);
    },
    'writes pandoc shaped citation id records without dropping ids' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('citation', [
                    'citations' => [[
                        'citationId' => 'smith2026',
                        'citationNoteNum' => 7,
                        'citationHash' => 12345,
                    ]],
                ]),
            ]),
        ]);

        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $citation = $decoded['blocks'][0]['c'][0];
        $record = $citation['c'][0][0];

        $t->same('Cite', $citation['t']);
        $t->same('smith2026', $record['citationId']);
        $t->same(7, $record['citationNoteNum']);
        $t->same(12345, $record['citationHash']);
        $t->same('Str', $citation['c'][1][0]['t']);
        $t->same('[@smith2026]', $citation['c'][1][0]['c']);
    },
    'flushes mixed link raw payload runs around nested block containers' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $link = static fn (string $url, string $label): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => $label,
        ], [$text($label)]);

        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('blockquote', [], [
                $text('Quote '),
                $link('https://example.test/quote-source', 'source'),
                $text(' '),
                new AstNode('raw_html_inline', ['html' => '<span data-review="quote-inline">raw</span>']),
                new AstNode('raw_html', ['html' => '<aside data-review="quote-block">Block raw</aside>']),
                new AstNode('div', ['classes' => ['quote-nested']], [
                    $text('Nested '),
                    new AstNode('raw_html_inline', ['html' => '<em data-review="nested-inline">raw</em>']),
                ]),
                $text('Quote '),
                $link('https://example.test/quote-tail', 'tail'),
            ]),
            new AstNode('div', ['id' => 'json-native-raw-div'], [
                $text('Div '),
                $link('https://example.test/div-source', 'source'),
                new AstNode('raw_block', ['format' => 'opml', 'text' => '<outline text="payload"/>']),
                new AstNode('blockquote', [], [
                    $text('Nested quote '),
                    new AstNode('raw_html_inline', ['html' => '<span data-review="nested-quote">inline</span>']),
                ]),
                new AstNode('raw_html_inline', ['html' => '<mark data-review="div-tail">tail</mark>']),
            ]),
            new AstNode('paragraph', [], [
                $text('Note '),
                new AstNode('note', [], [
                    $text('Note '),
                    $link('https://example.test/note-source', 'source'),
                    $text(' '),
                    new AstNode('raw_html_inline', ['html' => '<span data-review="note-inline">raw</span>']),
                    new AstNode('raw_block', ['format' => 'opml', 'text' => '<outline text="note-payload"/>']),
                    new AstNode('blockquote', [], [
                        $text('Nested note quote'),
                    ]),
                    $text('Note tail '),
                    new AstNode('raw_html_inline', ['html' => '<mark data-review="note-tail">tail</mark>']),
                ]),
            ]),
        ]);

        $jsonPacket = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $constructors = static fn (array $blocks): array => array_map(static fn (array $block): string => $block['t'], $blocks);
        $inlineConstructors = static fn (array $block): array => array_map(static fn (array $inline): string => $inline['t'], $block['c'] ?? []);
        $findNote = static function (array $inlines): ?array {
            foreach ($inlines as $inline) {
                if (($inline['t'] ?? null) === 'Note') {
                    return $inline;
                }
            }

            return null;
        };
        $nodeTypes = static fn (array $nodes): array => array_map(static fn (AstNode $node): string => $node->type, $nodes);
        $firstNodeOfType = static function (array $nodes, string $type): ?AstNode {
            foreach ($nodes as $node) {
                if ($node instanceof AstNode && $node->type === $type) {
                    return $node;
                }
            }

            return null;
        };

        $quoteBlocks = $jsonPacket['blocks'][0]['c'];
        $quoteNestedDivBlocks = $quoteBlocks[2]['c'][1];
        $divBlocks = $jsonPacket['blocks'][1]['c'][1];
        $divNestedQuoteBlocks = $divBlocks[2]['c'];
        $note = $findNote($jsonPacket['blocks'][2]['c']);
        $t->true(is_array($note), 'JSON paragraph includes note inline');
        $noteBlocks = is_array($note) && is_array($note['c'] ?? null) ? $note['c'] : [];

        $t->same(['Plain', 'RawBlock', 'Div', 'Plain'], $constructors($quoteBlocks), 'JSON blockquote separates inline runs from raw and nested blocks');
        $t->same(['Str', 'Space', 'Link', 'Space', 'RawInline'], $inlineConstructors($quoteBlocks[0]), 'JSON blockquote leading Plain keeps link and raw inline payloads');
        $t->same(['html', '<aside data-review="quote-block">Block raw</aside>'], $quoteBlocks[1]['c'], 'JSON blockquote raw block payload stays a block');
        $t->same(['Plain'], $constructors($quoteNestedDivBlocks), 'JSON nested div keeps a valid block list');
        $t->same(['Str', 'Space', 'RawInline'], $inlineConstructors($quoteNestedDivBlocks[0]), 'JSON nested div Plain keeps raw inline payload');
        $t->same('Link', $quoteBlocks[3]['c'][2]['t'], 'JSON blockquote trailing Plain keeps link payload');

        $t->same(['Plain', 'RawBlock', 'BlockQuote', 'Plain'], $constructors($divBlocks), 'JSON div separates inline runs from raw and nested blocks');
        $t->same(['Str', 'Space', 'Link'], $inlineConstructors($divBlocks[0]), 'JSON div leading Plain keeps link payload');
        $t->same(['opml', '<outline text="payload"/>'], $divBlocks[1]['c'], 'JSON generic raw block payload stays a block');
        $t->same(['Plain'], $constructors($divNestedQuoteBlocks), 'JSON nested blockquote keeps a valid block list');
        $t->same(['Str', 'Space', 'Str', 'Space', 'RawInline'], $inlineConstructors($divNestedQuoteBlocks[0]), 'JSON nested blockquote Plain keeps raw inline payload');
        $t->same(['RawInline'], $inlineConstructors($divBlocks[3]), 'JSON div trailing Plain keeps raw inline payload');

        $t->same(['Plain', 'RawBlock', 'BlockQuote', 'Plain'], $constructors($noteBlocks), 'JSON note separates inline runs from raw and nested blocks');
        $t->same(['Str', 'Space', 'Link', 'Space', 'RawInline'], $inlineConstructors($noteBlocks[0]), 'JSON note leading Plain keeps link and raw inline payloads');
        $t->same(['opml', '<outline text="note-payload"/>'], $noteBlocks[1]['c'], 'JSON note raw block payload stays a block');
        $t->same(['Plain'], $constructors($noteBlocks[2]['c']), 'JSON note nested blockquote keeps a valid block list');
        $t->same(['Str', 'Space', 'Str', 'Space', 'RawInline'], $inlineConstructors($noteBlocks[3]), 'JSON note trailing Plain keeps raw inline payload');

        foreach ([
            'json' => (new JsonReader())->read((new JsonWriter())->write($document)),
            'native' => (new NativeReader())->read((new NativeWriter())->write($document)),
        ] as $source => $roundTrip) {
            $quote = $roundTrip->children[0];
            $div = $roundTrip->children[1];
            $noteNode = $firstNodeOfType($roundTrip->children[2]->children, 'note');
            $quoteLeadingTypes = $nodeTypes($quote->children[0]->children);
            $quoteNestedTypes = $nodeTypes($quote->children[2]->children[0]->children);
            $quoteTrailingTypes = $nodeTypes($quote->children[3]->children);
            $divNestedTypes = $nodeTypes($div->children[2]->children[0]->children);
            $divTrailingTypes = $nodeTypes($div->children[3]->children);
            $noteLeadingTypes = $noteNode instanceof AstNode ? $nodeTypes($noteNode->children[0]->children) : [];
            $noteTrailingTypes = $noteNode instanceof AstNode ? $nodeTypes($noteNode->children[3]->children) : [];

            $t->same(['plain', 'raw_html', 'div', 'plain'], $nodeTypes($quote->children), "{$source} reader round-trips blockquote with block-only children");
            $t->same(true, in_array('link', $quoteLeadingTypes, true), "{$source} reader keeps leading blockquote link inside Plain");
            $t->same(true, in_array('raw_html_inline', $quoteLeadingTypes, true), "{$source} reader keeps leading blockquote raw inline inside Plain");
            $t->same(true, in_array('raw_html_inline', $quoteNestedTypes, true), "{$source} reader keeps nested div raw inline inside Plain");
            $t->same(true, in_array('link', $quoteTrailingTypes, true), "{$source} reader keeps trailing blockquote link inside Plain");

            $t->same(['plain', 'raw_block', 'blockquote', 'plain'], $nodeTypes($div->children), "{$source} reader round-trips div with block-only children");
            $t->same('opml', $div->children[1]->attr('format'), "{$source} reader keeps generic raw block format");
            $t->same('<outline text="payload"/>', $div->children[1]->attr('text'), "{$source} reader keeps generic raw block text");
            $t->same(true, in_array('raw_html_inline', $divNestedTypes, true), "{$source} reader keeps nested blockquote raw inline inside Plain");
            $t->same(true, in_array('raw_html_inline', $divTrailingTypes, true), "{$source} reader keeps trailing div raw inline inside Plain");

            $t->true($noteNode instanceof AstNode, "{$source} reader keeps note inline");
            $t->same(['plain', 'raw_block', 'blockquote', 'plain'], $nodeTypes($noteNode instanceof AstNode ? $noteNode->children : []), "{$source} reader round-trips note with block-only children");
            $t->same('opml', $noteNode instanceof AstNode ? $noteNode->children[1]->attr('format') : null, "{$source} reader keeps note raw block format");
            $t->same('<outline text="note-payload"/>', $noteNode instanceof AstNode ? $noteNode->children[1]->attr('text') : null, "{$source} reader keeps note raw block text");
            $t->same(true, in_array('link', $noteLeadingTypes, true), "{$source} reader keeps leading note link inside Plain");
            $t->same(true, in_array('raw_html_inline', $noteLeadingTypes, true), "{$source} reader keeps leading note raw inline inside Plain");
            $t->same(true, in_array('raw_html_inline', $noteTrailingTypes, true), "{$source} reader keeps trailing note raw inline inside Plain");
        }
    },
    'preserves tagged raw format constructors after json edits' => static function (TestRunner $t): void {
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => [
                    ['t' => 'Format', 'c' => 'html'],
                    '<section>Raw</section>',
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'RawInline', 'c' => [
                        ['t' => 'Format', 'c' => 'tex'],
                        '\\alpha',
                    ]],
                ]],
                ['t' => 'RawBlock', 'c' => ['opml', '<outline text="Bare"/>']],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $rawBlock = $document->children[0];
        $rawInline = $document->children[1]->children[0];
        $bareBlock = $document->children[2];

        $t->same('raw_html', $rawBlock->type);
        $t->same('Format', $rawBlock->attr('formatConstructor'));
        $t->same(['t' => 'Format', 'c' => 'html'], $rawBlock->attr('formatNative'));
        $t->same('raw_tex_inline', $rawInline->type);
        $t->same('Format', $rawInline->attr('formatConstructor'));
        $t->same(['t' => 'Format', 'c' => 'tex'], $rawInline->attr('formatNative'));
        $t->same('raw_block', $bareBlock->type);
        $t->same(null, $bareBlock->attr('formatNative'));

        $edited = new AstNode('document', [], [
            new AstNode($rawBlock->type, array_replace($rawBlock->attrs, [
                'html' => '<section>Edited</section>',
            ])),
            new AstNode('paragraph', [], [
                new AstNode($rawInline->type, array_replace($rawInline->attrs, [
                    'tex' => '\\beta',
                ])),
            ]),
            $bareBlock,
        ]);
        $decoded = json_decode((new JsonWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['t' => 'Format', 'c' => 'html'], $decoded['blocks'][0]['c'][0]);
        $t->same('<section>Edited</section>', $decoded['blocks'][0]['c'][1]);
        $t->same(['t' => 'Format', 'c' => 'tex'], $decoded['blocks'][1]['c'][0]['c'][0]);
        $t->same('\\beta', $decoded['blocks'][1]['c'][0]['c'][1]);
        $t->same('opml', $decoded['blocks'][2]['c'][0]);
    },
    'preserves ordered list enum payload shapes through json output' => static function (TestRunner $t): void {
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'OrderedList', 'c' => [
                    [4, 'Example', 'Period'],
                    [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Example']]]]],
                ]],
                ['t' => 'OrderedList', 'c' => [
                    [5, ['t' => 'LowerAlpha'], 'OneParen'],
                    [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Alpha']]]]],
                ]],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $first = $document->children[0];
        $second = $document->children[1];

        $t->same('ordered_list', $first->type);
        $t->same('example', $first->attr('style'));
        $t->same('Example', $first->attr('listStyleNative'));
        $t->same('period', $first->attr('delimiter'));
        $t->same('Period', $first->attr('listDelimiterNative'));
        $t->same('lower_alpha', $second->attr('style'));
        $t->same(['t' => 'LowerAlpha'], $second->attr('listStyleNative'));
        $t->same('one_paren', $second->attr('delimiter'));
        $t->same('OneParen', $second->attr('listDelimiterNative'));

        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $t->same('Example', $decoded['blocks'][0]['c'][0][1]);
        $t->same('Period', $decoded['blocks'][0]['c'][0][2]);
        $t->same(['t' => 'LowerAlpha'], $decoded['blocks'][1]['c'][0][1]);
        $t->same('OneParen', $decoded['blocks'][1]['c'][0][2]);

        $nativeDocument = (new NativeReader())->read('[ OrderedList ( 6 , UpperRoman , TwoParens ) [ [ Plain [ Str "Native" ] ] ] ]');
        $nativeDecoded = json_decode((new JsonWriter())->write($nativeDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('upper_roman', $nativeDocument->children[0]->attr('style'));
        $t->same('UpperRoman', $nativeDecoded['blocks'][0]['c'][0][1]);
        $t->same('TwoParens', $nativeDecoded['blocks'][0]['c'][0][2]);
    },
    'rejects incompatible pandoc json api versions' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            (new JsonReader())->read(json_encode([
                'pandoc-api-version' => [2, 0],
                'meta' => [],
                'blocks' => [],
            ], JSON_THROW_ON_ERROR));
        });
    },
];
