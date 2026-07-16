<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownFormatProfile;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile.md'
);

return [
    'maps upstream markdown github yaml-looking front matter as literal markdown' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown_github']))->read($fixture());
            $horizontalRule = $document->children[0] ?? new AstNode('missing');
            $heading = $document->children[1] ?? new AstNode('missing');
            $body = $document->children[2] ?? new AstNode('missing');

            $t->same([], $document->attr('meta', []));
            $t->same(3, count($document->children));
            $t->same('horizontal_rule', $horizontalRule->type);
            $t->same('heading', $heading->type);
            $t->same(2, $heading->attr('level'));
            $t->same('title-github-yaml', $heading->attr('id'));
            $t->same('title: Github YAML', $heading->attr('text'));
            $t->same('paragraph', $body->type);
            $t->same('body', $body->attr('text'));
        },

    'keeps upstream markdown github yaml metadata gate distinct from gfm' =>
        static function (TestRunner $t) use ($fixture): void {
            $githubDocument = (new MarkdownReader(['format' => 'markdown_github']))->read($fixture());
            $gfmDocument = (new MarkdownReader(['format' => 'gfm']))->read($fixture());

            $t->same(false, MarkdownFormatProfile::yamlMetadataEnabled(['format' => 'markdown_github'], true));
            $t->same(true, MarkdownFormatProfile::yamlMetadataEnabled(['format' => 'gfm'], false));
            $t->same([], $githubDocument->attr('meta', []));
            $t->same('Github YAML', $gfmDocument->attr('meta')['title'] ?? null);
            $t->same(['paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $gfmDocument->children
            ));
            $t->same('body', ($gfmDocument->children[0] ?? new AstNode('missing'))->attr('text'));
        },

    'records upstream markdown github yaml profile mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, count([
                'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile.md',
            ]));
        },
];
