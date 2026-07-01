<?php

declare(strict_types=1);

use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;

return [
    'accepts single-wrapped pandoc json constructor payloads in compatibility reader' => static function (TestRunner $t): void {
        $str = static fn (string $value): array => ['t' => 'Str', 'c' => $value];
        $space = static fn (): array => ['t' => 'Space'];
        $attr = static fn (string $id = '', array $classes = [], array $pairs = []): array => ['t' => 'Attr', 'c' => [[$id, $classes, $pairs]]];
        $plain = static fn (string $text): array => ['t' => 'Plain', 'c' => [[$str($text)]]];
        $emptyAttr = ['', [], []];

        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [[$str('Wrapped'), $space(), $str('Title')]]],
                'abstract' => ['t' => 'MetaBlocks', 'c' => [[$plain('Meta block')]]],
            ],
            'blocks' => [[
                ['t' => 'Header', 'c' => [[
                    2,
                    $attr('wrapped-header', ['native-json'], [['data-shape', 'single-wrapped']]),
                    [[$str('Wrapped'), $space(), $str('Header')]],
                ]]],
                ['t' => 'Para', 'c' => [[
                    $str('A'),
                    $space(),
                    ['t' => 'Emph', 'c' => [[$str('nested')]]],
                    $space(),
                    ['t' => 'Link', 'c' => [[
                        $attr('wrapped-link', ['source'], []),
                        [[$str('link')]],
                        ['t' => 'Target', 'c' => [['https://example.test/wrapped', 'Wrapped title']]],
                    ]]],
                ]]],
                ['t' => 'BlockQuote', 'c' => [[$plain('Quoted')]]],
                ['t' => 'Div', 'c' => [[
                    $attr('wrapped-div', ['section'], []),
                    [[$plain('Div text')]],
                ]]],
                ['t' => 'Table', 'c' => [[
                    $attr('wrapped-table', ['grid'], []),
                    ['t' => 'Caption', 'c' => [[null, [[$plain('Table caption')]]]]],
                    [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                    ['t' => 'TableHead', 'c' => [[$emptyAttr, []]]],
                    [
                        ['t' => 'TableBody', 'c' => [[
                            $attr('tbody', [], []),
                            ['t' => 'RowHeadColumns', 'c' => [1]],
                            [],
                            [
                                ['t' => 'Row', 'c' => [[
                                    $attr('row-1', [], []),
                                    [
                                        ['t' => 'Cell', 'c' => [[
                                            $attr('cell-1', ['metric'], []),
                                            ['t' => 'AlignCenter'],
                                            ['t' => 'RowSpan', 'c' => [2]],
                                            ['t' => 'ColSpan', 'c' => [1]],
                                            [[$plain('Cell text')]],
                                        ]]],
                                    ],
                                ]]],
                            ],
                        ]]],
                    ],
                    ['t' => 'TableFoot', 'c' => [[$emptyAttr, []]]],
                ]]],
            ]],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $quote = $document->children[2];
        $div = $document->children[3];
        $table = $document->children[4];
        $body = $table->children[1];
        $cell = $body->children[0]->children[0];

        $t->same('Wrapped Title', $document->attr('meta')['title']);
        $t->same('Meta block', $document->attr('meta')['abstract']['value'][0]->attr('text'));
        $t->same('heading', $heading->type);
        $t->same('wrapped-header', $heading->attr('id'));
        $t->same(['native-json'], $heading->attr('classes'));
        $t->same('Wrapped Header', $heading->attr('text'));
        $t->same('emph', $paragraph->children[2]->type);
        $t->same('link', $paragraph->children[4]->type);
        $t->same('https://example.test/wrapped', $paragraph->children[4]->attr('url'));
        $t->same('Quoted', $quote->children[0]->attr('text'));
        $t->same('Div text', $div->children[0]->attr('text'));
        $t->same('Table caption', $table->attr('caption'));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same('cell-1', $cell->attr('id'));
        $t->same('center', $cell->attr('align'));
        $t->same(2, $cell->attr('rowspan'));
        $t->same('Cell text', $cell->children[0]->attr('text'));

        $t->same('MetaInlines', $decoded['meta']['title']['t']);
        $t->same('Str', $decoded['meta']['title']['c'][0]['t']);
        $t->same('MetaBlocks', $decoded['meta']['abstract']['t']);
        $t->same('Plain', $decoded['meta']['abstract']['c'][0]['t']);
        $t->same('Header', $decoded['blocks'][0]['t']);
        $t->same(2, $decoded['blocks'][0]['c'][0]);
        $t->same('wrapped-header', $decoded['blocks'][0]['c'][1][0]);
        $t->same('Str', $decoded['blocks'][0]['c'][2][0]['t']);
        $t->same('Para', $decoded['blocks'][1]['t']);
        $t->same('Emph', $decoded['blocks'][1]['c'][2]['t']);
        $t->same('Link', $decoded['blocks'][1]['c'][4]['t']);
        $t->same(3, count($decoded['blocks'][1]['c'][4]['c']));
        $t->same('BlockQuote', $decoded['blocks'][2]['t']);
        $t->same('Plain', $decoded['blocks'][2]['c'][0]['t']);
        $t->same('Div', $decoded['blocks'][3]['t']);
        $t->same('Plain', $decoded['blocks'][3]['c'][1][0]['t']);
        $t->same('Table', $decoded['blocks'][4]['t']);
        $t->same(6, count($decoded['blocks'][4]['c']));
        $t->same('TableHead', $decoded['blocks'][4]['c'][3]['t']);
        $t->same(2, count($decoded['blocks'][4]['c'][3]['c']));
        $t->same(1, $decoded['blocks'][4]['c'][4][0]['c'][1]);
        $t->same(2, $decoded['blocks'][4]['c'][4][0]['c'][3][0]['c'][1][0]['c'][2]);
    },
    'accepts single-wrapped table helper collections in compatibility reader' => static function (TestRunner $t): void {
        $plain = static fn (string $text): array => ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => $text]]];
        $attr = static fn (string $id = '', array $classes = []): array => [$id, $classes, []];
        $cell = static fn (string $id, string $text, array $alignment, array $rowspan, array $colspan): array => [
            't' => 'Cell',
            'c' => [
                $attr($id),
                $alignment,
                $rowspan,
                $colspan,
                [[$plain($text)]],
            ],
        ];
        $row = static fn (string $id, array $cells): array => [
            't' => 'Row',
            'c' => [
                $attr($id),
                [$cells],
            ],
        ];

        $headRow = $row('head-row', [
            $cell('head-left', 'Left head', ['t' => 'AlignLeft'], ['t' => 'RowSpan', 'c' => [1]], ['t' => 'ColSpan', 'c' => [1]]),
            $cell('head-right', 'Right head', ['t' => 'AlignRight'], ['t' => 'RowSpan', 'c' => [1]], ['t' => 'ColSpan', 'c' => [1]]),
        ]);
        $bodyHeadRow = $row('body-head-row', [
            $cell('body-head-left', 'Body head', ['t' => 'AlignCenter'], ['t' => 'RowSpan', 'c' => [1]], ['t' => 'ColSpan', 'c' => [2]]),
        ]);
        $bodyRow = $row('body-row', [
            $cell('body-left', 'Left body', ['t' => 'AlignLeft'], ['t' => 'RowSpan', 'c' => [2]], ['t' => 'ColSpan', 'c' => [1]]),
            $cell('body-right', 'Right body', ['t' => 'AlignRight'], ['t' => 'RowSpan', 'c' => [1]], ['t' => 'ColSpan', 'c' => [1]]),
        ]);
        $body = [
            't' => 'TableBody',
            'c' => [
                $attr('body'),
                ['t' => 'RowHeadColumns', 'c' => [1]],
                [[$bodyHeadRow]],
                [[$bodyRow]],
            ],
        ];
        $columnSpecs = [
            [['t' => 'AlignLeft'], ['t' => 'ColWidth', 'c' => [0.4]]],
            [['t' => 'AlignRight'], ['t' => 'ColWidthDefault']],
        ];
        $source = [
            'pandoc-api-version' => [1, 23, 1, 2],
            'meta' => [],
            'blocks' => [
                ['t' => 'Table', 'c' => [
                    $attr('wrapped-helper-table', ['grid']),
                    ['t' => 'Caption', 'c' => [null, []]],
                    [$columnSpecs],
                    ['t' => 'TableHead', 'c' => [
                        $attr('head'),
                        [[$headRow]],
                    ]],
                    [[$body]],
                    ['t' => 'TableFoot', 'c' => [
                        $attr('foot'),
                        [[]],
                    ]],
                ]],
            ],
        ];

        $document = (new JsonReader())->read(json_encode($source, JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $head = $table->children[0];
        $parsedBody = $table->children[1];
        $bodyHeadRows = $parsedBody->attr('headRows');
        $decoded = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $decodedTable = $decoded['blocks'][0];

        $t->same('table', $table->type);
        $t->same(['left', 'right'], $table->attr('alignments'));
        $t->same([0.4, 0.0], $table->attr('widths'));
        $t->same('head-row', $head->children[0]->attr('id'));
        $t->same('head-right', $head->children[0]->children[1]->attr('id'));
        $t->same('body', $parsedBody->attr('id'));
        $t->same(1, $parsedBody->attr('rowHeadColumns'));
        $t->same('body-head-row', $bodyHeadRows[0]->attr('id'));
        $t->same('body-row', $parsedBody->children[0]->attr('id'));
        $t->same('body-right', $parsedBody->children[0]->children[1]->attr('id'));
        $t->same(2, $parsedBody->children[0]->children[0]->attr('rowspan'));

        $t->same('Table', $decodedTable['t']);
        $t->same('AlignLeft', $decodedTable['c'][2][0][0]['t']);
        $t->same('ColWidth', $decodedTable['c'][2][0][1]['t']);
        $t->same('TableHead', $decodedTable['c'][3]['t']);
        $t->same('Row', $decodedTable['c'][3]['c'][1][0]['t']);
        $t->same('Cell', $decodedTable['c'][3]['c'][1][0]['c'][1][1]['t']);
        $t->same('TableBody', $decodedTable['c'][4][0]['t']);
        $t->same(1, $decodedTable['c'][4][0]['c'][1]);
        $t->same('Row', $decodedTable['c'][4][0]['c'][2][0]['t']);
        $t->same('Cell', $decodedTable['c'][4][0]['c'][3][0]['c'][1][1]['t']);
        $t->same([], $decodedTable['c'][5]['c'][1]);
    },
];
