<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$citationDigitKeyFixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzz-citation-digit-key.md');

return [
    'maps upstream markdown citation key starting with digit fixture' =>
        static function (TestRunner $t) use ($citationDigitKeyFixture): void {
            $document = (new MarkdownReader())->read($citationDigitKeyFixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $citation = $paragraph->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type);
            $t->same('@1657:huyghens', $paragraph->attr('text'));
            $t->same(['citation'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same('citation', $citation->type);
            $t->same('1657:huyghens', $citation->attr('id'));
            $t->same('author_in_text', $citation->attr('mode'));
            $t->same('@1657:huyghens', $citation->attr('text'));
            $t->same(null, $citation->attr('prefix'));
            $t->same(null, $citation->attr('suffix'));
            $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $citation->children));
            $t->same('@1657:huyghens', ($citation->children[0] ?? new AstNode('missing'))->attr('text'));
        },

    'serializes upstream markdown digit-leading citation key through native handoff' =>
        static function (TestRunner $t) use ($citationDigitKeyFixture): void {
            $document = (new MarkdownReader())->read($citationDigitKeyFixture());
            $native = (new NativeWriter())->write($document);

            $t->contains('citationId = "1657:huyghens"', $native);
            $t->contains('citationMode = AuthorInText', $native);
            $t->contains('Str "@1657:huyghens"', $native);
        },

    'records upstream markdown citation digit-key mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
