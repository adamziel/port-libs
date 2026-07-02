<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;

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
    'round trips null block pandoc json constructors' => static function (TestRunner $t): void {
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'before']]],
                ['t' => 'Null'],
                ['t' => 'HorizontalRule'],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new JsonReader())->read(json_encode($decoded, JSON_THROW_ON_ERROR));
        $manual = new AstNode('document', [], [
            new AstNode('null_block'),
            new AstNode('horizontal_rule'),
        ]);
        $manualDecoded = json_decode((new JsonWriter())->write($manual), true, 512, JSON_THROW_ON_ERROR);

        $t->same('plain', $document->children[0]->type);
        $t->same('null_block', $document->children[1]->type);
        $t->same('horizontal_rule', $document->children[2]->type);
        $t->same('Null', $decoded['blocks'][1]['t']);
        $t->same(false, array_key_exists('c', $decoded['blocks'][1]));
        $t->same('null_block', $roundTrip->children[1]->type);
        $t->same('Null', $manualDecoded['blocks'][0]['t']);
        $t->same('HorizontalRule', $manualDecoded['blocks'][1]['t']);
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
