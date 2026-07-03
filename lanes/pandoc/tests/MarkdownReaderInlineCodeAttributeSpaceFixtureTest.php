<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown inline-code attribute-space fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-code-attribute-space.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $code = $paragraph->children[0] ?? new AstNode('missing');
            $literalAttributes = $paragraph->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same(2, count($paragraph->children));
            $t->same('code', $code->type);
            $t->same('*', $code->attr('text'));
            $t->same('text', $literalAttributes->type);
            $t->same(' {.haskell .special x="7"}', $literalAttributes->attr('text'));
            $t->contains('<code>*</code> {.haskell .special x=&quot;7&quot;}', $blocks);
        },

    'records upstream markdown inline-code attribute-space fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-code-attribute-space.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('`*` {.haskell .special x="7"}', $cases[0]);
        },
];
