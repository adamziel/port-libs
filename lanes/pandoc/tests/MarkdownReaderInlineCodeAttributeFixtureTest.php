<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown inline-code attribute fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-code-attribute.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $code = $paragraph->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same(1, count($paragraph->children));
            $t->same('code', $code->type);
            $t->same('document.write("Hello");', $code->attr('text'));
            $t->same(['javascript'], $code->attr('classes'));
            $t->same([], $code->attr('attributes', []));
            $t->contains('<code class="javascript">document.write(&quot;Hello&quot;);</code>', $blocks);
        },

    'records upstream markdown inline-code attribute fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-code-attribute.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('`document.write("Hello");`{.javascript}', $cases[0]);
        },
];
