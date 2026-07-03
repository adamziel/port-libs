<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-fenced-code-attributes.md'
);

return [
    'maps upstream markdown fenced code attributes fixture' => static function (TestRunner $t) use ($fixture): void {
        $document = (new MarkdownReader())->read($fixture);
        $code = $document->children[0] ?? new AstNode('missing');

        $t->same(['code_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('code_block', $code->type);
        $t->same('reader-fixture', $code->attr('id'));
        $t->same(['php'], $code->attr('classes'));
        $t->same(['data-phase' => 'selected'], $code->attr('attributes'));
        $t->same('{.php #reader-fixture data-phase="selected"}', $code->attr('info'));
        $t->same('echo "markdown fixture";', $code->attr('text'));
    },
];
