<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown inline math fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-math.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $math = $paragraph->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same(3, count($paragraph->children));
            $t->same('math', $math->type);
            $t->same('x_1 + y', $math->attr('text'));
            $t->same(false, $math->attr('display'));
            $t->contains('Inline math <span class="math inline">\(x_1 + y\)</span> stays inline.', $blocks);
        },

    'records upstream markdown inline math fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-math.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('Inline math $x_1 + y$ stays inline.', $cases[0]);
        },

    'maps selected upstream markdown dollar display math fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $intro = $document->children[0] ?? new AstNode('missing');
            $mathParagraph = $document->children[1] ?? new AstNode('missing');
            $math = $mathParagraph->children[0] ?? new AstNode('missing');
            $after = $document->children[2] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(3, count($document->children));
            $t->same('paragraph', $intro->type);
            $t->same('Before display:', $intro->attr('text'));
            $t->same('paragraph', $mathParagraph->type);
            $t->same('math', $math->type);
            $t->same('x^2 + y^2 = z^2', $math->attr('text'));
            $t->same(true, $math->attr('display'));
            $t->same('paragraph', $after->type);
            $t->same('After.', $after->attr('text'));
            $t->contains('<span class="math display">\[x^2 + y^2 = z^2\]</span>', $blocks);
        },
];
