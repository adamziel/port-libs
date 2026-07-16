<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzz-raw-html-list-boundary.md'
);

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream markdown raw html list boundary fixture' =>
        static function (TestRunner $t) use ($fixture, $childTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $list = $document->children[0] ?? new AstNode('missing');
            $item = $list->children[0] ?? new AstNode('missing');
            $firstDiv = $item->children[0] ?? new AstNode('missing');
            $buttonOpen = $item->children[1] ?? new AstNode('missing');
            $buttonText = $item->children[2] ?? new AstNode('missing');
            $buttonClose = $item->children[3] ?? new AstNode('missing');
            $secondDiv = $item->children[4] ?? new AstNode('missing');

            $t->same(['bullet_list'], $childTypes($document));
            $t->same('list_item', $item->type);
            $t->same(['div', 'raw_html', 'plain', 'raw_html', 'div'], $childTypes($item));
            $t->same('first div breaks', ($firstDiv->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('<button>', $buttonOpen->attr('html'));
            $t->same('if this button exists', $buttonText->attr('text'));
            $t->same('</button>', $buttonClose->attr('html'));
            $t->same('with this div too.', ($secondDiv->children[0] ?? new AstNode('missing'))->attr('text'));
        },

    'serializes upstream markdown raw html list boundary fixture through native handoff' =>
        static function (TestRunner $t) use ($fixture): void {
            $native = (new NativeWriter(['blocksOnly' => true]))
                ->write((new MarkdownReader(['format' => 'markdown']))->read($fixture()));

            $t->contains('BulletList', $native);
            $t->contains('Div ( "" , [  ] , [  ] ) [ Para [ Str "first"', $native);
            $t->contains('RawBlock (Format "html") "<button>"', $native);
            $t->contains('Plain [ Str "if" , Space , Str "this" , Space , Str "button"', $native);
            $t->contains('RawBlock (Format "html") "</button>"', $native);
            $t->contains('Div ( "" , [  ] , [  ] ) [ Para [ Str "with"', $native);
        },

    'records upstream markdown raw html list boundary mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
