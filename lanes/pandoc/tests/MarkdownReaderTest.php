<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads markdown blocks into a small shared ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("# Title\n\nA paragraph over\nmultiple lines.\n\n- One\n- Two");
        $t->same('document', $document->type);
        $t->same('heading', $document->children[0]->type);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('bullet_list', $document->children[2]->type);
        $t->same('list_item', $document->children[2]->children[0]->type);
    },
    'maps pandoc markdown inline mark semantics into ast nodes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('A *migrated* **post** with [`source`](https://example.test/source) and `code`.');
        $paragraph = $document->children[0];

        $t->same('paragraph', $paragraph->type);
        $t->same('emph', $paragraph->children[1]->type);
        $t->same('migrated', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('strong', $paragraph->children[3]->type);
        $t->same('post', $paragraph->children[3]->children[0]->attr('text'));
        $t->same('link', $paragraph->children[5]->type);
        $t->same('https://example.test/source', $paragraph->children[5]->attr('url'));
        $t->same('code', $paragraph->children[7]->type);
    },
    'maps upstream testsuite underscore emphasis strong and nested strong emphasis' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'This is *emphasized*, and so _is this_.',
            'This is **strong**, and so __is this__.',
            'An *[emphasized link](/url)*.',
            '***This is strong and em.***',
            'So is ___this___ word.',
        ]));

        $emphasis = $document->children[0];
        $strong = $document->children[1];
        $link = $document->children[2]->children[1];
        $nestedFull = $document->children[3]->children[0];
        $nestedWord = $document->children[4]->children[1];

        $t->same('emph', $emphasis->children[1]->type);
        $t->same('emphasized', $emphasis->children[1]->children[0]->attr('text'));
        $t->same('emph', $emphasis->children[3]->type);
        $t->same('is this', $emphasis->children[3]->children[0]->attr('text'));
        $t->same('strong', $strong->children[1]->type);
        $t->same('strong', $strong->children[3]->type);
        $t->same('is this', $strong->children[3]->children[0]->attr('text'));
        $t->same('emph', $link->type);
        $t->same('link', $link->children[0]->type);
        $t->same('/url', $link->children[0]->attr('url'));
        $t->same('strong', $nestedFull->type);
        $t->same('emph', $nestedFull->children[0]->type);
        $t->same('This is strong and em.', $nestedFull->children[0]->children[0]->attr('text'));
        $t->same('strong', $nestedWord->type);
        $t->same('emph', $nestedWord->children[0]->type);
        $t->same('this', $nestedWord->children[0]->children[0]->attr('text'));
    },
    'maps upstream markdown reader emph containing strong delimiter runs' => static function (TestRunner $t): void {
        $spacedStrong = (new MarkdownReader())->read('*x **xx** x*')->children[0]->children[0];
        $twoStrongs = (new MarkdownReader())->read('***a**b **c**d*')->children[0]->children[0];
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read(implode("\n\n", [
            '*x **xx** x*',
            '***a**b **c**d*',
        ])));

        $t->same('emph', $spacedStrong->type);
        $t->same(['text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $spacedStrong->children));
        $t->same('x ', $spacedStrong->children[0]->attr('text'));
        $t->same('xx', $spacedStrong->children[1]->children[0]->attr('text'));
        $t->same(' x', $spacedStrong->children[2]->attr('text'));
        $t->same('emph', $twoStrongs->type);
        $t->same(['strong', 'text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $twoStrongs->children));
        $t->same('a', $twoStrongs->children[0]->children[0]->attr('text'));
        $t->same('b ', $twoStrongs->children[1]->attr('text'));
        $t->same('c', $twoStrongs->children[2]->children[0]->attr('text'));
        $t->same('d', $twoStrongs->children[3]->attr('text'));
        $t->contains('<p><em>x <strong>xx</strong> x</em></p>', $blocks);
        $t->contains('<p><em><strong>a</strong>b <strong>c</strong>d</em></p>', $blocks);
    },
    'maps upstream markdown reader intraword underscore and raw latex url guard' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $intraword = $reader->read('_foot_ball_')->children[0]->children[0];
        $rawLatexUrl = $reader->read("\\begin\n")->children[0];

        $t->same('emph', $intraword->type);
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $intraword->children));
        $t->same('foot_ball', $intraword->children[0]->attr('text'));
        $t->same('paragraph', $rawLatexUrl->type);
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $rawLatexUrl->children));
        $t->same('\\begin', $rawLatexUrl->children[0]->attr('text'));
    },
    'maps upstream markdown reader alternating emph strong softbreak' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("*xxx* ***xxx*** xxx\n*xxx* ***xxx*** xxx");
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'emph',
            'text',
            'strong',
            'text',
            'softbreak',
            'emph',
            'text',
            'strong',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('xxx', $paragraph->children[0]->children[0]->attr('text'));
        $t->same('xxx', $paragraph->children[2]->children[0]->children[0]->attr('text'));
        $t->same(' xxx', $paragraph->children[3]->attr('text'));
        $t->same('xxx', $paragraph->children[5]->children[0]->attr('text'));
        $t->same('xxx', $paragraph->children[7]->children[0]->children[0]->attr('text'));
        $t->same('xxx xxx xxx xxx xxx xxx', $paragraph->attr('text'));
        $t->contains("<p><em>xxx</em> <strong><em>xxx</em></strong> xxx\n<em>xxx</em> <strong><em>xxx</em></strong> xxx</p>", $blocks);
    },
    'maps upstream testsuite strikeout inline markup' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('~~This is *strikeout*.~~');
        $strikeout = $document->children[0]->children[0];

        $t->same('strikeout', $strikeout->type);
        $t->same('This is ', $strikeout->children[0]->attr('text'));
        $t->same('emph', $strikeout->children[1]->type);
        $t->same('strikeout', $strikeout->children[1]->children[0]->attr('text'));
        $t->same('.', $strikeout->children[2]->attr('text'));
    },
    'maps upstream testsuite superscript and subscript inline markup' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(
            'Superscripts:  a^bc^d a^*hello*^ a^hello\ there^.'
            . "\n\n" . 'Subscripts: H~2~O, H~23~O, H~many\ of\ them~O.'
            . "\n\n" . 'These should not be superscripts or subscripts,'
            . "\n" . 'because of the unescaped spaces:  a^b c^d, a~b c~d.'
        );
        $superscripts = $document->children[0];
        $subscripts = $document->children[1];
        $notScript = $document->children[2];

        $t->same('superscript', $superscripts->children[1]->type);
        $t->same('bc', $superscripts->children[1]->children[0]->attr('text'));
        $t->same('superscript', $superscripts->children[3]->type);
        $t->same('emph', $superscripts->children[3]->children[0]->type);
        $t->same('hello', $superscripts->children[3]->children[0]->children[0]->attr('text'));
        $t->same('superscript', $superscripts->children[5]->type);
        $t->same("hello\xC2\xA0there", $superscripts->children[5]->children[0]->attr('text'));
        $t->same('subscript', $subscripts->children[1]->type);
        $t->same('2', $subscripts->children[1]->children[0]->attr('text'));
        $t->same('subscript', $subscripts->children[3]->type);
        $t->same('23', $subscripts->children[3]->children[0]->attr('text'));
        $t->same('subscript', $subscripts->children[5]->type);
        $t->same("many\xC2\xA0of\xC2\xA0them", $subscripts->children[5]->children[0]->attr('text'));
        $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $notScript->children));
        $t->contains('a^b c^d, a~b c~d.', $notScript->children[2]->attr('text'));
    },
    'maps upstream markdown reader mmd short subscript superscript delimiters' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $subspace = $reader->read('O~2 is dangerous')->children[0];
        $subnewline = $reader->read("O~2\n")->children[0];
        $subeof = $reader->read('O~2')->children[0];
        $subpunctuation = $reader->read('O~2.')->children[0];
        $subemph = $reader->read('O~2*combustible!*')->children[0];
        $subNoNesting = $reader->read('y~*2*')->children[0];
        $supspace = $reader->read('x^2 = y')->children[0];
        $supnewline = $reader->read("x^2\n")->children[0];
        $supeof = $reader->read('x^2')->children[0];
        $suppunctuation = $reader->read('x^2.')->children[0];
        $supemph = $reader->read('x^2*combustible!*')->children[0];
        $supNoNesting = $reader->read('y^*2*')->children[0];
        $regularSubscript = $reader->read('H~2~')->children[0];
        $regularSuperscript = $reader->read('x^3^')->children[0];
        $defaultWhitespaceGuard = $reader->read('a^b c^d, a~b c~d.')->children[0];

        $t->same(['text', 'subscript', 'text'], array_map(static fn (AstNode $node): string => $node->type, $subspace->children));
        $t->same('O', $subspace->children[0]->attr('text'));
        $t->same('2', $subspace->children[1]->children[0]->attr('text'));
        $t->same(' is dangerous', $subspace->children[2]->attr('text'));
        $t->same('2', $subnewline->children[1]->children[0]->attr('text'));
        $t->same(2, count($subeof->children));
        $t->same('.', $subpunctuation->children[2]->attr('text'));
        $t->same('emph', $subemph->children[2]->type);
        $t->same('combustible!', $subemph->children[2]->children[0]->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $subNoNesting->children));
        $t->same('y~', $subNoNesting->children[0]->attr('text'));
        $t->same('2', $subNoNesting->children[1]->children[0]->attr('text'));
        $t->same(['text', 'superscript', 'text'], array_map(static fn (AstNode $node): string => $node->type, $supspace->children));
        $t->same('x', $supspace->children[0]->attr('text'));
        $t->same('2', $supspace->children[1]->children[0]->attr('text'));
        $t->same(' = y', $supspace->children[2]->attr('text'));
        $t->same('2', $supnewline->children[1]->children[0]->attr('text'));
        $t->same(2, count($supeof->children));
        $t->same('.', $suppunctuation->children[2]->attr('text'));
        $t->same('emph', $supemph->children[2]->type);
        $t->same('combustible!', $supemph->children[2]->children[0]->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $supNoNesting->children));
        $t->same('y^', $supNoNesting->children[0]->attr('text'));
        $t->same('2', $supNoNesting->children[1]->children[0]->attr('text'));
        $t->same('subscript', $regularSubscript->children[1]->type);
        $t->same('2', $regularSubscript->children[1]->children[0]->attr('text'));
        $t->same('superscript', $regularSuperscript->children[1]->type);
        $t->same('3', $regularSuperscript->children[1]->children[0]->attr('text'));
        $t->same('a^b c^d, a~b c~d.', $defaultWhitespaceGuard->children[0]->attr('text'));
    },
    'maps upstream testsuite smart quote nesting and apostrophes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '"Hello," said the spider.  "\'Shelob\' is my name."',
            '\'A\', \'B\', and \'C\' are letters.',
            '\'Oak,\' \'elm,\' and \'beech\' are names of trees.' . "\n" . 'So is \'pine.\'',
            '\'He said, "I want to go."\'  Were you alive in the' . "\n" . '70\'s?',
        ]));
        $greeting = $document->children[0];
        $letters = $document->children[1];
        $trees = $document->children[2];
        $speech = $document->children[3];

        $t->same('quoted', $greeting->children[0]->type);
        $t->same('double', $greeting->children[0]->attr('kind'));
        $t->same('Hello,', $greeting->children[0]->children[0]->attr('text'));
        $t->same('quoted', $greeting->children[2]->type);
        $t->same('double', $greeting->children[2]->attr('kind'));
        $t->same('single', $greeting->children[2]->children[0]->attr('kind'));
        $t->same('Shelob', $greeting->children[2]->children[0]->children[0]->attr('text'));
        $t->same('single', $letters->children[0]->attr('kind'));
        $t->same('A', $letters->children[0]->children[0]->attr('text'));
        $t->same('single', $letters->children[2]->attr('kind'));
        $t->same('C', $letters->children[4]->children[0]->attr('text'));
        $t->same('single', $trees->children[0]->attr('kind'));
        $t->same('pine.', $trees->children[8]->children[0]->attr('text'));
        $t->same('single', $speech->children[0]->attr('kind'));
        $t->same('double', $speech->children[0]->children[1]->attr('kind'));
        $t->contains("70\u{2019}s?", $speech->attr('text'));
    },
    'maps upstream testsuite quoted code and reference links' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(
            'Here is some quoted \'`code`\' and a "[quoted link][1]".'
            . "\n\n" . '[1]: http://example.com/?foo=1&bar=2'
        );
        $paragraph = $document->children[0];
        $quotedCode = $paragraph->children[1];
        $quotedLink = $paragraph->children[3];

        $t->same(1, count($document->children));
        $t->same('quoted', $quotedCode->type);
        $t->same('single', $quotedCode->attr('kind'));
        $t->same('code', $quotedCode->children[0]->type);
        $t->same('code', $quotedCode->children[0]->attr('text'));
        $t->same('quoted', $quotedLink->type);
        $t->same('double', $quotedLink->attr('kind'));
        $t->same('link', $quotedLink->children[0]->type);
        $t->same('http://example.com/?foo=1&bar=2', $quotedLink->children[0]->attr('url'));
        $t->same('quoted link', $quotedLink->children[0]->children[0]->attr('text'));
    },
    'maps upstream testsuite smart dashes and ellipses' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'Some dashes:  one---two --- three---four --- five.',
            'Dashes between numbers: 5--7, 255--66, 1987--1999. ',
            'Ellipses...and...and....',
        ]));

        $t->same("Some dashes:  one\u{2014}two \u{2014} three\u{2014}four \u{2014} five.", $document->children[0]->children[0]->attr('text'));
        $t->same("Dashes between numbers: 5\u{2013}7, 255\u{2013}66, 1987\u{2013}1999.", $document->children[1]->children[0]->attr('text'));
        $t->same("Ellipses\u{2026}and\u{2026}and\u{2026}.", $document->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown smart quote before ellipses and apostrophe edges' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $quoteBeforeEllipses = $reader->read("'...hi'")->children[0];
        $apostropheBeforeEmph = $reader->read("D'oh! A l'*aide*!")->children[0];
        $frenchApostrophes = $reader->read("À l'arrivée de la guerre, le thème de l'«impossibilité du socialisme»")->children[0];
        $blocks = (new WordPressBlockWriter())->write($reader->read(implode("\n\n", [
            "'...hi'",
            "D'oh! A l'*aide*!",
            "À l'arrivée de la guerre, le thème de l'«impossibilité du socialisme»",
        ])));

        $t->same('quoted', $quoteBeforeEllipses->children[0]->type);
        $t->same('single', $quoteBeforeEllipses->children[0]->attr('kind'));
        $t->same("\u{2026}hi", $quoteBeforeEllipses->children[0]->children[0]->attr('text'));
        $t->same(["text", "emph", "text"], array_map(static fn (AstNode $node): string => $node->type, $apostropheBeforeEmph->children));
        $t->same("D\u{2019}oh! A l\u{2019}", $apostropheBeforeEmph->children[0]->attr('text'));
        $t->same('aide', $apostropheBeforeEmph->children[1]->children[0]->attr('text'));
        $t->same('!', $apostropheBeforeEmph->children[2]->attr('text'));
        $t->same("À l\u{2019}arrivée de la guerre, le thème de l\u{2019}«impossibilité du socialisme»", $frenchApostrophes->children[0]->attr('text'));
        $t->contains("<p>\u{2018}\u{2026}hi\u{2019}</p>", $blocks);
        $t->contains("<p>D\u{2019}oh! A l\u{2019}<em>aide</em>!</p>", $blocks);
        $t->contains("<p>À l\u{2019}arrivée de la guerre, le thème de l\u{2019}«impossibilité du socialisme»</p>", $blocks);
    },
    'maps upstream markdown smart unclosed double quote inside strong' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('**this should "be bold**');
        $strong = $document->children[0]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('strong', $strong->type);
        $t->same("this should \u{201C}be bold", $strong->children[0]->attr('text'));
        $t->contains('<p><strong>this should “be bold</strong></p>', $blocks);
    },
    'maps upstream markdown smart quotes around inline notes' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $singleDocument = $reader->read("'a^['b'.] c.'");
        $doubleDocument = $reader->read('"a^["b".] c."');
        $singleQuote = $singleDocument->children[0]->children[0];
        $doubleQuote = $doubleDocument->children[0]->children[0];
        $singleNote = $singleQuote->children[1];
        $doubleNote = $doubleQuote->children[1];
        $blocks = (new WordPressBlockWriter())->write($reader->read(implode("\n\n", [
            "'a^['b'.] c.'",
            '"a^["b".] c."',
        ])));

        $t->same('quoted', $singleQuote->type);
        $t->same('single', $singleQuote->attr('kind'));
        $t->same(['text', 'note', 'text'], array_map(static fn (AstNode $node): string => $node->type, $singleQuote->children));
        $t->same('a', $singleQuote->children[0]->attr('text'));
        $t->same(' c.', $singleQuote->children[2]->attr('text'));
        $t->same(['quoted', 'text'], array_map(static fn (AstNode $node): string => $node->type, $singleNote->children[0]->children));
        $t->same('single', $singleNote->children[0]->children[0]->attr('kind'));
        $t->same('b', $singleNote->children[0]->children[0]->children[0]->attr('text'));
        $t->same('.', $singleNote->children[0]->children[1]->attr('text'));
        $t->same('quoted', $doubleQuote->type);
        $t->same('double', $doubleQuote->attr('kind'));
        $t->same(['text', 'note', 'text'], array_map(static fn (AstNode $node): string => $node->type, $doubleQuote->children));
        $t->same('double', $doubleNote->children[0]->children[0]->attr('kind'));
        $t->same('b', $doubleNote->children[0]->children[0]->children[0]->attr('text'));
        $t->contains('<p>‘a<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> c.’</p>', $blocks);
        $t->contains('<p>“a<sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup> c.”</p>', $blocks);
        $t->contains('<li id="fn-1"><p>‘b’.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-2"><p>“b”.</p> <a href="#fnref-2" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'maps upstream testsuite latex raw inline and math list items' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '- \cite[22-23]{smith.1899}',
            '- $2+2=4$',
            '- $x \in y$',
            '- $\alpha \wedge \omega$',
            '- $223$ ',
            '- $p$-Tree',
            '- Here\'s some display math:',
            '  $$\frac{d}{dx}f(x)=\lim_{h\to 0}\frac{f(x+h)-f(x)}{h}$$',
            '- Here\'s one that has a line break in it:  $\alpha + \omega \times x^2$.  ',
        ]));
        $list = $document->children[0];

        $t->same('bullet_list', $list->type);
        $t->same('raw_tex', $list->children[0]->children[0]->type);
        $t->same('\cite[22-23]{smith.1899}', $list->children[0]->children[0]->attr('tex'));
        $t->same('math', $list->children[1]->children[0]->type);
        $t->same('2+2=4', $list->children[1]->children[0]->attr('text'));
        $t->same(false, $list->children[1]->children[0]->attr('display'));
        $t->same('x \in y', $list->children[2]->children[0]->attr('text'));
        $t->same('\alpha \wedge \omega', $list->children[3]->children[0]->attr('text'));
        $t->same('223', $list->children[4]->children[0]->attr('text'));
        $t->same('math', $list->children[5]->children[0]->type);
        $t->same('p', $list->children[5]->children[0]->attr('text'));
        $t->same('-Tree', $list->children[5]->children[1]->attr('text'));
        $t->same('math', $list->children[6]->children[1]->type);
        $t->same(true, $list->children[6]->children[1]->attr('display'));
        $t->same('\frac{d}{dx}f(x)=\lim_{h\to 0}\frac{f(x+h)-f(x)}{h}', $list->children[6]->children[1]->attr('text'));
        $t->same('\alpha + \omega \times x^2', $list->children[7]->children[1]->attr('text'));
        $t->same('.', $list->children[7]->children[2]->attr('text'));
    },
    'keeps upstream testsuite non math dollar examples as text' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '- To get the famous equation, write `$e = mc^2$`.',
            '- $22,000 is a *lot* of money.  So is $34,000.',
            '  (It worked if "lot" is emphasized.)',
            '- Shoes ($20) and socks ($5).',
            '- Escaped `$`:  $73 *this should be emphasized* 23\$.',
        ]));
        $list = $document->children[0];
        $codeItem = $list->children[0];
        $moneyItem = $list->children[1];
        $escapedItem = $list->children[3];
        $moneyTypes = array_map(static fn ($node): string => $node->type, $moneyItem->children);

        $t->same('code', $codeItem->children[1]->type);
        $t->same('$e = mc^2$', $codeItem->children[1]->attr('text'));
        $t->same(false, in_array('math', $moneyTypes, true));
        $t->same('emph', $moneyItem->children[1]->type);
        $t->same('lot', $moneyItem->children[1]->children[0]->attr('text'));
        $t->same('Shoes ($20) and socks ($5).', $list->children[2]->children[0]->attr('text'));
        $t->same('code', $escapedItem->children[1]->type);
        $t->same('$', $escapedItem->children[1]->attr('text'));
        $t->same(' 23$.', $escapedItem->children[4]->attr('text'));
    },
    'maps upstream markdown apostrophe after math' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("The value of the \$x\$'s and the systems' condition.");
        $paragraph = $document->children[0];

        $t->same('math', $paragraph->children[1]->type);
        $t->same('x', $paragraph->children[1]->attr('text'));
        $t->same("\u{2019}s and the systems\u{2019} condition.", $paragraph->children[2]->attr('text'));
    },
    'maps upstream markdown reader more dollars inside tex math braces' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '## $ in math',
            '',
            '$\\$2 + \\$3$',
            '',
            '$x = \\text{the $n$th root of $y$}$',
            '',
            'This should not be math:',
            '',
            '$PATH 90 $PATH',
        ]));
        $escapedDollarMath = $document->children[1];
        $nestedDollarMath = $document->children[2];
        $notMath = $document->children[4];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('heading', $document->children[0]->type);
        $t->same('$ in math', $document->children[0]->attr('text'));
        $t->same('math', $escapedDollarMath->children[0]->type);
        $t->same('\\$2 + \\$3', $escapedDollarMath->children[0]->attr('text'));
        $t->same(['math'], array_map(static fn (AstNode $node): string => $node->type, $nestedDollarMath->children));
        $t->same('x = \\text{the $n$th root of $y$}', $nestedDollarMath->children[0]->attr('text'));
        $t->same('$PATH 90 $PATH', $notMath->children[0]->attr('text'));
        $t->contains('<span class="math inline">\(x = \text{the $n$th root of $y$}\)</span>', $blocks);
    },
    'maps upstream markdown reader more raw html before header and commented list' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '## Horizontal rules with spaces at end',
            '',
            '* * * * *  ',
            '',
            '-- - -- -- -  ',
            '',
            '## Raw HTML before header',
            '',
            '<a></a>',
            '',
            '### my header',
            '',
            '## Commented-out list item',
            '',
            '- one',
            '<!--',
            '- two',
            '-->',
            '- three',
        ]));
        $emptyAnchor = $document->children[4];
        $commentedList = $document->children[7];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('heading', $document->children[0]->type);
        $t->same('Horizontal rules with spaces at end', $document->children[0]->attr('text'));
        $t->same('horizontal_rule', $document->children[1]->type);
        $t->same('horizontal_rule', $document->children[2]->type);
        $t->same('heading', $document->children[3]->type);
        $t->same('Raw HTML before header', $document->children[3]->attr('text'));
        $t->same('paragraph', $emptyAnchor->type);
        $t->same(['raw_html_inline', 'raw_html_inline'], array_map(static fn (AstNode $node): string => $node->type, $emptyAnchor->children));
        $t->same('<a>', $emptyAnchor->children[0]->attr('html'));
        $t->same('</a>', $emptyAnchor->children[1]->attr('html'));
        $t->same('heading', $document->children[5]->type);
        $t->same('my-header', $document->children[5]->attr('id'));
        $t->same('heading', $document->children[6]->type);
        $t->same('bullet_list', $commentedList->type);
        $t->same(3, count($commentedList->children));
        $t->same("one <!\u{2013}", $commentedList->children[0]->children[0]->attr('text'));
        $t->same("two \u{2013}>", $commentedList->children[1]->children[0]->attr('text'));
        $t->same('three', $commentedList->children[2]->children[0]->attr('text'));
        $t->contains('<p><a></a></p>', $blocks);
        $t->contains("<ul><li>one &lt;!\u{2013}</li><li>two \u{2013}&gt;</li><li>three</li></ul>", $blocks);
    },
    'maps upstream testsuite latex tabular block as raw tex' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Here\'s a LaTeX table:',
            '',
            '\begin{tabular}{|l|l|}\hline',
            'Animal & Number \\\\ \hline',
            'Dog    & 2      \\\\',
            'Cat    & 1      \\\\ \hline',
            '\end{tabular}',
            '',
            '* * * * *',
        ]));
        $table = $document->children[1];

        $t->same('paragraph', $document->children[0]->type);
        $t->same('raw_tex', $table->type);
        $t->same('tabular', $table->attr('environment'));
        $t->contains('\begin{tabular}{|l|l|}\hline', $table->attr('tex'));
        $t->contains('Cat    & 1      \\\\ \hline', $table->attr('tex'));
        $t->same('horizontal_rule', $document->children[2]->type);
    },
    'maps upstream markdown reader more raw tex environments and macros' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '## Raw ConTeXt environments',
            '',
            '\placeformula \startformula',
            '   L_{1} = L_{2}',
            '   \stopformula',
            '',
            '\start[a2]',
            '\start[a2]',
            '\stop[a2]',
            '\stop[a2]',
            '',
            '## Raw LaTeX environments',
            '',
            '\begin{center}',
            '\begin{tikzpicture}[baseline={([yshift=+-.5ex]current bounding box.center)}, level distance=24pt]',
            '\Tree [.{S} [.NP John\index{i} ] [.VP [.V likes ] [.NP himself\index{i,*j} ]]]',
            '\end{tikzpicture}',
            '\end{center}',
            '',
            '## Macros',
            '',
            '\newcommand{\tuple}[1]{\langle #1 \rangle}',
            '',
            '$\tuple{x,y}$',
        ]));
        $contextFormula = $document->children[1];
        $contextParagraph = $document->children[2];
        $contextStartStop = $document->children[3];
        $latexCenter = $document->children[5];
        $macro = $document->children[7];
        $macroMath = $document->children[8]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('raw_tex', $contextFormula->type);
        $t->same('\placeformula \startformula', $contextFormula->attr('tex'));
        $t->same('paragraph', $contextParagraph->type);
        $t->same('L_{1} = L_{2} \stopformula', $contextParagraph->attr('text'));
        $t->same('softbreak', $contextParagraph->children[1]->type);
        $t->same('raw_tex', $contextParagraph->children[2]->type);
        $t->same('\stopformula', $contextParagraph->children[2]->attr('tex'));
        $t->same('raw_tex', $contextStartStop->type);
        $t->same('context:a2', $contextStartStop->attr('environment'));
        $t->same('\start[a2]' . "\n" . '\start[a2]' . "\n" . '\stop[a2]' . "\n" . '\stop[a2]', $contextStartStop->attr('tex'));
        $t->same('raw_tex', $latexCenter->type);
        $t->same('center', $latexCenter->attr('environment'));
        $t->contains('\begin{tikzpicture}[baseline={([yshift=+-.5ex]current bounding box.center)}, level distance=24pt]', $latexCenter->attr('tex'));
        $t->same('raw_tex', $macro->type);
        $t->same('newcommand', $macro->attr('command'));
        $t->same('\newcommand{\tuple}[1]{\langle #1 \rangle}', $macro->attr('tex'));
        $t->same('math', $macroMath->type);
        $t->same('\langle x,y \rangle', $macroMath->attr('text'));
        $t->contains('<pre class="wp-block-code"><code class="language-tex">\newcommand{\tuple}[1]{\langle #1 \rangle}</code></pre>', $blocks);
        $t->contains('<span class="math inline">\(\langle x,y \rangle\)</span>', $blocks);
    },
    'maps upstream testsuite special characters unicode entities and escapes' => static function (TestRunner $t): void {
        $markdown = implode("\n\n", [
            '# Special Characters',
            'Here is some unicode:',
            implode("\n", [
                '- I hat: Î',
                '- o umlaut: ö',
                '- section: § ',
                '- set membership: ∈',
                '- copyright: ©',
            ]),
            'AT&T has an ampersand in their name.',
            'AT&amp;T is another way to write it.',
            'This & that.',
            '4 < 5.',
            '6 > 5.',
            'Backslash: \\\\',
            'Backtick: \`',
            'Asterisk: \*',
            'Underscore: \_',
            'Left brace: \{',
            'Right brace: \}',
            'Left bracket: \[',
            'Right bracket: \]',
            'Left paren: \(',
            'Right paren: \)',
            'Greater-than: \>',
            'Hash: \#',
            'Period: \.',
            'Bang: \!',
            'Plus: \+',
            'Minus: \-',
            '- - - - - - - - - - - - -',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $unicodeList = $document->children[2];
        $escapeParagraphs = array_slice($document->children, 8, 16);
        $escapeTexts = array_map(
            static fn ($node): string => (string) $node->children[0]->attr('text'),
            $escapeParagraphs
        );

        $t->same('heading', $document->children[0]->type);
        $t->same('Special Characters', $document->children[0]->attr('text'));
        $t->same('bullet_list', $unicodeList->type);
        $t->same('I hat: Î', $unicodeList->children[0]->children[0]->attr('text'));
        $t->same('o umlaut: ö', $unicodeList->children[1]->children[0]->attr('text'));
        $t->same('section: §', $unicodeList->children[2]->children[0]->attr('text'));
        $t->same('set membership: ∈', $unicodeList->children[3]->children[0]->attr('text'));
        $t->same('copyright: ©', $unicodeList->children[4]->children[0]->attr('text'));
        $t->same('AT&T has an ampersand in their name.', $document->children[3]->children[0]->attr('text'));
        $t->same('AT&T is another way to write it.', $document->children[4]->children[0]->attr('text'));
        $t->same('This & that.', $document->children[5]->children[0]->attr('text'));
        $t->same('4 < 5.', $document->children[6]->children[0]->attr('text'));
        $t->same('6 > 5.', $document->children[7]->children[0]->attr('text'));
        $t->same([
            'Backslash: \\',
            'Backtick: `',
            'Asterisk: *',
            'Underscore: _',
            'Left brace: {',
            'Right brace: }',
            'Left bracket: [',
            'Right bracket: ]',
            'Left paren: (',
            'Right paren: )',
            'Greater-than: >',
            'Hash: #',
            'Period: .',
            'Bang: !',
            'Plus: +',
            'Minus: -',
        ], $escapeTexts);
        $t->same('horizontal_rule', $document->children[24]->type);
    },
    'maps upstream testsuite explicit links with titles and empty destinations' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'Just a [URL](/url/).',
            '[URL and title](/url/ "title").',
            '[URL and title](/url/  "title preceded by two spaces").',
            "[URL and title](/url/\t\"title preceded by a tab\").",
            '[URL and title](/url/ "title with "quotes" in it")',
            "[URL and title](/url/ 'title with single quotes')",
            '[with\_underscore](/url/with_underscore)',
            '[Email link](mailto:nobody@nowhere.net)',
            '[Empty]().',
        ]));

        $t->same('link', $document->children[0]->children[1]->type);
        $t->same('/url/', $document->children[0]->children[1]->attr('url'));
        $t->same('URL', $document->children[0]->children[1]->children[0]->attr('text'));
        $t->same('title', $document->children[1]->children[0]->attr('title'));
        $t->same('title preceded by two spaces', $document->children[2]->children[0]->attr('title'));
        $t->same('title preceded by a tab', $document->children[3]->children[0]->attr('title'));
        $t->same('title with "quotes" in it', $document->children[4]->children[0]->attr('title'));
        $t->same('title with single quotes', $document->children[5]->children[0]->attr('title'));
        $t->same('with_underscore', $document->children[6]->children[0]->children[0]->attr('text'));
        $t->same('/url/with_underscore', $document->children[6]->children[0]->attr('url'));
        $t->same('mailto:nobody@nowhere.net', $document->children[7]->children[0]->attr('url'));
        $t->same('', $document->children[8]->children[0]->attr('url'));
    },
    'maps upstream testsuite reference links shortcuts and indented definitions' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Foo [bar][a].',
            '',
            '[a]: /url/',
            '',
            'With [embedded [brackets]][b].',
            '',
            '[b] by itself should be a link.',
            '',
            'Indented [once][].',
            '',
            'Indented [twice][].',
            '',
            'Indented [thrice][].',
            '',
            'This should [not][] be a link.',
            '',
            ' [once]: /url',
            '  [twice]: /url',
            '',
            '   [thrice]: /url',
            '',
            '    [not]: /url',
            '',
            '[b]: /url/',
            '',
            'Foo [bar][].',
            '',
            'Foo [biz](/url/ "Title with "quote" inside").',
            '',
            '  [bar]: /url/ "Title with "quotes" inside"',
        ]));

        $t->same('link', $document->children[0]->children[1]->type);
        $t->same('/url/', $document->children[0]->children[1]->attr('url'));
        $t->same('embedded [brackets]', $document->children[1]->children[1]->children[0]->attr('text'));
        $t->same('/url/', $document->children[1]->children[1]->attr('url'));
        $t->same('link', $document->children[2]->children[0]->type);
        $t->same('/url/', $document->children[2]->children[0]->attr('url'));
        $t->same('/url', $document->children[3]->children[1]->attr('url'));
        $t->same('/url', $document->children[4]->children[1]->attr('url'));
        $t->same('/url', $document->children[5]->children[1]->attr('url'));
        $t->same('This should [not][] be a link.', $document->children[6]->children[0]->attr('text'));
        $t->same('code_block', $document->children[7]->type);
        $t->same('[not]: /url', $document->children[7]->attr('text'));
        $t->same('Title with "quotes" inside', $document->children[8]->children[1]->attr('title'));
        $t->same('Title with "quote" inside', $document->children[9]->children[1]->attr('title'));
    },
    'maps upstream markdown reader unbalanced brackets and backslash escaped links' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $unbalanced = $reader->read('[[[[[[[[[[[[hi')->children[0];
        $url = $reader->read('[hi](/there\\))')->children[0]->children[0];
        $title = $reader->read('[hi](/there "a\"a")')->children[0]->children[0];
        $referenceTitle = $reader->read(implode("\n", [
            '[hi]',
            '',
            '[hi]: /there (a\\)a)',
        ]))->children[0]->children[0];
        $referenceUrl = $reader->read(implode("\n", [
            '[hi]',
            '',
            '[hi]: /there\\.0',
        ]))->children[0]->children[0];
        $blocks = (new WordPressBlockWriter())->write($reader->read(implode("\n\n", [
            '[escaped closing paren](/there\\))',
            '[escaped title](/there "a\"a")',
        ])));

        $t->same('paragraph', $unbalanced->type);
        $t->same('[[[[[[[[[[[[hi', $unbalanced->children[0]->attr('text'));
        $t->same('link', $url->type);
        $t->same('/there)', $url->attr('url'));
        $t->same('hi', $url->children[0]->attr('text'));
        $t->same('/there', $title->attr('url'));
        $t->same('a"a', $title->attr('title'));
        $t->same('/there', $referenceTitle->attr('url'));
        $t->same('a)a', $referenceTitle->attr('title'));
        $t->same('/there.0', $referenceUrl->attr('url'));
        $t->contains('<p><a href="/there)">escaped closing paren</a></p>', $blocks);
        $t->contains('<p><a href="/there" title="a&quot;a">escaped title</a></p>', $blocks);
    },
    'maps upstream markdown reader more urls with spaces and split reference definitions' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '[foo] and [bar]',
            '',
            '[foo]: ',
            '  /url',
            '',
            '[bar]:',
            '/url',
            '"title"',
            '',
            '[foo](/bar and baz)',
            '[foo](/bar',
            ' and baz )',
            '[foo]( /bar  and  baz  )',
            '[foo](bar baz  "title" )',
            '',
            '[baz][] [bam][] [bork][]',
            '',
            '[baz]: /foo foo',
            '[bam]:  /foo fee   ',
            '[bork]:  /foo/zee zob   (title)',
        ]));

        $blankLineReferences = $document->children[0];
        $spaceInlineLinks = $document->children[1];
        $spaceReferenceLinks = $document->children[2];

        $t->same('/url', $blankLineReferences->children[0]->attr('url'));
        $t->same('/url', $blankLineReferences->children[2]->attr('url'));
        $t->same('title', $blankLineReferences->children[2]->attr('title'));
        $t->same('/bar%20and%20baz', $spaceInlineLinks->children[0]->attr('url'));
        $t->same('/bar%20and%20baz', $spaceInlineLinks->children[2]->attr('url'));
        $t->same('/bar%20and%20baz', $spaceInlineLinks->children[4]->attr('url'));
        $t->same('bar%20baz', $spaceInlineLinks->children[6]->attr('url'));
        $t->same('title', $spaceInlineLinks->children[6]->attr('title'));
        $t->same('/foo%20foo', $spaceReferenceLinks->children[0]->attr('url'));
        $t->same('/foo%20fee', $spaceReferenceLinks->children[2]->attr('url'));
        $t->same('/foo/zee%20zob', $spaceReferenceLinks->children[4]->attr('url'));
        $t->same('title', $spaceReferenceLinks->children[4]->attr('title'));

        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<a href="/bar%20and%20baz">foo</a>', $blocks);
        $t->contains('<a href="/foo/zee%20zob" title="title">bork</a>', $blocks);
    },
    'maps upstream markdown reader more title block metadata' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '% Title',
            '  spanning multiple lines',
            '% Author One',
            '  Author Two; Author Three;',
            '  Author Four',
            ' ',
            '# Additional markdown reader tests',
        ]));
        $meta = $document->attr('meta');
        $titleInlines = $meta['titleInlines'] ?? [];
        $authorInlines = $meta['authorInlines'] ?? [];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Title spanning multiple lines', $meta['title']);
        $t->same(['Author One', 'Author Two', 'Author Three', 'Author Four'], $meta['author']);
        $t->same(['Author One', 'Author Two', 'Author Three', 'Author Four'], $meta['authors']);
        $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $titleInlines));
        $t->same('Title', $titleInlines[0]->attr('text'));
        $t->same('spanning multiple lines', $titleInlines[2]->attr('text'));
        $t->same(4, count($authorInlines));
        $t->same('Author Three', $authorInlines[2][0]->attr('text'));
        $t->same(1, count($document->children));
        $t->same('heading', $document->children[0]->type);
        $t->same('additional-markdown-reader-tests', $document->children[0]->attr('id'));
        $t->contains('<h1 id="additional-markdown-reader-tests">Additional markdown reader tests</h1>', $blocks);
    },
    'maps upstream markdown reader more entity links and parenthesized urls' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '## Entities in links and titles',
            '',
            '[link](/&uuml;rl "&ouml;&ouml;!")',
            '',
            '<http://g&ouml;&ouml;gle.com>',
            '',
            '<me@ex&auml;mple.com>',
            '',
            '[foobar]',
            '',
            '[foobar]: /&uuml;rl "&ouml;&ouml;!"',
            '',
            '## Parentheses in URLs',
            '',
            '[link](/hi(there))',
            '',
            '[link](/hithere\))',
            '',
            '[linky]',
            '',
            '[linky]: hi_(there_(nested))',
        ]));
        $inlineEntity = $document->children[1]->children[0];
        $uriEntity = $document->children[2]->children[0];
        $emailEntity = $document->children[3]->children[0];
        $referenceEntity = $document->children[4]->children[0];
        $balanced = $document->children[6]->children[0];
        $escaped = $document->children[7]->children[0];
        $referenceParentheses = $document->children[8]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('entities-in-links-and-titles', $document->children[0]->attr('id'));
        $t->same('/ürl', $inlineEntity->attr('url'));
        $t->same('öö!', $inlineEntity->attr('title'));
        $t->same('http://göögle.com', $uriEntity->attr('url'));
        $t->same(['uri'], $uriEntity->attr('classes'));
        $t->same('http://göögle.com', $uriEntity->children[0]->attr('text'));
        $t->same('mailto:me@exämple.com', $emailEntity->attr('url'));
        $t->same(['email'], $emailEntity->attr('classes'));
        $t->same('me@exämple.com', $emailEntity->children[0]->attr('text'));
        $t->same('/ürl', $referenceEntity->attr('url'));
        $t->same('öö!', $referenceEntity->attr('title'));
        $t->same('parentheses-in-urls', $document->children[5]->attr('id'));
        $t->same('/hi(there)', $balanced->attr('url'));
        $t->same('/hithere)', $escaped->attr('url'));
        $t->same('hi_(there_(nested))', $referenceParentheses->attr('url'));
        $t->contains('<a href="/ürl" title="öö!">link</a>', $blocks);
        $t->contains('<a href="http://göögle.com">http://göögle.com</a>', $blocks);
        $t->contains('<a href="mailto:me@exämple.com">me@exämple.com</a>', $blocks);
        $t->contains('<a href="/hi(there)">link</a>', $blocks);
        $t->contains('<a href="hi_(there_(nested))">linky</a>', $blocks);
    },
    'maps upstream markdown reader entities group character numeric and link title references' => static function (TestRunner $t): void {
        $character = (new MarkdownReader())->read('&lang; &ouml;')->children[0];
        $numeric = (new MarkdownReader())->read('&#44;&#x44;&#X44;')->children[0];
        $linkTitle = (new MarkdownReader())->read('[link](/url "title &lang; &ouml; &#44;")')->children[0]->children[0];
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read(implode("\n\n", [
            '&lang; &ouml;',
            '&#44;&#x44;&#X44;',
            '[link](/url "title &lang; &ouml; &#44;")',
        ])));

        $t->same("\u{27E8} ö", $character->children[0]->attr('text'));
        $t->same(',DD', $numeric->children[0]->attr('text'));
        $t->same('link', $linkTitle->children[0]->attr('text'));
        $t->same('/url', $linkTitle->attr('url'));
        $t->same("title \u{27E8} ö ,", $linkTitle->attr('title'));
        $t->contains("<p>\u{27E8} ö</p>", $blocks);
        $t->contains('<p>,DD</p>', $blocks);
        $t->contains("<a href=\"/url\" title=\"title \u{27E8} ö ,\">link</a>", $blocks);
    },
    'maps upstream markdown reader more reference link edge cases' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '## Backslashes in link references',
            '',
            '[\*\a](b)',
            '',
            '## Reference link fallbacks',
            '',
            '[*not a link*] [*nope*]...',
            '',
            '## Reference link followed by a citation',
            '',
            'MapReduce is a paradigm popularized by [Google] [@mapreduce] as its',
            'most vocal proponent.',
            '',
            '[Google]: http://google.com',
            '',
            '## Empty reference links',
            '',
            '[foo2]:',
            '',
            'bar',
            '',
            '[foo2]',
        ]));
        $backslashLink = $document->children[1]->children[0];
        $fallback = $document->children[3];
        $citationAdjacent = $document->children[5];
        $emptyReferenceText = $document->children[7];
        $emptyReferenceLink = $document->children[8]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('backslashes-in-link-references', $document->children[0]->attr('id'));
        $t->same('link', $backslashLink->type);
        $t->same('b', $backslashLink->attr('url'));
        $t->same(['text', 'raw_tex'], array_map(static fn (AstNode $node): string => $node->type, $backslashLink->children));
        $t->same('*', $backslashLink->children[0]->attr('text'));
        $t->same('\a', $backslashLink->children[1]->attr('tex'));
        $t->same('reference-link-fallbacks', $document->children[2]->attr('id'));
        $t->same(['text', 'emph', 'text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $fallback->children));
        $t->same('[', $fallback->children[0]->attr('text'));
        $t->same('not a link', $fallback->children[1]->children[0]->attr('text'));
        $t->same('] [', $fallback->children[2]->attr('text'));
        $t->same('nope', $fallback->children[3]->children[0]->attr('text'));
        $t->same("]\u{2026}", $fallback->children[4]->attr('text'));
        $t->same(['text', 'link', 'text', 'citation', 'text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $citationAdjacent->children));
        $t->same('http://google.com', $citationAdjacent->children[1]->attr('url'));
        $t->same('Google', $citationAdjacent->children[1]->children[0]->attr('text'));
        $t->same('mapreduce', $citationAdjacent->children[3]->attr('id'));
        $t->same('[@mapreduce]', $citationAdjacent->children[3]->attr('text'));
        $t->same('bar', $emptyReferenceText->children[0]->attr('text'));
        $t->same('link', $emptyReferenceLink->type);
        $t->same('', $emptyReferenceLink->attr('url'));
        $t->same('foo2', $emptyReferenceLink->children[0]->attr('text'));
        $t->contains('<a href="b">*<span class="pandoc-raw-tex">\a</span></a>', $blocks);
        $t->contains('<p>[<em>not a link</em>] [<em>nope</em>]…</p>', $blocks);
        $t->contains('<p>MapReduce is a paradigm popularized by <a href="http://google.com">Google</a> [@mapreduce] as its', $blocks);
        $t->contains('<p><a href="">foo2</a></p>', $blocks);
    },
    'maps upstream markdown reader citations and following note link boundaries' => static function (TestRunner $t): void {
        $simple = (new MarkdownReader())->read('@item1')->children[0]->children[0];
        $digit = (new MarkdownReader())->read('@1657:huyghens')->children[0]->children[0];
        $footnote = (new MarkdownReader())->read("@cita[^note]\n\n[^note]: note")->children[0];
        $inlineLink = (new MarkdownReader())->read('@cita [link](http://www.com)')->children[0];
        $referenceLink = (new MarkdownReader())->read("@cita [link][link]\n\n[link]: http://www.com")->children[0];
        $shortcutReference = (new MarkdownReader())->read("@cita [link]\n\n[link]: http://www.com")->children[0];
        $implicitHeader = (new MarkdownReader())->read("# Header\n@cita [Header]");
        $regularCitation = (new MarkdownReader())->read('@cita [foo]')->children[0]->children[0];

        $t->same('citation', $simple->type);
        $t->same('item1', $simple->attr('id'));
        $t->same('@item1', $simple->attr('text'));
        $t->same('author_in_text', $simple->attr('mode'));
        $t->same('citation', $digit->type);
        $t->same('1657:huyghens', $digit->attr('id'));
        $t->same(['citation', 'note'], array_map(static fn (AstNode $node): string => $node->type, $footnote->children));
        $t->same('cita', $footnote->children[0]->attr('id'));
        $t->same('note', $footnote->children[1]->attr('label'));
        $t->same('note', $footnote->children[1]->children[0]->attr('text'));
        $t->same(['citation', 'text', 'link'], array_map(static fn (AstNode $node): string => $node->type, $inlineLink->children));
        $t->same('http://www.com', $inlineLink->children[2]->attr('url'));
        $t->same(['citation', 'text', 'link'], array_map(static fn (AstNode $node): string => $node->type, $referenceLink->children));
        $t->same('http://www.com', $referenceLink->children[2]->attr('url'));
        $t->same(['citation', 'text', 'link'], array_map(static fn (AstNode $node): string => $node->type, $shortcutReference->children));
        $t->same('http://www.com', $shortcutReference->children[2]->attr('url'));
        $t->same('heading', $implicitHeader->children[0]->type);
        $t->same(['citation', 'text', 'link'], array_map(static fn (AstNode $node): string => $node->type, $implicitHeader->children[1]->children));
        $t->same('#header', $implicitHeader->children[1]->children[2]->attr('url'));
        $t->same('citation', $regularCitation->type);
        $t->same('cita', $regularCitation->attr('id'));
        $t->same('foo', $regularCitation->attr('suffix'));
        $t->same('@cita [foo]', $regularCitation->attr('text'));

        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read("@cita[^note]\n\n[^note]: note\n\n@cita [link](http://www.com)\n\n@cita [foo]"));
        $t->contains('<p>@cita<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('<p>@cita <a href="http://www.com">link</a></p>', $blocks);
        $t->contains('<p>@cita [foo]</p>', $blocks);
    },
    'maps upstream markdown reader more wrapping and bracketed spans' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            "## Wrapping shouldn't introduce new list items",
            '',
            '- blah blah blah blah blah blah blah blah blah blah blah blah blah blah 2015.',
            '',
            '## Bracketed spans',
            '',
            '[*foo* bar baz [link](url)]{.class #id key=val}',
        ]));
        $list = $document->children[1];
        $spanHeading = $document->children[2];
        $spanParagraph = $document->children[3];
        $span = $spanParagraph->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));
        $t->same('wrapping-shouldnt-introduce-new-list-items', $document->children[0]->attr('id'));
        $t->same('bullet_list', $list->type);
        $t->same(1, count($list->children));
        $t->same(false, (bool) $list->attr('loose'));
        $t->same('blah blah blah blah blah blah blah blah blah blah blah blah blah blah 2015.', $list->children[0]->attr('text'));
        $t->same('text', $list->children[0]->children[0]->type);
        $t->same('bracketed-spans', $spanHeading->attr('id'));
        $t->same('span', $span->type);
        $t->same('id', $span->attr('id'));
        $t->same(['class'], $span->attr('classes'));
        $t->same(['key' => 'val'], $span->attr('attributes'));
        $t->same(['emph', 'text', 'link'], array_map(static fn (AstNode $node): string => $node->type, $span->children));
        $t->same('foo', $span->children[0]->children[0]->attr('text'));
        $t->same(' bar baz ', $span->children[1]->attr('text'));
        $t->same('url', $span->children[2]->attr('url'));
        $t->contains('<h2 id="wrapping-shouldnt-introduce-new-list-items">Wrapping shouldn’t introduce new list items</h2>', $blocks);
        $t->contains('<ul><li>blah blah blah blah blah blah blah blah blah blah blah blah blah blah 2015.</li></ul>', $blocks);
        $t->contains('<span id="id" class="class"><em>foo</em> bar baz <a href="url">link</a></span>', $blocks);
    },
    'maps upstream markdown reader more backslash newline and code spans' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("hi\\\nthere\n\n`hi\\`\n\n`hi\nthere`\n\n`` hi````there ``\n\n`hi\n\nthere`");
        $hardBreak = $document->children[0];
        $escapedCode = $document->children[1]->children[0];
        $multilineCode = $document->children[2]->children[0];
        $longTickCode = $document->children[3]->children[0];
        $unterminatedStart = $document->children[4];
        $unterminatedEnd = $document->children[5];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, count($document->children));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $hardBreak->children));
        $t->same('hi', $hardBreak->children[0]->attr('text'));
        $t->same('there', $hardBreak->children[2]->attr('text'));
        $t->same("hi\nthere", $hardBreak->attr('text'));
        $t->same('code', $escapedCode->type);
        $t->same('hi\\', $escapedCode->attr('text'));
        $t->same('code', $multilineCode->type);
        $t->same('hi there', $multilineCode->attr('text'));
        $t->same('hi````there', $longTickCode->attr('text'));
        $t->same('`hi', $unterminatedStart->children[0]->attr('text'));
        $t->same('there`', $unterminatedEnd->children[0]->attr('text'));
        $t->contains('<p>hi<br/>there</p>', $blocks);
        $t->contains('<p><code>hi\</code></p>', $blocks);
        $t->contains('<p><code>hi there</code></p>', $blocks);
        $t->contains('<p><code>hi````there</code></p>', $blocks);
    },
    'maps upstream markdown reader inline code attributes and spaced literals' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '`document.write("Hello");`{.javascript}',
            '`*` {.haskell .special x="7"}',
            'Reviewer token: `wp_enqueue_script`{#enqueue .php data-source=batch-42 title="Import source"}.',
        ]));
        $attributed = $document->children[0]->children[0];
        $spaced = $document->children[1];
        $reviewerCode = $document->children[2]->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('code', $attributed->type);
        $t->same('document.write("Hello");', $attributed->attr('text'));
        $t->same(['javascript'], $attributed->attr('classes'));
        $t->same(2, count($spaced->children));
        $t->same('code', $spaced->children[0]->type);
        $t->same('*', $spaced->children[0]->attr('text'));
        $t->same([], $spaced->children[0]->attr('classes', []));
        $t->same(' {.haskell .special x="7"}', $spaced->children[1]->attr('text'));
        $t->same('enqueue', $reviewerCode->attr('id'));
        $t->same(['php'], $reviewerCode->attr('classes'));
        $t->same(['data-source' => 'batch-42', 'title' => 'Import source'], $reviewerCode->attr('attributes'));
        $t->contains('<p><code class="javascript">document.write(&quot;Hello&quot;);</code></p>', $blocks);
        $t->contains('<p><code>*</code> {.haskell .special x=&quot;7&quot;}</p>', $blocks);
        $t->contains('<p>Reviewer token: <code id="enqueue" class="php" data-source="batch-42" title="Import source">wp_enqueue_script</code>.</p>', $blocks);
    },
    'maps upstream markdown reader autolink attributes and spaced literals' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '<http://foo.bar>{#i .j .z k=v}',
            '<http://foo.bar> {#i .j .z k=v}',
            'Reviewer source: <https://example.test/review-token>{#review-token .source-link data-source=batch-42 title="Review token"}.',
        ]));
        $attributed = $document->children[0]->children[0];
        $spaced = $document->children[1];
        $reviewerLink = $document->children[2]->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $attributed->type);
        $t->same('http://foo.bar', $attributed->attr('url'));
        $t->same('i', $attributed->attr('id'));
        $t->same(['j', 'z'], $attributed->attr('classes'));
        $t->same(['k' => 'v'], $attributed->attr('attributes'));
        $t->same('http://foo.bar', $attributed->children[0]->attr('text'));
        $t->same(2, count($spaced->children));
        $t->same('link', $spaced->children[0]->type);
        $t->same(['uri'], $spaced->children[0]->attr('classes'));
        $t->same(' {#i .j .z k=v}', $spaced->children[1]->attr('text'));
        $t->same('review-token', $reviewerLink->attr('id'));
        $t->same(['source-link'], $reviewerLink->attr('classes'));
        $t->same(['data-source' => 'batch-42', 'title' => 'Review token'], $reviewerLink->attr('attributes'));
        $t->contains('<p><a href="http://foo.bar" id="i" class="j z">http://foo.bar</a></p>', $blocks);
        $t->contains('<p><a href="http://foo.bar">http://foo.bar</a> {#i .j .z k=v}</p>', $blocks);
        $t->contains('<p>Reviewer source: <a href="https://example.test/review-token" id="review-token" class="source-link" data-source="batch-42" title="Review token">https://example.test/review-token</a>.</p>', $blocks);
    },
    'maps upstream markdown reader bare uri autolink extension cases' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'http://google.com is a search engine.',
            'Try this query: http://google.com?search=fish&time=hour.',
            '(http://google.com).',
            'HTTPS://GOOGLE.COM,',
            'http://en.wikipedia.org/wiki/Sprite_(computer_graphics)',
            'http://en.wikipedia.org/wiki/Sprite_[computer_graphics]',
        ]));
        $leading = $document->children[0]->children[0];
        $query = $document->children[1]->children[1];
        $parenthesized = $document->children[2]->children[1];
        $uppercase = $document->children[3]->children[0];
        $balanced = $document->children[4]->children[0];
        $bracketed = $document->children[5]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $leading->type);
        $t->same('http://google.com', $leading->attr('url'));
        $t->same(['uri'], $leading->attr('classes'));
        $t->same('http://google.com', $leading->children[0]->attr('text'));
        $t->same(' is a search engine.', $document->children[0]->children[1]->attr('text'));
        $t->same('http://google.com?search=fish&time=hour', $query->attr('url'));
        $t->same('.', $document->children[1]->children[2]->attr('text'));
        $t->same('http://google.com', $parenthesized->attr('url'));
        $t->same(').', $document->children[2]->children[2]->attr('text'));
        $t->same('HTTPS://GOOGLE.COM', $uppercase->attr('url'));
        $t->same(',', $document->children[3]->children[1]->attr('text'));
        $t->same('http://en.wikipedia.org/wiki/Sprite_(computer_graphics)', $balanced->attr('url'));
        $t->same('http://en.wikipedia.org/wiki/Sprite_%5Bcomputer_graphics%5D', $bracketed->attr('url'));
        $t->same('http://en.wikipedia.org/wiki/Sprite_[computer_graphics]', $bracketed->children[0]->attr('text'));
        $t->contains('<p><a href="http://google.com">http://google.com</a> is a search engine.</p>', $blocks);
        $t->contains('<p>Try this query: <a href="http://google.com?search=fish&amp;time=hour">http://google.com?search=fish&amp;time=hour</a>.</p>', $blocks);
        $t->contains('<p>(<a href="http://google.com">http://google.com</a>).</p>', $blocks);
        $t->contains('<p><a href="http://en.wikipedia.org/wiki/Sprite_%5Bcomputer_graphics%5D">http://en.wikipedia.org/wiki/Sprite_[computer_graphics]</a></p>', $blocks);
    },
    'maps upstream markdown reader bare uri schemes and punctuation families' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'doi:10.1000/182,',
            'git://github.com/foo/bar.git,',
            'file:///Users/joe/joe.txt, and',
            'mailto:someone@somedomain.com.',
            'Use http: this is not a link!',
            'http://www.rubyonrails.com/contact;new',
            'http://www.rubyonrails.com/contact;new?with=query&string=params',
            'http://foo.example.com/controller/action?parm=value&p2=v2#anchor123',
            'http://foo.example.com:3000/controller/action+pack',
            'http://en.wikipedia.org/wiki/Sprite_{computer_graphics}',
            'https://example.org/?anchor=lala-',
        ]));
        $doi = $document->children[0]->children[0];
        $git = $document->children[1]->children[0];
        $file = $document->children[2]->children[0];
        $mailto = $document->children[3]->children[0];
        $semicolon = $document->children[5]->children[0];
        $semicolonQuery = $document->children[6]->children[0];
        $fragment = $document->children[7]->children[0];
        $plus = $document->children[8]->children[0];
        $curly = $document->children[9]->children[0];
        $trailingHyphen = $document->children[10]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $doi->type);
        $t->same('doi:10.1000/182', $doi->attr('url'));
        $t->same(['uri'], $doi->attr('classes'));
        $t->same(',', $document->children[0]->children[1]->attr('text'));
        $t->same('git://github.com/foo/bar.git', $git->attr('url'));
        $t->same(',', $document->children[1]->children[1]->attr('text'));
        $t->same('file:///Users/joe/joe.txt', $file->attr('url'));
        $t->same(', and', $document->children[2]->children[1]->attr('text'));
        $t->same('mailto:someone@somedomain.com', $mailto->attr('url'));
        $t->same('.', $document->children[3]->children[1]->attr('text'));
        $t->same(1, count($document->children[4]->children));
        $t->same('Use http: this is not a link!', $document->children[4]->children[0]->attr('text'));
        $t->same('http://www.rubyonrails.com/contact;new', $semicolon->attr('url'));
        $t->same('http://www.rubyonrails.com/contact;new?with=query&string=params', $semicolonQuery->attr('url'));
        $t->same('http://foo.example.com/controller/action?parm=value&p2=v2#anchor123', $fragment->attr('url'));
        $t->same('http://foo.example.com:3000/controller/action+pack', $plus->attr('url'));
        $t->same('http://en.wikipedia.org/wiki/Sprite_%7Bcomputer_graphics%7D', $curly->attr('url'));
        $t->same('http://en.wikipedia.org/wiki/Sprite_{computer_graphics}', $curly->children[0]->attr('text'));
        $t->same('https://example.org/?anchor=lala-', $trailingHyphen->attr('url'));
        $t->contains('<p><a href="doi:10.1000/182">doi:10.1000/182</a>,</p>', $blocks);
        $t->contains('<p><a href="git://github.com/foo/bar.git">git://github.com/foo/bar.git</a>,</p>', $blocks);
        $t->contains('<p><a href="file:///Users/joe/joe.txt">file:///Users/joe/joe.txt</a>, and</p>', $blocks);
        $t->contains('<p><a href="mailto:someone@somedomain.com">mailto:someone@somedomain.com</a>.</p>', $blocks);
        $t->contains('<p>Use http: this is not a link!</p>', $blocks);
        $t->contains('<p><a href="http://www.rubyonrails.com/contact;new?with=query&amp;string=params">http://www.rubyonrails.com/contact;new?with=query&amp;string=params</a></p>', $blocks);
        $t->contains('<p><a href="http://en.wikipedia.org/wiki/Sprite_%7Bcomputer_graphics%7D">http://en.wikipedia.org/wiki/Sprite_{computer_graphics}</a></p>', $blocks);
    },
    'maps remaining upstream markdown reader bare uri url shapes and raw html anchor boundary' => static function (TestRunner $t): void {
        $urls = [
            'http://el.wikipedia.org/wiki/Τεχνολογία',
            'http://example.com/Notification_Center-GitHub-20101108-140050.jpg',
            'https://github.com/github/hubot/blob/master/scripts/cream.js#L20-20',
            'http://www.rubyonrails.com',
            'http://www.rubyonrails.com:80',
            'http://www.rubyonrails.com/~minam',
            'https://www.rubyonrails.com/~minam',
            'http://www.rubyonrails.com/~minam/url%20with%20spaces',
            'http://www.rubyonrails.com/foo.cgi?something=here',
            'http://www.rubyonrails.com/foo.cgi?something=here&and=here',
            'http://www.rubyonrails.com/contact;new%20with%20spaces',
            'http://www.rubyonrails.com/~minam/contact;new?with=query&string=params',
            'http://en.wikipedia.org/wiki/Wikipedia:Today%27s_featured_picture_%28animation%29/January_20%2C_2007',
            'http://www.mail-archive.com/rails@lists.rubyonrails.org/',
            'http://www.amazon.com/Testing-Equal-Sign-In-Path/ref=pd_bbs_sr_1?ie=UTF8&s=books&qid=1198861734&sr=8-1',
            'http://en.wikipedia.org/wiki/Texas_hold%27em',
            'https://www.google.com/doku.php?id=gps:resource:scs:start',
            'http://www.rubyonrails.com',
            'http://manuals.ruby-on-rails.com/read/chapter.need_a-period/103#page281',
            'http://foo.example.com:3000/controller/action',
            'http://business.timesonline.co.uk/article/0,,9065-2473189,00.html',
            'http://www.mail-archive.com/ruby-talk@ruby-lang.org/',
            'https://example.org/?anchor=-lala',
        ];
        $document = (new MarkdownReader())->read(
            '<a href="http://foo.bar.baz">http://foo.bar.baz</a>'
            . "\n\n" . implode(",\n\n", $urls)
        );
        $rawAnchor = $document->children[0]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('raw_html_inline', $rawAnchor->type);
        $t->same('<a href="http://foo.bar.baz">http://foo.bar.baz</a>', $rawAnchor->attr('html'));
        $t->same(1, count($document->children[0]->children));

        foreach ($urls as $index => $url) {
            $paragraph = $document->children[$index + 1];
            $link = $paragraph->children[0];
            $t->same('link', $link->type);
            $t->same($url, $link->attr('url'));
            $t->same($url, $link->children[0]->attr('text'));
        }

        $t->same(',', $document->children[1]->children[1]->attr('text'));
        $t->contains('<p><a href="http://foo.bar.baz">http://foo.bar.baz</a></p>', $blocks);
        $t->contains('<a href="http://el.wikipedia.org/wiki/Τεχνολογία">http://el.wikipedia.org/wiki/Τεχνολογία</a>', $blocks);
        $t->contains('<a href="http://www.rubyonrails.com/~minam/url%20with%20spaces">http://www.rubyonrails.com/~minam/url%20with%20spaces</a>', $blocks);
        $t->contains('<a href="http://www.mail-archive.com/rails@lists.rubyonrails.org/">http://www.mail-archive.com/rails@lists.rubyonrails.org/</a>', $blocks);
        $t->contains('<a href="https://example.org/?anchor=-lala">https://example.org/?anchor=-lala</a>', $blocks);
    },
    'maps upstream markdown reader no links inside link labels' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '[<https://example.org>](url)',
            '[[a](url2)](url)',
            '[https://example.org(](url)',
            '[*emphasized* <https://example.org> label](url)',
        ]));
        $autolinkLabel = $document->children[0]->children[0];
        $inlineLinkLabel = $document->children[1]->children[0];
        $bareUriLabel = $document->children[2]->children[0];
        $mixedLabel = $document->children[3]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $autolinkLabel->type);
        $t->same('url', $autolinkLabel->attr('url'));
        $t->same(1, count($autolinkLabel->children));
        $t->same('<https://example.org>', $autolinkLabel->children[0]->attr('text'));
        $t->same('link', $inlineLinkLabel->type);
        $t->same('[a](url2)', $inlineLinkLabel->children[0]->attr('text'));
        $t->same('link', $bareUriLabel->type);
        $t->same('https://example.org(', $bareUriLabel->children[0]->attr('text'));
        $t->same('emph', $mixedLabel->children[0]->type);
        $t->same('emphasized', $mixedLabel->children[0]->children[0]->attr('text'));
        $t->same(' <https://example.org> label', $mixedLabel->children[1]->attr('text'));
        $t->contains('<p><a href="url">&lt;https://example.org&gt;</a></p>', $blocks);
        $t->contains('<p><a href="url">[a](url2)</a></p>', $blocks);
        $t->contains('<p><a href="url">https://example.org(</a></p>', $blocks);
        $t->contains('<p><a href="url"><em>emphasized</em> &lt;https://example.org&gt; label</a></p>', $blocks);
    },
    'maps upstream markdown reader more multilingual urls and numbered examples' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '<http://测.com?测=测>',
            '',
            '[foo](/bar/测?x=测 "title")',
            '',
            '<测@foo.测.baz>',
            '',
            '(@) First example.',
            '(@foo) Second example.',
            '',
            'Explanation of examples (@foo) and (@bar).',
            '',
            '(@bar) Third example.',
        ]));
        $uri = $document->children[0]->children[0];
        $inline = $document->children[1]->children[0];
        $email = $document->children[2]->children[0];
        $firstExamples = $document->children[3];
        $explanation = $document->children[4];
        $thirdExample = $document->children[5];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $uri->type);
        $t->same('http://测.com?测=测', $uri->attr('url'));
        $t->same(['uri'], $uri->attr('classes'));
        $t->same('http://测.com?测=测', $uri->children[0]->attr('text'));
        $t->same('/bar/测?x=测', $inline->attr('url'));
        $t->same('title', $inline->attr('title'));
        $t->same('link', $email->type);
        $t->same('mailto:测@foo.测.baz', $email->attr('url'));
        $t->same(['email'], $email->attr('classes'));
        $t->same('ordered_list', $firstExamples->type);
        $t->same(1, $firstExamples->attr('start'));
        $t->same('example', $firstExamples->attr('style'));
        $t->same('two_parens', $firstExamples->attr('delimiter'));
        $t->same(1, $firstExamples->children[0]->attr('number'));
        $t->same(2, $firstExamples->children[1]->attr('number'));
        $t->same('Explanation of examples (2) and (3).', $explanation->children[0]->attr('text'));
        $t->same('ordered_list', $thirdExample->type);
        $t->same(3, $thirdExample->attr('start'));
        $t->same(3, $thirdExample->children[0]->attr('number'));
        $t->contains('<a href="http://测.com?测=测">http://测.com?测=测</a>', $blocks);
        $t->contains('<a href="mailto:测@foo.测.baz">测@foo.测.baz</a>', $blocks);
        $t->contains('<ol><li>First example.</li><li>Second example.</li></ol>', $blocks);
        $t->contains('<p>Explanation of examples (2) and (3).</p>', $blocks);
        $t->contains('<ol start="3"><li>Third example.</li></ol>', $blocks);
    },
    'maps upstream markdown reader more implicit header references' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '### my header',
            '### My header',
            '### My other header',
            'A link to [My header].',
            'Another link to [it][My header].',
            'Should be [case insensitive][my header].',
            'Link to [Explicit header attributes].',
            '[my other header]: /foo',
            'But this is not a link to [My other header], since the reference is defined.',
            '## Explicit header attributes {#foobar .baz key="val"}',
        ]));

        $firstDuplicate = $document->children[0];
        $secondDuplicate = $document->children[1];
        $otherHeader = $document->children[2];
        $shortcut = $document->children[3]->children[1];
        $collapsed = $document->children[4]->children[1];
        $caseInsensitive = $document->children[5]->children[1];
        $forwardExplicitAttribute = $document->children[6]->children[1];
        $explicitOverride = $document->children[7]->children[1];
        $attributeHeading = $document->children[8];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('my-header', $firstDuplicate->attr('id'));
        $t->same('my-header-1', $secondDuplicate->attr('id'));
        $t->same('my-other-header', $otherHeader->attr('id'));
        $t->same('#my-header', $shortcut->attr('url'));
        $t->same('My header', $shortcut->children[0]->attr('text'));
        $t->same('#my-header', $collapsed->attr('url'));
        $t->same('it', $collapsed->children[0]->attr('text'));
        $t->same('#my-header', $caseInsensitive->attr('url'));
        $t->same('#foobar', $forwardExplicitAttribute->attr('url'));
        $t->same('/foo', $explicitOverride->attr('url'));
        $t->same('foobar', $attributeHeading->attr('id'));
        $t->same(['baz'], $attributeHeading->attr('classes'));
        $t->same(['key' => 'val'], $attributeHeading->attr('attributes'));
        $t->contains('<h2 id="foobar" class="baz">Explicit header attributes</h2>', $blocks);
        $t->contains('<a href="#my-header">My header</a>', $blocks);
        $t->contains('<a href="/foo">My other header</a>', $blocks);
    },
    'maps upstream markdown reader header edge cases' => static function (TestRunner $t): void {
        $blankBefore = (new MarkdownReader())->read("\n# Header\n");
        $bracketed = (new MarkdownReader())->read("# [hi]\n");
        $atx = (new MarkdownReader())->read("# Foo bar\n\n");
        $closingAtx = (new MarkdownReader())->read("# Foo bar with # #");
        $setext = (new MarkdownReader())->read("Foo bar\n=\n\n Foo bar 2 \n=");

        $t->same('heading', $blankBefore->children[0]->type);
        $t->same(1, $blankBefore->children[0]->attr('level'));
        $t->same('Header', $blankBefore->children[0]->attr('text'));
        $t->same('header', $blankBefore->children[0]->attr('id'));
        $t->same('[hi]', $bracketed->children[0]->attr('text'));
        $t->same('hi', $bracketed->children[0]->attr('id'));
        $t->same('Foo bar', $atx->children[0]->attr('text'));
        $t->same('foo-bar', $atx->children[0]->attr('id'));
        $t->same('Foo bar with #', $closingAtx->children[0]->attr('text'));
        $t->same('foo-bar-with', $closingAtx->children[0]->attr('id'));
        $t->same(['heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $setext->children));
        $t->same([1, 1], array_map(static fn (AstNode $node): int => (int) $node->attr('level'), $setext->children));
        $t->same('Foo bar', $setext->children[0]->attr('text'));
        $t->same('foo-bar', $setext->children[0]->attr('id'));
        $t->same('Foo bar 2', $setext->children[1]->attr('text'));
        $t->same('foo-bar-2', $setext->children[1]->attr('id'));
    },
    'maps upstream markdown reader implicit references for closing atx and setext headers' => static function (TestRunner $t): void {
        $closingAtx = (new MarkdownReader())->read("# Foo bar #\n[foo bar]\n\n[foo bar ]\n\n[ foo bar]");
        $setext = (new MarkdownReader())->read(" Header \n=\n\n[header]\n\n[header ]\n\n[ header]");
        $closingBlocks = (new WordPressBlockWriter())->write($closingAtx);
        $setextBlocks = (new WordPressBlockWriter())->write($setext);

        $t->same('Foo bar', $closingAtx->children[0]->attr('text'));
        $t->same('foo-bar', $closingAtx->children[0]->attr('id'));
        $t->same('#foo-bar', $closingAtx->children[1]->children[0]->attr('url'));
        $t->same('#foo-bar', $closingAtx->children[2]->children[0]->attr('url'));
        $t->same('#foo-bar', $closingAtx->children[3]->children[0]->attr('url'));
        $t->same('Header', $setext->children[0]->attr('text'));
        $t->same('header', $setext->children[0]->attr('id'));
        $t->same('#header', $setext->children[1]->children[0]->attr('url'));
        $t->same('#header', $setext->children[2]->children[0]->attr('url'));
        $t->same('#header', $setext->children[3]->children[0]->attr('url'));
        $t->contains('<h1 id="foo-bar">Foo bar</h1>', $closingBlocks);
        $t->contains('<a href="#foo-bar">foo bar</a>', $closingBlocks);
        $t->contains('<h1 id="header">Header</h1>', $setextBlocks);
        $t->contains('<a href="#header">header</a>', $setextBlocks);
    },
    'writes wordpress normalized markdown header imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $closingHeading = null;
        $setextHeading = null;
        foreach ($document->children as $node) {
            if ($node->type !== 'heading') {
                continue;
            }
            if ($node->attr('text') === 'Closing Hash Heading') {
                $closingHeading = $node;
                continue;
            }
            if ($node->attr('text') === 'Setext Import Heading') {
                $setextHeading = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($closingHeading instanceof AstNode, 'ATX closing-hash fixture heading should normalize before WordPress output');
        $t->true($setextHeading instanceof AstNode, 'Setext fixture heading should become a WordPress heading');
        $t->same(2, $closingHeading->attr('level'));
        $t->same('closing-hash-heading', $closingHeading->attr('id'));
        $t->same(2, $setextHeading->attr('level'));
        $t->same('setext-import-heading', $setextHeading->attr('id'));
        $t->contains('<h2 id="closing-hash-heading">Closing Hash Heading</h2>', $blocks);
        $t->contains('<h2 id="setext-import-heading">Setext Import Heading</h2>', $blocks);
    },
    'writes wordpress raw empty anchor before imported headings' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $rawAnchor = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'paragraph'
                && count($node->children) === 2
                && $node->children[0]->type === 'raw_html_inline'
                && $node->children[1]->type === 'raw_html_inline'
                && $node->children[0]->attr('html') === '<a>'
                && $node->children[1]->attr('html') === '</a>'
            ) {
                $rawAnchor = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($rawAnchor instanceof AstNode, 'Empty source anchors before headings should stay as raw HTML inline boundaries');
        $t->contains('<p><a></a></p>', $blocks);
        $t->contains('<h3 id="raw-anchor-follow-up">Raw Anchor Follow-up</h3>', $blocks);
    },
    'maps upstream markdown reader more line blocks' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '| But can a bee be said to be',
            '|     or not to be an entire bee,',
            '|         when half the bee is not a bee,',
            '|             due to some ancient injury?',
            '|',
            '| Continuation',
            ' line',
            '|   and',
            '       another',
        ]));
        $lineBlock = $document->children[0];
        $nbsp = "\xC2\xA0";
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('line_block', $lineBlock->type);
        $t->same(7, count($lineBlock->children));
        $t->same('But can a bee be said to be', $lineBlock->children[0]->attr('text'));
        $t->same(str_repeat($nbsp, 4) . 'or not to be an entire bee,', $lineBlock->children[1]->attr('text'));
        $t->same(str_repeat($nbsp, 8) . 'when half the bee is not a bee,', $lineBlock->children[2]->attr('text'));
        $t->same(str_repeat($nbsp, 12) . 'due to some ancient injury?', $lineBlock->children[3]->attr('text'));
        $t->same('', $lineBlock->children[4]->attr('text'));
        $t->same('Continuation line', $lineBlock->children[5]->attr('text'));
        $t->same(str_repeat($nbsp, 2) . 'and another', $lineBlock->children[6]->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $lineBlock->children[5]->children));
        $t->contains('<p>But can a bee be said to be<br/>' . str_repeat($nbsp, 4) . 'or not to be an entire bee,', $blocks);
        $t->contains('<br/><br/>Continuation line<br/>' . str_repeat($nbsp, 2) . 'and another</p>', $blocks);
    },
    'maps upstream markdown reader more rectangular grid tables' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '## Grid Tables',
            '',
            '+------------------+-----------+------------+',
            '| col 1            | col 2     | col 3      |',
            '+==================+===========+============+',
            '| r1 a             | b         | c          |',
            '| r1 bis           | b 2       | c 2        |',
            '+------------------+-----------+------------+',
            '| r2 d             | e         | f          |',
            '+------------------+-----------+------------+',
            '',
            'Headless',
            '',
            '+------------------+-----------+------------+',
            '| r1 a             | b         | c          |',
            '| r1 bis           | b 2       | c 2        |',
            '+------------------+-----------+------------+',
            '| r2 d             | e         | f          |',
            '+------------------+-----------+------------+',
            '',
            'With alignments',
            '',
            '+------------------+-----------+------------+',
            '| col 1            | col 2     | col 3      |',
            '+=================:+:==========+:==========:+',
            '| r1 a             | b         | c          |',
            '| r1 bis           | b 2       | c 2        |',
            '+------------------+-----------+------------+',
            '| r2 d             | e         | f          |',
            '+------------------+-----------+------------+',
            '',
            'Headless with alignments',
            '',
            '+-----------------:+:----------+:----------:+',
            '| r1 a             | b         | c          |',
            '| r1 bis           | b 2       | c 2        |',
            '+------------------+-----------+------------+',
            '| r2 d             | e         | f          |',
            '+------------------+-----------+------------+',
            '',
            'Spaces at ends of lines',
            '',
            '+------------------+-----------+------------+  ',
            '| r1 a             | b         | c          |',
            '| r1 bis           | b 2       | c 2        | ',
            '+------------------+-----------+------------+',
            '| r2 d             | e         | f          |',
            '+------------------+-----------+------------+',
            '',
            'East Asian characters have double width',
            '',
            '+--+----+',
            '|魚|fish|',
            '+--+----+',
            '',
            'Zero-width space in German',
            '',
            '+-------+-------+',
            '|German |English|',
            '+-------+-------+',
            '|Auf‌lage|edition|',
            '+-------+-------+',
            '',
            'Zero-width non-joiner in Persian',
            '',
            '+-------+---------+',
            '|می‌خواهم|I want to|',
            '+-------+---------+',
            '',
            'Empty cells',
            '',
            '+---+---+',
            '|   |   |',
            '+---+---+',
        ]));
        $headed = $document->children[1];
        $headless = $document->children[3];
        $aligned = $document->children[5];
        $alignedHeadless = $document->children[7];
        $trailingSpaces = $document->children[9];
        $eastAsian = $document->children[11];
        $zeroWidth = $document->children[13];
        $persian = $document->children[15];
        $emptyCells = $document->children[17];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $headed->type);
        $t->same([19 / 72, 12 / 72, 13 / 72], $headed->attr('widths'));
        $t->same(['default', 'default', 'default'], $headed->attr('alignments'));
        $t->same('col 1', $headed->children[0]->children[0]->children[0]->attr('text'));
        $t->same("r1 a\nr1 bis", $headed->children[1]->children[0]->children[0]->attr('text'));
        $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $headed->children[1]->children[0]->children[0]->children));
        $t->same('f', $headed->children[1]->children[1]->children[2]->attr('text'));
        $t->same([], $headless->children[0]->children);
        $t->same("c\nc 2", $headless->children[1]->children[0]->children[2]->attr('text'));
        $t->same(['right', 'left', 'center'], $aligned->attr('alignments'));
        $t->same('col 2', $aligned->children[0]->children[0]->children[1]->attr('text'));
        $t->same([], $alignedHeadless->children[0]->children);
        $t->same(['right', 'left', 'center'], $alignedHeadless->attr('alignments'));
        $t->same('r2 d', $trailingSpaces->children[1]->children[1]->children[0]->attr('text'));
        $t->same('魚', $eastAsian->children[1]->children[0]->children[0]->attr('text'));
        $t->same([3 / 72, 5 / 72], $eastAsian->attr('widths'));
        $t->same("Auf\u{200C}lage", $zeroWidth->children[1]->children[1]->children[0]->attr('text'));
        $t->same('می‌خواهم', $persian->children[1]->children[0]->children[0]->attr('text'));
        $t->same('I want to', $persian->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $emptyCells->children[1]->children[0]->children[0]->attr('text'));
        $t->same([], $emptyCells->children[1]->children[0]->children[0]->children);
        $t->contains('<colgroup><col style="width:26.3889%"/><col style="width:16.6667%"/><col style="width:18.0556%"/></colgroup>', $blocks);
        $t->contains('<th style="text-align:right">col 1</th><th style="text-align:left">col 2</th><th style="text-align:center">col 3</th>', $blocks);
        $t->contains("<td style=\"text-align:right\">r1 a\nr1 bis</td><td style=\"text-align:left\">b\nb 2</td><td style=\"text-align:center\">c\nc 2</td>", $blocks);
        $t->contains('<td>魚</td><td>fish</td>', $blocks);
        $t->contains('<td></td><td></td>', $blocks);
    },
    'maps upstream markdown reader more grid tables with multiple block children' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Multiple blocks in a cell',
            '',
            '+------------------+-----------+------------+',
            '| # col 1          | # col 2   | # col 3    |',
            '| col 1            | col 2     | col 3      |',
            '+------------------+-----------+------------+',
            '| r1 a             | - b       | c          |',
            '|                  | - b 2     | c 2        |',
            '| r1 bis           | - b 2     | c 2        |',
            '+------------------+-----------+------------+',
        ]));
        $table = $document->children[1];
        $firstRow = $table->children[1]->children[0];
        $secondRow = $table->children[1]->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same([], $table->children[0]->children);
        $t->same([19 / 72, 12 / 72, 13 / 72], $table->attr('widths'));
        $t->same(['heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $firstRow->children[0]->children));
        $t->same(1, $firstRow->children[0]->children[0]->attr('level'));
        $t->same('col-1', $firstRow->children[0]->children[0]->attr('id'));
        $t->same('col 1', $firstRow->children[0]->children[0]->attr('text'));
        $t->same('col 1', $firstRow->children[0]->children[1]->attr('text'));
        $t->same(['paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $secondRow->children[0]->children));
        $t->same('r1 a', $secondRow->children[0]->children[0]->attr('text'));
        $t->same('r1 bis', $secondRow->children[0]->children[1]->attr('text'));
        $t->same('bullet_list', $secondRow->children[1]->children[0]->type);
        $t->same(3, count($secondRow->children[1]->children[0]->children));
        $t->same('b 2', $secondRow->children[1]->children[0]->children[1]->attr('text'));
        $t->same(['text', 'softbreak', 'text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $secondRow->children[2]->children));
        $t->same("c\nc 2\nc 2", $secondRow->children[2]->attr('text'));
        $t->contains('<td><h1 id="col-1">col 1</h1><p>col 1</p></td>', $blocks);
        $t->contains('<td><p>r1 a</p><p>r1 bis</p></td><td><ul><li>b</li><li>b 2</li><li>b 2</li></ul></td>', $blocks);
    },
    'maps upstream markdown reader more grid table row and column spans' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Table with cells spanning multiple rows or columns:',
            '',
            '+---------------------+----------+',
            '| Property            | Earth    |',
            '+=============+=======+==========+',
            '|             | min   | -89.2 °C |',
            '| Temperature +-------+----------+',
            '| 1961-1990   | mean  | 14 °C    |',
            '|             +-------+----------+',
            '|             | min   | 56.7 °C  |',
            '+-------------+-------+----------+',
        ]));
        $table = $document->children[1];
        $headRow = $table->children[0]->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same([14 / 72, 8 / 72, 11 / 72], $table->attr('widths'));
        $t->same(2, count($headRow->children));
        $t->same(2, $headRow->children[0]->attr('colspan'));
        $t->same('Property', $headRow->children[0]->attr('text'));
        $t->same('Earth', $headRow->children[1]->attr('text'));
        $t->same(3, count($body->children));
        $t->same(3, count($body->children[0]->children));
        $t->same(2, count($body->children[1]->children));
        $t->same(2, count($body->children[2]->children));
        $t->same(3, $body->children[0]->children[0]->attr('rowspan'));
        $t->same("Temperature\n1961-1990", $body->children[0]->children[0]->attr('text'));
        $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $body->children[0]->children[0]->children));
        $t->same('mean', $body->children[1]->children[0]->attr('text'));
        $t->same('56.7 °C', $body->children[2]->children[1]->attr('text'));
        $t->contains('<th colspan="2">Property</th><th>Earth</th>', $blocks);
        $t->contains("<td rowspan=\"3\">Temperature\n1961-1990</td><td>min</td><td>-89.2 °C</td>", $blocks);
        $t->contains('<tr><td>mean</td><td>14 °C</td></tr><tr><td>min</td><td>56.7 °C</td></tr>', $blocks);
    },
    'maps upstream markdown reader more grid table complex header spans' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Table with complex header:',
            '',
            '+---------------------+-----------------------+',
            '| Location            | Temperature 1961-1990 |',
            '|                     | in degree Celsius     |',
            '|                     +-------+-------+-------+',
            '|                     | min   | mean  | max   |',
            '+=====================+=======+=======+=======+',
            '| Antarctica          | -89.2 | N/A   | 19.8  |',
            '+---------------------+-------+-------+-------+',
            '| Earth               | -89.2 | 14    | 56.7  |',
            '+---------------------+-------+-------+-------+',
        ]));
        $table = $document->children[1];
        $head = $table->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same([22 / 72, 8 / 72, 8 / 72, 8 / 72], $table->attr('widths'));
        $t->same(2, count($head->children));
        $t->same(2, $head->children[0]->children[0]->attr('rowspan'));
        $t->same('Location', $head->children[0]->children[0]->attr('text'));
        $t->same(3, $head->children[0]->children[1]->attr('colspan'));
        $t->same("Temperature 1961-1990\nin degree Celsius", $head->children[0]->children[1]->attr('text'));
        $t->same(['min', 'mean', 'max'], array_map(static fn (AstNode $cell): string => (string) $cell->attr('text'), $head->children[1]->children));
        $t->contains("<th rowspan=\"2\">Location</th><th colspan=\"3\">Temperature 1961-1990\nin degree Celsius</th>", $blocks);
        $t->contains('<tr><th>min</th><th>mean</th><th>max</th></tr>', $blocks);
        $t->contains('<tr><td>Earth</td><td>-89.2</td><td>14</td><td>56.7</td></tr>', $blocks);
    },
    'maps upstream testsuite ampersand links and autolinks' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            "Here's a [link with an ampersand in the URL][1].",
            '',
            "Here's a link with an amersand in the link text: [AT&T][2].",
            '',
            "Here's an [inline link](/script?foo=1&bar=2).",
            '',
            "Here's an [inline link in pointy braces](</script?foo=1&bar=2>).",
            '',
            '[1]: http://example.com/?foo=1&bar=2',
            '[2]: http://att.com/  "AT&T" ',
            '',
            'With an ampersand: <http://example.com/?foo=1&bar=2>',
            '',
            '* In a list?',
            '* <http://example.com/>',
            '* It should.',
            '',
            'An e-mail address:  <nobody@nowhere.net>',
            '',
            '> Blockquoted: <http://example.com/>',
            '',
            'Auto-links should not occur here: `<http://example.com/>`',
            '',
            "\tor here: <http://example.com/>",
            '',
            '----',
        ]));
        $referenceUrl = $document->children[0]->children[1];
        $referenceAmpersand = $document->children[1]->children[1];
        $pointy = $document->children[3]->children[1];
        $autolink = $document->children[4]->children[1];
        $listAutolink = $document->children[5]->children[1]->children[0];
        $email = $document->children[6]->children[1];
        $quoteLink = $document->children[7]->children[0]->children[1];

        $t->same('http://example.com/?foo=1&bar=2', $referenceUrl->attr('url'));
        $t->same('AT&T', $referenceAmpersand->children[0]->attr('text'));
        $t->same('AT&T', $referenceAmpersand->attr('title'));
        $t->same('/script?foo=1&bar=2', $document->children[2]->children[1]->attr('url'));
        $t->same('/script?foo=1&bar=2', $pointy->attr('url'));
        $t->same('http://example.com/?foo=1&bar=2', $autolink->attr('url'));
        $t->same(['uri'], $autolink->attr('classes'));
        $t->same('http://example.com/', $listAutolink->attr('url'));
        $t->same('mailto:nobody@nowhere.net', $email->attr('url'));
        $t->same(['email'], $email->attr('classes'));
        $t->same('http://example.com/', $quoteLink->attr('url'));
        $t->same('code', $document->children[8]->children[1]->type);
        $t->same('<http://example.com/>', $document->children[8]->children[1]->attr('text'));
        $t->same('code_block', $document->children[9]->type);
        $t->same('or here: <http://example.com/>', $document->children[9]->attr('text'));
        $t->same('horizontal_rule', $document->children[10]->type);
    },
    'maps upstream testsuite images as reference figures and inline images' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '# Images',
            '',
            'From "Voyage dans la Lune" by Georges Melies (1902):',
            '',
            '![lalune][]',
            '',
            '   [lalune]: lalune.jpg "Voyage dans la Lune"',
            '',
            'Here is a movie ![movie](movie.jpg) icon.',
            '',
            '----',
        ]));
        $figure = $document->children[2];
        $figureImage = $figure->children[0];
        $inlineImage = $document->children[3]->children[1];

        $t->same('heading', $document->children[0]->type);
        $t->same('quoted', $document->children[1]->children[1]->type);
        $t->same('figure', $figure->type);
        $t->same('lalune', $figure->attr('caption'));
        $t->same('image', $figureImage->type);
        $t->same('lalune', $figureImage->attr('alt'));
        $t->same('lalune.jpg', $figureImage->attr('url'));
        $t->same('Voyage dans la Lune', $figureImage->attr('title'));
        $t->same('image', $inlineImage->type);
        $t->same('movie', $inlineImage->attr('alt'));
        $t->same('movie.jpg', $inlineImage->attr('url'));
        $t->same(' icon.', $document->children[3]->children[2]->attr('text'));
        $t->same('horizontal_rule', $document->children[4]->type);
    },
    'maps upstream markdown reader figure latex placement attributes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('![caption](img.jpg){latex-placement="htbp" alt="alt text"}');
        $figure = $document->children[0];
        $image = $figure->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('figure', $figure->type);
        $t->same('caption', $figure->attr('caption'));
        $t->same(['latex-placement' => 'htbp'], $figure->attr('attributes'));
        $t->same('image', $image->type);
        $t->same('img.jpg', $image->attr('url'));
        $t->same('alt text', $image->attr('alt'));
        $t->same('caption', $image->attr('caption'));
        $t->same('caption', $image->children[0]->attr('text'));
        $t->contains('<figure class="wp-block-image" data-pandoc-latex-placement="htbp"><img src="img.jpg" alt="alt text"/><figcaption>caption</figcaption></figure>', $blocks);
    },
    'maps upstream html reader images as paragraph image inlines' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '<h1>Images</h1>',
            '<p>From "Voyage dans la Lune" by Georges Melies (1902):</p>',
            '<p><img src="lalune.jpg" title="Voyage dans la Lune" alt="lalune"></p>',
            '<p>Here is a movie <img src="movie.jpg" alt="movie"> icon.</p>',
            '<hr />',
        ]));
        $standaloneImageParagraph = $document->children[2];
        $standaloneImage = $standaloneImageParagraph->children[0];
        $inlineParagraph = $document->children[3];
        $inlineImage = $inlineParagraph->children[1];

        $t->same('heading', $document->children[0]->type);
        $t->same('images', $document->children[0]->attr('id'));
        $t->same('From "Voyage dans la Lune" by Georges Melies (1902):', $document->children[1]->attr('text'));
        $t->same('paragraph', $standaloneImageParagraph->type);
        $t->same('image', $standaloneImage->type);
        $t->same('lalune', $standaloneImage->attr('alt'));
        $t->same('lalune.jpg', $standaloneImage->attr('url'));
        $t->same('Voyage dans la Lune', $standaloneImage->attr('title'));
        $t->same('lalune', $standaloneImage->children[0]->attr('text'));
        $t->same('Here is a movie ', $inlineParagraph->children[0]->attr('text'));
        $t->same('image', $inlineImage->type);
        $t->same('movie', $inlineImage->attr('alt'));
        $t->same('movie.jpg', $inlineImage->attr('url'));
        $t->same(' icon.', $inlineParagraph->children[2]->attr('text'));
        $t->same('horizontal_rule', $document->children[4]->type);
    },
    'maps upstream html reader footnote anchors and emphasis spacing as html links' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '<h1>Footnotes</h1>',
            '<p>Here is a footnote reference<a href="#note_1">(1)</a>, and another<a href="#note_longnote">(longnote)</a>. This should <em>not</em> be a footnote reference, because it contains a space^(my note).</p>',
            '<p><a href="#ref_1">(1)</a> Here is the footnote. It can go anywhere in the document, not just at the end.</p>',
            '<p><a href="#ref_longnote">(longnote)</a> Here\'s the other note. This one contains multiple blocks.</p>',
            '<p>Caret characters are used to indicate that the blocks all belong to a single footnote (as with block quotes).</p>',
            '<pre><code>  { &lt;code> }',
            '</code></pre>',
            '<p>If you want, you can use a caret at the beginning of every line, as with blockquotes, but all that you need is a caret at the beginning of the first line of the block and any preceding blank lines.</p>',
            '<p>text<em> Leading space</em></p>',
            '<p><em>Trailing space </em>text</p>',
            '<p>text<em>   Leading spaces</em></p>',
            '<p><em>Trailing spaces    </em>text</p>',
        ]));
        $referenceParagraph = $document->children[1];
        $shortBackref = $document->children[2]->children[0];
        $longBackref = $document->children[3]->children[0];
        $leadingSpace = $document->children[7];
        $trailingSpace = $document->children[8];
        $leadingSpaces = $document->children[9];
        $trailingSpaces = $document->children[10];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(11, count($document->children));
        $t->same('footnotes', $document->children[0]->attr('id'));
        $t->same('link', $referenceParagraph->children[1]->type);
        $t->same('#note_1', $referenceParagraph->children[1]->attr('url'));
        $t->same('link', $referenceParagraph->children[3]->type);
        $t->same('#note_longnote', $referenceParagraph->children[3]->attr('url'));
        $t->same('emph', $referenceParagraph->children[5]->type);
        $t->contains('space^(my note).', $referenceParagraph->children[6]->attr('text'));
        $t->same('link', $shortBackref->type);
        $t->same('#ref_1', $shortBackref->attr('url'));
        $t->same('link', $longBackref->type);
        $t->same('#ref_longnote', $longBackref->attr('url'));
        $t->same('code_block', $document->children[5]->type);
        $t->same('  { <code> }', $document->children[5]->attr('text'));
        $t->same(['text', 'text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $leadingSpace->children));
        $t->same(' ', $leadingSpace->children[1]->attr('text'));
        $t->same('Leading space', $leadingSpace->children[2]->children[0]->attr('text'));
        $t->same(['emph', 'text', 'text'], array_map(static fn (AstNode $node): string => $node->type, $trailingSpace->children));
        $t->same('Trailing space', $trailingSpace->children[0]->children[0]->attr('text'));
        $t->same(' ', $trailingSpace->children[1]->attr('text'));
        $t->same(' ', $leadingSpaces->children[1]->attr('text'));
        $t->same('Leading spaces', $leadingSpaces->children[2]->children[0]->attr('text'));
        $t->same('Trailing spaces', $trailingSpaces->children[0]->children[0]->attr('text'));
        $t->same(' ', $trailingSpaces->children[1]->attr('text'));
        $t->contains('<a href="#note_1">(1)</a>', $blocks);
        $t->contains('<a href="#ref_longnote">(longnote)</a> Here&#039;s the other note.', $blocks);
        $t->contains('<pre class="wp-block-code"><code>  { &lt;code&gt; }</code></pre>', $blocks);
        $t->contains('<p>text <em>Leading space</em></p>', $blocks);
        $t->contains('<p><em>Trailing space</em> text</p>', $blocks);
    },
    'maps upstream testsuite footnote references inline notes quotes and lists' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '# Footnotes',
            '',
            'Here is a footnote reference,[^1] and another.[^longnote]',
            'This should *not* be a footnote reference, because it ',
            'contains a space.[^my note]  Here is an inline note.^[This',
            'is *easier* to type.  Inline notes may contain',
            '[links](http://google.com) and `]` verbatim characters,',
            'as well as [bracketed text].]',
            '',
            '> Notes can go in quotes.^[In quote.]',
            '',
            '1.  And in list items.^[In list.]',
            '',
            "[^longnote]: Here's the long note.  This one contains multiple",
            'blocks.  ',
            '',
            '    Subsequent blocks are indented to show that they belong to the',
            'footnote (as with list items).',
            '',
            '          { <code> }',
            '',
            '    If you want, you can indent every line, but you can also be',
            'lazy and just indent the first line of each block.',
            '',
            'This paragraph should not be part of the note, as it is not indented.',
            '',
            '  [^1]: Here is the footnote.  It can go anywhere after the footnote',
            '  reference.  It need not be placed at the end of the document.',
        ]));
        $paragraph = $document->children[1];
        $shortNote = $paragraph->children[1];
        $longNote = $paragraph->children[3];
        $inlineNote = $paragraph->children[10];
        $inlineNoteParagraph = $inlineNote->children[0];
        $quoteNote = $document->children[2]->children[0]->children[1];
        $listNote = $document->children[3]->children[0]->children[1];

        $t->same('heading', $document->children[0]->type);
        $t->same('Footnotes', $document->children[0]->attr('text'));
        $t->same('note', $shortNote->type);
        $t->same('1', $shortNote->attr('label'));
        $t->contains('Here is the footnote.', $shortNote->children[0]->attr('text'));
        $t->same('note', $longNote->type);
        $t->same('longnote', $longNote->attr('label'));
        $t->same(['paragraph', 'paragraph', 'code_block', 'paragraph'], array_map(static fn ($node): string => $node->type, $longNote->children));
        $t->same('  { <code> }', $longNote->children[2]->attr('text'));
        $t->same('emph', $paragraph->children[6]->type);
        $t->contains('[^my note]', $paragraph->children[9]->attr('text'));
        $t->same('note', $inlineNote->type);
        $t->same('emph', $inlineNoteParagraph->children[3]->type);
        $t->same('easier', $inlineNoteParagraph->children[3]->children[0]->attr('text'));
        $t->same('link', $inlineNoteParagraph->children[6]->type);
        $t->same('http://google.com', $inlineNoteParagraph->children[6]->attr('url'));
        $t->same('code', $inlineNoteParagraph->children[8]->type);
        $t->same(']', $inlineNoteParagraph->children[8]->attr('text'));
        $t->contains('[bracketed text].', $inlineNoteParagraph->children[11]->attr('text'));
        $t->same('note', $quoteNote->type);
        $t->same('In quote.', $quoteNote->children[0]->attr('text'));
        $t->same('note', $listNote->type);
        $t->same('In list.', $listNote->children[0]->attr('text'));
        $t->same('paragraph', $document->children[4]->type);
        $t->same('This paragraph should not be part of the note, as it is not indented.', $document->children[4]->attr('text'));
    },
    'maps upstream markdown footnote indentation and recursive reference edge cases' => static function (TestRunner $t): void {
        $flushLeft = (new MarkdownReader())->read("[^1]\n\n[^1]: my note\n\n     \nnot in note\n");
        $indented = (new MarkdownReader())->read("[^1]\n\n[^1]: my note\n     \n    in note\n");
        $recursive = (new MarkdownReader())->read("[^1]\n\n[^1]: See [^1]\n");

        $t->same('note', $flushLeft->children[0]->children[0]->type);
        $t->same(1, count($flushLeft->children[0]->children[0]->children));
        $t->same('not in note', $flushLeft->children[1]->attr('text'));
        $t->same('note', $indented->children[0]->children[0]->type);
        $t->same(2, count($indented->children[0]->children[0]->children));
        $t->same('my note', $indented->children[0]->children[0]->children[0]->attr('text'));
        $t->same('in note', $indented->children[0]->children[0]->children[1]->attr('text'));
        $t->same('note', $recursive->children[0]->children[0]->type);
        $t->same('See [^1]', $recursive->children[0]->children[0]->children[0]->attr('text'));
        $t->same(1, count($recursive->children[0]->children[0]->children[0]->children));
    },
    'maps upstream inline code containing list marker text' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("1. `#. x`\n2. `x``#. x`\n- `- x`\n- `x``- x`");
        $ordered = $document->children[0];
        $bullet = $document->children[1];

        $t->same('ordered_list', $ordered->type);
        $t->same('#. x', $ordered->children[0]->children[0]->attr('text'));
        $t->same('x``#. x', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->same('- x', $bullet->children[0]->children[0]->attr('text'));
        $t->same('x``- x', $bullet->children[1]->children[0]->attr('text'));
    },
    'maps upstream indented backtick fenced code command example' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("  ```haskell\n  let x = y\nin y\n   ```");
        $code = $document->children[0];

        $t->same('code_block', $code->type);
        $t->same(['haskell'], $code->attr('classes'));
        $t->same("let x = y\nin y", $code->attr('text'));
    },
    'maps upstream indented tilde fenced code attributes example' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(" ~~~ {.haskell}\n  let x = y\n in y +\ny +\n y\n~~~");
        $code = $document->children[0];

        $t->same('code_block', $code->type);
        $t->same(['haskell'], $code->attr('classes'));
        $t->same(" let x = y\nin y +\ny +\ny", $code->attr('text'));
    },
    'maps upstream markdown literate haskell bird tracks when enabled' => static function (TestRunner $t): void {
        $default = (new MarkdownReader())->read("> a");
        $document = (new MarkdownReader(['literateHaskell' => true]))->read("> a\n> b\n\n< c\n\n<div>\nsource note\n</div>");
        $birdTrack = $document->children[0];
        $inverseBirdTrack = $document->children[1];
        $htmlDiv = $document->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('blockquote', $default->children[0]->type);
        $t->same(['code_block', 'code_block', 'div'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(['haskell', 'literate'], $birdTrack->attr('classes'));
        $t->same("a\nb", $birdTrack->attr('text'));
        $t->same(['haskell'], $inverseBirdTrack->attr('classes'));
        $t->same('c', $inverseBirdTrack->attr('text'));
        $t->same('source note', $htmlDiv->children[0]->attr('text'));
        $t->contains("<pre class=\"wp-block-code\"><code class=\"language-haskell\">a\nb</code></pre>", $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-haskell">c</code></pre>', $blocks);
    },
    'maps upstream lhs fixture blockquote boundary when literate haskell is enabled' => static function (TestRunner $t): void {
        $markdown = <<<'MD'
lhs test
========

`unsplit` is an arrow that takes a pair of values and combines them to
return a single value:

> unsplit :: (Arrow a) => (b -> c -> d) -> a (b, c) d
> unsplit = arr . uncurry
>           -- arr (\op (x,y) -> x `op` y)

`(***)` combines two arrows into a new arrow by running the two arrows on a
pair of values.

    f *** g = first f >>> second g

Block quote:

 > foo bar
MD;
        $document = (new MarkdownReader(['literateHaskell' => true]))->read($markdown);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(
            ['heading', 'paragraph', 'code_block', 'paragraph', 'code_block', 'paragraph', 'blockquote'],
            array_map(static fn (AstNode $node): string => $node->type, $document->children)
        );
        $t->same(['haskell', 'literate'], $document->children[2]->attr('classes'));
        $t->same("unsplit :: (Arrow a) => (b -> c -> d) -> a (b, c) d\nunsplit = arr . uncurry\n          -- arr (\\op (x,y) -> x `op` y)", $document->children[2]->attr('text'));
        $t->same([], $document->children[4]->attr('classes', []));
        $t->same('f *** g = first f >>> second g', $document->children[4]->attr('text'));
        $t->same('foo bar', $document->children[6]->children[0]->attr('text'));
        $t->contains("<pre class=\"wp-block-code\"><code class=\"language-haskell\">unsplit :: (Arrow a) =&gt; (b -&gt; c -&gt; d) -&gt; a (b, c) d\nunsplit = arr . uncurry\n          -- arr (\\op (x,y) -&gt; x `op` y)</code></pre>", $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>foo bar</p></blockquote>', $blocks);
    },
    'maps upstream testsuite indented code blocks and tab expansion' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            'Code:',
            '',
            '    ---- (should be four hyphens)',
            '',
            '    sub status {',
            '        print "working";',
            '    }',
            '',
            "\tthis code block is indented by one tab",
            '',
            'And:',
            '',
            "\t\tthis code block is indented by two tabs",
            '',
            '    These should not be escaped:  \$ \\\\ \> \[ \{',
        ]);
        $document = (new MarkdownReader())->read($markdown);

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Code:', $document->children[0]->attr('text'));
        $t->same('code_block', $document->children[1]->type);
        $t->same("---- (should be four hyphens)\n\nsub status {\n    print \"working\";\n}\n\nthis code block is indented by one tab", $document->children[1]->attr('text'));
        $t->same('paragraph', $document->children[2]->type);
        $t->same('And:', $document->children[2]->attr('text'));
        $t->same('code_block', $document->children[3]->type);
        $t->same('    this code block is indented by two tabs' . "\n\n" . 'These should not be escaped:  \$ \\\\ \> \[ \{', $document->children[3]->attr('text'));
    },
    'maps upstream testsuite horizontal rules without treating spaced asterisks as a list' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("___________\n\nM.A. 2007\n\nB. Williams\n\n  *   *   *   *   *");

        $t->same('horizontal_rule', $document->children[0]->type);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('M.A. 2007', $document->children[1]->attr('text'));
        $t->same('paragraph', $document->children[2]->type);
        $t->same('B. Williams', $document->children[2]->attr('text'));
        $t->same('horizontal_rule', $document->children[3]->type);
    },
    'maps pandoc markdown dash and asterisk horizontal rule forms' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("---\n\n* * *\n\n___");

        $t->same('horizontal_rule', $document->children[0]->type);
        $t->same('horizontal_rule', $document->children[1]->type);
        $t->same('horizontal_rule', $document->children[2]->type);
    },
    'groups ordered lists as a list block' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("3. Export WXR\n4. Convert Markdown\n5. Publish blocks");
        $list = $document->children[0];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('Convert Markdown', $list->children[1]->attr('text'));
    },
    'maps upstream testsuite loose bullet list items as paragraphs' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("*\tasterisk 1\n\n*\tasterisk 2\n\n*\tasterisk 3");
        $list = $document->children[0];

        $t->same('bullet_list', $list->type);
        $t->true((bool) $list->attr('loose'));
        $t->same('paragraph', $list->children[0]->children[0]->type);
        $t->same('asterisk 1', $list->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $list->children[2]->children[0]->type);
        $t->same('asterisk 3', $list->children[2]->children[0]->attr('text'));
    },
    'maps upstream testsuite ordered list continuation paragraphs' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            "1.\tItem 1, graf one.",
            '',
            "\tItem 1. graf two. The quick brown fox jumped over the lazy dog's",
            "\tback.",
            "\t",
            "2.\tItem 2.",
            '',
            "3.\tItem 3.",
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];
        $firstItem = $list->children[0];

        $t->same('ordered_list', $list->type);
        $t->true((bool) $list->attr('loose'));
        $t->same(2, count($firstItem->children));
        $t->same('paragraph', $firstItem->children[0]->type);
        $t->same('Item 1, graf one.', $firstItem->children[0]->attr('text'));
        $t->same('Item 1. graf two. The quick brown fox jumped over the lazy dog\'s back.', $firstItem->children[1]->attr('text'));
        $t->same('Item 2.', $list->children[1]->children[0]->attr('text'));
    },
    'maps upstream markdown nested list item shape' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("* a\n* b\n* c\n    * d");
        $list = $document->children[0];
        $nested = $list->children[2]->children[1];

        $t->same('bullet_list', $list->type);
        $t->same('c', $list->children[2]->children[0]->attr('text'));
        $t->same('bullet_list', $nested->type);
        $t->same('d', $nested->children[0]->children[0]->attr('text'));
    },
    'maps upstream command task list items into ast attrs and checkbox html' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $simple = $reader->read("- [ ] foo\n- [x] bar")->children[0];
        $nested = $reader->read("- [x] foo\n  - [ ] bar\n  - [x] baz\n- [ ] bim")->children[0];
        $custom = $reader->read(implode("\n", [
            '- [ ]  unchecked',
            '- plain item',
            '-  [x] checked',
            '',
            'paragraph',
            '',
            '1. [ ] ordered unchecked',
            '2. [] plain item',
            '3. [x] ordered checked',
            '',
            'paragraph',
            '',
            '- [ ] list item with a',
            '',
            '    second paragraph',
            '',
            '- [x] checked',
        ]));
        $blocks = (new WordPressBlockWriter())->write($custom);

        $t->same('bullet_list', $simple->type);
        $t->true((bool) $simple->attr('taskList'));
        $t->same(false, $simple->children[0]->attr('taskChecked'));
        $t->same(true, $simple->children[1]->attr('taskChecked'));
        $t->same('foo', $simple->children[0]->children[0]->attr('text'));
        $t->same('bar', $simple->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $nested->children[0]->children[1]->type);
        $t->true((bool) $nested->children[0]->children[1]->attr('taskList'));
        $t->same(false, $nested->children[0]->children[1]->children[0]->attr('taskChecked'));
        $t->same(true, $nested->children[0]->children[1]->children[1]->attr('taskChecked'));
        $t->same(false, $custom->children[0]->attr('taskList', false), 'Mixed task/plain bullet lists should not receive task-list class');
        $t->same(false, $custom->children[0]->children[0]->attr('taskChecked'));
        $t->same(null, $custom->children[0]->children[1]->attr('taskChecked', null));
        $t->same(true, $custom->children[0]->children[2]->attr('taskChecked'));
        $t->same('ordered_list', $custom->children[2]->type);
        $t->same(false, $custom->children[2]->children[0]->attr('taskChecked'));
        $t->same('[] plain item', $custom->children[2]->children[1]->attr('text'));
        $t->same(true, $custom->children[2]->children[2]->attr('taskChecked'));
        $t->true((bool) $custom->children[4]->attr('taskList'));
        $t->same('paragraph', $custom->children[4]->children[0]->children[0]->type);
        $t->same('second paragraph', $custom->children[4]->children[0]->children[1]->attr('text'));
        $t->contains('<ul><li><label><input type="checkbox" />unchecked</label></li><li>plain item</li><li><label><input type="checkbox" checked="" />checked</label></li></ul>', $blocks);
        $t->contains('<ol><li><label><input type="checkbox" />ordered unchecked</label></li><li>[] plain item</li><li><label><input type="checkbox" checked="" />ordered checked</label></li></ol>', $blocks);
        $t->contains('<ul class="task-list"><li><p><label><input type="checkbox" />list item with a</label></p><p>second paragraph</p></li><li><p><label><input type="checkbox" checked="" />checked</label></p></li></ul>', $blocks);
    },
    'maps upstream command task list markdown and latex writer examples' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $markdownRoundTrip = $reader->read("- [ ] foo\n- [x] bar");
        $latexTaskList = $reader->read("- [ ] foo bar\n\n  baz\n\n- [x] ok");

        $t->same("- [ ] foo\n- [x] bar", (new MarkdownWriter())->write($markdownRoundTrip));
        $t->same(implode("\n", [
            '\begin{itemize}',
            '\item[$\square$]',
            '  foo bar',
            '',
            '  baz',
            '\item[$\boxtimes$]',
            '  ok',
            '\end{itemize}',
        ]), (new LatexWriter())->write($latexTaskList));
    },
    'maps upstream markdown writer heading attributes' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $document = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 1,
                'id' => 'import-review',
                'classes' => ['wp-import', 'needs-review'],
                'attributes' => ['data-source' => 'batch-42'],
            ], [$text('Import Review')]),
            new AstNode('heading', [
                'level' => 2,
                'id' => 'review-packet',
                'classes' => ['handoff'],
                'attributes' => ['title' => 'Migration "review" packet'],
            ], [
                $text('Reviewer '),
                new AstNode('emph', [], [$text('Packet')]),
            ]),
            new AstNode('heading', [
                'level' => 3,
                'id' => 'follow-up',
                'classes' => ['qa'],
            ], [$text('Follow-up')]),
        ]);

        $t->same(implode("\n\n", [
            '# Import Review {#import-review .wp-import .needs-review data-source="batch-42"}',
            '## Reviewer *Packet* {#review-packet .handoff title="Migration \\"review\\" packet"}',
            '### Follow-up {#follow-up .qa}',
        ]), (new MarkdownWriter())->write($document));

        $t->same(
            'Import Review {#import-review .wp-import .needs-review data-source="batch-42"}'
                . "\n" . '=============================================================================='
                . "\n\n" . 'Reviewer *Packet* {#review-packet .handoff title="Migration \\"review\\" packet"}'
                . "\n" . '-------------------------------------------------------------------------------'
                . "\n\n" . '### Follow-up {#follow-up .qa}',
            (new MarkdownWriter(['setextHeadings' => true]))->write($document)
        );
    },
    'maps upstream markdown writer softbreak space option' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Reviewer soft boundary']),
                new AstNode('softbreak'),
                new AstNode('text', ['text' => 'stays readable in a compact handoff']),
                new AstNode('linebreak'),
                new AstNode('text', ['text' => 'while hard line breaks remain explicit']),
            ]),
            new AstNode('table', [
                'alignments' => ['left', 'left'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Source'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Note'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'post'])]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'soft one']),
                            new AstNode('softbreak'),
                            new AstNode('text', ['text' => 'soft two']),
                            new AstNode('linebreak'),
                            new AstNode('text', ['text' => 'hard follow-up']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            "Reviewer soft boundary\nstays readable in a compact handoff\\\nwhile hard line breaks remain explicit",
            "| Source | Note                                       |\n"
                . "|:-----|:-----------------------------------------|\n"
                . '| post   | soft one<br />soft two<br />hard follow-up |',
        ]), (new MarkdownWriter())->write($document));

        $t->same(implode("\n\n", [
            "Reviewer soft boundary stays readable in a compact handoff\\\nwhile hard line breaks remain explicit",
            "| Source | Note                                  |\n"
                . "|:-----|:------------------------------------|\n"
                . '| post   | soft one soft two<br />hard follow-up |',
        ]), (new MarkdownWriter(['softBreak' => 'space']))->write($document));
    },
    'preserves rebased upstream markdown writer space softbreak and linebreak emission' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Source']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'review']),
                new AstNode('softbreak'),
                new AstNode('text', ['text' => 'continues']),
                new AstNode('linebreak'),
                new AstNode('text', ['text' => 'with hard break']),
            ]),
        ]);

        $t->same("Source review\ncontinues\\\nwith hard break", (new MarkdownWriter())->write($document));
        $t->same("Source review continues\\\nwith hard break", (new MarkdownWriter(['softBreak' => 'space']))->write($document));
    },
    'maps upstream markdown writer fancy ordered list markers' => static function (TestRunner $t): void {
        $writer = new MarkdownWriter();
        $reader = new MarkdownReader();
        $nested = $reader->read("A.  Upper Alpha\n    I.  Upper Roman.\n        (6) Decimal start with 6\n            c)  Lower alpha with paren");
        $twoParens = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 2, 'style' => 'decimal', 'delimiter' => 'two_parens'], [
                new AstNode('list_item', ['text' => 'begins with 2', 'number' => 2], [
                    new AstNode('text', ['text' => 'begins with 2']),
                ]),
                new AstNode('list_item', ['text' => 'and now 3', 'number' => 3], [
                    new AstNode('text', ['text' => 'and now 3']),
                ]),
            ]),
        ]);
        $roman = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                new AstNode('list_item', ['text' => 'roman checkpoint', 'number' => 4], [
                    new AstNode('text', ['text' => 'roman checkpoint']),
                ]),
                new AstNode('list_item', ['text' => 'publish handoff', 'number' => 5], [
                    new AstNode('text', ['text' => 'publish handoff']),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            'A.  Upper Alpha',
            '  I.  Upper Roman.',
            '    (6) Decimal start with 6',
            '      c)  Lower alpha with paren',
        ]), $writer->write($nested));
        $t->same("(2) begins with 2\n(3) and now 3", $writer->write($twoParens));
        $t->same("iv. roman checkpoint\nv.  publish handoff", $writer->write($roman));
        $t->same("1.  Autonumber.\n2.  More.\n  1.  Nested.", $writer->write($reader->read(" #.  Autonumber.\n #.  More.\n     #.  Nested.")));
    },
    'maps upstream markdown writer roman list marker overflow' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 3999, 'style' => 'upper_roman'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'last supported upper roman'])]),
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'overflow upper roman'])]),
            ]),
            new AstNode('ordered_list', ['start' => 4000, 'style' => 'lower_roman', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'overflow lower roman'])]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            "MMMCMXCIX. last supported upper roman\n?.  overflow upper roman",
            '?)  overflow lower roman',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer alphabetic list marker overflow' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 25, 'style' => 'lower_alpha', 'delimiter' => 'period'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'review y marker'])]),
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'review z marker'])]),
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'review aa marker'])]),
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'review ab marker'])]),
            ]),
            new AstNode('ordered_list', ['start' => 27, 'style' => 'upper_alpha', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'upper AA marker'])]),
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'upper AB marker'])]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            "y.  review y marker\nz.  review z marker\naa. review aa marker\nab. review ab marker",
            'AA) upper AA marker' . "\n" . 'AB) upper AB marker',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer decimal ordered list zero start marker' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 0, 'style' => 'decimal', 'delimiter' => 'period'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'zero indexed review step'])]),
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'first published step'])]),
            ]),
            new AstNode('ordered_list', ['start' => -2, 'style' => 'decimal', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'clamped imported negative marker'])]),
            ]),
            new AstNode('ordered_list', ['start' => 0, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                new AstNode('list_item', [], [new AstNode('text', ['text' => 'roman still starts at one'])]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            "0.  zero indexed review step\n1.  first published step",
            '0)  clamped imported negative marker',
            'i.  roman still starts at one',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer bullet list marker option' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('text', ['text' => 'review queue item']),
                    new AstNode('bullet_list', [], [
                        new AstNode('list_item', [], [
                            new AstNode('text', ['text' => 'nested review task']),
                        ]),
                    ]),
                ]),
                new AstNode('list_item', ['taskChecked' => true], [
                    new AstNode('text', ['text' => 'checked import task']),
                ]),
            ]),
        ]);

        $t->same("- review queue item\n  - nested review task\n- [x] checked import task", (new MarkdownWriter())->write($document));
        $t->same("+ review queue item\n  + nested review task\n+ [x] checked import task", (new MarkdownWriter(['bulletListMarker' => 'plus']))->write($document));
        $t->same("* review queue item\n  * nested review task\n* [x] checked import task", (new MarkdownWriter(['bulletListMarker' => 'star']))->write($document));
    },
    'maps upstream markdown writer note and reference placement' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [
            'text' => implode('', array_map(
                static fn (AstNode $node): string => $node->type === 'text' ? (string) $node->attr('text', '') : '',
                $children
            )),
        ], $children);
        $heading = static fn (string $text): AstNode => new AstNode('heading', [
            'level' => 1,
            'text' => $text,
        ], [new AstNode('text', ['text' => $text])]);
        $note = static fn (string $text): AstNode => new AstNode('note', [], [
            new AstNode('paragraph', ['text' => $text], [new AstNode('text', ['text' => $text])]),
        ]);

        $document = new AstNode('document', [], [
            $heading('First Header'),
            $paragraph([
                $text('This is a footnote.'),
                $note('Down here.'),
                $text(' And this is a '),
                new AstNode('link', ['url' => 'https://www.google.com', 'title' => ''], [$text('link')]),
                $text('.'),
            ]),
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('A note inside a block quote.'),
                    $note('The second note.'),
                ]),
                $paragraph([$text('A second paragraph.')]),
            ]),
            $heading('Second Header'),
            $paragraph([$text('Some more text.')]),
        ]);

        $endOfDocument = (new MarkdownWriter(['setextHeadings' => true]))->write($document);
        $endOfBlockFootnotesOnly = (new MarkdownWriter([
            'setextHeadings' => true,
            'referenceLocation' => 'end_of_block',
        ]))->write($document);
        $endOfBlock = (new MarkdownWriter([
            'setextHeadings' => true,
            'referenceLocation' => 'end_of_block',
            'referenceLinks' => true,
        ]))->write($document);
        $endOfSection = (new MarkdownWriter([
            'setextHeadings' => true,
            'referenceLocation' => 'end_of_section',
        ]))->write($document);

        $t->same(implode("\n", [
            'First Header',
            '============',
            '',
            'This is a footnote.[^1] And this is a [link](https://www.google.com).',
            '',
            '> A note inside a block quote.[^2]',
            '>',
            '> A second paragraph.',
            '',
            'Second Header',
            '=============',
            '',
            'Some more text.',
            '',
            '[^1]: Down here.',
            '',
            '[^2]: The second note.',
        ]), $endOfDocument);
        $t->same(implode("\n", [
            'First Header',
            '============',
            '',
            'This is a footnote.[^1] And this is a [link](https://www.google.com).',
            '',
            '[^1]: Down here.',
            '',
            '> A note inside a block quote.[^2]',
            '>',
            '> A second paragraph.',
            '',
            '[^2]: The second note.',
            '',
            'Second Header',
            '=============',
            '',
            'Some more text.',
        ]), $endOfBlockFootnotesOnly);
        $t->same(implode("\n", [
            'First Header',
            '============',
            '',
            'This is a footnote.[^1] And this is a [link].',
            '',
            '[^1]: Down here.',
            '',
            '  [link]: https://www.google.com',
            '',
            '> A note inside a block quote.[^2]',
            '>',
            '> A second paragraph.',
            '',
            '[^2]: The second note.',
            '',
            'Second Header',
            '=============',
            '',
            'Some more text.',
        ]), $endOfBlock);
        $t->same(implode("\n", [
            'First Header',
            '============',
            '',
            'This is a footnote.[^1] And this is a [link](https://www.google.com).',
            '',
            '> A note inside a block quote.[^2]',
            '>',
            '> A second paragraph.',
            '',
            '[^1]: Down here.',
            '',
            '[^2]: The second note.',
            '',
            'Second Header',
            '=============',
            '',
            'Some more text.',
        ]), $endOfSection);
    },
    'maps upstream markdown writer shortcut reference link boundaries' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $link = static fn (string $url, string $title, string $label): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => $title,
        ], [$text($label)]);
        $raw = static fn (string $markdown): AstNode => new AstNode('raw_inline', [
            'format' => 'markdown',
            'text' => $markdown,
        ]);
        $citation = static fn (string $markdown): AstNode => new AstNode('citation', [
            'id' => trim($markdown, '[]@'),
            'text' => $markdown,
        ], [$text($markdown)]);
        $write = static function (array $children): string {
            $document = new AstNode('document', [], [
                new AstNode('paragraph', [], $children),
            ]);

            return (new MarkdownWriter(['referenceLinks' => true]))->write($document);
        };

        $t->same("[foo]\n\n  [foo]: /url \"title\"", $write([
            $link('/url', 'title', 'foo'),
        ]));
        $t->same(implode("\n", [
            '[first][][second]',
            '',
            '  [first]: /url1 "title1"',
            '  [second]: /url2 "title2"',
        ]), $write([
            $link('/url1', 'title1', 'first'),
            $link('/url2', 'title2', 'second'),
        ]));
        $t->same(implode("\n", [
            '[first][] [second]',
            '',
            '  [first]: /url1 "title1"',
            '  [second]: /url2 "title2"',
        ]), $write([
            $link('/url1', 'title1', 'first'),
            $text(' '),
            $link('/url2', 'title2', 'second'),
        ]));
        $t->same(implode("\n", [
            '[foo][][foo][1][foo][2]',
            '',
            '  [foo]: /url1',
            '  [1]: /url2',
            '  [2]: /url3',
        ]), $write([
            $link('/url1', '', 'foo'),
            $link('/url2', '', 'foo'),
            $link('/url3', '', 'foo'),
        ]));
        $t->same(implode("\n", [
            '[foo][] [foo][1] [foo][2]',
            '',
            '  [foo]: /url1',
            '  [1]: /url2',
            '  [2]: /url3',
        ]), $write([
            $link('/url1', '', 'foo'),
            $text(' '),
            $link('/url2', '', 'foo'),
            $text(' '),
            $link('/url3', '', 'foo'),
        ]));
        $t->same(implode("\n", [
            '[link][]\\[text in brackets\\]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $text('[text in brackets]'),
        ]));
        $t->same(implode("\n", [
            '[link][] \\[text in brackets\\]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $text(' [text in brackets]'),
        ]));
        $t->same(implode("\n", [
            '[link][][rawText]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $raw('[rawText]'),
        ]));
        $t->same(implode("\n", [
            '[link][] [rawText]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $text(' '),
            $raw('[rawText]'),
        ]));
        $t->same(implode("\n", [
            '[link][] [rawText]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $raw(' [rawText]'),
        ]));
        $t->same(implode("\n", [
            '[link][][@author]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $citation('[@author]'),
        ]));
        $t->same(implode("\n", [
            '[link][] [@author]',
            '',
            '  [link]: /url',
        ]), $write([
            $link('/url', '', 'link'),
            $text(' '),
            $citation('[@author]'),
        ]));
    },
    'maps upstream markdown writer inline escaping and generated reference labels' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $link = static fn (string $url, string $label): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => '',
        ], [$text($label)]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('# Heading-looking source -- ... ::: ![draft] ~~gone~~ a_b *stars* _under_ `tick` | ^ ~ $ <tag> > &ouml; \\macro '),
                $link('/review', 'bracket [label]'),
                $text(' and '),
                $link('/review', 'bracket [again]'),
                $text(' then '),
                $link('/other', 'normal'),
                $text(' and '),
                $link('/other-2', 'normal'),
            ]),
        ]);

        $t->same(implode("\n", [
            '\\# Heading-looking source \\-- \\... \\::: \\![draft\\] \\~~gone\\~~ a_b \\*stars\\* \\_under\\_ \\`tick\\` \\| \\^ \\~ \\$ \\<tag\\> \\> \\&ouml; \\\\macro [bracket \\[label\\]][1] and [bracket \\[again\\]][1] then [normal] and [normal][2]',
            '',
            '  [1]: /review',
            '  [normal]: /other',
            '  [2]: /other-2',
        ]), (new MarkdownWriter(['referenceLinks' => true]))->write($document));
    },
    'maps upstream markdown writer uri email autolinks and link attributes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Source packet: '),
                new AstNode('link', [
                    'url' => 'https://example.test/review?post=42',
                    'classes' => ['uri'],
                ], [$text('https://example.test/review?post=42')]),
                $text(' and '),
                new AstNode('link', [
                    'url' => 'mailto:editor@example.test',
                    'classes' => ['email'],
                ], [$text('editor@example.test')]),
                $text(' plus '),
                new AstNode('link', [
                    'url' => 'https://example.test/packet',
                    'title' => 'Packet "review"',
                    'id' => 'packet',
                    'classes' => ['source-link', 'handoff'],
                    'attributes' => [
                        'data-source' => 'batch-42',
                        'title' => 'Packet "audit"',
                    ],
                ], [$text('packet link')]),
            ]),
        ]);

        $t->same(
            'Source packet: <https://example.test/review?post=42> and <editor@example.test> plus [packet link](https://example.test/packet "Packet \\"review\\""){#packet .source-link .handoff data-source="batch-42" title="Packet \\"audit\\""}',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer spaced link destinations with angle brackets' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('link', [
                    'url' => 'https://example.test/import packets/source one.html',
                    'title' => 'Packet review',
                ], [$text('source packet')]),
                $text(' and '),
                new AstNode('link', [
                    'url' => 'https://example.test/import<raw>/source two.html',
                ], [$text('raw packet')]),
                $text(' plus '),
                new AstNode('link', [
                    'url' => 'https://example.test/import packets/source one.html',
                ], [$text('reference packet')]),
            ]),
        ]);

        $t->same(
            '[source packet](<https://example.test/import packets/source one.html> "Packet review") and [raw packet](<https://example.test/import\\<raw\\>/source two.html>) plus [reference packet](<https://example.test/import packets/source one.html>)',
            (new MarkdownWriter())->write($document)
        );
        $t->same(implode("\n", [
            '[source packet] and [raw packet] plus [reference packet]',
            '',
            '  [source packet]: <https://example.test/import packets/source one.html> "Packet review"',
            '  [raw packet]: <https://example.test/import\\<raw\\>/source two.html>',
            '  [reference packet]: <https://example.test/import packets/source one.html>',
        ]), (new MarkdownWriter(['referenceLinks' => true]))->write($document));
    },
    'maps upstream markdown writer parenthesized link destinations with angle brackets' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('link', [
                    'url' => 'https://example.test/import/archive(2026)/source).html',
                    'title' => 'Archive (source)',
                ], [$text('archive packet')]),
                $text(' and '),
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/review(frame).jpg',
                    'alt' => 'Review frame',
                ], [$text('Review frame')]),
            ]),
        ]);

        $t->same(
            '[archive packet](<https://example.test/import/archive(2026)/source).html> "Archive (source)") and ![Review frame](<https://example.test/uploads/review(frame).jpg>)',
            (new MarkdownWriter())->write($document)
        );
        $t->same(implode("\n", [
            '[archive packet] and ![Review frame]',
            '',
            '  [archive packet]: <https://example.test/import/archive(2026)/source).html> "Archive (source)"',
            '  [Review frame]: <https://example.test/uploads/review(frame).jpg>',
        ]), (new MarkdownWriter(['referenceLinks' => true]))->write($document));
    },
    'maps upstream markdown writer reference definitions with link attributes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $sourceLink = static fn (string $id, string $source): AstNode => new AstNode('link', [
            'url' => '/source',
            'title' => 'Source title',
            'id' => $id,
            'classes' => ['source-link'],
            'attributes' => ['data-source' => $source],
        ], [$text('source')]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('link', [
                    'url' => 'https://example.test/review',
                    'classes' => ['uri'],
                ], [$text('https://example.test/review')]),
                $text(' '),
                $sourceLink('source-a', 'a'),
                $text(' '),
                $sourceLink('source-b', 'b'),
                $text(' '),
                $sourceLink('source-a', 'a'),
            ]),
        ]);

        $t->same(implode("\n", [
            '<https://example.test/review> [source][] [source][1] [source]',
            '',
            '  [source]: /source "Source title" {#source-a .source-link data-source="a"}',
            '  [1]: /source "Source title" {#source-b .source-link data-source="b"}',
        ]), (new MarkdownWriter(['referenceLinks' => true]))->write($document));
    },
    'maps upstream markdown writer images from testsuite figure and inline shapes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('figure', ['caption' => 'lalune'], [
                new AstNode('image', [
                    'url' => 'lalune.jpg',
                    'title' => 'Voyage dans la Lune',
                    'alt' => 'lalune',
                ], [$text('lalune')]),
            ]),
            new AstNode('paragraph', [], [
                $text('Here is a movie '),
                new AstNode('image', [
                    'url' => 'movie.jpg',
                    'alt' => 'movie',
                ], [$text('movie')]),
                $text(' icon.'),
            ]),
        ]);

        $t->same(implode("\n", [
            '![lalune](lalune.jpg "Voyage dans la Lune")',
            '',
            'Here is a movie ![movie](movie.jpg) icon.',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer image attributes alt override and autolink guard' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Source image guard: '),
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/source.jpg',
                    'alt' => 'https://example.test/uploads/source.jpg',
                ], [$text('https://example.test/uploads/source.jpg')]),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Reviewer media: '),
                new AstNode('image', [
                    'url' => '/uploads/review.jpg',
                    'title' => 'Review "image"',
                    'alt' => 'Editorial alt',
                    'id' => 'review-image',
                    'classes' => ['wp-import'],
                    'attributes' => ['data-source' => 'batch-42'],
                ], [$text('Visible caption')]),
                $text('.'),
            ]),
        ]);

        $t->same(implode("\n", [
            'Source image guard: ![](https://example.test/uploads/source.jpg).',
            '',
            'Reviewer media: ![Visible caption](/uploads/review.jpg "Review \\"image\\""){#review-image .wp-import alt="Editorial alt" data-source="batch-42"}.',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer inline code attributes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer token: '),
                new AstNode('code', [
                    'text' => 'wp_enqueue_script',
                    'id' => 'enqueue',
                    'classes' => ['php', 'wp-import'],
                    'attributes' => [
                        'data-source' => 'batch-42',
                        'title' => 'Import source',
                    ],
                ]),
                $text(' and literal '),
                new AstNode('code', [
                    'text' => 'a`b',
                    'classes' => ['sample'],
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Reviewer token: `wp_enqueue_script`{#enqueue .php .wp-import data-source="batch-42" title="Import source"} and literal `` a`b ``{.sample}.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer bracketed span attributes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer span: '),
                new AstNode('span', [
                    'id' => 'migration-span',
                    'classes' => ['review-span', 'wp-import'],
                    'attributes' => [
                        'data-source' => 'batch-42',
                        'title' => 'Migration span',
                    ],
                ], [
                    new AstNode('emph', [], [$text('urgent')]),
                    $text(' source flag '),
                    new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit')]),
                ]),
                $text(' and empty attrs '),
                new AstNode('span', [], [$text('plain')]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Reviewer span: [*urgent* source flag [edit](/wp-admin/post.php?post=42&action=edit)]{#migration-span .review-span .wp-import data-source="batch-42" title="Migration span"} and empty attrs plain.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer small caps underline strikeout superscript and subscript' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer marks: '),
                new AstNode('small_caps', [], [$text('source glossary')]),
                $text(', '),
                new AstNode('underline', [
                    'attributes' => ['data-source' => 'html-reader'],
                ], [
                    $text('inserted '),
                    new AstNode('strong', [], [$text('review')]),
                ]),
                $text(', '),
                new AstNode('strikeout', [], [
                    $text('legacy '),
                    new AstNode('emph', [], [$text('caption')]),
                ]),
                $text(', post'),
                new AstNode('superscript', [], [$text('draft 2')]),
                $text(', and H'),
                new AstNode('subscript', [], [$text('2')]),
                $text('O.'),
            ]),
        ]);

        $t->same(
            'Reviewer marks: [source glossary]{.smallcaps}, [inserted **review**]{.underline data-source="html-reader"}, ~~legacy *caption*~~, post^draft\\ 2^, and H~2~O.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer quoted inline emission' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer quotes: '),
                new AstNode('quoted', ['kind' => 'double'], [
                    $text('source says '),
                    new AstNode('quoted', ['kind' => 'single'], [$text('keep')]),
                ]),
                $text(' and '),
                new AstNode('quoted', ['kind' => 'single'], [
                    new AstNode('code', ['text' => 'wp_insert_post']),
                ]),
                $text(' before '),
                new AstNode('quoted', ['kind' => 'double'], [
                    new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit')]),
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Reviewer quotes: “source says ‘keep’” and ‘`wp_insert_post`’ before “[edit](/wp-admin/post.php?post=42&action=edit)”.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer math and raw inline emission' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer formula: '),
                new AstNode('math', ['text' => 'E = mc^2', 'display' => false]),
                $text(' and raw cite '),
                new AstNode('raw_tex', ['tex' => '\cite[22-23]{smith.1899}']),
                $text(' plus markdown '),
                new AstNode('raw_inline', ['format' => 'markdown', 'text' => '*kept*']),
                $text(' and html '),
                new AstNode('raw_inline', ['format' => 'html', 'text' => '<span>drop</span>']),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Display formula: '),
                new AstNode('math', ['text' => '\alpha + \omega \times x^2', 'display' => true]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('math', ['text' => 'p', 'display' => false]),
                    $text('-Tree with '),
                    new AstNode('raw_tex', ['tex' => '\cite{tree}']),
                ]),
            ]),
        ]);

        $t->same(
            'Reviewer formula: $E = mc^2$ and raw cite \cite[22-23]{smith.1899} plus markdown *kept* and html .'
                . "\n\n" . 'Display formula: $$\alpha + \omega \times x^2$$'
                . "\n\n" . '- $p$-Tree with \cite{tree}',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer raw block emission' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Before raw reviewer block.']),
            ]),
            new AstNode('raw_tex', ['tex' => '\begin{migration-review}' . "\n" . '\item keep source citation' . "\n" . '\end{migration-review}']),
            new AstNode('raw_block', ['format' => 'markdown', 'text' => '> Raw Markdown reviewer block' . "\n" . '> with migration note.']),
            new AstNode('raw_block', ['format' => 'html', 'text' => '<aside>drop from markdown</aside>']),
            new AstNode('raw_markdown', ['text' => '::: {.review-packet}' . "\n" . 'native raw markdown block' . "\n" . ':::']),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'After raw reviewer block.']),
            ]),
        ]);

        $t->same(
            'Before raw reviewer block.'
                . "\n\n" . '\begin{migration-review}'
                . "\n" . '\item keep source citation'
                . "\n" . '\end{migration-review}'
                . "\n\n" . '> Raw Markdown reviewer block'
                . "\n" . '> with migration note.'
                . "\n\n" . '::: {.review-packet}'
                . "\n" . 'native raw markdown block'
                . "\n" . ':::'
                . "\n\n" . 'After raw reviewer block.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer markdown family raw formats' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Markdown family raw inlines: ']),
                new AstNode('raw_inline', ['format' => 'markdown_strict', 'text' => '*strict*']),
                new AstNode('text', ['text' => ', ']),
                new AstNode('raw_inline', ['format' => 'markdown_phpextra', 'text' => '[extra]{.review}']),
                new AstNode('text', ['text' => ', ']),
                new AstNode('raw_inline', ['format' => 'markdown_mmd', 'text' => '[mmd][source]']),
                new AstNode('text', ['text' => ', ']),
                new AstNode('raw_inline', ['format' => 'commonmark_x', 'text' => '~~gfm extension~~']),
                new AstNode('text', ['text' => ', and ']),
                new AstNode('raw_inline', ['format' => 'markdown+tex_math_dollars', 'text' => '$raw$']),
                new AstNode('text', ['text' => ', ']),
                new AstNode('raw_inline', ['format' => 'commonmark_x-smart', 'text' => 'raw -- dash']),
                new AstNode('text', ['text' => ', ']),
                new AstNode('raw_inline', ['format' => 'gfm+pipe_tables', 'text' => '| raw |']),
                new AstNode('text', ['text' => ', and ']),
                new AstNode('raw_inline', ['format' => 'html', 'text' => '<span>drop</span>']),
                new AstNode('text', ['text' => '.']),
            ]),
            new AstNode('raw_block', ['format' => 'markdown_strict', 'text' => '> strict raw handoff']),
            new AstNode('raw_block', ['format' => 'markdown_phpextra', 'text' => '::: {.php-extra-review}' . "\n" . 'extra raw handoff' . "\n" . ':::']),
            new AstNode('raw_block', ['format' => 'markdown_mmd', 'text' => '[source]: https://example.test/source']),
            new AstNode('raw_block', ['format' => 'commonmark_x', 'text' => '~~extension raw handoff~~']),
            new AstNode('raw_block', ['format' => 'markdown+pipe_tables', 'text' => '| raw | table |' . "\n" . '| --- | --- |']),
            new AstNode('raw_block', ['format' => 'commonmark_x-smart', 'text' => 'raw -- block']),
            new AstNode('raw_block', ['format' => 'gfm+task_lists', 'text' => '- [x] raw task']),
            new AstNode('raw_block', ['format' => 'html', 'text' => '<aside>drop</aside>']),
        ]);

        $t->same(
            'Markdown family raw inlines: *strict*, [extra]{.review}, [mmd][source], ~~gfm extension~~, and $raw$, raw -- dash, | raw |, and .'
                . "\n\n" . '> strict raw handoff'
                . "\n\n" . '::: {.php-extra-review}'
                . "\n" . 'extra raw handoff'
                . "\n" . ':::'
                . "\n\n" . '[source]: https://example.test/source'
                . "\n\n" . '~~extension raw handoff~~'
                . "\n\n" . '| raw | table |'
                . "\n" . '| --- | --- |'
                . "\n\n" . 'raw -- block'
                . "\n\n" . '- [x] raw task',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer fenced div block emission' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('div', [
                'id' => 'review-packet',
                'classes' => ['wp-import', 'needs-review'],
                'attributes' => ['data-source' => 'batch-42'],
            ], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Reviewer block with literal ::: marker.']),
                ]),
                new AstNode('blockquote', [], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Keep nested quote with packet.']),
                    ]),
                ]),
            ]),
            new AstNode('div', [], []),
        ]);

        $markdown = (new MarkdownWriter())->write($document);

        $t->same(implode("\n", [
            ':::: {#review-packet .wp-import .needs-review data-source="batch-42"}',
            'Reviewer block with literal \::: marker.',
            '',
            '> Keep nested quote with packet.',
            '::::',
            '',
            ':::',
            ':::',
        ]), $markdown);
        $t->contains('{#review-packet .wp-import .needs-review data-source="batch-42"}', $markdown);
        $t->contains('> Keep nested quote with packet.', $markdown);
    },
    'maps upstream markdown writer fenced code block attributes' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Before code handoff.']),
            ]),
            new AstNode('code_block', [
                'text' => "wp post meta get 42 source_url\n```\nliteral nested fence",
                'id' => 'review-script',
                'classes' => ['bash', 'wp-cli'],
                'attributes' => ['data-source' => 'batch-42'],
            ]),
            new AstNode('code_block', ['text' => 'plain legacy snippet']),
        ]);

        $t->same(implode("\n", [
            'Before code handoff.',
            '',
            '````{#review-script .bash .wp-cli data-source="batch-42"}',
            'wp post meta get 42 source_url',
            '```',
            'literal nested fence',
            '````',
            '',
            '    plain legacy snippet',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer code span backtick delimiters' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Reviewer tokens: ']),
                new AstNode('code', ['text' => 'wp `meta` key']),
                new AstNode('text', ['text' => ', ']),
                new AstNode('code', [
                    'text' => ' `leading and trailing` ',
                    'id' => 'review-token',
                    'classes' => ['php'],
                    'attributes' => ['data-source' => 'batch-42'],
                ]),
                new AstNode('text', ['text' => ', and ']),
                new AstNode('code', ['text' => 'plain_token']),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);

        $t->same(
            'Reviewer tokens: `` wp `meta` key ``, ``  `leading and trailing`  ``{#review-token .php data-source="batch-42"}, and `plain_token`.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer space softbreak and hard line break inlines' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Reviewer']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'packet']),
                new AstNode('softbreak'),
                new AstNode('text', ['text' => 'soft boundary']),
                new AstNode('linebreak'),
                new AstNode('text', ['text' => 'hard boundary']),
            ]),
        ]);

        $t->same(
            "Reviewer packet\nsoft boundary\\\nhard boundary",
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer nested space softbreak and hard line break inlines' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('emph', [], [
                    new AstNode('text', ['text' => 'review']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'note']),
                    new AstNode('softbreak'),
                    new AstNode('text', ['text' => 'soft continuation']),
                ]),
                new AstNode('space'),
                new AstNode('strong', [], [
                    new AstNode('text', ['text' => 'hard']),
                    new AstNode('linebreak'),
                    new AstNode('text', ['text' => 'boundary']),
                ]),
            ]),
        ]);

        $t->same(
            "*review note\nsoft continuation* **hard\\\nboundary**",
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer blockquote space softbreak and hard line break inlines' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('blockquote', [], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Reviewer']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'packet']),
                    new AstNode('softbreak'),
                    new AstNode('text', ['text' => 'soft boundary']),
                    new AstNode('linebreak'),
                    new AstNode('text', ['text' => 'hard boundary']),
                ]),
            ]),
        ]);

        $t->same(
            "> Reviewer packet\n> soft boundary\\\n> hard boundary",
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer list item space softbreak and hard line break inlines' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('text', ['text' => 'Reviewer']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'packet']),
                    new AstNode('softbreak'),
                    new AstNode('text', ['text' => 'soft boundary']),
                    new AstNode('linebreak'),
                    new AstNode('text', ['text' => 'hard boundary']),
                ]),
            ]),
            new AstNode('ordered_list', ['style' => 'decimal', 'delimiter' => 'period'], [
                new AstNode('list_item', [], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Reviewer']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'packet']),
                        new AstNode('softbreak'),
                        new AstNode('text', ['text' => 'soft boundary']),
                        new AstNode('linebreak'),
                        new AstNode('text', ['text' => 'hard boundary']),
                    ]),
                ]),
            ]),
        ]);

        $t->same(
            "- Reviewer packet\n  soft boundary\\\n  hard boundary\n\n1.  Reviewer packet\n    soft boundary\\\n    hard boundary",
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer line block emission' => static function (TestRunner $t): void {
        $nbsp = "\xC2\xA0";
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Reviewer import stanza:']),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', ['text' => 'First source line'], [
                    new AstNode('text', ['text' => 'First source line']),
                ]),
                new AstNode('line', ['text' => str_repeat($nbsp, 4) . 'indented continuation'], [
                    new AstNode('text', ['text' => str_repeat($nbsp, 4) . 'indented continuation']),
                ]),
                new AstNode('line', ['text' => '']),
                new AstNode('line', ['text' => 'Final line with *literal* marker'], [
                    new AstNode('text', ['text' => 'Final line with *literal* marker']),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            'Reviewer import stanza:',
            '| First source line'
                . "\n" . '|     indented continuation'
                . "\n" . '|'
                . "\n" . '| Final line with \*literal\* marker',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer pipe table alignment widths and captions' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'Migration **review** packet',
                'captionInlines' => [
                    new AstNode('text', ['text' => 'Migration ']),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'review'])]),
                    new AstNode('text', ['text' => ' packet']),
                ],
                'alignments' => ['right', 'left', 'center'],
                'widths' => [0.15, 0.25, 0.35],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                        new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                        new AstNode('table_cell', ['text' => 'Review note'], [new AstNode('text', ['text' => 'Review note'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                        new AstNode('table_cell', ['text' => 'ready'], [new AstNode('text', ['text' => 'ready'])]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'source | audit']),
                        ]),
                    ]),
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                        new AstNode('table_cell', ['text' => 'needs-review'], [new AstNode('text', ['text' => 'needs-review'])]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'soft line one']),
                            new AstNode('softbreak'),
                            new AstNode('text', ['text' => 'soft line two']),
                        ]),
                    ]),
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => '3'], [new AstNode('text', ['text' => '3'])]),
                        new AstNode('table_cell', ['text' => 'blocked'], [new AstNode('text', ['text' => 'blocked'])]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'hard boundary']),
                            new AstNode('linebreak'),
                            new AstNode('text', ['text' => 'follow-up required']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '|  Posts | Status       |              Review note              |',
            '|-----:|:-----------|:-----------------------------------:|',
            '|     42 | ready        |            source \\| audit            |',
            '|      7 | needs-review |   soft line one<br />soft line two    |',
            '|      3 | blocked      | hard boundary<br />follow-up required |',
            '',
            ': Migration **review** packet',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer table short captions' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'long caption',
                'captionInlines' => [
                    new AstNode('text', ['text' => 'long ']),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'caption'])]),
                ],
                'shortCaption' => 'short caption',
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'short ']),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'caption'])]),
                ],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Column'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'value'])]),
                    ]),
                ]),
            ]),
            new AstNode('table', [
                'caption' => 'fallback long',
                'shortCaption' => 'fallback [short]',
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'A'])]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| Column |',
            '|------|',
            '| value  |',
            '',
            ': [short **caption**] long *caption*',
            '',
            '| A   |',
            '|---|',
            '',
            ': [fallback \\[short\\]] fallback long',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer short only table captions without dangling long caption space' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Review ']),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'queue'])]),
                ],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Item'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'State'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'media'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'queued'])]),
                    ]),
                ]),
            ]),
            new AstNode('table', [
                'shortCaption' => 'fallback [queue]',
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'A'])]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '| Item  | State  |',
                '|-----|------|',
                '| media | queued |',
                '',
                ': [Review **queue**]',
            ]),
            implode("\n", [
                '| A   |',
                '|---|',
                '',
                ': [fallback \\[queue\\]]',
            ]),
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer definition lists with multiple block bodies' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
        $term = static fn (array $children, string $plain): AstNode => new AstNode('term', ['text' => $plain], $children);

        $document = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                new AstNode('definition_item', ['term' => 'apple'], [
                    $term([
                        new AstNode('emph', [], [$text('apple')]),
                    ], 'apple'),
                    new AstNode('definition', ['loose' => false], [
                        $paragraph('red fruit'),
                        $paragraph('contains seeds, crisp, pleasant to taste'),
                    ]),
                ]),
                new AstNode('definition_item', ['term' => 'orange'], [
                    $term([$text('orange')], 'orange'),
                    new AstNode('definition', ['loose' => true], [
                        $paragraph('orange fruit'),
                        new AstNode('code_block', ['text' => '{ orange code block }']),
                        new AstNode('blockquote', [], [
                            $paragraph('orange block quote'),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '*apple*',
            ':   red fruit',
            '',
            '    contains seeds, crisp, pleasant to taste',
            '',
            'orange',
            ':   orange fruit',
            '',
            '        { orange code block }',
            '',
            '    > orange block quote',
            '',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer alternate definition markers to canonical colon output' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Source glossary',
            '',
            '  ~ Preserve alternate marker notes from older Pandoc exports.',
            '',
            '  ~ Verify nested review tasks',
            '',
            '    1. Confirm block conversion',
            '    2. Attach media IDs',
        ]));

        $t->same(implode("\n", [
            'Source glossary',
            ':   Preserve alternate marker notes from older Pandoc exports.',
            '',
            ':   Verify nested review tasks',
            '',
            '    1.  Confirm block conversion',
            '    2.  Attach media IDs',
            '',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer table span degradation to rectangular pipe rows' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'captionInlines' => [
                    new AstNode('text', ['text' => 'Grid span review']),
                ],
                'alignments' => ['left', 'center', 'right'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', ['colspan' => 2], [
                            new AstNode('text', ['text' => 'Review scope']),
                        ]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'Status']),
                        ]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['rowspan' => 2], [
                            new AstNode('text', ['text' => 'Media audit']),
                        ]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'posts | pages']),
                        ]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'ready']),
                        ]),
                    ]),
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'attachments']),
                        ]),
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'blocked']),
                        ]),
                    ]),
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['colspan' => 3], [
                            new AstNode('text', ['text' => 'Reviewer note across all columns']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| Review scope                     |                |  Status |',
            '|:-------------------------------|:------------:|------:|',
            '| Media audit                      | posts \\| pages |   ready |',
            '|                                  |  attachments   | blocked |',
            '| Reviewer note across all columns |                |         |',
            '',
            ': Grid span review',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer multi block table cell fallback safely inside pipe rows' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'alignments' => ['left', 'left'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Source'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Nested review'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'legacy import'])]),
                        new AstNode('table_cell', [], [
                            new AstNode('paragraph', [], [
                                new AstNode('text', ['text' => 'Reviewer note']),
                            ]),
                            new AstNode('table', [
                                'alignments' => ['left', 'right'],
                            ], [
                                new AstNode('table_head', [], [
                                    new AstNode('table_row', ['header' => true], [
                                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Field'])]),
                                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Count'])]),
                                    ]),
                                ]),
                                new AstNode('table_body', [], [
                                    new AstNode('table_row', [], [
                                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'posts | pages'])]),
                                        new AstNode('table_cell', [], [new AstNode('text', ['text' => '42'])]),
                                    ]),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| Source        | Nested review                                                                                                            |',
            '|:------------|:-----------------------------------------------------------------------------------------------------------------------|',
            '| legacy import | Reviewer note<br /><br />\\| Field          \\| Count \\|<br />\\|:-------------\\|----:\\|<br />\\| posts \\| pages \\|    42 \\| |',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer top level list code and delimiter spacing' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
        $writer = new MarkdownWriter();

        $listThenCode = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 1], [
                new AstNode('list_item', [], [
                    $paragraph('one'),
                    $paragraph('two'),
                ]),
            ]),
            new AstNode('code_block', ['text' => 'test']),
        ]);
        $tightSublist = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    $text('foo'),
                    new AstNode('bullet_list', [], [
                        new AstNode('list_item', [], [$text('bar')]),
                    ]),
                ]),
                new AstNode('list_item', [], [$text('baz')]),
            ]),
        ]);
        $emphStrongSpacing = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('emph', [], [
                    $text('f'),
                    new AstNode('strong', [], [$text(' d ')]),
                ]),
                $text('l'),
            ]),
        ]);

        $t->same(implode("\n", [
            '1.  one',
            '',
            '    two',
            '',
            '<!-- -->',
            '',
            '    test',
        ]), $writer->write($listThenCode));
        $t->same("- foo\n  - bar\n- baz", $writer->write($tightSublist));
        $t->same('*f **d*** l', $writer->write($emphStrongSpacing));
    },
    'maps upstream markdown reader more indented code at beginning of list items' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '-     code',
            '      code',
            '',
            '  1.     code',
            '         code',
            '',
            '  12345678.     code',
            '                code',
            '',
            '  -     code',
            '        code',
            '',
            '  -    no code',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];
        $item = $list->children[0];
        $ordered = $item->children[1];
        $nestedBullets = $item->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('bullet_list', $list->type);
        $t->same('code_block', $item->children[0]->type);
        $t->same("code\ncode", $item->children[0]->attr('text'));
        $t->same('ordered_list', $ordered->type);
        $t->same(1, $ordered->attr('start'));
        $t->same(12345678, $ordered->children[1]->attr('number'));
        $t->same('code_block', $ordered->children[0]->children[0]->type);
        $t->same("code\ncode", $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $nestedBullets->type);
        $t->same('code_block', $nestedBullets->children[0]->children[0]->type);
        $t->same('paragraph', $nestedBullets->children[1]->children[0]->type);
        $t->same('no code', $nestedBullets->children[1]->children[0]->attr('text'));
        $t->contains('<pre class="wp-block-code"><code>code' . "\n" . 'code</code></pre><ol>', $blocks);
        $t->contains('<li><p>no code</p></li>', $blocks);
    },
    'maps upstream markdown list item containing raw html blocks' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            ' -  <div>',
            '    first div breaks',
            '    </div>',
            '',
            '    <button>if this button exists</button>',
            '',
            '    <div>',
            '    with this div too.',
            '    </div>',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];
        $item = $list->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('bullet_list', $list->type);
        $t->same(1, count($list->children));
        $t->same(['div', 'raw_html', 'plain', 'raw_html', 'div'], array_map(static fn (AstNode $node): string => $node->type, $item->children));
        $t->same('first div breaks', $item->children[0]->children[0]->attr('text'));
        $t->same('<button>', $item->children[1]->attr('html'));
        $t->same('if this button exists', $item->children[2]->attr('text'));
        $t->same('</button>', $item->children[3]->attr('html'));
        $t->same('with this div too.', $item->children[4]->children[0]->attr('text'));
        $t->contains('<ul><li><div><p>first div breaks</p></div><button>if this button exists</button><div><p>with this div too.</p></div></li></ul>', $blocks);
    },
    'maps upstream testsuite list continuation lines indented with tabs and spaces' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            "+\tthis is a list item",
            "\tindented with tabs",
            '',
            '+   this is a list item',
            '    indented with spaces',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];

        $t->same('bullet_list', $list->type);
        $t->true((bool) $list->attr('loose'));
        $t->same('this is a list item indented with tabs', $list->children[0]->children[0]->attr('text'));
        $t->same('this is a list item indented with spaces', $list->children[1]->children[0]->attr('text'));
    },
    'maps upstream testsuite loose nested list paragraph shape' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("1. First\n\n2. Second:\n\n\t* Fee\n\t* Fie\n\t* Foe\n\n3. Third");
        $list = $document->children[0];
        $secondItem = $list->children[1];
        $nested = $secondItem->children[1];

        $t->same('ordered_list', $list->type);
        $t->same('paragraph', $secondItem->children[0]->type);
        $t->same('Second:', $secondItem->children[0]->attr('text'));
        $t->same('bullet_list', $nested->type);
        $t->same('Fee', $nested->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $list->children[2]->children[0]->type);
        $t->same('Third', $list->children[2]->children[0]->attr('text'));
    },
    'maps upstream testsuite parenthesized decimal roman and alpha list markers' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '(2) begins with 2',
            '(3) and now 3',
            '',
            '    with a continuation',
            '',
            '    iv. sublist with roman numerals,',
            '        starting with 4',
            '    v.  more items',
            '        (A)  a subsublist',
            '        (B)  a subsublist',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];
        $secondItem = $list->children[1];
        $roman = $secondItem->children[2];
        $alpha = $roman->children[1]->children[1];

        $t->same('ordered_list', $list->type);
        $t->same(2, $list->attr('start'));
        $t->same('decimal', $list->attr('style'));
        $t->same('two_parens', $list->attr('delimiter'));
        $t->same('paragraph', $secondItem->children[0]->type);
        $t->same('with a continuation', $secondItem->children[1]->attr('text'));
        $t->same(4, $roman->attr('start'));
        $t->same('lower_roman', $roman->attr('style'));
        $t->same('sublist with roman numerals, starting with 4', $roman->children[0]->children[0]->attr('text'));
        $t->same('upper_alpha', $alpha->attr('style'));
        $t->same('a subsublist', $alpha->children[1]->children[0]->attr('text'));
    },
    'maps upstream testsuite nested upper alpha upper roman decimal and lower alpha markers' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("A.  Upper Alpha\n    I.  Upper Roman.\n        (6) Decimal start with 6\n            c)  Lower alpha with paren");
        $alpha = $document->children[0];
        $roman = $alpha->children[0]->children[1];
        $decimal = $roman->children[0]->children[1];
        $lowerAlpha = $decimal->children[0]->children[1];

        $t->same('ordered_list', $alpha->type);
        $t->same('upper_alpha', $alpha->attr('style'));
        $t->same(1, $alpha->attr('start'));
        $t->same('upper_roman', $roman->attr('style'));
        $t->same(1, $roman->attr('start'));
        $t->same('decimal', $decimal->attr('style'));
        $t->same(6, $decimal->attr('start'));
        $t->same('lower_alpha', $lowerAlpha->attr('style'));
        $t->same(3, $lowerAlpha->attr('start'));
        $t->same('Lower alpha with paren', $lowerAlpha->children[0]->children[0]->attr('text'));
    },
    'maps upstream testsuite autonumbered list markers and nested autonumbering' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(" #.  Autonumber.\n #.  More.\n     #.  Nested.");
        $list = $document->children[0];
        $nested = $list->children[1]->children[1];

        $t->same('ordered_list', $list->type);
        $t->same(1, $list->attr('start'));
        $t->same('default', $list->attr('style'));
        $t->same('default', $list->attr('delimiter'));
        $t->same('More.', $list->children[1]->children[0]->attr('text'));
        $t->same('ordered_list', $nested->type);
        $t->same('default', $nested->attr('style'));
        $t->same('Nested.', $nested->children[0]->children[0]->attr('text'));
    },
    'maps upstream markdown reader more references curly quotes and consecutive lists' => static function (TestRunner $t): void {
        $markdown = implode("\n\n", [
            "## Case-insensitive references\n\n[Fum]\n\n[FUM]\n\n[bat]\n\n[fum]: /fum\n[BAT]: /bat",
            "## Curly smart quotes\n\n“Hi”\n\n‘Hi’",
            "## Consecutive lists\n\n- one\n- two\n1. one\n2. two\n\n a. one\n b. two",
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $referenceOne = $document->children[1]->children[0];
        $referenceTwo = $document->children[2]->children[0];
        $referenceThree = $document->children[3]->children[0];
        $curlyDouble = $document->children[5]->children[0];
        $curlySingle = $document->children[6]->children[0];
        $bulletList = $document->children[8];
        $decimalList = $document->children[9];
        $alphaList = $document->children[10];
        $guard = (new MarkdownReader())->read("B. Williams\n\nM.A. 2007");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $referenceOne->type);
        $t->same('/fum', $referenceOne->attr('url'));
        $t->same('Fum', $referenceOne->children[0]->attr('text'));
        $t->same('/fum', $referenceTwo->attr('url'));
        $t->same('FUM', $referenceTwo->children[0]->attr('text'));
        $t->same('/bat', $referenceThree->attr('url'));
        $t->same('bat', $referenceThree->children[0]->attr('text'));
        $t->same('text', $curlyDouble->type);
        $t->same('“Hi”', $curlyDouble->attr('text'));
        $t->same('text', $curlySingle->type);
        $t->same('‘Hi’', $curlySingle->attr('text'));
        $t->same('bullet_list', $bulletList->type);
        $t->same(2, count($bulletList->children));
        $t->same('ordered_list', $decimalList->type);
        $t->same('decimal', $decimalList->attr('style'));
        $t->same(2, count($decimalList->children));
        $t->same('ordered_list', $alphaList->type);
        $t->same('lower_alpha', $alphaList->attr('style'));
        $t->same(1, $alphaList->attr('start'));
        $t->same('one', $alphaList->children[0]->children[0]->attr('text'));
        $t->same('two', $alphaList->children[1]->children[0]->attr('text'));
        $t->same('paragraph', $guard->children[0]->type);
        $t->same('B. Williams', $guard->children[0]->attr('text'));
        $t->same('paragraph', $guard->children[1]->type);
        $t->same('M.A. 2007', $guard->children[1]->attr('text'));
        $t->contains('<p><a href="/fum">Fum</a></p>', $blocks);
        $t->contains('<p>“Hi”</p>', $blocks);
        $t->contains('<ul><li>one</li><li>two</li></ul>', $blocks);
        $t->contains('<ol><li>one</li><li>two</li></ol>', $blocks);
        $t->contains('<ol type="a"><li>one</li><li>two</li></ol>', $blocks);
    },
    'maps upstream markdown definition lists without blank space' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  :  bar\n\nfoo2\n  : bar2\n  : bar3\n");
        $list = $document->children[0];

        $t->same('definition_list', $list->type);
        $t->same('foo1', $list->children[0]->attr('term'));
        $t->same('bar', $list->children[0]->children[1]->children[0]->attr('text'));
        $t->same('foo2', $list->children[1]->attr('term'));
        $t->same('bar2', $list->children[1]->children[1]->children[0]->attr('text'));
        $t->same('bar3', $list->children[1]->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown definition marker at column zero' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo\n: bar\n");
        $list = $document->children[0];

        $t->same('definition_list', $list->type);
        $t->same('foo', $list->children[0]->children[0]->attr('text'));
        $t->same('bar', $list->children[0]->children[1]->children[0]->attr('text'));
    },
    'maps upstream markdown loose first definition paragraph' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n\n  :  bar\n\nfoo2\n\n  : bar2\n  : bar3\n");
        $firstDefinition = $document->children[0]->children[0]->children[1];
        $secondItem = $document->children[0]->children[1];

        $t->same('definition', $firstDefinition->type);
        $t->true((bool) $firstDefinition->attr('loose'));
        $t->same('bar', $firstDefinition->children[0]->attr('text'));
        $t->same(false, (bool) $secondItem->children[1]->attr('loose'));
        $t->same('bar2', $secondItem->children[1]->children[0]->attr('text'));
        $t->true((bool) $secondItem->children[2]->attr('loose'));
        $t->same('bar3', $secondItem->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown lazy definition continuations' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  :  bar\nbaz\n  : bar2\n");
        $item = $document->children[0]->children[0];

        $t->same('definition_item', $item->type);
        $t->same(3, count($item->children));
        $t->same('bar baz', $item->children[1]->children[0]->attr('text'));
        $t->same('bar2', $item->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown paragraph continuation inside definition' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  : bar\n\n    baz\n");
        $definition = $document->children[0]->children[0]->children[1];

        $t->same('definition', $definition->type);
        $t->same(2, count($definition->children));
        $t->same('bar', $definition->children[0]->attr('text'));
        $t->same('baz', $definition->children[1]->attr('text'));
    },
    'maps upstream markdown blank before second definition' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo1\n  :  bar\n\nfoo2\n  : bar2\n\n  : bar3\n");
        $secondItem = $document->children[0]->children[1];

        $t->same('foo2', $secondItem->attr('term'));
        $t->same(3, count($secondItem->children));
        $t->same('bar2', $secondItem->children[1]->children[0]->attr('text'));
        $t->same('bar3', $secondItem->children[2]->children[0]->attr('text'));
    },
    'maps upstream markdown list inside definition' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("foo\n:   - bar\n");
        $definition = $document->children[0]->children[0]->children[1];

        $t->same('definition', $definition->type);
        $t->same('bullet_list', $definition->children[0]->type);
        $t->same('bar', $definition->children[0]->children[0]->children[0]->attr('text'));
    },
    'maps upstream markdown definition list inside html div' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("<div>foo\n:   - bar\n</div>");
        $div = $document->children[0];
        $list = $div->children[0];

        $t->same('div', $div->type);
        $t->same('definition_list', $list->type);
        $t->same('foo', $list->children[0]->attr('term'));
        $t->same('bullet_list', $list->children[0]->children[1]->children[0]->type);
        $t->same('bar', $list->children[0]->children[1]->children[0]->children[0]->children[0]->attr('text'));
    },
    'maps upstream testsuite definition lists with multiple block bodies' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '*apple*',
            '',
            ':   red fruit',
            '',
            '    contains seeds,',
            '    crisp, pleasant to taste',
            '',
            '*orange*',
            '',
            ':   orange fruit',
            '',
            '        { orange code block }',
            '',
            '    > orange block quote',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];
        $apple = $list->children[0];
        $orangeDefinition = $list->children[1]->children[1];

        $t->same('definition_list', $list->type);
        $t->same('emph', $apple->children[0]->children[0]->type);
        $t->same('apple', $apple->children[0]->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $apple->children[1]->children[0]->type);
        $t->same('red fruit', $apple->children[1]->children[0]->attr('text'));
        $t->same('contains seeds, crisp, pleasant to taste', $apple->children[1]->children[1]->attr('text'));
        $t->same('paragraph', $orangeDefinition->children[0]->type);
        $t->same('code_block', $orangeDefinition->children[1]->type);
        $t->same('{ orange code block }', $orangeDefinition->children[1]->attr('text'));
        $t->same('blockquote', $orangeDefinition->children[2]->type);
        $t->same('orange block quote', $orangeDefinition->children[2]->children[0]->attr('text'));
    },
    'maps upstream testsuite alternate definition markers with nested lists' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            'apple',
            '',
            '  ~ red fruit',
            '',
            '  ~ computer',
            '',
            'orange',
            '',
            '  ~ orange fruit',
            '',
            '    1. sublist',
            '    2. sublist',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $list = $document->children[0];
        $apple = $list->children[0];
        $orangeDefinition = $list->children[1]->children[1];
        $sublist = $orangeDefinition->children[1];

        $t->same('definition_list', $list->type);
        $t->same('apple', $apple->attr('term'));
        $t->true((bool) $apple->children[1]->attr('loose'));
        $t->true((bool) $apple->children[2]->attr('loose'));
        $t->same('computer', $apple->children[2]->children[0]->attr('text'));
        $t->true((bool) $orangeDefinition->attr('loose'));
        $t->same('orange fruit', $orangeDefinition->children[0]->attr('text'));
        $t->same('ordered_list', $sublist->type);
        $t->same(1, $sublist->attr('start'));
        $t->same('sublist', $sublist->children[1]->children[0]->attr('text'));
    },
    'maps upstream testsuite simple block quote paragraphs' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("> This is a block quote.\n> It is pretty short.");
        $quote = $document->children[0];

        $t->same('blockquote', $quote->type);
        $t->same('paragraph', $quote->children[0]->type);
        $t->same('This is a block quote. It is pretty short.', $quote->children[0]->attr('text'));
    },
    'maps upstream testsuite block quote with code list and nested quotes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("> Code in a block quote:\n> \n>     sub status {\n>         print \"working\";\n>     }\n> \n> A list:\n> \n> 1. item one\n> 2. item two\n>\n> Nested block quotes:\n>\n> > nested\n>\n>>  nested\n>");
        $quote = $document->children[0];

        $t->same('blockquote', $quote->type);
        $t->same('paragraph', $quote->children[0]->type);
        $t->same('code_block', $quote->children[1]->type);
        $t->same("sub status {\n    print \"working\";\n}", $quote->children[1]->attr('text'));
        $t->same('ordered_list', $quote->children[3]->type);
        $t->same('item two', $quote->children[3]->children[1]->attr('text'));
        $t->same('blockquote', $quote->children[5]->type);
        $t->same('nested', $quote->children[5]->children[0]->attr('text'));
        $t->same('blockquote', $quote->children[6]->type);
        $t->same('nested', $quote->children[6]->children[0]->attr('text'));
    },
    'keeps upstream testsuite lazy quote marker inside paragraph' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("This should not be a block quote: 2\n> 1.");
        $paragraph = $document->children[0];

        $t->same(1, count($document->children));
        $t->same('paragraph', $paragraph->type);
        $t->same('This should not be a block quote: 2 > 1.', $paragraph->attr('text'));
    },
    'maps upstream testsuite html blocks as div containers' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '<div>foo</div>',
            '',
            '<div>',
            '<div>',
            '<div>',
            'foo',
            '</div>',
            '</div>',
            '<div>bar</div>',
            '</div>',
            '',
            '<div>',
            'foo',
            '</div>',
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $oneLine = $document->children[0];
        $nested = $document->children[1];
        $multiLine = $document->children[2];

        $t->same('div', $oneLine->type);
        $t->same('plain', $oneLine->children[0]->type);
        $t->same('foo', $oneLine->children[0]->attr('text'));
        $t->same('div', $nested->children[0]->type);
        $t->same('div', $nested->children[0]->children[0]->type);
        $t->same('paragraph', $nested->children[0]->children[0]->children[0]->type);
        $t->same('foo', $nested->children[0]->children[0]->children[0]->attr('text'));
        $t->same('plain', $nested->children[1]->children[0]->type);
        $t->same('bar', $nested->children[1]->children[0]->attr('text'));
        $t->same('paragraph', $multiLine->children[0]->type);
    },
    'maps upstream testsuite raw table and script html blocks' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '<table>',
            '<tr>',
            '<td>This is *emphasized*</td>',
            '<td>And this is **strong**</td>',
            '</tr>',
            '</table>',
            '',
            "<script type=\"text/javascript\">document.write('This *should not* be interpreted as markdown');</script>",
        ]);
        $document = (new MarkdownReader())->read($markdown);
        $table = $document->children[0];
        $script = $document->children[1];

        $t->same('raw_html', $table->type);
        $t->contains('<td>This is <em>emphasized</em></td>', $table->attr('html'));
        $t->contains('<td>And this is <strong>strong</strong></td>', $table->attr('html'));
        $t->same('raw_html', $script->type);
        $t->contains('*should not*', $script->attr('html'));
        $t->same(false, str_contains($script->attr('html'), '<em>should not</em>'));
    },
    'maps upstream command nested html tables into table cell block children' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
 <tr>
  <td>
   <table>  <tr> <td> a1 </td> <td> a2 </td> </tr>  </table>
  </td>
  <td>b</td>
 </tr>
 <tr>
   <td>c</td> <td>d </td>
 </tr>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $body = $table->children[1];
        $nested = $body->children[0]->children[0]->children[0];

        $t->same(1, count($document->children));
        $t->same('table', $table->type);
        $t->same([], $table->children[0]->children);
        $t->same([0.5, 0.5], $table->attr('widths'));
        $t->same('table', $nested->type);
        $t->same('a1', $nested->children[1]->children[0]->children[0]->attr('text'));
        $t->same('a2', $nested->children[1]->children[0]->children[1]->attr('text'));
        $t->same('b', $body->children[0]->children[1]->attr('text'));
        $t->same('c', $body->children[1]->children[0]->attr('text'));
        $t->same('d', $body->children[1]->children[1]->attr('text'));
    },
    'maps upstream command full html document with third-level nested table' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title> NestedTables </title>
