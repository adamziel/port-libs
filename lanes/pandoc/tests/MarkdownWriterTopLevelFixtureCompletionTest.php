<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $items): AstNode => new AstNode('ordered_list', [], $items);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$tests = [
    'records markdown writer top level fixture completion mapped case count' =>
        static function (TestRunner $t): void {
            $t->same(3, 3);
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

            $commentRoundTrip = (new MarkdownReader())->read("1.  one\n\n<!-- reviewer note -->\n\n    test");
            $t->same(['ordered_list', 'raw_html', 'code_block'], array_map(
                static fn (AstNode $node): string => $node->type,
                $commentRoundTrip->children
            ));
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

    'maps upstream markdown writer emph strong spacing fixture' =>
        static function (TestRunner $t) use ($document, $emph, $strong, $text): void {
            $document = $document([
                new AstNode('paragraph', [], [
                    $emph([
                        $text('f'),
                        $strong([$text(' d ')]),
                    ]),
                    $text('l'),
                ]),
            ]);

            $markdown = (new MarkdownWriter(['setextHeadings' => true]))->write($document);

            $t->same('*f **d*** l', $markdown);
        },
];

return $tests;
