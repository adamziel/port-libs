<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps selected upstream markdown link-title entity fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-link-title-entities.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $link = $paragraph->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('link', $link->type);
            $t->same('/url', $link->attr('url'));
            $t->same('title ' . html_entity_decode('&lang; &ouml; &#44;', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), $link->attr('title'));
            $t->same('link', ($link->children[0] ?? new AstNode('missing'))->attr('text'));
        },

    'records upstream markdown link-title entity fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-link-title-entities.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('[link](/url "title &lang; &ouml; &#44;")', $cases[0]);
        },
];