</head>
<body>
<table>
 <tr>
  <td>
    <table>  <tr>
	<td> a1 </td>
	<td>
	  <table>  <tr> <td> 1 </td> <td> 2 </td> </tr>  </table>
	</td>
    </tr>  </table>
  </td>
  <td>b</td>
 </tr>
 <tr>
   <td>c</td> <td>d </td>
 </tr>
</table>
</body>
</html>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $body = $table->children[1];
        $middle = $body->children[0]->children[0]->children[0];
        $inner = $middle->children[1]->children[0]->children[1]->children[0];

        $t->same(1, count($document->children));
        $t->same('table', $table->type);
        $t->same([0.5, 0.5], $table->attr('widths'));
        $t->same('table', $middle->type);
        $t->same('a1', $middle->children[1]->children[0]->children[0]->attr('text'));
        $t->same('table', $inner->type);
        $t->same('1', $inner->children[1]->children[0]->children[0]->attr('text'));
        $t->same('2', $inner->children[1]->children[0]->children[1]->attr('text'));
        $t->same('b', $body->children[0]->children[1]->attr('text'));
        $t->same('c', $body->children[1]->children[0]->attr('text'));
        $t->same('d', $body->children[1]->children[1]->attr('text'));
    },
    'writes wordpress nested html tables inside table cells for legacy imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Nested import table:</p>', $blocks);
        $t->contains('<td><table><colgroup><col style="width:50%"/><col style="width:50%"/></colgroup><tbody><tr><td>Inner posts</td><td>42</td></tr></tbody></table></td><td>Batch status</td>', $blocks);
        $t->contains('<tr><td>Reviewer</td><td>Ready</td></tr>', $blocks);
    },
    'writes wordpress third-level nested html tables without asciidoc downgrade' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Deep nested import table:</p>', $blocks);
        $t->contains('<td><table><colgroup><col style="width:50%"/><col style="width:50%"/></colgroup><tbody><tr><td>Outer note</td><td><table><colgroup><col style="width:50%"/><col style="width:50%"/></colgroup><tbody><tr><td>Inner posts</td><td>42</td></tr></tbody></table></td></tr></tbody></table></td><td>Batch status</td>', $blocks);
    },
    'maps upstream html table caption colgroup thead and tfoot structure' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="nordics" data-source="wikipedia">
