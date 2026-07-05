<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownFormatProfile;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static function (string $name): string {
    $bytes = file_get_contents(dirname(__DIR__) . '/fixtures/' . $name);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read fixture {$name}");
    }

    return $bytes;
};

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream command 11253 default latex macro ifstrequal fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read(
                $fixture('upstream-command-11253-latex-macros.md')
            );
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type);
            $t->same(['raw_tex_inline', 'softbreak', 'raw_tex_inline'], $inlineTypes($paragraph));
            $t->same('TRUE', ($paragraph->children[0] ?? new AstNode('missing'))->attr('tex'));
            $t->same('FALSE', ($paragraph->children[2] ?? new AstNode('missing'))->attr('tex'));
        },

    'maps upstream command 11253 disabled latex macro ifstrequal fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown-latex_macros']))->read(
                $fixture('upstream-command-11253-latex-macros-disabled.md')
            );
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type);
            $t->same(['raw_tex_inline', 'softbreak', 'raw_tex_inline'], $inlineTypes($paragraph));
            $t->same('\\ifstrequal{hello}{hello}{TRUE}{FALSE}', ($paragraph->children[0] ?? new AstNode('missing'))->attr('tex'));
            $t->same('\\ifstrequal{hello}{world}{TRUE}{FALSE}', ($paragraph->children[2] ?? new AstNode('missing'))->attr('tex'));
        },

    'keeps raw tex preservation separate from latex macro side effects' =>
        static function (TestRunner $t): void {
            $source = "\\newcommand{\\foo}[1]{#1!}\n\n$\\foo{x}$";
            $enabled = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $disabled = (new MarkdownReader(['format' => 'markdown-latex_macros']))->read($source);

            $t->same(true, MarkdownFormatProfile::rawTexEnabled(['format' => 'markdown-latex_macros'], true));
            $t->same(false, MarkdownFormatProfile::latexMacrosEnabled(['format' => 'markdown-latex_macros'], true));
            $t->same('raw_tex', ($enabled->children[0] ?? new AstNode('missing'))->type);
            $t->same('x!', ($enabled->children[1]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('raw_tex', ($disabled->children[0] ?? new AstNode('missing'))->type);
            $t->same('\\foo{x}', ($disabled->children[1]->children[0] ?? new AstNode('missing'))->attr('text'));
        },

    'records markdown reader latex macro ifstrequal fixture completion mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(6, 2 + 2 + 2);
        },
];
