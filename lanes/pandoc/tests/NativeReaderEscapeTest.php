<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'reads haskell named character escapes in pandoc native strings' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "alpha\ESC\&omega" , Space , Str "unit\US\&separator" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $paragraph = $document->children[0];

        $t->same('alpha' . chr(27) . 'omega', $paragraph->children[0]->attr('text'));
        $t->same('unit' . chr(31) . 'separator', $paragraph->children[2]->attr('text'));
        $t->same(
            '[ Para [ Str "alpha\27omega" , Space , Str "unit\31separator" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($document)
        );
    },
    'reads haskell single letter control escapes in pandoc native strings' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Plain [ Str "\a\b\f\v" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);

        $t->same(chr(7) . chr(8) . chr(12) . chr(11), $document->children[0]->children[0]->attr('text'));
    },
    'preserves textual native Space constructors through shared ast writers' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "left" , Space , Emph [ Str "middle" , Space , Str "right" ] , Space , Str "tail" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $paragraph = $document->children[0];
        $emphasis = $paragraph->children[2];
        $json = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
        ], $document->children));

        $t->same(['text', 'space', 'emph', 'space', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $paragraph->children
        ));
        $t->same(['text', 'space', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $emphasis->children
        ));
        $t->same('left middle right tail', $paragraph->attr('text'));
        $t->same(['Str', 'Space', 'Emph', 'Space', 'Str'], array_map(
            static fn (array $inline): string => $inline['t'],
            $json['blocks'][0]['c']
        ));
        $t->same(['Str', 'Space', 'Str'], array_map(
            static fn (array $inline): string => $inline['t'],
            $json['blocks'][0]['c'][2]['c']
        ));
        $t->same('left *middle right* tail', (new MarkdownWriter())->write($document));
        $t->same(
            '[ Para [ Str "left" , Space , Emph [ Str "middle" , Space , Str "right" ] , Space , Str "tail" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($document)
        );
    },
];