<caption><p>States belonging to the <em>Nordics.</em></p></caption>
<colgroup>
<col style="width: 30%" />
<col style="width: 30%" />
<col style="width: 20%" />
<col style="width: 20%" />
</colgroup>
<thead class="simple-head">
<tr>
<th style="text-align: center;">Name</th>
<th style="text-align: center;">Capital</th>
<th style="text-align: center;">Population<br />
(in 2018)</th>
<th style="text-align: center;">Area<br />
(in km<sup>2</sup>)</th>
</tr>
</thead>
<tbody class="souvereign-states">
<tr class="country">
<th style="text-align: center;">Denmark</th>
<td style="text-align: left;">Copenhagen</td>
<td style="text-align: left;">5,809,502</td>
<td style="text-align: left;">43,094</td>
</tr>
<tr class="country">
<th style="text-align: center;">Finland</th>
<td style="text-align: left;">Helsinki</td>
<td style="text-align: left;">5,537,364</td>
<td style="text-align: left;">338,145</td>
</tr>
<tr class="country">
<th style="text-align: center;">Iceland</th>
<td style="text-align: left;">Reykjavik</td>
<td style="text-align: left;">343,518</td>
<td style="text-align: left;">103,000</td>
</tr>
<tr class="country">
<th style="text-align: center;">Norway</th>
<td style="text-align: left;">Oslo</td>
<td style="text-align: left;">5,372,191</td>
<td style="text-align: left;">323,802</td>
</tr>
<tr class="country">
<th style="text-align: center;">Sweden</th>
<td style="text-align: left;">Stockholm</td>
<td style="text-align: left;">10,313,447</td>
<td style="text-align: left;">450,295</td>
</tr>
</tbody><tfoot>
<tr id="summary">
<td style="text-align: center;">Total</td>
<td style="text-align: left;"></td>
<td id="total-population" style="text-align: left;">27,376,022</td>
<td id="total-area" style="text-align: left;">1,258,336</td>
</tr>
</tfoot>

