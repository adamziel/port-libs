<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps checked-in upstream markdown definition-list fixture' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-definition-lists.md');
        $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
        $list = $document->children[0] ?? new AstNode('missing');
        $firstItem = $list->children[0] ?? new AstNode('missing');
        $secondItem = $list->children[1] ?? new AstNode('missing');

        $t->same('definition_list', $list->type);
        $t->same(2, count($list->children));
        $t->same('foo1', $firstItem->attr('term'));
        $t->same('bar', $firstItem->children[1]->children[0]->attr('text'));
        $t->same('foo2', $secondItem->attr('term'));
        $t->same('bar2', $secondItem->children[1]->children[0]->attr('text'));
        $t->same('bar3', $secondItem->children[2]->children[0]->attr('text'));
    },

    'maps checked-in upstream markdown blank-first definition-list fixture' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-definition-list-blank-first.md');
        $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
        $list = $document->children[0] ?? new AstNode('missing');
        $firstItem = $list->children[0] ?? new AstNode('missing');
        $secondItem = $list->children[1] ?? new AstNode('missing');
        $firstDefinition = $firstItem->children[1] ?? new AstNode('missing');
        $secondFirstDefinition = $secondItem->children[1] ?? new AstNode('missing');
        $secondSecondDefinition = $secondItem->children[2] ?? new AstNode('missing');

        $t->same('definition_list', $list->type);
        $t->same(2, count($list->children));
        $t->same('foo1', $firstItem->attr('term'));
        $t->same('definition', $firstDefinition->type);
        $t->same(true, (bool) $firstDefinition->attr('loose'));
        $t->same('bar', $firstDefinition->children[0]->attr('text'));
        $t->same('foo2', $secondItem->attr('term'));
        $t->same(false, (bool) $secondFirstDefinition->attr('loose'));
        $t->same('bar2', $secondFirstDefinition->children[0]->attr('text'));
        $t->same(true, (bool) $secondSecondDefinition->attr('loose'));
        $t->same('bar3', $secondSecondDefinition->children[0]->attr('text'));
    },

    'maps checked-in upstream markdown blank-second definition-list fixture' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-definition-list-blank-second.md');
        $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
        $list = $document->children[0] ?? new AstNode('missing');
        $firstItem = $list->children[0] ?? new AstNode('missing');
        $secondItem = $list->children[1] ?? new AstNode('missing');
        $firstDefinition = $firstItem->children[1] ?? new AstNode('missing');
        $secondFirstDefinition = $secondItem->children[1] ?? new AstNode('missing');
        $secondSecondDefinition = $secondItem->children[2] ?? new AstNode('missing');

        $t->same('definition_list', $list->type);
        $t->same(2, count($list->children));
        $t->same('foo1', $firstItem->attr('term'));
        $t->same(false, (bool) $firstDefinition->attr('loose'));
        $t->same('bar', $firstDefinition->children[0]->attr('text'));
        $t->same('foo2', $secondItem->attr('term'));
        $t->same(false, (bool) $secondFirstDefinition->attr('loose'));
        $t->same('bar2', $secondFirstDefinition->children[0]->attr('text'));
        $t->same(false, (bool) $secondSecondDefinition->attr('loose'));
        $t->same('bar3', $secondSecondDefinition->children[0]->attr('text'));
    },

    'maps checked-in upstream markdown nested-list definition-list fixture' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-definition-list-nested-list.md');
        $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');
        $definition = $item->children[1] ?? new AstNode('missing');
        $nested = $definition->children[0] ?? new AstNode('missing');
        $nestedItem = $nested->children[0] ?? new AstNode('missing');

        $t->same('definition_list', $list->type);
        $t->same(1, count($list->children));
        $t->same('foo', $item->attr('term'));
        $t->same('definition', $definition->type);
        $t->same(false, (bool) $definition->attr('loose'));
        $t->same('bullet_list', $nested->type);
        $t->same('-', $nested->attr('marker'));
        $t->same('bar', $nestedItem->attr('text'));
    },

    'maps checked-in upstream markdown html-div definition-list fixture' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-definition-list-html-div.md');
        $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
        $div = $document->children[0] ?? new AstNode('missing');
        $list = $div->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');
        $definition = $item->children[1] ?? new AstNode('missing');
        $nested = $definition->children[0] ?? new AstNode('missing');
        $nestedItem = $nested->children[0] ?? new AstNode('missing');

        $t->same('div', $div->type);
        $t->same('definition_list', $list->type);
        $t->same(1, count($list->children));
        $t->same('foo', $item->attr('term'));
        $t->same('definition', $definition->type);
        $t->same(false, (bool) $definition->attr('loose'));
        $t->same('bullet_list', $nested->type);
        $t->same('-', $nested->attr('marker'));
        $t->same('bar', $nestedItem->attr('text'));
    },

    'keeps checked-in upstream markdown definition-list fixture behind extension gate' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-definition-lists.md');
        $strict = (new MarkdownReader(['format' => 'markdown_strict']))->read($source);
        $strictWithDefinitionLists = (new MarkdownReader(['format' => 'markdown_strict+definition_lists']))->read($source);

        $t->true(
            !in_array('definition_list', array_map(static fn (AstNode $node): string => $node->type, $strict->children), true),
            'markdown_strict should not parse the fixture without +definition_lists'
        );
        $t->same('definition_list', ($strictWithDefinitionLists->children[0] ?? new AstNode('missing'))->type);
    },
];
