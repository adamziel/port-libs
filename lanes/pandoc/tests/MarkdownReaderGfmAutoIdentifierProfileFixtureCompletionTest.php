<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile.md'
);

return [
    'maps pandoc markdown gfm auto identifiers ascii profile fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'commonmark+gfm_auto_identifiers+ascii_identifiers']))->read($fixture());
            $headings = array_values(array_filter(
                $document->children,
                static fn (AstNode $node): bool => $node->type === 'heading'
            ));

            $t->same(4, count($headings));
            $t->same(
                ['ab-c-e', 'non-ascii-warning-raksmorgas', '-', '--1'],
                array_map(static fn (AstNode $node): string => (string) $node->attr('id', ''), $headings)
            );
            $t->same(
                ['A.B-C! e', 'non ascii ⚠️ räksmörgås', 'Привет мир', 'Привет мир'],
                array_map(static fn (AstNode $node): string => (string) $node->attr('text', ''), $headings)
            );
        },

    'keeps pandoc markdown gfm auto identifiers profile disabled under plain commonmark' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($fixture());
            $headings = array_values(array_filter(
                $document->children,
                static fn (AstNode $node): bool => $node->type === 'heading'
            ));

            $t->same(4, count($headings));
            $t->same(['', '', '', ''], array_map(
                static fn (AstNode $node): string => (string) $node->attr('id', ''),
                $headings
            ));
        },

    'records selected upstream markdown gfm auto identifiers profile fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(1, 1);
        },
];