</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $captionInlines = $table->attr('captionInlines');
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];
        $areaHeader = $head->children[0]->children[3];

        $t->same(1, count($document->children));
        $t->same('table', $table->type);
        $t->same('States belonging to the Nordics.', $table->attr('caption'));
        $t->same([0.3, 0.3, 0.2, 0.2], $table->attr('widths'));
        $t->same(true, is_array($captionInlines));
        $t->same('States belonging to the ', $captionInlines[0]->attr('text'));
        $t->same('emph', $captionInlines[1]->type);
        $t->same('Nordics.', $captionInlines[1]->children[0]->attr('text'));
        $t->same('table_head', $head->type);
        $t->same('table_body', $body->type);
        $t->same('table_foot', $foot->type);
        $t->same(5, count($body->children));
        $t->same('center', $head->children[0]->children[0]->attr('align'));
        $t->same(true, $body->children[0]->children[0]->attr('header'));
        $t->same('Denmark', $body->children[0]->children[0]->attr('text'));
        $t->same('Copenhagen', $body->children[0]->children[1]->attr('text'));
        $t->same(['text', 'linebreak', 'text', 'superscript', 'text'], array_map(static fn ($node): string => $node->type, $areaHeader->children));
        $t->same('2', $areaHeader->children[3]->children[0]->attr('text'));
        $t->same('Total', $foot->children[0]->children[0]->attr('text'));
        $t->same('27,376,022', $foot->children[0]->children[2]->attr('text'));
    },
    'maps upstream html reader document metadata headers and paragraphs' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="Content-Style-Type" content="text/css" />
