<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-hard-line-break-profile.md'
);

return [
    'maps pandoc markdown hard line break profile fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown+hard_line_breaks']))->read($fixture);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same(['text', 'linebreak', 'text', 'linebreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $paragraph->children
            ));
            $t->same("alpha\nbeta\ngamma", $paragraph->attr('text'));
            $t->contains('LineBreak', $native);
            $t->contains('Str "gamma"', $native);
        },

    'keeps pandoc markdown hard line break profile fixture soft by default' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture);
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['text', 'softbreak', 'text', 'softbreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $paragraph->children
            ));
            $t->same('alpha beta gamma', $paragraph->attr('text'));
        },
];
