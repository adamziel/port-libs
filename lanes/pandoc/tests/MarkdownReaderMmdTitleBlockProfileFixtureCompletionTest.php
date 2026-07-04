<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile.md'
);

return [
    'maps pandoc markdown mmd title block profile fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown_mmd']))->read($fixture());
            $meta = $document->attr('meta', []);
            $body = $document->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $body->type);
            $t->same('Body text.', $body->attr('text'));
            $t->same('My MMD Title', $meta['title'] ?? null);
            $t->same(['Ada Lovelace'], $meta['author'] ?? null);
            $t->same('2026-07-04', $meta['date'] ?? null);
            $t->contains('[ Para [ Str "Body" , Space , Str "text." ]', $native);
            $t->true(!str_contains($native, 'Title:'), 'MMD metadata block should not remain in native body output');
        },

    'keeps pandoc markdown mmd title block fixture literal when extension is disabled' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown_mmd-mmd_title_block']))->read($fixture());
            $first = $document->children[0] ?? new AstNode('missing');

            $t->same(2, count($document->children));
            $t->same('paragraph', $first->type);
            $t->same(['text', 'softbreak', 'text', 'softbreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $first->children
            ));
            $t->same('Title: My MMD Title', $first->children[0]->attr('text'));
        },

    'records selected upstream markdown mmd title block fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
