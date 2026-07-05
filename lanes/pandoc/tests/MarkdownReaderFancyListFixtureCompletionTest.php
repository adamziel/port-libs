<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-fancy-list-markers.md'
);
$parenthesizedFixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-fancy-list-parenthesized-profile.md'
);

return [
    'maps selected upstream markdown fancy-list marker fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown+fancy_lists']))->read($fixture());
            $alpha = $document->children[0] ?? new AstNode('missing');
            $roman = $document->children[1] ?? new AstNode('missing');
            $paren = $document->children[2] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(['ordered_list', 'ordered_list', 'ordered_list'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same(1, $alpha->attr('start'));
            $t->same('upper_alpha', $alpha->attr('style'));
            $t->same('period', $alpha->attr('delimiter'));
            $t->same('alpha', ($alpha->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('beta', ($alpha->children[1] ?? new AstNode('missing'))->attr('text'));

            $t->same(4, $roman->attr('start'));
            $t->same('upper_roman', $roman->attr('style'));
            $t->same('period', $roman->attr('delimiter'));
            $t->same('roman four', ($roman->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('roman five', ($roman->children[1] ?? new AstNode('missing'))->attr('text'));

            $t->same(3, $paren->attr('start'));
            $t->same('decimal', $paren->attr('style'));
            $t->same('two_parens', $paren->attr('delimiter'));
            $t->same('three', ($paren->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('four', ($paren->children[1] ?? new AstNode('missing'))->attr('text'));

            $t->contains('OrderedList ( 1 , UpperAlpha , Period )', $native);
            $t->contains('OrderedList ( 4 , UpperRoman , Period )', $native);
            $t->contains('OrderedList ( 3 , Decimal , TwoParens )', $native);
        },

    'keeps selected upstream markdown fancy-list marker fixture behind extension gate' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown-fancy_lists']))->read($fixture());

            $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
        },

    'records selected upstream markdown fancy-list marker fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $rows = array_values(array_filter(
                preg_split('/\R/', trim($fixture())) ?: [],
                static fn (string $row): bool => $row !== ''
            ));

            $t->same(6, count($rows));
            $t->same('A.  alpha', $rows[0]);
            $t->same('IV.  roman four', $rows[2]);
            $t->same('(3) three', $rows[4]);
        },

    'maps selected upstream markdown fancy-list parenthesized marker fixture' =>
        static function (TestRunner $t) use ($parenthesizedFixture): void {
            $document = (new MarkdownReader(['format' => 'markdown+fancy_lists']))->read($parenthesizedFixture());
            $roman = $document->children[0] ?? new AstNode('missing');
            $alpha = $document->children[1] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(['ordered_list', 'ordered_list'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));

            $t->same(1, $roman->attr('start'));
            $t->same('lower_roman', $roman->attr('style'));
            $t->same('two_parens', $roman->attr('delimiter'));
            $t->same('roman one', ($roman->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('roman two', ($roman->children[1] ?? new AstNode('missing'))->attr('text'));

            $t->same(1, $alpha->attr('start'));
            $t->same('lower_alpha', $alpha->attr('style'));
            $t->same('two_parens', $alpha->attr('delimiter'));
            $t->same('alpha one', ($alpha->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('alpha two', ($alpha->children[1] ?? new AstNode('missing'))->attr('text'));

            $t->contains('OrderedList ( 1 , LowerRoman , TwoParens )', $native);
            $t->contains('OrderedList ( 1 , LowerAlpha , TwoParens )', $native);
        },

    'keeps selected upstream markdown fancy-list parenthesized marker fixture behind extension gate' =>
        static function (TestRunner $t) use ($parenthesizedFixture): void {
            $document = (new MarkdownReader(['format' => 'markdown-fancy_lists']))->read($parenthesizedFixture());

            $t->same(['paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same('(i) roman one (ii) roman two', ($document->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('(a) alpha one (b) alpha two', ($document->children[1] ?? new AstNode('missing'))->attr('text'));
        },
];
