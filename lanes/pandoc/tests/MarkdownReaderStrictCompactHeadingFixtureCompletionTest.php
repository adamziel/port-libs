<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-strict-compact-heading.md'
);

return [
    'maps pandoc markdown strict compact atx heading fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown_strict']))->read($fixture);
            $heading = $document->children[0] ?? new AstNode('missing');

            $t->same(['heading'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same('heading', $heading->type);
            $t->same(1, $heading->attr('level'));
            $t->same('hi', $heading->attr('text'));
            $t->same('', $heading->attr('id', ''));
        },
];
