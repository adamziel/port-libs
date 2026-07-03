<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-footnote-definitions.md'
);

return [
    'maps selected upstream markdown recursive footnote definition fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $firstNote = $document->children[0]->children[0] ?? new AstNode('missing');
            $noteBody = $firstNote->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('note', $firstNote->type);
            $t->same('1', $firstNote->attr('label'));
            $t->same(1, count($firstNote->children));
            $t->same('paragraph', $noteBody->type);
            $t->same('See [^1]', $noteBody->attr('text'));
            $t->same(['text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $noteBody->children
            ));
        },

    'records selected upstream markdown recursive footnote definition fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $source = $fixture();
            $cases = array_values(array_filter(
                preg_split('/\n(?=\\[\\^[0-9]+\\]\n)/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same("[^1]\n\n[^1]: See [^1]", $cases[0]);
        },
];
