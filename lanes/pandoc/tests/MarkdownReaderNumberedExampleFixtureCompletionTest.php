<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-numbered-examples.md'
);

return [
    'maps selected upstream markdown numbered-example fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $list = $document->children[0] ?? new AstNode('missing');
            $alpha = $list->children[0] ?? new AstNode('missing');
            $beta = $list->children[1] ?? new AstNode('missing');
            $reference = $document->children[1] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(2, count($document->children));
            $t->same('ordered_list', $list->type);
            $t->same(1, $list->attr('start'));
            $t->same('example', $list->attr('style'));
            $t->same('two_parens', $list->attr('delimiter'));
            $t->same(2, count($list->children));
            $t->same('list_item', $alpha->type);
            $t->same(1, $alpha->attr('number'));
            $t->same('Alpha', $alpha->attr('text'));
            $t->same('list_item', $beta->type);
            $t->same(2, $beta->attr('number'));
            $t->same('Beta', $beta->attr('text'));
            $t->same('paragraph', $reference->type);
            $t->same('See (1).', $reference->attr('text'));
            $t->contains('OrderedList ( 1 , Example , TwoParens )', $native);
            $t->contains('Para [ Str "See" , Space , Str "(1)." ]', $native);
        },

    'records selected upstream markdown numbered-example fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $rows = array_values(array_filter(
                preg_split('/\R/', trim($fixture())) ?: [],
                static fn (string $row): bool => $row !== ''
            ));

            $t->same(3, count($rows));
            $t->same('(@first) Alpha', $rows[0]);
            $t->same('(@) Beta', $rows[1]);
            $t->same('See (@first).', $rows[2]);
        },
];