<meta name="generator" content="pandoc" />
<title>Pandoc Test Suite</title>
</head>
<body>
<h1 class="title">Pandoc Test Suite</h1>
<p>This is a set of tests for pandoc. Most of them are adapted from John Gruber's markdown test suite.</p>
<hr />
<h1>Headers</h1>
<h2>Level 2 with an <a href="/url">embedded link</a></h2>
<h3>Level 3 with <em>emphasis</em></h3>
<h4>Level 4</h4>
<h5>Level 5</h5>
<h1>Level 1</h1>
<h2>Level 2 with <em>emphasis</em></h2>
<h3>Level 3</h3>
<p>with no blank line</p>
<h2>Level 2</h2>
<p>with no blank line</p>
<hr />
<h1>Paragraphs</h1>
<p>Here's a regular paragraph.</p>
<p>In Markdown 1.0.0 and earlier. Version 8. This line turns into a list item. Because a hard-wrapped line in the middle of a paragraph looked like a list item.</p>
<p>Here's one with a bullet. * criminey.</p>
<p>There should be a hard line break<br />
 here.</p>
<hr />
</body>
</html>
HTML);
        $meta = $document->attr('meta');
        $title = $document->children[0];
        $embeddedLinkHeading = $document->children[4];
        $emphasisHeading = $document->children[5];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(21, count($document->children));
        $t->same(['title' => 'Pandoc Test Suite', 'generator' => 'pandoc'], $meta);
        $t->same('heading', $title->type);
        $t->same(1, $title->attr('level'));
        $t->same('pandoc-test-suite', $title->attr('id'));
        $t->same(['title'], $title->attr('classes'));
        $t->same(['class' => 'title'], $title->attr('htmlAttributes'));
        $t->same('link', $embeddedLinkHeading->children[1]->type);
        $t->same('/url', $embeddedLinkHeading->children[1]->attr('url'));
        $t->same('emph', $emphasisHeading->children[1]->type);
        $t->same('paragraph', $document->children[11]->type);
        $t->same('with no blank line', $document->children[11]->attr('text'));
        $t->same("There should be a hard line break\nhere.", $document->children[19]->attr('text'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $document->children[19]->children));
        $t->contains('<h1 id="pandoc-test-suite" class="title">Pandoc Test Suite</h1>', $blocks);
        $t->contains('<h2 id="level-2-with-an-embedded-link">Level 2 with an <a href="/url">embedded link</a></h2>', $blocks);
        $t->contains('<p>Here&#039;s one with a bullet. * criminey.</p>', $blocks);
    },
    'maps upstream html reader hard line breaks in paragraphs' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p>There should be a hard line break<br />
 here.</p>
HTML);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('paragraph', $paragraph->type);
        $t->same("There should be a hard line break\nhere.", $paragraph->attr('text'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('There should be a hard line break', $paragraph->children[0]->attr('text'));
        $t->same('here.', $paragraph->children[2]->attr('text'));
        $t->contains('<p>There should be a hard line break<br/>here.</p>', $blocks);
    },
    'maps upstream html reader standalone br fragments to linebreaks' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $source = implode("\n", [
            '<br/>',
            '<br> tail',
            '<br class="classic-break">',
        ]);
        $document = $reader->read($source);
        $firstBreak = $document->children[0]->children[0] ?? new AstNode('missing');
        $secondBreak = $document->children[1]->children[0] ?? new AstNode('missing');
        $secondTail = $document->children[1]->children[1] ?? new AstNode('missing');
        $thirdBreak = $document->children[2]->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('linebreak', $firstBreak->type);
        $t->same('linebreak', $secondBreak->type);
        $t->same('tail', $secondTail->attr('text'));
        $t->same('linebreak', $thirdBreak->type);
        $t->contains('<p><br/></p>', $blocks);
        $t->contains('<p><br/>tail</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-linebreak.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same(2, count($fixtureDocument->children));
        $t->same('linebreak', $fixtureDocument->children[0]->children[0]->type);
        $t->same('linebreak', $fixtureDocument->children[1]->children[0]->type);
        $t->contains('<p><br/></p>', $fixtureBlocks);
        $t->contains('<p><br/>Manual classic-editor break before reviewer note</p>', $fixtureBlocks);
    },
    'maps upstream html reader inline q cite as quoted spans' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p>Normal text but then a <q cite="https://www.imdb.com/title/tt0062622/quotes/qt0396921">inline quote</q>.</p>
<p><q>Missing a cite attribute means its just normal text</q></p>
HTML);
        $quotedWithCite = $document->children[0]->children[1];
        $span = $quotedWithCite->children[0];
        $plainQuoted = $document->children[1]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('quoted', $quotedWithCite->type);
        $t->same('double', $quotedWithCite->attr('kind'));
        $t->same('span', $span->type);
        $t->same(['cite' => 'https://www.imdb.com/title/tt0062622/quotes/qt0396921'], $span->attr('attributes'));
        $t->same('inline quote', $span->children[0]->attr('text'));
        $t->same('quoted', $plainQuoted->type);
        $t->same('Missing a cite attribute means its just normal text', $plainQuoted->children[0]->attr('text'));
        $t->contains('<p>Normal text but then a “<span cite="https://www.imdb.com/title/tt0062622/quotes/qt0396921">inline quote</span>”.</p>', $blocks);
        $t->contains('<p>“Missing a cite attribute means its just normal text”</p>', $blocks);
    },
    'maps upstream html reader small caps underline and strikeout inlines' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p>This is <span style="font-variant: small-caps;">small caps</span>.</p>
<p>These are all underlined: <u>foo</u> and <ins>bar</ins>.</p>
<p>These are all strikethrough: <s>foo</s>, <strike>bar</strike>, and <del>baz</del>.</p>
HTML);
        $smallCaps = $document->children[0]->children[1];
        $underlined = $document->children[1];
        $struck = $document->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, count($document->children));
        $t->same('small_caps', $smallCaps->type);
        $t->same('small caps', $smallCaps->children[0]->attr('text'));
        $t->same('underline', $underlined->children[1]->type);
        $t->same('foo', $underlined->children[1]->children[0]->attr('text'));
        $t->same('underline', $underlined->children[3]->type);
        $t->same('bar', $underlined->children[3]->children[0]->attr('text'));
        $t->same('strikeout', $struck->children[1]->type);
        $t->same('foo', $struck->children[1]->children[0]->attr('text'));
        $t->same('strikeout', $struck->children[3]->type);
        $t->same('bar', $struck->children[3]->children[0]->attr('text'));
        $t->same('strikeout', $struck->children[5]->type);
        $t->same('baz', $struck->children[5]->children[0]->attr('text'));
        $t->contains('<p>This is <span style="font-variant:small-caps">small caps</span>.</p>', $blocks);
        $t->contains('<p>These are all underlined: <u>foo</u> and <u>bar</u>.</p>', $blocks);
        $t->contains('<p>These are all strikethrough: <del>foo</del>, <del>bar</del>, and <del>baz</del>.</p>', $blocks);
    },
    'maps upstream html reader pre code blocks' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p>Code:</p>
<pre><code>---- (should be four hyphens)

sub status {
    print "working";
}

this code block is indented by one tab
</code></pre>
<p>And:</p>
<pre><code>    this code block is indented by two tabs

These should not be escaped:  \$ \\ \> \[ \{
</code></pre>
HTML);
        $firstCode = $document->children[1];
        $secondCode = $document->children[3];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Code:', $document->children[0]->attr('text'));
        $t->same('code_block', $firstCode->type);
        $t->same("---- (should be four hyphens)\n\nsub status {\n    print \"working\";\n}\n\nthis code block is indented by one tab", $firstCode->attr('text'));
        $t->same('code_block', $secondCode->type);
        $t->same('    this code block is indented by two tabs' . "\n\n" . 'These should not be escaped:  \$ \\\\ \> \[ \{', $secondCode->attr('text'));
        $t->contains('<pre class="wp-block-code"><code>---- (should be four hyphens)', $blocks);
        $t->contains('These should not be escaped:  \$ \\\\ \&gt; \[ \{</code></pre>', $blocks);
    },
    'maps upstream html reader blockquote containers with code lists and nested quotes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p>E-mail style:</p>
<blockquote>
<p>This is a block quote. It is pretty short.</p>
</blockquote>
<blockquote>
<p>Code in a block quote:</p>
<pre><code>sub status {
    print "working";
}
</code></pre>
<p>A list:</p>
<ol>
<li>item one</li>
<li>item two</li>
</ol>
<p>Nested block quotes:</p>
<blockquote>
<p>nested</p>
</blockquote>
<blockquote>
<p>nested</p>
</blockquote>
</blockquote>
<p>This should not be a block quote: 2 &gt; 1.</p>
<p>Box-style:</p>
<blockquote>
<p>Example:</p>
<pre><code>sub status {
    print "working";
}
</code></pre>
</blockquote>
<blockquote>
<ol>
<li>do laundry</li>
<li>take out the trash</li>
</ol>
</blockquote>
<p>Here's a nested one:</p>
<blockquote>
<p>Joe said:</p>
<blockquote>
<p>Don't quote me.</p>
</blockquote>
</blockquote>
<p>And a following paragraph.</p>
HTML);
        $simple = $document->children[1];
        $complex = $document->children[2];
        $notQuote = $document->children[3];
        $boxCode = $document->children[5];
        $listQuote = $document->children[6];
        $outerNested = $document->children[8];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(10, count($document->children));
        $t->same('blockquote', $simple->type);
        $t->same('This is a block quote. It is pretty short.', $simple->children[0]->attr('text'));
        $t->same(['paragraph', 'code_block', 'paragraph', 'ordered_list', 'paragraph', 'blockquote', 'blockquote'], array_map(static fn ($node): string => $node->type, $complex->children));
        $t->same("sub status {\n    print \"working\";\n}", $complex->children[1]->attr('text'));
        $t->same('item two', $complex->children[3]->children[1]->children[0]->attr('text'));
        $t->same('nested', $complex->children[5]->children[0]->attr('text'));
        $t->same('This should not be a block quote: 2 > 1.', $notQuote->attr('text'));
        $t->same(['paragraph', 'code_block'], array_map(static fn ($node): string => $node->type, $boxCode->children));
        $t->same('ordered_list', $listQuote->children[0]->type);
        $t->same('take out the trash', $listQuote->children[0]->children[1]->children[0]->attr('text'));
        $t->same('blockquote', $outerNested->children[1]->type);
        $t->same("Don't quote me.", $outerNested->children[1]->children[0]->attr('text'));
        $t->contains('<blockquote class="wp-block-quote"><p>Code in a block quote:</p><pre class="wp-block-code"><code>sub status {', $blocks);
        $t->contains('<ol><li>item one</li><li>item two</li></ol>', $blocks);
        $t->contains('<blockquote><p>Don&#039;t quote me.</p></blockquote>', $blocks);
    },
    'maps upstream html reader top-level lists and ordered styles' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p>Asterisks tight:</p>
<ul>
<li>asterisk 1</li>
<li>asterisk 2</li>
<li>asterisk 3</li>
</ul>
<p>Asterisks loose:</p>
<ul>
<li><p>asterisk 1</p>
</li>
<li><p>asterisk 2</p>
</li>
<li><p>asterisk 3</p>
</li>
</ul>
<p>Tight:</p>
<ol>
<li>First</li>
<li>Second</li>
<li>Third</li>
</ol>
<p>Multiple paragraphs:</p>
<ol>
<li><p>Item 1, graf one.</p>
<p>Item 1. graf two. The quick brown fox jumped over the lazy dog's back.</p>
</li>
<li><p>Item 2.</p>
</li>
<li><p>Item 3.</p>
</li>
</ol>
<p>List styles:</p>
<ol></ol>
<ol type="i"></ol>
<ol class="lower-roman"></ol>
<ol style="lower-roman"></ol>
<ol style="list-style: lower-roman;"></ol>
<ol style="list-style-type: lower-roman;"></ol>
HTML);
        $tightBullet = $document->children[1];
        $looseBullet = $document->children[3];
        $tightOrdered = $document->children[5];
        $multiParagraphOrdered = $document->children[7];
        $styleLists = array_slice($document->children, 9, 6);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(15, count($document->children));
        $t->same('bullet_list', $tightBullet->type);
        $t->same('text', $tightBullet->children[0]->children[0]->type);
        $t->same('asterisk 1', $tightBullet->children[0]->children[0]->attr('text'));
        $t->same('bullet_list', $looseBullet->type);
        $t->same('paragraph', $looseBullet->children[0]->children[0]->type);
        $t->same('asterisk 1', $looseBullet->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $tightOrdered->type);
        $t->same(1, $tightOrdered->attr('start'));
        $t->same('default', $tightOrdered->attr('style'));
        $t->same('Second', $tightOrdered->children[1]->children[0]->attr('text'));
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $multiParagraphOrdered->children[0]->children));
        $t->same("Item 1. graf two. The quick brown fox jumped over the lazy dog's back.", $multiParagraphOrdered->children[0]->children[1]->attr('text'));
        $t->same(['default', 'lower_roman', 'lower_roman', 'default', 'lower_roman', 'lower_roman'], array_map(static fn (AstNode $node): mixed => $node->attr('style'), $styleLists));
        $t->contains('<ul><li>asterisk 1</li><li>asterisk 2</li><li>asterisk 3</li></ul>', $blocks);
        $t->contains('<ul><li><p>asterisk 1</p></li><li><p>asterisk 2</p></li><li><p>asterisk 3</p></li></ul>', $blocks);
        $t->contains('<ol><li>First</li><li>Second</li><li>Third</li></ol>', $blocks);
        $t->contains('<ol><li><p>Item 1, graf one.</p><p>Item 1. graf two. The quick brown fox jumped over the lazy dog&#039;s back.</p></li><li><p>Item 2.</p></li><li><p>Item 3.</p></li></ol>', $blocks);
        $t->contains('<ol type="i"></ol>', $blocks);
    },
    'maps upstream html reader nested tabs and fancy list markers' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<h2>Nested</h2>
<ul>
<li>Tab<ul>
<li>Tab<ul>
<li>Tab</li>
</ul>
</li>
</ul>
</li>
</ul>
<p>Here's another:</p>
<ol>
<li>First</li>
<li>Second:<ul>
<li>Fee</li>
<li>Fie</li>
<li>Foe</li>
</ul>
</li>
<li>Third</li>
</ol>
<p>Same thing but with paragraphs:</p>
<ol>
<li><p>First</p></li>
<li><p>Second:</p>
<ul>
<li>Fee</li>
<li>Fie</li>
<li>Foe</li>
</ul>
</li>
<li><p>Third</p></li>
</ol>
<h2>Tabs and spaces</h2>
<ul>
<li><p>this is a list item indented with tabs</p></li>
<li><p>this is a list item indented with spaces</p>
<ul>
<li><p>this is an example list item indented with tabs</p></li>
<li><p>this is an example list item indented with spaces</p></li>
</ul>
</li>
</ul>
<h2 id="fancy-list-markers">Fancy list markers</h2>
<ol start="2" class="decimal">
<li>begins with 2</li>
<li><p>and now 3</p><p>with a continuation</p>
<ol start="4" class="lower-roman">
<li>sublist with roman numerals, starting with 4</li>
<li>more items<ol class="upper-alpha">
<li>a subsublist</li>
<li>a subsublist</li>
</ol></li>
</ol></li>
</ol>
<p>Nesting:</p>
<ol type="A">
<li>Upper Alpha<ol class="upper-roman">
<li>Upper Roman.<ol start="6" class="decimal">
<li>Decimal start with 6<ol start="3" type="a">
<li>Lower alpha with paren</li>
</ol></li>
</ol></li>
</ol></li>
</ol>
<p>Autonumbering:</p>
<ol>
<li>Autonumber.</li>
<li>More.<ol>
<li>Nested.</li>
</ol></li>
</ol>
HTML);
        $nestedHeading = $document->children[0];
        $nestedBullet = $document->children[1];
        $secondOrdered = $document->children[3];
        $paragraphOrdered = $document->children[5];
        $tabsList = $document->children[7];
        $fancyOrdered = $document->children[9];
        $nestingOrdered = $document->children[11];
        $autoOrdered = $document->children[13];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('heading', $nestedHeading->type);
        $t->same('nested', $nestedHeading->attr('id'));
        $t->same('bullet_list', $nestedBullet->type);
        $t->same(false, $nestedBullet->attr('loose'));
        $t->same('text', $nestedBullet->children[0]->children[0]->type);
        $t->same('bullet_list', $nestedBullet->children[0]->children[1]->type);
        $t->same('bullet_list', $nestedBullet->children[0]->children[1]->children[0]->children[1]->type);
        $t->same('ordered_list', $secondOrdered->type);
        $t->same('Second:', $secondOrdered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $secondOrdered->children[1]->children[1]->type);
        $t->same('Foe', $secondOrdered->children[1]->children[1]->children[2]->children[0]->attr('text'));
        $t->same('paragraph', $paragraphOrdered->children[1]->children[0]->type);
        $t->same('bullet_list', $paragraphOrdered->children[1]->children[1]->type);
        $t->same('paragraph', $tabsList->children[0]->children[0]->type);
        $t->same('paragraph', $tabsList->children[1]->children[1]->children[1]->children[0]->type);
        $t->same(2, $fancyOrdered->attr('start'));
        $t->same('decimal', $fancyOrdered->attr('style'));
        $t->same('paragraph', $fancyOrdered->children[1]->children[0]->type);
        $t->same('lower_roman', $fancyOrdered->children[1]->children[2]->attr('style'));
        $t->same('upper_alpha', $fancyOrdered->children[1]->children[2]->children[1]->children[1]->attr('style'));
        $t->same('upper_alpha', $nestingOrdered->attr('style'));
        $t->same('upper_roman', $nestingOrdered->children[0]->children[1]->attr('style'));
        $t->same(6, $nestingOrdered->children[0]->children[1]->children[0]->children[1]->attr('start'));
        $t->same('lower_alpha', $nestingOrdered->children[0]->children[1]->children[0]->children[1]->children[0]->children[1]->attr('style'));
        $t->same('default', $autoOrdered->attr('style'));
        $t->same('ordered_list', $autoOrdered->children[1]->children[1]->type);
        $t->contains('<h2 id="nested">Nested</h2>', $blocks);
        $t->contains('<ul><li>Tab<ul><li>Tab<ul><li>Tab</li></ul></li></ul></li></ul>', $blocks);
        $t->contains('<ol start="2"><li>begins with 2</li><li><p>and now 3</p><p>with a continuation</p><ol start="4" type="i"><li>sublist with roman numerals, starting with 4</li><li>more items<ol type="A"><li>a subsublist</li><li>a subsublist</li></ol></li></ol></li></ol>', $blocks);
        $t->contains('<ol type="A"><li>Upper Alpha<ol type="I"><li>Upper Roman.<ol start="6"><li>Decimal start with 6<ol start="3" type="a"><li>Lower alpha with paren</li></ol></li></ol></li></ol></li></ol>', $blocks);
    },
    'maps upstream html reader definition lists' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<h2>Definition</h2>
<dl>
  <dt>Violin</dt>
  <dd>Stringed musical instrument.</dd>
  <dd>Torture device.</dd>
  <dt>Cello</dt>
  <dt>Violoncello</dt>
  <dd>Low-voiced stringed instrument.</dd>
