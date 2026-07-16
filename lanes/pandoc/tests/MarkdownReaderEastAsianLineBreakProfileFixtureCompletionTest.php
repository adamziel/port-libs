<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zz-east-asian-line-break-profile.md'
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps pandoc markdown east asian line break profile fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown+east_asian_line_breaks']))->read($fixture);
            $joined = $document->children[0] ?? new AstNode('missing');
            $latin = $document->children[1] ?? new AstNode('missing');

            $t->same(2, count($document->children));
            $t->same('paragraph', $joined->type);
            $t->same(['text'], $inlineTypes($joined));
            $t->same('東京 source', $joined->attr('text'));
            $t->same('paragraph', $latin->type);
            $t->same(['text', 'softbreak', 'text'], $inlineTypes($latin));
            $t->same('A B', $latin->attr('text'));
        },

    'keeps pandoc markdown east asian line break profile fixture soft by default' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture);
            $first = $document->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $first->type);
            $t->same(['text', 'softbreak', 'text'], $inlineTypes($first));
            $t->same('東 京 source', $first->attr('text'));
        },
];
