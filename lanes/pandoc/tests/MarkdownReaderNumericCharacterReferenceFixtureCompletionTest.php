<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-numeric-character-references.md'
);

return [
    'maps selected upstream markdown numeric character-reference fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $text = $paragraph->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same(',DD', $paragraph->attr('text'));
            $t->same(1, count($paragraph->children));
            $t->same('text', $text->type);
            $t->same(',DD', $text->attr('text'));
        },

    'records upstream markdown numeric character-reference fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('&#44;&#x44;&#X44;', $cases[0]);
        },
];
