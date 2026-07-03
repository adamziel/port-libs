<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-mark.md'
);

return [
    'maps selected upstream markdown mark fixture to native mark span' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $mark = $paragraph->children[1] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('Before flagged claim after.', $paragraph->attr('text'));
            $t->same(['text', 'span', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same('Before ', $paragraph->children[0]->attr('text'));
            $t->same(' after.', $paragraph->children[2]->attr('text'));
            $t->same('span', $mark->type);
            $t->same(['mark'], $mark->attr('classes'));
            $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $mark->children));
            $t->same('flagged ', $mark->children[0]->attr('text'));
            $t->same('claim', $mark->children[1]->children[0]->attr('text'));
            $t->contains('Span ( "" , [ "mark" ] , [  ] ) [ Str "flagged" , Space , Strong [ Str "claim" ] ]', $native);
        },

    'records selected upstream markdown mark fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('Before ==flagged **claim**== after.', $cases[0]);
        },
];
