<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native nullary inline constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "Alpha", Space, SoftBreak, Str "Beta", LineBreak, Str "Gamma" ] ]
NATIVE;
        $expectedInlines = [
            ['t' => 'Str', 'c' => 'Alpha'],
            ['t' => 'Space'],
            ['t' => 'SoftBreak'],
            ['t' => 'Str', 'c' => 'Beta'],
            ['t' => 'LineBreak'],
            ['t' => 'Str', 'c' => 'Gamma'],
        ];

        $nativeDocument = (new NativeReader())->read($native);
        $paragraph = $nativeDocument->children[0];
        $children = $paragraph->children;
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['text', 'text', 'softbreak', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $children));
        $t->same(['Space'], $children[1]->attr('nativeInlineConstructors'));
        $t->same([['t' => 'Space']], $children[1]->attr('nativeInlineParts'));
        $t->same('SoftBreak', $children[2]->attr('constructor'));
        $t->same(['t' => 'SoftBreak'], $children[2]->attr('native'));
        $t->same('LineBreak', $children[4]->attr('constructor'));
        $t->same(['t' => 'LineBreak'], $children[4]->attr('native'));
        $t->same($expectedInlines, $jsonPacket['blocks'][0]['c']);
        $t->same($expectedInlines, $nativePacket['blocks'][0]['c']);
        $t->same(
            '[ Para [ Str "Alpha" , Space , SoftBreak , Str "Beta" , LineBreak , Str "Gamma" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );
    },
    'preserves textual native nullary block constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ HorizontalRule
, Null
, BlockQuote [ HorizontalRule, Null ]
]
NATIVE;
        $expectedBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            ['t' => 'BlockQuote', 'c' => [
                ['t' => 'HorizontalRule'],
                ['t' => 'Null'],
            ]],
        ];

        $nativeDocument = (new NativeReader())->read($native);
        $rule = $nativeDocument->children[0];
        $null = $nativeDocument->children[1];
        $quote = $nativeDocument->children[2];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['horizontal_rule', 'null_block', 'blockquote'], array_map(static fn (AstNode $node): string => $node->type, $nativeDocument->children));
        $t->same('HorizontalRule', $rule->attr('constructor'));
        $t->same(['t' => 'HorizontalRule'], $rule->attr('native'));
        $t->same('Null', $null->attr('constructor'));
        $t->same(['t' => 'Null'], $null->attr('native'));
        $t->same('HorizontalRule', $quote->children[0]->attr('constructor'));
        $t->same(['t' => 'HorizontalRule'], $quote->children[0]->attr('native'));
        $t->same('Null', $quote->children[1]->attr('constructor'));
        $t->same(['t' => 'Null'], $quote->children[1]->attr('native'));
        $t->same($expectedBlocks, $jsonPacket['blocks']);
        $t->same($expectedBlocks, $nativePacket['blocks']);
        $t->same(
            '[ HorizontalRule' . "\n"
            . ', Null' . "\n"
            . ', BlockQuote [ HorizontalRule' . "\n"
            . '  , Null' . "\n"
            . '  ]' . "\n"
            . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );
    },
    'preserves textual native raw format constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ RawBlock (Format "markdown+raw_tex") "$x$"
