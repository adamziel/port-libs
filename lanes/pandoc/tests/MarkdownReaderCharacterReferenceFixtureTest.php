<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps selected upstream markdown character-reference fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-character-references.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $text = $paragraph->children[0] ?? new AstNode('missing');
            $expected = html_entity_decode('&lang; &ouml;', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same($expected, $paragraph->attr('text'));
            $t->same(1, count($paragraph->children));
            $t->same('text', $text->type);
            $t->same($expected, $text->attr('text'));
        },

    'records upstream markdown character-reference fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-character-references.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('&lang; &ouml;', $cases[0]);
        },
];