</dl>
HTML);
        $heading = $document->children[0];
        $list = $document->children[1];
        $violin = $list->children[0];
        $cello = $list->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('heading', $heading->type);
        $t->same('definition', $heading->attr('id'));
        $t->same('definition_list', $list->type);
        $t->same(2, count($list->children));
        $t->same('Violin', $violin->attr('term'));
        $t->same('term', $violin->children[0]->type);
        $t->same('Stringed musical instrument.', $violin->children[1]->children[0]->attr('text'));
        $t->same('Torture device.', $violin->children[2]->children[0]->attr('text'));
        $t->same("Cello\nVioloncello", $cello->attr('term'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $cello->children[0]->children));
        $t->same('Cello', $cello->children[0]->children[0]->attr('text'));
        $t->same('Violoncello', $cello->children[0]->children[2]->attr('text'));
        $t->same('Low-voiced stringed instrument.', $cello->children[1]->children[0]->attr('text'));
        $t->contains('<h2 id="definition">Definition</h2>', $blocks);
        $t->contains('<dl><dt>Violin</dt><dd>Stringed musical instrument.</dd><dd>Torture device.</dd><dt>Cello<br/>Violoncello</dt><dd>Low-voiced stringed instrument.</dd></dl>', $blocks);
    },
    'maps upstream html reader inline markup empty emphasis and emphasized links' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<h1>Inline Markup</h1>
<p>This is <em>emphasized</em>, and so <em>is this</em>.</p>
<p>This is <strong>strong</strong>, and so <strong>is this</strong>.</p>
<p>Empty <strong></strong> and <em></em>.
<p>An <em><a href="/url">emphasized link</a></em>.</p>
HTML);
        $heading = $document->children[0];
        $emphasis = $document->children[1];
        $strong = $document->children[2];
        $empty = $document->children[3];
        $linked = $document->children[4];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(5, count($document->children));
        $t->same('heading', $heading->type);
        $t->same('inline-markup', $heading->attr('id'));
        $t->same(['text', 'emph', 'text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $emphasis->children));
        $t->same('emphasized', $emphasis->children[1]->children[0]->attr('text'));
        $t->same('is this', $emphasis->children[3]->children[0]->attr('text'));
        $t->same(['text', 'strong', 'text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $strong->children));
        $t->same('strong', $strong->children[1]->children[0]->attr('text'));
        $t->same('is this', $strong->children[3]->children[0]->attr('text'));
        $t->same(['text', 'strong', 'text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $empty->children));
        $t->same(0, count($empty->children[1]->children));
        $t->same(0, count($empty->children[3]->children));
        $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $linked->children));
        $t->same('link', $linked->children[1]->children[0]->type);
        $t->same('/url', $linked->children[1]->children[0]->attr('url'));
        $t->same('emphasized link', $linked->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<p>Empty <strong></strong> and <em></em>.</p>', $blocks);
        $t->contains('<p>An <em><a href="/url">emphasized link</a></em>.</p>', $blocks);
    },
    'maps upstream html reader nested strong emphasis and code spans' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<p><strong><em>This is strong and em.</em></strong></p>
<p>So is <strong><em>this</em></strong> word.</p>
<p><strong><em>This is strong and em.</em></strong></p>
<p>So is <strong><em>this</em></strong> word.</p>
<p>This is code: <code>&gt;</code>, <code>$</code>, <code>\</code>, <code>\$</code>, <code>&lt;html&gt;</code>.</p>
HTML);
        $firstNested = $document->children[0]->children[0];
        $firstWord = $document->children[1]->children[1];
        $secondNested = $document->children[2]->children[0];
        $secondWord = $document->children[3]->children[1];
        $codeParagraph = $document->children[4];
        $codeNodes = array_values(array_filter(
            $codeParagraph->children,
            static fn (AstNode $node): bool => $node->type === 'code'
        ));
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(5, count($document->children));
        foreach ([$firstNested, $secondNested] as $nested) {
            $t->same('strong', $nested->type);
            $t->same('emph', $nested->children[0]->type);
            $t->same('This is strong and em.', $nested->children[0]->children[0]->attr('text'));
        }
        foreach ([$firstWord, $secondWord] as $nestedWord) {
            $t->same('strong', $nestedWord->type);
            $t->same('emph', $nestedWord->children[0]->type);
            $t->same('this', $nestedWord->children[0]->children[0]->attr('text'));
        }
        $t->same(['>', '$', '\\', '\\$', '<html>'], array_map(static fn (AstNode $node): mixed => $node->attr('text'), $codeNodes));
        $t->contains('<p><strong><em>This is strong and em.</em></strong></p>', $blocks);
        $t->contains('<p>So is <strong><em>this</em></strong> word.</p>', $blocks);
        $t->contains('<p>This is code: <code>&gt;</code>, <code>$</code>, <code>\</code>, <code>\$</code>, <code>&lt;html&gt;</code>.</p>', $blocks);
    },
    'maps upstream html reader smart quotes dashes and ellipses as literal text' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<hr />
<h1>Smart quotes, ellipses, dashes</h1>
<p>"Hello," said the spider. "'Shelob' is my name."</p>
<p>'A', 'B', and 'C' are letters.</p>
<p>'Oak,' 'elm,' and 'beech' are names of trees. So is 'pine.'</p>
<p>'He said, "I want to go."' Were you alive in the 70's?</p>
<p>Here is some quoted '<code>code</code>' and a "<a href="http://example.com/?foo=1&amp;bar=2">quoted link</a>".</p>
<p>Some dashes: one---two --- three--four -- five.</p>
<p>Dashes between numbers: 5-7, 255-66, 1987-1999.</p>
<p>Ellipses...and. . .and . . . .</p>
<hr />
HTML);
        $heading = $document->children[1];
        $hello = $document->children[2];
        $possessive = $document->children[5];
        $quotedCode = $document->children[6];
        $dashes = $document->children[7];
        $numberDashes = $document->children[8];
        $ellipses = $document->children[9];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('horizontal_rule', $document->children[0]->type);
        $t->same('heading', $heading->type);
        $t->same('smart-quotes-ellipses-dashes', $heading->attr('id'));
        $t->same('horizontal_rule', $document->children[10]->type);
        $t->same('"Hello," said the spider. "\'Shelob\' is my name."', $hello->children[0]->attr('text'));
        $t->same('\'He said, "I want to go."\' Were you alive in the 70\'s?', $possessive->children[0]->attr('text'));
        $t->same(['text', 'code', 'text', 'link', 'text'], array_map(static fn (AstNode $node): string => $node->type, $quotedCode->children));
        $t->same('Here is some quoted \'', $quotedCode->children[0]->attr('text'));
        $t->same('code', $quotedCode->children[1]->attr('text'));
        $t->same('\' and a "', $quotedCode->children[2]->attr('text'));
        $t->same('http://example.com/?foo=1&bar=2', $quotedCode->children[3]->attr('url'));
        $t->same('quoted link', $quotedCode->children[3]->children[0]->attr('text'));
        $t->same('".', $quotedCode->children[4]->attr('text'));
        $t->same('Some dashes: one---two --- three--four -- five.', $dashes->children[0]->attr('text'));
        $t->same('Dashes between numbers: 5-7, 255-66, 1987-1999.', $numberDashes->children[0]->attr('text'));
        $t->same('Ellipses...and. . .and . . . .', $ellipses->children[0]->attr('text'));
        $t->contains('<h1 id="smart-quotes-ellipses-dashes">Smart quotes, ellipses, dashes</h1>', $blocks);
        $t->contains('<p>&quot;Hello,&quot; said the spider. &quot;&#039;Shelob&#039; is my name.&quot;</p>', $blocks);
        $t->contains('<p>Some dashes: one---two --- three--four -- five.</p>', $blocks);
        $t->contains('<p>Ellipses...and. . .and . . . .</p>', $blocks);
        $t->same(false, str_contains($blocks, "\u{201C}Hello"));
        $t->same(false, str_contains($blocks, "one\u{2014}two"));
        $t->same(false, str_contains($blocks, "\u{2026}"));
    },
    'maps upstream html reader latex as literal text not math or raw tex' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<h1>LaTeX</h1>
<ul>
<li>\cite[22-23]{smith.1899}</li>
<li>\doublespacing</li>
<li>$2+2=4$</li>
<li>$x \in y$</li>
<li>$\alpha \wedge \omega$</li>
<li>$223$</li>
<li>$p$-Tree</li>
<li>$\frac{d}{dx}f(x)=\lim_{h\to 0}\frac{f(x+h)-f(x)}{h}$</li>
<li>Here's one that has a line break in it: $\alpha + \omega \times x^2$.</li>
</ul>
<p>These shouldn't be math:</p>
<ul>
<li>To get the famous equation, write <code>$e = mc^2$</code>.</li>
<li>$22,000 is a <em>lot</em> of money. So is $34,000. (It worked if "lot" is emphasized.)</li>
<li>Escaped <code>$</code>: $73 <em>this should be emphasized</em> 23$.</li>
</ul>
<p>Here's a LaTeX table:</p>
<p>\begin{tabular}{|l|l|}\hline Animal &amp; Number \\ \hline Dog &amp; 2 \\ Cat &amp; 1 \\ \hline \end{tabular}</p>
<hr />
HTML);
        $heading = $document->children[0];
        $latexList = $document->children[1];
        $notMathList = $document->children[3];
        $tableSource = $document->children[5];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(7, count($document->children));
        $t->same('heading', $heading->type);
        $t->same('latex', $heading->attr('id'));
        $t->same('bullet_list', $latexList->type);
        $t->same('\cite[22-23]{smith.1899}', $latexList->children[0]->children[0]->attr('text'));
        $t->same('\doublespacing', $latexList->children[1]->children[0]->attr('text'));
        $t->same('$2+2=4$', $latexList->children[2]->children[0]->attr('text'));
        $t->same('$x \in y$', $latexList->children[3]->children[0]->attr('text'));
        $t->same('$\alpha \wedge \omega$', $latexList->children[4]->children[0]->attr('text'));
        $t->same('$223$', $latexList->children[5]->children[0]->attr('text'));
        $t->same('$p$-Tree', $latexList->children[6]->children[0]->attr('text'));
        $t->contains('$\frac{d}{dx}f(x)=\lim', $latexList->children[7]->children[0]->attr('text'));
        $t->same('Here\'s one that has a line break in it: $\alpha + \omega \times x^2$.', $latexList->children[8]->children[0]->attr('text'));
        $t->same('These shouldn\'t be math:', $document->children[2]->children[0]->attr('text'));
        $t->same('code', $notMathList->children[0]->children[1]->type);
        $t->same('$e = mc^2$', $notMathList->children[0]->children[1]->attr('text'));
        $t->same('emph', $notMathList->children[1]->children[1]->type);
        $t->same('$22,000 is a ', $notMathList->children[1]->children[0]->attr('text'));
        $t->same(' of money. So is $34,000. (It worked if "lot" is emphasized.)', $notMathList->children[1]->children[2]->attr('text'));
        $t->same('code', $notMathList->children[2]->children[1]->type);
        $t->same('$', $notMathList->children[2]->children[1]->attr('text'));
        $t->same('emph', $notMathList->children[2]->children[3]->type);
        $t->same(' 23$.', $notMathList->children[2]->children[4]->attr('text'));
        $t->same('Here\'s a LaTeX table:', $document->children[4]->children[0]->attr('text'));
        $t->same('paragraph', $tableSource->type);
        $t->contains('\begin{tabular}{|l|l|}\hline Animal & Number', $tableSource->children[0]->attr('text'));
        $t->same('horizontal_rule', $document->children[6]->type);
        foreach ([$latexList, $notMathList, $tableSource] as $node) {
            $types = array_map(static fn (AstNode $child): string => $child->type, $node->children);
            $t->same(false, in_array('math', $types, true));
            $t->same(false, in_array('raw_tex', $types, true));
        }
        $t->contains('<h1 id="latex">LaTeX</h1>', $blocks);
        $t->contains('<li>\cite[22-23]{smith.1899}</li><li>\doublespacing</li><li>$2+2=4$</li>', $blocks);
        $t->contains('<li>To get the famous equation, write <code>$e = mc^2$</code>.</li>', $blocks);
        $t->contains('<li>$22,000 is a <em>lot</em> of money. So is $34,000. (It worked if &quot;lot&quot; is emphasized.)</li>', $blocks);
        $t->contains('<p>\begin{tabular}{|l|l|}\hline Animal &amp; Number \\\\ \hline Dog &amp; 2 \\\\ Cat &amp; 1 \\\\ \hline \end{tabular}</p>', $blocks);
        $t->same(false, str_contains($blocks, 'pandoc-raw-tex'));
        $t->same(false, str_contains($blocks, 'class="math'));
    },
    'maps upstream html reader special characters as literal text' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<h1>Special Characters</h1>
<p>Here is some unicode:</p>
<ul>
<li>I hat: Î</li>
<li>o umlaut: ö</li>
<li>section: §</li>
<li>set membership: ∈</li>
<li>copyright: ©</li>
</ul>
<p>AT&amp;T has an ampersand in their name.</p>
<p>AT&amp;T is another way to write it.</p>
<p>This &amp; that.</p>
<p>4 &lt; 5.</p>
<p>6 &gt; 5.</p>
<p>Backslash: \</p>
<p>Backtick: `</p>
<p>Asterisk: *</p>
<p>Underscore: _</p>
<p>Left brace: {</p>
<p>Right brace: }</p>
<p>Left bracket: [</p>
<p>Right bracket: ]</p>
<p>Left paren: (</p>
<p>Right paren: )</p>
<p>Greater-than: &gt;</p>
<p>Hash: #</p>
<p>Period: .</p>
<p>Bang: !</p>
<p>Plus: +</p>
<p>Minus: -</p>
<hr />
HTML);
        $heading = $document->children[0];
        $unicodeList = $document->children[2];
        $entityParagraphs = array_slice($document->children, 3, 5);
        $literalParagraphs = array_slice($document->children, 8, 16);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(25, count($document->children));
        $t->same('heading', $heading->type);
        $t->same('special-characters', $heading->attr('id'));
        $t->same('Here is some unicode:', $document->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $unicodeList->type);
        $t->same([
            'I hat: Î',
            'o umlaut: ö',
            'section: §',
            'set membership: ∈',
            'copyright: ©',
        ], array_map(static fn (AstNode $node): mixed => $node->children[0]->attr('text'), $unicodeList->children));
        $t->same([
            'AT&T has an ampersand in their name.',
            'AT&T is another way to write it.',
            'This & that.',
            '4 < 5.',
            '6 > 5.',
        ], array_map(static fn (AstNode $node): mixed => $node->children[0]->attr('text'), $entityParagraphs));
        $t->same([
            'Backslash: \\',
            'Backtick: `',
            'Asterisk: *',
            'Underscore: _',
            'Left brace: {',
            'Right brace: }',
            'Left bracket: [',
            'Right bracket: ]',
            'Left paren: (',
            'Right paren: )',
            'Greater-than: >',
            'Hash: #',
            'Period: .',
            'Bang: !',
            'Plus: +',
            'Minus: -',
        ], array_map(static fn (AstNode $node): mixed => $node->children[0]->attr('text'), $literalParagraphs));
        $t->same('horizontal_rule', $document->children[24]->type);
        $t->contains('<h1 id="special-characters">Special Characters</h1>', $blocks);
        $t->contains('<li>I hat: Î</li><li>o umlaut: ö</li><li>section: §</li><li>set membership: ∈</li><li>copyright: ©</li>', $blocks);
        $t->contains('<p>AT&amp;T has an ampersand in their name.</p>', $blocks);
        $t->contains('<p>4 &lt; 5.</p>', $blocks);
        $t->contains('<p>Greater-than: &gt;</p>', $blocks);
        $t->contains('<p>Asterisk: *</p>', $blocks);
        $t->contains('<hr class="wp-block-separator has-alpha-channel-opacity"/>', $blocks);
        $t->same(false, str_contains($blocks, '<em></em>'));
        $t->same(false, str_contains($blocks, '<strong></strong>'));
    },
    'maps upstream html reader links explicit reference ampersand and code contexts' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(<<<'HTML'
<h1>Links</h1>
<h2>Explicit</h2>
<p>Just a <a href="/url/">URL</a>.</p>
<p><a href="/url/" title="title">URL and title</a>.</p>
<p><a href="/url/" title="title preceded by two spaces">URL and title</a>.</p>
<p><a href="/url/" title="title preceded by a tab">URL and title</a>.</p>
<p><a href="/url/" title="title with &quot;quotes&quot; in it">URL and title</a></p>
<p><a href="/url/" title="title with single quotes">URL and title</a></p>
Email link (nobody [at] nowhere.net)<p><a href="">Empty</a>.</p>
<h2>Reference</h2>
<p>Foo <a href="/url/">bar</a>.</p>
<p>Foo <a href="/url/">bar</a>.</p>
<p>Foo <a href="/url/">bar</a>.</p>
<p>With <a href="/url/">embedded [brackets]</a>.</p>
<p><a href="/url/">b</a> by itself should be a link.</p>
<p>Indented <a href="/url">once</a>.</p>
<p>Indented <a href="/url">twice</a>.</p>
<p>Indented <a href="/url">thrice</a>.</p>
<p>This should [not] be a link.</p>
<pre><code>[not]: /url
</code></pre>
<p>Foo <a href="/url/" title="Title with &quot;quotes&quot; inside">bar</a>.</p>
<p>Foo <a href="/url/" title="Title with &quot;quote&quot; inside">biz</a>.</p>
<h2>With ampersands</h2>
<p>Here's a <a href="http://example.com/?foo=1&amp;bar=2">link with an ampersand in the URL</a>.</p>
<p>Here's a link with an amersand in the link text: <a href="http://att.com/" title="AT&T">AT&amp;T</a>.</p>
<p>Here's an <a href="/script?foo=1&amp;bar=2">inline link</a>.</p>
<p>Here's an <a href="/script?foo=1&amp;bar=2">inline link in pointy braces</a>.</p>
<h2>Autolinks</h2>
<p>With an ampersand: <a href="http://example.com/?foo=1&amp;bar=2">http://example.com/?foo=1&amp;bar=2</a></p>
<ul>
<li>In a list?</li>
<li><a href="http://example.com/">http://example.com/</a></li>
<li>It should.</li>
</ul>
An e-mail address: nobody [at] nowhere.net<blockquote>
<p>Blockquoted: <a href="http://example.com/">http://example.com/</a></p>
</blockquote>
<p>Auto-links should not occur here: <code>&lt;http://example.com/&gt;</code></p>
<pre><code>or here: &lt;http://example.com/&gt;
</code></pre>
<hr />
HTML);
        $justUrl = $document->children[2];
        $titleWithQuotes = $document->children[6]->children[0];
        $emailText = $document->children[8];
        $emptyLink = $document->children[9]->children[0];
        $embeddedReferenceText = $document->children[14]->children[1];
        $notReference = $document->children[19];
        $referenceCodeBlock = $document->children[20];
        $referenceTitle = $document->children[21]->children[1];
        $ampersandUrl = $document->children[24]->children[1];
        $ampersandText = $document->children[25]->children[1];
        $autolinkParagraph = $document->children[29]->children[1];
        $listAutolink = $document->children[30]->children[1]->children[0];
        $emailBeforeQuote = $document->children[31];
        $quoteLink = $document->children[32]->children[0]->children[1];
        $codeSpanParagraph = $document->children[33];
        $codeBlock = $document->children[34];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(36, count($document->children));
        $t->same('links', $document->children[0]->attr('id'));
        $t->same('explicit', $document->children[1]->attr('id'));
        $t->same('/url/', $justUrl->children[1]->attr('url'));
        $t->same('title with "quotes" in it', $titleWithQuotes->attr('title'));
        $t->same('Email link (nobody [at] nowhere.net)', $emailText->children[0]->attr('text'));
        $t->same('', $emptyLink->attr('url'));
        $t->same('reference', $document->children[10]->attr('id'));
        $t->same('embedded [brackets]', $embeddedReferenceText->children[0]->attr('text'));
        $t->same('This should [not] be a link.', $notReference->children[0]->attr('text'));
        $t->same('code_block', $referenceCodeBlock->type);
        $t->same('[not]: /url', $referenceCodeBlock->attr('text'));
        $t->same('Title with "quotes" inside', $referenceTitle->attr('title'));
        $t->same('with-ampersands', $document->children[23]->attr('id'));
        $t->same('http://example.com/?foo=1&bar=2', $ampersandUrl->attr('url'));
        $t->same('AT&T', $ampersandText->children[0]->attr('text'));
        $t->same('AT&T', $ampersandText->attr('title'));
        $t->same('autolinks', $document->children[28]->attr('id'));
        $t->same('http://example.com/?foo=1&bar=2', $autolinkParagraph->attr('url'));
        $t->same('http://example.com/', $listAutolink->attr('url'));
        $t->same('An e-mail address: nobody [at] nowhere.net', $emailBeforeQuote->children[0]->attr('text'));
        $t->same('http://example.com/', $quoteLink->attr('url'));
        $t->same('code', $codeSpanParagraph->children[1]->type);
        $t->same('<http://example.com/>', $codeSpanParagraph->children[1]->attr('text'));
        $t->same('or here: <http://example.com/>', $codeBlock->attr('text'));
        $t->same('horizontal_rule', $document->children[35]->type);
        $t->contains('<a href="/url/" title="title with &quot;quotes&quot; in it">URL and title</a>', $blocks);
        $t->contains('<p>Email link (nobody [at] nowhere.net)</p>', $blocks);
        $t->contains('<p><a href="">Empty</a>.</p>', $blocks);
        $t->contains('<p>This should [not] be a link.</p>', $blocks);
        $t->contains('<a href="http://att.com/" title="AT&amp;T">AT&amp;T</a>', $blocks);
        $t->contains('<p>Auto-links should not occur here: <code>&lt;http://example.com/&gt;</code></p>', $blocks);
    },
    'maps upstream html reader table headers with omitted section tags' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('table_head', $head->type);
        $t->same(1, count($head->children));
        $t->same('X', $head->children[0]->children[0]->attr('text'));
        $t->same('Z', $head->children[0]->children[2]->attr('text'));
        $t->same('table_body', $body->type);
        $t->same(2, count($body->children));
        $t->same(false, (bool) $body->children[0]->children[0]->attr('header'));
        $t->same('1', $body->children[0]->children[0]->attr('text'));
        $t->same('6', $body->children[1]->children[2]->attr('text'));
        $t->contains('<thead><tr><th>X</th><th>Y</th><th>Z</th></tr></thead><tbody><tr><td>1</td><td>2</td><td>3</td></tr><tr><td>4</td><td>5</td><td>6</td></tr></tbody>', $blocks);
    },
    'maps upstream html reader row headers as table body row head columns' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <thead>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <th>1</th>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <th>4</th>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(true, $body->children[0]->children[0]->attr('header'));
        $t->same(false, (bool) $body->children[0]->children[1]->attr('header'));
        $t->same('1', $body->children[0]->children[0]->attr('text'));
        $t->same('4', $body->children[1]->children[0]->attr('text'));
        $t->contains('<tbody><tr><th>1</th><td>2</td><td>3</td></tr><tr><th>4</th><td>5</td><td>6</td></tr></tbody>', $blocks);
    },
    'maps upstream html reader omitted table section end tags' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <thead>
        <tr>
            <td>X</td>
            <td>Y</td>
            <td>Z</td>
        </tr>
    <tbody>
        <tr>
            <td>1</td>
            <td>2</td>
            <td>3</td>
        </tr>
    <tfoot>
        <tr>
            <td>4</td>
            <td>5</td>
            <td>6</td>
        </tr>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('X', $head->children[0]->children[0]->attr('text'));
        $t->same('1', $body->children[0]->children[0]->attr('text'));
        $t->same('4', $foot->children[0]->children[0]->attr('text'));
        $t->contains('<thead><tr><th>X</th><th>Y</th><th>Z</th></tr></thead><tbody><tr><td>1</td><td>2</td><td>3</td></tr></tbody><tfoot><tr><td>4</td><td>5</td><td>6</td></tr></tfoot>', $blocks);
    },
    'maps upstream html reader multiple table body sections without flattening' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <thead>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    </tbody>
    <tbody>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $head = $table->children[0];
        $firstBody = $table->children[1];
        $secondBody = $table->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('table_head', $head->type);
        $t->same('table_body', $firstBody->type);
        $t->same('table_body', $secondBody->type);
        $t->same(3, count($table->children));
        $t->same(1, count($firstBody->children));
        $t->same(1, count($secondBody->children));
        $t->same('1', $firstBody->children[0]->children[0]->attr('text'));
        $t->same('3', $firstBody->children[0]->children[2]->attr('text'));
        $t->same('4', $secondBody->children[0]->children[0]->attr('text'));
        $t->same('6', $secondBody->children[0]->children[2]->attr('text'));
        $t->contains('<thead><tr><th>X</th><th>Y</th><th>Z</th></tr></thead><tbody><tr><td>1</td><td>2</td><td>3</td></tr></tbody><tbody><tr><td>4</td><td>5</td><td>6</td></tr></tbody>', $blocks);
    },
    'maps upstream html reader paragraph block inside a multiple body table cell' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <thead>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>1</td>
        <td><p>2</p></td>
        <td>3</td>
    </tr>
    </tbody>
    <tbody>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $firstBody = $table->children[1];
        $secondBody = $table->children[2];
        $paragraphCell = $firstBody->children[0]->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('table_body', $firstBody->type);
        $t->same('table_body', $secondBody->type);
        $t->same('1', $firstBody->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $paragraphCell->children[0]->type);
        $t->same('2', $paragraphCell->children[0]->attr('text'));
        $t->same('2', $paragraphCell->children[0]->children[0]->attr('text'));
        $t->same('3', $firstBody->children[0]->children[2]->attr('text'));
        $t->same('6', $secondBody->children[0]->children[2]->attr('text'));
        $t->contains('<thead><tr><th>X</th><th>Y</th><th>Z</th></tr></thead><tbody><tr><td>1</td><td><p>2</p></td><td>3</td></tr></tbody><tbody><tr><td>4</td><td>5</td><td>6</td></tr></tbody>', $blocks);
    },
    'maps upstream html reader tables without headers body and foot variants' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();

        $explicitBody = $reader->read(<<<'HTML'
<table>
    <tbody>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML)->children[0];
        $omittedBody = $reader->read(<<<'HTML'
<table>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
</table>
HTML)->children[0];
        $emptyHead = $reader->read(<<<'HTML'
<table>
    <thead>
    </thead>
    <tbody>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML)->children[0];
        $bodyAndFootDocument = $reader->read(<<<'HTML'
<table>
    <tbody>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    </tbody>
    <tfoot>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tfoot>
</table>
HTML);
        $bodyAndFoot = $bodyAndFootDocument->children[0];
        $bodyAndFootBlocks = (new WordPressBlockWriter())->write($bodyAndFootDocument);

        foreach ([$explicitBody, $omittedBody, $emptyHead, $bodyAndFoot] as $table) {
            $t->same('table', $table->type);
            $t->same([], $table->children[0]->children);
            $t->same('table_body', $table->children[1]->type);
            $t->same(3, count($table->attr('alignments')));
            $t->same('1', $table->children[1]->children[0]->children[0]->attr('text'));
            $t->same('3', $table->children[1]->children[0]->children[2]->attr('text'));
        }

        $t->same(2, count($explicitBody->children[1]->children));
        $t->same(2, count($omittedBody->children[1]->children));
        $t->same(2, count($emptyHead->children[1]->children));
        $t->same('table_foot', $bodyAndFoot->children[2]->type);
        $t->same('4', $bodyAndFoot->children[2]->children[0]->children[0]->attr('text'));
        $t->same('6', $bodyAndFoot->children[2]->children[0]->children[2]->attr('text'));
        $t->contains('<tbody><tr><td>1</td><td>2</td><td>3</td></tr></tbody><tfoot><tr><td>4</td><td>5</td><td>6</td></tr></tfoot>', $bodyAndFootBlocks);
    },
    'maps upstream html reader body-local table head rows' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();

        $bodyWithFootDocument = $reader->read(<<<'HTML'
<table>
    <tbody>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td colspan="3">Details</td>
    </tr>
    </tbody>
    <tfoot>
    <tr>
        <th>4</th>
        <td>5</td>
        <td>6</td>
    </tr>
    </tfoot>
</table>
HTML);
        $omittedBodyDocument = $reader->read(<<<'HTML'
<table>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    <tr>
        <th>1</th>
        <th>2</th>
        <th>3</th>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
</table>
HTML);
        $explicitBody = $reader->read(<<<'HTML'
<table>
    <tbody>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML)->children[0];
        $emptyHead = $reader->read(<<<'HTML'
<table>
    <thead>
    </thead>
    <tbody>
    <tr>
        <th>X</th>
        <th>Y</th>
        <th>Z</th>
    </tr>
    <tr>
        <td>1</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML)->children[0];

        $bodyWithFoot = $bodyWithFootDocument->children[0];
        $bodyWithFootBody = $bodyWithFoot->children[1];
        $bodyHeadRows = $bodyWithFootBody->attr('headRows');
        $bodyWithFootBlocks = (new WordPressBlockWriter())->write($bodyWithFootDocument);
        $omittedBody = $omittedBodyDocument->children[0];
        $omittedBodyBody = $omittedBody->children[1];
        $omittedBodyHeadRows = $omittedBodyBody->attr('headRows');
        $omittedBodyBlocks = (new WordPressBlockWriter())->write($omittedBodyDocument);

        $t->same('table', $bodyWithFoot->type);
        $t->same([], $bodyWithFoot->children[0]->children);
        $t->same(true, is_array($bodyHeadRows));
        $t->same(1, $bodyWithFootBody->attr('headRowCount'));
        $t->same('X', $bodyHeadRows[0]->children[0]->attr('text'));
        $t->same('Z', $bodyHeadRows[0]->children[2]->attr('text'));
        $t->same(2, count($bodyWithFootBody->children));
        $t->same('1', $bodyWithFootBody->children[0]->children[0]->attr('text'));
        $t->same('Details', $bodyWithFootBody->children[1]->children[0]->attr('text'));
        $t->same(3, $bodyWithFootBody->children[1]->children[0]->attr('colspan'));
        $t->contains('<tbody><tr><th>X</th><th>Y</th><th>Z</th></tr><tr><td>1</td><td>2</td><td>3</td></tr><tr><td colspan="3">Details</td></tr></tbody><tfoot><tr><th>4</th><td>5</td><td>6</td></tr></tfoot>', $bodyWithFootBlocks);

        $t->same('X', $omittedBody->children[0]->children[0]->children[0]->attr('text'));
        $t->same(true, is_array($omittedBodyHeadRows));
        $t->same(1, $omittedBodyBody->attr('headRowCount'));
        $t->same('1', $omittedBodyHeadRows[0]->children[0]->attr('text'));
        $t->same('3', $omittedBodyHeadRows[0]->children[2]->attr('text'));
        $t->same(1, count($omittedBodyBody->children));
        $t->same('4', $omittedBodyBody->children[0]->children[0]->attr('text'));
        $t->contains('<thead><tr><th>X</th><th>Y</th><th>Z</th></tr></thead><tbody><tr><th>1</th><th>2</th><th>3</th></tr><tr><td>4</td><td>5</td><td>6</td></tr></tbody>', $omittedBodyBlocks);

        foreach ([$explicitBody, $emptyHead] as $table) {
            $body = $table->children[1];
            $headRows = $body->attr('headRows');
            $t->same([], $table->children[0]->children);
            $t->same(true, is_array($headRows));
            $t->same('X', $headRows[0]->children[0]->attr('text'));
            $t->same(2, count($body->children));
            $t->same('1', $body->children[0]->children[0]->attr('text'));
            $t->same('4', $body->children[1]->children[0]->attr('text'));
        }
    },
    'maps upstream html reader colspans without table headers' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <tr>
        <td colspan="2">1 and 2</td>
        <td>3</td>
    </tr>
    <tr>
        <td colspan="3">4, 5, and 6</td>
    </tr>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same([], $head->children);
        $t->same(['default', 'default', 'default'], $table->attr('alignments'));
        $t->same(2, count($body->children));
        $t->same(2, $body->children[0]->children[0]->attr('colspan'));
        $t->same('1 and 2', $body->children[0]->children[0]->attr('text'));
        $t->same('3', $body->children[0]->children[1]->attr('text'));
        $t->same(3, $body->children[1]->children[0]->attr('colspan'));
        $t->same('4, 5, and 6', $body->children[1]->children[0]->attr('text'));
        $t->contains('<tbody><tr><td colspan="2">1 and 2</td><td>3</td></tr><tr><td colspan="3">4, 5, and 6</td></tr></tbody>', $blocks);
    },
    'maps upstream html reader colspans and rowspans in headed tables' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
    <thead>
    <tr>
        <th colspan="3">Numbers</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td rowspan="2">1 and 4</td>
        <td>2</td>
        <td>3</td>
    </tr>
    <tr>
        <td>5</td>
        <td>6</td>
    </tr>
    </tbody>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(3, $head->children[0]->children[0]->attr('colspan'));
        $t->same(true, $head->children[0]->children[0]->attr('header'));
        $t->same('Numbers', $head->children[0]->children[0]->attr('text'));
        $t->same(2, $body->children[0]->children[0]->attr('rowspan'));
        $t->same('1 and 4', $body->children[0]->children[0]->attr('text'));
        $t->same(2, count($body->children[1]->children));
        $t->same('6', $body->children[1]->children[1]->attr('text'));
        $t->contains('<thead><tr><th colspan="3">Numbers</th></tr></thead><tbody><tr><td rowspan="2">1 and 4</td><td>2</td><td>3</td></tr><tr><td>5</td><td>6</td></tr></tbody>', $blocks);
    },
    'maps upstream html reader table attributes on sections rows and cells' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="attrib-test-table">
  <thead class="table-head">
    <tr class="table-head-row">
      <th abbr="x" colspan="3">Cat X</th>
    </tr>
    <tbody data-part="body" class="main">
    <tr data-part="row">
        <td data-part="cell">1</td>
        <td valign="bottom">2</td>
        <td style="color: #151950">3</td>
    </tr>
    </tbody>
    <tfoot class="summary">
    <tr bgcolor="#ccc">
        <td data-square="true">4</td>
        <td>5</td>
        <td>6</td>
    </tr>
    </tfoot>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];
        $headCell = $head->children[0]->children[0];
        $bodyRow = $body->children[0];
        $footRow = $foot->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('attrib-test-table', $table->attr('id'));
        $t->same(['table-head'], $head->attr('classes'));
        $t->same(['table-head-row'], $head->children[0]->attr('classes'));
        $t->same(['abbr' => 'x'], $headCell->attr('attributes'));
        $t->same(3, $headCell->attr('colspan'));
        $t->same('Cat X', $headCell->attr('text'));
        $t->same(['main'], $body->attr('classes'));
        $t->same(['part' => 'body'], $body->attr('attributes'));
        $t->same(['part' => 'row'], $bodyRow->attr('attributes'));
        $t->same(['part' => 'cell'], $bodyRow->children[0]->attr('attributes'));
        $t->same(['valign' => 'bottom'], $bodyRow->children[1]->attr('attributes'));
        $t->same(['style' => 'color: #151950'], $bodyRow->children[2]->attr('attributes'));
        $t->same(['summary'], $foot->attr('classes'));
        $t->same(['bgcolor' => '#ccc'], $footRow->attr('attributes'));
        $t->same(['square' => 'true'], $footRow->children[0]->attr('attributes'));
        $t->contains('<table id="attrib-test-table">', $blocks);
        $t->contains('<thead class="table-head"><tr class="table-head-row">', $blocks);
        $t->contains('<tbody class="main" data-part="body"><tr data-part="row">', $blocks);
        $t->contains('<tfoot class="summary"><tr bgcolor="#ccc">', $blocks);
        $t->contains('<th abbr="x" colspan="3">Cat X</th>', $blocks);
        $t->contains('<td data-part="cell">1</td><td valign="bottom">2</td><td style="color: #151950">3</td>', $blocks);
        $t->contains('<td data-square="true">4</td>', $blocks);
    },
    'maps upstream html reader empty tables as omitted blocks' => static function (TestRunner $t): void {
        $html = <<<'HTML'
This section should be empty.

<table>
    <tbody>
    </tbody>
</table>
<table>
</table>
HTML;
        $document = (new MarkdownReader())->read($html);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('This section should be empty.', $document->children[0]->children[0]->attr('text'));
        $t->contains('<p>This section should be empty.</p>', $blocks);
        $t->true(!str_contains($blocks, '<table>'), 'Empty upstream HTML tables should not render WordPress table markup');
        $t->true(!str_contains($blocks, '<!-- wp:html -->'), 'Empty upstream HTML tables should not fall back to raw HTML blocks');
    },
    'maps upstream markdown raw html regression boundaries' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '<del>test</del>',
            '</ div></.div>',
            '<!-- pandoc --help -->',
            "<\n\na>",
        ]));
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(7, count($document->children));
        $t->same('raw_html', $document->children[0]->type);
        $t->same('<del>', $document->children[0]->attr('html'));
        $t->same('plain', $document->children[1]->type);
        $t->same('test', $document->children[1]->attr('text'));
        $t->same('raw_html', $document->children[2]->type);
        $t->same('</del>', $document->children[2]->attr('html'));
        $t->same('paragraph', $document->children[3]->type);
        $t->same('</ div></.div>', $document->children[3]->children[0]->attr('text'));
        $t->same('raw_html', $document->children[4]->type);
        $t->same('<!-- pandoc --help -->', $document->children[4]->attr('html'));
        $t->same('<', $document->children[5]->children[0]->attr('text'));
        $t->same('a>', $document->children[6]->children[0]->attr('text'));
        $t->contains('<p>test</p>', $blocks);
        $t->contains('<p>&lt;/ div&gt;&lt;/.div&gt;</p>', $blocks);
        $t->contains('<!-- pandoc --help -->', $blocks);
    },
    'maps upstream markdown raw email and emoji extension cases' => static function (TestRunner $t): void {
        $rawEmailDocument = (new MarkdownReader())->read('**@user**');
        $emojiDocument = (new MarkdownReader())->read(':smile: and :+1:');
        $unknownDocument = (new MarkdownReader())->read('Unknown :not-a-pandoc-test-emoji: stays literal.');
        $rawEmail = $rawEmailDocument->children[0]->children[0];
        $smile = $emojiDocument->children[0]->children[0];
        $thumb = $emojiDocument->children[0]->children[2];
        $smileGlyph = "\u{1F604}";
        $thumbGlyph = "\u{1F44D}";
        $blocks = (new WordPressBlockWriter())->write($emojiDocument);

        $t->same('strong', $rawEmail->type);
        $t->same('@user', $rawEmail->children[0]->attr('text'));
        $t->same('span', $smile->type);
        $t->same(['emoji'], $smile->attr('classes'));
        $t->same(['data-emoji' => 'smile'], $smile->attr('attributes'));
        $t->same($smileGlyph, $smile->children[0]->attr('text'));
        $t->same(' and ', $emojiDocument->children[0]->children[1]->attr('text'));
        $t->same('span', $thumb->type);
        $t->same(['emoji'], $thumb->attr('classes'));
        $t->same(['data-emoji' => '+1'], $thumb->attr('attributes'));
        $t->same($thumbGlyph, $thumb->children[0]->attr('text'));
        $t->same('Unknown :not-a-pandoc-test-emoji: stays literal.', $unknownDocument->children[0]->children[0]->attr('text'));
        $t->contains('<p><span class="emoji" data-emoji="smile">' . $smileGlyph . '</span> and <span class="emoji" data-emoji="+1">' . $thumbGlyph . '</span></p>', $blocks);
    },
    'maps upstream markdown github wiki link extension cases' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '[[https://example.org]]',
            '[[title|https://example.org]]',
            '[[title|random string]]',
            '[[Name of page]]',
            '[[Name of ]page]]',
            '[[t`i*t_le|https://example.org]]',
        ]));
        $autolink = $document->children[0]->children[0];
        $titled = $document->children[1]->children[0];
        $badTarget = $document->children[2]->children[0];
        $pageName = $document->children[3]->children[0];
        $bracketPageName = $document->children[4]->children[0];
        $literalTitle = $document->children[5]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        foreach ([$autolink, $titled, $badTarget, $pageName, $bracketPageName, $literalTitle] as $link) {
            $t->same('link', $link->type);
            $t->same(['wikilink'], $link->attr('classes'));
        }
        $t->same('https://example.org', $autolink->attr('url'));
        $t->same('https://example.org', $autolink->children[0]->attr('text'));
        $t->same('https://example.org', $titled->attr('url'));
        $t->same('title', $titled->children[0]->attr('text'));
        $t->same('random string', $badTarget->attr('url'));
        $t->same('title', $badTarget->children[0]->attr('text'));
        $t->same('Name of page', $pageName->attr('url'));
        $t->same('Name of page', $pageName->children[0]->attr('text'));
        $t->same('Name of ]page', $bracketPageName->attr('url'));
        $t->same('Name of ]page', $bracketPageName->children[0]->attr('text'));
        $t->same('https://example.org', $literalTitle->attr('url'));
        $t->same('t`i*t_le', $literalTitle->children[0]->attr('text'));
        $t->contains('<a href="https://example.org" class="wikilink">https://example.org</a>', $blocks);
        $t->contains('<a href="https://example.org" class="wikilink">title</a>', $blocks);
        $t->contains('<a href="random string" class="wikilink">title</a>', $blocks);
        $t->contains('<a href="Name of ]page" class="wikilink">Name of ]page</a>', $blocks);
        $t->contains('<a href="https://example.org" class="wikilink">t`i*t_le</a>', $blocks);
    },
    'writes wordpress structured html table sections from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Structured HTML import table:</p>', $blocks);
        $t->contains('<figure class="wp-block-table"><table id="nordics" data-source="wikipedia"><colgroup><col style="width:30%"/><col style="width:30%"/><col style="width:20%"/><col style="width:20%"/></colgroup><thead class="simple-head"><tr><th style="text-align:center">Name</th><th style="text-align:center">Capital</th><th style="text-align:center">Population', $blocks);
        $t->contains('<tbody class="souvereign-states"><tr class="country"><th style="text-align:center">Denmark</th><td style="text-align:left">Copenhagen</td>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">States belonging to the <em>Nordics.</em></figcaption>', $blocks);
        $t->contains('<tfoot><tr id="summary"><td style="text-align:center">Total</td><td style="text-align:left"></td><td style="text-align:left">27,376,022</td><td style="text-align:left">1,258,336</td></tr></tfoot>', $blocks);
    },
    'writes wordpress multiple html table bodies from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $blocks = (new WordPressBlockWriter())->write($document);

        $segmentedTables = array_values(array_filter(
            $document->children,
            static fn ($node): bool => $node->type === 'table'
                && ($node->children[1] ?? null)?->type === 'table_body'
                && ($node->children[2] ?? null)?->type === 'table_body'
        ));
        $segmented = $segmentedTables[0];

        $t->contains('<p>Segmented HTML import table:</p>', $blocks);
        $t->same('table_body', $segmented->children[1]->type);
        $t->same(['batch' => 'published'], $segmented->children[1]->attr('attributes'));
        $t->same(['review-row' => 'published'], $segmented->children[1]->children[0]->attr('attributes'));
        $t->same('paragraph', $segmented->children[1]->children[0]->children[1]->children[0]->type);
        $t->same('12', $segmented->children[1]->children[0]->children[1]->children[0]->attr('text'));
        $t->same('table_body', $segmented->children[2]->type);
        $t->same(['batch' => 'review'], $segmented->children[2]->attr('attributes'));
        $t->same(['review-row' => 'media'], $segmented->children[2]->children[0]->attr('attributes'));
        $t->contains('<thead><tr><th>Batch</th><th>Posts</th><th>Status</th></tr></thead><tbody data-batch="published"><tr data-review-row="published"><td>May archive</td><td><p>12</p></td><td>Published</td></tr></tbody><tbody data-batch="review"><tr data-review-row="media"><td>June archive</td><td>8</td><td>Needs media review</td></tr></tbody>', $blocks);
    },
    'maps upstream testsuite raw html comments hr blocks and indented html code' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '<!-- Comment -->',
            '',
            '<!--',
            'Blah',
            'Blah',
            '-->',
            '',
            '<!--',
            "\tThis is another comment.",
            '-->',
            '',
            "\t<!-- Comment -->",
            '',
            '<!-- foo -->   ',
            '',
            "\t<hr />",
            '',
            '<hr>',
            '',
            '<hr class="foo" id="bar" />',
        ]);
        $document = (new MarkdownReader())->read($markdown);

        $t->same('raw_html', $document->children[0]->type);
        $t->same('<!-- Comment -->', $document->children[0]->attr('html'));
        $t->same("<!--\nBlah\nBlah\n-->", $document->children[1]->attr('html'));
        $t->same("<!--\n    This is another comment.\n-->", $document->children[2]->attr('html'));
        $t->same('code_block', $document->children[3]->type);
        $t->same('<!-- Comment -->', $document->children[3]->attr('text'));
        $t->same('<!-- foo -->', $document->children[4]->attr('html'));
        $t->same('code_block', $document->children[5]->type);
        $t->same('<hr />', $document->children[5]->attr('text'));
        $t->same('raw_html', $document->children[6]->type);
        $t->same('<hr>', $document->children[6]->attr('html'));
        $t->same('<hr class="foo" id="bar" />', $document->children[7]->attr('html'));
    },
    'maps upstream tables simple syntax with and without captions' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Simple table with caption:',
            '',
            '    Right Left    Center  Default',
            '  ------- ------ -------- ---------',
            '       12 12        12    12',
            '      123 123      123    123',
            '        1 1         1     1',
            '',
            '  : Demonstration of simple table syntax.',
            '',
            'Simple table without caption:',
            '',
            '    Right Left    Center  Default',
            '  ------- ------ -------- ---------',
            '       12 12        12    12',
            '      123 123      123    123',
            '        1 1         1     1',
            '',
            'Simple table indented two spaces:',
            '',
            '    Right Left    Center  Default',
            '  ------- ------ -------- ---------',
            '       12 12        12    12',
            '      123 123      123    123',
            '        1 1         1     1',
            '',
            '  : Demonstration of simple table syntax.',
        ]));
        $captioned = $document->children[1];
        $withoutCaption = $document->children[3];
        $indented = $document->children[5];

        $t->same('table', $captioned->type);
        $t->same('Demonstration of simple table syntax.', $captioned->attr('caption'));
        $t->same(['right', 'left', 'center', 'default'], $captioned->attr('alignments'));
        $t->same('Right', $captioned->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Default', $captioned->children[0]->children[0]->children[3]->attr('text'));
        $t->same(3, count($captioned->children[1]->children));
        $t->same('12', $captioned->children[1]->children[0]->children[0]->attr('text'));
        $t->same('123', $captioned->children[1]->children[1]->children[2]->attr('text'));
        $t->same('1', $captioned->children[1]->children[2]->children[3]->attr('text'));
        $t->same('', $withoutCaption->attr('caption'));
        $t->same(['right', 'left', 'center', 'default'], $withoutCaption->attr('alignments'));
        $t->same('Center', $withoutCaption->children[0]->children[0]->children[2]->attr('text'));
        $t->same('123', $withoutCaption->children[1]->children[1]->children[1]->attr('text'));
        $t->same('table', $indented->type);
        $t->same('Demonstration of simple table syntax.', $indented->attr('caption'));
        $t->same('Left', $indented->children[0]->children[0]->children[1]->attr('text'));
    },
    'maps upstream tables simple syntax without column headers' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Table without column headers:',
            '',
            '  ----- ----- ----- -----',
            '     12 12     12      12',
            '    123 123    123    123',
            '      1 1       1       1',
            '  ----- ----- ----- -----',
        ]));
        $table = $document->children[1];

        $t->same('table', $table->type);
        $t->same([], $table->children[0]->children);
        $t->same(['right', 'left', 'center', 'right'], $table->attr('alignments'));
        $t->same(3, count($table->children[1]->children));
        $t->same('12', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('12', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('123', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('1', $table->children[1]->children[2]->children[2]->attr('text'));
    },
    'maps upstream tables multiline syntax with wrapped rows captions and widths' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Multiline table with caption:',
            '',
            '  ---------------------------------------------------------------',
            '   Centered   Left              Right Default aligned',
            '    Header    Aligned         Aligned ',
            '  ----------- ---------- ------------ ---------------------------',
            '     First    row                12.0 Example of a row that spans',
            '                                      multiple lines.',
            '',
            '    Second    row                 5.0 Here\'s another one. Note',
            '                                      the blank line between',
            '                                      rows.',
            '  ---------------------------------------------------------------',
            '',
            '  : Here\'s the caption.',
            '    It may span multiple lines.',
            '',
            'Multiline table without caption:',
            '',
            '  ---------------------------------------------------------------',
            '   Centered   Left              Right Default aligned',
            '    Header    Aligned         Aligned ',
            '  ----------- ---------- ------------ ---------------------------',
            '     First    row                12.0 Example of a row that spans',
            '                                      multiple lines.',
            '',
            '    Second    row                 5.0 Here\'s another one. Note',
            '                                      the blank line between',
            '                                      rows.',
            '  ---------------------------------------------------------------',
        ]));
        $captioned = $document->children[1];
        $withoutCaption = $document->children[3];
        $head = $captioned->children[0]->children[0];
        $body = $captioned->children[1];
        $wrappedCell = $body->children[0]->children[3];

        $t->same('table', $captioned->type);
        $t->same("Here's the caption.\nIt may span multiple lines.", $captioned->attr('caption'));
        $t->same(['center', 'left', 'right', 'left'], $captioned->attr('alignments'));
        $t->same([0.15, 0.1375, 0.1625, 0.35], $captioned->attr('widths'));
        $t->same("Centered\nHeader", $head->children[0]->attr('text'));
        $t->same(['text', 'softbreak', 'text'], array_map(static fn ($node): string => $node->type, $head->children[0]->children));
        $t->same('Default aligned', $head->children[3]->attr('text'));
        $t->same(2, count($body->children));
        $t->same("Example of a row that spans\nmultiple lines.", $wrappedCell->attr('text'));
        $t->same(['text', 'softbreak', 'text'], array_map(static fn ($node): string => $node->type, $wrappedCell->children));
        $t->same("Here's another one. Note\nthe blank line between\nrows.", $body->children[1]->children[3]->attr('text'));
        $t->same('table', $withoutCaption->type);
        $t->same('', $withoutCaption->attr('caption'));
        $t->same(['center', 'left', 'right', 'left'], $withoutCaption->attr('alignments'));
    },
    'maps upstream tables multiline syntax without column headers' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Multiline table without column headers:',
            '',
            '  ----------- ---------- ------------ ---------------------------',
            '     First    row                12.0 Example of a row that spans',
            '                                      multiple lines.',
            '',
            '    Second    row                 5.0 Here\'s another one. Note',
            '                                      the blank line between',
            '                                      rows.',
            '  ----------- ---------- ------------ ---------------------------',
        ]));
        $table = $document->children[1];

        $t->same('table', $table->type);
        $t->same([], $table->children[0]->children);
        $t->same(['center', 'left', 'right', 'default'], $table->attr('alignments'));
        $t->same([0.15, 0.1375, 0.1625, 0.35], $table->attr('widths'));
        $t->same(2, count($table->children[1]->children));
        $t->same('First', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same("Example of a row that spans\nmultiple lines.", $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same("Here's another one. Note\nthe blank line between\nrows.", $table->children[1]->children[1]->children[3]->attr('text'));
    },
    'maps upstream pipe tables with captions alignments and body rows' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Simple table with caption:',
            '',
            '| Right | Left | Default | Center |',
            '| ----: | :--- | ------- | :----: |',
            '|   12  |  12  |    12   |    12  |',
            '|  123  |  123 |   123   |   123  |',
            '|    1  |    1 |     1   |     1  |',
            '',
            '  : Demonstration of simple table syntax.',
        ]));
        $table = $document->children[1];
        $head = $table->children[0];
        $body = $table->children[1];

        $t->same('paragraph', $document->children[0]->type);
        $t->same('table', $table->type);
        $t->same('Demonstration of simple table syntax.', $table->attr('caption'));
        $t->same(['right', 'left', 'default', 'center'], $table->attr('alignments'));
        $t->same('table_head', $head->type);
        $t->same('Right', $head->children[0]->children[0]->attr('text'));
        $t->same('Center', $head->children[0]->children[3]->attr('text'));
        $t->same(3, count($body->children));
        $t->same('12', $body->children[0]->children[0]->attr('text'));
        $t->same('123', $body->children[1]->children[2]->attr('text'));
        $t->same('1', $body->children[2]->children[3]->attr('text'));
    },
    'maps table captions as parsed inline content' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '| Item | Status |',
            '| :--- | :----- |',
            '| Posts | ready |',
            '',
            ': **Migration** [audit](https://example.test/audit "Audit") uses `batch` 1987--1999.',
        ]));
        $table = $document->children[0];
        $captionInlines = $table->attr('captionInlines');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('**Migration** [audit](https://example.test/audit "Audit") uses `batch` 1987--1999.', $table->attr('caption'));
        $t->same(true, is_array($captionInlines));
        $t->same('strong', $captionInlines[0]->type);
        $t->same('Migration', $captionInlines[0]->children[0]->attr('text'));
        $t->same('link', $captionInlines[2]->type);
        $t->same('https://example.test/audit', $captionInlines[2]->attr('url'));
        $t->same('Audit', $captionInlines[2]->attr('title'));
        $t->same('code', $captionInlines[4]->type);
        $t->same('batch', $captionInlines[4]->attr('text'));
        $t->contains("1987\u{2013}1999.", $captionInlines[5]->attr('text'));
        $t->contains('<figcaption class="wp-element-caption"><strong>Migration</strong> <a href="https://example.test/audit" title="Audit">audit</a> uses <code>batch</code> 1987–1999.</figcaption>', $blocks);
    },
    'maps upstream command short caption latex table shape' => static function (TestRunner $t): void {
        $latex = <<<'LATEX'
\begin{table}
\caption[short caption]{long caption}
\begin{tabular}{ll}
hi & hi \\
\end{tabular}
\end{table}
LATEX;
        $document = (new MarkdownReader())->read($latex);
        $table = $document->children[0];
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $captionInlines = $table->attr('captionInlines');
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('short caption', $table->attr('shortCaption'));
        $t->same('long caption', $table->attr('caption'));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same('short caption', $shortCaptionInlines[0]->attr('text'));
        $t->same(true, is_array($captionInlines));
        $t->same('long caption', $captionInlines[0]->attr('text'));
        $t->same(['left', 'left'], $table->attr('alignments'));
        $t->same([], $table->children[0]->children);
        $t->same(1, count($body->children));
        $t->same('hi', $body->children[0]->children[0]->attr('text'));
        $t->same('hi', $body->children[0]->children[1]->attr('text'));
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="short caption">', $blocks);
        $t->contains('<figcaption class="wp-element-caption">long caption</figcaption>', $blocks);
    },
    'maps upstream command docbook table cell alignments' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<informaltable frame="all" rowsep="1" colsep="1">
