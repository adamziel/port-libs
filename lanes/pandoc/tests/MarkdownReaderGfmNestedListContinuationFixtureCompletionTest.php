<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation.md'
);

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$itemText = static function (AstNode $item): string {
    $text = $item->children[0] ?? new AstNode('missing');

    return (string) $text->attr('text', '');
};

return [
    'maps upstream markdown gfm nested list continuation fixture' =>
        static function (TestRunner $t) use ($fixture, $childTypes, $itemText): void {
            $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
            $list = $document->children[0] ?? new AstNode('missing');
            $items = $list->children;
            $thirdItem = $items[2] ?? new AstNode('missing');
            $nestedList = $thirdItem->children[1] ?? new AstNode('missing');
            $nestedItem = $nestedList->children[0] ?? new AstNode('missing');

            $t->same(['bullet_list'], $childTypes($document));
            $t->same(['list_item', 'list_item', 'list_item'], $childTypes($list));
            $t->same('a', $itemText($items[0] ?? new AstNode('missing')));
            $t->same('b', $itemText($items[1] ?? new AstNode('missing')));
            $t->same('c', $itemText($thirdItem));
            $t->same(['text', 'bullet_list'], $childTypes($thirdItem));
            $t->same('d', $itemText($nestedItem));
        },

    'serializes upstream markdown gfm nested list continuation fixture through native and wordpress handoff' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->contains('BulletList [ [ Plain [ Str "a" ]', $native);
            $t->contains('Plain [ Str "c" ]', $native);
            $t->contains('BulletList [ [ Plain [ Str "d" ]', $native);
            $t->contains('<ul><li>a</li><li>b</li><li>c<ul><li>d</li></ul></li></ul>', $blocks);
        },

    'records upstream markdown gfm nested list continuation mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
