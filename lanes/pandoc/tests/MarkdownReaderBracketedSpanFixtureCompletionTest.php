<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bracketed-spans.md'
);

return [
    'maps selected upstream markdown bracketed-span fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $spanParagraph = $document->children[0] ?? new AstNode('missing');
            $span = $spanParagraph->children[0] ?? new AstNode('missing');
            $smallCapsParagraph = $document->children[1] ?? new AstNode('missing');
            $smallCaps = $smallCapsParagraph->children[1] ?? new AstNode('missing');
            $link = $span->children[2] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(2, count($document->children));
            $t->same('paragraph', $spanParagraph->type);
            $t->same('source link', $spanParagraph->attr('text'));
            $t->same(['span'], array_map(static fn (AstNode $node): string => $node->type, $spanParagraph->children));
            $t->same('span', $span->type);
            $t->same('span-review', $span->attr('id'));
            $t->same(['audit'], $span->attr('classes'));
            $t->same(['data-kind' => 'bracketed span'], $span->attr('attributes'));
            $t->same(['emph', 'text', 'link'], array_map(static fn (AstNode $node): string => $node->type, $span->children));
            $t->same('source', $span->children[0]->children[0]->attr('text'));
            $t->same(' ', $span->children[1]->attr('text'));
            $t->same('https://example.test/review', $link->attr('url'));
            $t->same('link', $link->children[0]->attr('text'));
            $t->same('paragraph', $smallCapsParagraph->type);
            $t->same('Caps term done.', $smallCapsParagraph->attr('text'));
            $t->same(['text', 'small_caps', 'text'], array_map(static fn (AstNode $node): string => $node->type, $smallCapsParagraph->children));
            $t->same('term', $smallCaps->children[0]->attr('text'));
            $t->same(['data-origin' => 'glossary'], $smallCaps->attr('attributes'));
            $t->contains('Span ( "span-review" , [ "audit" ] , [ ( "data-kind" , "bracketed span" ) ] )', $native);
            $t->contains('SmallCaps [ Str "term" ]', $native);
        },

    'records selected upstream markdown bracketed-span fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(2, count($cases));
            $t->same('[*source* [link](https://example.test/review)]{#span-review .audit data-kind="bracketed span"}', $cases[0]);
            $t->same('Caps [term]{.smallcaps data-origin=glossary} done.', $cases[1]);
        },
];
