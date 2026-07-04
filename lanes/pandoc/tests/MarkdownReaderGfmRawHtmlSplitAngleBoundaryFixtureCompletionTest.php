<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary.md'
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps selected upstream gfm raw html split angle boundary fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
            $blocks = $document->children;
            $native = (new NativeWriter())->write($document);

            $t->same(['paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $blocks
            ));

            $first = $blocks[0] ?? new AstNode('missing');
            $second = $blocks[1] ?? new AstNode('missing');

            $t->same(['text'], $inlineTypes($first));
            $t->same('<', $first->attr('text'));
            $t->same('<', ($first->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['text'], $inlineTypes($second));
            $t->same('a>', $second->attr('text'));
            $t->same('a>', ($second->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->contains('[ Para [ Str "<" ]', $native);
            $t->contains(', Para [ Str "a>" ]', $native);
            $t->true(!str_contains($native, 'RawBlock'), 'split angle boundary must stay literal');
            $t->true(!str_contains($native, 'RawInline'), 'split angle boundary must stay literal');
        },

    'records selected upstream gfm raw html split angle boundary fixture literal paragraph count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(2, count($cases));
            $t->same('<', $cases[0]);
            $t->same('a>', $cases[1]);
        },
];
