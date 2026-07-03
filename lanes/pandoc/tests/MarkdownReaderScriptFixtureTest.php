<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown superscript and subscript fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-superscript-subscript.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $superscripts = $document->children[0] ?? new AstNode('missing');
            $subscripts = $document->children[1] ?? new AstNode('missing');
            $notScript = $document->children[2] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(3, count($document->children));
            $t->same('paragraph', $superscripts->type);
            $t->same('paragraph', $subscripts->type);
            $t->same('paragraph', $notScript->type);

            $t->same(['text', 'superscript', 'text', 'superscript', 'text', 'superscript', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $superscripts->children
            ));
            $t->same('bc', $superscripts->children[1]->children[0]->attr('text'));
            $t->same('emph', $superscripts->children[3]->children[0]->type);
            $t->same('hello', $superscripts->children[3]->children[0]->children[0]->attr('text'));
            $t->same("hello\xC2\xA0there", $superscripts->children[5]->children[0]->attr('text'));

            $t->same(['text', 'subscript', 'text', 'subscript', 'text', 'subscript', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $subscripts->children
            ));
            $t->same('2', $subscripts->children[1]->children[0]->attr('text'));
            $t->same('23', $subscripts->children[3]->children[0]->attr('text'));
            $t->same("many\xC2\xA0of\xC2\xA0them", $subscripts->children[5]->children[0]->attr('text'));

            $t->same(['text', 'softbreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $notScript->children
            ));
            $t->contains('a^b c^d, a~b c~d.', $notScript->children[2]->attr('text'));
            $t->contains('<sup>bc</sup>', $blocks);
            $t->contains('<sup><em>hello</em></sup>', $blocks);
            $t->contains('<sup>hello' . "\xC2\xA0" . 'there</sup>', $blocks);
            $t->contains('<sub>2</sub>', $blocks);
            $t->contains('<sub>23</sub>', $blocks);
            $t->contains('<sub>many' . "\xC2\xA0" . 'of' . "\xC2\xA0" . 'them</sub>', $blocks);
        },

    'records upstream markdown superscript and subscript fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-superscript-subscript.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(3, count($cases));
            $t->same('Superscripts:  a^bc^d a^*hello*^ a^hello\ there^.', $cases[0]);
            $t->same('Subscripts: H~2~O, H~23~O, H~many\ of\ them~O.', $cases[1]);
            $t->same(
                "These should not be superscripts or subscripts,\nbecause of the unescaped spaces:  a^b c^d, a~b c~d.",
                $cases[2]
            );
        },
];
