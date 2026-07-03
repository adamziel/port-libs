<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-raw-html-invalid-comment.md'
);

return [
    'maps selected upstream markdown raw html technically invalid comment fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $raw = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('raw_html', $raw->type);
            $t->same('<!-- pandoc --help -->', $raw->attr('html'));
        },

    'keeps selected upstream markdown raw html invalid comment gated by raw-html profile boundary' =>
        static function (TestRunner $t) use ($fixture): void {
            $enabled = (new MarkdownReader(['format' => 'markdown+raw_html']))->read($fixture());
            $disabled = (new MarkdownReader(['format' => 'markdown-raw_html-smart']))->read($fixture());
            $enabledBlock = $enabled->children[0] ?? new AstNode('missing');
            $disabledBlock = $disabled->children[0] ?? new AstNode('missing');

            $t->same('raw_html', $enabledBlock->type);
            $t->same('<!-- pandoc --help -->', $enabledBlock->attr('html'));
            $t->same('paragraph', $disabledBlock->type);
            $t->same('<!-- pandoc --help -->', $disabledBlock->attr('text'));
        },

    'records upstream markdown raw html invalid comment fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('<!-- pandoc --help -->', $cases[0]);
        },
];
