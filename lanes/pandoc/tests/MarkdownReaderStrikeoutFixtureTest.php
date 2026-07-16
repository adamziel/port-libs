<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown strikeout fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-strikeout.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $strikeout = $paragraph->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('This is strikeout.', $paragraph->attr('text'));
            $t->same(1, count($paragraph->children));
            $t->same('strikeout', $strikeout->type);
            $t->same(['text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $strikeout->children));
            $t->same('This is ', $strikeout->children[0]->attr('text'));
            $t->same('strikeout', $strikeout->children[1]->children[0]->attr('text'));
            $t->same('.', $strikeout->children[2]->attr('text'));
            $t->contains('<p><del>This is <em>strikeout</em>.</del></p>', $blocks);
        },

    'records upstream markdown strikeout fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-strikeout.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('~~This is *strikeout*.~~', $cases[0]);
        },
];
