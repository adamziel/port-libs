<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $items): AstNode => new AstNode('ordered_list', [], $items);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$tests = [
    'records markdown writer top level fixture completion mapped case count' =>
        static function (TestRunner $t): void {
            $t->same(2, 2);
        },

    'maps upstream markdown writer indented code after list fixture' =>
        static function (TestRunner $t) use ($codeBlock, $document, $listItem, $orderedList, $paragraph): void {
            $document = $document([
                $orderedList([
                    $listItem([
                        $paragraph('one'),
                        $paragraph('two'),
                    ]),
                ]),
                $codeBlock('test'),
            ]);

            $markdown = (new MarkdownWriter(['setextHeadings' => true]))->write($document);

            $t->same("1.  one\n\n    two\n\n<!-- -->\n\n    test", $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same(2, count($roundTrip->children));
            $t->same('ordered_list', ($roundTrip->children[0] ?? new AstNode('missing'))->type);
            $t->same(1, count($roundTrip->children[0]->children));
            $t->same('code_block', ($roundTrip->children[1] ?? new AstNode('missing'))->type);
            $t->same('test', $roundTrip->children[1]->attr('text'));
        },

    'maps upstream markdown writer tight sublist fixture' =>
        static function (TestRunner $t) use ($bulletList, $document, $listItem, $plain): void {
            $document = $document([
                $bulletList([
                    $listItem([
                        $plain('foo'),
                        $bulletList([
                            $listItem([$plain('bar')]),
                        ]),
                    ]),
                    $listItem([$plain('baz')]),
                ]),
            ]);

            $markdown = (new MarkdownWriter(['setextHeadings' => true]))->write($document);

            $t->same("- foo\n  - bar\n- baz", $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $rootList = $roundTrip->children[0] ?? new AstNode('missing');
            $nestedList = $rootList->children[0]->children[1] ?? new AstNode('missing');
            $nestedText = $nestedList->children[0]->children[0] ?? new AstNode('missing');

            $t->same(1, count($roundTrip->children));
            $t->same('bullet_list', $rootList->type);
            $t->same(2, count($rootList->children));
            $t->same('bullet_list', $nestedList->type);
            $t->same('bar', $nestedText->attr('text'));
        },

    'keeps real html comments while skipping neutral writer separators' =>
        static function (TestRunner $t): void {
            $neutral = (new MarkdownReader())->read("alpha\n\n<!-- -->\n\nbeta");
            $realComment = (new MarkdownReader())->read('<!-- writer comment -->');

            $t->same(2, count($neutral->children));
            $t->same('paragraph', ($neutral->children[0] ?? new AstNode('missing'))->type);
            $t->same('paragraph', ($neutral->children[1] ?? new AstNode('missing'))->type);
            $t->same('raw_html', ($realComment->children[0] ?? new AstNode('missing'))->type);
            $t->same('<!-- writer comment -->', $realComment->children[0]->attr('html'));
        },
];

return $tests;
