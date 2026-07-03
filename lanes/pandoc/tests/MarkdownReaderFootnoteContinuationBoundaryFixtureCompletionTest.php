<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-footnote-continuation-boundaries.md'
);

$cases = static function () use ($fixture): array {
    return array_map(
        static fn (string $case): string => rtrim($case, "\n"),
        array_values(array_filter(
            preg_split('/\n(?=\\[\\^1\\]\n)/', trim($fixture())) ?: [],
            static fn (string $case): bool => $case !== ''
        ))
    );
};

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps selected upstream markdown footnote continuation boundary fixture' =>
        static function (TestRunner $t) use ($cases, $inlineTypes): void {
            [$flushLeftCase, $indentedCase] = $cases();
            $flushLeftDocument = (new MarkdownReader(['format' => 'markdown']))->read($flushLeftCase);
            $indentedDocument = (new MarkdownReader(['format' => 'markdown']))->read($indentedCase);
            $firstReference = $flushLeftDocument->children[0] ?? new AstNode('missing');
            $outsideParagraph = $flushLeftDocument->children[1] ?? new AstNode('missing');
            $secondReference = $indentedDocument->children[0] ?? new AstNode('missing');
            $firstNote = $firstReference->children[0] ?? new AstNode('missing');
            $secondNote = $secondReference->children[0] ?? new AstNode('missing');

            $t->same(['paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $flushLeftDocument->children
            ));
            $t->same(['paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $indentedDocument->children
            ));

            $t->same(['note'], $inlineTypes($firstReference));
            $t->same('1', $firstNote->attr('label'));
            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $firstNote->children));
            $t->same('my note', ($firstNote->children[0] ?? new AstNode('missing'))->attr('text'));

            $t->same(['text'], $inlineTypes($outsideParagraph));
            $t->same('not in note', $outsideParagraph->attr('text'));
            $t->same('not in note', ($outsideParagraph->children[0] ?? new AstNode('missing'))->attr('text'));

            $t->same(['note'], $inlineTypes($secondReference));
            $t->same('1', $secondNote->attr('label'));
            $t->same(['paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $secondNote->children
            ));
            $t->same('my note', ($secondNote->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('in note', ($secondNote->children[1] ?? new AstNode('missing'))->attr('text'));
        },

    'records selected upstream markdown footnote continuation boundary fixture mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $cases = $cases();

            $t->same(2, count($cases));
            $t->same("[^1]\n\n[^1]: my note\n\n     \nnot in note", $cases[0]);
            $t->same("[^1]\n\n[^1]: my note\n     \n    in note", $cases[1]);
        },
];