, Para [ RawInline (Format "latex") "\\alpha", Space, RawInline (Format "html5") "<span>ok</span>" ]
]
NATIVE;
        $markdownFormat = ['t' => 'Format', 'c' => 'markdown+raw_tex'];
        $texFormat = ['t' => 'Format', 'c' => 'latex'];
        $htmlFormat = ['t' => 'Format', 'c' => 'html5'];
        $markdownNative = ['t' => 'RawBlock', 'c' => [$markdownFormat, '$x$']];
        $texNative = ['t' => 'RawInline', 'c' => [$texFormat, '\\alpha']];
        $htmlNative = ['t' => 'RawInline', 'c' => [$htmlFormat, '<span>ok</span>']];

        $nativeDocument = (new NativeReader())->read($native);
        $rawBlock = $nativeDocument->children[0];
        $paragraph = $nativeDocument->children[1];
        $texInline = $paragraph->children[0];
        $htmlInline = $paragraph->children[2];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('raw_markdown', $rawBlock->type);
        $t->same('RawBlock', $rawBlock->attr('constructor'));
        $t->same($markdownNative, $rawBlock->attr('native'));
        $t->same('Format', $rawBlock->attr('formatConstructor'));
        $t->same($markdownFormat, $rawBlock->attr('formatNative'));
        $t->same('raw_tex_inline', $texInline->type);
        $t->same('RawInline', $texInline->attr('constructor'));
        $t->same($texNative, $texInline->attr('native'));
        $t->same('Format', $texInline->attr('formatConstructor'));
        $t->same($texFormat, $texInline->attr('formatNative'));
        $t->same('raw_html_inline', $htmlInline->type);
        $t->same('RawInline', $htmlInline->attr('constructor'));
        $t->same($htmlNative, $htmlInline->attr('native'));
        $t->same('Format', $htmlInline->attr('formatConstructor'));
        $t->same($htmlFormat, $htmlInline->attr('formatNative'));
        $t->same($markdownFormat, $jsonPacket['blocks'][0]['c'][0]);
        $t->same($texFormat, $jsonPacket['blocks'][1]['c'][0]['c'][0]);
        $t->same($htmlFormat, $jsonPacket['blocks'][1]['c'][2]['c'][0]);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);
        $t->same(
            '[ RawBlock (Format "markdown+raw_tex") "$x$"' . "\n"
            . ', Para [ RawInline (Format "latex") "\\\\alpha" , Space , RawInline (Format "html5") "<span>ok</span>" ]' . "\n"
            . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );

        $staleBlockNative = $markdownNative;
        $staleBlockNative['reviewQueue'] = 'textual-raw-block-source';
        $staleInlineNative = $texNative;
        $staleInlineNative['reviewQueue'] = 'textual-raw-inline-source';
        $editedDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('raw_markdown', array_replace($rawBlock->attrs, [
                'text' => '$y$',
                'markdown' => '$y$',
                'native' => $staleBlockNative,
            ])),
            new AstNode('paragraph', [], [
                new AstNode('raw_tex_inline', array_replace($texInline->attrs, [
                    'text' => '\\beta',
                    'tex' => '\\beta',
                    'native' => $staleInlineNative,
                ])),
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $editedPacket) {
            $t->same(['t' => 'RawBlock', 'c' => [$markdownFormat, '$y$']], $editedPacket['blocks'][0], "{$writer} regenerates edited textual raw block");
            $t->same(['t' => 'RawInline', 'c' => [$texFormat, '\\beta']], $editedPacket['blocks'][1]['c'][0], "{$writer} regenerates edited textual raw inline");
            $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][0]), "{$writer} drops stale raw block sidecar");
            $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][1]['c'][0]), "{$writer} drops stale raw inline sidecar");
        }
    },
    'preserves textual native quote and math enum constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Quoted SingleQuote [ Str "quoted" ], Space, Math InlineMath "x + 1", Space, Math DisplayMath "y = 2" ] ]
NATIVE;
        $singleQuote = ['t' => 'SingleQuote'];
        $inlineMath = ['t' => 'InlineMath'];
        $displayMath = ['t' => 'DisplayMath'];

        $nativeDocument = (new NativeReader())->read($native);
        $paragraph = $nativeDocument->children[0];
        $quoted = $paragraph->children[0];
        $inline = $paragraph->children[2];
        $display = $paragraph->children[4];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('quoted', $quoted->type);
        $t->same('single', $quoted->attr('kind'));
        $t->same('SingleQuote', $quoted->attr('quoteTypeConstructor'));
        $t->same($singleQuote, $quoted->attr('quoteTypeNative'));
        $t->same('math', $inline->type);
        $t->same(false, $inline->attr('display'));
        $t->same('InlineMath', $inline->attr('mathTypeConstructor'));
        $t->same($inlineMath, $inline->attr('mathTypeNative'));
        $t->same('math', $display->type);
        $t->same(true, $display->attr('display'));
        $t->same('DisplayMath', $display->attr('mathTypeConstructor'));
        $t->same($displayMath, $display->attr('mathTypeNative'));
        $t->same($singleQuote, $jsonPacket['blocks'][0]['c'][0]['c'][0]);
        $t->same($inlineMath, $jsonPacket['blocks'][0]['c'][2]['c'][0]);
        $t->same($displayMath, $jsonPacket['blocks'][0]['c'][4]['c'][0]);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);
        $t->same(
            '[ Para [ Quoted SingleQuote [ Str "quoted" ] , Space , Math InlineMath "x + 1" , Space , Math DisplayMath "y = 2" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );
    },
    'preserves textual native code link and span constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Code ( "hook-code" , [ "php" ] , [ ( "data-hook" , "plib-2wcn6" ) ] ) "gt hook", Space, Link ( "source" , [ "tracked" ] , [ ( "data-kind" , "native" ) ] ) [ Str "source" ] ( "https://example.test/source" , "Source" ), Space, Span ( "span-id" , [ "mark" ] , [ ( "data-span" , "yes" ) ] ) [ Code ( "span-code" , [  ] , [  ] ) "inline" ] ] ]
