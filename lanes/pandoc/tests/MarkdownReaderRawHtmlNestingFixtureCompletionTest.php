<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-raw-html-nesting.md'
);

return [
    'maps selected upstream markdown raw html nesting fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $open = $document->children[0] ?? new AstNode('missing');
            $plain = $document->children[1] ?? new AstNode('missing');
            $close = $document->children[2] ?? new AstNode('missing');

            $t->same(3, count($document->children));
            $t->same('raw_html', $open->type);
            $t->same('<del>', $open->attr('html'));
            $t->same('plain', $plain->type);
            $t->same('test', $plain->attr('text'));
            $t->same('raw_html', $close->type);
            $t->same('</del>', $close->attr('html'));
        },

    'keeps selected upstream markdown raw html nesting gated by raw-html profile boundary' =>
        static function (TestRunner $t) use ($fixture): void {
            $enabled = (new MarkdownReader(['format' => 'markdown+raw_html']))->read($fixture());
            $disabled = (new MarkdownReader(['format' => 'markdown-raw_html']))->read($fixture());

            $t->same(['raw_html', 'plain', 'raw_html'], array_map(
                static fn (AstNode $node): string => $node->type,
                $enabled->children
            ));
            $t->same('paragraph', $disabled->children[0]->type ?? 'missing');
            $t->same('<del>test</del>', $disabled->children[0]->attr('text') ?? null);
        },

    'records upstream markdown raw html nesting fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('<del>test</del>', $cases[0]);
        },
];
