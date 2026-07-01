<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;

return [
    'accepts tagged pandoc json helper constructors in compatibility reader' => static function (TestRunner $t): void {
        $caption = [
            't' => 'Caption',
            'c' => [
                ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [[
                    ['t' => 'Str', 'c' => 'Short'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'caption'],
                ]]]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Long'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'caption'],
                    ]],
                ],
            ],
        ];
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    ['t' => 'Attr', 'c' => [['helper-heading', ['json-native'], [['data-kind', 'attr']]]]],
                    [['t' => 'Str', 'c' => 'Helper']],
                ]],
                ['t' => 'OrderedList', 'c' => [
                    ['t' => 'ListAttributes', 'c' => [[3, ['t' => 'UpperAlpha'], ['t' => 'TwoParens']]]],
                    [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Item']]]]],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        ['t' => 'Attr', 'c' => [['source-link', ['review'], [['data-link', 'source']]]]],
                        [['t' => 'Str', 'c' => 'source']],
                        ['t' => 'Target', 'c' => [['https://example.test/source', 'Source title']]],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [
                            ['t' => 'Citation', 'c' => [[
                                'citationId' => 'doe2026',
                                'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
                                'citationSuffix' => [['t' => 'Str', 'c' => 'p. 4']],
                                'citationMode' => ['t' => 'SuppressAuthor'],
                                'citationNoteNum' => 7,
                                'citationHash' => 42,
                            ]]],
                        ],
                        [['t' => 'Str', 'c' => 'see -@doe2026, p. 4']],
                    ]],
                ]],
                ['t' => 'Table', 'c' => [
                    ['t' => 'Attr', 'c' => [['helper-table', ['wide'], []]]],
                    $caption,
                    [[['t' => 'AlignCenter'], ['t' => 'ColWidth', 'c' => [0.5]]]],
                    ['t' => 'TableHead', 'c' => [
                        ['t' => 'Attr', 'c' => [['head-section', [], []]]],
                        [],
                    ]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            ['t' => 'Attr', 'c' => [['body-section', [], []]]],
                            ['t' => 'RowHeadColumns', 'c' => [1]],
                            [],
                            [
                                ['t' => 'Row', 'c' => [
                                    ['t' => 'Attr', 'c' => [['row-1', [], []]]],
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            ['t' => 'Attr', 'c' => [['cell-1', ['metric'], [['data-cell', 'value']]]]],
                                            ['t' => 'AlignRight'],
                                            ['t' => 'RowSpan', 'c' => [2]],
                                            ['t' => 'ColSpan', 'c' => [3]],
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                    ['t' => 'TableFoot', 'c' => [
                        ['t' => 'Attr', 'c' => [['foot-section', [], []]]],
                        [],
                    ]],
                ]],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $heading = $document->children[0];
        $ordered = $document->children[1];
        $paragraph = $document->children[2];
        $link = $paragraph->children[0];
        $citation = $paragraph->children[2];
        $table = $document->children[3];
        $body = $table->children[1];
        $row = $body->children[0];
        $cell = $row->children[0];
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('helper-heading', $heading->attr('id'));
        $t->same(['json-native'], $heading->attr('classes'));
        $t->same(['data-kind' => 'attr'], $heading->attr('attributes'));
        $t->same(3, $ordered->attr('start'));
        $t->same('upper_alpha', $ordered->attr('style'));
        $t->same('two_parens', $ordered->attr('delimiter'));
        $t->same('https://example.test/source', $link->attr('url'));
        $t->same('Source title', $link->attr('title'));
        $t->same('suppress_author', $citation->attr('citations')[0]['mode']);
        $t->same(7, $citation->attr('citations')[0]['noteNum']);
        $t->same('Short caption', $table->attr('shortCaption'));
        $t->same('Long caption', $table->attr('caption'));
        $t->same(['center'], $table->attr('alignments'));
        $t->same([0.5], $table->attr('widths'));
        $t->same('body-section', $body->attr('id'));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same('row-1', $row->attr('id'));
        $t->same('cell-1', $cell->attr('id'));
        $t->same('right', $cell->attr('align'));
        $t->same(2, $cell->attr('rowspan'));
        $t->same(3, $cell->attr('colspan'));
        $t->same('Cell', $cell->children[0]->attr('text'));

        $t->same(['helper-heading', ['json-native'], [['data-kind', 'attr']]], $decoded['blocks'][0]['c'][1]);
        $t->same([3, ['t' => 'UpperAlpha'], ['t' => 'TwoParens']], $decoded['blocks'][1]['c'][0]);
        $t->same(['https://example.test/source', 'Source title'], $decoded['blocks'][2]['c'][0]['c'][2]);
        $t->same('doe2026', $decoded['blocks'][2]['c'][2]['c'][0][0]['citationId']);
        $t->same(['t' => 'SuppressAuthor'], $decoded['blocks'][2]['c'][2]['c'][0][0]['citationMode']);
        $t->same('Caption', $decoded['blocks'][3]['c'][1]['t']);
        $t->same([['t' => 'Str', 'c' => 'Short'], ['t' => 'Space'], ['t' => 'Str', 'c' => 'caption']], $decoded['blocks'][3]['c'][1]['c'][0]);
        $t->same(['t' => 'ColWidth', 'c' => 0.5], $decoded['blocks'][3]['c'][2][0][1]);
        $t->same(1, $decoded['blocks'][3]['c'][4][0]['c'][1]);
        $t->same(2, $decoded['blocks'][3]['c'][4][0]['c'][3][0]['c'][1][0]['c'][2]);
        $t->same(3, $decoded['blocks'][3]['c'][4][0]['c'][3][0]['c'][1][0]['c'][3]);
    },
];