NATIVE;
        $codeAttr = ['hook-code', ['php'], [['data-hook', 'plib-2wcn6']]];
        $linkAttr = ['source', ['tracked'], [['data-kind', 'native']]];
        $target = ['https://example.test/source', 'Source'];
        $spanAttr = ['span-id', ['mark'], [['data-span', 'yes']]];
        $spanCodeAttr = ['span-code', [], []];
        $codeNative = ['t' => 'Code', 'c' => [$codeAttr, 'gt hook']];
        $linkNative = ['t' => 'Link', 'c' => [$linkAttr, [['t' => 'Str', 'c' => 'source']], $target]];
        $spanCodeNative = ['t' => 'Code', 'c' => [$spanCodeAttr, 'inline']];
        $spanNative = ['t' => 'Span', 'c' => [$spanAttr, [$spanCodeNative]]];
        $expectedInlines = [
            $codeNative,
            ['t' => 'Space'],
            $linkNative,
            ['t' => 'Space'],
            $spanNative,
        ];

        $nativeDocument = (new NativeReader())->read($native);
        $paragraph = $nativeDocument->children[0];
        $code = $paragraph->children[0];
        $link = $paragraph->children[2];
        $span = $paragraph->children[4];
        $spanCode = $span->children[0];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['code', 'text', 'link', 'text', 'span'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('Code', $code->attr('constructor'));
        $t->same($codeAttr, $code->attr('attrNative'));
        $t->same($codeNative, $code->attr('native'));
        $t->same('Link', $link->attr('constructor'));
        $t->same($linkAttr, $link->attr('attrNative'));
        $t->same($target, $link->attr('targetNative'));
        $t->same($linkNative, $link->attr('native'));
        $t->same('Span', $span->attr('constructor'));
        $t->same($spanAttr, $span->attr('attrNative'));
        $t->same($spanNative, $span->attr('native'));
        $t->same($spanCodeNative, $spanCode->attr('native'));
        $t->same($expectedInlines, $jsonPacket['blocks'][0]['c']);
        $t->same($expectedInlines, $nativePacket['blocks'][0]['c']);
        $t->same(
            '[ Para [ Code ( "hook-code" , [ "php" ] , [ ( "data-hook" , "plib-2wcn6" ) ] ) "gt hook" , Space , Link ( "source" , [ "tracked" ] , [ ( "data-kind" , "native" ) ] ) [ Str "source" ] ( "https://example.test/source" , "Source" ) , Space , Span ( "span-id" , [ "mark" ] , [ ( "data-span" , "yes" ) ] ) [ Code ( "span-code" , [  ] , [  ] ) "inline" ] ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );

        $edited = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('code', array_replace($code->attrs, ['text' => 'gt done'])),
                new AstNode('link', array_replace($link->attrs, ['url' => 'https://example.test/edited']), $link->children),
                new AstNode('span', array_replace($span->attrs, ['id' => 'edited-span']), $span->children),
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($edited),
            'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $editedPacket) {
            $t->same(['t' => 'Code', 'c' => [$codeAttr, 'gt done']], $editedPacket['blocks'][0]['c'][0], "{$writer} regenerates edited code text");
            $t->same(['t' => 'Link', 'c' => [$linkAttr, [['t' => 'Str', 'c' => 'source']], ['https://example.test/edited', 'Source']]], $editedPacket['blocks'][0]['c'][1], "{$writer} regenerates edited link target");
            $t->same(['t' => 'Span', 'c' => [['edited-span', ['mark'], [['data-span', 'yes']]], [$spanCodeNative]]], $editedPacket['blocks'][0]['c'][2], "{$writer} regenerates edited span attr");
        }
    },
    'preserves textual native block and style container constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Plain [ Str "Plain", Space, Strong [ Str "payload" ] ]