<tgroup cols="16">
<tbody>
<row>
<entry align="center" valign="top"><simpara>1</simpara></entry>
<entry align="left" valign="top"><simpara>2</simpara></entry>
<entry align="right" valign="top"><simpara>3</simpara></entry>
<entry align="justify" valign="top"><simpara>4</simpara></entry>
</row>
</tbody>
</tgroup>
</informaltable>
XML;
        $document = (new MarkdownReader())->read($docbook);
        $table = $document->children[0];
        $row = $table->children[1]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(['default', 'default', 'default', 'default'], $table->attr('alignments'));
        $t->same([], $table->children[0]->children);
        $t->same('center', $row->children[0]->attr('align'));
        $t->same('left', $row->children[1]->attr('align'));
        $t->same('right', $row->children[2]->attr('align'));
        $t->same('default', $row->children[3]->attr('align', 'default'));
        $t->same('1', $row->children[0]->attr('text'));
        $t->same('4', $row->children[3]->attr('text'));
        $t->contains('<td style="text-align:center">1</td><td style="text-align:left">2</td><td style="text-align:right">3</td><td>4</td>', $blocks);
    },
    'maps upstream command docbook table column spans and colspec widths' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<informaltable frame="all" rowsep="1" colsep="1">
<tgroup cols="16">
<colspec colname="col_1" colwidth="6.25*"/>
<colspec colname="col_2" colwidth="6.25*"/>
<colspec colname="col_3" colwidth="6.25*"/>
<colspec colname="col_4" colwidth="6.25*"/>
<colspec colname="col_5" colwidth="6.25*"/>
<colspec colname="col_6" colwidth="6.25*"/>
<colspec colname="col_7" colwidth="6.25*"/>
<colspec colname="col_8" colwidth="6.25*"/>
<colspec colname="col_9" colwidth="6.25*"/>
<colspec colname="col_10" colwidth="6.25*"/>
<colspec colname="col_11" colwidth="6.25*"/>
<colspec colname="col_12" colwidth="6.25*"/>
<colspec colname="col_13" colwidth="6.25*"/>
<colspec colname="col_14" colwidth="6.25*"/>
<colspec colname="col_15" colwidth="6.25*"/>
<colspec colname="col_16" colwidth="6.25*"/>
<tbody>
<row>
<entry align="center" valign="top" namest="col_1" nameend="col_8"><simpara><emphasis role="strong">Octet no. 1</emphasis></simpara></entry>
<entry align="center" valign="top" namest="col_2" nameend="col_9"><simpara><emphasis role="strong">Octet no. 2</emphasis></simpara></entry>
</row>
<row>
<entry align="center" valign="top"><simpara>16</simpara></entry>
<entry align="center" valign="top"><simpara>15</simpara></entry>
<entry align="center" valign="top"><simpara>14</simpara></entry>
<entry align="center" valign="top"><simpara>13</simpara></entry>
<entry align="center" valign="top"><simpara>12</simpara></entry>
<entry align="center" valign="top"><simpara>11</simpara></entry>
<entry align="center" valign="top"><simpara>10</simpara></entry>
<entry align="center" valign="top"><simpara>9</simpara></entry>
<entry align="center" valign="top"><simpara>8</simpara></entry>
<entry align="center" valign="top"><simpara>7</simpara></entry>
<entry align="center" valign="top"><simpara>6</simpara></entry>
<entry align="center" valign="top"><simpara>5</simpara></entry>
<entry align="center" valign="top"><simpara>4</simpara></entry>
<entry align="center" valign="top"><simpara>3</simpara></entry>
<entry align="center" valign="top"><simpara>2</simpara></entry>
<entry align="center" valign="top"><simpara>1</simpara></entry>
</row>
<row>
<entry align="center" valign="top" namest="col_1" nameend="col_8"><simpara>Code A</simpara></entry>
<entry align="center" valign="top" namest="col_2" nameend="col_9"><simpara>Code B</simpara></entry>
</row>
</tbody>
</tgroup>
</informaltable>
XML;
        $document = (new MarkdownReader())->read($docbook);
        $table = $document->children[0];
        $widths = $table->attr('widths');
        $firstRow = $table->children[1]->children[0];
        $secondRow = $table->children[1]->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(16, count($table->attr('alignments')));
        $t->same(16, count($widths));
        $t->same(0.0625, $widths[0]);
        $t->same(8, $firstRow->children[0]->attr('colspan'));
        $t->same(8, $firstRow->children[1]->attr('colspan'));
        $t->same('center', $firstRow->children[0]->attr('align'));
        $t->same('strong', $firstRow->children[0]->children[0]->type);
        $t->same('Octet no. 1', $firstRow->children[0]->children[0]->children[0]->attr('text'));
        $t->same(16, count($secondRow->children));
        $t->same('16', $secondRow->children[0]->attr('text'));
        $t->same('1', $secondRow->children[15]->attr('text'));
        $t->same(8, $table->children[1]->children[2]->children[0]->attr('colspan'));
        $t->contains('<col style="width:6.25%"/>', $blocks);
        $t->contains('<td colspan="8" style="text-align:center"><strong>Octet no. 1</strong></td>', $blocks);
        $t->contains('<td colspan="8" style="text-align:center">Code B</td>', $blocks);
    },
    'maps upstream command row-span table head body and foot shape' => static function (TestRunner $t): void {
        $docbook = <<<'XML'
<informaltable frame="all" rowsep="1" colsep="1">
<tgroup cols="3">
<colspec colname="col_1" colwidth="1*"/>
<colspec colname="col_2" colwidth="1*"/>
<colspec colname="col_3" colwidth="1*"/>
<thead>
<row>
<entry>1</entry>
<entry morerows="1">2</entry>
<entry>3</entry>
</row>
<row>
<entry>1</entry>
<entry>3</entry>
</row>
</thead>
<tbody>
<row>
<entry>1</entry>
<entry morerows="1">2</entry>
<entry>3</entry>
</row>
<row>
<entry>1</entry>
<entry>3</entry>
</row>
<row>
<entry>1</entry>
<entry>2</entry>
<entry>3</entry>
</row>
</tbody>
<tfoot>
<row>
<entry>1</entry>
<entry morerows="1">2</entry>
<entry>3</entry>
</row>
<row>
<entry>1</entry>
<entry>3</entry>
</row>
</tfoot>
</tgroup>
</informaltable>
XML;
        $document = (new MarkdownReader())->read($docbook);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('table_head', $head->type);
        $t->same('table_body', $body->type);
        $t->same('table_foot', $foot->type);
        $t->same(2, $head->children[0]->children[1]->attr('rowspan'));
        $t->same(true, $head->children[0]->children[1]->attr('header'));
        $t->same(2, count($head->children[1]->children));
        $t->same(2, $body->children[0]->children[1]->attr('rowspan'));
        $t->same(3, count($body->children));
        $t->same(2, $foot->children[0]->children[1]->attr('rowspan'));
        $t->contains('<thead><tr><th>1</th><th rowspan="2">2</th><th>3</th></tr><tr><th>1</th><th>3</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td>1</td><td rowspan="2">2</td><td>3</td></tr><tr><td>1</td><td>3</td></tr><tr><td>1</td><td>2</td><td>3</td></tr></tbody>', $blocks);
        $t->contains('<tfoot><tr><td>1</td><td rowspan="2">2</td><td>3</td></tr><tr><td>1</td><td>3</td></tr></tfoot>', $blocks);
    },
    'maps upstream pipe table headerless and side-less forms' => static function (TestRunner $t): void {
        $headerless = (new MarkdownReader())->read(implode("\n", [
            'Headerless table without caption:',
            '',
            '|       |      |        |',
            '|------:|:-----|:------:|',
            '|12|12|12|',
            '|123|123|123|',
            '|1|1|1|',
        ]));
        $withoutSides = (new MarkdownReader())->read(implode("\n", [
            'Table without sides:',
            '',
            'Fruit |Quantity',
            '------|-------:',
            'apple |    5',
            'orange|   17',
            'pear  |  302',
        ]));
        $headerlessTable = $headerless->children[1];
        $sideTable = $withoutSides->children[1];

        $t->same('table', $headerlessTable->type);
        $t->same([], $headerlessTable->children[0]->children);
        $t->same(['right', 'left', 'center'], $headerlessTable->attr('alignments'));
        $t->same('12', $headerlessTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('table', $sideTable->type);
        $t->same(['default', 'right'], $sideTable->attr('alignments'));
        $t->same('Fruit', $sideTable->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Quantity', $sideTable->children[0]->children[0]->children[1]->attr('text'));
        $t->same('302', $sideTable->children[1]->children[2]->children[1]->attr('text'));
    },
    'maps upstream one-column and no-body pipe tables' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'One-column:',
            '',
            '|hi|',
            '|--|',
            '|lo|',
            '',
            'Pipe table with no body:',
            '',
            '| Header |',
            '| ------ |',
        ]));
        $oneColumn = $document->children[1];
        $noBody = $document->children[3];

        $t->same('table', $oneColumn->type);
        $t->same(['default'], $oneColumn->attr('alignments'));
        $t->same('hi', $oneColumn->children[0]->children[0]->children[0]->attr('text'));
        $t->same('lo', $oneColumn->children[1]->children[0]->children[0]->attr('text'));
        $t->same('table', $noBody->type);
        $t->same('Header', $noBody->children[0]->children[0]->children[0]->attr('text'));
        $t->same([], $noBody->children[1]->children);
    },
    'maps upstream pipe table escaped pipes and code span pipes' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Pipe table with tricky cell contents (see #2765):',
            '',
            '|               | IP_gene8-_1st| IP_gene8+_1st|',
            '|:--------------|-------------:|-------------:|',
            '|IP_gene8-_1st  |     1.0000000|     0.4357325|',
            '|IP_gene8+_1st  |     0.4357325|     1.0000000|',
            '|foo`bar|baz`   | and\|escaped |     3.0000000|',
        ]));
        $table = $document->children[1];
        $trickyRow = $table->children[1]->children[2];
        $codeCell = $trickyRow->children[0];
        $escapedCell = $trickyRow->children[1];

        $t->same('table', $table->type);
        $t->same(['left', 'right', 'right'], $table->attr('alignments'));
        $t->same('foo', $codeCell->children[0]->attr('text'));
        $t->same('code', $codeCell->children[1]->type);
        $t->same('bar|baz', $codeCell->children[1]->attr('text'));
        $t->same('and|escaped', $escapedCell->children[0]->attr('text'));
        $t->same('3.0000000', $trickyRow->children[2]->attr('text'));
    },
    'maps remaining upstream pipe table default headerless one-column and width cases' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $simplest = $reader->read(implode("\n", [
            'Simplest table without caption:',
            '',
            '| Default1 | Default2 | Default3 | ',
            ' |----------|----------|----------|',
            '|12|12|12|',
            '|123|123|123|',
            '|1|1|1|',
        ]));
        $simpleNoCaption = $reader->read(implode("\n", [
            'Simple table without caption:',
            '',
            '| Right | Left | Center | ',
            '|------:|:-----|:------:|',
            '|12|12|12|',
            '|123|123|123|',
            '|1|1|1|',
        ]));
        $headerlessOneColumn = $reader->read(implode("\n", [
            'Header-less one-column:',
            '',
            '|   |',
            '|:-:|',
            '|hi|',
        ]));
        $indentedLeft = $reader->read(implode("\n", [
            'Indented left column:',
            '',
            'Number of siblings | Salary',
            '------------------:|:------',
            '                 3 | 33',
            '                 4 | 44',
        ]));
        $relativeWidths = $reader->read(implode("\n", [
            'Long pipe table with relative widths:',
            '',
            '| Default1 | Default2 | Default3 |',
            ' |---------|----------|---------------------------------------|',
            '|123|this is a table cell|and this is a really long table cell that will probably need wrapping|',
            '|123|123|123|',
        ]));

        $simplestTable = $simplest->children[1];
        $noCaptionTable = $simpleNoCaption->children[1];
        $headerlessTable = $headerlessOneColumn->children[1];
        $indentedTable = $indentedLeft->children[1];
        $widthTable = $relativeWidths->children[1];

        $t->same('table', $simplestTable->type);
        $t->same('', $simplestTable->attr('caption'));
        $t->same(['default', 'default', 'default'], $simplestTable->attr('alignments'));
        $t->same('Default3', $simplestTable->children[0]->children[0]->children[2]->attr('text'));
        $t->same('1', $simplestTable->children[1]->children[2]->children[2]->attr('text'));
        $t->same(['right', 'left', 'center'], $noCaptionTable->attr('alignments'));
        $t->same('Center', $noCaptionTable->children[0]->children[0]->children[2]->attr('text'));
        $t->same('123', $noCaptionTable->children[1]->children[1]->children[0]->attr('text'));
        $t->same('table', $headerlessTable->type);
        $t->same([], $headerlessTable->children[0]->children);
        $t->same(['center'], $headerlessTable->attr('alignments'));
        $t->same('hi', $headerlessTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same(['right', 'left'], $indentedTable->attr('alignments'));
        $t->same('Number of siblings', $indentedTable->children[0]->children[0]->children[0]->attr('text'));
        $t->same('3', $indentedTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('44', $indentedTable->children[1]->children[1]->children[1]->attr('text'));
        $t->same([9 / 58, 10 / 58, 39 / 58], $widthTable->attr('widths'));
        $t->same('this is a table cell', $widthTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('and this is a really long table cell that will probably need wrapping', $widthTable->children[1]->children[0]->children[2]->attr('text'));
    },
    'writes wordpress block output from ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("# Title\n\nParagraph with **strong** text and [source](https://example.test).\n\n- One\n- Two\n\n3. First\n4. Second");
        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<p>Paragraph with <strong>strong</strong> text and <a href="https://example.test">source</a>.</p>', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->contains('<ul><li>One</li><li>Two</li></ul>', $blocks);
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3"><li>First</li><li>Second</li></ol>', $blocks);
    },
    'writes wordpress reference link titles and autolinks from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<a href="/wp-admin/post.php?post=42&amp;action=edit" title="Edit imported post">migration checklist</a>', $blocks);
        $t->contains('<a href="https://example.test/uploads/legacy%20media%20file.jpg" title="Legacy media file">legacy media file</a>', $blocks);
        $t->contains('<a href="/wp-content/uploads/import%20batch%2042.csv" title="Batch manifest">spaced batch manifest</a>', $blocks);
        $t->contains('<a href="https://example.test/audit?post=42&amp;status=ready">https://example.test/audit?post=42&amp;status=ready</a>', $blocks);
        $t->contains('<p>Bare URI audit: <a href="http://example.test/import?post=42&amp;stage=bare">http://example.test/import?post=42&amp;stage=bare</a>. Keep (<a href="https://example.test/media_(legacy)">https://example.test/media_(legacy)</a>) visible.</p>', $blocks);
        $t->contains('<p>Source URI audit: <a href="doi:10.1000/182">doi:10.1000/182</a>, <a href="git://github.com/example/wp-migration.git">git://github.com/example/wp-migration.git</a>, <a href="file:///Users/editor/imports/batch-42.csv">file:///Users/editor/imports/batch-42.csv</a>, and <a href="mailto:migration@example.test">mailto:migration@example.test</a>.</p>', $blocks);
        $t->contains('<p>Extended source URL audit: <a href="http://el.wikipedia.org/wiki/Τεχνολογία">http://el.wikipedia.org/wiki/Τεχνολογία</a>, <a href="http://www.rubyonrails.com/~minam/url%20with%20spaces">http://www.rubyonrails.com/~minam/url%20with%20spaces</a>, and <a href="http://www.mail-archive.com/rails@lists.rubyonrails.org/">http://www.mail-archive.com/rails@lists.rubyonrails.org/</a>.</p>', $blocks);
        $t->contains('<a href="https://example.test/review-token" id="review-token" class="source-link" data-source="batch-42" title="Review token">https://example.test/review-token</a>', $blocks);
        $t->contains('<a href="mailto:importer@example.test">importer@example.test</a>', $blocks);
        $t->contains('<a href="http://测.com?测=测">http://测.com?测=测</a>', $blocks);
        $t->contains('<a href="/bar/测?x=测" title="Translated media">translated media</a>', $blocks);
        $t->contains('<a href="mailto:测@foo.测.baz">测@foo.测.baz</a>', $blocks);
        $t->contains('<a href="/ürl" title="öö!">legacy umlaut media</a>', $blocks);
        $t->contains('<a href="http://göögle.com">http://göögle.com</a>', $blocks);
        $t->contains('<a href="mailto:me@exämple.com">me@exämple.com</a>', $blocks);
        $t->contains('<p>Emoji shortcode audit: <span class="emoji" data-emoji="smile">😄</span> and <span class="emoji" data-emoji="+1">👍</span> keep reviewer reactions visible without importing external assets.</p>', $blocks);
        $t->contains('<p>Wiki link audit: <a href="https://example.test/runbook" class="wikilink">Migration runbook</a> and <a href="Legacy import checklist" class="wikilink">Legacy import checklist</a> keep legacy wiki shortcuts visible.</p>', $blocks);
        $t->contains('<a href="https://example.test/review-label">&lt;https://example.test/source&gt;</a>', $blocks);
        $t->contains('<a href="https://example.test/link-label-audit">[edit link](/wp-admin/post.php?post=42&amp;action=edit)</a>', $blocks);
        $t->contains('<a href="https://example.test/bare-uri-label">https://example.test/raw-source(</a>', $blocks);
        $t->contains('<a href="/hi(there)">campaign landing</a>', $blocks);
        $t->contains('<a href="hi_(there_(nested))">nested reference</a>', $blocks);
        $t->contains('<p>Inline code attribute audit: <code id="enqueue-call" class="php" data-source="batch-42" title="Import source token">wp_enqueue_script</code> stays tagged for reviewer tooling.</p>', $blocks);
        $t->contains('<p>Backslash link label audit: <a href="b">*<span class="pandoc-raw-tex">\a</span></a> keeps escaped import markers visible.</p>', $blocks);
        $t->contains('<p>Backslash escape source audit: <a href="/there)">escaped closing paren</a> and <a href="/there" title="a&quot;a">escaped title</a> keep migration links intact.</p>', $blocks);
        $t->contains('<p>Reference escape source audit: <a href="/there" title="a)a">escaped reference title</a> and <a href="/there.0">escaped reference url</a> preserve source metadata.</p>', $blocks);
        $t->contains('<p>Fallback source markers: [<em>not a migration link</em>] [<em>no source</em>]…</p>', $blocks);
        $t->contains('<p>Citation-adjacent source link: MapReduce was popularized by <a href="https://example.test/source/mapreduce">Google</a> [@mapreduce] during source review.</p>', $blocks);
        $t->contains('<p>Citation boundary audit: @cita [review-only note] stays source citation text, while @cita <a href="https://example.test/citation-link">source log</a> keeps the reviewer link separate.</p>', $blocks);
        $t->contains('<p>Bracketed review span: <span id="migration-span" class="review-span" data-source="batch-42" title="Migration span"><em>urgent</em> source flag <a href="/wp-admin/post.php?post=42&amp;action=edit">edit</a></span>.</p>', $blocks);
        $t->contains('<p>Review the empty import target before publishing.</p>', $blocks);
        $t->contains('<p><a href="">empty-target</a></p>', $blocks);
        $t->contains('<ol><li>Capture source metadata.</li><li>Review multilingual media URLs.</li></ol>', $blocks);
        $t->contains('<p>Example cross-reference: follow step (2) before publishing.</p>', $blocks);
    },
    'writes wordpress citation boundary imports from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $citationParagraph = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'paragraph'
                && str_starts_with((string) $node->attr('text', ''), 'Citation boundary audit:')
            ) {
                $citationParagraph = $node;
                break;
            }
        }

        $t->true($citationParagraph instanceof AstNode, 'Citation boundary fixture paragraph should parse on the native Markdown path');
        $t->same(['text', 'citation', 'text', 'citation', 'text', 'link', 'text'], array_map(static fn (AstNode $node): string => $node->type, $citationParagraph->children));
        $t->same('cita', $citationParagraph->children[1]->attr('id'));
        $t->same('review-only note', $citationParagraph->children[1]->attr('suffix'));
        $t->same('@cita [review-only note]', $citationParagraph->children[1]->attr('text'));
        $t->same('cita', $citationParagraph->children[3]->attr('id'));
        $t->same('@cita', $citationParagraph->children[3]->attr('text'));
        $t->same('https://example.test/citation-link', $citationParagraph->children[5]->attr('url'));
        $t->same('source log', $citationParagraph->children[5]->children[0]->attr('text'));
    },
    'writes wordpress markdown hard breaks and multiline code spans from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Line break handoff: keep source line<br/>attached to reviewer continuation with <code>hi there</code> code span.</p>', $blocks);
    },
    'writes wordpress image blocks and inline media from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('<figure class="wp-block-image"><img src="https://example.test/uploads/release-frame.jpg" alt="Release archive frame" title="Release archive frame"/><figcaption>Release archive frame</figcaption></figure>', $blocks);
        $t->contains('<p>Inline media audit: <img src="https://example.test/uploads/thumb.jpg" alt="thumbnail" title="Thumbnail title"/> remains in paragraph text.</p>', $blocks);
        $t->contains('<figure class="wp-block-image" data-pandoc-latex-placement="htbp"><img src="https://example.test/uploads/reviewer-gallery.jpg" alt="Reviewer gallery alt text"/><figcaption>Reviewer gallery</figcaption></figure>', $blocks);
    },
    'writes wordpress footnote endnotes from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Footnote audit: migration source<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> and inline editor note.<sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup></p>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol>', $blocks);
        $t->contains('<li id="fn-1"><p>Source archive footnote keeps the reviewer trail.</p><p>Confirm media IDs before publishing.</p><pre class="wp-block-code"><code>  do_action(&#039;import_note&#039;);</code></pre> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-2"><p>Inline note keeps <a href="https://example.test/audit-footnote">audit link</a> and <code>]</code> marker visible.</p> <a href="#fnref-2" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'writes nested wordpress list markup from upstream-shaped ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("* a\n* b\n* c\n    * d");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<ul><li>a</li><li>b</li><li>c<ul><li>d</li></ul></li></ul>', $blocks);
    },
    'writes wordpress task list checkboxes from migration review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<ul class="task-list"><li><label><input type="checkbox" />Confirm imported task lists</label></li><li><label><input type="checkbox" checked="" />Keep completed reviewer tasks</label><ul class="task-list"><li><label><input type="checkbox" />Attach media checklist follow-up</label></li></ul></li></ul>', $blocks);
    },
    'writes wordpress loose list paragraphs from migration follow-up steps' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<li><p>Record reviewer follow-up.</p><p>Confirm shortcode cleanup in the migration log.</p></li>', $blocks);
    },
    'writes wordpress imported fancy ordered lists with nested starts' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<ul><li>Source intake</li><li>Media audit</li></ul>', $blocks);
        $t->contains('<ol><li>Prepare import batch</li><li>Confirm block output</li></ol>', $blocks);
        $t->contains('<ol type="a"><li>Editorial review</li><li>Publish handoff</li></ol>', $blocks);
        $t->contains('<ol start="2"><li>Confirm source identifiers</li><li>Schedule staged import<ol start="4" type="i"><li>Review roman checkpoint</li><li>Approve nested audit</li></ol></li></ol>', $blocks);
    },
    'writes wordpress definition list html from upstream-shaped ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("Plugin\n: Stable release\n\nChecklist\n:   - Verify imports");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<dl><dt>Plugin</dt><dd>Stable release</dd><dt>Checklist</dt><dd><ul><li>Verify imports</li></ul></dd></dl>', $blocks);
    },
    'writes wordpress html for div-wrapped upstream definition list' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("<div>Import audit\n:   - Preserve migration notes\n</div>");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<div><dl><dt>Import audit</dt><dd><ul><li>Preserve migration notes</li></ul></dd></dl></div>', $blocks);
    },
    'writes wordpress definition paragraphs from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<dt>Import note</dt><dd>Keep the archive URL attached and mention reviewer follow-up.</dd>', $blocks);
        $t->contains('<dt>Cleanup pass</dt><dd><p>Check legacy shortcodes after block conversion.</p><p>Record manual remediation notes.</p></dd>', $blocks);
        $t->contains('<div><dl><dt>Migration audit</dt><dd><ul><li>Preserve div-wrapped glossary notes from legacy imports</li></ul></dd></dl></div>', $blocks);
    },
    'writes wordpress alternate definition marker notes with nested review tasks' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<dt>Source glossary</dt><dd><p>Preserve alternate marker notes from older Pandoc exports.</p></dd><dd><p>Verify nested review tasks</p><ol><li>Confirm block conversion</li><li>Attach media IDs</li></ol></dd>', $blocks);
    },
    'writes wordpress raw html blocks for imported tables comments and custom dividers' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<table>' . "\n" . '<tr>' . "\n" . '<td><em>Legacy caption</em></td>' . "\n" . '<td><strong>Reviewer flag</strong></td>' . "\n" . '</tr>' . "\n" . '</table>', $blocks);
        $t->contains('<p>Empty import audit table:</p>', $blocks);
        $t->true(!str_contains($blocks, '<table>' . "\n" . '<tbody>' . "\n" . '</tbody>' . "\n" . '</table>'), 'Empty fixture tables should not become raw HTML blocks');
        $t->true(!str_contains($blocks, '<table>' . "\n" . '</table>'), 'Empty fixture tables should be omitted');
        $t->contains('<p>Markdown raw HTML boundary audit:</p>', $blocks);
        $t->contains('<p>Legacy raw deletion boundary</p>', $blocks);
        $t->contains('<!-- Preserve migration audit marker -->', $blocks);
        $t->contains('<hr class="legacy-import-divider" />', $blocks);
    },
    'writes wordpress headerless html reader table blocks for plain import grids' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $plainTable = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'table'
                && ($node->children[0] ?? null)?->type === 'table_head'
                && ($node->children[0] ?? null)?->children === []
                && ($node->children[1] ?? null)?->children[0]?->children[0]?->attr('text') === 'Draft posts'
            ) {
                $plainTable = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($plainTable !== null, 'Plain td-only HTML import grid should use the native table path');
        $t->contains('<p>Plain HTML reader import table:</p>', $blocks);
        $t->contains('<tbody><tr><td>Draft posts</td><td>12</td><td>Needs review</td></tr><tr><td>Media files</td><td>7</td><td>Ready</td></tr></tbody>', $blocks);
    },
    'writes wordpress body-headed html reader table blocks for import queues' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $bodyHeadedTable = null;
        foreach ($document->children as $node) {
            $body = $node->children[1] ?? null;
            if (
                $node->type === 'table'
                && $body?->type === 'table_body'
                && ($body->attr('headRows')[0] ?? null)?->children[0]?->attr('text') === 'Queue'
            ) {
                $bodyHeadedTable = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($bodyHeadedTable !== null, 'Body-local HTML reader head rows should stay on the native table path');
        $t->same(1, $bodyHeadedTable->children[1]->attr('headRowCount'));
        $t->same('Posts', $bodyHeadedTable->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<p>Body-headed HTML reader import table:</p>', $blocks);
        $t->contains('<tbody data-batch="audit"><tr><th>Queue</th><th>Items</th><th>Status</th></tr><tr><td>Posts</td><td>42</td><td>Ready</td></tr><tr><td colspan="3">Review body-local headers before publish</td></tr></tbody><tfoot><tr><th>Total</th><td>42</td><td>Ready</td></tr></tfoot>', $blocks);
    },
    'writes wordpress html reader quote citations and hard breaks from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $quotedParagraph = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'paragraph'
                && ($node->children[1] ?? null)?->type === 'quoted'
                && ($node->children[1]->children[0] ?? null)?->type === 'span'
            ) {
                $quotedParagraph = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($quotedParagraph !== null, 'HTML reader quote/cite paragraph should stay semantic on the native path');
        $t->same(['text', 'quoted', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $quotedParagraph->children));
        $t->contains('<p>HTML reader quote import paragraph:</p>', $blocks);
        $t->contains('<p>Reviewer source says “<span cite="https://example.test/import-log#quote">ready for block import</span>”<br/>Confirm citation metadata before publishing.</p>', $blocks);
    },
    'writes wordpress html reader editorial inline marks from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $markedParagraph = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'paragraph'
                && ($node->children[0] ?? null)?->type === 'small_caps'
            ) {
                $markedParagraph = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($markedParagraph !== null, 'HTML reader editorial marks should stay semantic on the native path');
        $t->same(['small_caps', 'text', 'underline', 'text', 'underline', 'text', 'strikeout', 'text', 'strikeout', 'text', 'strikeout', 'text'], array_map(static fn ($node): string => $node->type, $markedParagraph->children));
        $t->contains('<p>HTML reader editorial inline marks:</p>', $blocks);
        $t->contains('<p><span style="font-variant:small-caps">source glossary</span> flags <u>underlined source text</u>, <u>inserted reviewer note</u>, <del>stale caption</del>, <del>old shortcode</del>, and <del>deleted widget</del>.</p>', $blocks);
    },
    'writes wordpress html reader nested emphasis and code spans from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $nestedParagraph = null;
        $codeParagraph = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'paragraph'
                && ($node->children[0] ?? null)?->type === 'strong'
                && ($node->children[0]->children[0] ?? null)?->type === 'emph'
            ) {
                $nestedParagraph = $node;
            }
            if (
                $node->type === 'paragraph'
                && count(array_filter($node->children, static fn (AstNode $child): bool => $child->type === 'code')) === 3
                && str_contains((string) $node->attr('text'), 'Legacy block source')
            ) {
                $codeParagraph = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($nestedParagraph !== null, 'HTML reader nested strong/emphasis should stay semantic on the native path');
        $t->same('Urgent media cleanup', $nestedParagraph->children[0]->children[0]->children[0]->attr('text'));
        $t->true($codeParagraph !== null, 'HTML reader code spans should stay semantic on the native path');
        $t->same(['text', 'code', 'text', 'code', 'text', 'code', 'text'], array_map(static fn (AstNode $node): string => $node->type, $codeParagraph->children));
        $t->contains('<p><strong><em>Urgent media cleanup</em></strong> stays nested for reviewer emphasis.</p>', $blocks);
        $t->contains('<p>Legacy block source: <code>&lt;!-- wp:paragraph --&gt;</code>, <code>$post_id</code>, and <code>\$literal</code>.</p>', $blocks);
    },
    'writes wordpress pipe table blocks for import metrics' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<figure class="wp-block-table"><table><thead><tr><th style="text-align:left">Item</th><th style="text-align:right">Count</th><th style="text-align:left">Notes</th></tr></thead><tbody>', $blocks);
        $t->contains('<tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:left"><strong>ready</strong></td></tr>', $blocks);
        $t->contains('<tr><td style="text-align:left">Media</td><td style="text-align:right">7</td><td style="text-align:left">needs <code>alt</code></td></tr>', $blocks);
        $t->contains('<figcaption class="wp-element-caption"><strong>Migration</strong> <a href="/wp-admin/post.php?post=42&amp;action=edit" title="Edit imported post">batch summary</a> for <code>wp_posts</code>.</figcaption>', $blocks);
    },
    'writes wordpress relative width pipe table colgroups from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<table><colgroup><col style="width:15.5172%"/><col style="width:17.2414%"/><col style="width:67.2414%"/></colgroup><thead>', $blocks);
        $t->contains('<tr><th>Field</th><th>Count</th><th>Review Notes</th></tr>', $blocks);
        $t->contains('<tr><td>Posts</td><td>42</td><td>This long reviewer note should keep the wide column for migration summaries</td></tr>', $blocks);
        $t->contains('<tr><td>Media</td><td>7</td><td>Check <code>alt</code> text before publish</td></tr>', $blocks);
    },
    'writes wordpress grid table blocks for legacy import queues' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Grid table import queue:</p>', $blocks);
        $t->contains('<table><colgroup><col style="width:26.3889%"/><col style="width:16.6667%"/><col style="width:18.0556%"/></colgroup><thead>', $blocks);
        $t->contains('<th style="text-align:right">Source</th><th style="text-align:left">Count</th><th style="text-align:center">Status</th>', $blocks);
        $t->contains('<td style="text-align:right">Posts</td><td style="text-align:left">42</td><td style="text-align:center">ready</td>', $blocks);
        $t->contains("<td style=\"text-align:right\">Media files</td><td style=\"text-align:left\">108</td><td style=\"text-align:center\">needs alt\ntext</td>", $blocks);
        $t->contains('<p>Grid table block-rich import queue:</p>', $blocks);
        $t->contains('<td><h1 id="source">Source</h1><p>Source</p></td><td><h1 id="count">Count</h1><p>Count</p></td><td><h1 id="status">Status</h1><p>Status</p></td>', $blocks);
        $t->contains('<td><p>Posts</p><p>Review notes</p></td><td><ul><li>42</li><li>staged</li><li>signed</li></ul></td>', $blocks);
        $t->contains('<p>Grid table span import queue:</p>', $blocks);
        $t->contains('<th colspan="2">Review scope</th><th>Batch 42</th>', $blocks);
        $t->contains("<td rowspan=\"3\">Media audit\n2026-05</td><td>posts</td><td>ready</td>", $blocks);
        $t->contains('<tr><td>files</td><td>pending</td></tr><tr><td>links</td><td>queued</td></tr>', $blocks);
    },
    'writes wordpress simple table blocks for legacy import totals' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<th style="text-align:right">Field</th><th>Count</th><th style="text-align:right">Status</th>', $blocks);
        $t->contains('<td style="text-align:right">Posts</td><td>42</td><td style="text-align:right">Ready</td>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Legacy simple-table summary.</figcaption>', $blocks);
    },
    'writes wordpress docbook table spans for import audit exports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-docbook-table.xml');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<td colspan="4" style="text-align:center"><strong>Migration Batch 42</strong></td>', $blocks);
        $t->contains('<td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">ready</td><td>editorial</td>', $blocks);
        $t->contains('<td colspan="2" style="text-align:center">Needs media review</td><td colspan="2" style="text-align:center">Ready for block publish</td>', $blocks);
        $t->contains('<td rowspan="2" style="text-align:left">Media review window</td><td colspan="3" style="text-align:center">Initial sweep</td>', $blocks);
        $t->contains('<td colspan="3" style="text-align:center">Follow-up attachments</td>', $blocks);
        $t->contains('<tfoot><tr><td colspan="4" style="text-align:right">Review before publish</td></tr></tfoot>', $blocks);
    },
    'writes wordpress multiline simple table blocks for wrapped review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<colgroup><col style="width:15%"/><col style="width:13.75%"/><col style="width:16.25%"/><col style="width:35%"/></colgroup>', $blocks);
        $t->contains("<th style=\"text-align:center\">Section\nName</th><th style=\"text-align:left\">Owner\nTeam</th>", $blocks);
        $t->contains("<td style=\"text-align:left\">Needs reviewer approval\nbefore publish.</td>", $blocks);
        $t->contains('<figcaption class="wp-element-caption">Wrapped legacy review summary.</figcaption>', $blocks);
    },
    'writes wordpress short caption table metadata from latex imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Batch 42">', $blocks);
        $t->contains('<tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td></tr>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Long source table caption for reviewer handoff.</figcaption>', $blocks);
    },
    'writes wordpress underscore and nested emphasis from import review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Reviewer <em>import note</em> flags <strong><em>urgent media cleanup</em></strong> before publishing.</p>', $blocks);
        $t->contains('<p>Reviewer filename audit: <em>foot_ball</em> source marker keeps its inner underscore during import.</p>', $blocks);
        $t->contains('<p>Raw URL guard audit: \\begin remains literal source text when a pasted URL command is incomplete.</p>', $blocks);
        $t->contains('<p>Reviewer emphasis nesting: <em>x <strong>xx</strong> x</em> and <em><strong>a</strong>b <strong>c</strong>d</em>.</p>', $blocks);
        $t->contains("<p>Reviewer softbreak emphasis:\n<em>source review</em> <strong><em>urgent pass</em></strong> keeps line\n<em>source review</em> <strong><em>urgent pass</em></strong> in one paragraph.</p>", $blocks);
    },
    'writes wordpress strikeout superscript and subscript from import review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Chemistry note: H<sub>2</sub>O import and a<sup><em>draft</em></sup> status need <del>legacy cleanup</del>.</p>', $blocks);
        $t->contains('<p>Short script audit: O<sub>2</sub> levels and x<sup>2</sup><em>status</em> annotations stay compact for reviewer notes.</p>', $blocks);
    },
    'writes wordpress smart punctuation from import review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains("<p>Migration editor said, \u{201C}Don\u{2019}t flatten \u{2018}legacy\u{2019} captions\u{2026}\u{201D} Keep dates 1987\u{2013}1999 and one\u{2014}two review notes.</p>", $blocks);
        $t->contains("<p>French quote audit: \u{2018}\u{2026}legacy source\u{2019} starts truncated, and À l\u{2019}arrivée de la guerre, le thème de l\u{2019}«impossibilité du socialisme» plus D\u{2019}oh! A l\u{2019}<em>aide</em>! keep Pandoc smart punctuation.</p>", $blocks);
        $t->contains("<p>Unclosed quote audit: <strong>this should \u{201C}be bold</strong> during reviewer import.</p>", $blocks);
        $t->contains("<p>Inline note quote audit: \u{2018}a<sup id=\"fnref-3\"><a href=\"#fn-3\" role=\"doc-noteref\">3</a></sup> c.\u{2019} and \u{201C}a<sup id=\"fnref-4\"><a href=\"#fn-4\" role=\"doc-noteref\">4</a></sup> c.\u{201D} stay nested for reviewer import.</p>", $blocks);
        $t->contains("<li id=\"fn-3\"><p>\u{2018}source quote\u{2019}.</p> <a href=\"#fnref-3\" aria-label=\"Back to content\">Back</a></li>", $blocks);
        $t->contains("<li id=\"fn-4\"><p>\u{201C}review quote\u{201D}.</p> <a href=\"#fnref-4\" aria-label=\"Back to content\">Back</a></li>", $blocks);
    },
    'writes wordpress math and raw tex preservation markup from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<span class="math inline">\(x \in y\)</span>', $blocks);
        $t->contains('<span class="math inline">\(x = \text{the $n$th root of $y$}\)</span>', $blocks);
        $t->contains('<span class="pandoc-raw-tex">\cite[22-23]{smith.1899}</span>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-tex">\newcommand{\wptuple}[1]{\langle #1 \rangle}</code></pre>', $blocks);
        $t->contains('<span class="math inline">\(\langle post_id,media_id \rangle\)</span>', $blocks);
        $t->contains('<span class="math display">\[\alpha + \omega \times x^2\]</span>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-tex">\begin{tabular}{|l|l|}\hline', $blocks);
        $t->contains('Field &amp; Value \\\\ \hline', $blocks);
    },
    'writes wordpress entity decoded text without double escaping import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Entity import note: AT&amp;T sponsor text and 4 &lt; 5 comparator stay visible for review.</p>', $blocks);
        $t->contains("<p>Character reference audit: \u{27E8} ö and ,DD decode before WordPress escaping, while <a href=\"/url\" title=\"title \u{27E8} ö ,\">entity title</a> keeps its title decoded.</p>", $blocks);
        $t->same(false, str_contains($blocks, 'AT&amp;amp;T'));
    },
    'writes wordpress code block markup for migration snippets' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:code -->', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">do_shortcode(&#039;[legacy-gallery]&#039;);</code></pre>', $blocks);
    },
    'writes wordpress indented list code blocks from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Indented list code handoff:</p>', $blocks);
        $t->contains('<ul><li><pre class="wp-block-code"><code>do_action(&#039;pandoc_import_review&#039;);' . "\n" . 'update_post_meta($post_id, &#039;_pandoc_reviewed&#039;, &#039;1&#039;);</code></pre><ul><li>Keep four-space reviewer text as prose, not code.</li></ul></li></ul>', $blocks);
    },
    'writes wordpress literate haskell source docs from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-literate-haskell.md');
        $document = (new MarkdownReader(['literateHaskell' => true]))->read($fixture);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['heading', 'paragraph', 'code_block', 'code_block', 'div', 'paragraph', 'blockquote'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(['haskell', 'literate'], $document->children[2]->attr('classes'));
        $t->same("import Text.Pandoc\nrenderBlocks post = writeBlocks post", $document->children[2]->attr('text'));
        $t->same(['haskell'], $document->children[3]->attr('classes'));
        $t->same("migrationBatch = 42\nkeepReviewerNotes = True", $document->children[3]->attr('text'));
        $t->same('Reviewer note stays a quote, not literate code.', $document->children[6]->children[0]->attr('text'));
        $t->contains('<h1 id="literate-import-notes">Literate Import Notes</h1>', $blocks);
        $t->contains("<pre class=\"wp-block-code\"><code class=\"language-haskell\">import Text.Pandoc\nrenderBlocks post = writeBlocks post</code></pre>", $blocks);
        $t->contains("<pre class=\"wp-block-code\"><code class=\"language-haskell\">migrationBatch = 42\nkeepReviewerNotes = True</code></pre>", $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note stays a quote, not literate code.</p></blockquote>', $blocks);
    },
    'writes wordpress html reader pre code imports from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>HTML reader legacy code export:</p>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">do_shortcode(&#039;[legacy-carousel]&#039;);' . "\n" . 'echo esc_html($title);</code></pre>', $blocks);
    },
    'writes wordpress html reader blockquote imports from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $htmlQuote = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'blockquote'
                && ($node->children[0] ?? null)?->type === 'paragraph'
                && ($node->children[0]->attr('text') === 'Reviewer checklist:')
            ) {
                $htmlQuote = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($htmlQuote !== null, 'HTML reader blockquote import should stay on the native quote path');
        $t->same(['paragraph', 'code_block', 'ordered_list', 'blockquote'], array_map(static fn ($node): string => $node->type, $htmlQuote->children));
        $t->same(['php'], $htmlQuote->children[1]->attr('classes'));
        $t->same('Publish block version', $htmlQuote->children[2]->children[1]->children[0]->attr('text'));
        $t->contains('<p>HTML reader blockquote import:</p>', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer checklist:</p><pre class="wp-block-code"><code class="language-php">wp_update_post($post);</code></pre><ol><li>Confirm source quote</li><li>Publish block version</li></ol><blockquote><p>Nested reviewer approval stays attached.</p></blockquote></blockquote>', $blocks);
    },
    'writes wordpress html reader list imports from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $htmlList = null;
        $styledOrdered = null;
        $rawHtmlList = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'bullet_list'
                && ($node->children[0] ?? null)?->children[0]?->attr('text') === 'Review imported posts'
            ) {
                $htmlList = $node;
            }
            if (
                $node->type === 'bullet_list'
                && ($node->children[0] ?? null)?->children[0]?->type === 'div'
            ) {
                $rawHtmlList = $node;
            }
            if ($node->type === 'ordered_list' && $node->attr('start') === 4 && $node->attr('style') === 'lower_roman') {
                $styledOrdered = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($htmlList !== null, 'HTML reader top-level ul should stay on the native list path');
        $t->true($rawHtmlList !== null, 'Markdown list item with raw HTML blocks should stay one native list item');
        $t->true($styledOrdered !== null, 'HTML reader styled ol should preserve ordered-list metadata');
        $t->same('bullet_list', $htmlList->children[1]->children[1]->type);
        $t->same('Verify captions', $htmlList->children[1]->children[1]->children[1]->children[0]->attr('text'));
        $t->same(['div', 'raw_html', 'plain', 'raw_html', 'div'], array_map(static fn (AstNode $node): string => $node->type, $rawHtmlList->children[0]->children));
        $t->same('paragraph', $styledOrdered->children[0]->children[0]->type);
        $nestedHeading = null;
        $fancyQueue = null;
        foreach ($document->children as $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'HTML reader nested checklist') {
                $nestedHeading = $node;
            }
            if ($node->type === 'ordered_list' && $node->attr('start') === 2 && $node->attr('style') === 'decimal') {
                $fancyQueue = $node;
            }
        }
        $t->true($nestedHeading !== null, 'HTML reader h2 import should stay on the native heading path');
        $t->true($fancyQueue !== null, 'HTML reader fancy nested queue should preserve ordered-list metadata');
        $t->same('ordered_list', $fancyQueue->children[1]->children[2]->type);
        $t->same('upper_alpha', $fancyQueue->children[1]->children[2]->children[1]->children[1]->attr('style'));
        $t->contains('<p>HTML reader list import:</p>', $blocks);
        $t->contains('<ul><li>Review imported posts</li><li>Attach media audit<ul><li>Confirm alt text</li><li>Verify captions</li></ul></li></ul>', $blocks);
        $t->contains('<ol start="4" type="i"><li><p>Queue editorial pass</p></li><li><p>Publish reviewed batch</p></li></ol>', $blocks);
        $t->contains('<p>HTML reader list raw block import:</p>', $blocks);
        $t->contains('<ul><li><div><p>first migration div stays inside the review list</p></div><button>confirm source button</button><div><p>second migration div stays attached too.</p></div></li></ul>', $blocks);
        $t->contains('<h2 id="html-reader-nested-checklist">HTML reader nested checklist</h2>', $blocks);
        $t->contains('<ul><li>Audit source sections<ul><li>Posts<ul><li>Confirm nested review note</li></ul></li></ul></li></ul>', $blocks);
        $t->contains('<ol start="2"><li>Import source batch</li><li><p>Review media mapping</p><p>Record continuation note</p><ol start="4" type="i"><li>Check roman subqueue</li><li>Escalate captions<ol type="A"><li>Alt text</li><li>Credit line</li></ol></li></ol></li></ol>', $blocks);
    },
    'writes wordpress html reader definition imports from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $htmlDefinitionList = null;
        foreach ($document->children as $node) {
            if (
                $node->type === 'definition_list'
                && ($node->children[0] ?? null)?->attr('term') === 'Migration glossary'
            ) {
                $htmlDefinitionList = $node;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($htmlDefinitionList !== null, 'HTML reader dl import should stay on the native definition-list path');
        $t->same(2, count($htmlDefinitionList->children));
        $t->same('Reviewer FAQ entry.', $htmlDefinitionList->children[0]->children[2]->children[0]->attr('text'));
        $t->same("Reusable block\nSynced pattern", $htmlDefinitionList->children[1]->attr('term'));
        $t->same('linebreak', $htmlDefinitionList->children[1]->children[0]->children[1]->type);
        $t->contains('<p>HTML reader definition import:</p>', $blocks);
        $t->contains('<dl><dt>Migration glossary</dt><dd>Source term list.</dd><dd>Reviewer FAQ entry.</dd><dt>Reusable block<br/>Synced pattern</dt><dd>Shared block-era naming stays linked.</dd></dl>', $blocks);
    },
    'writes wordpress html reader empty emphasis and emphasized link imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $emptyMarkup = null;
        $emphasizedLink = null;
        foreach ($document->children as $node) {
            if ($node->type !== 'paragraph') {
                continue;
            }
            if (str_contains((string) $node->attr('text', ''), 'Empty importer marks')) {
                $emptyMarkup = $node;
            }
            if (str_contains((string) $node->attr('text', ''), 'emphasized edit link')) {
                $emphasizedLink = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($emptyMarkup !== null, 'HTML reader empty strong/emphasis paragraph should stay on the native path');
        $t->true($emphasizedLink !== null, 'HTML reader emphasized link paragraph should not be swallowed by implicit paragraph close handling');
        $t->same(['text', 'strong', 'text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $emptyMarkup->children));
        $t->same(0, count($emptyMarkup->children[1]->children));
        $t->same(0, count($emptyMarkup->children[3]->children));
        $t->same('emph', $emphasizedLink->children[1]->type);
        $t->same('link', $emphasizedLink->children[1]->children[0]->type);
        $t->same('/wp-admin/post.php?post=42&action=edit', $emphasizedLink->children[1]->children[0]->attr('url'));
        $t->contains('<p>Empty importer marks <strong></strong> and <em></em>.</p>', $blocks);
        $t->contains('<p>An <em><a href="/wp-admin/post.php?post=42&amp;action=edit">emphasized edit link</a></em> stays attached to review copy.</p>', $blocks);
    },
    'writes wordpress html reader literal punctuation imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $literalParagraph = null;
        $quotedSource = null;
        foreach ($document->children as $node) {
            if ($node->type !== 'paragraph') {
                continue;
            }
            if (str_contains((string) $node->attr('text', ''), 'HTML source quotes')) {
                $literalParagraph = $node;
            }
            if (str_contains((string) $node->attr('text', ''), 'Quoted HTML source')) {
                $quotedSource = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($literalParagraph !== null, 'HTML reader literal punctuation paragraph should stay on the native HTML path');
        $t->true($quotedSource !== null, 'HTML reader quoted code/link paragraph should stay on the native HTML path');
        $t->same('"HTML source quotes" and 70\'s apostrophe stay literal, with one---two and dates 1987-1999 unchanged.', $literalParagraph->children[0]->attr('text'));
        $t->same(['text', 'code', 'text', 'link', 'text'], array_map(static fn ($node): string => $node->type, $quotedSource->children));
        $t->same('https://example.test/review?item=42&stage=html', $quotedSource->children[3]->attr('url'));
        $t->contains('<p>&quot;HTML source quotes&quot; and 70&#039;s apostrophe stay literal, with one---two and dates 1987-1999 unchanged.</p>', $blocks);
        $t->contains('<p>Quoted HTML source &#039;<code>code</code>&#039; and a &quot;<a href="https://example.test/review?item=42&amp;stage=html">review link</a>&quot; stay literal.</p>', $blocks);
    },
    'writes wordpress html reader latex literal imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $latexHeading = null;
        $latexList = null;
        $literalTableSource = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'HTML reader LaTeX literal import') {
                $latexHeading = $node;
                $latexList = $document->children[$index + 1] ?? null;
                continue;
            }
            if ($node->type === 'paragraph' && str_contains((string) $node->attr('text', ''), '\begin{tabular}{|l|l|}\hline Field')) {
                $literalTableSource = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($latexHeading !== null, 'HTML reader LaTeX heading should stay on the native HTML path');
        $t->true($latexList instanceof AstNode && $latexList->type === 'bullet_list', 'HTML reader LaTeX list should stay a native list');
        $t->same('\cite[22-23]{smith.1899}', $latexList->children[0]->children[0]->attr('text'));
        $t->same('$x \in y$', $latexList->children[1]->children[0]->attr('text'));
        $t->same('Here\'s the source math literal: $\alpha + \omega \times x^2$.', $latexList->children[2]->children[0]->attr('text'));
        $t->true($literalTableSource !== null, 'HTML reader LaTeX table source should remain a literal paragraph');
        $t->contains('<h2 id="html-reader-latex-literal-import">HTML reader LaTeX literal import</h2>', $blocks);
        $t->contains('<ul><li>\cite[22-23]{smith.1899}</li><li>$x \in y$</li><li>Here&#039;s the source math literal: $\alpha + \omega \times x^2$.</li></ul>', $blocks);
        $t->contains('<p>\begin{tabular}{|l|l|}\hline Field &amp; Value \\\\ \hline Posts &amp; 42 \\\\ \hline \end{tabular}</p>', $blocks);
        $latexFragment = substr($blocks, strpos($blocks, '<h2 id="html-reader-latex-literal-import">') ?: 0);
        $latexFragment = substr($latexFragment, 0, strpos($latexFragment, '<p>Empty import audit table:</p>') ?: strlen($latexFragment));
        $t->same(false, str_contains($latexFragment, 'pandoc-raw-tex'));
        $t->same(false, str_contains($latexFragment, 'class="math'));
    },
    'writes wordpress html reader special character imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $specialHeading = null;
        $unicodeList = null;
        $comparisonParagraph = null;
        $escapeParagraph = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'HTML reader special characters import') {
                $specialHeading = $node;
                $unicodeList = $document->children[$index + 2] ?? null;
                continue;
            }
            if ($node->type === 'paragraph' && $node->attr('text') === '4 < 5 and 6 > 5 stay text for reviewer copy.') {
                $comparisonParagraph = $node;
            }
            if ($node->type === 'paragraph' && $node->attr('text') === 'Escapes stay literal: \\ ` * _ { } [ ] ( ) > # . ! + -.') {
                $escapeParagraph = $node;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($specialHeading !== null, 'HTML reader special-character heading should stay on the native HTML path');
        $t->true($unicodeList instanceof AstNode && $unicodeList->type === 'bullet_list', 'HTML reader Unicode list should stay a native list');
        $t->same('special-characters', $specialHeading->attr('id'));
        $t->same('I hat: Î', $unicodeList->children[0]->children[0]->attr('text'));
        $t->same('section: §', $unicodeList->children[1]->children[0]->attr('text'));
        $t->true($comparisonParagraph !== null, 'HTML reader comparison punctuation should remain literal text');
        $t->true($escapeParagraph !== null, 'HTML reader escape punctuation should remain literal text');
        $t->contains('<h2 id="special-characters">HTML reader special characters import</h2>', $blocks);
        $t->contains('<ul><li>I hat: Î</li><li>section: §</li><li>set membership: ∈</li><li>copyright: ©</li></ul>', $blocks);
        $t->contains('<p>AT&amp;T import source decodes once and writes safely.</p>', $blocks);
        $t->contains('<p>4 &lt; 5 and 6 &gt; 5 stay text for reviewer copy.</p>', $blocks);
        $t->contains('<p>Escapes stay literal: \ ` * _ { } [ ] ( ) &gt; # . ! + -.</p>', $blocks);
    },
    'writes wordpress html reader link imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $linkHeading = null;
        $reviewLinks = null;
        $referenceLikeText = null;
        $sourceContact = null;
        $emptyLegacyLink = null;
        $codeContext = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'HTML reader link import') {
                $linkHeading = $node;
                $reviewLinks = $document->children[$index + 1] ?? null;
                $referenceLikeText = $document->children[$index + 2] ?? null;
                $sourceContact = $document->children[$index + 3] ?? null;
                $emptyLegacyLink = $document->children[$index + 4] ?? null;
                $codeContext = $document->children[$index + 5] ?? null;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($linkHeading !== null, 'HTML reader link heading should stay on the native HTML path');
        $t->true($reviewLinks instanceof AstNode && $reviewLinks->type === 'paragraph', 'HTML reader explicit links should stay in paragraph nodes');
        $t->true($referenceLikeText instanceof AstNode && $referenceLikeText->type === 'paragraph', 'HTML reader reference-like text should not become Markdown links');
        $t->true($sourceContact instanceof AstNode && $sourceContact->type === 'paragraph', 'HTML reader bare text before a block tag should become its own paragraph');
        $t->true($emptyLegacyLink instanceof AstNode && $emptyLegacyLink->type === 'paragraph', 'HTML reader empty href paragraph should follow the bare text paragraph');
        $t->true($codeContext instanceof AstNode && $codeContext->type === 'paragraph', 'HTML reader link-like code context should stay code');
        $t->same('html-reader-link-import', $linkHeading->attr('id'));
        $t->same('/wp-admin/post.php?post=42&action=edit', $reviewLinks->children[1]->attr('url'));
        $t->same('Edit & verify', $reviewLinks->children[1]->attr('title'));
        $t->same('', $reviewLinks->children[3]->attr('url'));
        $t->same('Reference-like text [legacy-source] stays literal while ', $referenceLikeText->children[0]->attr('text'));
        $t->same('https://example.test/import?post=42&stage=links', $referenceLikeText->children[1]->attr('url'));
        $t->same('HTML reader source contact (importer [at] example.test)', $sourceContact->children[0]->attr('text'));
        $t->same('', $emptyLegacyLink->children[0]->attr('url'));
        $t->same('code', $codeContext->children[1]->type);
        $t->same('<https://example.test/import>', $codeContext->children[1]->attr('text'));
        $t->contains('<h2 id="html-reader-link-import">HTML reader link import</h2>', $blocks);
        $t->contains('<a href="/wp-admin/post.php?post=42&amp;action=edit" title="Edit &amp; verify">source edit link</a>', $blocks);
        $t->contains('<a href="">empty migration placeholder</a>', $blocks);
        $t->contains('<p>Reference-like text [legacy-source] stays literal while <a href="https://example.test/import?post=42&amp;stage=links">audit link</a> stays linked.</p>', $blocks);
        $t->contains('<p>HTML reader source contact (importer [at] example.test)</p>', $blocks);
        $t->contains('<p><a href="">Empty legacy link placeholder</a>.</p>', $blocks);
        $t->contains('<p>Auto-links should not occur here: <code>&lt;https://example.test/import&gt;</code></p>', $blocks);
    },
    'writes wordpress html reader image imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $imageHeading = null;
        $standaloneImageParagraph = null;
        $inlineImageParagraph = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'HTML reader image import') {
                $imageHeading = $node;
                $standaloneImageParagraph = $document->children[$index + 1] ?? null;
                $inlineImageParagraph = $document->children[$index + 2] ?? null;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($imageHeading !== null, 'HTML reader image heading should stay on the native HTML path');
        $t->true($standaloneImageParagraph instanceof AstNode && $standaloneImageParagraph->type === 'paragraph', 'HTML reader standalone image stays a Pandoc-style paragraph image');
        $t->true($inlineImageParagraph instanceof AstNode && $inlineImageParagraph->type === 'paragraph', 'HTML reader inline image stays inside paragraph text');
        $t->same('html-reader-image-import', $imageHeading->attr('id'));
        $t->same('image', $standaloneImageParagraph->children[0]->type);
        $t->same('https://example.test/uploads/html-legacy-frame.jpg', $standaloneImageParagraph->children[0]->attr('url'));
        $t->same('Legacy frame title', $standaloneImageParagraph->children[0]->attr('title'));
        $t->same('Legacy frame', $standaloneImageParagraph->children[0]->attr('alt'));
        $t->same('Inline HTML media ', $inlineImageParagraph->children[0]->attr('text'));
        $t->same('image', $inlineImageParagraph->children[1]->type);
        $t->same('inline icon', $inlineImageParagraph->children[1]->attr('alt'));
        $t->contains('<h2 id="html-reader-image-import">HTML reader image import</h2>', $blocks);
        $t->contains('<figure class="wp-block-image"><img src="https://example.test/uploads/html-legacy-frame.jpg" alt="Legacy frame" title="Legacy frame title"/><figcaption>Legacy frame</figcaption></figure>', $blocks);
        $t->contains('<p>Inline HTML media <img src="https://example.test/uploads/html-inline-icon.jpg" alt="inline icon"/> stays inside reviewer copy.</p>', $blocks);
    },
    'writes wordpress html reader footnote link imports without native note conversion' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $footnoteHeading = null;
        $referenceParagraph = null;
        $backReferenceParagraph = null;
        $codeBlock = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'HTML reader footnote link import') {
                $footnoteHeading = $node;
                $referenceParagraph = $document->children[$index + 1] ?? null;
                $backReferenceParagraph = $document->children[$index + 2] ?? null;
                $codeBlock = $document->children[$index + 3] ?? null;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->true($footnoteHeading !== null, 'HTML reader footnote heading should stay on the native HTML path');
        $t->true($referenceParagraph instanceof AstNode && $referenceParagraph->type === 'paragraph', 'HTML reader note anchors stay as paragraph links');
        $t->true($backReferenceParagraph instanceof AstNode && $backReferenceParagraph->type === 'paragraph', 'HTML reader back-reference anchors stay as paragraph links');
        $t->true($codeBlock instanceof AstNode && $codeBlock->type === 'code_block', 'HTML reader footnote continuation code stays a code block');
        $t->same('html-reader-footnote-link-import', $footnoteHeading->attr('id'));
        $t->same('link', $referenceParagraph->children[1]->type);
        $t->same('#note_editor', $referenceParagraph->children[1]->attr('url'));
        $t->same('emph', $referenceParagraph->children[3]->type);
        $t->same('link', $backReferenceParagraph->children[0]->type);
        $t->same('#ref_editor', $backReferenceParagraph->children[0]->attr('url'));
        $t->same('  wp_insert_post($review_post);', $codeBlock->attr('text'));
        $t->contains('<h2 id="html-reader-footnote-link-import">HTML reader footnote link import</h2>', $blocks);
        $t->contains('<p>Legacy source note<a href="#note_editor">(editor)</a> stays linked, while this <em>not</em> marker stays inline.</p>', $blocks);
        $t->contains('<p><a href="#ref_editor">(editor)</a> Review the source annotation before publishing.</p>', $blocks);
        $t->contains('<pre class="wp-block-code"><code>  wp_insert_post($review_post);</code></pre>', $blocks);
        $t->contains('<p>Reviewer <em>Leading space</em></p>', $blocks);
        $t->contains('<p><em>Trailing space</em> reviewer</p>', $blocks);
    },
    'writes wordpress html reader full document exports with title classes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $meta = $document->attr('meta');
        $titleHeading = null;
        $literalParagraph = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'heading' && $node->attr('text') === 'Imported HTML Batch 42') {
                $titleHeading = $node;
                $literalParagraph = $document->children[$index + 3] ?? null;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['title' => 'Imported HTML Batch 42', 'generator' => 'pandoc'], $meta);
        $t->true($titleHeading instanceof AstNode && $titleHeading->type === 'heading', 'Full HTML export title should become a heading node');
        $t->same(['title'], $titleHeading->attr('classes'));
        $t->true($literalParagraph instanceof AstNode && $literalParagraph->type === 'paragraph', 'HTML export paragraph should stay on the HTML reader path');
        $t->same('Review * stays literal inside HTML paragraphs.', $literalParagraph->attr('text'));
        $t->contains('<h1 id="imported-html-batch-42" class="title">Imported HTML Batch 42</h1>', $blocks);
        $t->contains('<p>Review * stays literal inside HTML paragraphs.</p>', $blocks);
    },
    'writes wordpress code block markup for tab-indented legacy snippets' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("Legacy importer:\n\n\t\techo esc_html(\$title);");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:code -->', $blocks);
        $t->contains('<pre class="wp-block-code"><code>    echo esc_html($title);</code></pre>', $blocks);
    },
    'writes wordpress markdown line block imports' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $document = (new MarkdownReader())->read($fixture);
        $lineBlock = null;
        foreach ($document->children as $index => $node) {
            if ($node->type === 'paragraph' && $node->attr('text') === 'Line block handoff:') {
                $lineBlock = $document->children[$index + 1] ?? null;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($document);
        $nbsp = "\xC2\xA0";

        $t->true($lineBlock instanceof AstNode && $lineBlock->type === 'line_block', 'Fixture line block should stay a line_block AST node');
        $t->same('Reviewer import stanza', $lineBlock->children[0]->attr('text'));
        $t->same(str_repeat($nbsp, 2) . 'preserve source indentation', $lineBlock->children[1]->attr('text'));
        $t->same('', $lineBlock->children[2]->attr('text'));
        $t->same('Continuation line', $lineBlock->children[3]->attr('text'));
        $t->contains('<p>Line block handoff:</p>', $blocks);
        $t->contains('<p>Reviewer import stanza<br/>' . str_repeat($nbsp, 2) . 'preserve source indentation<br/><br/>Continuation line</p>', $blocks);
    },
    'writes wordpress quote block markup for migration reviewer notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:quote -->', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note: keep the archive URL attached to the imported post.</p></blockquote>', $blocks);
    },
    'writes wordpress separator block for imported markdown section breaks' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:separator -->', $blocks);
        $t->contains('<hr class="wp-block-separator has-alpha-channel-opacity"/>', $blocks);
    },
    'escapes wordpress block inline html while preserving marks' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('Use **<unsafe>** and `x < y`.');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<strong>&lt;unsafe&gt;</strong>', $blocks);
        $t->contains('<code>x &lt; y</code>', $blocks);
    },
];
