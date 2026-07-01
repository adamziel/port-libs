<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native table helper constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Table ( "text-table" , [ "native" ] , [ ( "z" , "last" ) , ( "a" , "first" ) ] )
  (Caption Nothing [])
  [ ( AlignRight , ColWidth 0.25 ) , ( AlignDefault , ColWidthDefault ) ]
  (TableHead ( "head-id" , [ "section" ] , [ ( "z" , "head-last" ) , ( "a" , "head-first" ) ] )
    [ Row ( "head-row" , [ "row" ] , [ ( "z" , "row-last" ) , ( "a" , "row-first" ) ] )
      [ Cell ( "head-cell" , [ "cell" ] , [ ( "z" , "cell-last" ) , ( "a" , "cell-first" ) ] )
        AlignCenter
        (RowSpan 2)
        (ColSpan 3)
        [ Plain [ Str "Head", Space, Str "cell" ] ]
      ]
    ]
  )
  [ (TableBody ( "body-id" , [ "body" ] , [ ( "z" , "body-last" ) , ( "a" , "body-first" ) ] )
      (RowHeadColumns 1)
      [ Row ( "body-head-row" , [] , [ ( "z" , "body-head-row-last" ) , ( "a" , "body-head-row-first" ) ] )
        [ Cell ( "body-head-cell" , [] , [ ( "z" , "body-head-cell-last" ) , ( "a" , "body-head-cell-first" ) ] )
          AlignLeft
          (RowSpan 1)
          (ColSpan 1)
          [ Plain [ Str "Key" ] ]
        ]
      ]
      [ Row ( "body-row" , [] , [ ( "z" , "body-row-last" ) , ( "a" , "body-row-first" ) ] )
        [ Cell ( "body-cell" , [] , [ ( "z" , "body-cell-last" ) , ( "a" , "body-cell-first" ) ] )
          AlignDefault
          (RowSpan 1)
          (ColSpan 2)
          [ Plain [ Str "Value" ] ]
        ]
      ]
    )
  ]
  (TableFoot ( "foot-id" , [ "foot" ] , [ ( "z" , "foot-last" ) , ( "a" , "foot-first" ) ] )
    [ Row ( "foot-row" , [] , [ ( "z" , "foot-row-last" ) , ( "a" , "foot-row-first" ) ] )
      [ Cell ( "foot-cell" , [] , [ ( "z" , "foot-cell-last" ) , ( "a" , "foot-cell-first" ) ] )
        AlignRight
        (RowSpan 1)
        (ColSpan 1)
        [ Plain [ Str "Foot" ] ]
      ]
    ]
  )
]
NATIVE;

        $document = (new NativeReader())->read($native);
        $table = $document->children[0];
        $head = $table->children[0];
        $headRow = $head->children[0];
        $headCell = $headRow->children[0];
        $body = $table->children[1];
        $bodyHeadRow = $body->attr('headRows')[0] ?? new AstNode('missing');
        $bodyHeadCell = $bodyHeadRow->children[0] ?? new AstNode('missing');
        $bodyRow = $body->children[0];
        $bodyCell = $bodyRow->children[0];
        $foot = $table->children[2];
        $footRow = $foot->children[0];
        $footCell = $footRow->children[0];

        $jsonPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [$table]));
        $nativePacket = json_decode((new NativeWriter())->write(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [$table])), true, 512, JSON_THROW_ON_ERROR);

        $tableAttr = ['text-table', ['native'], [['z', 'last'], ['a', 'first']]];
        $headAttr = ['head-id', ['section'], [['z', 'head-last'], ['a', 'head-first']]];
        $headRowAttr = ['head-row', ['row'], [['z', 'row-last'], ['a', 'row-first']]];
        $headCellAttr = ['head-cell', ['cell'], [['z', 'cell-last'], ['a', 'cell-first']]];
        $bodyAttr = ['body-id', ['body'], [['z', 'body-last'], ['a', 'body-first']]];
        $bodyHeadRowAttr = ['body-head-row', [], [['z', 'body-head-row-last'], ['a', 'body-head-row-first']]];
        $bodyHeadCellAttr = ['body-head-cell', [], [['z', 'body-head-cell-last'], ['a', 'body-head-cell-first']]];
        $bodyRowAttr = ['body-row', [], [['z', 'body-row-last'], ['a', 'body-row-first']]];
        $bodyCellAttr = ['body-cell', [], [['z', 'body-cell-last'], ['a', 'body-cell-first']]];
        $footAttr = ['foot-id', ['foot'], [['z', 'foot-last'], ['a', 'foot-first']]];
        $footRowAttr = ['foot-row', [], [['z', 'foot-row-last'], ['a', 'foot-row-first']]];
        $footCellAttr = ['foot-cell', [], [['z', 'foot-cell-last'], ['a', 'foot-cell-first']]];

        $t->same('Table', $table->attr('constructor'));
        $t->same($tableAttr, $table->attr('attrNative'));
        $t->same([['t' => 'AlignRight'], ['t' => 'AlignDefault']], $table->attr('alignmentNatives'));
        $t->same([['t' => 'ColWidth', 'c' => 0.25], ['t' => 'ColWidthDefault']], $table->attr('columnWidthNatives'));
        $t->same([
            [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => 0.25]],
            [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
        ], $table->attr('columnSpecNatives'));

        $t->same($headAttr, $head->attr('attrNative'));
        $t->same($headRowAttr, $headRow->attr('attrNative'));
        $t->same($headCellAttr, $headCell->attr('attrNative'));
        $t->same(['t' => 'AlignCenter'], $headCell->attr('alignmentNative'));
        $t->same(['t' => 'RowSpan', 'c' => 2], $headCell->attr('rowSpanNative'));
        $t->same(['t' => 'ColSpan', 'c' => 3], $headCell->attr('colSpanNative'));

        $t->same($bodyAttr, $body->attr('attrNative'));
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $body->attr('rowHeadColumnsNative'));
        $t->same($bodyHeadRowAttr, $bodyHeadRow->attr('attrNative'));
        $t->same($bodyHeadCellAttr, $bodyHeadCell->attr('attrNative'));
        $t->same(['t' => 'AlignLeft'], $bodyHeadCell->attr('alignmentNative'));
        $t->same(['t' => 'RowSpan', 'c' => 1], $bodyHeadCell->attr('rowSpanNative'));
        $t->same(['t' => 'ColSpan', 'c' => 1], $bodyHeadCell->attr('colSpanNative'));
        $t->same($bodyRowAttr, $bodyRow->attr('attrNative'));
        $t->same($bodyCellAttr, $bodyCell->attr('attrNative'));
        $t->same(['t' => 'AlignDefault'], $bodyCell->attr('alignmentNative'));
        $t->same(['t' => 'ColSpan', 'c' => 2], $bodyCell->attr('colSpanNative'));

        $t->same($footAttr, $foot->attr('attrNative'));
        $t->same($footRowAttr, $footRow->attr('attrNative'));
        $t->same($footCellAttr, $footCell->attr('attrNative'));
        $t->same(['t' => 'AlignRight'], $footCell->attr('alignmentNative'));

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $packet) {
            $encodedTable = $packet['blocks'][0];
            $encodedHead = $encodedTable['c'][3];
            $encodedBody = $encodedTable['c'][4][0];
            $encodedFoot = $encodedTable['c'][5];

            $t->same($tableAttr, $encodedTable['c'][0], "{$writer} preserves table attr order");
            $t->same($headAttr, $encodedHead['c'][0], "{$writer} preserves head attr order");
            $t->same($headRowAttr, $encodedHead['c'][1][0]['c'][0], "{$writer} preserves head row attr order");
            $t->same($headCellAttr, $encodedHead['c'][1][0]['c'][1][0]['c'][0], "{$writer} preserves head cell attr order");
            $t->same(['t' => 'RowSpan', 'c' => 2], $encodedHead['c'][1][0]['c'][1][0]['c'][2], "{$writer} preserves head cell RowSpan");
            $t->same(['t' => 'ColSpan', 'c' => 3], $encodedHead['c'][1][0]['c'][1][0]['c'][3], "{$writer} preserves head cell ColSpan");
            $t->same($bodyAttr, $encodedBody['c'][0], "{$writer} preserves body attr order");
            $t->same(['t' => 'RowHeadColumns', 'c' => 1], $encodedBody['c'][1], "{$writer} preserves RowHeadColumns");
            $t->same($bodyHeadRowAttr, $encodedBody['c'][2][0]['c'][0], "{$writer} preserves body head-row attr order");
            $t->same($bodyHeadCellAttr, $encodedBody['c'][2][0]['c'][1][0]['c'][0], "{$writer} preserves body head-cell attr order");
            $t->same($bodyRowAttr, $encodedBody['c'][3][0]['c'][0], "{$writer} preserves body row attr order");
            $t->same($bodyCellAttr, $encodedBody['c'][3][0]['c'][1][0]['c'][0], "{$writer} preserves body cell attr order");
            $t->same($footAttr, $encodedFoot['c'][0], "{$writer} preserves foot attr order");
            $t->same($footRowAttr, $encodedFoot['c'][1][0]['c'][0], "{$writer} preserves foot row attr order");
            $t->same($footCellAttr, $encodedFoot['c'][1][0]['c'][1][0]['c'][0], "{$writer} preserves foot cell attr order");
        }
    },
];