, Para [ Emph [ Str "Para" ], Space, Underline [ Str "payload" ] ]
, Header 3 ( "native-heading" , [ "json-native" ] , [ ( "data-kind" , "header" ) ] ) [ Str "Head" ]
, CodeBlock ( "code" , [ "php" ] , [  ] ) "echo 1;"
, BlockQuote [ Para [ Str "Quote" ] ]
, BulletList [ [ Plain [ Str "Bullet" ] ] ]
, OrderedList ( 2 , LowerRoman , OneParen ) [ [ Plain [ Str "Order" ] ] ]
, DefinitionList [ ( [ Str "Term" ] , [ [ Plain [ Str "Definition" ] ] ] ) ]
, LineBlock [ [ Str "Line", Space, Strong [ Str "one" ] ] ]
, Div ( "div-id" , [ "wrap" ] , [ ( "data-kind" , "div" ) ] ) [ Para [ Str "Wrapped" ] ]
, Para [ Note [ Para [ Str "Note" ] ], Space, Quoted DoubleQuote [ Strong [ Str "quoted" ] ], Space, Math DisplayMath "z" ]
]
NATIVE;
        $plainNative = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Plain'],
            ['t' => 'Space'],
            ['t' => 'Strong', 'c' => [
                ['t' => 'Str', 'c' => 'payload'],
            ]],
        ]];
        $paraNative = ['t' => 'Para', 'c' => [
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'Para'],
            ]],
            ['t' => 'Space'],
            ['t' => 'Underline', 'c' => [
                ['t' => 'Str', 'c' => 'payload'],
            ]],
        ]];
        $headerAttr = ['native-heading', ['json-native'], [['data-kind', 'header']]];
        $headerNative = ['t' => 'Header', 'c' => [
            3,
            $headerAttr,
            [['t' => 'Str', 'c' => 'Head']],
        ]];
        $codeNative = ['t' => 'CodeBlock', 'c' => [
            ['code', ['php'], []],
            'echo 1;',
        ]];
        $quoteNative = ['t' => 'BlockQuote', 'c' => [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Quote'],
            ]],
        ]];
        $bulletNative = ['t' => 'BulletList', 'c' => [[[
            't' => 'Plain',
            'c' => [['t' => 'Str', 'c' => 'Bullet']],
        ]]]];
        $orderedNative = ['t' => 'OrderedList', 'c' => [
            [2, ['t' => 'LowerRoman'], ['t' => 'OneParen']],
            [[[
                't' => 'Plain',
                'c' => [['t' => 'Str', 'c' => 'Order']],
            ]]],
        ]];
        $definitionNative = ['t' => 'DefinitionList', 'c' => [[
            [['t' => 'Str', 'c' => 'Term']],
            [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Definition']]]]],
        ]]];
        $lineNative = ['t' => 'LineBlock', 'c' => [[
            ['t' => 'Str', 'c' => 'Line'],
            ['t' => 'Space'],
            ['t' => 'Strong', 'c' => [
                ['t' => 'Str', 'c' => 'one'],
            ]],
        ]]];
        $divNative = ['t' => 'Div', 'c' => [
            ['div-id', ['wrap'], [['data-kind', 'div']]],
            [['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Wrapped']]]],
        ]];
        $noteNative = ['t' => 'Note', 'c' => [
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Note']]],
        ]];
        $quotedNative = ['t' => 'Quoted', 'c' => [
            ['t' => 'DoubleQuote'],
            [['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'quoted']]]],
        ]];
        $mathNative = ['t' => 'Math', 'c' => [
            ['t' => 'DisplayMath'],
            'z',
        ]];
        $trailingParaNative = ['t' => 'Para', 'c' => [
            $noteNative,
            ['t' => 'Space'],
            $quotedNative,
            ['t' => 'Space'],
            $mathNative,
        ]];
        $expectedBlocks = [
            $plainNative,
            $paraNative,
            $headerNative,
            $codeNative,
            $quoteNative,
            $bulletNative,
            $orderedNative,
            $definitionNative,
            $lineNative,
            $divNative,
            $trailingParaNative,
        ];

        $nativeDocument = (new NativeReader())->read($native);
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);
        $plain = $nativeDocument->children[0];
        $paragraph = $nativeDocument->children[1];
        $heading = $nativeDocument->children[2];
        $code = $nativeDocument->children[3];
        $blockquote = $nativeDocument->children[4];
        $bullet = $nativeDocument->children[5];
        $ordered = $nativeDocument->children[6];
        $definition = $nativeDocument->children[7];
        $lineBlock = $nativeDocument->children[8];
        $div = $nativeDocument->children[9];
        $trailing = $nativeDocument->children[10];
        $note = $trailing->children[0];
        $quoted = $trailing->children[2];
        $math = $trailing->children[4];

        $t->same('Plain', $plain->attr('constructor'));
        $t->same($plainNative, $plain->attr('native'));
        $t->same('Strong', $plain->children[2]->attr('constructor'));
        $t->same('Para', $paragraph->attr('constructor'));
        $t->same($paraNative, $paragraph->attr('native'));
        $t->same('Emph', $paragraph->children[0]->attr('constructor'));
        $t->same('Underline', $paragraph->children[2]->attr('constructor'));
        $t->same('Header', $heading->attr('constructor'));
        $t->same($headerAttr, $heading->attr('attrNative'));
        $t->same($headerNative, $heading->attr('native'));
        $t->same('CodeBlock', $code->attr('constructor'));
        $t->same($codeNative, $code->attr('native'));
        $t->same('BlockQuote', $blockquote->attr('constructor'));
        $t->same($quoteNative, $blockquote->attr('native'));
        $t->same('BulletList', $bullet->attr('constructor'));
        $t->same($bulletNative, $bullet->attr('native'));
        $t->same('OrderedList', $ordered->attr('constructor'));
        $t->same('LowerRoman', $ordered->attr('listStyleConstructor'));
        $t->same('OneParen', $ordered->attr('listDelimiterConstructor'));
        $t->same($orderedNative, $ordered->attr('native'));
        $t->same('DefinitionList', $definition->attr('constructor'));
        $t->same($definitionNative, $definition->attr('native'));
        $t->same('LineBlock', $lineBlock->attr('constructor'));
        $t->same($lineNative, $lineBlock->attr('native'));
        $t->same('Div', $div->attr('constructor'));
        $t->same($divNative, $div->attr('native'));
        $t->same('Para', $trailing->attr('constructor'));
        $t->same($trailingParaNative, $trailing->attr('native'));
        $t->same('Note', $note->attr('constructor'));
        $t->same($noteNative, $note->attr('native'));
        $t->same('Quoted', $quoted->attr('constructor'));
        $t->same($quotedNative, $quoted->attr('native'));
        $t->same('Math', $math->attr('constructor'));
        $t->same($mathNative, $math->attr('native'));
        $t->same($expectedBlocks, $jsonPacket['blocks']);
        $t->same($expectedBlocks, $nativePacket['blocks']);

        $edited = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('heading', array_replace($heading->attrs, ['level' => 4]), $heading->children),
            new AstNode('ordered_list', array_replace($ordered->attrs, ['start' => 5]), $ordered->children),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($edited),
            'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $editedPacket) {
            $t->same(4, $editedPacket['blocks'][0]['c'][0], "{$writer} regenerates edited textual header");
            $t->same(5, $editedPacket['blocks'][1]['c'][0][0], "{$writer} regenerates edited textual ordered-list start");
        }
    },
];
