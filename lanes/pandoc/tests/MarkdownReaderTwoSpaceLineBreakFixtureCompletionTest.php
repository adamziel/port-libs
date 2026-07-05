<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-two-space-line-break.md'
);

return [
    'maps pandoc markdown two-space hard line break fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same("alpha\nbeta", $paragraph->attr('text'));
            $t->same(['text', 'linebreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $paragraph->children
            ));
            $t->same('alpha', ($paragraph->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('beta', ($paragraph->children[2] ?? new AstNode('missing'))->attr('text'));
            $t->contains('LineBreak', $native);
        },

    'records pandoc markdown two-space hard line break fixture byte boundary' =>
        static function (TestRunner $t) use ($fixture): void {
            $source = $fixture();
            $lines = explode("\n", rtrim($source, "\n"));

            $t->same(2, count($lines));
            $t->same('alpha  ', $lines[0]);
            $t->same('beta', $lines[1]);
        },
];
