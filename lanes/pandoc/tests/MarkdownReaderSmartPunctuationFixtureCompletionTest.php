<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$smartPunctuationFixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-smart-punctuation.md');

$smartInlineNoteQuotesFixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-smart-inline-note-quotes.md');

$smartInlineNoteDoubleQuotesFixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-smart-inline-note-double-quotes.md');

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$firstChild = static fn (AstNode $node, int $index): AstNode =>
    $node->children[$index] ?? new AstNode('missing');

return [
    'maps selected upstream markdown smart punctuation fixture' =>
        static function (TestRunner $t) use ($smartPunctuationFixture, $inlineTypes, $firstChild): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($smartPunctuationFixture());
            $paragraphs = $document->children;
            $quoteBeforeEllipsis = $paragraphs[0] ?? new AstNode('missing');
            $apostropheBeforeEmph = $paragraphs[1] ?? new AstNode('missing');
            $apostropheAfterMath = $paragraphs[2] ?? new AstNode('missing');
            $unclosedDoubleQuote = $paragraphs[3] ?? new AstNode('missing');
            $singleInlineNote = $paragraphs[4] ?? new AstNode('missing');
            $doubleInlineNote = $paragraphs[5] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);
            $native = (new NativeWriter())->write($document);

            $t->same(6, count($paragraphs));
            $t->same(['quoted'], $inlineTypes($quoteBeforeEllipsis));
            $t->same('single', $firstChild($quoteBeforeEllipsis, 0)->attr('kind'));
            $t->same("\u{2026}hi", $firstChild($firstChild($quoteBeforeEllipsis, 0), 0)->attr('text'));

            $t->same(['text', 'emph', 'text'], $inlineTypes($apostropheBeforeEmph));
            $t->same("D\u{2019}oh! A l\u{2019}", $firstChild($apostropheBeforeEmph, 0)->attr('text'));
            $t->same('aide', $firstChild($firstChild($apostropheBeforeEmph, 1), 0)->attr('text'));
            $t->same('!', $firstChild($apostropheBeforeEmph, 2)->attr('text'));

            $t->same(['text', 'math', 'text'], $inlineTypes($apostropheAfterMath));
            $t->same('x', $firstChild($apostropheAfterMath, 1)->attr('text'));
            $t->same("\u{2019}s and the systems\u{2019} condition.", $firstChild($apostropheAfterMath, 2)->attr('text'));

            $t->same(['strong'], $inlineTypes($unclosedDoubleQuote));
            $t->same("this should \u{201C}be bold", $firstChild($firstChild($unclosedDoubleQuote, 0), 0)->attr('text'));

            $t->same(['quoted'], $inlineTypes($singleInlineNote));
            $t->same('single', $firstChild($singleInlineNote, 0)->attr('kind'));
            $t->same(['text', 'note', 'text'], $inlineTypes($firstChild($singleInlineNote, 0)));
            $singleNoteParagraph = $firstChild($firstChild($firstChild($singleInlineNote, 0), 1), 0);
            $t->same('b', $firstChild($firstChild($singleNoteParagraph, 0), 0)->attr('text'));

            $t->same(['quoted'], $inlineTypes($doubleInlineNote));
            $t->same('double', $firstChild($doubleInlineNote, 0)->attr('kind'));
            $t->same(['text', 'note', 'text'], $inlineTypes($firstChild($doubleInlineNote, 0)));
            $doubleNoteParagraph = $firstChild($firstChild($firstChild($doubleInlineNote, 0), 1), 0);
            $t->same('b', $firstChild($firstChild($doubleNoteParagraph, 0), 0)->attr('text'));

            $t->contains('Quoted SingleQuote [ Str "\\8230hi" ]', $native);
            $t->contains('Math InlineMath "x"', $native);
            $t->contains('Quoted DoubleQuote [ Str "a" , Note [ Para [ Quoted DoubleQuote [ Str "b" ]', $native);
            $t->contains('<p>‘…hi’</p>', $blocks);
            $t->contains('D’oh! A l’<em>aide</em>!', $blocks);
            $t->contains('The value of the <span class="math inline">\\(x\\)</span>’s and the systems’ condition.', $blocks);
        },

    'maps isolated upstream markdown smart inline note quote fixture' =>
        static function (TestRunner $t) use ($smartInlineNoteQuotesFixture, $inlineTypes, $firstChild): void {
            $document = (new MarkdownReader(['format' => 'markdown+smart']))->read($smartInlineNoteQuotesFixture());
            $paragraph = $firstChild($document, 0);
            $quote = $firstChild($paragraph, 0);
            $noteParagraph = $firstChild($firstChild($quote, 1), 0);
            $innerQuote = $firstChild($noteParagraph, 0);
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same(['quoted'], $inlineTypes($paragraph));
            $t->same('single', $quote->attr('kind'));
            $t->same(['text', 'note', 'text'], $inlineTypes($quote));
            $t->same('a', $firstChild($quote, 0)->attr('text'));
            $t->same('single', $innerQuote->attr('kind'));
            $t->same('b', $firstChild($innerQuote, 0)->attr('text'));
            $t->same('.', $firstChild($noteParagraph, 1)->attr('text'));
            $t->same(' c.', $firstChild($quote, 2)->attr('text'));
            $t->contains('Quoted SingleQuote [ Str "a" , Note [ Para [ Quoted SingleQuote [ Str "b" ] , Str "." ]', $native);
            $t->contains(', Space , Str "c." ]', $native);
        },

    'maps isolated upstream markdown smart inline note double quote fixture' =>
        static function (TestRunner $t) use ($smartInlineNoteDoubleQuotesFixture, $inlineTypes, $firstChild): void {
            $document = (new MarkdownReader(['format' => 'markdown+smart']))->read($smartInlineNoteDoubleQuotesFixture());
            $paragraph = $firstChild($document, 0);
            $quote = $firstChild($paragraph, 0);
            $noteParagraph = $firstChild($firstChild($quote, 1), 0);
            $innerQuote = $firstChild($noteParagraph, 0);
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same(['quoted'], $inlineTypes($paragraph));
            $t->same('double', $quote->attr('kind'));
            $t->same(['text', 'note', 'text'], $inlineTypes($quote));
            $t->same('a', $firstChild($quote, 0)->attr('text'));
            $t->same('double', $innerQuote->attr('kind'));
            $t->same('b', $firstChild($innerQuote, 0)->attr('text'));
            $t->same('.', $firstChild($noteParagraph, 1)->attr('text'));
            $t->same(' c.', $firstChild($quote, 2)->attr('text'));
            $t->contains('Quoted DoubleQuote [ Str "a" , Note [ Para [ Quoted DoubleQuote [ Str "b" ] , Str "." ]', $native);
            $t->contains(', Space , Str "c." ]', $native);
        },

    'records upstream markdown smart punctuation fixture mapped-case count' =>
        static function (TestRunner $t) use ($smartPunctuationFixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($smartPunctuationFixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(6, count($cases));
            $t->same("'...hi'", $cases[0]);
            $t->same("D'oh! A l'*aide*!", $cases[1]);
            $t->same('The value of the $x$\'s and the systems\' condition.', $cases[2]);
            $t->same('**this should "be bold**', $cases[3]);
            $t->same("'a^['b'.] c.'", $cases[4]);
            $t->same('"a^["b".] c."', $cases[5]);
        },
];
