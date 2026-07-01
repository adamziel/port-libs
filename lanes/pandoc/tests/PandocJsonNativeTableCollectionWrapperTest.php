<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves empty single wrapped table helper collections' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['empty-wrapped-table', ['json-native'], []],
                ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                [[]],
                ['t' => 'TableHead', 'c' => [['head', [], []], [[]]], 'reviewQueue' => 'empty-head-rows'],
                [[]],
                ['t' => 'TableFoot', 'c' => [['foot', [], []], [[]]], 'reviewQueue' => 'empty-foot-rows'],
            ],
            'reviewQueue' => 'empty-table-collections',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $stripNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $foot = $table->children[1];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', $stripNative($table), [
                    new AstNode('table_head', $stripNative($head), []),
                    new AstNode('table_foot', $stripNative($foot), []),
                ]),
            ]);

            $t->same([[]], $table->attr('columnSpecsNative'), "{$source} reader records empty wrapped column specs");
            $t->same([[]], $table->attr('tableBodiesNative'), "{$source} reader records empty wrapped table bodies");
            $t->same([[]], $head->attr('tableRowsNative'), "{$source} reader records empty wrapped head rows");
            $t->same([[]], $foot->attr('tableRowsNative'), "{$source} reader records empty wrapped foot rows");
            $t->same(['table_head', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children), "{$source} reader keeps empty wrapped sections");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($tableBlock, $encoded['blocks'][0], "{$source} {$writer} writer preserves unchanged empty wrappers");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];

                $t->same([[]], $encodedTable['c'][2], "{$source} {$writer} writer preserves rebuilt empty column specs wrapper");
                $t->same([[]], $encodedTable['c'][3]['c'][1], "{$source} {$writer} writer preserves rebuilt empty head rows wrapper");
                $t->same([[]], $encodedTable['c'][4], "{$source} {$writer} writer preserves rebuilt empty table bodies wrapper");
                $t->same([[]], $encodedTable['c'][5]['c'][1], "{$source} {$writer} writer preserves rebuilt empty foot rows wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar after rebuild");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable['c'][3]), "{$source} {$writer} writer drops stale head sidecar after rebuild");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable['c'][5]), "{$source} {$writer} writer drops stale foot sidecar after rebuild");
            }
        }
    },
    'preserves single wrapped table body row and cell collections' => static function (TestRunner $t): void {
        $plain = static fn (string $text): array => ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => $text]]];
        $cell = static fn (string $id, string $text): array => [
            't' => 'Cell',
            'c' => [
                [$id, [], []],
                ['t' => 'AlignDefault'],
                ['t' => 'RowSpan', 'c' => 1],
                ['t' => 'ColSpan', 'c' => 1],
                [$plain($text)],
            ],
        ];
        $row = static fn (string $id, array $cells): array => [
            't' => 'Row',
            'c' => [
                [$id, [], []],
                [$cells],
            ],
        ];
        $body = static fn (string $id, array $rows, array $headRows = []): array => [
            't' => 'TableBody',
            'c' => [
                [$id, [], []],
                ['t' => 'RowHeadColumns', 'c' => 0],
                $headRows === [] ? [] : [$headRows],
                [$rows],
            ],
        ];

        $headCells = [$cell('head-cell-a', 'Head A'), $cell('head-cell-b', 'Head B')];
        $headRows = [
            $row('head-row-a', $headCells),
            $row('head-row-b', [$cell('head-cell-c', 'Head C')]),
        ];
        $bodyRows = [
            $row('body-row-a', [$cell('body-cell-a', 'Alpha'), $cell('body-cell-b', 'Beta')]),
            $row('body-row-b', [$cell('body-cell-c', 'Gamma')]),
        ];
        $secondBodyRows = [
            $row('body-row-c', [$cell('body-cell-d', 'Delta')]),
        ];
        $bodies = [
            $body('body-a', $bodyRows, [$row('body-head-row-a', [$cell('body-head-cell-a', 'Body Head')])]),
            $body('body-b', $secondBodyRows),
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['wrapped-table-collections', ['json-native'], []],
                ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                [
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [['head', [], []], [$headRows]]],
                [$bodies],
                ['t' => 'TableFoot', 'c' => [['foot', [], []], []]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $stripNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $copyRow = static function (AstNode $row) use ($stripNative): AstNode {
            return new AstNode('table_row', $stripNative($row), $row->children);
        };
        $copySection = static function (AstNode $section) use ($stripNative, $copyRow): AstNode {
            $attrs = $stripNative($section);
            if (isset($attrs['headRows']) && is_array($attrs['headRows'])) {
                $attrs['headRows'] = array_map($copyRow, $attrs['headRows']);
            }

            return new AstNode($section->type, $attrs, array_map($copyRow, $section->children));
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $firstBody = $table->children[1];
            $firstBodyRow = $firstBody->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', $stripNative($table), array_map($copySection, $table->children)),
            ]);

            $t->same([$bodies], $table->attr('tableBodiesNative'), "{$source} reader records wrapped table body collection");
            $t->same([$headRows], $head->attr('tableRowsNative'), "{$source} reader records wrapped head rows");
            $t->same([$bodyRows], $firstBody->attr('tableRowsNative'), "{$source} reader records wrapped body rows");
            $t->same([$headCells], $head->children[0]->attr('tableCellsNative'), "{$source} reader records wrapped head cells");
            $t->same([$bodyRows[0]['c'][1][0]], $firstBodyRow->attr('tableCellsNative'), "{$source} reader records wrapped body cells");
            $t->same(4, count($table->children), "{$source} keeps head, two body sections, and foot");
            $t->same(2, count($firstBody->children), "{$source} first body row count");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedHead = $encodedTable['c'][3];
                $encodedFirstBody = $encodedTable['c'][4][0][0];
                $encodedFirstBodyRows = $encodedFirstBody['c'][3];
                $encodedFirstBodyFirstRow = $encodedFirstBodyRows[0][0];

                $t->same([$bodies], $encodedTable['c'][4], "{$source} {$writer} writer preserves wrapped table body collection");
                $t->same([$headRows], $encodedHead['c'][1], "{$source} {$writer} writer preserves wrapped head row collection");
                $t->same([$bodyRows], $encodedFirstBody['c'][3], "{$source} {$writer} writer preserves wrapped body row collection");
                $t->same([$bodyRows[0]['c'][1][0]], $encodedFirstBodyFirstRow['c'][1], "{$source} {$writer} writer preserves wrapped body cell collection");
            }
        }
    },
];
