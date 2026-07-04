<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
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
    'maps upstream raw tex inline control words and starred macros' => static function (TestRunner $t): void {
        $cases = [
            'A \LaTeX B' => ['\LaTeX ', 'LaTeX', 'B'],
            'A \TeX B' => ['\TeX ', 'TeX', 'B'],
            'A \foo,B' => ['\foo', 'foo', ',B'],
            'A \foo[B]C' => ['\foo[B]', 'foo', 'C'],
            'A \newpage' => ['\newpage', 'newpage', null],
            'A \a{b} C' => ['\a{b}', 'a', ' C'],
            'A \a* B' => ['\a* ', 'a', 'B'],
            'A \foo*{y} B' => ['\foo*{y}', 'foo', ' B'],
            'A \foo*[x]{y} B' => ['\foo*[x]{y}', 'foo', ' B'],
        ];

        foreach ($cases as $source => [$tex, $command, $tail]) {
            $paragraph = (new MarkdownReader())->read($source)->children[0];
            $expectedTypes = $tail === null ? ['text', 'raw_tex'] : ['text', 'raw_tex', 'text'];

            $t->same($expectedTypes, array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $source);
            $t->same('A ', $paragraph->children[0]->attr('text'), $source);
            $t->same($tex, $paragraph->children[1]->attr('tex'), $source);
            $t->same($command, $paragraph->children[1]->attr('command'), $source);
            if ($tail !== null) {
                $t->same($tail, $paragraph->children[2]->attr('text'), $source);
            }
        }

        $disabled = (new MarkdownReader(['format' => 'markdown-raw_tex']))->read('A \LaTeX B')->children[0];
        $escaped = (new MarkdownReader())->read('A \% B')->children[0];

        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $disabled->children));
        $t->same('A \LaTeX B', $disabled->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $escaped->children));
        $t->same('A % B', $escaped->attr('text'));
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
    'maps upstream markdown raw tex sectioning command lines as raw blocks' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '\section {Intro}',
            '\subsection[Short]{Long}',
            '\paragraph*{Run in}',
            '\appendix',
            '\tableofcontents',
            '',
            'After sectioning.',
        ]);
        $document = (new MarkdownReader())->read($source);
        $disabled = (new MarkdownReader(['format' => 'markdown-raw_tex']))->read($source);
        $raw = $document->children[0];

        $t->same(['raw_tex', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('sectioning', $raw->attr('command'));
        $t->same(
            '\section {Intro}' . "\n"
                . '\subsection[Short]{Long}' . "\n"
                . '\paragraph*{Run in}' . "\n"
                . '\appendix' . "\n"
                . '\tableofcontents',
            $raw->attr('tex')
        );
        $t->same('After sectioning.', $document->children[1]->attr('text'));
        $t->same(['paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $disabled->children));
        $t->same('\section {Intro} \subsection[Short]{Long} \paragraph*{Run in} \appendix \tableofcontents', $disabled->children[0]->attr('text'));
    },
    'maps upstream markdown raw tex sectioning commands that interrupt paragraphs' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('A \section{Intro} B \subsection[Short]{Long} C');
        $continued = (new MarkdownReader())->read("A \section{Intro} B\nC");
        $code = (new MarkdownReader())->read('A `\section{Intro}` B');
        $escaped = (new MarkdownReader())->read('A \\\\section{Intro} B');
        $macro = (new MarkdownReader())->read('A \newcommand{\x}{y} B');

        $t->same(['plain', 'raw_tex', 'plain', 'raw_tex', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('A', $document->children[0]->attr('text'));
        $t->same('\section{Intro}', $document->children[1]->attr('tex'));
        $t->same('B', $document->children[2]->attr('text'));
        $t->same('\subsection[Short]{Long}', $document->children[3]->attr('tex'));
        $t->same('C', $document->children[4]->attr('text'));

        $t->same(['plain', 'raw_tex', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $continued->children));
        $t->same('A', $continued->children[0]->attr('text'));
        $t->same('B C', $continued->children[2]->attr('text'));
        $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $continued->children[2]->children));

        $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $code->children));
        $t->same(['text', 'code', 'text'], array_map(static fn (AstNode $node): string => $node->type, $code->children[0]->children));
        $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $escaped->children));
        $t->same('A \section{Intro} B', $escaped->children[0]->attr('text'));
        $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $macro->children));
        $t->same(['text', 'raw_tex', 'text'], array_map(static fn (AstNode $node): string => $node->type, $macro->children[0]->children));
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
        $t->same(['text', 'quoted', 'text'], array_map(static fn (AstNode $node): string => $node->type, $document->children[4]->children));
        $t->same('[URL and title](/url/ ', $document->children[4]->children[0]->attr('text'));
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
        $t->same('Foo [bar][].', $document->children[8]->children[0]->attr('text'));
        $t->same(['text', 'quoted', 'text'], array_map(static fn (AstNode $node): string => $node->type, $document->children[9]->children));
        $t->same('Foo [biz](/url/ ', $document->children[9]->children[0]->attr('text'));
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
        $t->contains('<p>MapReduce is a paradigm popularized by <a href="http://google.com">Google</a> <span class="pandoc-citation" data-pandoc-citation-id="mapreduce"', $blocks);
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
        $t->contains('<p><span class="pandoc-citation" data-pandoc-citation-id="cita"', $blocks);
        $t->contains('>@cita</span><sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('>@cita</span> <a href="http://www.com">link</a></p>', $blocks);
        $t->contains('>@cita [foo]</span></p>', $blocks);
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
    'maps upstream command nested spanlike classes into wordpress html wrappers' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('[test]{.foo .underline #bar .smallcaps .kbd}');
        $span = $document->children[0]->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('span', $span->type);
        $t->same('bar', $span->attr('id'));
        $t->same(['foo', 'underline', 'smallcaps', 'kbd'], $span->attr('classes'));
        $t->contains('<p><kbd id="bar"><u><span class="smallcaps">test</span></u></kbd></p>', $blocks);
        $t->true(!str_contains($blocks, 'class="foo'), 'Spanlike HTML handoff should not keep the pre-spanlike source class on the rendered wrapper');
        $t->true(!str_contains($blocks, 'class="foo underline'), 'Spanlike HTML handoff should consume source spanlike marker classes');
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
    'maps upstream command parse raw markdown raw attributes into reader ast' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '*Hi `\foo{there}`{=latex}*',
            '*Hi `<blink>`{=html}there`</blink>`{=html}*',
            '`<outline text="Legacy"/>`{=opml}',
            "```{=html}\n<section data-source=\"batch-42\">Review</section>\n```",
            "```{=tex}\n\\begin{review}\nsource\n\\end{review}\n```",
        ]));

        $latexEmph = $document->children[0]->children[0];
        $htmlEmph = $document->children[1]->children[0];
        $opmlRaw = $document->children[2]->children[0];
        $htmlBlock = $document->children[3];
        $texBlock = $document->children[4];
        $markdown = (new MarkdownWriter())->write($document);
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('emph', $latexEmph->type);
        $t->same(['text', 'raw_inline'], array_map(static fn (AstNode $node): string => $node->type, $latexEmph->children));
        $t->same('Hi ', $latexEmph->children[0]->attr('text'));
        $t->same('latex', $latexEmph->children[1]->attr('format'));
        $t->same('\foo{there}', $latexEmph->children[1]->attr('text'));
        $t->same('emph', $htmlEmph->type);
        $t->same(['text', 'raw_html_inline', 'text', 'raw_html_inline'], array_map(static fn (AstNode $node): string => $node->type, $htmlEmph->children));
        $t->same('<blink>', $htmlEmph->children[1]->attr('html'));
        $t->same('there', $htmlEmph->children[2]->attr('text'));
        $t->same('</blink>', $htmlEmph->children[3]->attr('html'));
        $t->same('raw_inline', $opmlRaw->type);
        $t->same('opml', $opmlRaw->attr('format'));
        $t->same('<outline text="Legacy"/>', $opmlRaw->attr('text'));
        $t->same('raw_block', $htmlBlock->type);
        $t->same('html', $htmlBlock->attr('format'));
        $t->same('<section data-source="batch-42">Review</section>', $htmlBlock->attr('text'));
        $t->same('raw_block', $texBlock->type);
        $t->same('tex', $texBlock->attr('format'));
        $t->same("\\begin{review}\nsource\n\\end{review}", $texBlock->attr('text'));
        $t->contains('*Hi \foo{there}*', $markdown);
        $t->contains('*Hi <blink>there</blink>*', $markdown);
        $t->contains('`<outline text="Legacy"/>`{=opml}', $markdown);
        $t->contains('<section data-source="batch-42">Review</section>', $markdown);
        $t->contains("\\begin{review}\nsource\n\\end{review}", $markdown);
        $t->contains('RawInline (Format "latex")', $native);
        $t->contains('RawInline (Format "html") "<blink>"', $native);
        $t->contains('RawInline (Format "opml") "<outline text=\"Legacy\"/>"', $native);
        $t->contains('<p><em>Hi <span class="pandoc-raw-tex">\foo{there}</span></em></p>', $blocks);
        $t->contains('<p><em>Hi <blink>there</blink></em></p>', $blocks);
        $t->contains('<span class="pandoc-raw-opml" data-pandoc-raw-format="opml">&lt;outline text=&quot;Legacy&quot;/&gt;</span>', $blocks);
        $t->contains('<section data-source="batch-42">Review</section>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-tex">\begin{review}', $blocks);
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
        $t->same('alt text', $image->children[0]->attr('text'));
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
    'maps upstream latex writer inline math pipe escaping' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
            'text' => $text,
            'display' => $display,
        ]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $math('\sigma|_{\{x\}}'),
            ]),
        ]);
        $wordpressDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer equation: '),
                $math('\sigma|_{\{x\}}'),
                $text(' before publish.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Display check: '),
                $math('\alpha + \omega \times x^2', true),
            ]),
        ]);

        $t->same('\(\sigma|_{\{x\}}\)', (new LatexWriter())->write($document));
        $t->contains('Reviewer equation: \(\sigma|_{\{x\}}\) before publish.', (new LatexWriter())->write($wordpressDocument));
        $t->contains('Display check: \[\alpha + \omega \times x^2\]', (new LatexWriter())->write($wordpressDocument));
        $blocks = (new WordPressBlockWriter())->write($wordpressDocument);
        $t->contains('<span class="math inline">\\(\\sigma|_{\\{x\\}}\\)</span>', $blocks);
        $t->contains('<span class="math display">\\[\\alpha + \\omega \\times x^2\\]</span>', $blocks);
    },
    'maps upstream latex writer raw tex inline and block passthrough' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $rawTexInline = static fn (string $tex): AstNode => new AstNode('raw_tex_inline', ['tex' => $tex, 'text' => $tex]);
        $rawInline = static fn (string $format, string $source): AstNode => new AstNode('raw_inline', [
            'format' => $format,
            'text' => $source,
        ]);
        $rawTexBlock = <<<'TEX'
\begin{tabular}{|l|l|}\hline
Animal & Number \\ \hline
Dog    & 2      \\
Cat    & 1      \\ \hline
\end{tabular}
TEX;
        $document = new AstNode('document', [], [
            $paragraph([
                $text('Source citation: '),
                $rawTexInline('\cite[22-23]{smith.1899}'),
                $text(' and '),
                $rawInline('latex', '\LaTeX{}'),
                $rawInline('html', '<span>drop me</span>'),
                $text('.'),
            ]),
            new AstNode('raw_tex', ['tex' => $rawTexBlock]),
            new AstNode('raw_block', ['format' => 'latex', 'text' => '\input{review-appendix.tex}']),
            new AstNode('raw_block', ['format' => 'html', 'text' => '<div>not for latex</div>']),
        ]);
        $review = new AstNode('document', [], [
            $paragraph([
                $text('Reviewer keeps citation '),
                $rawTexInline('\cite{wp-import}'),
                $text(' attached to the source packet.'),
            ]),
            new AstNode('raw_tex', [
                'tex' => "\\begin{tabular}{ll}\nField & Value \\\\\n\\end{tabular}",
            ]),
        ]);

        $t->same(implode("\n\n", [
            'Source citation: \cite[22-23]{smith.1899} and \LaTeX{}.',
            $rawTexBlock,
            '\input{review-appendix.tex}',
        ]), (new LatexWriter())->write($document));

        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('Reviewer keeps citation \cite{wp-import} attached to the source packet.', $latex);
        $t->contains("\\begin{tabular}{ll}\nField & Value \\\\\n\\end{tabular}", $latex);
        $t->contains('<span class="pandoc-raw-tex">\cite{wp-import}</span>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-tex">\\begin{tabular}{ll}' . "\n" . 'Field &amp; Value \\\\', $blocks);
    },
    'maps upstream latex writer inline code quote escapes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $code = static fn (string $text): AstNode => new AstNode('code', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

        $t->same('\texttt{dog\textquotesingle{}s}', (new LatexWriter())->write($plain($code("dog's"))));
        $t->same('\texttt{\textasciigrave{}nu?\textasciigrave{}}', (new LatexWriter())->write($plain($code('`nu?`'))));
        $t->same(implode("\n", [
            '\begin{itemize}',
            '\tightlist',
            '\item',
            '  code \texttt{dog\textquotesingle{}s}',
            '\end{itemize}',
        ]), (new LatexWriter())->write((new MarkdownReader())->read("- code `dog's`")));

        $review = new AstNode('document', [], [
            $paragraph([
                $text('Reviewer commands: '),
                $code("dog's"),
                $text(' and '),
                $code('`nu?`'),
                $text(' stay literal before publish.'),
            ]),
        ]);
        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('Reviewer commands: \texttt{dog\textquotesingle{}s} and \texttt{\textasciigrave{}nu?\textasciigrave{}} stay literal before publish.', $latex);
        $t->contains('<code>dog&#039;s</code>', $blocks);
        $t->contains('<code>`nu?`</code>', $blocks);
    },
    'maps upstream latex writer code blocks inside footnotes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $codeBlock = static fn (string $text): AstNode => new AstNode('code_block', ['text' => $text]);
        $note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
        $upstreamNote = new AstNode('document', [], [
            new AstNode('plain', [], [
                $note([
                    $paragraph([$text('hi')]),
                    $codeBlock('hi'),
                ]),
            ]),
        ]);
        $review = new AstNode('document', [], [
            $paragraph([
                $text('Source audit:'),
                $note([
                    $paragraph([$text('Inspect the shortcode export before publishing.')]),
                    $codeBlock('do_shortcode(\'[gallery ids="4,5"]\');'),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '\footnote{hi',
            '',
            '\begin{Verbatim}',
            'hi',
            '\end{Verbatim}}',
        ]), (new LatexWriter())->write($upstreamNote));

        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\footnote{Inspect the shortcode export before publishing.', $latex);
        $t->contains('\begin{Verbatim}' . "\n" . 'do_shortcode(\'[gallery ids="4,5"]\');' . "\n" . '\end{Verbatim}}', $latex);
        $t->contains('<p>Source audit:<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('<pre class="wp-block-code"><code>do_shortcode(&#039;[gallery ids=&quot;4,5&quot;]&#039;);</code></pre>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1">', $blocks);
    },
    'maps upstream latex writer idiomatic listing code blocks' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $codeBlock = static fn (string $text, array $attrs = []): AstNode => new AstNode('code_block', $attrs + ['text' => $text]);

        $withIdentifier = new AstNode('document', [], [
            $codeBlock('hi', ['id' => 'id']),
        ]);
        $withoutIdentifier = new AstNode('document', [], [
            $codeBlock('hi'),
        ]);
        $review = new AstNode('document', [], [
            $paragraph([$text('Reviewer keeps the source snippet label for print export.')]),
            $codeBlock('do_shortcode(\'[gallery ids="4,5"]\');', [
                'id' => 'shortcode-audit',
                'classes' => ['php'],
                'attributes' => ['data-source' => 'legacy-shortcode'],
            ]),
        ]);

        $t->same(implode("\n", [
            '\begin{lstlisting}[label=id]',
            'hi',
            '\end{lstlisting}',
        ]), (new LatexWriter(['writerHighlightMethod' => 'IdiomaticHighlighting']))->write($withIdentifier));
        $t->same(implode("\n", [
            '\begin{lstlisting}',
            'hi',
            '\end{lstlisting}',
        ]), (new LatexWriter(['highlightMethod' => 'idiomatic']))->write($withoutIdentifier));

        $latex = (new LatexWriter(['writerHighlightMethod' => 'IdiomaticHighlighting']))->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\begin{lstlisting}[label=shortcode-audit]', $latex);
        $t->contains('do_shortcode(\'[gallery ids="4,5"]\');', $latex);
        $t->contains('\end{lstlisting}', $latex);
        $t->contains('<pre class="wp-block-code" id="shortcode-audit" data-source="legacy-shortcode"><code class="language-php">do_shortcode(&#039;[gallery ids=&quot;4,5&quot;]&#039;);</code></pre>', $blocks);
    },
    'maps upstream latex writer highlighted strikeout inline code' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $code = static fn (string $text, array $attrs = []): AstNode => new AstNode('code', $attrs + ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $strikeout = static fn (array $children): AstNode => new AstNode('strikeout', [], $children);
        $doc = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);

        $upstream = $doc($strikeout([
            $code('foo', ['classes' => ['haskell']]),
            $text(' bar'),
        ]));
        $disabled = $doc($strikeout([
            $code('foo', ['classes' => ['haskell']]),
            $text(' bar'),
        ]));
        $review = new AstNode('document', [], [
            $paragraph([
                $text('Reviewer flags '),
                $strikeout([
                    $code('renderBlocks', [
                        'classes' => ['haskell'],
                        'attributes' => ['data-source' => 'migration-lint'],
                    ]),
                    $text(' before release'),
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(
            '\st{\mbox{\VERB|\NormalTok{foo}|} bar}',
            (new LatexWriter())->write($upstream)
        );
        $t->same(
            '\st{\mbox{\texttt{foo}} bar}',
            (new LatexWriter(['writerHighlightMethod' => false]))->write($disabled)
        );

        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\st{\mbox{\VERB|\NormalTok{renderBlocks}|} before release}', $latex);
        $t->contains('<del><code class="haskell" data-source="migration-lint">renderBlocks</code> before release</del>', $blocks);
    },
    'maps upstream latex writer styled inline notes for emph and strong' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
        $note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
        $emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
        $strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
        $doc = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $bigNote = static fn (string $first = 'paragraph1', string $second = 'paragraph2'): AstNode => $note([
            $paragraph([$text($first)]),
            $paragraph([$text($second)]),
        ]);

        $t->same(
            '\emph{This sentence}\footnote{paragraph1' . "\n\n" . '  paragraph2}\emph{ has footnote.}',
            (new LatexWriter())->write($doc($emph([
                $text('This sentence'),
                $bigNote(),
                $text(' has footnote.'),
            ])))
        );
        $t->same(
            '\textbf{This sentence}\footnote{paragraph1' . "\n\n" . '  paragraph2}\textbf{ has footnote.}',
            (new LatexWriter())->write($doc($strong([
                $text('This sentence'),
                $bigNote(),
                $text(' has footnote.'),
            ])))
        );
        $t->same(
            '\emph{This sentence\footnote{paragraph} has footnote.}',
            (new LatexWriter())->write($doc($emph([
                $text('This sentence'),
                $note([$plain([$text('paragraph')])]),
                $text(' has footnote.'),
            ])))
        );
        $t->same(
            '\emph{This \textbf{nested sentence }}\footnote{paragraph1' . "\n\n" . '  paragraph2}\emph{\textbf{has }footnote.}',
            (new LatexWriter())->write($doc($emph([
                $text('This '),
                $strong([
                    $text('nested sentence '),
                    $bigNote(),
                    $text('has '),
                ]),
                $text('footnote.'),
            ])))
        );
        $t->same(
            '\emph{This sentence}\footnote{1-paragraph1' . "\n\n" . '  1-paragraph2}\emph{ has}\footnote{2-paragraph1' . "\n\n" . '  2-paragraph2}\emph{ footnote.}',
            (new LatexWriter())->write($doc($emph([
                $text('This sentence'),
                $bigNote('1-paragraph1', '1-paragraph2'),
                $text(' has'),
                $bigNote('2-paragraph1', '2-paragraph2'),
                $text(' footnote.'),
            ])))
        );

        $review = new AstNode('document', [], [
            $paragraph([
                $text('Reviewer keeps '),
                $emph([
                    $text('source emphasis'),
                    $bigNote('First reviewer paragraph.', 'Second reviewer paragraph.'),
                    $text(' visible'),
                ]),
                $text(' and '),
                $strong([
                    $text('strong source text'),
                    $note([$plain([$text('Single reviewer note.')])]),
                    $text(' intact'),
                ]),
                $text('.'),
            ]),
        ]);
        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\emph{source emphasis}\footnote{First reviewer paragraph.' . "\n\n" . '  Second reviewer paragraph.}\emph{ visible}', $latex);
        $t->contains('\textbf{strong source text\footnote{Single reviewer note.} intact}', $latex);
        $t->contains('<em>source emphasis<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> visible</em>', $blocks);
        $t->contains('<strong>strong source text<sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup> intact</strong>', $blocks);
        $t->contains('<li id="fn-1"><p>First reviewer paragraph.</p><p>Second reviewer paragraph.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'maps upstream latex writer styled inline notes for underline and strikeout' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $code = static fn (string $text): AstNode => new AstNode('code', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
        $note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
        $underline = static fn (array $children): AstNode => new AstNode('underline', [], $children);
        $strikeout = static fn (array $children): AstNode => new AstNode('strikeout', [], $children);
        $doc = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $bigNote = static fn (string $first = 'paragraph1', string $second = 'paragraph2'): AstNode => $note([
            $paragraph([$text($first)]),
            $paragraph([$text($second)]),
        ]);

        $t->same(
            '\ul{This sentence}\footnote{paragraph1' . "\n\n" . '  paragraph2}\ul{ has footnote.}',
            (new LatexWriter())->write($doc($underline([
                $text('This sentence'),
                $bigNote(),
                $text(' has footnote.'),
            ])))
        );
        $t->same(
            '\st{This sentence}\footnote{paragraph1' . "\n\n" . '  paragraph2}\st{ has footnote.}',
            (new LatexWriter())->write($doc($strikeout([
                $text('This sentence'),
                $bigNote(),
                $text(' has footnote.'),
            ])))
        );
        $t->same(
            '\st{\mbox{\texttt{foo}} bar}',
            (new LatexWriter())->write($doc($strikeout([
                $code('foo'),
                $text(' bar'),
            ])))
        );

        $review = new AstNode('document', [], [
            $paragraph([
                $text('Reviewer marks '),
                $underline([
                    $text('inserted source context'),
                    $bigNote('First insert-note paragraph.', 'Second insert-note paragraph.'),
                    $text(' before publish'),
                ]),
                $text(' and '),
                $strikeout([
                    $text('stale shortcode'),
                    $note([$plain([$text('Keep source deletion note.')])]),
                    $text(' safely removed'),
                ]),
                $text('.'),
            ]),
        ]);
        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\ul{inserted source context}\footnote{First insert-note paragraph.' . "\n\n" . '  Second insert-note paragraph.}\ul{ before publish}', $latex);
        $t->contains('\st{stale shortcode\footnote{Keep source deletion note.} safely removed}', $latex);
        $t->contains('<u>inserted source context<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> before publish</u>', $blocks);
        $t->contains('<del>stale shortcode<sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup> safely removed</del>', $blocks);
        $t->contains('<li id="fn-1"><p>First insert-note paragraph.</p><p>Second insert-note paragraph.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'maps upstream latex writer heading defaults and list item headings' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $heading = static fn (int $level, string $textValue): AstNode => new AstNode('heading', ['level' => $level], [
            $text($textValue),
        ]);
        $document = new AstNode('document', [], [
            $heading(1, 'header1'),
            $heading(2, 'header2'),
            $heading(3, 'header3'),
        ]);
        $listDocument = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    $heading(2, 'foo'),
                ]),
            ]),
        ]);
        $reviewDocument = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1, 'id' => 'migration-review'], [
                $text('Migration Review'),
            ]),
            new AstNode('paragraph', [], [
                $text('Summarize block conversion decisions before publish.'),
            ]),
            new AstNode('heading', ['level' => 2], [
                $text('Media Checks'),
            ]),
            new AstNode('paragraph', [], [
                $text('Confirm captions and source URLs.'),
            ]),
        ]);

        $t->same(implode("\n\n", [
            '\section{header1}',
            '\subsection{header2}',
            '\subsubsection{header3}',
        ]), (new LatexWriter())->write($document));
        $t->same(implode("\n", [
            '\begin{itemize}',
            '\item ~',
            '  \subsection{foo}',
            '\end{itemize}',
        ]), (new LatexWriter())->write($listDocument));
        $latex = (new LatexWriter())->write($reviewDocument);
        $blocks = (new WordPressBlockWriter())->write($reviewDocument);

        $t->contains('\section{Migration Review}', $latex);
        $t->contains('\subsection{Media Checks}', $latex);
        $t->contains('<h1 id="migration-review">Migration Review</h1>', $blocks);
        $t->contains('<h2>Media Checks</h2>', $blocks);
    },
    'maps upstream latex writer top-level division options' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $heading = static fn (int $level, string $textValue, array $attrs = []): AstNode => new AstNode('heading', $attrs + ['level' => $level], [
            $text($textValue),
        ]);
        $document = new AstNode('document', [], [
            $heading(1, 'header1'),
            $heading(2, 'header2'),
            $heading(3, 'header3'),
        ]);
        $unnumberedPart = new AstNode('document', [], [
            $heading(1, 'header1', ['classes' => ['unnumbered']]),
        ]);
        $reviewDocument = new AstNode('document', [], [
            $heading(1, 'Legacy Handbook', ['id' => 'legacy-handbook']),
            $heading(2, 'Import Checklist'),
            new AstNode('paragraph', [], [
                $text('Keep the reviewer export aligned with the source book hierarchy.'),
            ]),
        ]);

        $t->same(implode("\n\n", [
            '\chapter{header1}',
            '\section{header2}',
            '\subsection{header3}',
        ]), (new LatexWriter(['writerTopLevelDivision' => 'chapter']))->write($document));
        $t->same(implode("\n\n", [
            '\part{header1}',
            '\chapter{header2}',
            '\section{header3}',
        ]), (new LatexWriter(['topLevelDivision' => 'part']))->write($document));
        $t->same(implode("\n", [
            '\part*{header1}',
            '\addcontentsline{toc}{part}{header1}',
            '',
        ]), (new LatexWriter(['writerTopLevelDivision' => 'TopLevelPart']))->write($unnumberedPart));
        $t->same((new LatexWriter())->write($document), (new LatexWriter(['writerTopLevelDivision' => 'TopLevelDefault']))->write($document));

        $latex = (new LatexWriter(['writerTopLevelDivision' => 'chapter']))->write($reviewDocument);
        $blocks = (new WordPressBlockWriter())->write($reviewDocument);

        $t->contains('\chapter{Legacy Handbook}', $latex);
        $t->contains('\section{Import Checklist}', $latex);
        $t->contains('<h1 id="legacy-handbook">Legacy Handbook</h1>', $blocks);
        $t->contains('<h2>Import Checklist</h2>', $blocks);
    },
    'maps upstream latex writer unnumbered headings with inline notes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);

        $upstreamHeading = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 1,
                'id' => 'foo',
                'classes' => ['unnumbered'],
            ], [
                $text('Header 1'),
                $note([
                    new AstNode('plain', [], [$text('note')]),
                ]),
            ]),
        ]);
        $reviewHeading = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 1,
                'id' => 'source-audit',
                'classes' => ['unnumbered'],
            ], [
                $text('Source Audit'),
                $note([
                    $paragraph([$text('Keep reviewer-only context out of the PDF bookmark.')]),
                ]),
            ]),
            $paragraph([$text('Public handoff starts after the review heading.')]),
        ]);

        $t->same(implode("\n", [
            '\section*{\texorpdfstring{Header 1\footnote{note}}{Header 1}}\label{foo}',
            '\addcontentsline{toc}{section}{Header 1}',
            '',
        ]), (new LatexWriter())->write($upstreamHeading));

        $latex = (new LatexWriter())->write($reviewHeading);
        $blocks = (new WordPressBlockWriter())->write($reviewHeading);

        $t->contains('\section*{\texorpdfstring{Source Audit\footnote{Keep reviewer-only context out of the PDF bookmark.}}{Source Audit}}\label{source-audit}', $latex);
        $t->contains('\addcontentsline{toc}{section}{Source Audit}', $latex);
        $t->true(!str_contains($latex, '{Source AuditKeep reviewer-only context'), 'LaTeX PDF-string fallback should omit inline note text');
        $t->contains('<h1 id="source-audit" class="unnumbered">Source Audit<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></h1>', $blocks);
        $t->contains('<li id="fn-1"><p>Keep reviewer-only context out of the PDF bookmark.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'maps upstream latex writer image headings with texorpdfstring fallback' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $image = static fn (string $url, string $alt): AstNode => new AstNode('image', [
            'url' => $url,
            'alt' => $alt,
        ], [$text($alt)]);
        $upstreamHeading = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1], [
                $image('imgs/foo.jpg', 'Alt text'),
            ]),
        ]);
        $reviewHeading = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1, 'id' => 'source-hero'], [
                $text('Source hero '),
                $image('https://example.test/uploads/source-hero.jpg', 'Legacy hero image'),
            ]),
            new AstNode('paragraph', [], [
                $text('Reviewer confirms heading art survived import.'),
            ]),
        ]);

        $t->same(
            '\section{\texorpdfstring{\protect\pandocbounded{\includegraphics[keepaspectratio,alt={Alt text}]{imgs/foo.jpg}}}{Alt text}}',
            (new LatexWriter())->write($upstreamHeading)
        );

        $latex = (new LatexWriter())->write($reviewHeading);
        $blocks = (new WordPressBlockWriter())->write($reviewHeading);

        $t->contains('\section{\texorpdfstring{Source hero \protect\pandocbounded{\includegraphics[keepaspectratio,alt={Legacy hero image}]{https://example.test/uploads/source-hero.jpg}}}{Source hero Legacy hero image}}', $latex);
        $t->contains('<h1 id="source-hero">Source hero <img src="https://example.test/uploads/source-hero.jpg" alt="Legacy hero image"/></h1>', $blocks);
        $t->contains('<p>Reviewer confirms heading art survived import.</p>', $blocks);
    },
    'maps upstream latex writer definition lists and internal links' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $term = static fn (array $children, string $fallback): AstNode => new AstNode('term', ['text' => $fallback], $children);
        $definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
        $item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
            'definition_item',
            ['term' => $term->attr('text', '')],
            array_merge([$term], $definitions)
        );
        $heading = static fn (int $level, string $textValue): AstNode => new AstNode('heading', ['level' => $level], [
            $text($textValue),
        ]);

        $internalLink = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([
                    new AstNode('link', ['url' => '#go'], [$text('testing')]),
                ], 'testing'), [
                    $definition([$plain([$text('hi there')])]),
                ]),
            ]),
        ]);
        $headingDefinition = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([$text('foo')], 'foo'), [
                    $definition([
                        $heading(2, 'bar'),
                        $paragraph([$text('baz')]),
                    ]),
                ]),
            ]),
        ]);
        $review = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([$text('Source review')], 'Source review'), [
                    $definition([
                        $heading(2, 'Block Audit'),
                        $paragraph([
                            $text('Check '),
                            new AstNode('link', ['url' => '#media-checks'], [$text('media checks')]),
                            $text(' before publish.'),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '\begin{description}',
            '\tightlist',
            '\item[{\hyperref[go]{testing}}]',
            'hi there',
            '\end{description}',
        ]), (new LatexWriter())->write($internalLink));
        $t->same(implode("\n", [
            '\begin{description}',
            '\item[foo] ~ ',
            '\subsection{bar}',
            '',
            'baz',
            '\end{description}',
        ]), (new LatexWriter())->write($headingDefinition));

        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\item[Source review] ~ ', $latex);
        $t->contains('\subsection{Block Audit}', $latex);
        $t->contains('\hyperref[media-checks]{media checks}', $latex);
        $t->contains('<dt>Source review</dt>', $blocks);
        $t->contains('<h2>Block Audit</h2>', $blocks);
        $t->contains('<a href="#media-checks">media checks</a>', $blocks);
    },
    'maps upstream latex writer figure placement and image alt text' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $image = static fn (string $url, string $alt): AstNode => new AstNode('image', [
            'url' => $url,
            'alt' => $alt,
        ], [$text($alt)]);
        $plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
        $figure = static fn (array $attrs, string $caption, AstNode $image): AstNode => new AstNode('figure', $attrs + [
            'caption' => $caption,
            'captionInlines' => [$text($caption)],
        ], [
            $plain([$image]),
        ]);

        $upstreamFigure = new AstNode('document', [], [
            $figure([
                'attributes' => ['latex-placement' => 'htbp'],
            ], 'caption', $image('img.jpg', 'alt text')),
        ]);
        $reviewFigure = new AstNode('document', [], [
            $figure([
                'id' => 'fig-import-frame',
                'classes' => ['migration-frame'],
                'attributes' => ['latex-placement' => 'H'],
            ], 'Imported hero frame', $image('https://example.test/uploads/imported-frame.jpg', 'Imported frame')),
        ]);

        $t->same(implode("\n", [
            '\begin{figure}[htbp]',
            '\centering',
            '\pandocbounded{\includegraphics[keepaspectratio,alt={alt text}]{img.jpg}}',
            '\caption{caption}',
            '\end{figure}',
        ]), (new LatexWriter())->write($upstreamFigure));

        $latex = (new LatexWriter())->write($reviewFigure);
        $blocks = (new WordPressBlockWriter())->write($reviewFigure);

        $t->contains('\begin{figure}[H]', $latex);
        $t->contains('\pandocbounded{\includegraphics[keepaspectratio,alt={Imported frame}]{https://example.test/uploads/imported-frame.jpg}}', $latex);
        $t->contains('\caption{Imported hero frame}', $latex);
        $t->contains('<figure class="wp-block-image migration-frame" id="fig-import-frame" data-pandoc-latex-placement="H"><img src="https://example.test/uploads/imported-frame.jpg" alt="Imported frame"/><figcaption>Imported hero frame</figcaption></figure>', $blocks);
    },
    'maps upstream latex writer block quotes and horizontal rules' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
        $document = new AstNode('document', [], [
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('This is a block quote.'),
                    new AstNode('softbreak'),
                    $text('It is pretty short.'),
                ]),
                new AstNode('code_block', ['text' => "sub status {\n    print \"working\";\n}"]),
                $paragraph([$text('A list:')]),
                new AstNode('ordered_list', [], [
                    new AstNode('list_item', [], [$plain([$text('item one')])]),
                    new AstNode('list_item', [], [$plain([$text('item two')])]),
                ]),
                new AstNode('blockquote', [], [
                    $paragraph([$text('nested')]),
                ]),
            ]),
            new AstNode('horizontal_rule'),
        ]);
        $review = new AstNode('document', [], [
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('Reviewer note: keep archive context with the imported post.'),
                ]),
            ]),
            new AstNode('horizontal_rule'),
            $paragraph([$text('Publish checklist resumes after the source break.')]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{quote}',
                'This is a block quote.',
                'It is pretty short.',
                '',
                '\begin{Verbatim}',
                'sub status {',
                '    print "working";',
                '}',
                '\end{Verbatim}',
                '',
                'A list:',
                '',
                '\begin{enumerate}',
                '\tightlist',
                '\item',
                '  item one',
                '\item',
                '  item two',
                '\end{enumerate}',
                '',
                '\begin{quote}',
                'nested',
                '\end{quote}',
                '\end{quote}',
            ]),
            '\begin{center}\rule{0.5\linewidth}{0.5pt}\end{center}',
        ]), (new LatexWriter())->write($document));

        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\begin{quote}' . "\n" . 'Reviewer note: keep archive context with the imported post.' . "\n" . '\end{quote}', $latex);
        $t->contains('\begin{center}\rule{0.5\linewidth}{0.5pt}\end{center}', $latex);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note: keep archive context with the imported post.</p></blockquote>', $blocks);
        $t->contains('<!-- wp:separator -->', $blocks);
        $t->contains('<p>Publish checklist resumes after the source break.</p>', $blocks);
    },
    'maps upstream latex writer ordered list counters labels and tightness' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (string $textValue): AstNode => new AstNode('plain', [], [$text($textValue)]);
        $paragraph = static fn (string $textValue): AstNode => new AstNode('paragraph', [], [$text($textValue)]);
        $item = static fn (string $textValue): AstNode => new AstNode('list_item', [], [$plain($textValue)]);

        $styledLists = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                $item('roman checkpoint'),
                $item('publish handoff'),
            ]),
            new AstNode('ordered_list', ['start' => 2, 'style' => 'decimal', 'delimiter' => 'two_parens'], [
                $item('begins with two'),
            ]),
            new AstNode('ordered_list', ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'one_paren'], [
                $item('alpha review'),
            ]),
        ]);
        $nestedList = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 2, 'style' => 'decimal', 'delimiter' => 'two_parens'], [
                new AstNode('list_item', [], [
                    $plain('Import source batch'),
                    new AstNode('ordered_list', ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                        $item('Check roman subqueue'),
                        $item('Escalate captions'),
                    ]),
                ]),
            ]),
        ]);
        $looseList = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 1, 'style' => 'default', 'delimiter' => 'default'], [
                new AstNode('list_item', [], [
                    $paragraph('Loose paragraph stays paragraph-shaped.'),
                ]),
            ]),
        ]);
        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer checklist for the imported archive:'),
            ]),
            new AstNode('ordered_list', ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                $item('Queue editorial review'),
                $item('Publish reviewed batch'),
            ]),
        ]);

        $t->same(implode("\n\n", [
            implode("\n", [
                '\begin{enumerate}',
                '\def\labelenumi{\roman{enumi}.}',
                '\setcounter{enumi}{3}',
                '\tightlist',
                '\item',
                '  roman checkpoint',
                '\item',
                '  publish handoff',
                '\end{enumerate}',
            ]),
            implode("\n", [
                '\begin{enumerate}',
                '\def\labelenumi{(\arabic{enumi})}',
                '\setcounter{enumi}{1}',
                '\tightlist',
                '\item',
                '  begins with two',
                '\end{enumerate}',
            ]),
            implode("\n", [
                '\begin{enumerate}',
                '\def\labelenumi{\Alph{enumi})}',
                '\tightlist',
                '\item',
                '  alpha review',
                '\end{enumerate}',
            ]),
        ]), (new LatexWriter())->write($styledLists));
        $t->same(implode("\n", [
            '\begin{enumerate}',
            '\def\labelenumi{(\arabic{enumi})}',
            '\setcounter{enumi}{1}',
            '\tightlist',
            '\item',
            '  Import source batch',
            '  \begin{enumerate}',
            '  \def\labelenumii{\roman{enumii}.}',
            '  \setcounter{enumii}{3}',
            '  \tightlist',
            '  \item',
            '    Check roman subqueue',
            '  \item',
            '    Escalate captions',
            '  \end{enumerate}',
            '\end{enumerate}',
        ]), (new LatexWriter())->write($nestedList));
        $t->same(implode("\n", [
            '\begin{enumerate}',
            '\item',
            '  Loose paragraph stays paragraph-shaped.',
            '\end{enumerate}',
        ]), (new LatexWriter())->write($looseList));

        $latex = (new LatexWriter())->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('\def\labelenumi{\roman{enumi}.}', $latex);
        $t->contains('\setcounter{enumi}{3}', $latex);
        $t->contains('\tightlist', $latex);
        $t->contains('<ol start="4" type="i"><li>Queue editorial review</li><li>Publish reviewed batch</li></ol>', $blocks);
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
    'maps upstream markdown writer header attributes and auto id elision' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 1,
                'id' => 'review-anchors',
                'classes' => ['wp-import-review'],
                'attributes' => ['source' => 'batch-42', 'title' => 'Review "anchors"'],
            ], [$text('Review Anchors')]),
            new AstNode('heading', [
                'level' => 2,
                'id' => 'custom-review-id',
            ], [$text('Custom Review Anchor')]),
            new AstNode('heading', [
                'level' => 3,
                'id' => 'source-review-id',
            ], [
                $text('Review '),
                new AstNode('emph', [], [$text('Source')]),
            ]),
        ]);
        $autoIds = (new MarkdownReader())->read("# Duplicate\n\n# Duplicate");

        $t->same(implode("\n\n", [
            '# Review Anchors {#review-anchors .wp-import-review source="batch-42" title="Review \"anchors\""}',
            '## Custom Review Anchor {#custom-review-id}',
            '### Review *Source* {#source-review-id}',
        ]), (new MarkdownWriter())->write($document));
        $t->same(implode("\n\n", [
            "Review Anchors {#review-anchors .wp-import-review source=\"batch-42\" title=\"Review \\\"anchors\\\"\"}\n==============",
            "Custom Review Anchor {#custom-review-id}\n--------------------",
            '### Review *Source* {#source-review-id}',
        ]), (new MarkdownWriter(['setextHeadings' => true]))->write($document));
        $t->same("# Duplicate\n\n# Duplicate", (new MarkdownWriter())->write($autoIds));
        $t->same(implode("\n\n", [
            '# Review Anchors',
            '## Custom Review Anchor',
            '### Review *Source*',
        ]), (new MarkdownWriter(['headerAttributes' => false]))->write($document));
    },
    'maps upstream markdown writer fenced code block attributes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $languageOnly = new AstNode('document', [], [
            new AstNode('code_block', [
                'classes' => ['php'],
                'attributes' => [],
                'text' => "do_shortcode('[legacy-gallery]');",
            ]),
        ]);
        $attributed = new AstNode('document', [], [
            new AstNode('code_block', [
                'id' => 'review-snippet',
                'classes' => ['php', 'numberLines'],
                'attributes' => [
                    'startFrom' => '42',
                    'data-source' => 'batch-42',
                    'title' => 'Review "snippet"',
                ],
                'text' => "echo \"source\";\n```\nreturn true;",
            ]),
        ]);
        $languageFallback = new AstNode('document', [], [
            new AstNode('code_block', [
                'classes' => ['sourceCode', 'language-php'],
                'attributes' => ['data-source' => 'batch-42'],
                'text' => 'echo $post_id;',
            ]),
        ]);
        $listThenFencedCode = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$text('Review snippet')]),
            ]),
            new AstNode('code_block', [
                'classes' => ['php'],
                'attributes' => [],
                'text' => 'echo "ok";',
            ]),
        ]);
        $listThenIndentedCode = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$text('Review snippet')]),
            ]),
            new AstNode('code_block', ['text' => 'echo "ok";']),
        ]);

        $t->same(implode("\n", [
            '``` php',
            "do_shortcode('[legacy-gallery]');",
            '```',
        ]), (new MarkdownWriter())->write($languageOnly));
        $t->same(implode("\n", [
            '````{#review-snippet .php .numberLines startFrom="42" data-source="batch-42" title="Review \"snippet\""}',
            'echo "source";',
            '```',
            'return true;',
            '````',
        ]), (new MarkdownWriter())->write($attributed));
        $t->same(implode("\n", [
            '``` php',
            'echo $post_id;',
            '```',
        ]), (new MarkdownWriter(['fencedCodeAttributes' => false]))->write($languageFallback));
        $t->same('    echo $post_id;', (new MarkdownWriter([
            'backtickCodeBlocks' => false,
            'fencedCodeBlocks' => false,
        ]))->write($languageFallback));
        $t->same(implode("\n", [
            '- Review snippet',
            '',
            '``` php',
            'echo "ok";',
            '```',
        ]), (new MarkdownWriter())->write($listThenFencedCode));
        $t->same(implode("\n", [
            '- Review snippet',
            '',
            '<!-- -->',
            '',
            '    echo "ok";',
        ]), (new MarkdownWriter())->write($listThenIndentedCode));
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
            '\\# Heading-looking source \\-- \\... \\::: \\![draft\\] \\~\\~gone\\~\\~ a_b \\*stars\\* \\_under\\_ \\`tick\\` \\| \\^ \\~ \\$ \\<tag\\> \\> \\&ouml; \\\\macro [bracket \\[label\\]][1] and [bracket \\[again\\]][1] then [normal] and [normal][2]',
            '',
            '  [1]: /review',
            '  [normal]: /other',
            '  [2]: /other-2',
        ]), (new MarkdownWriter(['referenceLinks' => true]))->write($document));
    },
    'maps upstream markdown writer plain marker escaping at block starts' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
        $document = new AstNode('document', [], [
            $paragraph('1. Source batch remains text'),
            $paragraph('(2) Parenthesized source marker stays text'),
            $paragraph('iv.  Roman review checkpoint stays text'),
            $paragraph('#. Autonumber source marker stays text'),
            $paragraph('- reviewer dash marker stays text'),
            $paragraph('+ reviewer plus marker stays text'),
            $paragraph('% Imported title line remains body text'),
            $paragraph('A. Single-space upper-alpha marker does not need escaping'),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('paragraph', [], [$text('1. Nested paragraph remains text')]),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document);

        $t->same(implode("\n\n", [
            '1\\. Source batch remains text',
            '\\(2\\) Parenthesized source marker stays text',
            'iv\\.  Roman review checkpoint stays text',
            '#\\. Autonumber source marker stays text',
            '\\- reviewer dash marker stays text',
            '\\+ reviewer plus marker stays text',
            '\\% Imported title line remains body text',
            'A. Single-space upper-alpha marker does not need escaping',
            '- 1\\. Nested paragraph remains text',
        ]), $markdown);
        $roundTrip = (new MarkdownReader())->read($markdown);
        $t->same('paragraph', $roundTrip->children[0]->type);
        $t->same('paragraph', $roundTrip->children[1]->type);
        $t->same('paragraph', $roundTrip->children[2]->type);
        $t->same('paragraph', $roundTrip->children[3]->type);
        $t->same('paragraph', $roundTrip->children[4]->type);
        $t->same('paragraph', $roundTrip->children[5]->type);
        $t->same('paragraph', $roundTrip->children[6]->type);
        $t->same('bullet_list', $roundTrip->children[8]->type);
        $t->same('1. Nested paragraph remains text', $roundTrip->children[8]->children[0]->children[0]->attr('text'));
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
    'maps upstream markdown writer escaped destinations for spaces and parentheses' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Source asset: '),
                new AstNode('link', [
                    'url' => '/wp-content/uploads/alpha beta).jpg',
                    'title' => 'Migration "asset"',
                ], [$text('asset')]),
                $text(' and '),
                new AstNode('image', [
                    'url' => '/wp-content/uploads/chart (final).png',
                    'alt' => 'chart',
                ], [$text('chart')]),
                $text('.'),
            ]),
        ]);
        $referenceDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('link', [
                    'url' => '/wp-content/uploads/review packet).pdf',
                    'title' => 'PDF source',
                ], [$text('packet')]),
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document);
        $roundTrip = (new MarkdownReader())->read($markdown);
        $roundTripParagraph = $roundTrip->children[0];

        $t->same('Source asset: [asset](</wp-content/uploads/alpha beta).jpg> "Migration \\"asset\\"") and ![chart](</wp-content/uploads/chart (final).png>).', $markdown);
        $t->same('/wp-content/uploads/alpha%20beta).jpg', $roundTripParagraph->children[1]->attr('url'));
        $t->same('/wp-content/uploads/chart%20(final).png', $roundTripParagraph->children[3]->attr('url'));
        $t->same(implode("\n", [
            '[packet]',
            '',
            '  [packet]: </wp-content/uploads/review packet).pdf> "PDF source"',
        ]), (new MarkdownWriter(['referenceLinks' => true]))->write($referenceDocument));
    },
    'maps upstream markdown writer wikilink title pipe variants' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $link = static fn (string $url, string $label): AstNode => new AstNode('link', [
            'url' => $url,
            'classes' => ['wikilink'],
        ], [$text($label)]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [$link('https://example.org', 'https://example.org')]),
            new AstNode('paragraph', [], [$link('https://example.org', 'title')]),
            new AstNode('paragraph', [], [$link('Home', 'Home')]),
            new AstNode('paragraph', [], [$link('Name of page', 'Title')]),
        ]);
        $reviewPacket = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Wiki shortcuts: '),
                $link('https://example.test/runbook', 'Migration runbook'),
                $text(' and '),
                $link('Legacy import checklist', 'Legacy import checklist'),
                $text('.'),
            ]),
        ]);
        $extraAttrsFallback = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('link', [
                    'url' => 'Name of page',
                    'classes' => ['wikilink', 'source-link'],
                ], [$text('Title')]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            '[[https://example.org]]',
            '[[https://example.org|title]]',
            '[[Home]]',
            '[[Name of page|Title]]',
        ]), (new MarkdownWriter(['variant' => 'markdown+wikilinks_title_after_pipe']))->write($document));
        $t->same(implode("\n\n", [
            '[[https://example.org]]',
            '[[title|https://example.org]]',
            '[[Home]]',
            '[[Title|Name of page]]',
        ]), (new MarkdownWriter(['variant' => 'commonmark_x+wikilinks_title_before_pipe']))->write($document));
        $t->same(
            'Wiki shortcuts: [[https://example.test/runbook|Migration runbook]] and [[Legacy import checklist]].',
            (new MarkdownWriter(['wikilinksTitleAfterPipe' => true]))->write($reviewPacket)
        );
        $t->same(
            '[Title](Name of page){.wikilink .source-link}',
            (new MarkdownWriter(['wikilinksTitleAfterPipe' => true]))->write($extraAttrsFallback)
        );
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
    'maps upstream markdown writer code attributes and backtick markers' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer token: '),
                new AstNode('code', [
                    'text' => 'wp_enqueue_script',
                    'id' => 'enqueue',
                    'classes' => ['php'],
                    'attributes' => [
                        'data-source' => 'batch-42',
                        'title' => 'Import source',
                    ],
                ]),
                $text(' and '),
                new AstNode('code', [
                    'text' => 'echo `legacy`',
                    'classes' => ['source-token'],
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Reviewer token: `wp_enqueue_script`{#enqueue .php data-source="batch-42" title="Import source"} and `` echo `legacy` ``{.source-token}.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer bracketed spans emoji and bang guard' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Review span: '),
                new AstNode('span', [
                    'id' => 'migration-span',
                    'classes' => ['review-span'],
                    'attributes' => [
                        'data-source' => 'batch-42',
                        'title' => 'Migration span',
                    ],
                ], [
                    new AstNode('emph', [], [$text('urgent')]),
                    $text(' source flag '),
                    new AstNode('link', [
                        'url' => '/wp-admin/post.php?post=42&action=edit',
                    ], [$text('edit')]),
                ]),
                $text(' and emoji '),
                new AstNode('span', [
                    'classes' => ['emoji'],
                    'attributes' => ['data-emoji' => 'smile'],
                ], [$text("\u{1F604}")]),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Do not create accidental image syntax!'),
                new AstNode('span', ['classes' => ['review-span']], [$text('flag')]),
                $text(' or !'),
                new AstNode('link', ['url' => '/source'], [$text('source')]),
                $text('.'),
            ]),
        ]);

        $t->same(implode("\n", [
            'Review span: [*urgent* source flag [edit](/wp-admin/post.php?post=42&action=edit)]{#migration-span .review-span data-source="batch-42" title="Migration span"} and emoji :smile:.',
            '',
            'Do not create accidental image syntax\\![flag]{.review-span} or \\![source](/source).',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer raw html and native span fallback' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Fallback span: '),
                new AstNode('span', [
                    'id' => 'migration-span',
                    'classes' => ['review-span'],
                    'attributes' => [
                        'data-source' => 'batch-42',
                        'title' => 'Migration span',
                    ],
                ], [
                    $text('review '),
                    new AstNode('emph', [], [$text('flag')]),
                    $text(' '),
                    new AstNode('link', ['url' => '/source'], [$text('source')]),
                ]),
                $text(' and unwrapped '),
                new AstNode('span', [], [$text('plain')]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Fallback span: <span id="migration-span" class="review-span" data-source="batch-42" title="Migration span">review *flag* [source](/source)</span> and unwrapped plain.',
            (new MarkdownWriter(['bracketedSpans' => false]))->write($document)
        );
        $t->same(
            'Fallback span: <span id="migration-span" class="review-span" data-source="batch-42" title="Migration span">review *flag* [source](/source)</span> and unwrapped plain.',
            (new MarkdownWriter(['bracketedSpans' => false, 'rawHtml' => false, 'nativeSpans' => true]))->write($document)
        );
        $t->same(
            'Fallback span: review *flag* [source](/source) and unwrapped plain.',
            (new MarkdownWriter(['bracketedSpans' => false, 'rawHtml' => false]))->write($document)
        );
        $t->same(
            'Fallback span: [review *flag* [source](/source)]{#migration-span .review-span data-source="batch-42" title="Migration span"} and unwrapped plain.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer mark spans and literal mark escaping' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Highlight '),
                new AstNode('span', ['classes' => ['mark']], [
                    $text('source '),
                    new AstNode('emph', [], [$text('flag')]),
                ]),
                $text(' and direct '),
                new AstNode('mark', [], [$text('review note')]),
                $text(' plus literal ==not mark== and '),
                new AstNode('span', [
                    'id' => 'review',
                    'classes' => ['mark'],
                ], [$text('tagged')]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Highlight ==source *flag*== and direct ==review note== plus literal \\=\\=not mark\\=\\= and [tagged]{#review .mark}.',
            (new MarkdownWriter())->write($document)
        );
    },
    'maps upstream markdown writer quoted underline and small caps inlines' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Quote handoff: '),
                new AstNode('quoted', ['kind' => 'single'], [
                    $text('reviewer source'),
                ]),
                $text(' and '),
                new AstNode('quoted', ['kind' => 'double'], [
                    $text('see '),
                    new AstNode('link', ['url' => '/source'], [$text('source')]),
                ]),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Editorial marks: '),
                new AstNode('underline', [], [
                    $text('underlined '),
                    new AstNode('emph', [], [$text('source')]),
                ]),
                $text(' and '),
                new AstNode('small_caps', [], [
                    $text('source glossary'),
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(implode("\n", [
            'Quote handoff: \'reviewer source\' and "see [source](/source)".',
            '',
            'Editorial marks: [underlined *source*]{.underline} and [source glossary]{.smallcaps}.',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer quoted smart disabled fallbacks' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Quote handoff: '),
                new AstNode('quoted', ['kind' => 'single'], [
                    $text('reviewer source'),
                ]),
                $text(' and '),
                new AstNode('quoted', ['kind' => 'double'], [
                    $text('see '),
                    new AstNode('link', ['url' => '/source'], [$text('source')]),
                ]),
                $text('.'),
            ]),
        ]);
        $literalText = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Literal "source" and it\'s 1987--1999...'),
            ]),
        ]);

        $t->same(
            "Quote handoff: 'reviewer source' and \"see [source](/source)\".",
            (new MarkdownWriter())->write($document)
        );
        $t->same(
            "Quote handoff: \u{2018}reviewer source\u{2019} and \u{201C}see [source](/source)\u{201D}.",
            (new MarkdownWriter(['smart' => false]))->write($document)
        );
        $t->same(
            'Quote handoff: &lsquo;reviewer source&rsquo; and &ldquo;see [source](/source)&rdquo;.',
            (new MarkdownWriter(['smart' => false, 'preferAscii' => true]))->write($document)
        );
        $t->same(
            'Literal "source" and it\'s 1987--1999...',
            (new MarkdownWriter(['smart' => false]))->write($literalText)
        );
    },
    'maps upstream markdown writer preferAscii str entity conversion' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text("Prefer ASCII: Résumé © ∈ 😀 and “quoted”… 1987–1999 — α."),
            ]),
        ]);

        $t->same(
            'Prefer ASCII: R&eacute;sum&eacute; &COPY; &in; &#128512; and "quoted"... 1987--1999 --- &alpha;.',
            (new MarkdownWriter(['preferAscii' => true]))->write($document)
        );
        $t->same(
            'Prefer ASCII: R&eacute;sum&eacute; &COPY; &in; &#128512; and &ldquo;quoted&rdquo;&mldr; 1987&ndash;1999 &mdash; &alpha;.',
            (new MarkdownWriter(['preferAscii' => true, 'smart' => false]))->write($document)
        );
    },
    'maps upstream markdown writer linebreak option branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Line break handoff: source'),
                new AstNode('linebreak'),
                $text('reviewer continuation.'),
            ]),
        ]);

        $t->same(
            "Line break handoff: source\\\nreviewer continuation.",
            (new MarkdownWriter())->write($document)
        );
        $t->same(
            "Line break handoff: source  \nreviewer continuation.",
            (new MarkdownWriter(['escapedLineBreaks' => false]))->write($document)
        );
        $t->same(
            "Line break handoff: source\nreviewer continuation.",
            (new MarkdownWriter(['hardLineBreaks' => true]))->write($document)
        );
    },
    'maps upstream markdown writer softbreak wrap option branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer source line'),
                new AstNode('softbreak'),
                $text('editor continuation.'),
            ]),
        ]);

        $t->same(
            'Reviewer source line editor continuation.',
            (new MarkdownWriter())->write($document)
        );
        $t->same(
            'Reviewer source line editor continuation.',
            (new MarkdownWriter(['wrap' => 'none']))->write($document)
        );
        $t->same(
            "Reviewer source line\neditor continuation.",
            (new MarkdownWriter(['wrap' => 'preserve']))->write($document)
        );
        $t->same(
            "Reviewer source line\neditor continuation.",
            (new MarkdownWriter(['wrap' => 'wrap-preserve']))->write($document)
        );
        $t->same(
            'Reviewer source line editor continuation.',
            (new MarkdownWriter(['wrap' => 'preserve', 'hardLineBreaks' => true]))->write($document)
        );
    },
    'maps upstream markdown writer line block and fallback branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $nbsp = "\xC2\xA0";
        $document = new AstNode('document', [], [
            new AstNode('line_block', [], [
                new AstNode('line', [], [
                    $text('Reviewer import stanza'),
                ]),
                new AstNode('line', [], [
                    $text(str_repeat($nbsp, 2) . 'preserve source indentation'),
                ]),
                new AstNode('line', ['text' => '']),
                new AstNode('line', [], [
                    $text('Continuation '),
                    new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit')]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| Reviewer import stanza',
            '| ' . str_repeat($nbsp, 2) . 'preserve source indentation',
            '|',
            '| Continuation [edit](/wp-admin/post.php?post=42&action=edit)',
        ]), (new MarkdownWriter())->write($document));
        $t->same(implode("\n", [
            'Reviewer import stanza\\',
            str_repeat($nbsp, 2) . 'preserve source indentation\\',
            '\\',
            'Continuation [edit](/wp-admin/post.php?post=42&action=edit)',
        ]), (new MarkdownWriter(['lineBlocks' => false]))->write($document));
        $t->same(implode("\n", [
            'Reviewer import stanza\\',
            str_repeat($nbsp, 2) . 'preserve source indentation\\',
            '\\',
            'Continuation [edit](/wp-admin/post.php?post=42&action=edit)',
        ]), (new MarkdownWriter(['variant' => 'commonmark']))->write($document));
        $t->same(implode("\n", [
            '| Reviewer import stanza',
            '| ' . str_repeat($nbsp, 2) . 'preserve source indentation',
            '|',
            '| Continuation [edit](/wp-admin/post.php?post=42&action=edit)',
        ]), (new MarkdownWriter(['variant' => 'commonmark', 'lineBlocks' => true]))->write($document));
    },
    'maps upstream plain writer line block branch' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $nbsp = "\xC2\xA0";
        $document = new AstNode('document', [], [
            new AstNode('line_block', [], [
                new AstNode('line', [], [
                    $text('Reviewer '),
                    new AstNode('emph', [], [$text('import')]),
                    $text(' stanza'),
                ]),
                new AstNode('line', [], [
                    $text(str_repeat($nbsp, 2) . 'preserve source indentation'),
                ]),
                new AstNode('line', ['text' => '']),
                new AstNode('line', [], [
                    new AstNode('link', [
                        'url' => '/wp-admin/post.php?post=42&action=edit',
                    ], [
                        $text('edit source'),
                    ]),
                    $text(' with '),
                    new AstNode('code', ['text' => 'wp_update_post']),
                ]),
                new AstNode('line', [], [
                    new AstNode('link', [
                        'url' => 'https://example.test/import-review',
                        'classes' => ['uri'],
                    ], [
                        $text('https://example.test/import-review'),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            'Reviewer import stanza',
            str_repeat($nbsp, 2) . 'preserve source indentation',
            '',
            'edit source with wp_update_post',
            'https://example.test/import-review',
        ]), (new MarkdownWriter(['variant' => 'plain']))->write($document));
        $t->same(implode("\n", [
            'Reviewer import stanza',
            str_repeat($nbsp, 2) . 'preserve source indentation',
            '',
            'edit source with wp_update_post',
            'https://example.test/import-review',
        ]), (new MarkdownWriter(['variant' => 'plain', 'lineBlocks' => false]))->write($document));
    },
    'maps upstream plain writer block branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $plainItem = static fn (string $value): AstNode => new AstNode('list_item', [], [$text($value)]);
        $document = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 1,
                'id' => 'imported-review',
                'classes' => ['source-review'],
            ], [
                $text('Imported '),
                new AstNode('emph', [], [$text('Review')]),
            ]),
            $paragraph([
                $text('Body '),
                new AstNode('link', ['url' => '/source'], [$text('link')]),
                $text(' and '),
                new AstNode('code', ['text' => 'wp_update_post']),
                $text('.'),
            ]),
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('Quote '),
                    new AstNode('strong', [], [$text('needs')]),
                    $text(' '),
                    new AstNode('link', ['url' => '/archive'], [$text('source')]),
                    $text('.'),
                ]),
                $paragraph([$text('Second paragraph')]),
            ]),
            new AstNode('raw_block', [
                'format' => 'plain',
                'text' => "raw reviewer note\nsecond line",
            ]),
            new AstNode('raw_html', ['html' => '<aside data-source="batch-42">HTML omitted from plain export</aside>']),
            new AstNode('horizontal_rule'),
            new AstNode('bullet_list', [], [$plainItem('First queue')]),
            new AstNode('bullet_list', [], [$plainItem('Second queue')]),
        ]);

        $t->same(implode("\n", [
            'Imported Review',
            '',
            'Body link and wp_update_post.',
            '',
            '  Quote needs source.',
            '  ',
            '  Second paragraph',
            '',
            'raw reviewer note',
            'second line',
            '',
            '------------',
            '',
            '- First queue',
            '',
            '- Second queue',
        ]), (new MarkdownWriter(['variant' => 'plain', 'columns' => 12]))->write($document));
    },
    'maps upstream plain writer list and definition list branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $term = static fn (array $children, string $fallback): AstNode => new AstNode('term', ['text' => $fallback], $children);
        $definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
        $item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
            'definition_item',
            ['term' => $term->attr('text', '')],
            array_merge([$term], $definitions)
        );

        $lists = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    $text('Review '),
                    new AstNode('emph', [], [$text('source')]),
                    $text(' via '),
                    new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit link')]),
                    $text(' and '),
                    new AstNode('code', ['text' => 'wp_update_post']),
                ]),
            ]),
            new AstNode('ordered_list', ['start' => 7], [
                new AstNode('list_item', [], [
                    $paragraph([
                        $text('Notify '),
                        new AstNode('strong', [], [$text('reviewer')]),
                        $text(' with import batch'),
                    ]),
                ]),
            ]),
        ]);
        $definitions = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([
                    new AstNode('emph', [], [$text('Reusable')]),
                    $text(' block'),
                ], 'Reusable block'), [
                    $definition([$plain([
                        $text('Synced pattern from '),
                        new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('source edit')]),
                    ])]),
                    $definition([$plain([
                        $text('Needs '),
                        new AstNode('strong', [], [$text('editor')]),
                        $text(' confirmation'),
                    ])]),
                ]),
                $item($term([$text('Shortcode cleanup')], 'Shortcode cleanup'), [
                    $definition([
                        $paragraph([
                            $text('Review '),
                            new AstNode('emph', [], [$text('shortcode')]),
                            $text(' source.'),
                        ]),
                        new AstNode('code_block', ['text' => '[gallery ids="12,13"]']),
                        new AstNode('blockquote', [], [
                            $paragraph([$text('quoted note')]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '- Review source via edit link and wp_update_post',
            '',
            '7.  Notify reviewer with import batch',
        ]), (new MarkdownWriter(['variant' => 'plain']))->write($lists));

        $plainDefinitions = (new MarkdownWriter(['variant' => 'plain']))->write($definitions);
        $t->same(implode("\n", [
            'Reusable block',
            '  Synced pattern from source edit',
            '  Needs editor confirmation',
            '',
            'Shortcode cleanup',
            '',
            '  Review shortcode source.',
            '',
            '      [gallery ids="12,13"]',
            '',
            '    quoted note',
        ]), $plainDefinitions);
        $t->true(!str_contains($plainDefinitions, ':'), 'PlainText definition lists use a space leader instead of Markdown definition markers');
    },
    'maps upstream plain writer image and note branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
        $document = new AstNode('document', [], [
            new AstNode('figure', [
                'caption' => 'Reviewer screenshot',
            ], [
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/reviewer-screenshot.jpg',
                    'title' => 'Reviewer screenshot',
                    'alt' => 'Reviewer screenshot',
                ], [$text('Reviewer screenshot')]),
            ]),
            $paragraph([
                $text('Inline media '),
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/source.jpg',
                    'alt' => 'https://example.test/uploads/source.jpg',
                ], [$text('https://example.test/uploads/source.jpg')]),
                $text(' keeps its guard and note'),
                $note([
                    $paragraph([
                        $text('Confirm media ID via '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('edit link')]),
                        $text(' and '),
                        new AstNode('code', ['text' => 'wp_update_post']),
                        $text('.'),
                    ]),
                    $paragraph([$text('Second note paragraph')]),
                    new AstNode('code_block', ['text' => "do_action('import_note');"]),
                ]),
                $text(' plus follow-up'),
                $note([
                    $paragraph([
                        $text('Follow-up '),
                        new AstNode('emph', [], [$text('editorial')]),
                        $text(' note.'),
                    ]),
                ]),
                $text('.'),
            ]),
        ]);

        $plain = (new MarkdownWriter(['variant' => 'plain']))->write($document);

        $t->same(implode("\n", [
            '[Reviewer screenshot]',
            '',
            'Inline media [] keeps its guard and note[1] plus follow-up[2].',
            '',
            '[1] Confirm media ID via edit link and wp_update_post.',
            '',
            'Second note paragraph',
            '',
            "    do_action('import_note');",
            '',
            '[2] Follow-up editorial note.',
        ]), $plain);
        $t->true(!str_contains($plain, '![Reviewer screenshot]'), 'PlainText image output should not leak Markdown image syntax');
        $t->true(!str_contains($plain, '[^1]:'), 'PlainText notes use bracketed numeric labels without Markdown footnote markers');
    },
    'maps upstream plain writer gutenberg strong and emphasis branch' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('strong', [], [$text('Straße')]),
                $text(' review via '),
                new AstNode('strong', [], [
                    new AstNode('link', [
                        'url' => '/wp-admin/post.php?post=42&action=edit',
                    ], [$text('source edit')]),
                    $text(' with '),
                    new AstNode('code', ['text' => 'wp_update_post']),
                    $text(' and '),
                    new AstNode('emph', [], [$text('urgent')]),
                ]),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Emphasis '),
                new AstNode('emph', [], [$text('visible')]),
                $text(' but nested '),
                new AstNode('emph', [], [
                    new AstNode('emph', [], [$text('collapses')]),
                ]),
                $text('.'),
            ]),
        ]);

        $plain = (new MarkdownWriter(['variant' => 'plain']))->write($document);
        $gutenbergPlain = (new MarkdownWriter(['variant' => 'plain', 'gutenberg' => true]))->write($document);

        $t->same(implode("\n", [
            'Straße review via source edit with wp_update_post and urgent.',
            '',
            'Emphasis visible but nested collapses.',
        ]), $plain);
        $t->same(implode("\n", [
            'STRASSE review via SOURCE EDIT WITH wp_update_post AND _URGENT_.',
            '',
            'Emphasis _visible_ but nested collapses.',
        ]), $gutenbergPlain);
        $t->contains('STRASSE', $gutenbergPlain, 'PlainText Gutenberg strong uppercase handles German sharp-s expansion');
        $t->contains('wp_update_post', $gutenbergPlain, 'PlainText Gutenberg capitalization preserves code-span source tokens');
        $t->true(!str_contains($gutenbergPlain, '/wp-admin/post.php'), 'PlainText Gutenberg links still render labels, not Markdown destinations');
    },
    'maps upstream html writer code sample and variable roles' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter();

        $t->same('<code>@&amp;</code>', $writer->write($plain(new AstNode('code', ['text' => '@&']))));
        $t->same('<code>Answer is 42</code>', $writer->write($plain(new AstNode('code', ['text' => 'Answer is 42']))));
        $t->same('<samp>Answer is 42</samp>', $writer->write($plain(new AstNode('code', [
            'text' => 'Answer is 42',
            'classes' => ['sample'],
        ]))));
        $t->same('<var>result</var>', $writer->write($plain(new AstNode('code', [
            'text' => 'result',
            'classes' => ['variable'],
        ]))));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer diagnostics: '),
                new AstNode('code', ['text' => 'core/image']),
                $text(', '),
                new AstNode('code', ['text' => 'Missing alt text', 'classes' => ['sample']]),
                $text(', and '),
                new AstNode('code', ['text' => 'post_title', 'classes' => ['variable']]),
                $text('.'),
            ]),
        ]);
        $html = $writer->write($review);

        $t->contains('<p>Reviewer diagnostics: <code>core/image</code>, <samp>Missing alt text</samp>, and <var>post_title</var>.</p>', $html);
        $t->true(!str_contains($html, 'class="sample"'), 'Bare sample code role should render as samp, not a classed code element');
        $t->true(!str_contains($html, 'class="variable"'), 'Bare variable code role should render as var, not a classed code element');
    },
    'maps upstream html writer highlighted code sample and variable roles' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter();

        $t->same('<code class="sourceCode haskell"><span class="op">&gt;&gt;=</span></code>', $writer->write($plain(new AstNode('code', [
            'text' => '>>=',
            'classes' => ['haskell'],
        ]))));
        $t->same('<code class="nolanguage">&gt;&gt;=</code>', $writer->write($plain(new AstNode('code', [
            'text' => '>>=',
            'classes' => ['nolanguage'],
        ]))));
        $t->same('<samp><code class="sourceCode haskell sample"><span class="op">&gt;&gt;=</span></code></samp>', $writer->write($plain(new AstNode('code', [
            'text' => '>>=',
            'classes' => ['sample', 'haskell'],
        ]))));
        $t->same('<var><code class="sourceCode haskell variable"><span class="op">&gt;&gt;=</span></code></var>', $writer->write($plain(new AstNode('code', [
            'text' => '>>=',
            'classes' => ['haskell', 'variable'],
        ]))));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer transform: '),
                new AstNode('code', ['text' => 'publish >>= notify', 'classes' => ['sample', 'haskell']]),
                $text(' stores '),
                new AstNode('code', ['text' => 'postId >>= save', 'classes' => ['haskell', 'variable']]),
                $text('.'),
            ]),
        ]);
        $html = $writer->write($review);

        $t->contains('<samp><code class="sourceCode haskell sample">publish <span class="op">&gt;&gt;=</span> notify</code></samp>', $html);
        $t->contains('<var><code class="sourceCode haskell variable">postId <span class="op">&gt;&gt;=</span> save</code></var>', $html);
        $t->true(!str_contains($html, 'class="sourceCode nolanguage"'), 'nolanguage should bypass the highlighted code branch');
    },
    'maps upstream html writer styled inline constructors' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter();

        $t->same('<u>review &lt;source&gt;</u>', $writer->write($plain(new AstNode('underline', [], [
            $text('review <source>'),
        ]))));
        $t->same('<del>stale caption</del>', $writer->write($plain(new AstNode('strikeout', [], [
            $text('stale caption'),
        ]))));
        $t->same('<span class="smallcaps">source glossary</span>', $writer->write($plain(new AstNode('small_caps', [], [
            $text('source glossary'),
        ]))));
        $t->same('<sup>2</sup>', $writer->write($plain(new AstNode('superscript', [], [$text('2')]))));
        $t->same('<sub>2</sub>', $writer->write($plain(new AstNode('subscript', [], [$text('2')]))));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('HTML styled review: '),
                new AstNode('underline', [], [
                    $text('manual '),
                    new AstNode('emph', [], [$text('check')]),
                ]),
                $text(', '),
                new AstNode('strikeout', [], [$text('legacy shortcode')]),
                $text(', '),
                new AstNode('small_caps', [], [$text('source glossary')]),
                $text(', H'),
                new AstNode('subscript', [], [$text('2')]),
                $text('O, and note'),
                new AstNode('superscript', [], [
                    new AstNode('link', [
                        'url' => '/wp-admin/post.php?post=42&action=edit',
                    ], [$text('42')]),
                ]),
                $text('.'),
            ]),
        ]);
        $preview = $writer->write($review);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
            new AstNode('raw_html', [
                'html' => '<section class="pandoc-inline-review" data-pandoc-source="html-writer-styled-inlines">' . $preview . '</section>',
            ]),
        ]));

        $t->same('<p>HTML styled review: <u>manual <em>check</em></u>, <del>legacy shortcode</del>, <span class="smallcaps">source glossary</span>, H<sub>2</sub>O, and note<sup><a href="/wp-admin/post.php?post=42&amp;action=edit">42</a></sup>.</p>', $preview);
        $t->contains('data-pandoc-source="html-writer-styled-inlines"', $blocks);
        $t->contains('<u>manual <em>check</em></u>', $blocks);
        $t->contains('<span class="smallcaps">source glossary</span>', $blocks);
    },
    'maps upstream html writer span-like class lowering' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter();

        $t->same('<kbd id="bar"><u><span class="smallcaps">test</span></u></kbd>', $writer->write($plain(new AstNode('span', [
            'id' => 'bar',
            'classes' => ['foo', 'underline', 'smallcaps', 'kbd'],
        ], [$text('test')]))));
        $t->same('<mark class="review" data-source="batch-42"><u>publish key</u></mark>', $writer->write($plain(new AstNode('span', [
            'classes' => ['underline', 'review', 'mark'],
            'attributes' => ['data-source' => 'batch-42'],
        ], [$text('publish key')]))));
        $t->same('<dfn><abbr>HTML</abbr></dfn>', $writer->write($plain(new AstNode('span', [
            'classes' => ['abbr', 'dfn'],
        ], [$text('HTML')]))));
        $t->same('<span class="source" style="font-style:normal;font-weight:normal;color:red;">reset citation style</span>', $writer->write($plain(new AstNode('span', [
            'classes' => ['csl-no-emph', 'source', 'csl-no-strong'],
            'attributes' => ['style' => 'color:red;'],
        ], [$text('reset citation style')]))));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('HTML span-like review: '),
                new AstNode('span', [
                    'id' => 'shortcut-source',
                    'classes' => ['source-note', 'underline', 'smallcaps', 'kbd'],
                    'attributes' => ['data-pandoc-review' => 'shortcut'],
                ], [$text('Ctrl+Alt+P')]),
                $text(' opens '),
                new AstNode('span', [
                    'classes' => ['mark', 'review-highlight'],
                ], [$text('publish preview')]),
                $text('.'),
            ]),
        ]);
        $preview = $writer->write($review);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
            new AstNode('raw_html', [
                'html' => '<section class="pandoc-spanlike-review" data-pandoc-source="html-writer-spanlike">' . $preview . '</section>',
            ]),
        ]));

        $t->same('<p>HTML span-like review: <kbd id="shortcut-source" data-pandoc-review="shortcut"><u><span class="smallcaps">Ctrl+Alt+P</span></u></kbd> opens <mark class="review-highlight">publish preview</mark>.</p>', $preview);
        $t->true(!str_contains($preview, 'source-note'), 'Classes before the first upstream span-like class should not be retained on lowered HTML elements');
        $t->contains('data-pandoc-source="html-writer-spanlike"', $blocks);
        $t->contains('<kbd id="shortcut-source" data-pandoc-review="shortcut"><u><span class="smallcaps">Ctrl+Alt+P</span></u></kbd>', $blocks);
        $t->contains('<mark class="review-highlight">publish preview</mark>', $blocks);
    },
    'maps upstream html writer softbreak wrap and linebreak branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $softbreakDocument = $document([
            $paragraph([
                $text('Source summary:'),
                new AstNode('softbreak'),
                $text('Needs review before publish.'),
            ]),
        ]);
        $linebreakDocument = $document([
            $paragraph([
                $text('Checklist'),
                new AstNode('linebreak'),
                $text('Confirm media attribution.'),
            ]),
        ]);

        $t->same('<p>Source summary: Needs review before publish.</p>', (new HtmlWriter())->write($softbreakDocument));
        $t->same('<p>Source summary: Needs review before publish.</p>', (new HtmlWriter(['writerWrapText' => 'wrap-auto']))->write($softbreakDocument));
        $t->same('<p>Source summary: Needs review before publish.</p>', (new HtmlWriter(['writerWrapText' => 'wrap-none']))->write($softbreakDocument));
        $t->same("<p>Source summary:\nNeeds review before publish.</p>", (new HtmlWriter(['writerWrapText' => 'wrap-preserve']))->write($softbreakDocument));
        $t->same("<p>Checklist<br />\nConfirm media attribution.</p>", (new HtmlWriter())->write($linebreakDocument));

        $review = $document([
            $paragraph([
                $text('Legacy excerpt:'),
                new AstNode('softbreak'),
                new AstNode('emph', [], [$text('keep the source line fold')]),
                $text(' for reviewer context.'),
            ]),
            $paragraph([
                $text('Checklist'),
                new AstNode('linebreak'),
                $text('Confirm media attribution.'),
            ]),
        ]);
        $compactPreview = (new HtmlWriter(['writerWrapText' => 'wrap-none']))->write($review);
        $preservedPreview = (new HtmlWriter(['writerWrapText' => 'wrap-preserve']))->write($review);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
            new AstNode('raw_html', [
                'html' => '<section class="pandoc-softbreak-review" data-pandoc-source="html-writer-softbreak">' . $preservedPreview . '</section>',
            ]),
        ]));

        $t->contains('<p>Legacy excerpt: <em>keep the source line fold</em> for reviewer context.</p>', $compactPreview);
        $t->contains("<p>Legacy excerpt:\n<em>keep the source line fold</em> for reviewer context.</p>", $preservedPreview);
        $t->contains("Checklist<br />\nConfirm media attribution.", $preservedPreview);
        $t->contains('data-pandoc-source="html-writer-softbreak"', $blocks);
        $t->contains("<p>Legacy excerpt:\n<em>keep the source line fold</em> for reviewer context.</p>", $blocks);
    },
    'maps upstream html writer raw inline html pass through' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $raw = static fn (string $format, string $source): AstNode => new AstNode('raw_inline', [
            'format' => $format,
            'text' => $source,
        ]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $writer = new HtmlWriter();

        $t->same('<span data-source="legacy"><em>trusted</em></span>', $writer->write($plain($raw('html', '<span data-source="legacy"><em>trusted</em></span>'))));
        $t->same('<mark data-review="publish">ready</mark>', $writer->write($plain($raw('html5', '<mark data-review="publish">ready</mark>'))));
        $t->same('', $writer->write($plain($raw('tex', '\cite{wp-import}'))));

        $review = $document([
            new AstNode('paragraph', [], [
                $text('Inline review: '),
                $raw('html', '<span class="source-note">trusted <em>HTML</em></span>'),
                $text(', '),
                $raw('html5', '<mark data-review="publish">ready</mark>'),
                $raw('tex', '\cite{wp-import}'),
                $text('.'),
            ]),
        ]);
        $preview = $writer->write($review);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
            new AstNode('raw_html', [
                'html' => '<section class="pandoc-raw-inline-review" data-pandoc-source="html-writer-raw-inline">' . $preview . '</section>',
            ]),
        ]));

        $t->same('<p>Inline review: <span class="source-note">trusted <em>HTML</em></span>, <mark data-review="publish">ready</mark>.</p>', $preview);
        $t->true(!str_contains($preview, 'wp-import'), 'Non-HTML raw inline payloads should not render in the bounded HTML writer branch');
        $t->contains('data-pandoc-source="html-writer-raw-inline"', $blocks);
        $t->contains('<span class="source-note">trusted <em>HTML</em></span>', $blocks);
        $t->contains('<mark data-review="publish">ready</mark>', $blocks);
    },
    'maps upstream html writer code block fallback pre code output' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = static fn (AstNode $block): AstNode => new AstNode('document', [], [$block]);
        $writer = new HtmlWriter();

        $plainCode = new AstNode('code_block', [
            'text' => "Answer is <42> & rising\nnext line",
        ]);
        $attributedCode = new AstNode('code_block', [
            'id' => 'source-snippet',
            'classes' => ['migration-review'],
            'attributes' => [
                'data-source' => 'legacy-shortcode',
                'data-row' => '42',
            ],
            'text' => 'if ($a < $b && $c > 0) { return $b; }',
        ]);

        $t->same("<pre><code>Answer is &lt;42&gt; &amp; rising\nnext line</code></pre>", $writer->write($document($plainCode)));
        $t->same(
            '<pre id="source-snippet" class="migration-review" data-source="legacy-shortcode" data-row="42"><code>if ($a &lt; $b &amp;&amp; $c &gt; 0) { return $b; }</code></pre>',
            $writer->write($document($attributedCode))
        );

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [$text('Reviewer source snippet before block import:')]),
            new AstNode('code_block', [
                'id' => 'source-filter',
                'attributes' => [
                    'data-source' => 'classic-widget',
                ],
                'text' => "if (\$post_id > 0) {\n    clean_post_cache(\$post_id);\n}",
            ]),
        ]);
        $html = $writer->write($review);

        $t->contains('<p>Reviewer source snippet before block import:</p>', $html);
        $t->contains('<pre id="source-filter" data-source="classic-widget"><code>if ($post_id &gt; 0) {', $html);
        $t->contains("    clean_post_cache(\$post_id);\n}</code></pre>", $html);
    },
    'maps upstream html writer structural figure line and horizontal rule blocks' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $writer = new HtmlWriter();
        $lineBlock = new AstNode('line_block', [], [
            new AstNode('line', [], [
                $text('Reviewer '),
                new AstNode('emph', [], [$text('stanza')]),
            ]),
            new AstNode('line', ['text' => '']),
            new AstNode('line', [], [
                new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit source')]),
            ]),
        ]);
        $figure = new AstNode('figure', [
            'id' => 'fig-lalune',
            'classes' => ['review-frame'],
            'attributes' => [
                'data-source' => 'testsuite-images',
            ],
            'caption' => 'lalune',
        ], [
            new AstNode('plain', [], [
                new AstNode('image', [
                    'url' => 'lalune.jpg',
                    'title' => 'Voyage dans la Lune',
                    'alt' => 'lalune',
                ], [$text('lalune')]),
            ]),
        ]);

        $t->same('<hr />', $writer->write($document([new AstNode('horizontal_rule')])));
        $t->same(
            '<div class="line-block">Reviewer <em>stanza</em><br /><br /><a href="/wp-admin/post.php?post=42&amp;action=edit">edit source</a></div>',
            $writer->write($document([$lineBlock]))
        );
        $t->same(implode("\n", [
            '<figure id="fig-lalune" class="review-frame" data-source="testsuite-images">',
            '<img src="lalune.jpg" title="Voyage dans la Lune" alt="lalune" />',
            '<figcaption aria-hidden="true">lalune</figcaption>',
            '</figure>',
        ]), $writer->write($document([$figure])));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [$text('HTML preview before WordPress import:')]),
            $figure,
            $lineBlock,
            new AstNode('horizontal_rule'),
        ]);
        $html = $writer->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('<p>HTML preview before WordPress import:</p>', $html);
        $t->contains('<figcaption aria-hidden="true">lalune</figcaption>', $html);
        $t->contains('<div class="line-block">Reviewer <em>stanza</em><br /><br />', $html);
        $t->contains('<hr />', $html);
        $t->contains('<figure class="wp-block-image review-frame" id="fig-lalune" data-source="testsuite-images"><img src="lalune.jpg" alt="lalune" title="Voyage dans la Lune"/><figcaption>lalune</figcaption></figure>', $blocks);
        $t->contains('<p>Reviewer <em>stanza</em><br/><br/><a href="/wp-admin/post.php?post=42&amp;action=edit">edit source</a></p>', $blocks);
        $t->contains('<!-- wp:separator -->', $blocks);
    },
    'maps upstream html writer media categories from video audio command fixture' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (AstNode $inline): AstNode => new AstNode('paragraph', [], [$inline]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $writer = new HtmlWriter();

        $media = $document([
            $paragraph(new AstNode('image', ['url' => './test.mp4'])),
            $paragraph(new AstNode('image', [
                'url' => 'foo/test.webm',
                'attributes' => [
                    'width' => '300',
                ],
            ], [$text('Your browser does not support video.')])),
            $paragraph(new AstNode('image', ['url' => 'test.mp3'])),
            $paragraph(new AstNode('image', ['url' => './test.pdf'])),
            $paragraph(new AstNode('image', ['url' => './test.jpg'])),
        ]);

        $t->same(implode("\n", [
            '<p><video src="./test.mp4" controls=""><a href="./test.mp4">Video</a></video></p>',
            '<p><video src="foo/test.webm" width="300" controls=""><a href="foo/test.webm">Your browser does not support video.</a></video></p>',
            '<p><audio src="test.mp3" controls=""><a href="test.mp3">Audio</a></audio></p>',
            '<p><embed src="./test.pdf" /></p>',
            '<p><img src="./test.jpg" /></p>',
        ]), $writer->write($media));

        $review = $document([
            new AstNode('paragraph', [], [$text('HTML media preview before WordPress import:')]),
            $paragraph(new AstNode('image', [
                'url' => 'https://example.test/uploads/release-walkthrough.mp4',
            ], [$text('Release walkthrough')])),
            $paragraph(new AstNode('image', [
                'url' => 'https://example.test/uploads/release-notes.pdf',
                'title' => 'Release notes PDF',
            ])),
        ]);
        $html = $writer->write($review);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
            new AstNode('raw_html', [
                'html' => '<section class="pandoc-media-review" data-pandoc-source="html-writer-media">' . $html . '</section>',
            ]),
        ]));

        $t->contains('<video src="https://example.test/uploads/release-walkthrough.mp4" controls=""><a href="https://example.test/uploads/release-walkthrough.mp4">Release walkthrough</a></video>', $html);
        $t->contains('<embed src="https://example.test/uploads/release-notes.pdf" title="Release notes PDF" />', $html);
        $t->contains('data-pandoc-source="html-writer-media"', $blocks);
        $t->contains('<video src="https://example.test/uploads/release-walkthrough.mp4"', $blocks);
        $t->contains('<embed src="https://example.test/uploads/release-notes.pdf"', $blocks);
    },
    'maps upstream html writer raw blocks and native div wrappers' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $writer = new HtmlWriter();

        $rawHtml = new AstNode('raw_block', [
            'format' => 'html',
            'text' => '<aside data-source="batch-42">Trusted source aside</aside>',
        ]);
        $rawHtml5 = new AstNode('raw_block', [
            'format' => 'html5',
            'text' => '<section data-source="html5">HTML5 raw handoff</section>',
        ]);
        $rawTex = new AstNode('raw_block', [
            'format' => 'tex',
            'text' => "\\begin{tabular}{ll}\nsource & value\n\\end{tabular}",
        ]);
        $plainDiv = new AstNode('div', [], [
            new AstNode('plain', [], [$text('foo')]),
        ]);
        $review = new AstNode('div', [
            'id' => 'review-body',
            'classes' => ['section', 'wp-import-review'],
            'attributes' => ['data-source' => 'legacy-export'],
        ], [
            new AstNode('paragraph', [], [$text('HTML preview before WordPress import.')]),
            $rawHtml,
            $rawHtml5,
            new AstNode('div', ['classes' => ['nested']], [
                new AstNode('plain', [], [$text('Nested reviewer note')]),
            ]),
            $rawTex,
        ]);

        $t->same("<div>\nfoo\n</div>", $writer->write($document([$plainDiv])));
        $t->same('<aside data-source="batch-42">Trusted source aside</aside>', $writer->write($document([$rawHtml])));
        $t->same('', $writer->write($document([$rawTex])));
        $t->same(implode("\n", [
            '<section id="review-body" class="wp-import-review" data-source="legacy-export">',
            '<p>HTML preview before WordPress import.</p>',
            '<aside data-source="batch-42">Trusted source aside</aside>',
            '<section data-source="html5">HTML5 raw handoff</section>',
            '<div class="nested">',
            'Nested reviewer note',
            '</div>',
            '</section>',
        ]), $writer->write($document([$review])));

        $html = $writer->write($document([$review]));
        $blocks = (new WordPressBlockWriter())->write($document([$review]));

        $t->true(!str_contains($html, 'tabular'), 'Non-HTML raw blocks should be omitted from HTML writer output');
        $t->contains('<div id="review-body" class="section wp-import-review" data-source="legacy-export">', $blocks);
        $t->contains('<pre class="wp-block-code pandoc-raw-html" data-pandoc-raw-format="html"><code class="language-html">&lt;aside data-source=&quot;batch-42&quot;&gt;Trusted source aside&lt;/aside&gt;</code></pre>', $blocks);
        $t->contains('<pre class="wp-block-code pandoc-raw-tex" data-pandoc-raw-format="tex"><code class="language-tex">\\begin{tabular}{ll}', $blocks);
    },
    'maps upstream html writer wrapper and csl div branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $writer = new HtmlWriter();

        $wrapper = new AstNode('div', [
            'id' => 'source-wrapper',
            'classes' => ['source-packet'],
            'attributes' => [
                'wrapper' => '1',
                'data-source' => 'batch-42',
            ],
        ], [
            new AstNode('paragraph', [], [
                $text('Wrapped '),
                new AstNode('strong', [], [$text('review')]),
                $text(' packet.'),
            ]),
        ]);
        $bibliography = new AstNode('div', [
            'id' => 'refs',
            'classes' => ['csl-bib-body'],
        ], [
            new AstNode('div', [
                'id' => 'ref-source-audit',
                'classes' => ['csl-entry'],
                'attributes' => ['data-source' => 'citation-export'],
            ], [
                new AstNode('paragraph', [], [
                    $text('Doe, J. '),
                    new AstNode('emph', [], [$text('Migration source audit')]),
                    $text('.'),
                ]),
                new AstNode('paragraph', [], [
                    $text('Retrieved from '),
                    new AstNode('link', [
                        'url' => 'https://example.test/source-audit',
                    ], [$text('source archive')]),
                    $text('.'),
                ]),
            ]),
        ]);

        $t->same(
            '<p id="source-wrapper" class="source-packet" data-source="batch-42">Wrapped <strong>review</strong> packet.</p>',
            $writer->write($document([$wrapper]))
        );
        $t->same(implode("\n", [
            '<div id="refs" class="csl-bib-body" role="list">',
            '<div id="ref-source-audit" class="csl-entry" data-source="citation-export" role="listitem">',
            'Doe, J. <em>Migration source audit</em>.',
            'Retrieved from <a href="https://example.test/source-audit">source archive</a>.',
            '</div>',
            '</div>',
        ]), $writer->write($document([$bibliography])));

        $blocks = (new WordPressBlockWriter())->write($document([$wrapper, $bibliography]));

        $t->true(!str_contains($writer->write($document([$wrapper])), 'wrapper='), 'Wrapper marker should not leak to HTML output');
        $t->contains('<div id="refs" class="csl-bib-body">', $blocks);
        $t->contains('<div id="ref-source-audit" class="csl-entry" data-source="citation-export">', $blocks);
        $t->contains('Migration source audit', $blocks);
    },
    'maps upstream html writer citation biblioref and footnote document roles' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $writer = new HtmlWriter();
        $back = "\u{21A9}\u{FE0E}";

        $citation = new AstNode('citation', [
            'citations' => [
                ['id' => 'source-audit'],
                ['citationId' => 'media-review'],
            ],
        ], [
            $text('See '),
            new AstNode('link', ['url' => '#ref-source-audit'], [$text('Source Audit')]),
            $text(' and '),
            new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('review post')]),
        ]);
        $note = new AstNode('note', [], [
            $paragraph([
                $text('Review the matching source entry before publishing.'),
            ]),
        ]);
        $review = $document([
            $paragraph([
                $text('Bibliography pointer '),
                $citation,
                $text(' keeps reviewer metadata.'),
            ]),
            $paragraph([
                $text('Footnote pointer'),
                $note,
                $text(' stays accessible.'),
            ]),
        ]);

        $html = $writer->write($review);

        $t->contains('<span class="citation" data-cites="source-audit media-review">See <a href="#ref-source-audit" role="doc-biblioref">Source Audit</a> and <a href="/wp-admin/post.php?post=42&amp;action=edit">review post</a></span>', $html);
        $t->contains('<a href="#fn1" class="footnote-ref" id="fnref1" role="doc-noteref"><sup>1</sup></a>', $html);
        $t->contains('<a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a>', $html);

        $fallback = new AstNode('citation', ['id' => 'fallback-source'], []);
        $t->same('<span class="citation" data-cites="fallback-source">[@fallback-source]</span>', $writer->write($document([new AstNode('plain', [], [$fallback])])));
    },
    'maps upstream html writer mathjax and katex math spans' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
            'text' => $text,
            'display' => $display,
        ]);
        $document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);

        $inlineMath = $math('x < y');
        $displayMath = $math('\alpha + \omega \times x^2', true);
        $mathjax = new HtmlWriter(['htmlMathMethod' => 'mathjax']);
        $katex = new HtmlWriter(['writerHTMLMathMethod' => ['method' => 'katex']]);
        $review = $document([
            $paragraph([
                $text('Equation handoff: '),
                $math('\langle post_id,media_id \rangle'),
                $text(' before publishing.'),
            ]),
            $paragraph([
                $text('Display equation: '),
                $displayMath,
            ]),
        ]);

        $t->same('<span class="math inline">\(x &lt; y\)</span>', $mathjax->write($plain($inlineMath)));
        $t->same('<span class="math display">\[\alpha + \omega \times x^2\]</span>', $mathjax->write($plain($displayMath)));
        $t->same('<span class="math inline">x &lt; y</span>', $katex->write($plain($inlineMath)));
        $t->same('<span class="math display">\alpha + \omega \times x^2</span>', $katex->write($plain($displayMath)));

        $html = $mathjax->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('<p>Equation handoff: <span class="math inline">\(\langle post_id,media_id \rangle\)</span> before publishing.</p>', $html);
        $t->contains('<span class="math display">\[\alpha + \omega \times x^2\]</span>', $html);
        $t->contains('<span class="math inline">\(\langle post_id,media_id \rangle\)</span>', $blocks);
        $t->contains('<span class="math display">\[\alpha + \omega \times x^2\]</span>', $blocks);
    },
    'falls back to source spans for malformed plainmath structural mathml' => static function (TestRunner $t): void {
        $math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
            'text' => $text,
            'display' => $display,
        ]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter(['htmlMathMethod' => 'mathml']);
        $valid = $writer->write($plain($math('\frac{a}{b}', true)));
        $cases = [
            '\frac{a}{' => '<span class="math inline">\frac{a}{</span>',
            '\sqrt{x' => '<span class="math inline">\sqrt{x</span>',
            '\left( x + y' => '<span class="math display">\left( x + y</span>',
            '\begin{pmatrix}a&b' => '<span class="math display">\begin{pmatrix}a&amp;b</span>',
        ];

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $valid);
        $t->contains('<mfrac><mi>a</mi><mi>b</mi></mfrac>', $valid);

        foreach ($cases as $tex => $expectedHtml) {
            $display = str_contains($tex, '\left') || str_contains($tex, '\begin');
            $html = $writer->write($plain($math($tex, $display)));
            $dom = new DOMDocument('1.0', 'UTF-8');

            $t->same($expectedHtml, $html);
            $t->true($dom->loadXML('<root>' . $html . '</root>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));
            $t->true(!str_contains($html, '<math'), 'Malformed PlainMath should not emit partial MathML for ' . $tex);
            $t->true(!str_contains($html, '<mi>\\'), 'Malformed PlainMath should not expose command-token MathML for ' . $tex);
        }
    },
    'maps upstream html writer webtex and gladtex math outputs' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
            'text' => $text,
            'display' => $display,
        ]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);

        $webtex = new HtmlWriter([
            'writerHTMLMathMethod' => [
                'method' => 'webtex',
                'url' => 'https://example.test/math?tex=',
            ],
        ]);
        $gladtex = new HtmlWriter(['htmlMathMethod' => 'gladtex']);
        $inlineMath = $math('  x < y  ');
        $displayMath = $math('\alpha + \omega \times x^2', true);
        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Equation image handoff: '),
                $math('\langle post_id,media_id \rangle'),
                $text(' stays inspectable.'),
            ]),
            new AstNode('paragraph', [], [
                $text('GladTeX handoff: '),
                $displayMath,
            ]),
        ]);

        $t->same(
            '<img style="vertical-align:middle" src="https://example.test/math?tex=%5Ctextstyle%20x%20%3C%20y" alt="x &lt; y" title="x &lt; y" class="math inline" />',
            $webtex->write($plain($inlineMath))
        );
        $t->same(
            '<img style="vertical-align:middle" src="https://example.test/math?tex=%5Cdisplaystyle%20%5Calpha%20%2B%20%5Comega%20%5Ctimes%20x%5E2" alt="\alpha + \omega \times x^2" title="\alpha + \omega \times x^2" class="math display" />',
            $webtex->write($plain($displayMath))
        );
        $t->same('<eq env="math">  x &lt; y  </eq>', $gladtex->write($plain($inlineMath)));
        $t->same('<eq env="displaymath">\alpha + \omega \times x^2</eq>', $gladtex->write($plain($displayMath)));

        $webtexHtml = $webtex->write($review);
        $gladtexHtml = $gladtex->write($review);
        $blocks = (new WordPressBlockWriter())->write($review);

        $t->contains('src="https://example.test/math?tex=%5Ctextstyle%20%5Clangle%20post_id%2Cmedia_id%20%5Crangle"', $webtexHtml);
        $t->contains('<eq env="displaymath">\alpha + \omega \times x^2</eq>', $gladtexHtml);
        $t->contains('<span class="math inline">\(\langle post_id,media_id \rangle\)</span>', $blocks);
        $t->contains('<span class="math display">\[\alpha + \omega \times x^2\]</span>', $blocks);
    },
    'maps upstream html writer table caption colgroup sections and spans' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
        $row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
        $writer = new HtmlWriter();

        $table = new AstNode('table', [
            'id' => 'migration-table',
            'classes' => ['audit-table'],
            'attributes' => ['data-source' => 'batch-42'],
            'captionInlines' => [
                new AstNode('strong', [], [$text('Migration')]),
                $text(' '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                    'title' => 'Edit imported post',
                ], [$text('source audit')]),
            ],
            'widths' => [0.25, 0.35, 0.0],
            'alignments' => ['left', 'right', 'default'],
        ], [
            new AstNode('table_head', ['classes' => ['source-head']], [
                $row([
                    $cell([$text('Field')]),
                    $cell([$text('Review status')], ['colspan' => 2, 'align' => 'center']),
                ]),
            ]),
            new AstNode('table_body', [
                'htmlAttributes' => ['data-phase' => 'import'],
                'rowHeadColumns' => 1,
            ], [
                $row([
                    $cell([$text('Posts')], ['attributes' => ['scope' => 'row']]),
                    $cell([$text('42')]),
                    $cell([new AstNode('paragraph', [], [$text('Needs <review>')])], ['rowspan' => 2]),
                ], ['classes' => ['flagged']]),
                $row([
                    $cell([$text('Media')], ['attributes' => ['scope' => 'row']]),
                    $cell([new AstNode('code', ['text' => 'ready()'])]),
                ]),
            ]),
            new AstNode('table_foot', [], [
                $row([
                    $cell([$text('Ready for block import')], ['colspan' => 3]),
                ]),
            ]),
        ]);

        $html = $writer->write(new AstNode('document', [], [$table]));
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same(implode("\n", [
            '<table id="migration-table" class="audit-table" data-source="batch-42" style="width:60%;">',
            '<caption><strong>Migration</strong> <a href="/wp-admin/post.php?post=42&amp;action=edit" title="Edit imported post">source audit</a></caption>',
            '<colgroup><col style="width: 25%" /><col style="width: 35%" /><col /></colgroup>',
            '<thead class="source-head">',
            '<tr><th style="text-align:left">Field</th><th colspan="2" style="text-align:center">Review status</th></tr>',
            '</thead>',
            '<tbody data-phase="import">',
            '<tr class="flagged"><th scope="row" style="text-align:left">Posts</th><td style="text-align:right">42</td><td rowspan="2"><p>Needs &lt;review&gt;</p></td></tr>',
            '<tr><th scope="row" style="text-align:left">Media</th><td style="text-align:right"><code>ready()</code></td></tr>',
            '</tbody>',
            '<tfoot>',
            '<tr><td colspan="3" style="text-align:left">Ready for block import</td></tr>',
            '</tfoot>',
            '</table>',
        ]), $html);
        $t->contains('<figure class="wp-block-table"><table id="migration-table" class="audit-table" data-source="batch-42"><thead class="source-head"><tr><th style="text-align:left">Field</th><th colspan="2" style="text-align:center">Review status</th></tr></thead>', $blocks);
        $t->contains('<tbody data-phase="import"><tr class="flagged"><th scope="row" style="text-align:left">Posts</th><td style="text-align:right">42</td><td rowspan="2"><p>Needs &lt;review&gt;</p></td>', $blocks);
        $t->contains('<tfoot><tr><td colspan="3" style="text-align:left">Ready for block import</td></tr></tfoot>', $blocks);
    },
    'maps upstream html writer image alt text and heading attribute filtering' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter();

        $image = new AstNode('image', [
            'url' => '/url',
            'title' => 'title',
        ], [
            $text('my '),
            new AstNode('emph', [], [$text('image')]),
        ]);
        $heading = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 1,
                'attributes' => [
                    'invalid' => '1',
                    'lang' => 'en',
                ],
            ], [
                $text('test'),
            ]),
        ]);

        $t->same('<img src="/url" title="title" alt="my image" />', $writer->write($plain($image)));
        $t->same('<h1 lang="en">test</h1>', $writer->write($heading));

        $review = new AstNode('document', [], [
            new AstNode('heading', [
                'level' => 2,
                'attributes' => [
                    'lang' => 'en',
                    'invalid' => 'drop-me',
                ],
            ], [
                $text('Media Review'),
            ]),
            new AstNode('paragraph', [], [
                $text('Featured asset: '),
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/imported-frame.jpg',
                    'title' => 'Original export frame',
                    'attributes' => [
                        'data-source' => 'legacy-export',
                    ],
                ], [
                    $text('imported '),
                    new AstNode('strong', [], [$text('frame')]),
                ]),
                $text('.'),
            ]),
        ]);
        $html = $writer->write($review);

        $t->contains('<h2 lang="en">Media Review</h2>', $html);
        $t->contains('<img src="https://example.test/uploads/imported-frame.jpg" title="Original export frame" alt="imported frame" data-source="legacy-export" />', $html);
        $t->true(!str_contains($html, 'drop-me'), 'HTML writer heading output should drop disallowed upstream attributes');
        $t->true(!str_contains($html, '<em>image</em>'), 'HTML writer image alt text should stringify formatted inlines');
    },
    'maps upstream html writer definition list empty terms' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $term = static fn (array $children = [], string $fallback = ''): AstNode => new AstNode('term', ['text' => $fallback], $children);
        $definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
        $item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
            'definition_item',
            ['term' => $term->attr('text', '')],
            array_merge([$term], $definitions)
        );
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

        $upstream = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term(), [
                    $definition([$paragraph([$text('foo bar')])]),
                ]),
            ]),
        ]);
        $review = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([$text('Source status')], 'Source status'), [
                    $definition([$paragraph([$text('Ready for WordPress block import')])]),
                ]),
                $item($term(), [
                    $definition([$paragraph([$text('Legacy export supplied a blank glossary term that must stay visible for review')])]),
                ]),
            ]),
        ]);
        $html = (new HtmlWriter())->write($review);

        $t->same(implode("\n", [
            '<dl>',
            '<dt></dt>',
            '<dd>',
            '<p>foo bar</p>',
            '</dd>',
            '</dl>',
        ]), (new HtmlWriter())->write($upstream));
        $t->contains('<dt>Source status</dt>', $html);
        $t->contains('<p>Ready for WordPress block import</p>', $html);
        $t->contains('<dt></dt>', $html, 'HTML writer should preserve empty definition terms for upstream parity and reviewer audit');
        $t->true(!str_contains($html, '<dt> </dt>'), 'Empty terms should not gain synthetic whitespace');
    },
    'maps upstream html writer list tags starts styles and task labels' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (string $textValue): AstNode => new AstNode('paragraph', [], [$text($textValue)]);
        $item = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
        $document = static fn (AstNode $node): AstNode => new AstNode('document', [], [$node]);
        $writer = new HtmlWriter();

        $tightBullets = new AstNode('bullet_list', [], [
            $item([$text('asterisk 1')]),
            $item([$text('asterisk 2')]),
        ]);
        $looseBullets = new AstNode('bullet_list', [], [
            $item([$paragraph('asterisk 1')], ['loose' => true]),
            $item([$paragraph('asterisk 2')], ['loose' => true]),
        ]);
        $numbered = new AstNode('ordered_list', [
            'start' => 3,
            'style' => 'lower_roman',
            'delimiter' => 'period',
        ], [
            $item([$text('First')]),
            $item([$text('Second')]),
        ]);
        $nested = new AstNode('ordered_list', [
            'start' => 1,
            'style' => 'decimal',
            'delimiter' => 'period',
        ], [
            $item([
                $text('Second:'),
                new AstNode('bullet_list', [], [
                    $item([$text('Fee')]),
                    $item([$text('Fie')]),
                ]),
            ]),
        ]);
        $tasks = new AstNode('bullet_list', ['taskList' => true], [
            $item([$text('Confirm media IDs')], ['taskChecked' => false]),
            $item([$text('Publish migrated list')], ['taskChecked' => true]),
        ]);

        $t->same(implode("\n", [
            '<ul>',
            '<li>asterisk 1</li>',
            '<li>asterisk 2</li>',
            '</ul>',
        ]), $writer->write($document($tightBullets)));
        $t->same(implode("\n", [
            '<ul>',
            '<li><p>asterisk 1</p></li>',
            '<li><p>asterisk 2</p></li>',
            '</ul>',
        ]), $writer->write($document($looseBullets)));
        $t->same(implode("\n", [
            '<ol start="3" type="i">',
            '<li>First</li>',
            '<li>Second</li>',
            '</ol>',
        ]), $writer->write($document($numbered)));
        $t->contains('<ol type="1">', $writer->write($document($nested)));
        $t->contains("<li>Second:\n<ul>\n<li>Fee</li>\n<li>Fie</li>\n</ul></li>", $writer->write($document($nested)));
        $t->same(implode("\n", [
            '<ul class="task-list">',
            '<li><label><input type="checkbox" />Confirm media IDs</label></li>',
            '<li><label><input type="checkbox" checked="" />Publish migrated list</label></li>',
            '</ul>',
        ]), $writer->write($document($tasks)));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [$text('Reviewer checklist before WordPress publish:')]),
            new AstNode('ordered_list', [
                'start' => 4,
                'style' => 'upper_alpha',
                'delimiter' => 'one_paren',
            ], [
                $item([$text('Verify block order')]),
                $item([
                    $text('Attach source notes'),
                    new AstNode('bullet_list', [], [
                        $item([$text('Keep imported anchor links')]),
                    ]),
                ]),
            ]),
        ]);
        $html = $writer->write($review);

        $t->contains('<p>Reviewer checklist before WordPress publish:</p>', $html);
        $t->contains('<ol start="4" type="A">', $html);
        $t->contains("<li>Attach source notes\n<ul>\n<li>Keep imported anchor links</li>\n</ul></li>", $html);
    },
    'maps upstream html writer nested links in link labels to spans' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $writer = new HtmlWriter();

        $nestedLink = new AstNode('link', [
            'id' => 'source-note',
            'classes' => ['legacy-ref'],
            'url' => 'https://example.test/source-note',
            'title' => 'Nested target title must be dropped',
            'attributes' => ['data-source' => 'batch-42'],
        ], [
            $text('source note'),
        ]);
        $outerLink = new AstNode('link', [
            'url' => 'https://example.test/review',
            'title' => 'Review packet',
        ], [
            $text('Read '),
            $nestedLink,
            $text(' now'),
        ]);
        $emphasizedNested = new AstNode('link', [
            'url' => 'https://example.test/review-emphasis',
        ], [
            $text('Open '),
            new AstNode('emph', [], [
                $text('the '),
                new AstNode('link', [
                    'classes' => ['admin-target'],
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [
                    $text('admin target'),
                ]),
            ]),
        ]);

        $t->same(
            '<a href="https://example.test/review" title="Review packet">Read <span id="source-note" class="legacy-ref" data-source="batch-42">source note</span> now</a>',
            $writer->write($plain($outerLink))
        );
        $t->same(
            '<a href="https://example.test/review-emphasis">Open <em>the <span class="admin-target">admin target</span></em></a>',
            $writer->write($plain($emphasizedNested))
        );

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer link packet: '),
                $outerLink,
                $text('.'),
            ]),
        ]);
        $preview = $writer->write($review);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
            new AstNode('raw_html', [
                'html' => '<section class="pandoc-link-label-review" data-pandoc-source="html-writer-remove-links">' . $preview . '</section>',
            ]),
        ]));

        $t->contains('<span id="source-note" class="legacy-ref" data-source="batch-42">source note</span>', $preview);
        $t->true(!str_contains($preview, 'source-note" title='), 'Nested link target metadata should not leak onto the span label');
        $t->true(!str_contains($preview, '<a href="https://example.test/source-note"'), 'HTML writer link labels should not contain nested anchors');
        $t->contains('data-pandoc-source="html-writer-remove-links"', $blocks);
        $t->contains('<span id="source-note" class="legacy-ref" data-source="batch-42">source note</span>', $blocks);
    },
    'maps upstream html writer quoted cite q-tag option' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (AstNode $inline): AstNode => new AstNode('document', [], [
            new AstNode('plain', [], [$inline]),
        ]);
        $quoted = new AstNode('quoted', ['kind' => 'double'], [
            new AstNode('span', [
                'attributes' => [
                    'cite' => 'http://example.org',
                ],
            ], [
                $text('examples'),
            ]),
        ]);

        $t->same("\u{201C}<span cite=\"http://example.org\">examples</span>\u{201D}", (new HtmlWriter())->write($plain($quoted)));
        $t->same('<q cite="http://example.org">examples</q>', (new HtmlWriter(['htmlQTags' => true]))->write($plain($quoted)));

        $review = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Source reviewer says '),
                new AstNode('quoted', ['kind' => 'double'], [
                    new AstNode('span', [
                        'attributes' => [
                            'cite' => 'https://example.test/import-log#note-42',
                        ],
                    ], [
                        $text('ready for block import'),
                    ]),
                ]),
                $text('.'),
            ]),
        ]);
        $html = (new HtmlWriter(['htmlQTags' => true]))->write($review);

        $t->contains('<p>Source reviewer says <q cite="https://example.test/import-log#note-42">ready for block import</q>.</p>', $html);
        $t->true(!str_contains($html, '<span cite='), 'HTML q-tag output should lift cite metadata onto q for the upstream quote case');
    },
    'maps upstream html writer footnote reference locations' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $heading = static fn (int $level, string $textValue): AstNode => new AstNode('heading', ['level' => $level], [
            $text($textValue),
        ]);
        $note = static fn (string $textValue): AstNode => new AstNode('note', [], [
            $paragraph([$text($textValue)]),
        ]);
        $noteTestDoc = new AstNode('document', [], [
            $heading(1, 'Page title'),
            $heading(2, 'First section'),
            $paragraph([
                $text('This is a footnote.'),
                $note('Down here.'),
                $text(' And this is a '),
                new AstNode('link', ['url' => 'https://www.google.com'], [$text('link')]),
                $text('.'),
            ]),
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('A note inside a block quote.'),
                    $note('The second note.'),
                ]),
                $paragraph([$text('A second paragraph.')]),
            ]),
            $heading(2, 'Second section'),
            $paragraph([$text('Some more text.')]),
        ]);
        $back = "\u{21A9}\u{FE0E}";

        $t->same(implode("\n", [
            '<h1>Page title</h1>',
            '<h2>First section</h2>',
            '<p>This is a footnote.<a href="#fn1" class="footnote-ref" id="fnref1" role="doc-noteref"><sup>1</sup></a> And this is a <a href="https://www.google.com">link</a>.</p>',
            '<blockquote>',
            '<p>A note inside a block quote.<a href="#fn2" class="footnote-ref" id="fnref2" role="doc-noteref"><sup>2</sup></a></p>',
            '<p>A second paragraph.</p>',
            '</blockquote>',
            '<h2>Second section</h2>',
            '<p>Some more text.</p>',
            '<div class="footnotes footnotes-end-of-document">',
            '<hr />',
            '<ol>',
            '<li id="fn1"><p>Down here.<a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '<li id="fn2"><p>The second note.<a href="#fnref2" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '</ol>',
            '</div>',
        ]), (new HtmlWriter(['referenceLocation' => 'end_of_document']))->write($noteTestDoc));

        $t->same(implode("\n", [
            '<h1>Page title</h1>',
            '<h2>First section</h2>',
            '<p>This is a footnote.<a href="#fn1" class="footnote-ref" id="fnref1" role="doc-noteref"><sup>1</sup></a> And this is a <a href="https://www.google.com">link</a>.</p>',
            '<div class="footnotes footnotes-end-of-block">',
            '<ol>',
            '<li id="fn1"><p>Down here.<a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '</ol>',
            '</div>',
            '<blockquote>',
            '<p>A note inside a block quote.<a href="#fn2" class="footnote-ref" id="fnref2" role="doc-noteref"><sup>2</sup></a></p>',
            '<p>A second paragraph.</p>',
            '</blockquote>',
            '<div class="footnotes footnotes-end-of-block">',
            '<ol start="2">',
            '<li id="fn2"><p>The second note.<a href="#fnref2" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '</ol>',
            '</div>',
            '<h2>Second section</h2>',
            '<p>Some more text.</p>',
        ]), (new HtmlWriter(['referenceLocation' => 'end_of_block']))->write($noteTestDoc));

        $t->same(implode("\n", [
            '<h1>Page title</h1>',
            '<h2>First section</h2>',
            '<p>This is a footnote.<a href="#fn1" class="footnote-ref" id="fnref1" role="doc-noteref"><sup>1</sup></a> And this is a <a href="https://www.google.com">link</a>.</p>',
            '<blockquote>',
            '<p>A note inside a block quote.<a href="#fn2" class="footnote-ref" id="fnref2" role="doc-noteref"><sup>2</sup></a></p>',
            '<p>A second paragraph.</p>',
            '</blockquote>',
            '<div class="footnotes footnotes-end-of-section">',
            '<hr />',
            '<ol>',
            '<li id="fn1"><p>Down here.<a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '<li id="fn2"><p>The second note.<a href="#fnref2" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '</ol>',
            '</div>',
            '<h2>Second section</h2>',
            '<p>Some more text.</p>',
        ]), (new HtmlWriter(['referenceLocation' => 'end_of_section']))->write($noteTestDoc));

        $review = new AstNode('document', [], [
            $heading(2, 'Reviewer HTML Packet'),
            $paragraph([
                $text('Import source trail'),
                new AstNode('note', [], [
                    $paragraph([
                        $text('Confirm the legacy post before publishing in WordPress.'),
                        new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('Edit imported post')]),
                    ]),
                ]),
                $text(' stays attached to the paragraph.'),
            ]),
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('Editorial caveat'),
                    new AstNode('note', [], [
                        $paragraph([$text('Keep this reviewer note scoped to the quote block.')]),
                    ]),
                    $text('.'),
                ]),
            ]),
        ]);
        $html = (new HtmlWriter(['referenceLocation' => 'end_of_block']))->write($review);

        $t->contains('<h2>Reviewer HTML Packet</h2>', $html);
        $t->contains('<p>Import source trail<a href="#fn1" class="footnote-ref" id="fnref1" role="doc-noteref"><sup>1</sup></a> stays attached to the paragraph.</p>', $html);
        $t->contains('<li id="fn1"><p>Confirm the legacy post before publishing in WordPress.<a href="/wp-admin/post.php?post=42&amp;action=edit">Edit imported post</a><a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>', $html);
        $t->contains('<blockquote>' . "\n" . '<p>Editorial caveat<a href="#fn2" class="footnote-ref" id="fnref2" role="doc-noteref"><sup>2</sup></a>.</p>' . "\n" . '</blockquote>', $html);
        $t->contains('<div class="footnotes footnotes-end-of-block">' . "\n" . '<ol start="2">', $html);
    },
    'maps upstream html writer end of section footnotes with section divs' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $heading = static fn (int $level, string $textValue): AstNode => new AstNode('heading', ['level' => $level], [
            $text($textValue),
        ]);
        $note = static fn (string $textValue): AstNode => new AstNode('note', [], [
            $paragraph([$text($textValue)]),
        ]);
        $noteTestDoc = new AstNode('document', [], [
            $heading(1, 'Page title'),
            $heading(2, 'First section'),
            $paragraph([
                $text('This is a footnote.'),
                $note('Down here.'),
                $text(' And this is a '),
                new AstNode('link', ['url' => 'https://www.google.com'], [$text('link')]),
                $text('.'),
            ]),
            new AstNode('blockquote', [], [
                $paragraph([
                    $text('A note inside a block quote.'),
                    $note('The second note.'),
                ]),
                $paragraph([$text('A second paragraph.')]),
            ]),
            $heading(2, 'Second section'),
            $paragraph([$text('Some more text.')]),
        ]);
        $back = "\u{21A9}\u{FE0E}";

        $t->same(implode("\n", [
            '<div class="section level1">',
            '<h1>Page title</h1>',
            '<div class="section level2">',
            '<h2>First section</h2>',
            '<p>This is a footnote.<a href="#fn1" class="footnote-ref" id="fnref1" role="doc-noteref"><sup>1</sup></a> And this is a <a href="https://www.google.com">link</a>.</p>',
            '<blockquote>',
            '<p>A note inside a block quote.<a href="#fn2" class="footnote-ref" id="fnref2" role="doc-noteref"><sup>2</sup></a></p>',
            '<p>A second paragraph.</p>',
            '</blockquote>',
            '<div class="footnotes footnotes-end-of-section">',
            '<hr />',
            '<ol>',
            '<li id="fn1"><p>Down here.<a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '<li id="fn2"><p>The second note.<a href="#fnref2" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>',
            '</ol>',
            '</div>',
            '</div>',
            '<div class="section level2">',
            '<h2>Second section</h2>',
            '<p>Some more text.</p>',
            '</div>',
            '</div>',
        ]), (new HtmlWriter([
            'referenceLocation' => 'end_of_section',
            'writerSectionDivs' => true,
        ]))->write($noteTestDoc));

        $review = new AstNode('document', [], [
            $heading(1, 'Imported Article Review'),
            $heading(2, 'Source Notes'),
            $paragraph([
                $text('Source URL needs verification'),
                new AstNode('note', [], [
                    $paragraph([
                        $text('Open the original import packet before publishing.'),
                        new AstNode('link', ['url' => '/wp-admin/post.php?post=77&action=edit'], [$text('Review source post')]),
                    ]),
                ]),
                $text('.'),
            ]),
            $heading(2, 'Publish Checklist'),
            $paragraph([$text('Confirm media ownership.')]),
        ]);
        $html = (new HtmlWriter([
            'referenceLocation' => 'end_of_section',
            'writerSectionDivs' => true,
        ]))->write($review);

        $t->contains('<div class="section level1">' . "\n" . '<h1>Imported Article Review</h1>', $html);
        $t->contains('<div class="section level2">' . "\n" . '<h2>Source Notes</h2>', $html);
        $t->contains('<li id="fn1"><p>Open the original import packet before publishing.<a href="/wp-admin/post.php?post=77&amp;action=edit">Review source post</a><a href="#fnref1" class="footnote-back" role="doc-backlink">' . $back . '</a></p></li>', $html);
        $t->true(strpos($html, '<div class="footnotes footnotes-end-of-section">') < strpos($html, '<h2>Publish Checklist</h2>'), 'Section footnotes should render before the next section div');
    },
    'maps upstream plain writer table cells and fallback captions' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $caption = [
            new AstNode('strong', [], [$text('Migration')]),
            $text(' '),
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
                'title' => 'Edit imported post',
            ], [$text('source edit')]),
            $text(' for '),
            new AstNode('code', ['text' => 'wp_posts']),
        ];
        $tableAttrs = [
            'captionInlines' => $caption,
            'classes' => ['wp-review'],
            'attributes' => ['source' => 'batch-42'],
            'alignments' => ['left', 'right'],
        ];
        $simple = new AstNode('table', $tableAttrs, [
            new AstNode('table_head', [], [
                $row([
                    $cell([$text('Field')], ['header' => true]),
                    $cell([$text('Review')], ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell([$text('Status')]),
                    $cell([
                        new AstNode('strong', [], [$text('Ready')]),
                        $text(' via '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('edit link')]),
                        $text(' and '),
                        new AstNode('code', ['text' => 'wp_update_post']),
                    ]),
                ]),
            ]),
        ]);
        $spanned = new AstNode('table', $tableAttrs, [
            new AstNode('table_head', [], [
                $row([
                    $cell([$text('Section')], ['header' => true]),
                    $cell([$text('Count')], ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell([$text('All imports')], ['colspan' => 2, 'align' => 'center']),
                ]),
                $row([
                    $cell([$text('Posts')]),
                    $cell([$text('42')]),
                ]),
            ]),
        ]);

        $plainSimple = (new MarkdownWriter(['variant' => 'plain', 'columns' => 96]))->write(new AstNode('document', [], [$simple]));
        $plainApproximate = (new MarkdownWriter([
            'variant' => 'plain',
            'gridTables' => false,
            'rawHtml' => false,
            'columns' => 96,
        ]))->write(new AstNode('document', [], [$spanned]));
        $plainPlaceholder = (new MarkdownWriter([
            'variant' => 'plain',
            'gridTables' => false,
            'rawHtml' => false,
            'pipeTables' => false,
            'columns' => 96,
        ]))->write(new AstNode('document', [], [$spanned]));

        $t->contains('Ready via edit link and wp_update_post', $plainSimple);
        $t->contains(': Migration source edit for wp_posts {.wp-review source="batch-42"}', $plainSimple);
        $t->true(!str_contains($plainSimple, '**Ready**'), 'PlainText table cells strip strong delimiters');
        $t->true(!str_contains($plainSimple, '[edit link]('), 'PlainText table cells strip Markdown link destinations');
        $t->true(!str_contains($plainSimple, '`wp_update_post`'), 'PlainText table cells strip code-span ticks');
        $t->contains('| All imports |       |', $plainApproximate);
        $t->contains(': Migration source edit for wp_posts {.wp-review source="batch-42"}', $plainApproximate);
        $t->same(implode("\n", [
            '[TABLE]',
            '',
            ': Migration source edit for wp_posts {.wp-review source="batch-42"}',
        ]), $plainPlaceholder);
    },
    'maps upstream plain writer template titleblock metadata' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '% Migration *Audit*',
            '% Data Liberation [Team](/team); WordPress *Review*',
            '% `May 23`, 2026',
            '',
            'Body [source](/wp-admin/post.php?post=42&action=edit) and `wp_update_post`.',
        ]));

        $bodyOnly = (new MarkdownWriter(['variant' => 'plain']))->write($document);
        $templated = (new MarkdownWriter(['variant' => 'plain', 'template' => true]))->write($document);
        $standalone = (new MarkdownWriter(['variant' => 'plain', 'standalone' => true]))->write($document);
        $titleblockOverride = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
            'variables' => [
                'titleblock' => 'Override *raw* [admin](/wp-admin/post.php?post=42&action=edit)',
            ],
        ]))->write($document);

        $t->same('Body source and wp_update_post.', $bodyOnly);
        $t->same(implode("\n", [
            'Migration Audit',
            'Data Liberation Team; WordPress Review',
            'May 23, 2026',
            '',
            'Body source and wp_update_post.',
        ]), $templated);
        $t->same($templated, $standalone);
        $t->same(implode("\n", [
            'Override *raw* [admin](/wp-admin/post.php?post=42&action=edit)',
            '',
            'Body source and wp_update_post.',
        ]), $titleblockOverride);
        $t->true(!str_contains($templated, '[Team]('), 'PlainText template titleblock strips author link destinations');
        $t->true(!str_contains($templated, '*Audit*'), 'PlainText template titleblock strips title emphasis delimiters');
        $t->true(!str_contains($templated, '`May 23`'), 'PlainText template titleblock strips date code ticks');
        $t->true(!str_contains($titleblockOverride, 'Migration Audit'), 'Writer titleblock variables take precedence over generated metadata titleblocks');
    },
    'maps upstream plain writer template include variables' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $document = new AstNode('document', [
            'meta' => [
                'include-before' => [
                    $paragraph([
                        $text('Meta before '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('source edit')]),
                    ]),
                ],
                'include-after' => [
                    $paragraph([
                        $text('Meta after '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('source edit')]),
                        $text(' and '),
                        new AstNode('code', ['text' => 'do_action']),
                        $text('.'),
                    ]),
                ],
            ],
        ], [
            $paragraph([
                $text('Body '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source')]),
                $text(' and note'),
                new AstNode('note', [], [
                    $paragraph([
                        $text('Notify '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('source edit')]),
                        $text(' via '),
                        new AstNode('code', ['text' => 'wp_update_post']),
                        $text('.'),
                    ]),
                ]),
                $text('.'),
            ]),
        ]);

        $bodyOnly = (new MarkdownWriter(['variant' => 'plain']))->write($document);
        $templated = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
            'variables' => [
                'header-includes' => 'Plain import header',
                'include-before' => [
                    'Raw preface [admin](/wp-admin/post.php?post=42&action=edit)',
                    'Second preface line',
                ],
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'Body source and note[1].',
            '',
            '[1] Notify source edit via wp_update_post.',
        ]), $bodyOnly);
        $t->same(implode("\n", [
            'Plain import header',
            '',
            'Raw preface [admin](/wp-admin/post.php?post=42&action=edit)',
            '',
            'Second preface line',
            '',
            'Body source and note[1].',
            '',
            '[1] Notify source edit via wp_update_post.',
            '',
            'Meta after source edit and do_action.',
        ]), $templated);
        $t->true(!str_contains($templated, 'Meta before'), 'Writer variables override same-named metadata in the template context');
        $t->contains('[admin](/wp-admin/post.php?post=42&action=edit)', $templated, 'Writer variables are emitted as raw template values');
        $t->true(!str_contains($templated, '[source edit]('), 'Metadata include-after blocks render through PlainText inline semantics');
        $t->true(!str_contains($templated, '`do_action`'), 'Metadata include-after blocks strip code ticks like writePlain body output');
    },
    'maps upstream plain writer template body context override' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $document = new AstNode('document', [
            'meta' => [
                'body' => [
                    $paragraph([
                        $text('Metadata body from '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('source edit')]),
                        $text(' and '),
                        new AstNode('code', ['text' => 'wp_update_post']),
                        $text('.'),
                    ]),
                    new AstNode('bullet_list', [], [
                        new AstNode('list_item', [], [
                            $text('Metadata checklist item'),
                        ]),
                    ]),
                ],
                'include-after' => [
                    $paragraph([
                        $text('Metadata footer '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('source edit')]),
                        $text('.'),
                    ]),
                ],
            ],
        ], [
            $paragraph([
                $text('Actual body should not appear with a body context override.'),
            ]),
        ]);

        $metadataBody = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
        ]))->write($document);
        $variableBody = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
            'variables' => [
                'body' => 'Variable body [admin](/wp-admin/post.php?post=42&action=edit)',
                'include-after' => 'Variable footer',
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'Metadata body from source edit and wp_update_post.',
            '',
            '- Metadata checklist item',
            '',
            'Metadata footer source edit.',
        ]), $metadataBody);
        $t->same(implode("\n", [
            'Variable body [admin](/wp-admin/post.php?post=42&action=edit)',
            '',
            'Variable footer',
        ]), $variableBody);
        $t->true(!str_contains($metadataBody, 'Actual body should not appear'), 'Metadata body overrides the automatically rendered template body');
        $t->true(!str_contains($metadataBody, '[source edit]('), 'Metadata body renders through PlainText link semantics');
        $t->true(!str_contains($metadataBody, '`wp_update_post`'), 'Metadata body renders through PlainText code semantics');
        $t->contains('[admin](/wp-admin/post.php?post=42&action=edit)', $variableBody, 'Writer body variables are emitted as raw template values');
        $t->true(!str_contains($variableBody, 'Metadata body'), 'Writer body variables take precedence over metadata body fields');
    },
    'maps upstream plain writer custom template meta json context' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'Migration Audit',
                'author' => ['Data Liberation Team'],
                'date' => '2026-05-23',
                'batch' => 'wp-42',
                'review' => [
                    'status' => 'ready',
                    'count' => 42,
                ],
                'include-before' => [
                    $paragraph([
                        $text('Meta before '),
                        new AstNode('link', [
                            'url' => '/wp-admin/post.php?post=42&action=edit',
                        ], [$text('source edit')]),
                        $text(' using '),
                        new AstNode('code', ['text' => 'wp_update_post']),
                        $text('.'),
                    ]),
                ],
            ],
        ], [
            $paragraph([
                $text('Converted body '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source edit')]),
                $text(' and '),
                new AstNode('code', ['text' => 'wp_update_post']),
                $text('.'),
            ]),
        ]);
        $template = <<<'TPL'
$if(titleblock)$
$titleblock$

$endif$
META: $meta-json$

$for(include-before)$
BEFORE: $include-before$

$endfor$
BODY:
$body$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
            'variables' => [
                'include-before' => [
                    'Raw variable [admin](/wp-admin/post.php?post=42&action=edit)',
                ],
            ],
        ]))->write($document);

        $t->contains("Migration Audit\nData Liberation Team\n2026-05-23", $custom);
        $t->contains('META: {"title":"Migration Audit"', $custom);
        $t->contains('"batch":"wp-42"', $custom);
        $t->contains('"review":{"status":"ready","count":42}', $custom);
        $t->contains('"include-before":"Meta before source edit using wp_update_post."', $custom);
        $t->contains('BEFORE: Raw variable [admin](/wp-admin/post.php?post=42&action=edit)', $custom);
        $t->contains("BODY:\nConverted body source edit and wp_update_post.", $custom);
        $t->same(1, substr_count($custom, 'Meta before source edit using wp_update_post.'), 'meta-json is metadata-only and does not let metadata override writer variables in the template body');
        $t->same(false, str_contains($custom, '[source edit]('));
    },
    'maps upstream plain writer custom template else sep and dotted variables' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $document = new AstNode('document', [
            'meta' => [
                'workflow' => [
                    'status' => 'ready',
                    'queue' => 'editorial',
                ],
                'recipients' => [
                    [
                        'name' => 'Editor',
                        'email' => 'editor@example.test',
                    ],
                    [
                        'name' => 'Publisher',
                        'email' => 'publisher@example.test',
                    ],
                ],
            ],
        ], [
            $paragraph([
                $text('Converted body '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source edit')]),
                $text('.'),
            ]),
        ]);
        $template = <<<'TPL'
Status: $if(workflow.status)$$workflow.status$$else$missing$endif$
Queue: ${ workflow.queue }
Fallback: ${ if(missing) }hidden${ else }WordPress review queue${ endif }
Map truth: $if(workflow)$yes$else$no$endif$
Workflow map: $workflow$
Notify: $for(recipients)$$it.name$ <$it.email$>$sep$, $endfor$
Body:
${body}
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'Status: ready',
            'Queue: editorial',
            'Fallback: WordPress review queue',
            'Map truth: yes',
            'Workflow map: true',
            'Notify: Editor <editor@example.test>, Publisher <publisher@example.test>',
            'Body:',
            'Converted body source edit.',
        ]), $custom);
        $t->contains('Status: ready', $custom, 'Dotted metadata values render in custom PlainText templates');
        $t->contains('Fallback: WordPress review queue', $custom, 'Else branches render when a template variable is absent');
        $t->contains('Workflow map: true', $custom, 'Map values render as true when directly interpolated');
        $t->same(1, substr_count($custom, ', '), 'Loop separators render only between values');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Body substitution still uses PlainText semantics inside custom templates');
    },
    'maps upstream doctemplates standalone else and elseif newline swallowing' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'foo' => 1,
                'bar' => null,
                'baz' => ['a', 'b'],
                'bim' => ['zub' => 'sim'],
                'sup' => [
                    ['biz' => 'qux'],
                    ['sax' => ''],
                ],
            ],
        ]);
        $conditionalsTemplate = <<<'TPL'
.
${if(sup.sax)}
XXX
${else}
YYY
${endif}
${if(bar)}
BAR
${endif}
${if(bar)}BAR${endif}
${if(foo)}
FOO
${endif}
${if(baz)}
BAZ
${endif}
${if(bim)}
BIM
${endif}
${if(sup)}
SUP
${endif}
.
TPL;
        $elseifTemplate = <<<'TPL'
$if(sup.sax)$
XXX
$elseif(baz)$
YYY
$else$
ZZZ
$endif$

$if(sup.sax)$
XXX
$elseif(baz.nonexist)$
YYY
$elseif(sup.sax)$
ZZZ
$else$
WWW
$endif$
TPL;

        $conditionals = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $conditionalsTemplate,
        ]))->write($document);
        $elseif = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $elseifTemplate,
        ]))->write($document);

        $t->same(implode("\n", [
            '.',
            'YYY',
            '',
            'FOO',
            'BAZ',
            'BIM',
            'SUP',
            '.',
        ]), $conditionals);
        $t->same(implode("\n", [
            'YYY',
            '',
            'WWW',
        ]), $elseif);
        $t->true(!str_starts_with($conditionals, ".\n\nYYY"), 'Standalone else does not leave a leading blank line before its selected branch');
        $t->true(!str_starts_with($elseif, "\nYYY"), 'Standalone elseif does not leave a leading blank line before its selected branch');
    },
    'maps upstream doctemplates final-newline scalar values' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'employee' => [
                    ['name' => "John\n"],
                    ['name' => "Sara\n\n"],
                    ['name' => 'Omar'],
                ],
            ],
        ]);
        $template = <<<'TPL'
$for(employee)$
$employee.name$
$ endfor $
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'John',
            'Sara',
            '',
            'Omar',
        ]), $custom);
        $t->contains("John\nSara", $custom, 'A single trailing scalar newline does not double the template line ending');
        $t->contains("Sara\n\nOmar", $custom, 'Two trailing scalar newlines preserve one intentional blank line');
        $t->true(!str_ends_with($custom, "\n"), 'The surrounding custom PlainText template still omits a final output newline');
    },
    'maps upstream doctemplates boolean scalar values' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'foo' => true,
                'bar' => false,
            ],
        ]);
        $template = <<<'TPL'
$foo$
$bar$
$if(foo)$XXX$else$YYY$endif$
$if(bar)$XXX$else$YYY$endif$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'true',
            'false',
            'XXX',
            'YYY',
        ]), $custom);
        $t->contains('false', $custom, 'Direct false variables render as text instead of disappearing');
        $t->true(!str_contains($custom, 'YYY' . "\n" . 'YYY'), 'Boolean truthiness still uses false for conditional branches');
    },
    'maps upstream doctemplates space-in-loop and direct list values' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'foo' => 1,
                'bar' => null,
                'baz' => ['a', 'b'],
                'bim' => ['zub' => 'sim'],
                'sup' => [
                    ['biz' => 'qux'],
                    ['sax' => 2],
                ],
                'employee' => [
                    ['name' => ['first' => 'John', 'last' => 'Doe']],
                    ['name' => ['first' => 'Omar', 'last' => 'Smith'], 'salary' => '30000'],
                    ['name' => ['first' => 'Sara', 'last' => 'Chen'], 'salary' => '60000'],
                ],
            ],
        ]);
        $spaceTemplate = <<<'TPL'
$for(employee)$
$employee.name.first$

$endfor$
---
$for(nonexistent)$

$endfor$
---
TPL;
        $valuesTemplate = <<<'TPL'
$foo$
$bar$
$baz$
$bim$
$sup$
TPL;

        $spacedLoop = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $spaceTemplate,
        ]))->write($document);
        $values = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $valuesTemplate,
        ]))->write($document);

        $t->same(implode("\n", [
            'John',
            '',
            'Omar',
            '',
            'Sara',
            '',
            '---',
            '---',
        ]), $spacedLoop);
        $t->same(implode("\n", [
            '1',
            '',
            'ab',
            'true',
            'truetrue',
        ]), $values);
        $t->contains("John\n\nOmar\n\nSara", $spacedLoop, 'Loop body blank lines are preserved between rendered list values');
        $t->true(!str_contains($spacedLoop, "\n---\n\n---"), 'Empty loops with a blank body do not emit whitespace');
        $t->contains("\nab\n", $values, 'Direct scalar lists concatenate without implicit paragraph separators');
        $t->contains("\ntruetrue", $values, 'Direct lists of maps concatenate each map as true');
    },
    'maps upstream plain writer custom template nested loops and elseif branches' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'phases' => [
                    [
                        'name' => 'intake',
                        'status' => 'ready',
                        'reviewers' => [
                            ['name' => 'Editor'],
                            ['name' => 'Publisher'],
                        ],
                    ],
                    [
                        'name' => 'publish',
                        'fallback_status' => 'queued',
                        'reviewers' => [],
                    ],
                ],
                'labels' => ['draft', 'public'],
            ],
        ]);
        $template = <<<'TPL'
Labels: $for(labels)$$it$$sep$ | $endfor$
$for(phases)$Phase $it.name$: $if(it.status)$$it.status$$elseif(it.fallback_status)$$it.fallback_status$$else$missing$endif$
Reviewers: $if(it.reviewers)$$for(it.reviewers)$$it.name$$sep$ / $endfor$$else$none$endif$$sep$
---
$endfor$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'Labels: draft | public',
            'Phase intake: ready',
            'Reviewers: Editor / Publisher',
            '---',
            'Phase publish: queued',
            'Reviewers: none',
        ]), $custom);
        $t->contains('Labels: draft | public', $custom, 'Anaphoric it renders scalar loop values');
        $t->same(1, substr_count($custom, '---'), 'Outer loop separator ignores nested reviewer loop separators');
        $t->same(1, substr_count($custom, ' / '), 'Nested loop separator renders only inside reviewer values');
        $t->contains('Phase publish: queued', $custom, 'Elseif branches render when the initial conditional is false');
        $t->true(!str_contains($custom, 'missing'), 'Elseif branch prevents fallback else text');
    },
    'maps upstream plain writer custom template comments and literal dollars' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'secondary_value' => 'fallback',
            ],
        ]);
        $template = <<<'TPL'
Cost: $$5
Visible before comment$-- hidden template comment $secondary_value$
Value: $if(primary)$primary$elseif(secondary_value)$$secondary_value$$else$none$endif$
Escaped: ${ secondary_value }
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'Cost: $5',
            'Visible before comment',
            'Value: fallback',
            'Escaped: fallback',
        ]), $custom);
        $t->same(1, substr_count($custom, '$'), 'Double-dollar delimiters render one literal dollar');
        $t->true(!str_contains($custom, 'hidden template comment'), 'Template comments are omitted through the end of the line');
        $t->contains('Value: fallback', $custom, 'Elseif can read underscore-bearing variable names');
        $t->true(!str_contains($custom, 'Value: none'), 'Elseif branch suppresses the final else branch');
    },
    'maps upstream plain writer custom template partials and applied partials' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'workflow' => [
                    'status' => 'ready',
                    'queue' => 'editorial',
                ],
                'reviewers' => [
                    [
                        'name' => 'Editor',
                        'email' => 'editor@example.test',
                        'role' => 'content',
                    ],
                    [
                        'name' => 'Publisher',
                        'email' => 'publisher@example.test',
                        'role' => 'final',
                    ],
                ],
                'labels' => ['draft', 'public'],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => ' and ']),
                new AstNode('code', ['text' => 'wp_update_post']),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
Packet
${ reviewer-list() }
Workflow: ${ workflow:workflow-line() }
Labels: ${ labels[, ] }
Body:
${body}
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
            'partials' => [
                'reviewer-list' => "Reviewers: \${ reviewers:reviewer()[; ] }\n\${ footer() }\n",
                'reviewer' => '$it.name$ <$it.email$> ($it.role$)' . "\n",
                'footer' => "Queue: \$workflow.queue$\n",
                'workflow-line' => '$it.status$ / $it.queue$',
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'Packet',
            'Reviewers: Editor <editor@example.test> (content); Publisher <publisher@example.test> (final)',
            'Queue: editorial',
            'Workflow: ready / editorial',
            'Labels: draft, public',
            'Body:',
            'Converted body source edit and wp_update_post.',
        ]), $custom);
        $t->same(1, substr_count($custom, '; '), 'Applied partial separators render only between reviewer values');
        $t->contains('Workflow: ready / editorial', $custom, 'Map values can be applied to a partial through the anaphoric it context');
        $t->contains('Labels: draft, public', $custom, 'Bracket separators apply to interpolated array variables');
        $t->true(!str_contains($custom, '${ footer() }'), 'Partials can include other partials');
        $t->true(!str_contains($custom, "\n\nWorkflow:"), 'Final newlines are omitted from included partials');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Body substitution still uses PlainText semantics after partial rendering');
    },
    'maps upstream doctemplates brace delimiters and literal dollar interpolation' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'employee' => [
                    [
                        'name' => [
                            'first' => 'John',
                            'last' => 'Doe',
                        ],
                    ],
                    [
                        'name' => [
                            'first' => 'Omar',
                            'last' => 'Smith',
                        ],
                        'salary' => '30000',
                    ],
                    [
                        'name' => [
                            'first' => 'Sara',
                            'last' => 'Chen',
                        ],
                        'salary' => '60000',
                    ],
                ],
            ],
        ]);
        $template = <<<'TPL'
${ for(employee) }
Hi, ${employee.name.first}. ${ if(employee.salary) }You make $$${ employee.salary }.${ else }No salary data.${ endif }
${ endfor }
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'Hi, John. No salary data.',
            'Hi, Omar. You make $30000.',
            'Hi, Sara. You make $60000.',
        ]), $custom);
        $t->same(3, substr_count($custom, 'Hi, '), 'Braced for-loop delimiters iterate all employees');
        $t->contains('Hi, Omar. You make $30000.', $custom, 'Braced variable and conditional delimiters render together with a literal dollar');
        $t->true(!str_contains($custom, '${'), 'Braced directives do not leak into rendered output');
    },
    'maps upstream doctemplates indented bare partial nesting' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'employee' => [
                    [
                        'name' => [
                            'first' => 'John',
                            'last' => 'Doe',
                        ],
                    ],
                    [
                        'name' => [
                            'first' => 'Omar',
                            'last' => 'Smith',
                        ],
                        'salary' => '30000',
                    ],
                    [
                        'name' => [
                            'first' => 'Sara',
                            'last' => 'Chen',
                        ],
                        'salary' => '60000',
                    ],
                ],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
$for(employee)$
$it:name()$
$endfor$

$employee:name()[, ]$

---
  $boilerplate()$
---
Body:
$body/chomp$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
            'partials' => [
                'name' => '($it.name.first$) $it.name.last$',
                'boilerplate' => "BOILERPLATE\nHERE\n",
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            '(John) Doe',
            '(Omar) Smith',
            '(Sara) Chen',
            '',
            '(John) Doe, (Omar) Smith, (Sara) Chen',
            '',
            '---',
            '  BOILERPLATE',
            '  HERE',
            '---',
            'Body:',
            'Converted body source edit.',
        ]), $custom);
        $t->contains('(John) Doe, (Omar) Smith, (Sara) Chen', $custom, 'Applied partial separators render between employee rows');
        $t->contains("---\n  BOILERPLATE\n  HERE\n---", $custom, 'Indented bare partials nest every rendered line under the source indentation');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Body substitution keeps PlainText link semantics after partial nesting');
    },
    'maps upstream doctemplates partial recursion loop guard' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);

        $upstreamFixture = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => '$loop1()$' . "\n",
            'partials' => [
                'loop1' => '$loop2()$',
                'loop2' => '$loop1()$',
            ],
        ]))->write(new AstNode('document'));
        $handoff = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => "Review packet\n\${ guard() }\nBody: \$body/chomp$",
            'partials' => [
                'guard' => 'Guard: ${ reviewer-loop() }' . "\n",
                'reviewer-loop' => '${ guard() }' . "\n",
            ],
        ]))->write($document);

        $t->same('(loop)', $upstreamFixture);
        $t->same(implode("\n", [
            'Review packet',
            'Guard: (loop)',
            'Body: Converted body source edit.',
        ]), $handoff);
        $t->contains('Guard: (loop)', $handoff, 'Recursive reviewer partials render Pandoc doctemplates loop sentinel instead of disappearing');
        $t->true(!str_contains($handoff, '/wp-admin/post.php'), 'Body substitution keeps PlainText link semantics after partial recursion detection');
    },
    'maps upstream doctemplates compile failure diagnostics' => static function (TestRunner $t): void {
        $compileError = static function (array $options): string {
            try {
                (new MarkdownWriter($options))->write(new AstNode('document'));
            } catch (InvalidArgumentException $exception) {
                return $exception->getMessage();
            }

            throw new RuntimeException('Expected invalid doctemplate syntax to fail compilation');
        };

        $t->same(implode("\n", [
            '(line 1, column 6):',
            'unexpected "$"',
            'expecting ".", "/" or ")"',
        ]), $compileError([
            'variant' => 'plain',
            'template' => '$if(x$and$endif$',
        ]));
        $t->same(implode("\n", [
            '"foobar.txt" (line 1, column 5):',
            'unexpected "$"',
            'expecting letter or digit or "()"',
        ]), $compileError([
            'variant' => 'plain',
            'templatePath' => 'foobar.txt',
            'template' => '$sep$',
        ]));
        $t->same(implode("\n", [
            '"foobar.txt" (line 1, column 10):',
            'unexpected "$"',
            'expecting letter, letter or digit or "()"',
            'Unknown pipe nope',
        ]), $compileError([
            'variant' => 'plain',
            'templatePath' => 'foobar.txt',
            'template' => '$foo/nope$',
        ]));
        $t->same(implode("\n", [
            '"foobar.txt" (line 1, column 10):',
            'unexpected "$"',
            'expecting letter, integer parameter for pipe, letter or digit or "()"',
        ]), $compileError([
            'variant' => 'plain',
            'templatePath' => 'foobar.txt',
            'template' => '$foo/left$',
        ]));
        $t->same(implode("\n", [
            '"foobar.txt" (line 1, column 11):',
            'unexpected "a"',
            'expecting integer parameter for pipe',
        ]), $compileError([
            'variant' => 'plain',
            'templatePath' => 'foobar.txt',
            'template' => '$foo/left a$',
        ]));
        $t->same(implode("\n", [
            '"test/bad.txt" (line 2, column 7):',
            'unexpected "s"',
            'expecting "$"',
        ]), $compileError([
            'variant' => 'plain',
            'templatePath' => 'test/foobar.txt',
            'template' => '$bad()$',
            'partials' => [
                'bad' => "partial\n" . '$with syntax error',
            ],
        ]));
    },
    'maps upstream doctemplates loop in object and partial nesting limit' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'worksite' => [
                    'name' => 'canyon',
                    'workers' => [
                        [
                            'name' => ['first' => 'John', 'last' => 'Doe'],
                        ],
                        [
                            'name' => ['first' => 'Omar', 'last' => 'Smith'],
                            'salary' => '30000',
                        ],
                        [
                            'name' => ['first' => 'Sara', 'last' => 'Chen'],
                            'salary' => '60000',
                        ],
                    ],
                ],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $objectLoop = <<<'TPL'
${ for(worksite.workers) }
${it.name.last}, ${it.name.first}
${ endfor }
Body: $body/chomp$
TPL;

        $safePartials = [];
        for ($index = 1; $index <= 51; $index++) {
            $safePartials['p' . $index] = $index === 51 ? 'leaf' : '$p' . ($index + 1) . '()$';
        }
        $loopPartials = $safePartials;
        $loopPartials['p51'] = '$p52()$';
        $loopPartials['p52'] = 'too deep';

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $objectLoop,
        ]))->write($document);
        $safeDepth = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => '$p1()$',
            'partials' => $safePartials,
        ]))->write(new AstNode('document'));
        $guardedDepth = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => '$p1()$',
            'partials' => $loopPartials,
        ]))->write(new AstNode('document'));

        $t->same(implode("\n", [
            'Doe, John',
            'Smith, Omar',
            'Chen, Sara',
            'Body: Converted body source edit.',
        ]), $custom);
        $t->same('leaf', $safeDepth, 'doctemplates permits partial nesting through level 50');
        $t->same('(loop)', $guardedDepth, 'doctemplates emits the loop sentinel only after the level 50 guard is exceeded');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Object-loop reviewer handoff keeps PlainText body link semantics');
    },
    'maps upstream plain writer custom template no-parameter pipes' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'workflow' => [
                    'status' => 'READY',
                    'queue' => 'Editorial',
                ],
                'labels' => ['draft', 'public', 'legal'],
                'reviewers' => [
                    [
                        'name' => 'Editor',
                        'role' => 'Content',
                    ],
                    [
                        'name' => 'Publisher',
                        'role' => 'Final',
                    ],
                ],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
Status: $workflow.status/lowercase$
Queue: $workflow.queue/uppercase$
Has labels: $if(labels/length)$yes$else$no$endif$
Labels: $labels/length$ total; first=$labels/first$; last=$labels/last$; middle=$for(labels/rest/allbutlast)$$it$$sep$, $endfor$; reversed=$labels/reverse[, ]$
Partial reviewers: ${ reviewers:reviewer()/uppercase[; ] }
$for(reviewers/pairs)$$it.key/alpha/uppercase$. $it.value.name/uppercase$ ($it.value.role/lowercase$)$sep$
$endfor$
Body:
$body/chomp$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
            'partials' => [
                'reviewer' => '$it.name$',
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'Status: ready',
            'Queue: EDITORIAL',
            'Has labels: yes',
            'Labels: 3 total; first=draft; last=legal; middle=public; reversed=legal, public, draft',
            'Partial reviewers: EDITOR; PUBLISHER',
            'A. EDITOR (content)',
            'B. PUBLISHER (final)',
            'Body:',
            'Converted body source edit.',
        ]), $custom);
        $t->contains('A. EDITOR (content)', $custom, 'pairs exposes one-based list keys for alpha/uppercase reviewer labels');
        $t->contains('middle=public', $custom, 'rest/allbutlast pipes can be chained before a loop');
        $t->contains('reversed=legal, public, draft', $custom, 'reverse keeps separator rendering on transformed arrays');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Piped body substitution keeps PlainText link semantics');
    },
    'maps upstream doctemplates pipes fixture list recursion and txt partials' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'items' => [
                    "one with\na line break",
                    'two',
                    "three with\na line break",
                ],
                'hasblanksmap' => [
                    'a' => "hello\n\n",
                    'b' => "there\n\n",
                ],
                'digits' => [1, 5, 20],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
$items/pairs/reverse:enum()$

$for(hasblanksmap/chomp/pairs/uppercase)$
$it.key$ ($it.value$)
$endfor$

$digits/roman[ ]$

---
  $boilerplate()$
---
$partial_foo()$
Body: $body/chomp$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
            'partials' => [
                'enum.txt' => '$it.key/alpha/uppercase$.  $^$$it.value$' . "\n\n",
                'boilerplate.txt' => "BOILERPLATE\nHERE\n\n",
                'partial_foo.txt' => 'Hello' . "\n",
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'C.  three with',
            '    a line break',
            'B.  two',
            'A.  one with',
            '    a line break',
            '',
            '',
            'A (HELLO)',
            'B (THERE)',
            '',
            'i v xx',
            '',
            '---',
            '  BOILERPLATE',
            '  HERE',
            '---',
            'Hello',
            'Body: Converted body source edit.',
        ]), $custom);
        $t->contains("C.  three with\n    a line break", $custom, 'Applied partials apply pairs/reverse to the source list before rendering');
        $t->contains("A (HELLO)\nB (THERE)", $custom, 'chomp recurses through maps before pairs/uppercase rendering');
        $t->contains("\ni v xx\n", $custom, 'roman maps over direct scalar lists before bracket separators');
        $t->contains("  BOILERPLATE\n  HERE\n---\nHello", $custom, 'Bare partial names resolve to upstream .txt partial files while preserving direct partial newline trimming');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Pipes fixture WordPress handoff keeps PlainText body link semantics');
    },
    'maps upstream plain writer custom template alignment pipes' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'workflow' => [
                    'status' => 'ready',
                    'queue' => 'WP',
                ],
                'employee' => [
                    [
                        'name' => [
                            'first' => 'Ada',
                        ],
                        'salary' => '42',
                    ],
                    [
                        'name' => [
                            'first' => 'Grace',
                        ],
                        'salary' => '3140',
                    ],
                ],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
Roster:
$for(employee)$$it.name.first/uppercase/left 8 "| "$$it.salary/right 6 " | " " |"$$sep$
$endfor$
Centered: $workflow.status/center 9 "[" "]"$
Overwide: $workflow.queue/right 1 "<" ">"$
Escaped: $workflow.status/left 6 "\\[" "\\]"$
Map unchanged: $workflow/left 8$
Body:
$body/left 30 "[[" "]]"$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            'Roster:',
            '| ADA      |     42 |',
            '| GRACE    |   3140 |',
            'Centered: [  ready  ]',
            'Overwide: <WP>',
            'Escaped: \[ready \]',
            'Map unchanged: true',
            'Body:',
            '[[Converted body source edit.   ]]',
        ]), $custom);
        $t->contains('| ADA      |     42 |', $custom, 'left and right pipes keep bordered fixed-width table cells');
        $t->contains('Centered: [  ready  ]', $custom, 'center pipe splits padding on both sides of textual values');
        $t->contains('Escaped: \[ready \]', $custom, 'quoted pipe borders unescape backslash escapes');
        $t->contains('Overwide: <WP>', $custom, 'alignment pipes do not truncate over-wide text');
        $t->contains('Map unchanged: true', $custom, 'alignment pipes have no effect on non-text map values');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Aligned body substitution keeps PlainText link semantics');
    },
    'maps upstream doctemplates pad multiline alignment fixture' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'sup' => "a multiline\nstring",
                'baz' => [
                    "a\nb",
                    "b\nc\nd",
                ],
                'employee' => [
                    [
                        'name' => ['first' => 'John', 'last' => 'Doe'],
                    ],
                    [
                        'name' => ['first' => 'Omar', 'last' => 'Smith'],
                        'salary' => '30000',
                    ],
                    [
                        'name' => ['first' => 'Sara', 'last' => 'Chen'],
                        'salary' => '60000',
                    ],
                ],
            ],
        ], []);
        $template = <<<'TPL'
$sup/right 15$$sup/center 15$$sup/left 15$

$for(baz/pairs)$
$it.key/alpha/right 4$. $^$$it.value$
$endfor$

+------+-----------+
$for(baz/pairs)$
$it.key/right 4 "| " " | "$$it.value/left 10 "" "|"$
+------+-----------+
$endfor$

|------------|------------|
$for(employee)$
$it.name.first/uppercase/left 10 "| "$$it.salary/right 10 " | " " |"$
$endfor$
|------------|------------|
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            '    a multiline  a multiline  a multiline',
            '         string    string     string',
            '',
            '   a. a',
            '      b',
            '   b. b',
            '      c',
            '      d',
            '',
            '+------+-----------+',
            '|    1 | a         |',
            '|      | b         |',
            '+------+-----------+',
            '|    2 | b         |',
            '|      | c         |',
            '|      | d         |',
            '+------+-----------+',
            '',
            '|------------|------------|',
            '| JOHN       |            |',
            '| OMAR       |      30000 |',
            '| SARA       |      60000 |',
            '|------------|------------|',
        ]), $custom);
        $t->contains('| JOHN       |            |', $custom, 'Missing aligned values render as padded empty cells like doctemplates Block nullToSimple');
        $t->contains("|    2 | b         |\n|      | c         |\n|      | d         |", $custom, 'Adjacent multiline alignment blocks render vertically filled borders');
    },
    'maps upstream plain writer template breakable spaces and nowrap pipe' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
$~$Legacy import reviewer packet needs exact editorial and media approval.$~$
Fixed: Legacy import reviewer packet needs exact editorial and media approval.
Nowrap: ${ lock()/nowrap }
Chomped: [${ trailing()/chomp }]
Body: $body/chomp$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'columns' => 28,
            'template' => $template,
            'partials' => [
                'lock' => '$~$Legal hold import references must stay on one reviewer line.$~$',
                'trailing' => "\$~\$ready \n\$~\$",
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'Legacy import reviewer',
            'packet needs exact editorial',
            'and media approval.',
            'Fixed: Legacy import reviewer packet needs exact editorial and media approval.',
            'Nowrap: Legal hold import references must stay on one reviewer line.',
            'Chomped: [ready]',
            'Body: Converted body source edit.',
        ]), $custom);
        $t->contains("Legacy import reviewer\npacket needs exact editorial\nand media approval.", $custom, 'Breakable template spaces wrap at writerColumns');
        $t->contains('Fixed: Legacy import reviewer packet needs exact editorial and media approval.', $custom, 'Ordinary template spaces stay nonbreakable');
        $t->contains('Nowrap: Legal hold import references must stay on one reviewer line.', $custom, 'nowrap disables breakable-space wrapping on partial output');
        $t->contains('Chomped: [ready]', $custom, 'chomp removes a trailing breakable space after newline normalization');
        $t->true(!str_contains($custom, '$~$'), 'Breakable-space template directives do not leak to output');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Body substitution still uses PlainText link semantics with breakable template rendering');
    },
    'maps upstream plain writer template nesting directive and automatic multiline variable nesting' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Converted body ']),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [new AstNode('text', ['text' => 'source edit'])]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $template = <<<'TPL'
Packet:
Item: $^$$description$ ($status$)
      Follow-up: $followup$
Auto:
  $auto$
Partial: $^$${ disclaimer() }
Body:
$body/chomp$
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
            'variables' => [
                'description' => "Media queue needs alt text\nand caption review",
                'status' => 'open',
                'followup' => 'legal hold',
                'auto' => "source reviewer line\nmedia reviewer line",
            ],
            'partials' => [
                'disclaimer' => "Review source archive\nbefore publish",
            ],
        ]))->write($document);

        $t->same(implode("\n", [
            'Packet:',
            'Item: Media queue needs alt text',
            '      and caption review (open)',
            '      Follow-up: legal hold',
            'Auto:',
            '  source reviewer line',
            '  media reviewer line',
            'Partial: Review source archive',
            '         before publish',
            'Body:',
            'Converted body source edit.',
        ]), $custom);
        $t->true(!str_contains($custom, '$^$'), 'Template nesting directives do not leak to output');
        $t->contains("Item: Media queue needs alt text\n      and caption review (open)", $custom, 'The explicit nesting directive indents multiline variable output to the current output column');
        $t->contains("Auto:\n  source reviewer line\n  media reviewer line", $custom, 'A multiline variable alone on an indented line nests automatically');
        $t->contains("Partial: Review source archive\n         before publish", $custom, 'The explicit nesting directive also nests multiline partial output');
        $t->true(!str_contains($custom, '/wp-admin/post.php'), 'Body substitution keeps PlainText link semantics with nested template output');
    },
    'maps upstream doctemplates nesting blank-line and nested directive fixture' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'foo' => 1,
                'baz' => ['a', 'b'],
                'bim' => ['zub' => 'sim'],
                'sup' => "a multiline\nstring",
            ],
        ]);
        $template = <<<'TPL'
    $sup$
   $sup$

   $^$$sup$

$bim.zub$ $^$$sup$

$bim.zub$ $^$$foo$
          bar $sup$

$for(baz)$
1. $^$Hello
   $if(it)$
     $it$
   $endif$
$endfor$

  $^$hey $sup$
  hey $sup$

  hey $sup$

  hey
  $if(foo)$

  $foo$
  $endif$
  hey
TPL;

        $custom = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => $template,
        ]))->write($document);

        $t->same(implode("\n", [
            '    a multiline',
            '    string',
            '   a multiline',
            '   string',
            '',
            '   a multiline',
            '   string',
            '',
            'sim a multiline',
            '    string',
            '',
            'sim 1',
            '    bar a multiline',
            '    string',
            '',
            '1. Hello',
            '     a',
            '1. Hello',
            '     b',
            '',
            '  hey a multiline',
            '  string',
            '  hey a multiline',
            '  string',
            '',
            '  hey a multiline',
            '  string',
            '',
            '  hey',
            '',
            '  1',
            '  hey',
        ]), $custom);
        $t->contains("1. Hello\n     a\n1. Hello\n     b", $custom, 'Nested conditionals inside a nested loop keep only the intended continuation indentation');
        $t->contains("  hey\n\n  1\n  hey", $custom, 'Nested control blocks keep intentional blank lines without ending the nesting level');
        $t->true(preg_match('/^ +$/m', $custom) !== 1, 'Nested blank lines should not contain indentation-only output lines');
        $t->true(!str_contains($custom, "\n    1\n"), 'Nested control output should not double-count source indentation');
    },
    'maps upstream plain writer template table of contents' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
        $document = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1, 'id' => 'import-review'], [
                $text('Import '),
                new AstNode('link', [
                    'id' => 'source-link',
                    'classes' => ['source'],
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                    'attributes' => ['data-source' => 'batch-42'],
                ], [$text('Review')]),
            ]),
            new AstNode('heading', ['level' => 2, 'id' => 'media-audit'], [
                $text('Media '),
                new AstNode('code', ['text' => 'audit']),
            ]),
            new AstNode('heading', ['level' => 3, 'id' => 'deep-detail'], [
                $text('Deep detail'),
            ]),
            new AstNode('heading', ['level' => 2, 'id' => 'private-heading', 'classes' => ['unlisted']], [
                $text('Private reviewer heading'),
            ]),
            $paragraph([
                $text('Body '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source edit')]),
                $text(' and '),
                new AstNode('code', ['text' => 'wp_update_post']),
                $text('.'),
            ]),
        ]);

        $bodyOnly = (new MarkdownWriter([
            'variant' => 'plain',
            'tableOfContents' => true,
        ]))->write($document);
        $templated = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
            'tableOfContents' => true,
            'tocDepth' => 2,
        ]))->write($document);

        $t->same(implode("\n", [
            'Import Review',
            '',
            'Media audit',
            '',
            'Deep detail',
            '',
            'Private reviewer heading',
            '',
            'Body source edit and wp_update_post.',
        ]), $bodyOnly);
        $t->same(implode("\n", [
            '- Import Review',
            '  - Media audit',
            '',
            'Import Review',
            '',
            'Media audit',
            '',
            'Deep detail',
            '',
            'Private reviewer heading',
            '',
            'Body source edit and wp_update_post.',
        ]), $templated);
        $t->true(!str_contains($templated, '/wp-admin/post.php'), 'PlainText template TOC strips heading link destinations');
        $t->true(!str_contains($templated, '#import-review'), 'PlainText template TOC strips generated TOC link anchors');
        $t->true(!str_contains($templated, 'source-link'), 'PlainText template TOC strips source link attributes');
        $t->true(!str_contains($templated, 'Deep detail' . "\n  -"), 'TOC depth prevents deeper headings from creating nested entries');
        $t->true(!str_starts_with($templated, "- Import Review\n  - Media audit\n  - Private"), 'Unlisted headings without section numbers stay out of the PlainText TOC');
    },
    'maps upstream plain writer numbered template table of contents' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $heading = static fn (
            int $level,
            string $id,
            string $label,
            array $classes = [],
            array $attributes = []
        ): AstNode => new AstNode(
            'heading',
            [
                'level' => $level,
                'id' => $id,
                'classes' => $classes,
                'attributes' => $attributes,
            ],
            [$text($label)]
        );
        $document = new AstNode('document', [], [
            $heading(1, 'import-review', 'Import Review'),
            $heading(2, 'source-audit-queue', 'Source Audit Queue', ['unlisted']),
            $heading(2, 'appendix', 'Appendix', ['unnumbered']),
            $heading(2, 'legacy-batch', 'Legacy Batch', ['unlisted'], ['number' => 'A']),
            $heading(2, 'publish-review', 'Publish Review'),
            $heading(3, 'deep-detail', 'Deep Detail'),
            new AstNode('paragraph', [], [$text('Body text.')]),
        ]);

        $numberedToc = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
            'tableOfContents' => true,
            'numberSections' => true,
            'tocDepth' => 3,
        ]))->write($document);
        $explicitOnlyToc = (new MarkdownWriter([
            'variant' => 'plain',
            'template' => true,
            'tableOfContents' => true,
            'tocDepth' => 3,
        ]))->write($document);

        $t->same(implode("\n", [
            '- 1 Import Review',
            '  - 1.1 Source Audit Queue',
            '  - Appendix',
            '  - A Legacy Batch',
            '  - 1.3 Publish Review',
            '    - 1.3.1 Deep Detail',
            '',
            'Import Review',
            '',
            'Source Audit Queue',
            '',
            'Appendix',
            '',
            'Legacy Batch',
            '',
            'Publish Review',
            '',
            'Deep Detail',
            '',
            'Body text.',
        ]), $numberedToc);
        $t->same(implode("\n", [
            '- Import Review',
            '  - Appendix',
            '  - Legacy Batch',
            '  - Publish Review',
            '    - Deep Detail',
            '',
            'Import Review',
            '',
            'Source Audit Queue',
            '',
            'Appendix',
            '',
            'Legacy Batch',
            '',
            'Publish Review',
            '',
            'Deep Detail',
            '',
            'Body text.',
        ]), $explicitOnlyToc);
        $t->contains('1.1 Source Audit Queue', $numberedToc, 'Generated section numbers keep unlisted headings visible in numbered PlainText TOCs');
        $t->contains('A Legacy Batch', $numberedToc, 'Explicit heading numbers are rendered in numbered PlainText TOCs');
        $t->true(str_starts_with($explicitOnlyToc, "- Import Review\n  - Appendix"), 'Unlisted headings without section numbers stay hidden when numbering is disabled');
        $t->true(!str_contains($explicitOnlyToc, '- A Legacy Batch'), 'Explicit numbers do not render unless numberSections is enabled');
    },
    'maps upstream markdown writer standalone table of contents command fixture' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $heading = static fn (int $level, string $label): AstNode => new AstNode('heading', [
            'level' => $level,
        ], [$text($label)]);
        $document = new AstNode('document', [], [
            $heading(1, 'A'),
            $heading(2, 'b'),
            $heading(1, 'B'),
            $heading(2, 'b'),
            new AstNode('div', ['classes' => ['interior']], [
                $heading(1, 'C'),
                $heading(2, 'cc'),
                $heading(1, 'D'),
            ]),
            new AstNode('div', ['classes' => ['blue']], [
                $heading(1, 'E'),
                $heading(2, 'e'),
            ]),
        ]);

        $markdown = (new MarkdownWriter([
            'standalone' => true,
            'tableOfContents' => true,
        ]))->write($document);
        $toc = explode("\n\n# A", $markdown, 2)[0];

        $t->same(implode("\n", [
            '- [A](#a){#toc-a}',
            '  - [b](#b){#toc-b}',
            '- [B](#b-1){#toc-b-1}',
            '  - [b](#b-2){#toc-b-2}',
            '- [E](#e){#toc-e}',
            '  - [e](#e-1){#toc-e-1}',
        ]), $toc);
        $t->true(!str_contains($toc, '#c'), 'Divs with multiple top-level headings stay out of the standalone Markdown TOC');
        $t->contains('::: {.interior}', $markdown);
        $t->contains('::: {.blue}', $markdown);
        $t->contains('# C', $markdown);
        $t->contains('# E', $markdown);
    },
    'maps upstream markdown writer underline and small caps fallback toggles' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Styles: '),
                new AstNode('underline', [], [$text('manual review')]),
                $text(' and '),
                new AstNode('small_caps', [], [
                    $text('source glossary '),
                    new AstNode('link', ['url' => '/source'], [$text('audit')]),
                    $text(' code '),
                    new AstNode('code', ['text' => 'wp_post']),
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Styles: <u>manual review</u> and <span class="smallcaps">source glossary [audit](/source) code `wp_post`</span>.',
            (new MarkdownWriter(['bracketedSpans' => false]))->write($document)
        );
        $t->same(
            'Styles: <span class="underline">manual review</span> and <span class="smallcaps">source glossary [audit](/source) code `wp_post`</span>.',
            (new MarkdownWriter(['bracketedSpans' => false, 'rawHtml' => false, 'nativeSpans' => true]))->write($document)
        );
        $t->same(
            'Styles: *manual review* and SOURCE GLOSSARY [AUDIT](/source) CODE `wp_post`.',
            (new MarkdownWriter(['bracketedSpans' => false, 'rawHtml' => false]))->write($document)
        );
        $t->same(
            'Styles: [manual review]{.underline} and SOURCE GLOSSARY [AUDIT](/source) CODE `wp_post`.',
            (new MarkdownWriter(['rawHtml' => false]))->write($document)
        );
    },
    'maps upstream markdown writer strikeout scripts math and raw inlines' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Cleanup '),
                new AstNode('strikeout', [], [
                    $text('legacy '),
                    new AstNode('emph', [], [$text('markup')]),
                ]),
                $text(' water H'),
                new AstNode('subscript', [], [$text('2')]),
                $text(' and status '),
                new AstNode('superscript', [], [
                    new AstNode('emph', [], [$text('draft')]),
                ]),
                $text(' plus inline math '),
                new AstNode('math', ['text' => 'x \in y', 'display' => false]),
                $text('2 follows raw '),
                new AstNode('raw_tex', ['tex' => '\cite[22-23]{smith.1899}']),
                $text(' and raw attr '),
                new AstNode('raw_inline', ['format' => 'opml', 'text' => '<outline/>']),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Script words '),
                new AstNode('subscript', [], [$text('review status')]),
                $text(' and '),
                new AstNode('superscript', [], [$text('post id')]),
                $text(' display '),
                new AstNode('math', ['text' => '\alpha + \omega', 'display' => true]),
                $text('.'),
            ]),
        ]);

        $t->same(implode("\n", [
            'Cleanup ~~legacy *markup*~~ water H~2~ and status ^*draft*^ plus inline math $x \in y$<!-- -->2 follows raw \cite[22-23]{smith.1899} and raw attr `<outline/>`{=opml}.',
            '',
            'Script words ~review\ status~ and ^post\ id^ display $$\alpha + \omega$$.',
        ]), (new MarkdownWriter())->write($document));
    },
    'maps upstream markdown writer raw inline extension fallbacks' => static function (TestRunner $t): void {
        $write = static function (AstNode $node, array $options = []): string {
            return (new MarkdownWriter($options))->write(new AstNode('document', [], [
                new AstNode('paragraph', [], [$node]),
            ]));
        };

        $rawHtml = new AstNode('raw_html_inline', ['html' => '<mark data-source="batch-42">review</mark>']);
        $rawTex = new AstNode('raw_tex', ['tex' => '\cite[22-23]{smith.1899}']);
        $rawOpml = new AstNode('raw_inline', ['format' => 'opml', 'text' => '<outline `tick`/>']);
        $rawMarkdown = new AstNode('raw_inline', ['format' => 'markdown_github', 'text' => '**trusted reviewer markdown**']);

        $t->same(
            '<mark data-source="batch-42">review</mark>',
            $write($rawHtml)
        );
        $t->same(
            '<mark data-source="batch-42">review</mark>',
            $write($rawHtml, ['rawAttribute' => false])
        );
        $t->same('', $write($rawHtml, [
            'rawAttribute' => false,
            'rawHtml' => false,
        ]));
        $t->same('\cite[22-23]{smith.1899}', $write($rawTex));
        $t->same('\cite[22-23]{smith.1899}', $write($rawTex, ['rawAttribute' => false]));
        $t->same('', $write($rawTex, [
            'rawAttribute' => false,
            'rawTex' => false,
        ]));
        $t->same('``<outline `tick`/>``{=opml}', $write($rawOpml));
        $t->same('', $write($rawOpml, ['rawAttribute' => false]));
        $t->same('**trusted reviewer markdown**', $write($rawMarkdown, [
            'rawAttribute' => false,
            'rawHtml' => false,
            'rawTex' => false,
        ]));
    },
    'maps upstream commonmark writer raw inline and linebreak variant branches' => static function (TestRunner $t): void {
        $writeInline = static function (AstNode $node, array $options = []): string {
            return (new MarkdownWriter(['variant' => 'commonmark'] + $options))->write(new AstNode('document', [], [
                new AstNode('paragraph', [], [$node]),
            ]));
        };
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $lineBreakDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('CommonMark break'),
                new AstNode('linebreak'),
                $text('review continuation.'),
            ]),
        ]);
        $rawCommonmark = new AstNode('raw_inline', ['format' => 'commonmark_x', 'text' => '<span data-review="ok">source</span>']);
        $rawGfm = new AstNode('raw_inline', ['format' => 'gfm', 'text' => '<ins>review</ins>']);
        $rawMarkdownGithub = new AstNode('raw_inline', ['format' => 'markdown_github', 'text' => '**github-only review**']);
        $rawHtml = new AstNode('raw_html_inline', ['html' => '<mark data-source="batch-42">review</mark>']);
        $rawTex = new AstNode('raw_tex', ['tex' => '\cite[22-23]{smith.1899}']);

        $t->same(
            "CommonMark break\\\nreview continuation.",
            (new MarkdownWriter(['variant' => 'commonmark', 'escapedLineBreaks' => false]))->write($lineBreakDocument)
        );
        $t->same(
            "CommonMark break\nreview continuation.",
            (new MarkdownWriter(['variant' => 'commonmark', 'escapedLineBreaks' => false, 'hardLineBreaks' => true]))->write($lineBreakDocument)
        );
        $t->same('<span data-review="ok">source</span>', $writeInline($rawCommonmark));
        $t->same('<ins>review</ins>', $writeInline($rawGfm));
        $t->same('', $writeInline($rawMarkdownGithub));
        $t->same('<mark data-source="batch-42">review</mark>', $writeInline($rawHtml));
        $t->same('', $writeInline($rawTex));
        $t->same('`\cite[22-23]{smith.1899}`{=tex}', $writeInline($rawTex, ['rawAttribute' => true]));
        $t->same('`**github-only review**`{=markdown_github}', (new MarkdownWriter(['variant' => 'commonmark_x']))->write(new AstNode('document', [], [
            new AstNode('paragraph', [], [$rawMarkdownGithub]),
        ])));
    },
    'maps upstream commonmark writer raw block variant branches' => static function (TestRunner $t): void {
        $writeBlock = static function (AstNode $node, array $options = []): string {
            return (new MarkdownWriter(array_replace(['variant' => 'commonmark'], $options)))->write(new AstNode('document', [], [$node]));
        };

        $rawCommonmark = new AstNode('raw_block', [
            'format' => 'commonmark_x',
            'text' => '<section data-source="batch-42">CommonMark source block</section>',
        ]);
        $rawCommonmarkCore = new AstNode('raw_block', [
            'format' => 'commonmark',
            'text' => '<aside>CommonMark core source block</aside>',
        ]);
        $rawGfm = new AstNode('raw_block', [
            'format' => 'gfm',
            'text' => '<ins>GFM reviewer insertion</ins>',
        ]);
        $rawMarkdown = new AstNode('raw_block', [
            'format' => 'markdown',
            'text' => 'Trusted **Markdown** source block',
        ]);
        $rawMarkdownGithub = new AstNode('raw_block', [
            'format' => 'markdown_github',
            'text' => '## GitHub-only reviewer markdown',
        ]);
        $rawHtml = new AstNode('raw_html', [
            'html' => "<div data-source=\"batch-42\">\n\n<p>Review block</p>\n  \n<p>Continuation</p>\n</div>",
        ]);
        $rawTex = new AstNode('raw_tex', [
            'tex' => "\\begin{review}\nsource\n\\end{review}",
        ]);

        $t->same('<section data-source="batch-42">CommonMark source block</section>', $writeBlock($rawCommonmark));
        $t->same('<aside>CommonMark core source block</aside>', $writeBlock($rawCommonmarkCore));
        $t->same('<ins>GFM reviewer insertion</ins>', $writeBlock($rawGfm));
        $t->same('Trusted **Markdown** source block', $writeBlock($rawMarkdown));
        $t->same('', $writeBlock($rawMarkdownGithub));
        $t->same(
            "```{=markdown_github}\n## GitHub-only reviewer markdown\n```",
            $writeBlock($rawMarkdownGithub, ['rawAttribute' => true])
        );
        $t->same(
            "```{=markdown_github}\n## GitHub-only reviewer markdown\n```",
            $writeBlock($rawMarkdownGithub, ['variant' => 'commonmark_x'])
        );
        $t->same(
            "<div data-source=\"batch-42\">\n&#10;<p>Review block</p>\n  &#10;<p>Continuation</p>\n</div>",
            $writeBlock($rawHtml, ['rawHtml' => false, 'rawAttribute' => false])
        );
        $t->same('', $writeBlock($rawTex));
        $t->same(
            "```{=tex}\n\\begin{review}\nsource\n\\end{review}\n```",
            $writeBlock($rawTex, ['variant' => 'commonmark_x'])
        );
    },
    'maps upstream markdown writer raw block extension fallbacks' => static function (TestRunner $t): void {
        $write = static function (AstNode $node, array $options = []): string {
            return (new MarkdownWriter($options))->write(new AstNode('document', [], [$node]));
        };

        $rawHtml = new AstNode('raw_html', ['html' => '<section data-source="batch-42">Review</section>']);
        $rawTex = new AstNode('raw_tex', ['tex' => "\\begin{review}\nsource\n\\end{review}"]);
        $rawOpml = new AstNode('raw_block', ['format' => 'opml', 'text' => '<outline text="Legacy"/>']);
        $rawMarkdown = new AstNode('raw_block', ['format' => 'markdown_phpextra', 'text' => '## Trusted reviewer markdown']);

        $t->same('<section data-source="batch-42">Review</section>', $write($rawHtml));
        $t->same(
            "```{=html}\n<section data-source=\"batch-42\">Review</section>\n```",
            $write($rawHtml, ['rawHtml' => false])
        );
        $t->same('', $write($rawHtml, [
            'rawHtml' => false,
            'rawAttribute' => false,
        ]));
        $t->same("\\begin{review}\nsource\n\\end{review}", $write($rawTex));
        $t->same(
            "```{=tex}\n\\begin{review}\nsource\n\\end{review}\n```",
            $write($rawTex, ['rawTex' => false])
        );
        $t->same('', $write($rawTex, [
            'rawTex' => false,
            'rawAttribute' => false,
        ]));
        $t->same("```{=opml}\n<outline text=\"Legacy\"/>\n```", $write($rawOpml));
        $t->same('', $write($rawOpml, ['rawAttribute' => false]));
        $t->same('## Trusted reviewer markdown', $write($rawMarkdown, [
            'rawAttribute' => false,
            'rawHtml' => false,
            'rawTex' => false,
        ]));
    },
    'maps upstream markdown writer div fenced native and disabled fallbacks' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('div', [
                'id' => 'review-scope',
                'classes' => ['source-div'],
                'attributes' => ['data-source' => 'batch-42'],
            ], [
                new AstNode('paragraph', [], [$text('Review block')]),
                new AstNode('div', ['classes' => ['nested']], [
                    new AstNode('paragraph', [], [$text('Nested note')]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            ':::: {#review-scope .source-div data-source="batch-42"}',
            'Review block',
            '',
            '::: {.nested}',
            'Nested note',
            ':::',
            '::::',
        ]), (new MarkdownWriter())->write($document));
        $t->same(implode("\n", [
            '<div id="review-scope" class="source-div" data-source="batch-42">',
            'Review block',
            '',
            '<div class="nested">',
            'Nested note',
            '</div>',
            '</div>',
        ]), (new MarkdownWriter(['fencedDivs' => false]))->write($document));
        $t->same(implode("\n", [
            'Review block',
            '',
            'Nested note',
        ]), (new MarkdownWriter([
            'fencedDivs' => false,
            'nativeDivs' => false,
            'rawHtml' => false,
        ]))->write($document));
    },
    'maps upstream markdown writer strikeout disabled extension fallback' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Cleanup '),
                new AstNode('strikeout', [], [
                    $text('legacy '),
                    new AstNode('emph', [], [$text('caption')]),
                ]),
                $text('.'),
            ]),
        ]);

        $t->same('Cleanup ~~legacy *caption*~~.', (new MarkdownWriter())->write($document));
        $t->same('Cleanup <s>legacy *caption*</s>.', (new MarkdownWriter(['strikeout' => false]))->write($document));
        $t->same('Cleanup legacy *caption*.', (new MarkdownWriter([
            'strikeout' => false,
            'rawHtml' => false,
        ]))->write($document));
    },
    'maps upstream markdown writer script disabled extension fallbacks' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Script fallback H'),
                new AstNode('subscript', [], [$text('2')]),
                $text(' and x'),
                new AstNode('superscript', [], [$text('2')]),
                $text(' plus words '),
                new AstNode('subscript', [], [$text('review status')]),
                $text(' and marked '),
                new AstNode('superscript', [], [
                    new AstNode('emph', [], [$text('draft')]),
                ]),
                $text('.'),
            ]),
        ]);

        $t->same(
            'Script fallback H<sub>2</sub> and x<sup>2</sup> plus words <sub>review\ status</sub> and marked <sup>*draft*</sup>.',
            (new MarkdownWriter([
                'subscript' => false,
                'superscript' => false,
            ]))->write($document)
        );
        $t->same(
            "Script fallback H\u{2082} and x\u{00B2} plus words _(review\\ status) and marked ^(*draft*).",
            (new MarkdownWriter([
                'subscript' => false,
                'superscript' => false,
                'rawHtml' => false,
            ]))->write($document)
        );
        $t->same(
            'Script fallback H² and x² plus words _(review\ status) and marked ^(*draft*).',
            (new MarkdownWriter([
                'subscript' => false,
                'superscript' => false,
                'rawHtml' => false,
                'preferAscii' => true,
            ]))->write($document)
        );
    },
    'maps upstream markdown writer citation rendering modes and affixes' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Citation review: '),
                new AstNode('citation', [
                    'citations' => [
                        [
                            'id' => 'doe',
                            'mode' => 'author_in_text',
                            'suffix' => [$text('p. 7')],
                        ],
                        [
                            'id' => 'roe',
                            'prefix' => [$text('see')],
                            'suffix' => [$text('ch. 2')],
                        ],
                    ],
                ]),
                $text(' and '),
                new AstNode('citation', [
                    'citations' => [
                        [
                            'id' => 'doe',
                            'prefix' => [$text('see')],
                            'suffix' => [$text('pp. 10-12')],
                        ],
                        [
                            'id' => 'roe',
                            'mode' => 'suppress_author',
                            'suffix' => [$text(', ch. 4')],
                        ],
                        [
                            'id' => '1657:huyghens',
                        ],
                        [
                            'id' => 'legacy key',
                            'suffix' => [$text('appendix')],
                        ],
                    ],
                ]),
                $text('.'),
            ]),
        ]);

        $readerCompatible = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('citation', [
                    'id' => 'cita',
                    'mode' => 'author_in_text',
                    'suffix' => 'review-only note',
                    'text' => '@cita [review-only note]',
                ]),
                $text(' and '),
                new AstNode('citation', [
                    'id' => 'mapreduce',
                    'mode' => 'normal',
                    'text' => '[@mapreduce]',
                ]),
            ]),
        ]);

        $t->same(
            'Citation review: @doe [p. 7; see @roe ch. 2] and [see @doe pp. 10-12; -@roe, ch. 4; @1657:huyghens; @{legacy key} appendix].',
            (new MarkdownWriter())->write($document)
        );
        $t->same(
            '@cita [review-only note] and [@mapreduce]',
            (new MarkdownWriter())->write($readerCompatible)
        );
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
    'maps upstream markdown writer figure implicit html div and content fallbacks' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plainImage = static fn (AstNode $image): AstNode => new AstNode('plain', [], [$image]);
        $write = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter($options))->write(new AstNode('document', [], [$node]));
        $implicitFigure = new AstNode('figure', [
            'id' => 'fig-review',
            'caption' => 'Reviewer caption',
        ], [
            $plainImage(new AstNode('image', [
                'url' => '/uploads/review.jpg',
                'title' => 'fig:Legacy title',
                'alt' => 'Reviewer alt',
            ], [$text('Reviewer alt')])),
        ]);
        $fallbackFigure = new AstNode('figure', [
            'id' => 'review-figure',
            'classes' => ['source-figure'],
            'attributes' => ['data-source' => 'batch-42'],
            'caption' => 'Reviewer frame',
        ], [
            $plainImage(new AstNode('image', [
                'url' => '/uploads/review-frame.jpg',
            ], [$text('Reviewer frame')])),
        ]);

        $t->same(
            '![Reviewer caption](/uploads/review.jpg "Legacy title"){#fig-review alt="Reviewer alt"}',
            $write($implicitFigure)
        );
        $t->same(
            '![Reviewer frame](/uploads/review-frame.jpg){#review-figure .source-figure data-source="batch-42"}',
            $write($fallbackFigure)
        );
        $t->same(
            '![Reviewer frame](/uploads/review-frame.jpg){#review-figure .source-figure data-source="batch-42"}',
            $write($fallbackFigure, ['rawHtml' => false])
        );
        $t->same(
            '![Reviewer frame](/uploads/review-frame.jpg){#review-figure .source-figure data-source="batch-42"}',
            $write($fallbackFigure, [
                'rawHtml' => false,
                'fencedDivs' => false,
                'nativeDivs' => false,
            ])
        );
        $t->same(implode("\n", [
            ':::: {.figure}',
            '![lalune](lalune.jpg)',
            '',
            '::: {.caption}',
            'lalune',
            ':::',
            '::::',
        ]), $write(new AstNode('figure', ['caption' => 'lalune'], [
            new AstNode('image', [
                'url' => 'lalune.jpg',
                'alt' => 'lalune',
            ], [$text('lalune')]),
        ]), [
            'implicitFigures' => false,
            'rawHtml' => false,
        ]));
    },
    'maps upstream markdown writer table pipe html and disabled fallbacks' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode(
                'table_cell',
                ['text' => $text] + $attrs,
                [$txt($text)]
            );
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $write = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter($options))->write(new AstNode('document', [], [$node]));
        $simple = (new MarkdownReader())->read(implode("\n", [
            '| Item | Count | Notes |',
            '| :--- | ----: | :---- |',
            '| Posts | 42 | **ready** |',
            '| Media | 7 | needs `alt` |',
            '',
            ': **Migration** [batch summary](/wp-admin/post.php?post=42&action=edit "Edit imported post") for `wp_posts`.',
        ]));
        $spanned = new AstNode('table', [
            'caption' => 'Review scope',
            'classes' => ['source-table'],
            'attributes' => ['source' => 'batch-42'],
            'alignments' => ['left', 'right'],
            'widths' => [0.4, 0.6],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('Section', ['header' => true]),
                    $cell('Count', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell('All imports', ['colspan' => 2, 'align' => 'center']),
                ]),
                $row([
                    $cell('Posts'),
                    $cell('42'),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '  Item      Count Notes',
            '  ------- ------- -------------',
            '  Posts        42 **ready**',
            '  Media         7 needs `alt`',
            '',
            '  : **Migration**',
            '  [batch summary](/wp-admin/post.php?post=42&action=edit "Edit imported post")',
            '  for `wp_posts`.',
        ]), (new MarkdownWriter())->write($simple));
        $t->same(implode("\n", [
            '| Item  | Count | Notes       |',
            '|:------|------:|:------------|',
            '| Posts |    42 | **ready**   |',
            '| Media |     7 | needs `alt` |',
            '',
            ': **Migration**',
            '[batch summary](/wp-admin/post.php?post=42&action=edit "Edit imported post")',
            'for `wp_posts`.',
        ]), (new MarkdownWriter(['simpleTables' => false]))->write($simple));
        $spannedGrid = $write($spanned);
        $t->true(str_starts_with($spannedGrid, '+---------------------------+'), 'Spanned tables use grid-table Markdown when grid_tables is enabled');
        $t->contains('| All imports', $spannedGrid);
        $t->contains(': Review scope {.source-table source="batch-42"}', $spannedGrid);
        $t->true(!str_contains($spannedGrid, '<table'), 'Grid-capable spanned Markdown should not use raw HTML by default');
        $t->contains('<table class="source-table" data-source="batch-42">', $write($spanned, ['gridTables' => false]));
        $t->contains('<td colspan="2" style="text-align:center">All imports</td>', $write($spanned, ['gridTables' => false]));
        $t->same(implode("\n", [
            '| Section     | Count |',
            '|:------------|------:|',
            '| All imports |       |',
            '| Posts       |    42 |',
            '',
            ': Review scope {.source-table source="batch-42"}',
        ]), $write($spanned, ['gridTables' => false, 'rawHtml' => false]));
        $t->same(implode("\n", [
            '[TABLE]',
            '',
            ': Review scope {.source-table source="batch-42"}',
        ]), $write($spanned, [
            'gridTables' => false,
            'rawHtml' => false,
            'pipeTables' => false,
        ]));
    },
    'maps upstream markdown writer simple table branch before pipe tables' => static function (TestRunner $t): void {
        $captioned = (new MarkdownReader())->read(implode("\n", [
            '    Right Left    Center  Default',
            '  ------- ------ -------- ---------',
            '       12 12        12    12',
            '      123 123      123    123',
            '        1 1         1     1',
            '',
            '  : Demonstration of simple table syntax.',
        ]));
        $headless = (new MarkdownReader())->read(implode("\n", [
            '  ----- ----- ----- -----',
            '     12 12     12      12',
            '    123 123    123    123',
            '      1 1       1       1',
            '  ----- ----- ----- -----',
        ]));

        $captionedMarkdown = (new MarkdownWriter())->write($captioned);
        $pipeFallback = (new MarkdownWriter(['simpleTables' => false]))->write($captioned);
        $multilineFallback = (new MarkdownWriter(['simpleTables' => false, 'pipeTables' => false]))->write($captioned);

        $t->same(implode("\n", [
            '    Right Left    Center  Default',
            '  ------- ------ -------- ---------',
            '       12 12        12    12',
            '      123 123      123    123',
            '        1 1         1     1',
            '',
            '  : Demonstration of simple table syntax.',
        ]), $captionedMarkdown);
        $t->true(!str_contains($captionedMarkdown, '|'), 'simple_tables output should be selected before pipe_tables');
        $t->same(implode("\n", [
            '  ----- ----- ----- -----',
            '     12 12     12      12',
            '    123 123    123    123',
            '      1 1       1       1',
            '  ----- ----- ----- -----',
        ]), (new MarkdownWriter())->write($headless));
        $t->true(str_starts_with($pipeFallback, '| Right | Left | Center | Default |'), 'Disabling simple_tables falls back to pipe table syntax when pipe_tables is enabled');
        $t->contains(': Demonstration of simple table syntax.', $pipeFallback);
        $t->true(str_starts_with($multilineFallback, '  -------------------------'), 'Disabling simple_tables and pipe_tables falls through to the multiline pandocTable branch');
        $t->contains('  : Demonstration of simple table syntax.', $multilineFallback);
        $t->true(!str_contains($multilineFallback, '<table'), 'Widthless simple table fallbacks should remain Markdown-native before raw HTML');
    },
    'maps upstream markdown writer pandocTable display-width numChars branch' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode('table_cell', ['text' => $text] + $attrs, [$txt($text)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'Unicode width reviewer handoff',
                'classes' => ['wp-review-width'],
                'attributes' => ['source' => 'batch-42'],
                'alignments' => ['left', 'left', 'left'],
            ], [
                new AstNode('table_head', [], [
                    $row([
                        $cell('項目詳細', ['header' => true]),
                        $cell('German', ['header' => true]),
                        $cell('Note', ['header' => true]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    $row([
                        $cell('画像'),
                        $cell("Auf\u{200C}lage"),
                        $cell('ready'),
                    ]),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document);
        $lines = explode("\n", $markdown);

        $t->same('  項目詳細   German    Note', $lines[0]);
        $t->same('  ---------- --------- -------', $lines[1], 'Pandoc Table.hs numChars uses display columns plus two alignment chars');
        $t->same("  画像       Auf\u{200C}lage   ready", $lines[2]);
        $t->contains(': Unicode width reviewer handoff {.wp-review-width source="batch-42"}', $markdown);
        $t->true(!str_contains($markdown, '<table'), 'Display-width simple table output stays native Markdown for WordPress handoff');
    },
    'maps upstream markdown writer grid table branch for row and column spans' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode(
                'table_cell',
                ['text' => $text] + $attrs,
                [$txt($text)]
            );
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'Rowspan audit',
                'alignments' => ['default', 'default', 'default'],
            ], [
                new AstNode('table_head', [], [
                    $row([
                        $cell('1', ['header' => true]),
                        $cell('2', ['header' => true, 'rowspan' => 2]),
                        $cell('3', ['header' => true]),
                    ]),
                    $row([
                        $cell('1', ['header' => true]),
                        $cell('3', ['header' => true]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    $row([
                        $cell('Scope', ['colspan' => 3, 'align' => 'center']),
                    ]),
                    $row([
                        $cell('1'),
                        $cell('2', ['rowspan' => 2]),
                        $cell('3'),
                    ]),
                    $row([
                        $cell('1'),
                        $cell('3'),
                    ]),
                ]),
                new AstNode('table_foot', [], [
                    $row([
                        $cell('1'),
                        $cell('2', ['rowspan' => 2]),
                        $cell('3'),
                    ]),
                    $row([
                        $cell('1'),
                        $cell('3'),
                    ]),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document);

        $t->contains("+---+   +---+\n| 1 |   | 3 |", $markdown, 'Rowspan continuation cells suppress the internal horizontal rule');
        $t->contains("| Scope     |\n+---+---+---+", $markdown, 'Column-spanned cells omit interior vertical separators and rejoin the following border');
        $t->contains("+===+===+===+\n| 1 | 2 | 3 |", $markdown, 'Table head and foot boundaries use Pandoc double grid borders');
        $t->contains(': Rowspan audit', $markdown);
        $t->true(!str_contains($markdown, '<table'), 'Spanned grid-table output should stay native Markdown');
    },
    'maps upstream markdown writer grid table branch for block cells and footers' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
        $textCell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode(
                'table_cell',
                ['text' => $text] + $attrs,
                [$txt($text)]
            );
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'Grid reviewer table',
                'classes' => ['grid-review'],
                'attributes' => ['source' => 'batch-42'],
                'alignments' => ['left', 'right'],
                'widths' => [0.3, 0.7],
            ], [
                new AstNode('table_head', [], [
                    $row([
                        $textCell('Section', ['header' => true]),
                        $textCell('Notes', ['header' => true]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    $row([
                        $cell([
                            new AstNode('heading', ['level' => 3], [$txt('Imports')]),
                            new AstNode('paragraph', [], [$txt('Ready for review')]),
                            new AstNode('bullet_list', [], [
                                new AstNode('list_item', [], [$txt('posts')]),
                                new AstNode('list_item', [], [$txt('media')]),
                            ]),
                        ]),
                        $cell([
                            $txt('Batch 42'),
                            new AstNode('linebreak'),
                            $txt('needs source scan'),
                        ]),
                    ]),
                ]),
                new AstNode('table_foot', [], [
                    $row([
                        $textCell('Total'),
                        $textCell('49'),
                    ]),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter(['columns' => 60]))->write($document);

        $t->true(str_starts_with($markdown, '+'), 'Grid table starts with a grid border');
        $t->contains('+:', $markdown, 'Grid header separator carries left alignment marker');
        $t->contains(':+', $markdown, 'Grid header separator carries right alignment marker');
        $t->contains('| ### Imports', $markdown);
        $t->contains('| Ready for review', $markdown);
        $t->contains('| - posts', $markdown);
        $t->contains('| Batch 42\\', $markdown);
        $t->contains('| Total', $markdown);
        $t->contains(': Grid reviewer table {.grid-review source="batch-42"}', $markdown);
        $t->true(!str_contains($markdown, '<table'), 'Grid-capable Markdown should not use the raw HTML table fallback');
    },
    'maps upstream markdown writer multiline table branch for width-bearing simple cells' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $textCell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode(
                'table_cell',
                ['text' => $text] + $attrs,
                [$txt($text)]
            );
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $table = new AstNode('table', [
            'caption' => "Reviewer multiline table\nSource widths preserved",
            'classes' => ['multiline-review'],
            'attributes' => ['source' => 'batch-42'],
            'alignments' => ['center', 'left', 'right', 'left'],
            'widths' => [0.15, 0.1375, 0.1625, 0.35],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $textCell("Centered\nHeader", ['header' => true]),
                    $textCell("Left\nAligned", ['header' => true]),
                    $textCell("Right\nAligned", ['header' => true]),
                    $textCell('Default aligned', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $textCell('First'),
                    $textCell('row'),
                    $textCell('12.0'),
                    $textCell("Example of a row that spans\nmultiple lines."),
                ]),
                $row([
                    $textCell('Second'),
                    $textCell('row'),
                    $textCell('5.0'),
                    $textCell("Another reviewer note\nwith a blank line\nbetween rows."),
                ]),
            ]),
        ]);
        $headless = new AstNode('table', [
            'alignments' => ['center', 'left', 'right', 'default'],
            'widths' => [0.15, 0.1375, 0.1625, 0.35],
        ], [
            new AstNode('table_head'),
            new AstNode('table_body', [], [
                $row([
                    $textCell('First'),
                    $textCell('row'),
                    $textCell('12.0'),
                    $textCell("Headerless reviewer note\nkeeps wrapped lines."),
                ]),
            ]),
        ]);
        $write = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter(['columns' => 80] + $options))->write(new AstNode('document', [], [$node]));
        $markdown = $write($table);
        $normalized = preg_replace('/[ \t]+\n/', "\n", $markdown) ?? $markdown;
        $headlessMarkdown = preg_replace('/[ \t]+\n/', "\n", $write($headless)) ?? $write($headless);
        $pipeFallback = $write($table, ['multilineTables' => false]);

        $t->true(str_starts_with($markdown, '  ---------------------------------------------------------------'), 'Headed multiline table starts with the full Pandoc table border');
        $t->contains('   Centered   Left              Right Default aligned', $normalized);
        $t->contains('    Header    Aligned         Aligned', $normalized);
        $t->contains('  ----------- ---------- ------------ ---------------------------', $normalized);
        $t->contains("Example of a row that spans\n                                      multiple lines.", $normalized);
        $t->contains("Another reviewer note\n                                      with a blank line\n                                      between rows.", $normalized);
        $t->contains("  : Reviewer multiline table\n  Source widths preserved {.multiline-review source=\"batch-42\"}", $normalized);
        $t->true(!str_contains($markdown, '<table'), 'Width-bearing simple tables should not use raw HTML when multiline tables are available');
        $t->true(!str_contains($markdown, '|'), 'Width-bearing simple tables should use Pandoc multiline syntax before pipe syntax');
        $t->true(str_starts_with($headlessMarkdown, '  ----------- ---------- ------------ ---------------------------'), 'Headless multiline tables use per-column rules instead of the full top border');
        $t->contains("Headerless reviewer note\n                                      keeps wrapped lines.", $headlessMarkdown);
        $t->true(str_starts_with($pipeFallback, '|'), 'Disabling multiline tables falls back to pipe-table Markdown for simple cells');
        $t->contains('Centered<br />Header', $pipeFallback);
    },
    'maps upstream markdown writer multiline table wrap auto minimum word widths' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode('table_cell', ['text' => $text] + $attrs, [$txt($text)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $longSourceToken = 'wp_post_meta_supercalifragilisticexpialidocious_key';
        $table = new AstNode('table', [
            'caption' => 'WrapAuto reviewer handoff',
            'classes' => ['wp-review-wrap'],
            'attributes' => ['source' => 'batch-42'],
            'alignments' => ['left', 'left'],
            'widths' => [0.1, 0.25],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('Source token', ['header' => true]),
                    $cell('Reviewer note', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell($longSourceToken),
                    $cell('Needs editorial review before import'),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter(['columns' => 36]))->write(new AstNode('document', [], [$table]));
        $lines = explode("\n", $markdown);
        $separator = trim($lines[3]);
        [$sourceRule, $noteRule] = explode(' ', $separator);

        $t->same(53, strlen($sourceRule), 'WrapAuto minOffset branch expands the relative source-token column instead of breaking a long word');
        $t->same(11, strlen($noteRule), 'The note column keeps the narrow relative width and wraps at word boundaries');
        $t->contains($longSourceToken, $markdown);
        $t->true(!str_contains($markdown, "supercalifragilistic\n"), 'Long unbreakable reviewer/source token is not split across lines');
        $t->contains("Reviewer   \n                                                        note", $markdown);
        $t->contains("Needs      \n                                                        editorial", $markdown);
        $t->contains("review     \n                                                        before     \n                                                        import", $markdown);
        $t->contains(': WrapAuto reviewer handoff {.wp-review-wrap source="batch-42"}', $markdown);
        $t->true(!str_contains($markdown, '<table'), 'Width-constrained multiline writer output stays native Markdown');
    },
    'maps upstream markdown writer multiline table wrap none full line widths' => static function (TestRunner $t): void {
        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode('table_cell', ['text' => $text] + $attrs, [$txt($text)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $longSourceToken = 'wp_post_meta_supercalifragilisticexpialidocious_key';
        $reviewNote = 'Needs editorial review before import';
        $table = new AstNode('table', [
            'caption' => 'WrapNone reviewer handoff',
            'classes' => ['wp-review-nowrap'],
            'attributes' => ['source' => 'batch-42'],
            'alignments' => ['left', 'left'],
            'widths' => [0.1, 0.25],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('Source token', ['header' => true]),
                    $cell('Reviewer note', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell($longSourceToken),
                    $cell($reviewNote),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter(['columns' => 36, 'wrap' => 'none']))->write(new AstNode('document', [], [$table]));
        $hardLineBreakMarkdown = (new MarkdownWriter(['columns' => 36, 'hardLineBreaks' => true]))->write(new AstNode('document', [], [$table]));
        $lines = explode("\n", $markdown);
        $separator = trim($lines[2]);
        [$sourceRule, $noteRule] = explode(' ', $separator);

        $t->same(strlen($longSourceToken) + 2, strlen($sourceRule), 'WrapNone uses the full source-token line width plus Pandoc alignment padding');
        $t->same(strlen($reviewNote) + 2, strlen($noteRule), 'WrapNone uses the full reviewer-note line width instead of minOffset word widths');
        $t->contains($longSourceToken . '   ' . $reviewNote, $markdown);
        $t->true(!str_contains($markdown, "Needs      \n"), 'WrapNone does not wrap the reviewer note at word boundaries');
        $t->true(!str_contains($markdown, "review     \n"), 'WrapNone keeps the entire reviewer note on one table row');
        $t->contains(': WrapNone reviewer handoff {.wp-review-nowrap source="batch-42"}', $markdown);
        $t->same($markdown, $hardLineBreakMarkdown, 'Pandoc writeMarkdown forces WrapNone when hard line breaks are enabled');
        $t->true(!str_contains($markdown, '<table'), 'No-wrap multiline writer output stays native Markdown');
    },
    'maps upstream markdown writer pipe table relative widths and over-column padding' => static function (TestRunner $t): void {
        $relativeWidths = (new MarkdownReader())->read(implode("\n", [
            'Long pipe table with relative widths:',
            '',
            '| Default1 | Default2 | Default3 |',
            ' |---------|----------|---------------------------------------|',
            '|123|this is a table cell|and this is a really long table cell that will probably need wrapping|',
            '|123|123|123|',
        ]))->children[1];
        $relativeMarkdown = (new MarkdownWriter([
            'columns' => 40,
            'multilineTables' => false,
        ]))->write(new AstNode('document', [], [$relativeWidths]));

        $txt = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $text, array $attrs = []) use ($txt): AstNode {
            return new AstNode('table_cell', ['text' => $text] + $attrs, [$txt($text)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $overColumn = new AstNode('table', [
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('Long note', ['header' => true]),
                    $cell('Count', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell('this cell is far too wide'),
                    $cell('42'),
                ]),
            ]),
        ]);
        $overColumnMarkdown = (new MarkdownWriter([
            'columns' => 20,
            'simpleTables' => false,
            'multilineTables' => false,
        ]))->write(new AstNode('document', [], [$overColumn]));

        $t->same(implode("\n", [
            '| Default1 | Default2 | Default3 |',
            '|-------|--------|--------------------------|',
            '| 123 | this is a table cell | and this is a really long table cell that will probably need wrapping |',
            '| 123 | 123 | 123 |',
        ]), $relativeMarkdown);
        $t->same([9 / 58, 10 / 58, 39 / 58], $relativeWidths->attr('widths'));
        $t->same(26, strlen('--------------------------'), 'relative third-column pipe delimiter uses writerColumns-scaled width');
        $t->same(implode("\n", [
            '| Long note | Count |',
            '|:---|---:|',
            '| this cell is far too wide | 42 |',
        ]), $overColumnMarkdown);
    },
    'maps upstream markdown writer pipe table positional default widths' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $value, array $attrs = []) use ($text): AstNode {
            return new AstNode('table_cell', ['text' => $value] + $attrs, [$text($value)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $table = new AstNode('table', [
            'alignments' => ['default', 'right', 'left'],
            'widths' => [0.0, 0.25, 0.75],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('項目詳細', ['header' => true]),
                    $cell('Items', ['header' => true]),
                    $cell('Reviewer note', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell('画像'),
                    $cell('42'),
                    $cell('Long source notes intentionally exceed the narrow review column.'),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter([
            'columns' => 40,
            'multilineTables' => false,
        ]))->write(new AstNode('document', [], [$table]));
        $lines = explode("\n", $markdown);

        $t->same(implode("\n", [
            '| 項目詳細 | Items | Reviewer note |',
            '|--|----------:|:' . str_repeat('-', 28) . '|',
            '| 画像 | 42 | Long source notes intentionally exceed the narrow review column. |',
        ]), $markdown);
        $t->same('--', explode('|', $lines[1])[1], 'The default first column keeps a zero-width delimiter slot');
        $t->same('----------:', explode('|', $lines[1])[2], 'The second column keeps its own 25 percent width hint');
        $t->same(':' . str_repeat('-', 28), explode('|', $lines[1])[3], 'The third column keeps its own 75 percent width hint');
        $t->true(!str_starts_with($lines[1], '|-----------|'), 'Default-width columns are not shifted onto the first relative delimiter');
    },
    'maps upstream markdown writer pipe table caption wrap auto branch' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $value, array $attrs = []) use ($text): AstNode {
            return new AstNode('table_cell', ['text' => $value] + $attrs, [$text($value)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $table = new AstNode('table', [
            'caption' => 'Migration reviewer captions wrap before publishing WordPress imports',
            'alignments' => ['left', 'default'],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('Source', ['header' => true]),
                    $cell('Review note', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell('Posts'),
                    $cell('Confirm captions.'),
                ]),
            ]),
        ]);

        $write = static fn (array $options = []): string => (new MarkdownWriter($options + [
            'columns' => 36,
            'simpleTables' => false,
            'multilineTables' => false,
        ]))->write(new AstNode('document', [], [$table]));
        $auto = $write();
        $wrapNone = $write(['wrap' => 'none']);
        $hardLineBreaks = $write(['hardLineBreaks' => true]);
        $captionLines = array_slice(explode("\n", $auto), -2);

        $t->same(': Migration reviewer captions wrap', $captionLines[0]);
        $t->same('before publishing WordPress imports', $captionLines[1]);
        $t->true(strlen($captionLines[0]) <= 36, 'Auto-wrapped first caption line respects writerColumns');
        $t->true(strlen($captionLines[1]) <= 36, 'Auto-wrapped continuation caption line respects writerColumns');
        $t->contains(': Migration reviewer captions wrap before publishing WordPress imports', $wrapNone);
        $t->same($wrapNone, $hardLineBreaks, 'Pandoc writeMarkdown disables wrapping when hard line breaks force WrapNone');
    },
    'maps upstream markdown writer disabled table caption marker branch' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static function (string $value, array $attrs = []) use ($text): AstNode {
            return new AstNode('table_cell', ['text' => $value] + $attrs, [$text($value)]);
        };
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $table = new AstNode('table', [
            'caption' => 'CommonMark reviewer captions stay visible',
            'classes' => ['wp-review-commonmark'],
            'attributes' => ['source' => 'batch-42'],
            'alignments' => ['left', 'default'],
        ], [
            new AstNode('table_head', [], [
                $row([
                    $cell('Source', ['header' => true]),
                    $cell('Review note', ['header' => true]),
                ]),
            ]),
            new AstNode('table_body', [], [
                $row([
                    $cell('Posts'),
                    $cell('Confirm no-colon caption handoff.'),
                ]),
            ]),
        ]);

        $document = new AstNode('document', [], [$table]);
        $simpleDisabled = (new MarkdownWriter(['tableCaptions' => false]))->write($document);
        $pipeDisabled = (new MarkdownWriter([
            'tableCaptions' => false,
            'simpleTables' => false,
            'multilineTables' => false,
        ]))->write($document);
        $commonmarkPipe = (new MarkdownWriter([
            'variant' => 'commonmark',
            'simpleTables' => false,
            'multilineTables' => false,
        ]))->write($document);

        $t->contains('CommonMark reviewer captions stay visible {.wp-review-commonmark source="batch-42"}', $simpleDisabled);
        $t->true(!str_contains($simpleDisabled, ': CommonMark reviewer captions'), 'Disabled table_captions keeps simple-table caption text but omits Pandoc colon marker');
        $t->contains('| Source | Review note                       |', $pipeDisabled);
        $t->contains("\nCommonMark reviewer captions stay visible {.wp-review-commonmark source=\"batch-42\"}", $pipeDisabled);
        $t->true(!str_contains($pipeDisabled, "\n: CommonMark reviewer captions"), 'Disabled table_captions keeps pipe-table caption text but omits Pandoc colon marker');
        $t->same($pipeDisabled, $commonmarkPipe, 'CommonMark writer profile follows the same no-colon table-caption branch');
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
    'maps upstream markdown writer raw html fallback for attributed links and images' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Fallback: '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                    'title' => 'Review "packet"',
                    'id' => 'review-link',
                    'classes' => ['source-link'],
                    'attributes' => [
                        'source' => 'batch-42',
                        'aria-label' => 'Review packet',
                    ],
                ], [
                    $text('review '),
                    new AstNode('emph', [], [$text('packet')]),
                ]),
                $text(' media '),
                new AstNode('image', [
                    'url' => '/uploads/review.jpg?size=full&v=2',
                    'title' => 'Review image',
                    'alt' => 'Editorial alt',
                    'id' => 'review-image',
                    'classes' => ['wp-import'],
                    'attributes' => ['source' => 'batch-42'],
                ], [$text('Visible caption')]),
                $text('.'),
            ]),
        ]);
        $referenceDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('link', [
                    'url' => '/source',
                    'id' => 'source-link',
                    'classes' => ['review'],
                    'attributes' => ['source' => 'batch-42'],
                ], [$text('source')]),
            ]),
        ]);

        $t->same(
            'Fallback: <a href="/wp-admin/post.php?post=42&amp;action=edit" title="Review &quot;packet&quot;" id="review-link" class="source-link" data-source="batch-42" aria-label="Review packet">review <em>packet</em></a> media <img src="/uploads/review.jpg?size=full&amp;v=2" title="Review image" id="review-image" class="wp-import" alt="Editorial alt" data-source="batch-42" />.',
            (new MarkdownWriter(['linkAttributes' => false]))->write($document)
        );
        $t->same(
            'Fallback: [review *packet*](/wp-admin/post.php?post=42&action=edit "Review \"packet\"") media ![Visible caption](/uploads/review.jpg?size=full&v=2 "Review image").',
            (new MarkdownWriter(['linkAttributes' => false, 'rawHtml' => false]))->write($document)
        );
        $t->same(implode("\n", [
            '[source]',
            '',
            '  [source]: /source',
        ]), (new MarkdownWriter([
            'referenceLinks' => true,
            'linkAttributes' => false,
        ]))->write($referenceDocument));
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
    'maps upstream command gfm details list raw html boundaries' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $item = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
        $document = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                $item([
                    $text('list item'),
                    new AstNode('raw_html', ['html' => '<details>']),
                    new AstNode('bullet_list', [], [
                        $item([$text('subitem')]),
                    ]),
                    new AstNode('raw_html', ['html' => '</details>']),
                    new AstNode('paragraph', [], [
                        $text('item '),
                        new AstNode('emph', [], [$text('continue')]),
                        $text(' '),
                        new AstNode('strong', [], [$text('with')]),
                        $text(' formatting'),
                    ]),
                ]),
                $item([$text('next list item')]),
            ]),
        ]);
        $expected = implode("\n", [
            '- list item',
            '  <details>',
            '',
            '  - subitem',
            '',
            '  </details>',
            '',
            '  item *continue* **with** formatting',
            '- next list item',
        ]);
        $defaultMarkdown = (new MarkdownWriter())->write($document);
        $gfmMarkdown = (new MarkdownWriter(['variant' => 'gfm']))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(rtrim((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-gfm-details-list.md'), "\r\n"), $gfmMarkdown);
        $t->same($expected, $gfmMarkdown);
        $t->true(!str_contains($defaultMarkdown, "  <details>\n\n  - subitem"), 'Default markdown writer should not claim the GFM details/list spacing slice');
        $t->contains('<details><ul><li>subitem</li></ul></details>', $blocks);
        $t->contains('item <em>continue</em> <strong>with</strong> formatting</li>', $blocks);
    },
    'maps upstream markdown writer adjacent bullet and ordered list separators' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $item = static fn (string $value): AstNode => new AstNode('list_item', [], [$text($value)]);
        $bulletLists = new AstNode('document', [], [
            new AstNode('bullet_list', [], [$item('Review imported posts')]),
            new AstNode('bullet_list', [], [$item('Audit media attachments')]),
        ]);
        $orderedLists = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 1], [
                $item('Import first batch'),
                $item('Import second batch'),
            ]),
            new AstNode('ordered_list', [
                'start' => 3,
                'style' => 'lower_alpha',
                'delimiter' => 'period',
            ], [
                $item('Reviewer alpha queue'),
            ]),
        ]);

        $t->same(implode("\n", [
            '- Review imported posts',
            '',
            '<!-- -->',
            '',
            '- Audit media attachments',
        ]), (new MarkdownWriter())->write($bulletLists));
        $t->same(implode("\n", [
            '- Review imported posts',
            '',
            '&nbsp;',
            '',
            '- Audit media attachments',
        ]), (new MarkdownWriter(['rawHtml' => false]))->write($bulletLists));
        $t->same(implode("\n", [
            '1.  Import first batch',
            '2.  Import second batch',
            '',
            '<!-- -->',
            '',
            'c.  Reviewer alpha queue',
        ]), (new MarkdownWriter())->write($orderedLists));
        $t->same(implode("\n", [
            '1.  Import first batch',
            '2.  Import second batch',
            '',
            '&nbsp;',
            '',
            'c.  Reviewer alpha queue',
        ]), (new MarkdownWriter(['rawHtml' => false]))->write($orderedLists));
    },
    'maps upstream markdown writer raw and plain fixBlocks tight boundaries' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
        $rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);

        $plainRawPlain = new AstNode('document', [], [
            $plain('Source review note'),
            $rawHtml('<aside data-source="batch-42">Raw WordPress audit card</aside>'),
            $plain('Reviewer continuation'),
        ]);
        $rawPlain = new AstNode('document', [], [
            $rawHtml('<section data-source="batch-42">Imported raw block</section>'),
            $plain('Plain reviewer handoff'),
        ]);
        $rawRawHeading = new AstNode('document', [], [
            $rawHtml('<!-- wp:separator -->'),
            $rawHtml('<section data-source="batch-42">Second raw block</section>'),
            new AstNode('heading', ['level' => 2], [$text('Next Review Step')]),
        ]);
        $divBody = new AstNode('document', [], [
            new AstNode('div', ['classes' => ['source-review']], [
                $plain('Div source note'),
                $rawHtml('<aside data-source="batch-42">Raw card inside fenced div</aside>'),
                $plain('Div reviewer continuation'),
            ]),
        ]);

        $t->same(implode("\n", [
            'Source review note',
            '<aside data-source="batch-42">Raw WordPress audit card</aside>',
            'Reviewer continuation',
        ]), (new MarkdownWriter())->write($plainRawPlain));
        $t->same(implode("\n", [
            '<section data-source="batch-42">Imported raw block</section>',
            'Plain reviewer handoff',
        ]), (new MarkdownWriter())->write($rawPlain));
        $t->same(implode("\n", [
            '<!-- wp:separator -->',
            '<section data-source="batch-42">Second raw block</section>',
            '',
            '## Next Review Step',
        ]), (new MarkdownWriter())->write($rawRawHeading));
        $t->same(implode("\n", [
            '::: {.source-review}',
            'Div source note',
            '<aside data-source="batch-42">Raw card inside fenced div</aside>',
            'Div reviewer continuation',
            ':::',
        ]), (new MarkdownWriter())->write($divBody));
    },
    'maps upstream markdown writer in-list plain before fenced div fixBlocks branch' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
        $listReviewPacket = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    $plain('Review source note'),
                    new AstNode('div', [
                        'classes' => ['wp-import-review'],
                        'attributes' => ['data-source' => 'batch-42'],
                    ], [
                        $plain('Raw packet stays grouped'),
                    ]),
                ]),
            ]),
        ]);
        $disabledFencedDivs = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    $plain('Review source note'),
                    new AstNode('div', [
                        'classes' => ['wp-import-review'],
                    ], [
                        $plain('Raw packet falls back to content'),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '- Review source note',
            '',
            '  ::: {.wp-import-review data-source="batch-42"}',
            '  Raw packet stays grouped',
            '  :::',
        ]), (new MarkdownWriter())->write($listReviewPacket));
        $t->same(implode("\n", [
            '-',
            '  Review source note',
            '  Raw packet falls back to content',
        ]), (new MarkdownWriter([
            'fencedDivs' => false,
            'nativeDivs' => false,
            'rawHtml' => false,
        ]))->write($disabledFencedDivs));
    },
    'maps upstream markdown writer ordered paragraph before fenced div boundary' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
        $document = new AstNode('document', [], [
            new AstNode('ordered_list', [
                'start' => 100,
                'style' => 'decimal',
                'delimiter' => 'period',
            ], [
                new AstNode('list_item', [], [
                    $paragraph('Review source paragraph'),
                    new AstNode('div', [
                        'classes' => ['wp-import-review'],
                        'attributes' => ['data-source' => 'batch-42'],
                    ], [
                        $plain('Raw packet stays grouped'),
                    ]),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document);

        $t->same(implode("\n", [
            '100. Review source paragraph',
            '',
            '     ::: {.wp-import-review data-source="batch-42"}',
            '     Raw packet stays grouped',
            '     :::',
        ]), $markdown);
        $t->true(!str_contains($markdown, "\n  :::"), 'Ordered-list fenced Divs use marker continuation indent, not the bullet-list two-space indent');
        $t->contains("\n\n     :::", $markdown, 'Paragraph-before-Div list items keep the Pandoc block boundary blank line');
    },
    'maps upstream markdown writer definition list tight loose and fallback branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
        $term = static fn (array $children, string $text): AstNode => new AstNode('term', ['text' => $text], $children);
        $definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
        $item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
            'definition_item',
            ['term' => $term->attr('text', '')],
            array_merge([$term], $definitions)
        );

        $list = new AstNode('definition_list', [], [
            $item($term([$text('apple')], 'apple'), [
                $definition([$plain('red fruit')]),
                $definition([$plain('computer')]),
            ]),
            $item($term([new AstNode('emph', [], [$text('orange')])], 'orange'), [
                $definition([
                    $paragraph('orange fruit'),
                    new AstNode('code_block', ['text' => '{ orange code block }']),
                    new AstNode('blockquote', [], [$paragraph('orange block quote')]),
                ]),
            ]),
        ]);
        $document = new AstNode('document', [], [$list]);
        $tabStopTwo = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([$text('Plugin')], 'Plugin'), [$definition([$plain('Stable release')])]),
            ]),
        ]);
        $disabledDefinitionLists = (new MarkdownWriter(['definitionLists' => false]))->write(new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([$text('apple')], 'apple'), [
                    $definition([$plain('red fruit')]),
                    $definition([$plain('computer')]),
                ]),
            ]),
        ]));
        $adjacentDefinitionLists = new AstNode('document', [], [
            new AstNode('definition_list', [], [
                $item($term([$text('Alpha')], 'Alpha'), [$definition([$plain('first glossary item')])]),
            ]),
            new AstNode('definition_list', [], [
                $item($term([$text('Beta')], 'Beta'), [$definition([$plain('second glossary item')])]),
            ]),
        ]);

        $t->same(implode("\n", [
            'apple',
            ':   red fruit',
            ':   computer',
            '',
            '*orange*',
            '',
            ':   orange fruit',
            '',
            '        { orange code block }',
            '',
            '    > orange block quote',
        ]), (new MarkdownWriter())->write($document));
        $t->same("Plugin\n: Stable release", (new MarkdownWriter(['tabStop' => 2]))->write($tabStopTwo));
        $t->same(implode("\n", [
            'apple  ',
            'red fruit',
            '',
            'computer',
        ]), $disabledDefinitionLists);
        $t->same(implode("\n", [
            'Alpha',
            ':   first glossary item',
            '',
            '<!-- -->',
            '',
            'Beta',
            ':   second glossary item',
        ]), (new MarkdownWriter())->write($adjacentDefinitionLists));
        $t->same(implode("\n", [
            'Alpha',
            ':   first glossary item',
            '',
            '&nbsp;',
            '',
            'Beta',
            ':   second glossary item',
        ]), (new MarkdownWriter(['rawHtml' => false]))->write($adjacentDefinitionLists));
    },
    'maps upstream markdown writer nested and empty emphasis branches' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $writer = new MarkdownWriter();
        $nestedEmphasis = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Reviewer emphasis: '),
                new AstNode('emph', [], [
                    new AstNode('emph', [], [
                        $text('source '),
                        new AstNode('strong', [], [$text('flag')]),
                    ]),
                ]),
                $text('.'),
            ]),
        ]);
        $emptyEmphasis = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Empty emphasis: before'),
                new AstNode('emph', [], []),
                $text('after.'),
            ]),
        ]);
        $emptyStrong = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Empty strong: before'),
                new AstNode('strong', [], []),
                $text('after.'),
            ]),
        ]);

        $t->same('Reviewer emphasis: source **flag**.', $writer->write($nestedEmphasis));
        $t->same('Empty emphasis: beforeafter.', $writer->write($emptyEmphasis));
        $t->same('Empty strong: beforeafter.', $writer->write($emptyStrong));
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
    'maps upstream command details summary raw html with markdown body' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-details-summary.md');
        $document = (new MarkdownReader())->read($source);
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['raw_html', 'raw_html', 'paragraph', 'paragraph', 'raw_html'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('<details class="migration-review" data-source="classic">', $document->children[0]->attr('html'));
        $t->same('<summary>Show imported source notes</summary>', $document->children[1]->attr('html'));
        $t->same('details para 1 with emphasis.', $document->children[2]->attr('text'));
        $t->same(['text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $document->children[2]->children));
        $t->same('emphasis', $document->children[2]->children[1]->children[0]->attr('text'));
        $t->same('details para 2 with strong context.', $document->children[3]->attr('text'));
        $t->same('strong', $document->children[3]->children[1]->type);
        $t->same('</details>', $document->children[4]->attr('html'));
        $t->contains('RawBlock (Format "html") "<details class=\\"migration-review\\" data-source=\\"classic\\">"', $native);
        $t->contains('RawBlock (Format "html") "<summary>Show imported source notes</summary>"', $native);
        $t->contains('Emph [ Str "emphasis" ]', $native);
        $t->contains('Strong [ Str "strong" ]', $native);
        $t->contains('<details class="migration-review" data-source="classic">', $blocks);
        $t->contains('<summary>Show imported source notes</summary>', $blocks);
        $t->contains('<p>details para 1 with <em>emphasis</em>.</p>', $blocks);
        $t->contains('<p>details para 2 with <strong>strong</strong> context.</p>', $blocks);
        $t->true(!str_contains($blocks, '&lt;details'), 'Details raw HTML boundaries should stay active for WordPress review handoff');

        $disabled = (new MarkdownReader(['htmlRawHtml' => false]))->read($source);
        $disabledNative = (new NativeWriter(['blocksOnly' => true]))->write($disabled);
        $t->same(['plain', 'paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $disabled->children));
        $t->same('Show imported source notes', $disabled->children[0]->attr('text'));
        $t->true(!str_contains($disabledNative, 'RawBlock (Format "html")'), 'Disabled raw HTML import should drop details and summary raw boundaries');
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
    'maps upstream html reader root lang metadata cases' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $lang = $reader->read('<html lang="es">hola');
        $xmlLang = $reader->read('<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es"><head></head><body>hola</body></html>');

        $t->same('es', $lang->attr('meta')['lang'] ?? '');
        $t->same('paragraph', $lang->children[0]->type);
        $t->same('hola', $lang->children[0]->attr('text'));
        $t->same('es', $xmlLang->attr('meta')['lang'] ?? '');
        $t->same('paragraph', $xmlLang->children[0]->type);
        $t->same('hola', $xmlLang->children[0]->attr('text'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-lang-metadata.html');
        $document = $reader->read($fixture);
        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($document);

        $t->same('HTML Lang Import', $document->attr('meta')['title'] ?? '');
        $t->same('es', $document->attr('meta')['lang'] ?? '');
        $t->same('Revision del lote: conservar idioma de origen para la revision editorial.', $document->children[0]->attr('text'));
        $t->contains('<dt data-pandoc-meta-key="lang">lang</dt><dd><span>es</span></dd>', $blocks);
        $t->contains('<p>Revision del lote: conservar idioma de origen para la revision editorial.</p>', $blocks);
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
    'maps upstream html reader line-block divs into line block ast' => static function (TestRunner $t): void {
        $nbsp = "\xC2\xA0";
        $reader = new MarkdownReader();
        $fragment = $reader->read('<div class="line-block">hi<br /><br>&nbsp;there</div>');
        $lineBlock = $fragment->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($fragment);
        $blocks = (new WordPressBlockWriter())->write($fragment);

        $t->same(1, count($fragment->children));
        $t->same('line_block', $lineBlock->type);
        $t->same(3, count($lineBlock->children));
        $t->same('hi', $lineBlock->children[0]->attr('text'));
        $t->same([], $lineBlock->children[1]->children);
        $t->same($nbsp . 'there', $lineBlock->children[2]->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $lineBlock->children[0]->children));
        $t->contains('LineBlock [ [ Str "hi" ] , [  ] , [ Str "\160there" ] ]', $native);
        $t->contains('<p>hi<br/><br/>' . $nbsp . 'there</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-line-block.html');
        $document = $reader->read($fixture);
        $fixtureLineBlock = $document->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Line Block Import', $document->attr('meta')['title'] ?? '');
        $t->same('line_block', $fixtureLineBlock->type);
        $t->same(4, count($fixtureLineBlock->children));
        $t->same('Reviewer stanza', $fixtureLineBlock->children[0]->attr('text'));
        $t->same('', $fixtureLineBlock->children[1]->attr('text'));
        $t->same($nbsp . $nbsp . 'keep indentation', $fixtureLineBlock->children[2]->attr('text'));
        $t->same('link', $fixtureLineBlock->children[3]->children[0]->type);
        $t->contains('<p>Reviewer stanza<br/><br/>' . $nbsp . $nbsp . 'keep indentation<br/><a href="/wp-admin/post.php?post=42&amp;action=edit">edit source</a></p>', $fixtureBlocks);
        $t->contains('<p>After stanza.</p>', $fixtureBlocks);
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
    'maps upstream html reader small inline tags to small spans' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Lead <small id="discarded" class="source-small">fine <em>print</em></small> tail.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $small = $paragraph->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'span', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('span', $small->type);
        $t->same('', $small->attr('id', ''));
        $t->same(['small'], $small->attr('classes'));
        $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $small->children));
        $t->same('fine ', $small->children[0]->attr('text'));
        $t->same('print', $small->children[1]->children[0]->attr('text'));
        $t->contains('Span ( "" , [ "small" ] , [  ] ) [ Str "fine" , Space , Emph [ Str "print" ] ]', $native);
        $t->contains('<p>Lead <span class="small">fine <em>print</em></span> tail.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-small-inline.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureSmall = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Small Inline Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('span', $fixtureSmall->type);
        $t->same(['small'], $fixtureSmall->attr('classes'));
        $t->same('', $fixtureSmall->attr('id', ''));
        $t->contains('<span class="small">source fine print <strong>needs review</strong></span>', $fixtureBlocks);
    },
    'maps upstream html reader bdo direction override to spans' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Lead <bdo dir="RTL">source <strong>slug</strong></bdo> tail <bdo>plain order</bdo>.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $direction = $paragraph->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'span', 'text', 'text', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('span', $direction->type);
        $t->same(['dir' => 'rtl'], $direction->attr('attributes'));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $direction->children));
        $t->same('source ', $direction->children[0]->attr('text'));
        $t->same('slug', $direction->children[1]->children[0]->attr('text'));
        $t->same('plain order', $paragraph->children[3]->attr('text'), 'bdo without dir should return plain inline contents');
        $t->contains('Span ( "" , [  ] , [ ( "dir" , "rtl" ) ] ) [ Str "source" , Space , Strong [ Str "slug" ] ]', $native);
        $t->contains('<p>Lead <span dir="rtl">source <strong>slug</strong></span> tail plain order.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-bdo-direction.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureDirection = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML BDO Direction Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('span', $fixtureDirection->type);
        $t->same(['dir' => 'rtl'], $fixtureDirection->attr('attributes'));
        $t->contains('<span dir="rtl">source <strong>slug</strong></span>', $fixtureBlocks);
        $t->contains('<p>Neutral plain order stays plain.</p>', $fixtureBlocks);
    },
    'maps upstream html reader span-like inline elements to classed spans' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $standaloneKbd = $reader->read('<kbd>Ctrl+P</kbd>')->children[0]->children[0] ?? new AstNode('missing');
        $document = $reader->read('<p>Use <kbd id="key" class="source" data-origin="classic">Ctrl <strong>P</strong></kbd>, <mark class="review">publish</mark>, and <dfn><abbr title="HyperText Markup Language">HTML</abbr></dfn>.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $kbd = $paragraph->children[1] ?? new AstNode('missing');
        $mark = $paragraph->children[3] ?? new AstNode('missing');
        $dfn = $paragraph->children[5] ?? new AstNode('missing');
        $abbr = $dfn->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('span', $standaloneKbd->type);
        $t->same(['kbd'], $standaloneKbd->attr('classes'));
        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'span', 'text', 'span', 'text', 'span', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('key', $kbd->attr('id'));
        $t->same(['kbd', 'source'], $kbd->attr('classes'));
        $t->same(['origin' => 'classic'], $kbd->attr('attributes'));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $kbd->children));
        $t->same(['mark', 'review'], $mark->attr('classes'));
        $t->same('publish', $mark->children[0]->attr('text'));
        $t->same(['dfn'], $dfn->attr('classes'));
        $t->same(['abbr'], $abbr->attr('classes'));
        $t->same(['title' => 'HyperText Markup Language'], $abbr->attr('attributes'));
        $t->contains('Span ( "key" , [ "kbd" , "source" ] , [ ( "origin" , "classic" ) ] ) [ Str "Ctrl" , Space , Strong [ Str "P" ] ]', $native);
        $t->contains('<p>Use <kbd id="key" class="source" data-origin="classic">Ctrl <strong>P</strong></kbd>, <mark class="review">publish</mark>, and <dfn><abbr title="HyperText Markup Language">HTML</abbr></dfn>.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-spanlike-inline.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureKbd = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Span-Like Inline Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('span', $fixtureKbd->type);
        $t->same(['kbd', 'source-key'], $fixtureKbd->attr('classes'));
        $t->same(['origin' => 'classic-editor'], $fixtureKbd->attr('attributes'));
        $t->contains('<kbd id="publish-shortcut" class="source-key" data-origin="classic-editor">Ctrl+Alt+P</kbd>', $fixtureBlocks);
        $t->contains('<dfn><abbr title="HyperText Markup Language">HTML</abbr></dfn>', $fixtureBlocks);
    },
    'maps upstream html reader standalone inline flow fragments' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $source = implode("\n", [
            '<small data-source="classic">source <strong>fine print</strong></small>',
            '<sup>2</sup>',
            '<span id="term" class="smallcaps">source term</span>',
            '<time datetime="2026-05-24">handoff day</time>',
            '<q cite="https://example.test/source">quoted source</q>',
            '<cite data-source="manual"><em>Handbook</em></cite>',
        ]);
        $document = $reader->read($source);
        $small = $document->children[0]->children[0] ?? new AstNode('missing');
        $sup = $document->children[1]->children[0] ?? new AstNode('missing');
        $smallCaps = $document->children[2]->children[0] ?? new AstNode('missing');
        $timeOpen = $document->children[3]->children[0] ?? new AstNode('missing');
        $quote = $document->children[4]->children[0] ?? new AstNode('missing');
        $citeOpen = $document->children[5]->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, count($document->children));
        $t->same('span', $small->type);
        $t->same(['small'], $small->attr('classes'));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $small->children));
        $t->same('superscript', $sup->type);
        $t->same('2', $sup->children[0]->attr('text'));
        $t->same('small_caps', $smallCaps->type);
        $t->same('source term', $smallCaps->children[0]->attr('text'));
        $t->same('raw_html_inline', $timeOpen->type);
        $t->same('<time datetime="2026-05-24">', $timeOpen->attr('html'));
        $t->same('handoff day', $document->children[3]->children[1]->attr('text'));
        $t->same('quoted', $quote->type);
        $t->same(['cite' => 'https://example.test/source'], $quote->children[0]->attr('attributes'));
        $t->same('raw_html_inline', $citeOpen->type);
        $t->same('<cite data-source="manual">', $citeOpen->attr('html'));
        $t->contains('Span ( "" , [ "small" ] , [  ] ) [ Str "source" , Space , Strong [ Str "fine" , Space , Str "print" ] ]', $native);
        $t->contains('Superscript [ Str "2" ]', $native);
        $t->contains('SmallCaps [ Str "source" , Space , Str "term" ]', $native);
        $t->contains('RawInline (Format "html") "<time datetime=\\"2026-05-24\\">"', $native);
        $t->contains('RawInline (Format "html") "<cite data-source=\\"manual\\">"', $native);
        $t->contains('<p><span class="small">source <strong>fine print</strong></span></p>', $blocks);
        $t->contains('<p><sup>2</sup></p>', $blocks);
        $t->contains('<p><span style="font-variant:small-caps">source term</span></p>', $blocks);
        $t->contains('<p><time datetime="2026-05-24">handoff day</time></p>', $blocks);
        $t->contains('<p>“<span cite="https://example.test/source">quoted source</span>”</p>', $blocks);
        $t->contains('<p><cite data-source="manual"><em>Handbook</em></cite></p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-inline-flow.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same(5, count($fixtureDocument->children));
        $t->contains('<span class="small">source <strong>fine print</strong></span>', $fixtureBlocks);
        $t->contains('<span style="font-variant:small-caps">review term</span>', $fixtureBlocks);
        $t->contains('<time datetime="2026-05-24">handoff day</time>', $fixtureBlocks);
        $t->contains('<p>“<span cite="https://example.test/import-log#source">quoted source</span>”</p>', $fixtureBlocks);
        $t->contains('<cite data-source="manual"><em>Import Handbook</em></cite>', $fixtureBlocks);
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
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('linebreak', $firstBreak->type);
        $t->same('linebreak', $secondBreak->type);
        $t->same('tail', $secondTail->attr('text'));
        $t->same('linebreak', $thirdBreak->type);
        $t->contains('Para [ LineBreak ]', $native);
        $t->contains('Para [ LineBreak , Str "tail" ]', $native);
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
    'maps upstream html reader span smallcaps class to native small caps' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Glossary <span id="legacy-term" class="source smallcaps" data-origin="classic">source <a href="/glossary">term</a></span> tail.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $smallCaps = $paragraph->children[1] ?? new AstNode('missing');
        $link = $smallCaps->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'small_caps', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('small_caps', $smallCaps->type);
        $t->same('', $smallCaps->attr('id', ''));
        $t->same([], $smallCaps->attr('classes', []));
        $t->same('source ', $smallCaps->children[0]->attr('text'));
        $t->same('link', $link->type);
        $t->same('/glossary', $link->attr('url'));
        $t->same('term', $link->children[0]->attr('text'));
        $t->contains('SmallCaps [ Str "source" , Space , Link ( "" , [  ] , [  ] ) [ Str "term" ] ( "/glossary" , "" ) ]', $native);
        $t->contains('<p>Glossary <span style="font-variant:small-caps">source <a href="/glossary">term</a></span> tail.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-smallcaps-class.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureSmallCaps = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureStyled = $fixtureDocument->children[1]->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML SmallCaps Class Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('small_caps', $fixtureSmallCaps->type);
        $t->same('small_caps', $fixtureStyled->type);
        $t->same([], $fixtureSmallCaps->attr('classes', []), 'Upstream SmallCaps constructor drops span classes after recognizing smallcaps');
        $t->contains('<span style="font-variant:small-caps">source <a href="/glossary">term</a></span>', $fixtureBlocks);
        $t->contains('<span style="font-variant:small-caps">review token</span>', $fixtureBlocks);
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
    'maps upstream html reader pre code block attributes and pre precedence' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $attributedCode = $reader->read("<pre><code id=\"a\" class=\"python\">\nprint('hi')\n</code></pre>")->children[0] ?? new AstNode('missing');
        $preWins = $reader->read("<pre id=\"c\"><code id=\"d\">print('hi mom!')\n</code></pre>")->children[0] ?? new AstNode('missing');
        $attributedNative = (new NativeWriter())->write(new AstNode('document', [], [$attributedCode]));
        $preWinsNative = (new NativeWriter())->write(new AstNode('document', [], [$preWins]));

        $t->same('code_block', $attributedCode->type);
        $t->same('a', $attributedCode->attr('id'));
        $t->same(['python'], $attributedCode->attr('classes'));
        $t->same([], $attributedCode->attr('attributes'));
        $t->same("\nprint('hi')", $attributedCode->attr('text'));
        $t->contains('CodeBlock ( "a" , [ "python" ] , [  ] ) "\\nprint(\'hi\')"', $attributedNative);

        $t->same('code_block', $preWins->type);
        $t->same('c', $preWins->attr('id'));
        $t->same([], $preWins->attr('classes', []));
        $t->same([], $preWins->attr('attributes'));
        $t->same("print('hi mom!')", $preWins->attr('text'));
        $t->contains('CodeBlock ( "c" , [  ] , [  ] ) "print(\'hi mom!\')"', $preWinsNative);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-pre-code-attributes.html');
        $document = $reader->read($fixture);
        $blocks = (new WordPressBlockWriter())->write($document);
        $reviewSnippet = $document->children[1] ?? new AstNode('missing');
        $preWrapper = $document->children[3] ?? new AstNode('missing');

        $t->same('HTML Code Block Attribute Import', $document->attr('meta')['title'] ?? '');
        $t->same('legacy-snippet', $reviewSnippet->attr('id'));
        $t->same(['php'], $reviewSnippet->attr('classes'));
        $t->same(['source' => 'batch-42'], $reviewSnippet->attr('attributes'));
        $t->same('pre-wrapper-wins', $preWrapper->attr('id'));
        $t->same(['review' => 'keep'], $preWrapper->attr('attributes'));
        $t->same([], $preWrapper->attr('classes', []));
        $t->contains('<pre class="wp-block-code" id="legacy-snippet" data-source="batch-42"><code class="language-php">wp_update_post($post_id);', $blocks);
        $t->contains('<pre class="wp-block-code" id="pre-wrapper-wins" data-review="keep"><code>console.log(&#039;pre attrs win&#039;);</code></pre>', $blocks);
        $t->true(!str_contains($blocks, 'nested-code-loses'), 'HTML reader code-block handoff should use pre attrs when upstream pre precedence applies');
    },
    'maps upstream html reader pre br line breaks and bare pre blocks' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $withBreak = $reader->read("<pre><code id=\"legacy\" class=\"language-php\">echo 1;<br>echo 2;\n</code></pre>")->children[0] ?? new AstNode('missing');
        $barePre = $reader->read("<pre id=\"source-raw\" data-review=\"keep\">first<br/>second\n</pre>")->children[0] ?? new AstNode('missing');
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-pre-code-br.html');
        $document = $reader->read($fixture);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $legacySnippet = $document->children[1] ?? new AstNode('missing');
        $rawPre = $document->children[3] ?? new AstNode('missing');

        $t->same('code_block', $withBreak->type);
        $t->same('legacy', $withBreak->attr('id'));
        $t->same(['php'], $withBreak->attr('classes'));
        $t->same("echo 1;\necho 2;", $withBreak->attr('text'));
        $t->same('code_block', $barePre->type);
        $t->same('source-raw', $barePre->attr('id'));
        $t->same(['review' => 'keep'], $barePre->attr('attributes'));
        $t->same("first\nsecond", $barePre->attr('text'));

        $t->same('HTML Pre Code Break Import', $document->attr('meta')['title'] ?? '');
        $t->same('legacy-shortcode', $legacySnippet->attr('id'));
        $t->same(['php'], $legacySnippet->attr('classes'));
        $t->same(['source' => 'classic-editor'], $legacySnippet->attr('attributes'));
        $t->same("do_shortcode('[gallery]');\necho esc_html(\$title);", $legacySnippet->attr('text'));
        $t->same('raw-pre-export', $rawPre->attr('id'));
        $t->same(['review' => 'keep'], $rawPre->attr('attributes'));
        $t->same("First imported line\nSecond imported line", $rawPre->attr('text'));
        $t->contains('CodeBlock ( "legacy-shortcode" , [ "php" ] , [ ( "source" , "classic-editor" ) ] ) "do_shortcode', $roundTrip);
        $t->contains('CodeBlock ( "raw-pre-export" , [  ] , [ ( "review" , "keep" ) ] ) "First imported line\\nSecond imported line"', $roundTrip);
        $t->contains('<pre class="wp-block-code" id="legacy-shortcode" data-source="classic-editor"><code class="language-php">do_shortcode(&#039;[gallery]&#039;);' . "\n" . 'echo esc_html($title);</code></pre>', $blocks);
        $t->contains('<pre class="wp-block-code" id="raw-pre-export" data-review="keep"><code>First imported line' . "\n" . 'Second imported line</code></pre>', $blocks);
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
    'maps upstream html reader list item ids into span and div anchors' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(<<<'HTML'
<ul>
<li>foo</li>
<li id="id">bar<ul><li>subbar</li></ul></li>
<li>baz</li>
</ul>
<ul>
<li><p>foo</p></li>
<li id="loose-id"><p>bar</p></li>
<li><p>baz</p></li>
</ul>
HTML);
        $tightList = $document->children[0] ?? new AstNode('missing');
        $tightAnchoredItem = $tightList->children[1] ?? new AstNode('missing');
        $tightSpan = $tightAnchoredItem->children[0] ?? new AstNode('missing');
        $nestedList = $tightAnchoredItem->children[1] ?? new AstNode('missing');
        $looseList = $document->children[1] ?? new AstNode('missing');
        $looseAnchoredItem = $looseList->children[1] ?? new AstNode('missing');
        $looseDiv = $looseAnchoredItem->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('span', $tightSpan->type);
        $t->same('id', $tightSpan->attr('id'));
        $t->same('bar', $tightSpan->children[0]->attr('text'));
        $t->same('bullet_list', $nestedList->type);
        $t->same('subbar', $nestedList->children[0]->children[0]->attr('text'));
        $t->same('div', $looseDiv->type);
        $t->same('loose-id', $looseDiv->attr('id'));
        $t->same('paragraph', $looseDiv->children[0]->type);
        $t->same('bar', $looseDiv->children[0]->attr('text'));
        $t->contains('Plain [ Span ( "id" , [  ] , [  ] ) [ Str "bar" ]', $native);
        $t->contains('Div ( "loose-id" , [  ] , [  ] )', $native);
        $t->contains('- [bar]{#id}', $markdown);
        $t->contains('::: {#loose-id}', $markdown);
        $t->contains('<li><span id="id">bar</span><ul><li>subbar</li></ul></li>', $blocks);
        $t->contains('<li><div id="loose-id"><p>bar</p></div></li>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-list-item-id.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureTightList = $fixtureDocument->children[1] ?? new AstNode('missing');
        $fixtureLooseList = $fixtureDocument->children[2] ?? new AstNode('missing');
        $fixtureTightSpan = $fixtureTightList->children[1]->children[0] ?? new AstNode('missing');
        $fixtureLooseDiv = $fixtureLooseList->children[1]->children[0] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML List Item ID Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('source-bar', $fixtureTightSpan->attr('id'));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $fixtureTightSpan->children));
        $t->same('loose-source', $fixtureLooseDiv->attr('id'));
        $t->contains('<span id="source-bar">bar <strong>label</strong></span>', $fixtureBlocks);
        $t->contains('<div id="loose-source"><p>loose anchored paragraph</p></div>', $fixtureBlocks);
    },
    'maps upstream html reader orphan list blocks as list items and continuations' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(<<<'HTML'
<ul>
<p>orphan intro</p>
<li>valid item</li>
<ul><li>orphan nested</li></ul>
<li>tail</li>
</ul>
<ol start="3">
<li>one</li>
<div>orphan after</div>
<li>two</li>
</ol>
HTML);
        $bullet = $document->children[0] ?? new AstNode('missing');
        $ordered = $document->children[1] ?? new AstNode('missing');
        $leadingOrphanItem = $bullet->children[0] ?? new AstNode('missing');
        $continuedItem = $bullet->children[1] ?? new AstNode('missing');
        $nestedList = $continuedItem->children[1] ?? new AstNode('missing');
        $orderedContinuedItem = $ordered->children[0] ?? new AstNode('missing');
        $orderedContinuation = $orderedContinuedItem->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('bullet_list', $bullet->type);
        $t->same(3, count($bullet->children));
        $t->same('paragraph', $leadingOrphanItem->children[0]->type);
        $t->same('orphan intro', $leadingOrphanItem->children[0]->attr('text'));
        $t->same('valid item orphan nested', $continuedItem->attr('text'));
        $t->same('bullet_list', $nestedList->type);
        $t->same('orphan nested', $nestedList->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $ordered->type);
        $t->same(3, $ordered->attr('start'));
        $t->same(['text', 'div'], array_map(static fn (AstNode $node): string => $node->type, $orderedContinuedItem->children));
        $t->same('orphan after', $orderedContinuation->children[0]->attr('text'));
        $t->contains('BulletList [ [ Para [ Str "orphan" , Space , Str "intro" ]', $native);
        $t->contains('Plain [ Str "valid" , Space , Str "item" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "orphan" , Space , Str "nested" ]', $native);
        $t->contains('OrderedList ( 3 , DefaultStyle , DefaultDelim )', $native);
        $t->contains('- orphan intro', $markdown);
        $t->contains('- valid item', $markdown);
        $t->contains('3.  one', $markdown);
        $t->contains('orphan after', $markdown);
        $t->contains('<ul><li><p>orphan intro</p></li><li>valid item<ul><li>orphan nested</li></ul></li><li>tail</li></ul>', $blocks);
        $t->contains('<ol start="3"><li>one<div><p>orphan after</p></div></li><li>two</li></ol>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-orphan-list-blocks.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureBullet = $fixtureDocument->children[1] ?? new AstNode('missing');
        $fixtureOrdered = $fixtureDocument->children[2] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Orphan List Block Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('Source intro note outside li', $fixtureBullet->children[0]->children[0]->attr('text'));
        $t->same('Nested orphan item', $fixtureBullet->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Continuation block outside li', $fixtureOrdered->children[0]->children[1]->children[0]->attr('text'));
        $t->contains('<li><p>Source intro note outside li</p></li>', $fixtureBlocks);
        $t->contains('<li>Primary import item<ul><li>Nested orphan item</li></ul></li>', $fixtureBlocks);
        $t->contains('<ol start="3"><li>Queued review<div><p>Continuation block outside li</p></div></li><li>Publish after review</li></ol>', $fixtureBlocks);
    },
    'maps upstream html reader checkbox inputs inside list items' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(<<<'HTML'
<ul class="task-list">
<li><label><input type="checkbox" />foo</label></li>
<li><label><input type="checkbox" checked="" />bar</label></li>
<li><input type="button" checked="" />foobar</li>
<li><input id="hello" type="checkbox" checked/><label for="hello">hello</label></li>
</ul>
<p><input type="checkbox" checked/>outside list checkbox is ignored</p>
HTML);
        $list = $document->children[0] ?? new AstNode('missing');
        $outside = $document->children[1] ?? new AstNode('missing');
        $allTasks = $reader->read('<ul><li><input type="checkbox" />foo</li><li><input type="checkbox" checked/>bar</li></ul>')->children[0] ?? new AstNode('missing');
        $literalGlyph = $reader->read("<ul><li>\u{2612} literal glyph</li></ul>")->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('bullet_list', $list->type);
        $t->same(false, $list->attr('taskList', false), 'Mixed HTML checkbox/plain lists should not claim every item is a task list item');
        $t->same(false, $list->children[0]->attr('taskChecked'));
        $t->same('foo', $list->children[0]->attr('text'));
        $t->same('foo', $list->children[0]->children[0]->attr('text'));
        $t->same(true, $list->children[1]->attr('taskChecked'));
        $t->same('bar', $list->children[1]->attr('text'));
        $t->same(null, $list->children[2]->attr('taskChecked', null));
        $t->same('foobar', $list->children[2]->attr('text'));
        $t->same(true, $list->children[3]->attr('taskChecked'));
        $t->same('hello', $list->children[3]->attr('text'));
        $t->same('outside list checkbox is ignored', $outside->attr('text'));
        $t->true((bool) $allTasks->attr('taskList'), 'All-checkbox HTML lists should become task-list handoffs');
        $t->same(null, $literalGlyph->children[0]->attr('taskChecked', null));
        $t->same("\u{2612} literal glyph", $literalGlyph->children[0]->attr('text'));
        $t->contains('<ul><li><label><input type="checkbox" />foo</label></li><li><label><input type="checkbox" checked="" />bar</label></li><li>foobar</li><li><label><input type="checkbox" checked="" />hello</label></li></ul>', $blocks);
        $t->contains('<p>outside list checkbox is ignored</p>', $blocks);
        $t->true(!str_contains($blocks, 'type="button"'), 'Non-checkbox input controls should not leak into list item output');

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-checkbox-list.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureList = $fixtureDocument->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Checkbox List Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('bullet_list', $fixtureList->type);
        $t->same(false, $fixtureList->children[0]->attr('taskChecked'));
        $t->same(true, $fixtureList->children[1]->attr('taskChecked'));
        $t->same(null, $fixtureList->children[2]->attr('taskChecked', null));
        $t->same(true, $fixtureList->children[3]->attr('taskChecked'));
        $t->contains('<p>Before reviewer checklist.</p>', $fixtureBlocks);
        $t->contains('<li><label><input type="checkbox" />Review imported media</label></li>', $fixtureBlocks);
        $t->contains('<li><label><input type="checkbox" checked="" />Confirm source links</label></li>', $fixtureBlocks);
        $t->contains('<li>Non-task control text</li>', $fixtureBlocks);
        $t->contains('<li><label><input type="checkbox" checked="" />Mark ready to publish</label></li>', $fixtureBlocks);
        $t->contains('<p>Outside list controls are ignored.</p>', $fixtureBlocks);
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
        $t->contains('<p><del>test</del></p>', $blocks);
        $t->contains('<p>&lt;/ div&gt;&lt;/.div&gt;</p>', $blocks);
        $t->contains('<!-- pandoc --help -->', $blocks);
    },
    'maps commonmark paragraph raw html starts to blank-line boundaries' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '<p data-source="commonmark-raw-paragraph">',
            '**raw paragraph** import copy.',
            '',
            'After **paragraph** boundary.',
            '',
            '<p>Structured <em>closed</em> paragraph.</p>',
        ]));
        $rawParagraph = $document->children[0];
        $paragraph = $document->children[1];
        $closedParagraph = $document->children[2];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, count($document->children));
        $t->same('raw_html', $rawParagraph->type);
        $t->same(
            '<p data-source="commonmark-raw-paragraph">' . "\n" . '**raw paragraph** import copy.',
            $rawParagraph->attr('html')
        );
        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('paragraph', $closedParagraph->type);
        $t->same(['text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $closedParagraph->children));
        $t->same('closed', $closedParagraph->children[1]->children[0]->attr('text'));
        $t->contains('<!-- wp:html -->' . "\n" . '<p data-source="commonmark-raw-paragraph">' . "\n" . '**raw paragraph** import copy.', $blocks);
        $t->contains('<p>After <strong>paragraph</strong> boundary.</p>', $blocks);
        $t->contains('<p>Structured <em>closed</em> paragraph.</p>', $blocks);
        $t->true(!str_contains($blocks, '<p>**raw paragraph** import copy.'), 'Unclosed CommonMark p start should stay raw instead of merging as structured HTML paragraph text');
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
    'maps upstream command markdown abbreviations to nonbreaking spaces' => static function (TestRunner $t): void {
        $nbsp = "\xC2\xA0";
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-md-abbrevs.md');
        $document = (new MarkdownReader())->read("Mr. Bob\n\nHi Mr\\. Bob\n\nDr. Rivera and e.g. examples.");
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('Mr. Bob', $fixture);
        $t->contains('Str "Mr.\160Bob"', $fixture);
        $t->same('Mr.' . $nbsp . 'Bob', $document->children[0]->children[0]->attr('text'));
        $t->same('Hi Mr. Bob', $document->children[1]->children[0]->attr('text'));
        $t->same('Dr.' . $nbsp . 'Rivera and e.g.' . $nbsp . 'examples.', $document->children[2]->children[0]->attr('text'));
        $t->contains('Str "Mr.\160Bob"', $native);
        $t->contains('Str "Hi" , Space , Str "Mr." , Space , Str "Bob"', $native);
        $t->contains('Str "Dr.\160Rivera" , Space , Str "and" , Space , Str "e.g.\160examples."', $native);
        $t->contains('<p>Mr.' . $nbsp . 'Bob</p>', $blocks);
        $t->contains('<p>Hi Mr. Bob</p>', $blocks);
        $t->contains('<p>Dr.' . $nbsp . 'Rivera and e.g.' . $nbsp . 'examples.</p>', $blocks);
    },
    'maps upstream markdown github wiki link extension cases' => static function (TestRunner $t): void {
        $document = (new MarkdownReader(['format' => 'markdown_github+wikilinks_title_before_pipe']))->read(implode("\n\n", [
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

        foreach ([$autolink, $titled, $badTarget, $pageName, $literalTitle] as $link) {
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
        $t->same('text', $bracketPageName->type);
        $t->same('[[Name of ]page]]', $bracketPageName->attr('text'));
        $t->same('https://example.org', $literalTitle->attr('url'));
        $t->same('t`i*t_le', $literalTitle->children[0]->attr('text'));
        $t->contains('<a href="https://example.org" class="wikilink">https://example.org</a>', $blocks);
        $t->contains('<a href="https://example.org" class="wikilink">title</a>', $blocks);
        $t->contains('<a href="random string" class="wikilink">title</a>', $blocks);
        $t->contains('<p>[[Name of ]page]]</p>', $blocks);
        $t->contains('<a href="https://example.org" class="wikilink">t`i*t_le</a>', $blocks);
    },
    'writes wordpress structured html table sections from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Structured HTML import table:</p>', $blocks);
        $t->contains('<figure class="wp-block-table"><table id="nordics" data-source="wikipedia"><colgroup><col style="width:30%"/><col style="width:30%"/><col style="width:20%"/><col style="width:20%"/></colgroup><thead class="simple-head"><tr><th style="text-align:center">Name</th><th style="text-align:center">Capital</th><th style="text-align:center">Population', $blocks);
        $t->contains('<tbody class="souvereign-states"><tr class="country"><th style="text-align:center">Denmark</th><td style="text-align:left">Copenhagen</td>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">States belonging to the <em>Nordics.</em></figcaption>', $blocks);
        $t->contains('<tfoot><tr id="summary"><td style="text-align:center">Total</td><td style="text-align:left"></td><td id="total-population" style="text-align:left">27,376,022</td><td id="total-area" style="text-align:left">1,258,336</td></tr></tfoot>', $blocks);
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
        $t->same('horizontal_rule', $document->children[6]->type);
        $t->same([], $document->children[6]->attrs);
        $t->same('horizontal_rule', $document->children[7]->type);
        $t->same([], $document->children[7]->attrs);
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
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader(['format' => 'markdown+wikilinks_title_before_pipe']))->read($fixture));

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
        $t->contains('<p>Citation-adjacent source link: MapReduce was popularized by <a href="https://example.test/source/mapreduce">Google</a> <span class="pandoc-citation" data-pandoc-citation-id="mapreduce"', $blocks);
        $t->contains('>[@mapreduce]</span> during source review.</p>', $blocks);
        $t->contains('<p>Citation boundary audit: <span class="pandoc-citation" data-pandoc-citation-id="cita"', $blocks);
        $t->contains('>@cita [review-only note]</span> stays source citation text, while <span class="pandoc-citation" data-pandoc-citation-id="cita"', $blocks);
        $t->contains('>@cita</span> <a href="https://example.test/citation-link">source log</a> keeps the reviewer link separate.</p>', $blocks);
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

        $t->contains("<dt>Import note</dt><dd>Keep the archive URL attached\nand mention reviewer follow-up.</dd>", $blocks);
        $t->contains('<dt>Cleanup pass</dt><dd>Check legacy shortcodes after block conversion.', $blocks);
        $t->contains('Record manual remediation notes.</dd>', $blocks);
        $t->contains('<div><dl><dt>Migration audit</dt><dd><ul><li>Preserve div-wrapped glossary notes from legacy imports</li></ul></dd></dl></div>', $blocks);
    },
    'writes wordpress alternate definition marker notes with nested review tasks' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<dt>Source glossary</dt><dd><p>Preserve alternate marker notes from older Pandoc exports.</p></dd><dd><p>Verify nested review tasks</p><ol><li>Confirm block conversion</li><li>Attach media IDs</li></ol></dd>', $blocks);
    },
    'writes wordpress raw html blocks and semantic dividers for imported fixtures' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<table>' . "\n" . '<tr>' . "\n" . '<td><em>Legacy caption</em></td>' . "\n" . '<td><strong>Reviewer flag</strong></td>' . "\n" . '</tr>' . "\n" . '</table>', $blocks);
        $t->contains('<p>Empty import audit table:</p>', $blocks);
        $t->true(!str_contains($blocks, '<table>' . "\n" . '<tbody>' . "\n" . '</tbody>' . "\n" . '</table>'), 'Empty fixture tables should not become raw HTML blocks');
        $t->true(!str_contains($blocks, '<table>' . "\n" . '</table>'), 'Empty fixture tables should be omitted');
        $t->contains('<p>Markdown raw HTML boundary audit:</p>', $blocks);
        $t->contains('<p><del>Legacy raw deletion boundary</del></p>', $blocks);
        $t->contains('<!-- Preserve migration audit marker -->', $blocks);
        $t->contains('<!-- wp:separator -->', $blocks);
        $t->contains('<hr class="wp-block-separator has-alpha-channel-opacity"/>', $blocks);
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
    'maps upstream html reader anchors without href and image attributes' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $anchorDoc = $reader->read('<html><body><p><a name="anchor"></a></p></body></html>');
        $anchor = $anchorDoc->children[0]->children[0] ?? new AstNode('missing');
        $externalImageDoc = $reader->read('<html><body><p><img data-external="1" src="http://example.com/stickman.gif"></p></body></html>');
        $externalImage = $externalImageDoc->children[0]->children[0] ?? new AstNode('missing');
        $titleImageDoc = $reader->read('<html><body><p><img title="The title" src="http://example.com/stickman.gif"></p></body></html>');
        $titleImage = $titleImageDoc->children[0]->children[0] ?? new AstNode('missing');

        $t->same('span', $anchor->type);
        $t->same('anchor', $anchor->attr('id'));
        $t->same([], $anchor->children);
        $t->same('image', $externalImage->type);
        $t->same('http://example.com/stickman.gif', $externalImage->attr('url'));
        $t->same(['external' => '1'], $externalImage->attr('attributes'));
        $t->same(['data-external' => '1'], $externalImage->attr('htmlAttributes'));
        $t->same('image', $titleImage->type);
        $t->same('The title', $titleImage->attr('title'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-anchor-image-attrs.html');
        $document = $reader->read($fixture);
        $blocks = (new WordPressBlockWriter())->write($document);
        $legacyAnchor = $document->children[0]->children[0] ?? new AstNode('missing');
        $externalCover = $document->children[1]->children[0] ?? new AstNode('missing');
        $reviewAnchor = $document->children[2]->children[0] ?? new AstNode('missing');

        $t->same('legacy-section', $legacyAnchor->attr('id'));
        $t->same('https://cdn.example.test/original/cover.jpg', $externalCover->attr('url'));
        $t->same(['external' => '1'], $externalCover->attr('attributes'));
        $t->same('review-anchor', $reviewAnchor->attr('id'));
        $t->contains('<p><span id="legacy-section"></span>Legacy source section anchor.</p>', $blocks);
        $t->contains('<figure class="wp-block-image"><img src="https://cdn.example.test/original/cover.jpg" alt="External cover" title="External cover title" data-external="1"/><figcaption>External cover</figcaption></figure>', $blocks);
        $t->contains('<p><span id="review-anchor"></span>Reviewer jump target.</p>', $blocks);
    },
    'maps upstream html reader figure and figcaption blocks' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $captionAfterImage = $reader->read('<figure><img src="foo.png" title="voyage"><figcaption>bar</figcaption></figure>');
        $captionBeforeImage = $reader->read('<figure><figcaption>bar</figcaption><img src="foo.png" title="voyage"></figure>');
        $noCaption = $reader->read('<figure><img src="foo.png" title="voyage"></figure>');
        $wrappedImage = $reader->read('<figure><p><img src="foo.png" title="voyage"></p><figcaption>bar</figcaption></figure>');
        $richCaption = $reader->read('<figure><img src="foo.png" title="voyage" alt="this is ignored"><figcaption>bar <strong>baz</strong></figcaption></figure>');
        $withList = $reader->read('<figure class="important"><img src="../media/rId25.jpg" /><ul><li>ITEM</li></ul><figcaption>CAP2</figcaption></figure>');

        $figure = $captionAfterImage->children[0] ?? new AstNode('missing');
        $image = $figure->children[0]->children[0] ?? new AstNode('missing');
        $richFigure = $richCaption->children[0] ?? new AstNode('missing');
        $richImage = $richFigure->children[0]->children[0] ?? new AstNode('missing');
        $richCaptionInlines = $richFigure->attr('captionInlines', []);
        $listedFigure = $withList->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($withList);

        $t->same('figure', $figure->type);
        $t->same('bar', $figure->attr('caption'));
        $t->same('plain', $figure->children[0]->type);
        $t->same('image', $image->type);
        $t->same('foo.png', $image->attr('url'));
        $t->same('voyage', $image->attr('title'));
        $t->same([], $image->children, 'Image without alt should keep an empty label inside HTML figure bodies');
        $t->same('bar', $captionBeforeImage->children[0]->attr('caption'));
        $t->same('plain', $captionBeforeImage->children[0]->children[0]->type);
        $t->same('', $noCaption->children[0]->attr('caption'));
        $t->same('paragraph', $wrappedImage->children[0]->children[0]->type);
        $t->same('bar baz', $richFigure->attr('caption'));
        $t->same('text', $richCaptionInlines[0]->type);
        $t->same('bar ', $richCaptionInlines[0]->attr('text'));
        $t->same('strong', $richCaptionInlines[1]->type);
        $t->same('this is ignored', $richImage->attr('alt'));
        $t->same('this is ignored', $richImage->children[0]->attr('text'));
        $t->same(['important'], $listedFigure->attr('classes'));
        $t->same(['plain', 'bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $listedFigure->children));
        $t->same('CAP2', $listedFigure->attr('caption'));
        $t->contains('Figure ( "" , [ "important" ] , [  ] ) (Caption Nothing [ Plain [ Str "CAP2" ]', $native);
        $t->contains('Plain [ Image ( "" , [  ] , [  ] ) [  ] ( "../media/rId25.jpg" , "" ) ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "ITEM" ]', $native);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-figure-caption.html');
        $document = $reader->read($fixture);
        $fixtureFigure = $document->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Figure Import', $document->attr('meta')['title'] ?? '');
        $t->same('release-figure', $fixtureFigure->attr('id'));
        $t->same(['important', 'source-media'], $fixtureFigure->attr('classes'));
        $t->same(['review' => 'keep'], $fixtureFigure->attr('attributes'));
        $t->same('Release frame source', $fixtureFigure->attr('caption'));
        $t->contains('<figure class="wp-block-image important source-media" id="release-figure"><img src="https://example.test/wp-content/uploads/imports/batch-42/release-frame.jpg" alt="Release archive frame" title="Release frame title"/><figcaption><strong>Release</strong> frame <a href="/wp-admin/post.php?post=42&amp;action=edit" title="Edit source">source</a></figcaption></figure>', $blocks);
    },
    'maps upstream html reader native divs main extraction' => static function (TestRunner $t): void {
        $reader = new MarkdownReader(['htmlNativeDivs' => true]);
        $mainOnly = $reader->read('<header>ignore me</header><nav><p>ignore me</p><main>hello</main><footer>ignore me</footer>');
        $roleMain = $reader->read('<main role=foobar>hello</main>');
        $attributedMain = $reader->read('<main id=foo class=bar data-baz=qux>hello</main>');
        $closedParagraph = $reader->read('<p>hello<main>main content</main>');
        $trailingText = $reader->read('<main>main content</main>non-main content');

        $t->same(1, count($mainOnly->children));
        $t->same('paragraph', $mainOnly->children[0]->type);
        $t->same('hello', $mainOnly->children[0]->attr('text'));
        $t->same('div', $roleMain->children[0]->type);
        $t->same(['role' => 'foobar'], $roleMain->children[0]->attr('attributes'));
        $t->same('hello', $roleMain->children[0]->children[0]->attr('text'));
        $t->same('div', $attributedMain->children[0]->type);
        $t->same('foo', $attributedMain->children[0]->attr('id'));
        $t->same(['bar'], $attributedMain->children[0]->attr('classes'));
        $t->same(['baz' => 'qux', 'role' => 'main'], $attributedMain->children[0]->attr('attributes'));
        $t->same(['id' => 'foo', 'class' => 'bar', 'data-baz' => 'qux', 'role' => 'main'], $attributedMain->children[0]->attr('htmlAttributes'));
        $t->same('main content', $closedParagraph->children[0]->attr('text'));
        $t->same('main content', $trailingText->children[0]->attr('text'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-main-native-divs.html');
        $document = $reader->read($fixture);
        $mainDiv = $document->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Main Import', $document->attr('meta')['title'] ?? '');
        $t->same('div', $mainDiv->type);
        $t->same('conversion-body', $mainDiv->attr('id'));
        $t->same(['wp-import'], $mainDiv->attr('classes'));
        $t->same(['source' => 'legacy-export', 'role' => 'main'], $mainDiv->attr('attributes'));
        $t->same('heading', $mainDiv->children[0]->type);
        $t->same('paragraph', $mainDiv->children[1]->type);
        $t->contains('<div id="conversion-body" class="wp-import" data-source="legacy-export" role="main"><h1 id="main-article">Main Article</h1><p>Only the main document body should reach WordPress review.</p></div>', $blocks);
        $t->true(!str_contains($blocks, 'Export header outside'), 'HTML native-div main handoff should drop source header boilerplate');
        $t->true(!str_contains($blocks, 'Navigation outside'), 'HTML native-div main handoff should drop source navigation boilerplate');
        $t->true(!str_contains($blocks, 'Export footer outside'), 'HTML native-div main handoff should drop source footer boilerplate');
    },
    'maps upstream html reader native divs header wrapper' => static function (TestRunner $t): void {
        $reader = new MarkdownReader(['htmlNativeDivs' => true]);
        $standalone = $reader->read('<header id="title">Title</header>');
        $header = $standalone->children[0] ?? new AstNode('missing');

        $t->same('div', $header->type);
        $t->same('title', $header->attr('id'));
        $t->same(['header'], $header->attr('classes'));
        $t->same(['id' => 'title', 'class' => 'header'], $header->attr('htmlAttributes'));
        $t->same('paragraph', $header->children[0]->type);
        $t->same('Title', $header->children[0]->attr('text'));

        $nested = $reader->read('<main><header id="deck" class="source-title" data-review="yes"><h1>Feature</h1><p>Deck</p></header><p>Body</p></main>');
        $nestedHeader = $nested->children[0] ?? new AstNode('missing');
        $t->same('div', $nestedHeader->type);
        $t->same('deck', $nestedHeader->attr('id'));
        $t->same(['source-title', 'header'], $nestedHeader->attr('classes'));
        $t->same(['review' => 'yes'], $nestedHeader->attr('attributes'));
        $t->same(['id' => 'deck', 'class' => 'source-title header', 'data-review' => 'yes'], $nestedHeader->attr('htmlAttributes'));
        $t->same('heading', $nestedHeader->children[0]->type);
        $t->same('paragraph', $nestedHeader->children[1]->type);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-header-native-divs.html');
        $document = $reader->read($fixture);
        $mainDiv = $document->children[0] ?? new AstNode('missing');
        $articleHeader = $mainDiv->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Header Import', $document->attr('meta')['title'] ?? '');
        $t->same('div', $mainDiv->type);
        $t->same('article-body', $mainDiv->attr('id'));
        $t->same('div', $articleHeader->type);
        $t->same('import-title', $articleHeader->attr('id'));
        $t->same(['header'], $articleHeader->attr('classes'));
        $t->same(['review' => 'keep'], $articleHeader->attr('attributes'));
        $t->contains('<div id="article-body" class="wp-import" data-source="legacy-export" role="main"><div id="import-title" class="header" data-review="keep"><h1 id="imported-feature">Imported Feature</h1><p>Deck copy that belongs with the article header.</p></div><p>Article body copy for WordPress review.</p></div>', $blocks);
    },
    'maps upstream html reader native divs section and aside wrappers' => static function (TestRunner $t): void {
        $reader = new MarkdownReader(['htmlNativeDivs' => true]);
        $standalone = $reader->read('<section id="source" class="legacy"><h2 id="source">Source</h2><p>Body</p></section>');
        $section = $standalone->children[0] ?? new AstNode('missing');
        $heading = $section->children[0] ?? new AstNode('missing');

        $t->same('div', $section->type);
        $t->same('source', $section->attr('id'));
        $t->same(['section', 'legacy'], $section->attr('classes'));
        $t->same(['id' => 'source', 'class' => 'section legacy'], $section->attr('htmlAttributes'));
        $t->same('heading', $heading->type);
        $t->same('', $heading->attr('id', ''), 'Heading id matching the native div id should be cleared');
        $t->same('Body', $section->children[1]->attr('text'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-section-aside-native-divs.html');
        $document = $reader->read($fixture);
        $mainDiv = $document->children[0] ?? new AstNode('missing');
        $articleSection = $mainDiv->children[0] ?? new AstNode('missing');
        $reviewAside = $mainDiv->children[1] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Section Aside Import', $document->attr('meta')['title'] ?? '');
        $t->same('div', $mainDiv->type);
        $t->same('article', $mainDiv->attr('id'));
        $t->same(['wp-import'], $mainDiv->attr('classes'));
        $t->same(['source' => 'legacy-export', 'role' => 'main'], $mainDiv->attr('attributes'));
        $t->same('div', $articleSection->type);
        $t->same('release-audit', $articleSection->attr('id'));
        $t->same(['section', 'source-section'], $articleSection->attr('classes'));
        $t->same(['review' => 'keep'], $articleSection->attr('attributes'));
        $t->same('', $articleSection->children[0]->attr('id', ''), 'Section heading id should move to wrapper only once');
        $t->same('div', $reviewAside->type);
        $t->same('migration-note', $reviewAside->attr('id'));
        $t->same(['aside', 'review-note'], $reviewAside->attr('classes'));
        $t->same(['priority' => 'high'], $reviewAside->attr('attributes'));
        $t->contains('<div id="article" class="wp-import" data-source="legacy-export" role="main"><div id="release-audit" class="section source-section" data-review="keep"><h2>Release audit</h2><p>Reviewer section copy belongs in the import packet.</p></div><div id="migration-note" class="aside review-note" data-priority="high"><p>Keep the migration note visible for editors.</p></div></div>', $blocks);
    },
    'maps upstream html reader iframe local resource fallback' => static function (TestRunner $t): void {
        $resources = [
            'https://example.test/imports/embedded-review.html' => [
                'mime' => 'text/html; charset=utf-8',
                'body' => '<!doctype html><html><body><h2>Embedded review</h2><p>Nested <strong>review</strong> content from the source frame.</p></body></html>',
            ],
            'https://example.test/imports/media/release-frame.jpg' => [
                'mime' => 'image/jpeg',
                'body' => '',
            ],
            'https://example.test/imports/legacy-packet.bin' => [
                'mime' => 'application/octet-stream',
                'body' => 'legacy packet',
            ],
        ];
        $reader = new MarkdownReader(['htmlIframeResources' => $resources]);

        $htmlFrame = $reader->read('<iframe src="https://example.test/imports/embedded-review.html"></iframe>')->children[0] ?? new AstNode('missing');
        $imageFrame = $reader->read('<iframe src="https://example.test/imports/media/release-frame.jpg"></iframe>')->children[0] ?? new AstNode('missing');
        $genericFrame = $reader->read('<iframe src="https://example.test/imports/legacy-packet.bin"></iframe>')->children[0] ?? new AstNode('missing');

        $t->same('div', $htmlFrame->type);
        $t->same(['iframe'], $htmlFrame->attr('classes'));
        $t->same(['class' => 'iframe'], $htmlFrame->attr('htmlAttributes'));
        $t->same(['heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $htmlFrame->children));
        $t->same('Embedded review', $htmlFrame->children[0]->attr('text'));
        $t->same('strong', $htmlFrame->children[1]->children[1]->type);
        $t->same('div', $imageFrame->type);
        $t->same('plain', $imageFrame->children[0]->type);
        $t->same('image', $imageFrame->children[0]->children[0]->type);
        $t->same('https://example.test/imports/media/release-frame.jpg', $imageFrame->children[0]->children[0]->attr('url'));
        $t->same([], $imageFrame->children[0]->children[0]->children);
        $t->same('div', $genericFrame->type);
        $t->same(['src' => 'https://example.test/imports/legacy-packet.bin'], $genericFrame->attr('attributes'));
        $t->same([], $genericFrame->children);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-iframe-local-resource.html');
        $document = $reader->read($fixture);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Iframe Import', $document->attr('meta')['title'] ?? '');
        $t->same('Before embedded source frames.', $document->children[0]->attr('text'));
        $t->same(['iframe'], $document->children[1]->attr('classes'));
        $t->same('https://example.test/imports/media/release-frame.jpg', $document->children[2]->children[0]->children[0]->attr('url'));
        $t->same(['src' => 'https://example.test/imports/legacy-packet.bin'], $document->children[3]->attr('attributes'));
        $t->contains('<div class="iframe"><h2 id="embedded-review">Embedded review</h2><p>Nested <strong>review</strong> content from the source frame.</p></div>', $blocks);
        $t->contains('<div class="iframe"><img src="https://example.test/imports/media/release-frame.jpg" alt=""/></div>', $blocks);
    },
    'maps upstream html reader svg fallback when raw html is disabled' => static function (TestRunner $t): void {
        $reader = new MarkdownReader(['htmlRawHtml' => false]);
        $document = $reader->read('<p>Icon <svg id="wp-icon" class="fa-fw source-icon" data-source="batch-42" viewBox="0 0 10 10"><title>Ignored source title</title><path d="M0 0h10v10H0z"></path></svg> approved.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $image = $paragraph->children[1] ?? new AstNode('missing');
        $decodedSvg = base64_decode(substr((string) $image->attr('url', ''), strlen('data:image/svg+xml;base64,')), true);
        $aliasImage = (new MarkdownReader(['rawHtml' => false]))
            ->read('<p><svg class="fa-w-14"><path d="M0 0"></path></svg></p>')
            ->children[0]->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'image', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('Icon ', $paragraph->children[0]->attr('text'));
        $t->same(' approved.', $paragraph->children[2]->attr('text'));
        $t->same('image', $image->type);
        $t->same('wp-icon', $image->attr('id'));
        $t->same(['fa-fw', 'source-icon'], $image->attr('classes'));
        $t->same(['width' => '1em'], $image->attr('attributes'));
        $t->same('', $image->attr('alt'));
        $t->same('', $image->attr('title'));
        $t->same([], $image->children);
        $t->true(str_starts_with((string) $image->attr('url'), 'data:image/svg+xml;base64,'));
        $t->true(is_string($decodedSvg), 'SVG fallback URL should contain base64-encoded SVG source');
        $t->contains('<svg id="wp-icon" class="fa-fw source-icon"', (string) $decodedSvg);
        $t->contains('<path d="M0 0h10v10H0z"', (string) $decodedSvg);
        $t->same('image', $aliasImage->type);
        $t->same(['width' => '1em'], $aliasImage->attr('attributes'));
        $t->contains('Image ( "wp-icon" , [ "fa-fw" , "source-icon" ] , [ ( "width" , "1em" ) ] ) [  ] ( "data:image/svg+xml;base64,', $native);
        $t->contains('<p>Icon <img src="data:image/svg+xml;base64,', $blocks);
        $t->contains('alt="" data-pandoc-width="1em" style="width:1em" id="wp-icon" class="fa-fw source-icon"/> approved.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-svg-disabled-raw-html.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureImage = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML SVG Disabled Raw Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('migration-icon', $fixtureImage->attr('id'));
        $t->same(['fa-fw', 'source-icon'], $fixtureImage->attr('classes'));
        $t->same(['width' => '1em'], $fixtureImage->attr('attributes'));
        $t->contains('<p>Reviewer icon <img src="data:image/svg+xml;base64,', $fixtureBlocks);
        $t->contains('id="migration-icon" class="fa-fw source-icon"/> marks a legacy shortcode.</p>', $fixtureBlocks);
    },
    'maps upstream html reader svg as raw inline when raw html is enabled' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Icon <svg id="wp-icon" class="source-icon" data-source="batch-42" viewBox="0 0 10 10"><title>Source title</title><path d="M0 0h10v10H0z"></path></svg> approved.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $rawSvg = $paragraph->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'raw_html_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('raw_html_inline', $rawSvg->type);
        $t->contains('<svg id="wp-icon" class="source-icon" data-source="batch-42" viewbox="0 0 10 10">', $rawSvg->attr('html'));
        $t->contains('<title>Source title</title>', $rawSvg->attr('html'));
        $t->contains('<path d="M0 0h10v10H0z"></path>', $rawSvg->attr('html'));
        $t->contains('RawInline (Format "html") "<svg id=\\"wp-icon\\" class=\\"source-icon\\" data-source=\\"batch-42\\" viewbox=\\"0 0 10 10\\">', $native);
        $t->contains('<p>Icon <svg id="wp-icon" class="source-icon" data-source="batch-42" viewbox="0 0 10 10"><title>Source title</title><path d="M0 0h10v10H0z"></path></svg> approved.</p>', $blocks);
        $t->true(!str_contains($rawSvg->attr('html'), 'data:image/svg+xml'), 'Raw HTML SVG branch should not use the disabled-raw fallback data image');

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-svg-raw-html.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureRawSvg = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML SVG Raw Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('raw_html_inline', $fixtureRawSvg->type);
        $t->contains('<svg id="migration-icon" class="source-icon" data-source="batch-42" viewbox="0 0 10 10">', $fixtureRawSvg->attr('html'));
        $t->contains('<p>Reviewer raw icon <svg id="migration-icon" class="source-icon" data-source="batch-42" viewbox="0 0 10 10"><title>Migration icon</title><path d="M0 0h10v10H0z"></path></svg> stays raw for source review.</p>', $fixtureBlocks);
        $t->true(!str_contains($fixtureBlocks, 'data:image/svg+xml'), 'WordPress raw SVG handoff should not rewrite the source SVG to a data image');
    },
    'maps upstream html reader style raw inline and script math branches' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Reviewer CSS <style>.legacy{color:red}</style> equation <script type="math/tex">x^2</script> display <script type="math/tex; mode=display">\alpha</script>.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $style = $paragraph->children[1] ?? new AstNode('missing');
        $inlineMath = $paragraph->children[3] ?? new AstNode('missing');
        $displayMath = $paragraph->children[5] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'raw_html_inline', 'text', 'math', 'text', 'math', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<style>.legacy{color:red}</style>', $style->attr('html'));
        $t->same('x^2', $inlineMath->attr('text'));
        $t->same(false, $inlineMath->attr('display'));
        $t->same('\alpha', $displayMath->attr('text'));
        $t->same(true, $displayMath->attr('display'));
        $t->contains('RawInline (Format "html") "<style>.legacy{color:red}</style>"', $native);
        $t->contains('Math InlineMath "x^2"', $native);
        $t->contains('Math DisplayMath "\\\\alpha"', $native);
        $t->contains('<p>Reviewer CSS <style>.legacy{color:red}</style> equation <span class="math inline">\(x^2\)</span> display <span class="math display">\[\alpha\]</span>.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-style-script-inline.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureStyle = $fixtureDocument->children[0]->children[1] ?? new AstNode('missing');
        $fixtureInlineMath = $fixtureDocument->children[1]->children[1] ?? new AstNode('missing');
        $fixtureDisplayMath = $fixtureDocument->children[1]->children[3] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Style Script Inline Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('raw_html_inline', $fixtureStyle->type);
        $t->contains('.legacy-callout { color: #333; }', $fixtureStyle->attr('html'));
        $t->same('math', $fixtureInlineMath->type);
        $t->same(false, $fixtureInlineMath->attr('display'));
        $t->same('math', $fixtureDisplayMath->type);
        $t->same(true, $fixtureDisplayMath->attr('display'));
        $t->contains('<p>Reviewer CSS <style>.legacy-callout { color: #333; }</style> stays visible.</p>', $fixtureBlocks);
        $t->contains('<p>Equation <span class="math inline">\(x^2 + y^2\)</span> and display <span class="math display">\[\alpha + \omega\]</span> stay native.</p>', $fixtureBlocks);
    },
    'maps upstream html reader generic raw inline tags and comments' => static function (TestRunner $t): void {
        $source = '<p>Action <button class="wp-action" data-source="batch-42"><strong>Publish</strong></button> note<!--source:classic--><time datetime="2026-05-24">today</time>.</p>';
        $reader = new MarkdownReader();
        $document = $reader->read($source);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $buttonOpen = $paragraph->children[1] ?? new AstNode('missing');
        $buttonStrong = $paragraph->children[2] ?? new AstNode('missing');
        $buttonClose = $paragraph->children[3] ?? new AstNode('missing');
        $comment = $paragraph->children[5] ?? new AstNode('missing');
        $timeOpen = $paragraph->children[6] ?? new AstNode('missing');
        $timeText = $paragraph->children[7] ?? new AstNode('missing');
        $timeClose = $paragraph->children[8] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'text',
            'raw_html_inline',
            'strong',
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<button class="wp-action" data-source="batch-42">', $buttonOpen->attr('html'));
        $t->same('strong', $buttonStrong->type);
        $t->same('Publish', $buttonStrong->children[0]->attr('text'));
        $t->same('</button>', $buttonClose->attr('html'));
        $t->same('<!--source:classic-->', $comment->attr('html'));
        $t->same('<time datetime="2026-05-24">', $timeOpen->attr('html'));
        $t->same('today', $timeText->attr('text'));
        $t->same('</time>', $timeClose->attr('html'));
        $t->contains('RawInline (Format "html") "<button class=\\"wp-action\\" data-source=\\"batch-42\\">"', $native);
        $t->contains('Strong [ Str "Publish" ]', $native);
        $t->contains('RawInline (Format "html") "<!--source:classic-->"', $native);
        $t->contains('RawInline (Format "html") "<time datetime=\\"2026-05-24\\">"', $native);
        $t->contains('<p>Action <button class="wp-action" data-source="batch-42"><strong>Publish</strong></button> note<!--source:classic--><time datetime="2026-05-24">today</time>.</p>', $blocks);

        $disabledDocument = (new MarkdownReader(['htmlRawHtml' => false]))->read($source);
        $disabledNative = (new NativeWriter(['blocksOnly' => true]))->write($disabledDocument);
        $disabledBlocks = (new WordPressBlockWriter())->write($disabledDocument);

        $t->true(!str_contains($disabledNative, 'RawInline (Format "html")'), 'Disabled raw HTML import should drop generic raw inline tag boundaries and comments');
        $t->contains('Strong [ Str "Publish" ]', $disabledNative);
        $t->contains('<p>Action <strong>Publish</strong> notetoday.</p>', $disabledBlocks);
        $t->true(!str_contains($disabledBlocks, '<button'), 'Disabled raw HTML import should not render unknown inline tag boundaries');
        $t->true(!str_contains($disabledBlocks, '<time'), 'Disabled raw HTML import should not render generic inline tag boundaries');
        $t->true(!str_contains($disabledBlocks, 'source:classic'), 'Disabled raw HTML import should omit raw comments');

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-generic-raw-inline.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureParagraph = $fixtureDocument->children[0] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Generic Raw Inline Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('raw_html_inline', $fixtureParagraph->children[1]->type ?? '');
        $t->same('strong', $fixtureParagraph->children[2]->type ?? '');
        $t->same('raw_html_inline', $fixtureParagraph->children[5]->type ?? '');
        $t->same('raw_html_inline', $fixtureParagraph->children[6]->type ?? '');
        $t->contains('<button class="wp-action" data-source="batch-42"><strong>Publish</strong></button>', $fixtureBlocks);
        $t->contains('<!--source:classic--><time datetime="2026-05-24">today</time>', $fixtureBlocks);
    },
    'maps upstream html reader cite and wbr raw inline fallback' => static function (TestRunner $t): void {
        $source = '<p>Source <cite id="manual" class="source-title" data-source="batch-42"><em>Handbook</em></cite> slug<wbr data-break="slug">tail.</p>';
        $reader = new MarkdownReader();
        $document = $reader->read($source);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $citeOpen = $paragraph->children[1] ?? new AstNode('missing');
        $citeEmph = $paragraph->children[2] ?? new AstNode('missing');
        $citeClose = $paragraph->children[3] ?? new AstNode('missing');
        $wbr = $paragraph->children[5] ?? new AstNode('missing');
        $tail = $paragraph->children[6] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'text',
            'raw_html_inline',
            'emph',
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<cite id="manual" class="source-title" data-source="batch-42">', $citeOpen->attr('html'));
        $t->same('emph', $citeEmph->type);
        $t->same('Handbook', $citeEmph->children[0]->attr('text'));
        $t->same('</cite>', $citeClose->attr('html'));
        $t->same('<wbr data-break="slug">', $wbr->attr('html'));
        $t->same('tail.', $tail->attr('text'));
        $t->contains('RawInline (Format "html") "<cite id=\\"manual\\" class=\\"source-title\\" data-source=\\"batch-42\\">"', $native);
        $t->contains('Emph [ Str "Handbook" ]', $native);
        $t->contains('RawInline (Format "html") "<wbr data-break=\\"slug\\">"', $native);
        $t->contains('<p>Source <cite id="manual" class="source-title" data-source="batch-42"><em>Handbook</em></cite> slug<wbr data-break="slug">tail.</p>', $blocks);

        $disabledDocument = (new MarkdownReader(['htmlRawHtml' => false]))->read($source);
        $disabledNative = (new NativeWriter(['blocksOnly' => true]))->write($disabledDocument);
        $disabledBlocks = (new WordPressBlockWriter())->write($disabledDocument);

        $t->true(!str_contains($disabledNative, 'RawInline (Format "html")'), 'Disabled raw HTML import should drop cite and wbr raw inline boundaries');
        $t->contains('Emph [ Str "Handbook" ]', $disabledNative);
        $t->contains('<p>Source <em>Handbook</em> slugtail.</p>', $disabledBlocks);
        $t->true(!str_contains($disabledBlocks, '<cite'), 'Disabled raw HTML import should not render cite boundaries');
        $t->true(!str_contains($disabledBlocks, '<wbr'), 'Disabled raw HTML import should not render wbr boundaries');

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-cite-wbr-raw-inline.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureParagraph = $fixtureDocument->children[0] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Cite Wbr Raw Inline Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('raw_html_inline', $fixtureParagraph->children[1]->type ?? '');
        $t->same('raw_html_inline', $fixtureParagraph->children[5]->type ?? '');
        $t->contains('<cite data-source="classic-editor">Import Review Handbook</cite>', $fixtureBlocks);
        $t->contains('import-<wbr data-source="slug-break">packet remains readable.', $fixtureBlocks);
    },
    'maps upstream html reader math renderer spans as skipped visual output' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Equation <script type="math/tex">x^2</script><span class="mjx-chtml"><span>rendered duplicate</span></span> and<span class="MathJax_Preview">preview duplicate</span><span class="MathJax_CHTML">visual duplicate</span> done.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $math = $paragraph->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'math', 'text', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('x^2', $math->attr('text'));
        $t->same('Equation x^2 and done.', $paragraph->attr('text'));
        $t->contains('Math InlineMath "x^2"', $native);
        $t->true(!str_contains($native, 'mjx-chtml'), 'MathJax visual renderer span should be skipped instead of imported as a generic span');
        $t->true(!str_contains($native, 'MathJax_CHTML'), 'MathJax CHTML visual renderer span should be skipped');
        $t->true(!str_contains($blocks, 'rendered duplicate'), 'WordPress handoff should not duplicate MathJax visual text');
        $t->true(!str_contains($blocks, 'preview duplicate'), 'WordPress handoff should not duplicate MathJax preview text');
        $t->contains('<p>Equation <span class="math inline">\(x^2\)</span> and done.</p>', $blocks);

        $katexDocument = $reader->read('<p>KaTeX <script type="math/tex; mode=display">\alpha</script><span class="katex-html"><span>visual duplicate</span></span> after.</p>');
        $katexParagraph = $katexDocument->children[0] ?? new AstNode('missing');
        $katexBlocks = (new WordPressBlockWriter())->write($katexDocument);

        $t->same(['text', 'math', 'text'], array_map(static fn (AstNode $node): string => $node->type, $katexParagraph->children));
        $t->same(true, $katexParagraph->children[1]->attr('display'));
        $t->true(!str_contains($katexBlocks, 'visual duplicate'), 'KaTeX visual renderer span should be skipped');
        $t->contains('<p>KaTeX <span class="math display">\[\alpha\]</span> after.</p>', $katexBlocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-math-renderer-spans.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Math Renderer Span Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('Inline equation x^2 + y^2 stays single.', $fixtureDocument->children[0]->attr('text'));
        $t->same('Display equation \\alpha + \\omega stays single.', $fixtureDocument->children[1]->attr('text'));
        $t->same('Legacy preview falls back to source text.', $fixtureDocument->children[2]->attr('text'));
        $t->contains('<p>Inline equation <span class="math inline">\(x^2 + y^2\)</span> stays single.</p>', $fixtureBlocks);
        $t->contains('<p>Display equation <span class="math display">\[\alpha + \omega\]</span> stays single.</p>', $fixtureBlocks);
        $t->true(!str_contains($fixtureBlocks, 'rendered duplicate'), 'Fixture handoff should skip MathJax rendered duplicate text');
        $t->true(!str_contains($fixtureBlocks, 'KaTeX duplicate'), 'Fixture handoff should skip KaTeX rendered duplicate text');
    },
    'maps upstream html reader mathml tex annotations and assistive spans' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(<<<'HTML'
<p>Inline <math><semantics><mrow><msup><mi>x</mi><mn>2</mn></msup></mrow><annotation encoding="application/x-tex">x^2</annotation></semantics></math> and fallback <math data-source="mathml-only"><mrow><mi>y</mi><mo>+</mo><mn>1</mn></mrow></math>.</p>
<p><span class="MJX_Assistive_MathML"><math display="block"><semantics><mrow><mi>E</mi><mo>=</mo><mi>m</mi><msup><mi>c</mi><mn>2</mn></msup></mrow><annotation encoding="application/x-tex">E=mc^2</annotation></semantics></math></span></p>
HTML);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $inlineMath = $paragraph->children[1] ?? new AstNode('missing');
        $fallback = $paragraph->children[3] ?? new AstNode('missing');
        $displayMath = $document->children[1]->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['text', 'math', 'text', 'span', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('math', $inlineMath->type);
        $t->same(false, $inlineMath->attr('display'));
        $t->same('x^2', $inlineMath->attr('text'));
        $t->same('span', $fallback->type);
        $t->same(['math'], $fallback->attr('classes'));
        $t->same(['source' => 'mathml-only'], $fallback->attr('attributes'));
        $t->same('y+1', $fallback->children[0]->attr('text'));
        $t->same('math', $displayMath->type);
        $t->same(true, $displayMath->attr('display'));
        $t->same('E=mc^2', $displayMath->attr('text'));
        $t->contains('Math InlineMath "x^2"', $native);
        $t->contains('Math DisplayMath "E=mc^2"', $native);
        $t->true(!str_contains($native, 'MJX_Assistive_MathML'), 'Assistive MathML wrapper should be unwrapped instead of retained as a generic span');
        $t->contains('<span class="math inline">\(x^2\)</span>', $blocks);
        $t->contains('data-source="mathml-only"', $blocks);
        $t->contains('<span class="math display">\[E=mc^2\]</span>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-mathml-annotation.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML MathML Annotation Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('Inline equation: x^2 and fallback: y+1.', $fixtureDocument->children[0]->attr('text'));
        $t->same('Assistive source: E=mc^2', $fixtureDocument->children[1]->attr('text'));
        $t->contains('<p>Inline equation: <span class="math inline">\(x^2\)</span> and fallback:', $fixtureBlocks);
        $t->contains('data-source="mathml-only"', $fixtureBlocks);
        $t->contains('<p>Assistive source: <span class="math display">\[E=mc^2\]</span></p>', $fixtureBlocks);
    },
    'maps upstream html reader span class strikeout branch' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read('<p>Migration <span class="strikeout">remove <strong>legacy</strong></span> before publish.</p>');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $strikeout = $paragraph->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'strikeout', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $strikeout->children));
        $t->same('remove ', $strikeout->children[0]->attr('text'));
        $t->same('legacy', $strikeout->children[1]->children[0]->attr('text'));
        $t->contains('Strikeout [ Str "remove" , Space , Strong [ Str "legacy" ] ]', $native);
        $t->contains('<p>Migration <del>remove <strong>legacy</strong></del> before publish.</p>', $blocks);
        $t->true(!str_contains($native, 'Span ( "" , [ "strikeout" ]'), 'Exact strikeout span should map to native Strikeout instead of generic Span');

        $editDocument = $reader->read('<p><del>old caption</del> and <ins>new caption</ins></p>');
        $editParagraph = $editDocument->children[0] ?? new AstNode('missing');

        $t->same(['strikeout', 'text', 'underline'], array_map(static fn (AstNode $node): string => $node->type, $editParagraph->children));
        $t->same('old caption', $editParagraph->children[0]->children[0]->attr('text'));
        $t->same('new caption', $editParagraph->children[2]->children[0]->attr('text'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-span-strikeout.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureParagraph = $fixtureDocument->children[0] ?? new AstNode('missing');
        $fixtureStrikeout = $fixtureParagraph->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Span Strikeout Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('strikeout', $fixtureStrikeout->type);
        $t->same('remove the legacy shortcode', $fixtureStrikeout->children[0]->attr('text'));
        $t->contains('<p>Migration note <del>remove the legacy shortcode</del> before publish.</p>', $fixtureBlocks);
        $t->contains('<p>Explicit markup <del>old caption</del> and <u>new caption</u> remain reviewable.</p>', $fixtureBlocks);
    },
    'maps upstream html reader disabled raw html skip for style script and textarea' => static function (TestRunner $t): void {
        $reader = new MarkdownReader(['htmlRawHtml' => false]);
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-raw-disabled-skip.html');
        $document = $reader->read($fixture);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $mathBlock = $document->children[1] ?? new AstNode('missing');
        $math = $mathBlock->children[0] ?? new AstNode('missing');
        $after = $document->children[2] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Raw Disabled Import', $document->attr('meta')['title'] ?? '');
        $t->same(['paragraph', 'plain', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('Inline style and script text remains.', $paragraph->attr('text'));
        $t->same('plain', $mathBlock->type);
        $t->same('math', $math->type);
        $t->same(true, $math->attr('display'));
        $t->same('\alpha + \omega', $math->attr('text'));
        $t->same('After raw payloads.', $after->attr('text'));
        $t->true(!str_contains($native, 'RawBlock (Format "html")'), 'Disabled raw HTML import should not keep raw HTML blocks');
        $t->true(!str_contains($native, 'RawInline (Format "html")'), 'Disabled raw HTML import should not keep raw HTML inlines');
        $t->contains('Math DisplayMath "\\\\alpha + \\\\omega"', $native);
        $t->contains('<p>Inline style and script text remains.</p>', $blocks);
        $t->contains('<p><span class="math display">\[\alpha + \omega\]</span></p>', $blocks);
        $t->contains('<p>After raw payloads.</p>', $blocks);
        $t->true(!str_contains($blocks, '<style'), 'Disabled raw HTML WordPress handoff should not emit style tags');
        $t->true(!str_contains($blocks, '<script'), 'Disabled raw HTML WordPress handoff should not emit generic script tags');
        $t->true(!str_contains($blocks, '<textarea'), 'Disabled raw HTML WordPress handoff should not emit textarea tags');
    },
    'maps upstream html reader style elements as raw html inlines in full documents' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(implode("\n", [
            '<!doctype html>',
            '<html>',
            '<body>',
            '<p>Before stylesheet.</p>',
            '<style id="legacy-theme-css" data-source="batch-42">',
            '.legacy-card { border: 1px solid #ddd; }',
            '</style>',
            '<p>After stylesheet.</p>',
            '</body>',
            '</html>',
        ]));
        $styleParagraph = $document->children[1] ?? new AstNode('missing');
        $raw = $styleParagraph->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('Before stylesheet.', $document->children[0]->attr('text'));
        $t->same('raw_html_inline', $raw->type);
        $t->contains('<style id="legacy-theme-css" data-source="batch-42">', $raw->attr('html'));
        $t->contains('.legacy-card { border: 1px solid #ddd; }', $raw->attr('html'));
        $t->contains('</style>', $raw->attr('html'));
        $t->same('After stylesheet.', $document->children[2]->attr('text'));
        $t->contains('Para [ RawInline (Format "html") "<style id=\\"legacy-theme-css\\" data-source=\\"batch-42\\">\\n.legacy-card { border: 1px solid #ddd; }\\n</style>" ]', $native);
        $t->contains('<p><style id="legacy-theme-css" data-source="batch-42">', $blocks);
        $t->contains('</style></p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-style-raw-block.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureStyleParagraph = $fixtureDocument->children[1] ?? new AstNode('missing');
        $fixtureRaw = $fixtureStyleParagraph->children[0] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Style Raw Block Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('raw_html_inline', $fixtureRaw->type);
        $t->contains('.legacy-card .caption { font-style: italic; }', $fixtureRaw->attr('html'));
        $t->contains('<p>Before migration stylesheet.</p>', $fixtureBlocks);
        $t->contains('<p><style id="legacy-theme-css" data-source="batch-42">', $fixtureBlocks);
        $t->contains('<p>After migration stylesheet.</p>', $fixtureBlocks);
    },
    'maps upstream html reader script elements as raw html blocks in full documents' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(implode("\n", [
            '<!doctype html>',
            '<html>',
            '<body>',
            '<p>Before script.</p>',
            '<script id="legacy-widget-js" type="text/javascript" data-source="batch-42">',
            'document.write("This *should not* become emphasis");',
            '</script>',
            '<p>After script.</p>',
            '</body>',
            '</html>',
        ]));
        $raw = $document->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['paragraph', 'raw_html', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('Before script.', $document->children[0]->attr('text'));
        $t->same('raw_html', $raw->type);
        $t->contains('<script id="legacy-widget-js" type="text/javascript" data-source="batch-42">', $raw->attr('html'));
        $t->contains('document.write("This *should not* become emphasis");', $raw->attr('html'));
        $t->contains('</script>', $raw->attr('html'));
        $t->same('After script.', $document->children[2]->attr('text'));
        $t->contains('RawBlock (Format "html") "<script id=\\"legacy-widget-js\\" type=\\"text/javascript\\" data-source=\\"batch-42\\">', $native);
        $t->contains('<!-- wp:html -->' . "\n" . '<script id="legacy-widget-js" type="text/javascript" data-source="batch-42">', $blocks);
        $t->contains('</script>' . "\n" . '<!-- /wp:html -->', $blocks);
        $t->true(!str_contains($blocks, '<p><script'), 'Full HTML script blocks should not be wrapped in paragraph markup');
        $t->true(!str_contains($blocks, '<em>should not</em>'), 'Script contents should stay raw instead of being parsed as Markdown');

        $mathDocument = $reader->read(implode("\n", [
            '<!doctype html>',
            '<html>',
            '<body>',
            '<script type="math/tex; mode=display">\alpha</script>',
            '</body>',
            '</html>',
        ]));
        $mathBlock = $mathDocument->children[0] ?? new AstNode('missing');
        $math = $mathBlock->children[0] ?? new AstNode('missing');

        $t->same('plain', $mathBlock->type);
        $t->same('math', $math->type);
        $t->same(true, $math->attr('display'));
        $t->same('\alpha', $math->attr('text'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-script-raw-block.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureRaw = $fixtureDocument->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Script Raw Block Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('raw_html', $fixtureRaw->type);
        $t->contains('window.LegacyWidgetQueue.push', $fixtureRaw->attr('html'));
        $t->contains('<p>Before migration script.</p>', $fixtureBlocks);
        $t->contains('<script id="legacy-widget-js" type="text/javascript" data-source="batch-42">', $fixtureBlocks);
        $t->contains('<p>After migration script.</p>', $fixtureBlocks);
    },
    'maps upstream html reader textarea as raw html block' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $document = $reader->read(implode("\n", [
            '<textarea id="legacy-packet" class="source-payload" data-source="batch-42">',
            'Legacy shortcode [gallery ids="10,11"] and review notes stay literal.',
            '</textarea>',
            '',
            'After the legacy packet.',
        ]));
        $raw = $document->children[0] ?? new AstNode('missing');
        $paragraph = $document->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('raw_html', $raw->type);
        $t->same(implode("\n", [
            '<textarea id="legacy-packet" class="source-payload" data-source="batch-42">',
            'Legacy shortcode [gallery ids="10,11"] and review notes stay literal.',
            '</textarea>',
        ]), $raw->attr('html'));
        $t->same('paragraph', $paragraph->type);
        $t->same('After the legacy packet.', $paragraph->attr('text'));
        $t->contains('RawBlock (Format "html") "<textarea id=\\"legacy-packet\\" class=\\"source-payload\\" data-source=\\"batch-42\\">\\nLegacy shortcode [gallery ids=\\"10,11\\"] and review notes stay literal.\\n</textarea>"', $native);
        $t->contains('<!-- wp:html -->' . "\n" . '<textarea id="legacy-packet" class="source-payload" data-source="batch-42">', $blocks);
        $t->contains('Legacy shortcode [gallery ids="10,11"] and review notes stay literal.', $blocks);
        $t->contains('</textarea>' . "\n" . '<!-- /wp:html -->', $blocks);
        $t->contains('<p>After the legacy packet.</p>', $blocks);

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-textarea-raw-block.html');
        $fixtureDocument = $reader->read($fixture);
        $fixtureRaw = $fixtureDocument->children[1] ?? new AstNode('missing');
        $fixtureBlocks = (new WordPressBlockWriter())->write($fixtureDocument);

        $t->same('HTML Textarea Raw Import', $fixtureDocument->attr('meta')['title'] ?? '');
        $t->same('Before the legacy packet.', $fixtureDocument->children[0]->attr('text'));
        $t->same('raw_html', $fixtureRaw->type);
        $t->contains('<textarea id="legacy-packet" class="source-payload" data-source="batch-42">', $fixtureRaw->attr('html'));
        $t->contains('Legacy shortcode [gallery ids="10,11"] and review notes stay literal.', $fixtureRaw->attr('html'));
        $t->same('After the legacy packet.', $fixtureDocument->children[2]->attr('text'));
        $t->contains('<p>Before the legacy packet.</p>', $fixtureBlocks);
        $t->contains('<textarea id="legacy-packet" class="source-payload" data-source="batch-42">', $fixtureBlocks);
        $t->contains('<p>After the legacy packet.</p>', $fixtureBlocks);
    },
    'maps upstream html reader doc-noteref anchors into native notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-doc-noteref-footnotes.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $note = $paragraph->children[1] ?? new AstNode('missing');
        $noteParagraph = $note->children[0] ?? new AstNode('missing');
        $strong = $noteParagraph->children[1] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Doc Noteref Import', $document->attr('meta')['title'] ?? '');
        $t->same(1, count($document->children));
        $t->same('paragraph', $paragraph->type);
        $t->same(['text', 'note', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('Legacy review note', $paragraph->children[0]->attr('text'));
        $t->same(' stays attached.', $paragraph->children[2]->attr('text'));
        $t->same('note', $note->type);
        $t->same(1, count($note->children));
        $t->same('paragraph', $noteParagraph->type);
        $t->same(['text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $noteParagraph->children));
        $t->same('source context', $strong->children[0]->attr('text'));
        $t->contains('Note [ Para [ Str "Editor" , Space , Str "note" , Space , Str "with" , Space , Strong [ Str "source" , Space , Str "context" ] , Str "." ]', $native);
        $t->contains('<p>Legacy review note<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> stays attached.</p>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p>Editor note with <strong>source context</strong>.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li></ol></section>', $blocks);
        $t->true(!str_contains($blocks, 'footnote-back'), 'Original HTML footnote backlink should not be duplicated in native note output');
        $t->true(!str_contains($blocks, 'id="footnotes"'), 'Original doc-endnotes container should be replaced by native WordPress footnotes');
    },
    'maps upstream html reader doc-noteref placement through table captions and cells' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-doc-noteref-table-placement.html');
        $document = (new MarkdownReader())->read($fixture);
        $table = $document->children[2] ?? new AstNode('missing');
        $captionInlines = $table->attr('captionInlines', []);
        $headCell = $table->children[0]->children[0]->children[0] ?? new AstNode('missing');
        $bodyCell = $table->children[1]->children[0]->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Doc Noteref Table Placement Import', $document->attr('meta')['title'] ?? '');
        $t->same(['heading', 'paragraph', 'table', 'heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('table', $table->type);
        $t->same('Sample table.caption footnote', $table->attr('caption'));
        $t->same(['text', 'note'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same('note', $headCell->children[1]->type);
        $t->same('note', $bodyCell->children[1]->type);
        $t->contains('Caption Nothing [ Plain [ Str "Sample" , Space , Str "table." , Note', $native);
        $t->same('header footnote', $headCell->children[1]->children[0]->attr('text'));
        $t->same('table cell footnote', $bodyCell->children[1]->children[0]->attr('text'));
        $t->contains('<p>hello<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Sample table.<sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup></figcaption>', $blocks);
        $t->contains('<th style="text-align:center">Fruit<sup id="fnref-3"><a href="#fn-3" role="doc-noteref">3</a></sup></th>', $blocks);
        $t->contains('<td style="text-align:center">Bans<sup id="fnref-4"><a href="#fn-4" role="doc-noteref">4</a></sup></td>', $blocks);
        $t->contains('<p>dolly<sup id="fnref-5"><a href="#fn-5" role="doc-noteref">5</a></sup></p>', $blocks);
        $t->contains('<li id="fn-1"><p>doc footnote</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li><li id="fn-2"><p>caption footnote</p> <a href="#fnref-2" aria-label="Back to content">Back</a></li><li id="fn-3"><p>header footnote</p> <a href="#fnref-3" aria-label="Back to content">Back</a></li><li id="fn-4"><p>table cell footnote</p> <a href="#fnref-4" aria-label="Back to content">Back</a></li><li id="fn-5"><p>doc footnote</p> <a href="#fnref-5" aria-label="Back to content">Back</a></li>', $blocks);
        $t->true(!str_contains($blocks, 'footnotes-end-of-document'), 'Original Pandoc footnote section should be replaced by native WordPress footnotes');
        $t->true(!str_contains($blocks, 'footnote-back'), 'Original upstream footnote backlinks should not leak into the WordPress table handoff');
    },
    'maps upstream html reader inline code aliases and semantic classes' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $code = $reader->read('<code>Answer is 42</code>')->children[0]->children[0] ?? new AstNode('missing');
        $tt = $reader->read('<tt>Answer is 42</tt>')->children[0]->children[0] ?? new AstNode('missing');
        $samp = $reader->read('<samp>Answer is 42</samp>')->children[0]->children[0] ?? new AstNode('missing');
        $var = $reader->read('<var>result</var>')->children[0]->children[0] ?? new AstNode('missing');

        $t->same('code', $code->type);
        $t->same('Answer is 42', $code->attr('text'));
        $t->same([], $code->attr('classes', []));
        $t->same('code', $tt->type);
        $t->same('Answer is 42', $tt->attr('text'));
        $t->same([], $tt->attr('classes', []));
        $t->same('code', $samp->type);
        $t->same('Answer is 42', $samp->attr('text'));
        $t->same(['sample'], $samp->attr('classes'));
        $t->same(['class' => 'sample'], $samp->attr('htmlAttributes'));
        $t->same('code', $var->type);
        $t->same('result', $var->attr('text'));
        $t->same(['variable'], $var->attr('classes'));
        $t->same(['class' => 'variable'], $var->attr('htmlAttributes'));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-inline-code-aliases.html');
        $document = $reader->read($fixture);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $codes = array_values(array_filter(
            $paragraph->children,
            static fn (AstNode $node): bool => $node->type === 'code'
        ));
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('HTML Inline Code Import', $document->attr('meta')['title'] ?? '');
        $t->same(4, count($codes));
        $t->same('core/image', $codes[0]->attr('text'));
        $t->same('[gallery ids="4,5"]', $codes[1]->attr('text'));
        $t->same(['sample'], $codes[2]->attr('classes'));
        $t->same('Missing alt text', $codes[2]->attr('text'));
        $t->same(['variable'], $codes[3]->attr('classes'));
        $t->same('post_title', $codes[3]->attr('text'));
        $t->contains('<p>Reviewer diagnostics: <code>core/image</code>, <code>[gallery ids=&quot;4,5&quot;]</code>, <code class="sample">Missing alt text</code>, and <code class="variable">post_title</code>.</p>', $blocks);
    },
    'maps upstream html reader base href media urls into absolute import links' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $html = static fn (string $base, string $src): string => implode("\n", [
            '<!doctype html>',
            '<html>',
            '<head><base href="' . $base . '"></head>',
            '<body><p><img src="' . $src . '" alt="Stickman"></p></body>',
            '</html>',
        ]);
        $imageUrl = static function (AstNode $document): string {
            $image = $document->children[0]->children[0] ?? new AstNode('missing');

            return (string) $image->attr('url', '');
        };

        $t->same('http://www.w3schools.com/images/stickman.gif', $imageUrl($reader->read($html('http://www.w3schools.com/images/foo', 'stickman.gif'))));
        $t->same('http://www.w3schools.com/images/stickman.gif', $imageUrl($reader->read($html('http://www.w3schools.com/images/', 'stickman.gif'))));
        $t->same('http://www.w3schools.com/stickman.gif', $imageUrl($reader->read($html('http://www.w3schools.com/images/', '/stickman.gif'))));
        $t->same('http://example.com/stickman.gif', $imageUrl($reader->read($html('http://www.w3schools.com/images/', 'http://example.com/stickman.gif'))));

        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-base-media.html');
        $document = $reader->read($fixture);
        $blocks = (new WordPressBlockWriter())->write($document);
        $standaloneImage = $document->children[0]->children[0] ?? new AstNode('missing');
        $reviewLink = $document->children[1]->children[1] ?? new AstNode('missing');
        $inlineImage = $document->children[2]->children[1] ?? new AstNode('missing');

        $t->same('https://example.test/wp-content/uploads/imports/batch-42/release-frame.jpg', $standaloneImage->attr('url'));
        $t->same('https://example.test/wp-content/uploads/imports/audit/post.html', $reviewLink->attr('url'));
        $t->same('https://example.test/wp-content/uploads/thumb.jpg', $inlineImage->attr('url'));
        $t->contains('<figure class="wp-block-image"><img src="https://example.test/wp-content/uploads/imports/batch-42/release-frame.jpg" alt="Release frame" title="Release frame title"/><figcaption>Release frame</figcaption></figure>', $blocks);
        $t->contains('<p>Review <a href="https://example.test/wp-content/uploads/imports/audit/post.html" title="Audit packet">source packet</a> before publishing.</p>', $blocks);
        $t->contains('<p>Root media <img src="https://example.test/wp-content/uploads/thumb.jpg" alt="thumbnail"/> stays absolute to the site.</p>', $blocks);
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
    'maps upstream native writer full document metadata and inline constructors' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '% Native Packet',
            '% Data Team; Reviewer',
            '% May 24, 2026',
            '',
            '# Native Review {#native-review .handoff}',
            '',
            'Reviewer **strong** note with [source](https://example.test/source "Source title") and `code`.',
            '',
            '---',
        ]));
        $native = (new NativeWriter(['standalone' => true]))->write($document);

        $t->contains('Pandoc', $native);
        $t->contains('Meta { unMeta = fromList', $native);
        $t->contains('( "author" , MetaList [ MetaInlines [ Str "Data" , Space , Str "Team" ] , MetaInlines [ Str "Reviewer" ] ] )', $native);
        $t->contains('( "date" , MetaInlines [ Str "May" , Space , Str "24," , Space , Str "2026" ] )', $native);
        $t->contains('( "title" , MetaInlines [ Str "Native" , Space , Str "Packet" ] )', $native);
        $t->contains('Header 1 ( "native-review" , [ "handoff" ] , [  ] ) [ Str "Native" , Space , Str "Review" ]', $native);
        $t->contains('Strong [ Str "strong" ]', $native);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "source" ] ( "https://example.test/source" , "Source title" )', $native);
        $t->contains('Code ( "" , [  ] , [  ] ) "code"', $native);
        $t->contains('HorizontalRule', $native);
    },
    'maps upstream native writer block-list round trip boundary for reviewer packets' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [new AstNode('text', ['text' => $text])]);
        $document = new AstNode('document', [], [
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('plain', [], [$text('Confirm media captions')]),
                ]),
            ]),
            new AstNode('ordered_list', ['start' => 3, 'style' => 'lower_alpha', 'delimiter' => 'two_parens'], [
                new AstNode('list_item', [], [
                    $paragraph('Queue editorial review'),
                ]),
            ]),
            new AstNode('blockquote', [], [
                $paragraph('Nested reviewer note'),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', ['text' => 'Reviewer stanza']),
                new AstNode('line', ['text' => '  keep indentation']),
            ]),
            new AstNode('code_block', [
                'text' => "wp_update_post(\$post_id);\nclean_post_cache(\$post_id);",
                'classes' => ['php'],
                'attributes' => ['data-source' => 'batch-42'],
            ]),
        ]);
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);

        $t->true(!str_starts_with($native, 'Pandoc'), 'Block-list native writer mode should omit the Pandoc wrapper');
        $t->contains('BulletList [ [ Plain [ Str "Confirm" , Space , Str "media" , Space , Str "captions" ]', $native);
        $t->contains('OrderedList ( 3 , LowerAlpha , TwoParens )', $native);
        $t->contains('Para [ Str "Queue" , Space , Str "editorial" , Space , Str "review" ]', $native);
        $t->contains('BlockQuote [ Para [ Str "Nested" , Space , Str "reviewer" , Space , Str "note" ]', $native);
        $t->contains('LineBlock [ [ Str "Reviewer" , Space , Str "stanza" ] , [ Space , Str "keep" , Space , Str "indentation" ] ]', $native);
        $t->contains('CodeBlock ( "" , [ "php" ] , [ ( "data-source" , "batch-42" ) ] ) "wp_update_post($post_id);\\nclean_post_cache($post_id);"', $native);
    },
    'maps upstream native writer figure and citation constructors' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [], [
            new AstNode('figure', [
                'id' => 'fig-release',
                'classes' => ['wp-review'],
                'attributes' => ['data-source' => 'batch-42'],
                'caption' => 'Release frame',
                'shortCaption' => 'review short',
            ], [
                new AstNode('image', [
                    'id' => 'release-image',
                    'classes' => ['review-media'],
                    'attributes' => ['data-source' => 'batch-42'],
                    'url' => 'https://example.test/uploads/release.jpg',
                    'title' => 'Release archive',
                    'alt' => 'Release frame',
                ], [$text('Release frame')]),
            ]),
            new AstNode('paragraph', [], [
                $text('Citation packet '),
                new AstNode('citation', [
                    'citations' => [
                        [
                            'id' => 'doe',
                            'mode' => 'author_in_text',
                            'suffix' => [$text('p. 7')],
                            'noteNum' => 4,
                        ],
                        [
                            'id' => 'roe',
                            'mode' => 'suppress_author',
                            'prefix' => [$text('see')],
                            'suffix' => 'ch. 2',
                            'noteNum' => 4,
                        ],
                    ],
                ], [$text('@doe [p. 7; see -@roe ch. 2]')]),
                $text(' stays attached.'),
            ]),
        ]);

        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);

        $t->contains('Figure ( "fig-release" , [ "wp-review" ] , [ ( "data-source" , "batch-42" ) ] ) (Caption (Just [ Str "review" , Space , Str "short" ]) [ Plain [ Str "Release" , Space , Str "frame" ]', $native);
        $t->contains('Image ( "release-image" , [ "review-media" ] , [ ( "data-source" , "batch-42" ) ] ) [ Str "Release" , Space , Str "frame" ] ( "https://example.test/uploads/release.jpg" , "Release archive" )', $native);
        $t->contains('Cite [ Citation { citationId = "doe" , citationPrefix = [] , citationSuffix = [ Str "p." , Space , Str "7" ] , citationMode = AuthorInText , citationNoteNum = 4 , citationHash = 0 } , Citation { citationId = "roe" , citationPrefix = [ Str "see" ] , citationSuffix = [ Str "ch." , Space , Str "2" ] , citationMode = SuppressAuthor , citationNoteNum = 4 , citationHash = 0 } ] [ Str "@doe" , Space , Str "[p." , Space , Str "7;" , Space , Str "see" , Space , Str "-@roe" , Space , Str "ch." , Space , Str "2]" ]', $native);
    },
    'maps upstream native writer table constructors' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
        $row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'id' => 'review-table',
                'classes' => ['wp-review-table'],
                'attributes' => ['source' => 'batch-42'],
                'alignments' => ['left', 'right'],
                'widths' => [0.4, 0.6],
                'caption' => 'Migration table',
                'shortCaption' => 'audit short',
            ], [
                new AstNode('table_head', ['id' => 'thead-review'], [
                    $row([
                        $cell([$text('Field')], ['header' => true]),
                        $cell([$text('Result')], ['header' => true]),
                    ]),
                ]),
                new AstNode('table_body', [
                    'id' => 'tbody-review',
                    'rowHeadColumns' => 1,
                    'headRows' => [
                        $row([
                            $cell([$text('Batch')], ['header' => true]),
                            $cell([$text('Owner')], ['header' => true]),
                        ]),
                    ],
                ], [
                    $row([
                        $cell([$text('All imports')], [
                            'align' => 'center',
                            'colspan' => 2,
                            'attributes' => ['data-cell' => 'summary'],
                        ]),
                    ]),
                    $row([
                        $cell([$text('Posts')], ['header' => true]),
                        $cell([
                            new AstNode('paragraph', [], [$text('Ready for review')]),
                            new AstNode('bullet_list', [], [
                                new AstNode('list_item', [], [$text('media')]),
                            ]),
                        ]),
                    ]),
                ]),
                new AstNode('table_foot', ['id' => 'tfoot-review'], [
                    $row([
                        $cell([$text('Total')]),
                        $cell([$text('49')]),
                    ]),
                ]),
            ]),
        ]);

        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);

        $t->contains('Table ( "review-table" , [ "wp-review-table" ] , [ ( "source" , "batch-42" ) ] ) (Caption (Just [ Str "audit" , Space , Str "short" ]) [ Plain [ Str "Migration" , Space , Str "table" ]', $native);
        $t->contains('[ ( AlignLeft , ColWidth 0.4 ) , ( AlignRight , ColWidth 0.6 ) ]', $native);
        $t->contains('TableHead ( "thead-review" , [  ] , [  ] ) [ Row ( "" , [  ] , [  ] ) [ Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) [ Plain [ Str "Field"', $native);
        $t->contains('TableBody ( "tbody-review" , [  ] , [  ] ) (RowHeadColumns 1) [ Row ( "" , [  ] , [  ] ) [ Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) [ Plain [ Str "Batch"', $native);
        $t->contains('Cell ( "" , [  ] , [ ( "data-cell" , "summary" ) ] ) AlignCenter (RowSpan 1) (ColSpan 2) [ Plain [ Str "All" , Space , Str "imports"', $native);
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) [ Para [ Str "Ready" , Space , Str "for" , Space , Str "review"', $native);
        $t->contains('BulletList [ [ Plain [ Str "media" ]', $native);
        $t->contains('TableFoot ( "tfoot-review" , [  ] , [  ] ) [ Row ( "" , [  ] , [  ] ) [ Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) [ Plain [ Str "Total"', $native);
    },
    'maps upstream native writer read round trip property boundary' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'Native Reader Packet',
                'author' => ['Data Team', 'Reviewer'],
                'date' => 'May 24, 2026',
                'batch' => 'wp-native-42',
                'ready' => true,
                'reviewers' => ['content', 'media'],
            ],
        ], [
            new AstNode('heading', ['level' => 2, 'id' => 'native-reader', 'classes' => ['handoff']], [
                $text('Native Reader'),
            ]),
            new AstNode('paragraph', [], [
                $text('Preserve '),
                new AstNode('strong', [], [$text('review')]),
                $text(' link '),
                new AstNode('link', ['url' => 'https://example.test/source/post-42', 'title' => 'Source archive'], [
                    $text('legacy post'),
                ]),
                $text(' and '),
                new AstNode('code', ['text' => 'wp_insert_post']),
                $text('.'),
            ]),
            new AstNode('ordered_list', ['start' => 4, 'style' => 'upper_roman', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [
                    new AstNode('paragraph', [], [$text('Queue editorial review')]),
                ]),
            ]),
            new AstNode('code_block', [
                'text' => "wp_update_post(\$post_id);\nclean_post_cache(\$post_id);",
                'classes' => ['php'],
                'attributes' => ['data-source' => 'batch-42'],
            ]),
        ]);

        $native = (new NativeWriter(['standalone' => true]))->write($document);
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['standalone' => true]))->write($parsed);
        $meta = $parsed->attr('meta');

        $t->same($native, $roundTrip);
        $t->same('Native Reader Packet', $meta['title']);
        $t->same(['Data Team', 'Reviewer'], $meta['author']);
        $t->same('May 24, 2026', $meta['date']);
        $t->same(true, $meta['ready']);
        $t->same('MetaList', $meta['reviewers']['type']);
        $t->same('heading', $parsed->children[0]->type);
        $t->same('native-reader', $parsed->children[0]->attr('id'));
        $t->same('ordered_list', $parsed->children[2]->type);
        $t->same('upper_roman', $parsed->children[2]->attr('style'));
        $t->same('one_paren', $parsed->children[2]->attr('delimiter'));
        $t->same('wp_update_post($post_id);' . "\n" . 'clean_post_cache($post_id);', $parsed->children[3]->attr('text'));
    },
    'reads native figure citation and table packets for wordpress handoff' => static function (TestRunner $t): void {
        $text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
        $cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
        $row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
        $document = new AstNode('document', [], [
            new AstNode('figure', [
                'id' => 'fig-release',
                'classes' => ['wp-review'],
                'caption' => 'Release frame',
                'shortCaption' => 'review short',
            ], [
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/release.jpg',
                    'title' => 'Release archive',
                    'alt' => 'Release frame',
                ], [$text('Release frame')]),
            ]),
            new AstNode('paragraph', [], [
                $text('Citation packet '),
                new AstNode('citation', [
                    'citations' => [
                        [
                            'id' => 'doe',
                            'mode' => 'author_in_text',
                            'suffix' => [$text('p. 7')],
                            'noteNum' => 4,
                        ],
                        [
                            'id' => 'roe',
                            'mode' => 'suppress_author',
                            'prefix' => [$text('see')],
                            'suffix' => 'ch. 2',
                            'noteNum' => 4,
                        ],
                    ],
                ], [$text('@doe [p. 7; see -@roe ch. 2]')]),
                $text(' stays attached.'),
            ]),
            new AstNode('table', [
                'id' => 'migration-review-table',
                'classes' => ['review-table'],
                'alignments' => ['left', 'right'],
                'widths' => [0.4, 0.6],
                'caption' => 'Migration review table',
                'shortCaption' => 'review table',
            ], [
                new AstNode('table_head', [], [
                    $row([
                        $cell([$text('Field')]),
                        $cell([$text('Review note')]),
                    ]),
                ]),
                new AstNode('table_body', [
                    'rowHeadColumns' => 1,
                    'headRows' => [
                        $row([
                            $cell([$text('Batch')]),
                            $cell([$text('Owner')]),
                        ]),
                    ],
                ], [
                    $row([
                        $cell([$text('All imports')], ['colspan' => 2, 'align' => 'center']),
                    ]),
                    $row([
                        $cell([$text('Posts')]),
                        $cell([
                            new AstNode('paragraph', [], [$text('Confirm block conversion before publish.')]),
                            new AstNode('bullet_list', [], [
                                new AstNode('list_item', [], [$text('media captions')]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $parsed = (new NativeReader())->read($native);
        $table = $parsed->children[2];
        $citation = null;
        foreach ($parsed->children[1]->children as $inline) {
            if ($inline->type === 'citation' || $inline->type === 'citation_group') {
                $citation = $inline;
                break;
            }
        }
        $blocks = (new WordPressBlockWriter())->write($parsed);

        $t->same($native, (new NativeWriter(['blocksOnly' => true]))->write($parsed));
        $t->same('figure', $parsed->children[0]->type);
        $t->same('image', $parsed->children[0]->children[0]->type);
        $t->same('Release frame', $parsed->children[0]->attr('caption'));
        $t->true($citation instanceof AstNode, 'Native reader should preserve the Cite inline node');
        $t->same('citation_group', $citation->type);
        $t->same('author_in_text', $citation->children[0]->attr('mode'));
        $t->same('suppress_author', $citation->children[1]->attr('mode'));
        $t->same('table', $table->type);
        $t->same(['left', 'right'], $table->attr('alignments'));
        $t->same([0.4, 0.6], $table->attr('widths'));
        $t->same(1, $table->children[1]->attr('rowHeadColumns'));
        $t->same(1, count($table->children[1]->attr('headRows')));
        $t->same(2, $table->children[1]->children[0]->children[0]->attr('colspan'));
        $t->contains('<figure class="wp-block-image wp-review" id="fig-release" data-pandoc-short-caption="review short"><img src="https://example.test/uploads/release.jpg" alt="Release frame" title="Release archive"/><figcaption>Release frame</figcaption></figure>', $blocks);
        $t->contains('<p>Citation packet <span class="pandoc-citation" data-pandoc-citation-count="2"', $blocks);
        $t->contains('>@doe [p. 7; see -@roe ch. 2]</span> stays attached.</p>', $blocks);
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="review table"><table id="migration-review-table" class="review-table"><colgroup>', $blocks);
        $t->contains('<td colspan="2" style="text-align:center">All imports</td>', $blocks);
        $t->contains('<li>media captions</li>', $blocks);
    },
    'maps upstream native html row head columns into wordpress table headers' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-html-row-header-table.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $table = $parsed->children[1];
        $body = $table->children[1];
        $firstBodyRow = $body->children[0];
        $secondBodyRow = $body->children[1];

        $t->same('Row headers', $parsed->children[0]->attr('text'));
        $t->same('table', $table->type);
        $t->same(['default', 'default', 'default'], $table->attr('alignments'));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(null, $firstBodyRow->children[0]->attr('header'), 'Native RowHeadColumns should drive row headers without per-cell header attrs');
        $t->same('1', $firstBodyRow->children[0]->children[0]->attr('text'));
        $t->same('2', $firstBodyRow->children[1]->children[0]->attr('text'));
        $t->same('4', $secondBodyRow->children[0]->children[0]->attr('text'));
        $t->contains('TableBody ( "" , [  ] , [  ] ) (RowHeadColumns 1)', $roundTrip);
        $t->contains('<thead><tr><th>X</th><th>Y</th><th>Z</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><th>1</th><td>2</td><td>3</td></tr><tr><th>4</th><td>5</td><td>6</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, '<tbody><tr><td>1</td>'), 'WordPress table handoff should not downgrade Native row headers to data cells');
    },
    'maps upstream native markdown citation packets into wordpress metadata spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-markdown-citations-slice.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $list = $parsed->children[1];
        $normalCitation = $list->children[0]->children[0]->children[0];
        $authorCitation = $list->children[1]->children[0]->children[0];
        $groupCitation = $list->children[2]->children[0]->children[0];
        $note = $list->children[3]->children[0]->children[5];
        $noteCitation = $note->children[0]->children[0];

        $t->same('heading', $parsed->children[0]->type);
        $t->same('pandoc-with-citeproc-hs', $parsed->children[0]->attr('id'));
        $t->same('bullet_list', $list->type);
        $t->same('normal', $normalCitation->attr('citations')[0]['mode']);
        $t->same('nonexistent', $normalCitation->attr('citations')[0]['id']);
        $t->same('author_in_text', $authorCitation->attr('citations')[0]['mode']);
        $t->same('p.' . "\xC2\xA0" . '30', $authorCitation->attr('citations')[0]['suffix'][0]->attr('text'));
        $t->same(3, count($groupCitation->children));
        $t->same('suppress_author', $groupCitation->children[1]->attr('mode'));
        $t->same('пункт3', $groupCitation->children[2]->attr('id'));
        $t->same('see', $groupCitation->children[2]->attr('prefix')[0]->attr('text'));
        $t->same('note', $note->type);
        $t->same('пункт3', $noteCitation->attr('citations')[0]['id']);
        $t->contains('Cite [ Citation { citationId = "\1087\1091\1085\1082\1090\&3"', $roundTrip);
        $t->contains('data-pandoc-citation-id="nonexistent"', $blocks);
        $t->contains('data-pandoc-citation-id="item1"', $blocks);
        $t->contains('data-pandoc-citation-count="3"', $blocks);
        $t->contains('data-pandoc-citation-ids="[&quot;item1&quot;,&quot;item2&quot;,&quot;пункт3&quot;]"', $blocks);
        $t->contains('&quot;mode&quot;:&quot;suppress_author&quot;', $blocks);
        $t->contains('&quot;prefix&quot;:&quot;see also&quot;', $blocks);
        $t->contains('&quot;suffix&quot;:&quot;p. 30&quot;', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p><span class="pandoc-citation" data-pandoc-citation-id="пункт3"', $blocks);
        $t->contains('>@пункт3 [p. 12]</span> and a citation without locators <span class="pandoc-citation" data-pandoc-citation-id="пункт3"', $blocks);
    },
    'maps upstream native string numeric escape separators' => static function (TestRunner $t): void {
        $nbsp = "\xC2\xA0";
        $native = <<<'NATIVE'
[ Para [ Str "M.A.\160\&2007" ]
, Para [ Str "Batch\160\&42" ]
]
NATIVE;

        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);

        $t->same('M.A.' . $nbsp . '2007', $parsed->children[0]->children[0]->attr('text'));
        $t->same('Batch' . $nbsp . '42', $parsed->children[1]->children[0]->attr('text'));
        $t->contains('Str "M.A.\160\&2007"', $roundTrip);
        $t->contains('Str "Batch\160\&42"', $roundTrip);
        $t->true(!str_contains($roundTrip, '\1602007'), 'Native writer should terminate numeric escapes before following digits');
        $t->true(!str_contains($roundTrip, '\16042'), 'Native writer should terminate numeric escapes before source batch IDs');
        $t->contains('<p>Batch' . $nbsp . '42</p>', $blocks);
    },
    'maps upstream command empty paragraphs into opt-in wordpress output' => static function (TestRunner $t): void {
        $commandFixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-empty-paragraphs.md');
        $native = '[Para [Str "hi"], Para [], Para [], Para [Str "lo"]]';
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $defaultBlocks = (new WordPressBlockWriter())->write($parsed);
        $preservedBlocks = (new WordPressBlockWriter(['preserveEmptyParagraphs' => true]))->write($parsed);

        $t->contains('% pandoc -f native -t html5', $commandFixture);
        $t->contains('% pandoc -f native -t html5+empty_paragraphs', $commandFixture);
        $t->contains("<p>hi</p>\n<p>lo</p>", $commandFixture);
        $t->contains("<p>hi</p>\n<p></p>\n<p></p>\n<p>lo</p>", $commandFixture);
        $t->same(4, count($parsed->children));
        $t->same([], $parsed->children[1]->children);
        $t->same([], $parsed->children[2]->children);
        $t->contains('Para [  ]', $roundTrip);
        $t->contains('<p>hi</p>', $defaultBlocks);
        $t->contains('<p>lo</p>', $defaultBlocks);
        $t->true(!str_contains($defaultBlocks, '<p></p>'), 'Default WordPress output should match Pandoc html5 by dropping empty paragraphs');
        $t->same(2, substr_count($preservedBlocks, '<p></p>'));
        $t->same(4, substr_count($preservedBlocks, '<!-- wp:paragraph -->'));
        $t->contains("<p>hi</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p></p>", $preservedBlocks);
    },
    'maps upstream native odt continued lists without empty wordpress paragraphs' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-list-continuation.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $listStarts = [];
        foreach ($parsed->children as $node) {
            if ($node->type === 'ordered_list') {
                $listStarts[] = $node->attr('start');
            }
        }

        $t->same(17, count($parsed->children));
        $t->same([1, 2, 4, 1, 2], $listStarts);
        $t->same('paragraph', $parsed->children[1]->type);
        $t->same([], $parsed->children[1]->children);
        $t->contains('OrderedList ( 4 , Decimal , Period )', $roundTrip);
        $t->contains('Para [  ]', $roundTrip);
        $t->contains('<ol><li>Some text (1.)</li></ol>', $blocks);
        $t->contains('<ol start="2"><li>Some text (2.)</li><li>Some text (3.)</li></ol>', $blocks);
        $t->contains('<ol start="4"><li>Some text (4.)</li></ol>', $blocks);
        $t->contains('<p>Some text before starting new list from 1.</p>', $blocks);
        $t->same(4, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->true(!str_contains($blocks, '<p></p>'), 'ODT Native empty paragraph separators should not become empty WordPress paragraph blocks');
    },
    'maps upstream native odt nested continued lists with source marker metadata' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-list-continuation-nested.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $reviewBlocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($parsed);
        $listStarts = [];
        $nestedLists = [];
        $emptySeparators = 0;
        foreach ($parsed->children as $node) {
            if ($node->type === 'ordered_list') {
                $listStarts[] = $node->attr('start');
                $nested = $node->children[0]->children[1] ?? null;
                if ($nested instanceof AstNode) {
                    $nestedLists[] = $nested;
                }
                continue;
            }
            if ($node->type === 'paragraph' && $node->children === []) {
                $emptySeparators++;
            }
        }

        $t->same(9, count($parsed->children));
        $t->same([1, 2, 3], $listStarts);
        $t->same(4, $emptySeparators);
        $t->same('Some text in between.', $parsed->children[2]->attr('text'));
        $t->same('Some text in between.', $parsed->children[6]->attr('text'));
        $t->same(3, count($nestedLists));
        $t->same(['lower_alpha', 'lower_alpha', 'lower_alpha'], array_map(static fn (AstNode $node): string => (string) $node->attr('style'), $nestedLists));
        $t->same(['period', 'period', 'period'], array_map(static fn (AstNode $node): string => (string) $node->attr('delimiter'), $nestedLists));
        $t->same('Sub item 2.b', $nestedLists[1]->children[1]->children[0]->attr('text'));
        $t->contains('OrderedList ( 2 , Decimal , Period )', $roundTrip);
        $t->contains('OrderedList ( 3 , Decimal , Period )', $roundTrip);
        $t->same(3, substr_count($roundTrip, 'OrderedList ( 1 , LowerAlpha , Period )'));
        $t->contains('<ol><li>Top one<ol type="a"><li>Sub item 1.a</li><li>Sub item 1.b</li></ol></li></ol>', $blocks);
        $t->contains('<p>Some text in between.</p>', $blocks);
        $t->contains('<ol start="2"><li>Top two<ol type="a"><li>Sub item 2.a</li><li>Sub item 2.b</li></ol></li></ol>', $blocks);
        $t->contains('<ol start="3"><li>Top three<ol type="a"><li>Sub item 3.a</li><li>Sub item 3.b</li></ol></li></ol>', $blocks);
        $t->contains('<ol type="a" data-pandoc-list-style="lower_alpha" data-pandoc-list-delimiter="period"><li>Sub item 1.a</li><li>Sub item 1.b</li></ol>', $reviewBlocks);
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->true(!str_contains($blocks, 'data-pandoc-list-delimiter'), 'WordPress default output should not add review-only nested list delimiter metadata');
        $t->true(!str_contains($blocks, '<p></p>'), 'ODT Native nested list separators should not become empty WordPress paragraph blocks');
    },
    'maps upstream native odt mixed list styles with optional wordpress source markers' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-ordered-list-mixed.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['standalone' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $reviewBlocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($parsed);
        $topList = $parsed->children[0];
        $nestedDecimal = $topList->children[2]->children[1];
        $nestedAlpha = $nestedDecimal->children[0]->children[1];
        $continuedList = $parsed->children[2];

        $t->same(3, count($parsed->children));
        $t->same('ordered_list', $topList->type);
        $t->same('decimal', $topList->attr('style'));
        $t->same('period', $topList->attr('delimiter'));
        $t->same(5, count($topList->children));
        $t->same('ordered_list', $nestedDecimal->type);
        $t->same('decimal', $nestedDecimal->attr('style'));
        $t->same('ordered_list', $nestedAlpha->type);
        $t->same('lower_alpha', $nestedAlpha->attr('style'));
        $t->same('one_paren', $nestedAlpha->attr('delimiter'));
        $t->same('And', $nestedAlpha->children[0]->children[0]->children[0]->attr('text'));
        $t->same('another!', $nestedAlpha->children[0]->children[0]->children[2]->attr('text'));
        $t->same('ordered_list', $continuedList->type);
        $t->same(4, $continuedList->attr('start'));
        $t->contains('OrderedList ( 1 , LowerAlpha , OneParen )', $roundTrip);
        $t->contains('OrderedList ( 4 , Decimal , Period )', $roundTrip);
        $t->contains('<ol type="a"><li>And another!</li><li>It&#039;s great up here!</li></ol>', $blocks);
        $t->true(!str_contains($blocks, 'data-pandoc-list-delimiter'), 'WordPress default output should not add review-only list delimiter metadata');
        $t->contains('<ol type="a" data-pandoc-list-style="lower_alpha" data-pandoc-list-delimiter="one_paren"><li>And another!</li><li>It&#039;s great up here!</li></ol>', $reviewBlocks);
        $t->contains('<ol start="4"><li>Start new list, but a different starting point.</li><li>Because we can.</li></ol>', $reviewBlocks);
    },
    'maps upstream native odt image captions into wordpress figures' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-image-with-caption.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $figure = $parsed->children[0];
        $image = $figure->children[0];
        $captionInlines = $figure->attr('captionInlines');
        $imageAttrs = $image->attr('attributes');

        $t->same(1, count($parsed->children));
        $t->same('figure', $figure->type);
        $t->same('Image caption', $figure->attr('caption'));
        $t->true(is_array($captionInlines), 'ODT image caption should retain parsed Native caption inlines');
        $t->same('Image', $captionInlines[0]->attr('text'));
        $t->same('caption', $captionInlines[2]->attr('text'));
        $t->same('image', $image->type);
        $t->same('Pictures/10000000000000FA000000FAD6A15225.jpg', $image->attr('url'));
        $t->same('Abbildung 1: Image caption', $image->attr('alt'));
        $t->same('5.292cm', $imageAttrs['width']);
        $t->same('5.292cm', $imageAttrs['height']);
        $t->contains('Caption Nothing [ Plain [ Str "Image" , Space , Str "caption"', $roundTrip);
        $t->contains('( "height" , "5.292cm" ) , ( "width" , "5.292cm" )', $roundTrip);
        $t->contains('<figure class="wp-block-image"><img src="Pictures/10000000000000FA000000FAD6A15225.jpg" alt="Abbildung 1: Image caption" data-pandoc-width="5.292cm" data-pandoc-height="5.292cm" style="width:5.292cm; height:5.292cm"/><figcaption>Image caption</figcaption></figure>', $blocks);
        $t->true(!str_contains($blocks, '<figcaption>Abbildung 1:'), 'WordPress figure captions should use the Native caption, not the image alt label');
    },
    'maps upstream native odt table spans into wordpress table cells' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-table-with-spans.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $table = $parsed->children[0];
        $head = $table->children[0];
        $body = $table->children[1];

        $t->same(2, count($parsed->children));
        $t->same('table', $table->type);
        $t->same('paragraph', $parsed->children[1]->type);
        $t->same([], $parsed->children[1]->children);
        $t->same(2, count($head->children));
        $t->same(2, $head->children[0]->children[0]->attr('rowspan'));
        $t->same(2, $head->children[1]->children[0]->attr('colspan'));
        $t->same(5, count($body->children));
        $t->same(3, $body->children[0]->children[2]->attr('rowspan'));
        $t->same(2, $body->children[2]->children[0]->attr('colspan'));
        $t->same(2, $body->children[3]->children[1]->attr('rowspan'));
        $t->same(2, $body->children[3]->children[1]->attr('colspan'));
        $t->contains('(RowSpan 3)', $roundTrip);
        $t->contains('(ColSpan 2)', $roundTrip);
        $t->contains('<thead><tr><th rowspan="2">H1 Rowspan 2</th><th>H1-2</th><th>H1-3</th></tr><tr><th colspan="2">H2-2/3</th></tr></thead>', $blocks);
        $t->contains('<td>B1-1</td><td>B1-2</td><td rowspan="3">Rowspan 3</td>', $blocks);
        $t->contains('<td colspan="2">Columnspan 2</td>', $blocks);
        $t->contains('<td colspan="2" rowspan="2">Columnspan &amp; Rowspan 2</td>', $blocks);
        $t->true(!str_contains($blocks, '<p></p>'), 'ODT Native empty trailing paragraphs should not become empty WordPress paragraph blocks');
        $t->true(!str_contains($blocks, '<colgroup>'), 'ODT default-width table spans should not invent a colgroup');
    },
    'maps upstream native odt multiple header rows into wordpress thead' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-simple-table-multiple-header-rows.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $table = $parsed->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $rowTexts = static fn (AstNode $row): array => array_map(
            static fn (AstNode $cell): string => (string) ($cell->children[0]->attr('text', '') ?? ''),
            $row->children
        );

        $t->same(2, count($parsed->children));
        $t->same('table', $table->type);
        $t->same([null, null, null], $table->attr('widths'));
        $t->same(2, count($head->children));
        $t->same(['A', 'B', 'C'], $rowTexts($head->children[0]));
        $t->same(['I', 'II', 'II'], $rowTexts($head->children[1]));
        $t->same(3, count($body->children));
        $t->same(['1', '', ''], $rowTexts($body->children[0]));
        $t->same(['2', '', ''], $rowTexts($body->children[1]));
        $t->same(['3', '', ''], $rowTexts($body->children[2]));
        $t->contains('( AlignDefault , ColWidthDefault )', $roundTrip);
        $t->contains('<thead><tr><th>A</th><th>B</th><th>C</th></tr><tr><th>I</th><th>II</th><th>II</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td>1</td><td></td><td></td></tr><tr><td>2</td><td></td><td></td></tr><tr><td>3</td><td></td><td></td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, '<colgroup>'), 'ODT ColWidthDefault table should not invent a WordPress colgroup');
        $t->true(!str_contains($blocks, '<p></p>'), 'ODT trailing empty Para should not become an empty WordPress paragraph block');
    },
    'maps upstream native odt reference anchors into wordpress-safe fragments' => static function (TestRunner $t): void {
        $textNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-reference-to-text.native');
        $listNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-reference-to-list-item.native');
        $textParsed = (new NativeReader())->read($textNative);
        $listParsed = (new NativeReader())->read($listNative);
        $textRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($textParsed);
        $listRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($listParsed);
        $blocks = (new WordPressBlockWriter())->write(new AstNode(
            'document',
            [],
            array_merge($textParsed->children, $listParsed->children)
        ));
        $sourceAnchor = $textParsed->children[0]->children[0];
        $textReference = $textParsed->children[1]->children[6];
        $lineBreak = $textParsed->children[2]->children[3];
        $tailAnchor = $textParsed->children[2]->children[13];
        $list = $listParsed->children[0];
        $listAnchor = $list->children[0]->children[0]->children[0];
        $listReference = $listParsed->children[1]->children[10];

        $t->same('span', $sourceAnchor->type);
        $t->same('an anchor', $sourceAnchor->attr('id'));
        $t->same('link', $textReference->type);
        $t->same('#an anchor', $textReference->attr('url'));
        $t->same('linebreak', $lineBreak->type);
        $t->same('anchor', $tailAnchor->attr('id'));
        $t->same('ordered_list', $list->type);
        $t->same('anchor', $listAnchor->attr('id'));
        $t->same('#anchor', $listReference->attr('url'));
        $t->same([], $listParsed->children[2]->children);
        $t->contains('Span ( "an anchor" , [  ] , [  ] ) [  ]', $textRoundTrip);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "Some" , Space , Str "text" ] ( "#an anchor" , "" )', $textRoundTrip);
        $t->contains('Span ( "anchor" , [  ] , [  ] ) [  ]', $listRoundTrip);
        $t->contains('<span id="an-anchor" data-pandoc-source-id="an anchor"></span>Some text.', $blocks);
        $t->contains('<a href="#an-anchor" data-pandoc-source-href="#an anchor">Some text</a>', $blocks);
        $t->contains('Some text<br/>Another one with a link<span id="anchor"></span>', $blocks);
        $t->contains('<ol><li><span id="anchor"></span>A list item</li><li>Another list item</li></ol>', $blocks);
        $t->contains('<a href="#anchor">1.</a>', $blocks);
        $t->true(!str_contains($blocks, '<span id="an anchor"'), 'WordPress output should not emit a raw whitespace-containing anchor id');
        $t->true(!str_contains($blocks, '<a href="#an anchor"'), 'WordPress output should not emit a raw whitespace-containing fragment href');
        $t->true(!str_contains($blocks, '<p></p>'), 'ODT Native empty reference separators should not become empty WordPress paragraph blocks');
    },
    'maps upstream native epub section ids into wordpress html handoff' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-epub-section-slice.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['standalone' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($parsed);
        $meta = $parsed->attr('meta');
        $cover = $parsed->children[0]->children[0];
        $frontMarker = $parsed->children[1]->children[0];
        $frontmatter = $parsed->children[2];
        $bodymatter = $parsed->children[3];
        $chapter = $bodymatter->children[0];

        $t->same('The Waste Land', $meta['title']);
        $t->same('2011-09-01', $meta['date']);
        $t->same('image', $cover->type);
        $t->same('wasteland-cover.jpg', $cover->attr('url'));
        $t->same('span', $frontMarker->type);
        $t->same('wasteland-content.xhtml', $frontMarker->attr('id'));
        $t->same('wasteland-content.xhtml_frontmatter', $frontmatter->attr('id'));
        $t->same(['section', 'frontmatter'], $frontmatter->attr('classes'));
        $t->same('wasteland-content.xhtml_bodymatter', $bodymatter->attr('id'));
        $t->same(['section', 'bodymatter'], $bodymatter->attr('classes'));
        $t->same('wasteland-content.xhtml_ch1', $chapter->attr('id'));
        $t->same(['section'], $chapter->attr('classes'));
        $t->contains('( "author" , MetaInlines [ Str "T.S. Eliot" ] )', $roundTrip);
        $t->contains('Div ( "wasteland-content.xhtml_bodymatter" , [ "section" , "bodymatter" ] , [  ] )', $roundTrip);
        $t->contains('<dt data-pandoc-meta-key="title">title</dt><dd><span>The Waste Land</span></dd>', $blocks);
        $t->contains('<figure class="wp-block-image"><img src="wasteland-cover.jpg" alt=""/></figure>', $blocks);
        $t->contains('<span id="wasteland-content.xhtml"></span>', $blocks);
        $t->contains('<div id="wasteland-content.xhtml_frontmatter" class="section frontmatter"></div>', $blocks);
        $t->contains('<div id="wasteland-content.xhtml_bodymatter" class="section bodymatter"><div id="wasteland-content.xhtml_ch1" class="section"><h2>I. THE BURIAL OF THE DEAD</h2></div></div>', $blocks);
    },
    'maps upstream native epub display math without inline downgrade' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-epub-math-slice.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['standalone' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($parsed);
        $meta = $parsed->attr('meta');
        $frontMarker = $parsed->children[0]->children[0];
        $mathSection = $parsed->children[1];
        $requiredCase = $mathSection->children[1];
        $optionalCase = $mathSection->children[2];
        $displayLine = $requiredCase->children[1];
        $displayMath = [$displayLine->children[0], $displayLine->children[2], $displayLine->children[4]];
        $inlineMath = $optionalCase->children[1]->children[0];

        $t->same('EPUBTEST 0100 - Reflowable Content Tests', $meta['title']);
        $t->same('content-mathml-001.xhtml', $frontMarker->attr('id'));
        $t->same('content-mathml-001.xhtml_mathml', $mathSection->attr('id'));
        $t->same(['section'], $mathSection->attr('classes'));
        $t->same('content-mathml-001.xhtml_mathml-010', $requiredCase->attr('id'));
        $t->same(['section', 'ctest'], $requiredCase->attr('classes'));
        $t->same('content-mathml-001.xhtml_mathml-023', $optionalCase->attr('id'));
        $t->same(['section', 'otest'], $optionalCase->attr('classes'));
        $t->same(['math', 'math', 'math'], array_map(static fn (AstNode $node): string => $node->type, $displayMath));
        $t->same([true, true, true], array_map(static fn (AstNode $node): bool => $node->attr('display') === true, $displayMath));
        $t->contains('\\int_{- \\infty}^{\\infty}', $displayMath[0]->attr('text'));
        $t->contains('\\sum\\limits_{n = 1}^{\\infty}', $displayMath[1]->attr('text'));
        $t->contains('\\frac{- b \\pm \\sqrt{b^{2} - 4ac}}{2a}', $displayMath[2]->attr('text'));
        $t->same('math', $inlineMath->type);
        $t->same(false, $inlineMath->attr('display'));
        $t->same('{2x}{+ y - z}', $inlineMath->attr('text'));
        $t->same(3, substr_count($roundTrip, 'Math DisplayMath'));
        $t->same(1, substr_count($roundTrip, 'Math InlineMath'));
        $t->contains('Math DisplayMath "\\\\int_{- \\\\infty}^{\\\\infty}e^{- x^{2}}\\\\, dx = \\\\sqrt{\\\\pi}"', $roundTrip);
        $t->contains('Math InlineMath "{2x}{+ y - z}"', $roundTrip);
        $t->same(3, substr_count($blocks, 'class="math display"'));
        $t->same(1, substr_count($blocks, 'class="math inline"'));
        $t->contains('<span class="math display">\\[\\int_{- \\infty}^{\\infty}e^{- x^{2}}\\, dx = \\sqrt{\\pi}\\]</span>', $blocks);
        $t->contains('<span class="math inline">\\({2x}{+ y - z}\\)</span>', $blocks);
        $t->contains('<div id="content-mathml-001.xhtml_mathml" class="section">', $blocks);
        $t->contains('<div id="content-mathml-001.xhtml_mathml-010" class="section ctest">', $blocks);
        $t->contains('<div id="content-mathml-001.xhtml_mathml-023" class="section otest">', $blocks);
    },
    'maps upstream native epub default ordered list style without coercion' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-epub-default-list-style.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['standalone' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($parsed);
        $meta = $parsed->attr('meta');
        $frontMarker = $parsed->children[0]->children[0];
        $section = $parsed->children[1];
        $list = $section->children[1];
        $legacyList = new AstNode('document', [], [
            new AstNode('ordered_list', ['start' => 1], [
                new AstNode('list_item', [], [new AstNode('plain', [], [new AstNode('text', ['text' => 'legacy'])])]),
            ]),
        ]);
        $legacyRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($legacyList);

        $t->same('EPUBTEST 0101 - Styling Tests', $meta['title']);
        $t->same('front.xhtml', $frontMarker->attr('id'));
        $t->same('div', $section->type);
        $t->same(['section'], $section->attr('classes'));
        $t->same('ordered_list', $list->type);
        $t->same(1, $list->attr('start'));
        $t->same('default', $list->attr('style'));
        $t->same('default', $list->attr('delimiter'));
        $t->same('.', $list->children[0]->children[0]->children[0]->attr('text'));
        $t->contains('OrderedList ( 1 , DefaultStyle , DefaultDelim )', $roundTrip);
        $t->true(!str_contains($roundTrip, 'OrderedList ( 1 , Decimal , Period ) [ [ Plain [ Str "." ]'), 'EPUB default list style should not be coerced to Decimal/Period');
        $t->contains('OrderedList ( 1 , Decimal , Period )', $legacyRoundTrip);
        $t->contains('<dt data-pandoc-meta-key="title">title</dt><dd><span>EPUBTEST 0101 - Styling Tests</span></dd>', $blocks);
        $t->contains('<span id="front.xhtml"></span>', $blocks);
        $t->contains('<div class="section"><h1>EPUB 3 Styling Test Document: 0101</h1><ol><li>.</li></ol></div>', $blocks);
        $t->true(!str_contains($blocks, '<ol type='), 'DefaultStyle ordered lists should not emit a concrete HTML type attribute');
    },
    'maps upstream native structural fixture with parenthesized table sections' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-structure-slice.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);

        $t->same(4, count($parsed->children));
        $t->same('definition_list', $parsed->children[0]->type);
        $t->same('apple', $parsed->children[0]->children[0]->attr('term'));
        $t->same('code_block', $parsed->children[0]->children[0]->children[1]->children[1]->type);
        $t->same('blockquote', $parsed->children[0]->children[0]->children[1]->children[2]->type);
        $t->same('div', $parsed->children[1]->type);
        $t->same('review-wrapper', $parsed->children[1]->attr('id'));
        $t->same(['wp-import-review'], $parsed->children[1]->attr('classes'));
        $t->same('raw_html', $parsed->children[1]->children[2]->type);
        $t->same('raw_block', $parsed->children[2]->type);
        $t->same('openxml', $parsed->children[2]->attr('format'));
        $t->same('table', $parsed->children[3]->type);
        $t->same('table_head', $parsed->children[3]->children[0]->type);
        $t->same('Field', $parsed->children[3]->children[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->contains('(TableHead', $roundTrip);
        $t->contains('(TableFoot', $roundTrip);
        $t->contains('<dl><dt><em>apple</em></dt><dd>red fruit<pre class="wp-block-code"><code>{ orange code block }</code></pre><blockquote><p>orange block quote</p></blockquote></dd><dt>orange</dt><dd>orange fruit</dd><dd>bank</dd></dl>', $blocks);
        $t->contains('<div id="review-wrapper" class="wp-import-review" data-source="batch-42"><div><div><p>foo</p></div></div><div><p>bar</p></div><!-- Comment --></div>', $blocks);
        $t->contains('<figure class="wp-block-table"><table id="review-table"><thead><tr><th>Field</th><th>Status</th></tr></thead><tbody><tr><td>Media</td><td>Ready</td></tr></tbody></table></figure>', $blocks);
    },
    'maps upstream native docx parenthesized pandoc meta wrapper' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-inline-formatting.native');
        $parsed = (new NativeReader())->read($native);
        $blocks = (new WordPressBlockWriter())->write($parsed);

        $t->same(5, count($parsed->children));
        $t->same(['nativeFormat' => 'pandoc-native-text'], $parsed->attrs);
        $t->same('emph', $parsed->children[0]->children[4]->type);
        $t->same('italics', $parsed->children[0]->children[4]->children[0]->attr('text'));
        $t->same('strong', $parsed->children[0]->children[6]->type);
        $t->same('emph', $parsed->children[0]->children[6]->children[2]->type);
        $t->same('small_caps', $parsed->children[1]->children[4]->type);
        $t->same('strikeout', $parsed->children[1]->children[13]->type);
        $t->same('underline', $parsed->children[2]->children[6]->type);
        $t->same('superscript', $parsed->children[3]->children[8]->type);
        $t->same('subscript', $parsed->children[3]->children[20]->type);
        $t->same('linebreak', $parsed->children[4]->children[3]->type);
        $t->contains('<p>Regular text <em>italics</em> <strong>bold <em>bold italics</em></strong>.</p>', $blocks);
        $t->contains('<span style="font-variant:small-caps">Small Caps</span>', $blocks);
        $t->contains('<del>strikethrough</del>', $blocks);
        $t->contains('<u>single underlines for <em>emphasis</em></u>', $blocks);
        $t->contains('<sup>superscript</sup>', $blocks);
        $t->contains('<sub>subscript</sub>', $blocks);
        $t->contains("A line<br/>break.", $blocks);
    },
    'maps upstream native docx task list glyphs into opt-in wordpress checkboxes' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-task-list.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $defaultBlocks = (new WordPressBlockWriter())->write($parsed);
        $blocks = (new WordPressBlockWriter(['taskGlyphsAsCheckboxes' => true]))->write($parsed);
        $list = $parsed->children[0];
        $firstItemParagraph = $list->children[0]->children[0];
        $nestedTask = $list->children[2]->children[1]->children[0]->children[0];

        $t->same(1, count($parsed->children));
        $t->same('bullet_list', $list->type);
        $t->same('paragraph', $firstItemParagraph->type);
        $t->same("\u{2610}", $firstItemParagraph->children[0]->attr('text'));
        $t->same("\u{2612}", $list->children[1]->children[0]->children[0]->attr('text'));
        $t->same('plain', $nestedTask->type);
        $t->same("\u{2612}", $nestedTask->children[0]->attr('text'));
        $t->contains('Str "\\9744"', $roundTrip);
        $t->contains('Str "\\9746"', $roundTrip);
        $t->contains('<ul><li>' . "\u{2610}" . ' Unchecked</li>', $defaultBlocks);
        $t->contains('<ul class="task-list"><li><label><input type="checkbox" />Unchecked</label></li>', $blocks);
        $t->contains('<li><p><label><input type="checkbox" checked="" />Checked</label></p><p>with continuation paragraph</p></li>', $blocks);
        $t->contains('<ul class="task-list"><li><label><input type="checkbox" checked="" />Checked sublist</label><ul class="task-list"><li><label><input type="checkbox" />Unchecked subsublist</label><ol><li>Numbered child</li></ol></li></ul></li></ul>', $blocks);
        $t->true(!str_contains($blocks, "\u{2610}"), 'Opt-in task glyph handoff should not leave unchecked ballot glyphs in reviewer checkbox labels');
        $t->true(!str_contains($blocks, "\u{2612}"), 'Opt-in task glyph handoff should not leave checked ballot glyphs in reviewer checkbox labels');
    },
    'maps upstream native docx custom styles into wordpress data attributes' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-custom-style.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $styledSpans = array_values(array_filter(
            $parsed->children[1]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $styledDiv = $parsed->children[2];

        $t->same(3, count($parsed->children));
        $t->same(2, count($styledSpans));
        $t->same('Emphatic', $styledSpans[0]->attr('attributes')['custom-style']);
        $t->same('Strengthened', $styledSpans[1]->attr('attributes')['custom-style']);
        $t->same('div', $styledDiv->type);
        $t->same('My Block Style', $styledDiv->attr('attributes')['custom-style']);
        $t->contains('( "custom-style" , "Emphatic" )', $roundTrip);
        $t->contains('( "custom-style" , "My Block Style" )', $roundTrip);
        $t->contains('<span data-pandoc-custom-style="Emphatic">emphasized</span>', $blocks);
        $t->contains('<span data-pandoc-custom-style="Strengthened">strong</span>', $blocks);
        $t->contains('<div data-pandoc-custom-style="My Block Style"><p>One paragraph of text.</p><p>And another paragraph of <span data-pandoc-custom-style="Emphatic">really', $blocks);
        $t->true(!str_contains($blocks, ' custom-style='), 'WordPress output should not emit raw upstream custom-style attributes');
    },
    'maps upstream native docx document properties into wordpress metadata review block' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-document-properties.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['standalone' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($parsed);
        $defaultBlocks = (new WordPressBlockWriter())->write($parsed);
        $meta = $parsed->attr('meta');
        $nestedCustom = $meta['nested-custom']['value'][0]['custom 7'] ?? null;

        $t->same(1, count($parsed->children));
        $t->same('Testing custom properties', $meta['title']);
        $t->same(['A. M.'], $meta['author']);
        $t->same('MetaBlocks', $meta['abstract']['type']);
        $t->same('MetaBlocks', $meta['description']['type']);
        $t->same('MetaList', $meta['keywords']['type']);
        $t->same('MetaList', $meta['nested-custom']['type']);
        $t->same('MetaInlines', $nestedCustom['type'] ?? '');
        $t->contains('RawInline (Format "html") "<i>"', $roundTrip);
        $t->contains('MetaMap (fromList [ ( "custom 7" , MetaInlines', $roundTrip);
        $t->contains('<section class="pandoc-document-metadata" data-pandoc-source="native-meta"><dl>', $blocks);
        $t->contains('<dt data-pandoc-meta-key="title">title</dt><dd><span>Testing custom properties</span></dd>', $blocks);
        $t->contains('<dt data-pandoc-meta-key="author">author</dt><dd><ul><li><span>A. M.</span></li></ul></dd>', $blocks);
        $t->contains('<dt data-pandoc-meta-key="custom5">custom5</dt><dd><span>Escaping html &lt;i&gt;asdf&lt;/i&gt;</span></dd>', $blocks);
        $t->contains('<dt data-pandoc-meta-key="description">description</dt><dd><p>Long description spanning several lines.</p><p>This is ' . "\u{00E1}" . ' second &lt;i&gt;line&lt;/i&gt;.</p></dd>', $blocks);
        $t->contains('<dt data-pandoc-meta-key="custom 7">custom 7</dt><dd><span>Nested Custom value 7</span></dd>', $blocks);
        $t->contains('<p>Testing document properties</p>', $blocks);
        $t->true(!str_contains($blocks, '<i>'), 'WordPress metadata review output should escape raw HTML metadata');
        $t->true(!str_contains($defaultBlocks, 'pandoc-document-metadata'), 'WordPress metadata review block should be opt-in');
    },
    'maps upstream native docx empty index fields into wordpress review spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-empty-field.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $indexSpans = array_values(array_filter(
            $parsed->children[0]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $links = array_values(array_filter(
            $parsed->children[0]->children,
            static fn (AstNode $node): bool => $node->type === 'link'
        ));

        $t->same(3, count($parsed->children));
        $t->same(1, count($indexSpans));
        $t->same(['indexref'], $indexSpans[0]->attr('classes'));
        $t->same('French', $indexSpans[0]->attr('attributes')['entry']);
        $t->same(2, count($links));
        $t->same('https://books.google.com/books?id=sp_Zcb9ot90C&lpg=PR4&hl=zh-CN&pg=PA19#v=onepage&q&f=true', $links[0]->attr('url'));
        $t->same('Classic', $links[1]->children[0]->attr('text'));
        $t->contains('Span ( "" , [ "indexref" ] , [ ( "entry" , "French" ) ] ) [  ]', $roundTrip);
        $t->contains('<span class="indexref" data-pandoc-index-entry="French"></span>', $blocks);
        $t->contains('<a href="https://books.google.com/books?id=sp_Zcb9ot90C&amp;lpg=PR4&amp;hl=zh-CN&amp;pg=PA19#v=onepage&amp;q&amp;f=true">Foundations of Analysis, 2nd Edition</a>', $blocks);
        $t->contains('<a href="https://books.google.ae/books?id=dlc0DwAAQBAJ&amp;lpg=PT29&amp;hl=zh-CN&amp;pg=PT26#v=onepage&amp;q&amp;f=true">Classic Set Theory: For Guided Independent Study</a>', $blocks);
        $t->contains('<p>Index:</p>', $blocks);
        $t->contains('<p>French, 1</p>', $blocks);
        $t->true(!str_contains($blocks, ' entry="'), 'WordPress output should not leak raw upstream index entry attributes');
    },
    'maps upstream native docx image dimensions into wordpress image metadata' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-image-no-embed.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $image = $parsed->children[1]->children[0];
        $attributes = $image->attr('attributes');

        $t->same(2, count($parsed->children));
        $t->same('image', $image->type);
        $t->same('media/image1.jpg', $image->attr('url'));
        $t->same('An unhappy fish.', $image->attr('title'));
        $t->same("He realizes he's making the file-size too big.", $image->attr('alt'));
        $t->same('6.5in', $attributes['width']);
        $t->same('5.508333333333334in', $attributes['height']);
        $t->contains('( "height" , "5.508333333333334in" ) , ( "width" , "6.5in" )', $roundTrip);
        $t->contains('<img src="media/image1.jpg" alt="He realizes he&#039;s making the file-size too big." title="An unhappy fish." data-pandoc-width="6.5in" data-pandoc-height="5.508333333333334in" style="width:6.5in; height:5.508333333333334in"/>', $blocks);
        $t->true(!str_contains($blocks, ' width="6.5in"'), 'WordPress output should not emit invalid raw width attributes with CSS units');
        $t->true(!str_contains($blocks, ' height="5.508333333333334in"'), 'WordPress output should not emit invalid raw height attributes with CSS units');
    },
    'maps upstream native docx vml object images into wordpress source format metadata' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-vml-object-image.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $image = $parsed->children[1]->children[0] ?? new AstNode('missing');
        $regularImage = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'media/image1.jpeg',
                    'alt' => '',
                ]),
            ]),
        ]);

        $t->same(2, count($parsed->children));
        $t->same('image', $image->type);
        $t->same('media/image1.emf', $image->attr('url'));
        $t->contains('Image ( "" , [  ] , [  ] ) [  ] ( "media/image1.emf" , "" )', $roundTrip);
        $t->contains('<figure class="wp-block-image"><img src="media/image1.emf" alt="" data-pandoc-source-format="emf"/></figure>', $blocks);
        $t->contains('<p>Test with object as image:</p>', $blocks);
        $t->true(!str_contains((new WordPressBlockWriter())->write($regularImage), 'data-pandoc-source-format'), 'WordPress output should not flag browser-native image formats');
    },
    'maps upstream native docx image textbox captions into wordpress media review metadata' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-image-textbox-caption.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $figure = $parsed->children[0];
        $image = $figure->children[0] ?? new AstNode('missing');
        $imageAttrs = $image->attr('attributes');
        $caption = "1 Daniel Schliebner: Cantor'sches Diagonalverfahren. Von Mengen, Unendlichkeiten und Wahnsinn, Pdf: https://www2.informatik.hu-berlin.de/~kossahl/Uni/Ma1/Cantor.pdf";

        $t->same(1, count($parsed->children));
        $t->same('figure', $figure->type);
        $t->same($caption, $figure->attr('caption'));
        $t->same('image', $image->type);
        $t->same('media/image1.emf', $image->attr('url'));
        $t->same('', $image->attr('alt'));
        $t->same('2.3680555555555554in', $imageAttrs['width']);
        $t->same('0.9340277777777778in', $imageAttrs['height']);
        $t->contains('Caption Nothing [ Para [ Str "1" , Space , Str "Daniel"', $roundTrip);
        $t->contains('Image ( "" , [  ] , [ ( "height" , "0.9340277777777778in" ) , ( "width" , "2.3680555555555554in" ) ] ) [  ] ( "media/image1.emf" , "" )', $roundTrip);
        $t->contains('<img src="media/image1.emf" alt="1 Daniel Schliebner: Cantor&#039;sches Diagonalverfahren. Von Mengen, Unendlichkeiten und Wahnsinn, Pdf: https://www2.informatik.hu-berlin.de/~kossahl/Uni/Ma1/Cantor.pdf" data-pandoc-width="2.3680555555555554in" data-pandoc-height="0.9340277777777778in" style="width:2.3680555555555554in; height:0.9340277777777778in" data-pandoc-source-format="emf" data-pandoc-alt-source="figure-caption"/>', $blocks);
        $t->contains('<figcaption>1 Daniel Schliebner: Cantor&#039;sches Diagonalverfahren. Von Mengen, Unendlichkeiten und Wahnsinn, Pdf: https://www2.informatik.hu-berlin.de/~kossahl/Uni/Ma1/Cantor.pdf</figcaption>', $blocks);
        $t->true(!str_contains($roundTrip, 'data-pandoc-alt-source'), 'WordPress caption-derived alt metadata should not mutate Native read-back output');
    },
    'maps upstream native docx diagrams into explicit wordpress review spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-diagram.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $heading = $parsed->children[0];
        $diagram = $parsed->children[1]->children[0] ?? new AstNode('missing');

        $t->same(2, count($parsed->children));
        $t->same('heading', $heading->type);
        $t->same('diagram-after', $heading->attr('id'));
        $t->same('span', $diagram->type);
        $t->same(['diagram'], $diagram->attr('classes'));
        $t->same('[DIAGRAM]', $diagram->children[0]->attr('text'));
        $t->contains('Span ( "" , [ "diagram" ] , [  ] ) [ Str "[DIAGRAM]" ]', $roundTrip);
        $t->contains('<span class="diagram" data-pandoc-diagram="unsupported-docx-diagram">[DIAGRAM]</span>', $blocks);
        $t->true(!str_contains($blocks, 'data-pandoc-diagram="[DIAGRAM]"'), 'WordPress output should not treat diagram placeholder text as trusted metadata');
    },
    'maps upstream native jats figure body alt text into wordpress image alt' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-jats-figure-alt-text.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $figure = $parsed->children[0];
        $altBlock = $figure->children[0];
        $imageParagraph = $figure->children[1];
        $image = $imageParagraph->children[0] ?? new AstNode('missing');

        $t->same(1, count($parsed->children));
        $t->same('figure', $figure->type);
        $t->same('fig-1', $figure->attr('id'));
        $t->same('bar', $figure->attr('caption'));
        $t->same('plain', $altBlock->type);
        $t->same('alternative-decription', $altBlock->children[0]->attr('text'));
        $t->same('paragraph', $imageParagraph->type);
        $t->same('image', $image->type);
        $t->same('foo.png', $image->attr('url'));
        $t->same('', $image->attr('alt'));
        $t->contains('Plain [ Str "alternative-decription" ]', $roundTrip);
        $t->contains('Para [ Image ( "" , [  ] , [  ] ) [  ] ( "foo.png" , "" ) ]', $roundTrip);
        $t->contains('<figure class="wp-block-image" id="fig-1"><img src="foo.png" alt="alternative-decription"/><figcaption>bar</figcaption></figure>', $blocks);
        $t->true(!str_contains($blocks, 'src=""'), 'Nested figure images should not fall back to an empty placeholder src');
        $t->true(!str_contains($blocks, '<p>alternative-decription</p>'), 'Figure alt text should become image metadata, not a visible body paragraph');
    },
    'maps upstream native docx scientific table widths and header row spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-table-header-rowspan-slice.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $table = $parsed->children[0];
        $widths = $table->attr('widths', []);
        $head = $table->children[0];
        $firstHeaderRow = $head->children[0];
        $secondHeaderRow = $head->children[1];
        $body = $table->children[1];
        $groupHeader = $firstHeaderRow->children[4];

        $t->same(1, count($parsed->children));
        $t->same('table', $table->type);
        $t->same(8, count($widths));
        $t->true(abs($widths[3] - 0.09707602339181289) < 0.000000000000001, 'NativeReader should parse exponent ColWidth values');
        $t->true(abs($widths[4] - 0.07719298245614035) < 0.000000000000001, 'NativeReader should parse exponent ColWidth values below 0.1');
        $t->same(2, $firstHeaderRow->children[0]->attr('rowspan'));
        $t->same(2, $firstHeaderRow->children[1]->attr('rowspan'));
        $t->same(3, $groupHeader->attr('colspan'));
        $t->same('E', $groupHeader->children[0]->children[0]->attr('text'));
        $t->same(3, count($secondHeaderRow->children));
        $t->same(8, count($body->children[0]->children));
        $t->contains('ColWidth 0.097076023391813', $roundTrip);
        $t->contains('<col style="width:9.7076%"/>', $blocks);
        $t->contains('<th rowspan="2" style="text-align:left">A</th>', $blocks);
        $t->contains('<th colspan="3">E</th>', $blocks);
        $t->contains('<th><strong>G</strong></th><th><strong>H</strong></th><th><strong>I</strong></th>', $blocks);
    },
    'maps upstream native docx table gridbefore placeholders into wordpress cells' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-table-gridbefore-slice.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $defaultBlocks = (new WordPressBlockWriter())->write($parsed);
        $reviewBlocks = (new WordPressBlockWriter(['markEmptyTableCells' => true]))->write($parsed);
        $table = $parsed->children[0];
        $widths = $table->attr('widths', []);
        $head = $table->children[0];
        $body = $table->children[1];
        $cellText = static function (AstNode $cell): string {
            $parts = [];
            foreach ($cell->children as $block) {
                $parts[] = (string) $block->attr('text', '');
            }

            return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
        };
        $rowTexts = static fn (AstNode $row): array => array_map($cellText, $row->children);
        $bitsHeader = $head->children[0]->children[1];
        $firstBodyRow = $body->children[0];
        $spacerRow = $body->children[1];
        $codeRow = $body->children[2];
        $reservedRow = $body->children[3];

        $t->same(1, count($parsed->children));
        $t->same('table', $table->type);
        $t->same(11, count($widths));
        $t->true(abs($widths[0] - 0.007883369330453563) < 0.000000000000001, 'NativeReader should parse DOCX gridBefore scientific widths');
        $t->same(4, count($head->children[0]->children));
        $t->same(8, $bitsHeader->attr('colspan'));
        $t->same('Bits', $cellText($bitsHeader));
        $t->same(4, count($body->children));
        $t->same(['', '8', '7', '6', '5', '4', '3', '2', '1', '', ''], $rowTexts($firstBodyRow));
        $t->same(array_fill(0, 11, ''), $rowTexts($spacerRow));
        $t->same(2, $codeRow->children[0]->attr('colspan'));
        $t->same('CODED TEXT', $cellText($codeRow->children[9]));
        $t->same(10, $reservedRow->children[1]->attr('colspan'));
        $t->contains('ColWidth 0.007883369330454', $roundTrip);
        $t->contains('(ColSpan 10)', $roundTrip);
        $t->contains('<col style="width:0.7883%"/>', $defaultBlocks);
        $t->contains('<th></th><th colspan="8">Bits</th><th></th><th></th>', $defaultBlocks);
        $t->contains('<tbody><tr><td></td><td>8</td><td>7</td><td>6</td><td>5</td><td>4</td><td>3</td><td>2</td><td>1</td><td></td><td></td></tr>', $defaultBlocks);
        $t->contains('<td colspan="2">0</td>', $defaultBlocks);
        $t->contains('<td colspan="10">All other values are reserved.</td>', $defaultBlocks);
        $t->true(!str_contains($defaultBlocks, 'data-pandoc-empty-cell'), 'Default WordPress output should preserve blank DOCX grid cells without reviewer markers');
        $t->same(19, substr_count($reviewBlocks, 'data-pandoc-empty-cell="true"'));
        $t->contains('<th data-pandoc-empty-cell="true"></th><th colspan="8">Bits</th>', $reviewBlocks);
        $t->contains('<td data-pandoc-empty-cell="true"></td><td>8</td>', $reviewBlocks);
        $t->true(!str_contains($reviewBlocks, 'data-pandoc-empty-cell="true" colspan="2">0'), 'Non-empty spanning DOCX table cells should not be marked empty');
    },
    'maps upstream native docx table caption anchors into wordpress figcaptions' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-table-caption-anchor.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $firstLink = $parsed->children[0]->children[2];
        $firstTable = $parsed->children[1];
        $secondTable = $parsed->children[3];
        $firstCaptionInlines = $firstTable->attr('captionInlines');
        $secondCaptionInlines = $secondTable->attr('captionInlines');
        $firstAnchor = is_array($firstCaptionInlines) ? $firstCaptionInlines[0] : new AstNode('missing');
        $secondAnchor = is_array($secondCaptionInlines) ? $secondCaptionInlines[0] : new AstNode('missing');

        $t->same(5, count($parsed->children));
        $t->same('link', $firstLink->type);
        $t->same('#_Ref71265628', $firstLink->attr('url'));
        $t->same('Table 1', $firstTable->attr('caption'));
        $t->same('Table 2', $secondTable->attr('caption'));
        $t->same('span', $firstAnchor->type);
        $t->same('_Ref71265628', $firstAnchor->attr('id'));
        $t->same(['anchor'], $firstAnchor->attr('classes'));
        $t->same('span', $secondAnchor->type);
        $t->same('_Ref71265695', $secondAnchor->attr('id'));
        $t->contains('Span ( "_Ref71265628" , [ "anchor" ] , [  ] ) [  ]', $roundTrip);
        $t->contains('<a href="#_Ref71265628">Table 1</a>', $blocks);
        $t->contains('<figcaption class="wp-element-caption"><span id="_Ref71265628" class="anchor" data-pandoc-anchor="empty-target"></span>Table 1</figcaption>', $blocks);
        $t->contains('<figcaption class="wp-element-caption"><span id="_Ref71265695" class="anchor" data-pandoc-anchor="empty-target"></span>Table 2</figcaption>', $blocks);
    },
    'maps upstream native docx comments into wordpress review spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-comments.native');
        $parsed = (new NativeReader())->read($native);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $firstComment = $parsed->children[0]->children[4];
        $fourthParagraphSpans = array_values(array_filter(
            $parsed->children[3]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $nestedComment = $fourthParagraphSpans[0];

        $t->same(4, count($parsed->children));
        $t->same('span', $firstComment->type);
        $t->same(['comment-start'], $firstComment->attr('classes'));
        $t->same('0', $firstComment->attr('attributes')['id']);
        $t->same('Jesse Rosenthal', $firstComment->attr('attributes')['author']);
        $t->same('2016-05-09T16:13:00Z', $firstComment->attr('attributes')['date']);
        $t->same('linebreak', $nestedComment->children[9]->type);
        $t->contains('<span class="comment-start" data-pandoc-comment-id="0" data-pandoc-comment-author="Jesse Rosenthal" data-pandoc-comment-date="2016-05-09T16:13:00Z">I left a comment.</span>', $blocks);
        $t->contains('<span class="comment-end" data-pandoc-comment-id="0"></span>on it.', $blocks);
        $t->contains('multiple paragraphs.<br/>See?', $blocks);
        $t->contains('<span class="comment-start" data-pandoc-comment-id="4" data-pandoc-comment-author="Jesse Rosenthal" data-pandoc-comment-date="2016-06-22T14:36:00Z">Do something else.</span>', $blocks);
        $t->contains('<span class="comment-end" data-pandoc-comment-id="3"><span class="comment-end" data-pandoc-comment-id="4"></span></span>', $blocks);
        $t->true(!str_contains($blocks, ' author="'), 'WordPress output should not emit raw upstream author attributes');
        $t->true(!str_contains($blocks, ' date="'), 'WordPress output should not emit raw upstream date attributes');
    },
    'maps upstream native docx track changes into wordpress ins del spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-track-changes.native');
        $parsed = (new NativeReader())->read($native);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $insertions = array_values(array_filter(
            $parsed->children[0]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $deletions = array_values(array_filter(
            $parsed->children[1]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $inlineText = static function (AstNode $node): string {
            $text = '';
            foreach ($node->children as $child) {
                $text .= (string) $child->attr('text', '');
            }

            return $text;
        };

        $t->same(2, count($parsed->children));
        $t->same(['insertion'], $insertions[0]->attr('classes'));
        $t->same('eng-dept', $insertions[0]->attr('attributes')['author']);
        $t->same('2014-06-25T10:40:00Z', $insertions[0]->attr('attributes')['date']);
        $t->same('two exciting', $inlineText($insertions[0]));
        $t->same(['deletion'], $deletions[0]->attr('classes'));
        $t->same('n excessively modified', $inlineText($deletions[0]));
        $t->contains('<p>This is a text with <ins class="insertion" data-pandoc-change-author="eng-dept" data-pandoc-change-date="2014-06-25T10:40:00Z" datetime="2014-06-25T10:40:00Z">two exciting</ins> insertions.</p>', $blocks);
        $t->contains('<p>This is a text with a<del class="deletion" data-pandoc-change-author="eng-dept" data-pandoc-change-date="2014-06-25T10:42:00Z" datetime="2014-06-25T10:42:00Z">n excessively modified</del> deletion.</p>', $blocks);
        $t->true(!str_contains($blocks, ' author="'), 'WordPress output should not emit raw upstream author attributes');
        $t->true(!str_contains($blocks, ' date="'), 'WordPress output should not emit raw upstream date attributes');
    },
    'maps upstream native docx moved text into paired wordpress review spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-track-changes-move-all.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $insertion = $parsed->children[1]->children[0] ?? new AstNode('missing');
        $deletion = $parsed->children[3]->children[0] ?? new AstNode('missing');
        $inlineText = static function (AstNode $node): string {
            $text = '';
            foreach ($node->children as $child) {
                $text .= (string) $child->attr('text', '');
            }

            return $text;
        };

        $t->same(4, count($parsed->children));
        $t->same('span', $insertion->type);
        $t->same(['insertion'], $insertion->attr('classes'));
        $t->same('span', $deletion->type);
        $t->same(['deletion'], $deletion->attr('classes'));
        $t->same('Jesse Rosenthal', $insertion->attr('attributes')['author']);
        $t->same($insertion->attr('attributes'), $deletion->attr('attributes'));
        $t->same('Here is the text to be moved.', $inlineText($insertion));
        $t->same('Here is the text to be moved.', $inlineText($deletion));
        $t->contains('Span ( "" , [ "insertion" ] , [ ( "author" , "Jesse Rosenthal" ) , ( "date" , "2016-04-16T08:20:00Z" ) ] )', $roundTrip);
        $t->contains('Span ( "" , [ "deletion" ] , [ ( "author" , "Jesse Rosenthal" ) , ( "date" , "2016-04-16T08:20:00Z" ) ] )', $roundTrip);
        $t->contains('<p><ins class="insertion" data-pandoc-change-author="Jesse Rosenthal" data-pandoc-change-date="2016-04-16T08:20:00Z" datetime="2016-04-16T08:20:00Z">Here is the text to be moved.</ins></p>', $blocks);
        $t->contains('<p><del class="deletion" data-pandoc-change-author="Jesse Rosenthal" data-pandoc-change-date="2016-04-16T08:20:00Z" datetime="2016-04-16T08:20:00Z">Here is the text to be moved.</del></p>', $blocks);
        $t->contains('<p>Here is some text.</p>', $blocks);
        $t->contains('<p>Here is some more text.</p>', $blocks);
        $t->true(!str_contains($blocks, ' author="'), 'WordPress output should not emit raw upstream author attributes for moved text');
    },
    'maps upstream native docx accepted and rejected moved text decisions' => static function (TestRunner $t): void {
        $acceptedNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-track-changes-move-accept.native');
        $rejectedNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-track-changes-move-reject.native');
        $accepted = (new NativeReader())->read($acceptedNative);
        $rejected = (new NativeReader())->read($rejectedNative);
        $acceptedRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($accepted);
        $rejectedRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($rejected);
        $writer = new WordPressBlockWriter();
        $acceptedBlocks = $writer->write($accepted);
        $rejectedBlocks = $writer->write($rejected);
        $position = static fn (string $haystack, string $needle): int => strpos($haystack, $needle) === false ? -1 : (int) strpos($haystack, $needle);
        $acceptedContext = $position($acceptedBlocks, '<p>Here is some text.</p>');
        $acceptedMoved = $position($acceptedBlocks, '<p>Here is the text to be moved.</p>');
        $acceptedLaterContext = $position($acceptedBlocks, '<p>Here is some more text.</p>');
        $rejectedContext = $position($rejectedBlocks, '<p>Here is some text.</p>');
        $rejectedLaterContext = $position($rejectedBlocks, '<p>Here is some more text.</p>');
        $rejectedMoved = $position($rejectedBlocks, '<p>Here is the text to be moved.</p>');
        $blockTexts = static fn (AstNode $document): array => array_map(
            static fn (AstNode $node): string => (string) $node->attr('text', ''),
            $document->children
        );

        $t->same([
            'Here is some text.',
            'Here is the text to be moved.',
            'Here is some more text.',
        ], $blockTexts($accepted));
        $t->same([
            'Here is some text.',
            'Here is some more text.',
            'Here is the text to be moved.',
        ], $blockTexts($rejected));
        $t->contains('Para [ Str "Here" , Space , Str "is" , Space , Str "the" , Space , Str "text" , Space , Str "to" , Space , Str "be" , Space , Str "moved." ]', $acceptedRoundTrip);
        $t->contains('Para [ Str "Here" , Space , Str "is" , Space , Str "some" , Space , Str "more" , Space , Str "text." ]', $rejectedRoundTrip);
        $t->true(!str_contains($acceptedRoundTrip, 'Span ('), 'Accepted moved text fixture should collapse to plain paragraphs');
        $t->true(!str_contains($rejectedRoundTrip, 'Span ('), 'Rejected moved text fixture should collapse to plain paragraphs');
        $t->true($acceptedContext >= 0 && $acceptedMoved >= 0 && $acceptedLaterContext >= 0, 'Accepted decision should render all three WordPress paragraphs');
        $t->true($rejectedContext >= 0 && $rejectedMoved >= 0 && $rejectedLaterContext >= 0, 'Rejected decision should render all three WordPress paragraphs');
        $t->true($acceptedContext < $acceptedMoved);
        $t->true($acceptedMoved < $acceptedLaterContext);
        $t->true($rejectedContext < $rejectedLaterContext);
        $t->true($rejectedLaterContext < $rejectedMoved);
        $t->true(!str_contains($acceptedBlocks, '<ins') && !str_contains($acceptedBlocks, '<del'), 'Accepted review decision should not render residual change spans');
        $t->true(!str_contains($rejectedBlocks, '<ins') && !str_contains($rejectedBlocks, '<del'), 'Rejected review decision should not render residual change spans');
    },
    'maps upstream native docx accepted and rejected insertion deletion decisions' => static function (TestRunner $t): void {
        $cases = [
            'accepted insertion' => [
                'fixture' => 'upstream-native-docx-track-changes-insertion-accept.native',
                'text' => 'This is a text with two exciting insertions.',
                'roundTrip' => 'Para [ Str "This" , Space , Str "is" , Space , Str "a" , Space , Str "text" , Space , Str "with" , Space , Str "two" , Space , Str "exciting" , Space , Str "insertions." ]',
            ],
            'rejected insertion' => [
                'fixture' => 'upstream-native-docx-track-changes-insertion-reject.native',
                'text' => 'This is a text with insertions.',
                'roundTrip' => 'Para [ Str "This" , Space , Str "is" , Space , Str "a" , Space , Str "text" , Space , Str "with" , Space , Str "insertions." ]',
            ],
            'accepted deletion' => [
                'fixture' => 'upstream-native-docx-track-changes-deletion-accept.native',
                'text' => 'This is a text with a deletion.',
                'roundTrip' => 'Para [ Str "This" , Space , Str "is" , Space , Str "a" , Space , Str "text" , Space , Str "with" , Space , Str "a" , Space , Str "deletion." ]',
            ],
            'rejected deletion' => [
                'fixture' => 'upstream-native-docx-track-changes-deletion-reject.native',
                'text' => 'This is a text with an excessively modified deletion.',
                'roundTrip' => 'Para [ Str "This" , Space , Str "is" , Space , Str "a" , Space , Str "text" , Space , Str "with" , Space , Str "an" , Space , Str "excessively" , Space , Str "modified" , Space , Str "deletion." ]',
            ],
        ];

        foreach ($cases as $label => $case) {
            $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $case['fixture']);
            $document = (new NativeReader())->read($native);
            $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $inlineTypes = array_map(static fn (AstNode $node): string => $node->type, $paragraph->children);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same($case['text'], $paragraph->attr('text'));
            $t->same([], array_values(array_filter($inlineTypes, static fn (string $type): bool => $type !== 'text')));
            $t->contains($case['roundTrip'], $roundTrip);
            $t->contains('<p>' . $case['text'] . '</p>', $blocks);
            $t->true(!str_contains($roundTrip, 'Span ('), $label . ' fixture should collapse to a plain Native paragraph');
            $t->true(!str_contains($blocks, '<ins') && !str_contains($blocks, '<del'), $label . ' fixture should not render residual review markup');
        }
    },
    'maps upstream native docx scrubbed review metadata without fake dates' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-track-changes-scrubbed-metadata.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $spans = array_values(array_filter(
            $parsed->children[0]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));

        $t->same(1, count($parsed->children));
        $t->same(['deletion'], $spans[0]->attr('classes'));
        $t->same('Author', $spans[0]->attr('attributes')['author']);
        $t->true(!isset($spans[0]->attr('attributes')['date']), 'Deletion metadata date should be absent after upstream scrub');
        $t->same(['insertion'], $spans[1]->attr('classes'));
        $t->same('Author', $spans[1]->attr('attributes')['author']);
        $t->true(!isset($spans[1]->attr('attributes')['date']), 'Insertion metadata date should be absent after upstream scrub');
        $t->same(['comment-start'], $spans[2]->attr('classes'));
        $t->same('3', $spans[2]->attr('attributes')['id']);
        $t->same('Author', $spans[2]->attr('attributes')['author']);
        $t->same(['comment-end'], $spans[3]->attr('classes'));
        $t->contains('Span ( "" , [ "deletion" ] , [ ( "author" , "Author" ) ] ) [ Str "dummy" ]', $roundTrip);
        $t->contains('Span ( "" , [ "insertion" ] , [ ( "author" , "Author" ) ] ) [ Str "test" ]', $roundTrip);
        $t->contains('<del class="deletion" data-pandoc-change-author="Author" data-pandoc-change-date-status="missing">dummy</del>', $blocks);
        $t->contains('<ins class="insertion" data-pandoc-change-author="Author" data-pandoc-change-date-status="missing">test</ins>', $blocks);
        $t->contains('<span class="comment-start" data-pandoc-comment-id="3" data-pandoc-comment-author="Author" data-pandoc-comment-date-status="missing">With a comment!</span>document<span class="comment-end" data-pandoc-comment-id="3"></span>.', $blocks);
        $t->true(!str_contains($blocks, ' datetime="'), 'WordPress output should not invent datetime for scrubbed review metadata');
        $t->true(!str_contains($blocks, ' date="'), 'WordPress output should not emit raw upstream date attributes for scrubbed metadata');
    },
    'maps upstream native docx paragraph insertion deletion into wordpress boundary spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-paragraph-insertion-deletion.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $insertion = $parsed->children[0]->children[5];
        $deletion = $parsed->children[1]->children[1];

        $t->same(3, count($parsed->children));
        $t->same('span', $insertion->type);
        $t->same(['paragraph-insertion'], $insertion->attr('classes'));
        $t->same('Seeley, Jason', $insertion->attr('attributes')['author']);
        $t->same('2017-09-17T16:39:00Z', $insertion->attr('attributes')['date']);
        $t->same('span', $deletion->type);
        $t->same(['paragraph-deletion'], $deletion->attr('classes'));
        $t->contains('Span ( "" , [ "paragraph-insertion" ]', $roundTrip);
        $t->contains('Span ( "" , [ "paragraph-deletion" ]', $roundTrip);
        $t->contains('<p>This is a<span class="paragraph-insertion" data-pandoc-paragraph-change="insertion" data-pandoc-change-author="Seeley, Jason" data-pandoc-change-date="2017-09-17T16:39:00Z" datetime="2017-09-17T16:39:00Z"></span></p>', $blocks);
        $t->contains('<p>split<span class="paragraph-deletion" data-pandoc-paragraph-change="deletion" data-pandoc-change-author="Seeley, Jason" data-pandoc-change-date="2017-09-17T16:39:00Z" datetime="2017-09-17T16:39:00Z"></span></p>', $blocks);
        $t->contains('<p>Paragraph.</p>', $blocks);
        $t->true(!str_contains($blocks, ' author="'), 'WordPress output should not emit raw upstream author attributes');
        $t->true(!str_contains($blocks, ' date="'), 'WordPress output should not emit raw upstream date attributes');
    },
    'maps upstream native docx accepted and rejected paragraph split decisions' => static function (TestRunner $t): void {
        $acceptedNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-paragraph-insertion-deletion-accept.native');
        $rejectedNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-paragraph-insertion-deletion-reject.native');
        $accepted = (new NativeReader())->read($acceptedNative);
        $rejected = (new NativeReader())->read($rejectedNative);
        $acceptedRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($accepted);
        $rejectedRoundTrip = (new NativeWriter(['blocksOnly' => true]))->write($rejected);
        $writer = new WordPressBlockWriter();
        $acceptedBlocks = $writer->write($accepted);
        $rejectedBlocks = $writer->write($rejected);
        $blockTexts = static fn (AstNode $document): array => array_map(
            static fn (AstNode $node): string => (string) $node->attr('text', ''),
            $document->children
        );
        $position = static fn (string $haystack, string $needle): int => strpos($haystack, $needle) === false ? -1 : (int) strpos($haystack, $needle);
        $acceptedFirst = $position($acceptedBlocks, '<p>This is a</p>');
        $acceptedSecond = $position($acceptedBlocks, '<p>split Paragraph.</p>');
        $rejectedFirst = $position($rejectedBlocks, '<p>This is a split</p>');
        $rejectedSecond = $position($rejectedBlocks, '<p>Paragraph.</p>');

        $t->same(['This is a', 'split Paragraph.'], $blockTexts($accepted));
        $t->same(['This is a split', 'Paragraph.'], $blockTexts($rejected));
        $t->contains('Para [ Str "This" , Space , Str "is" , Space , Str "a" ]', $acceptedRoundTrip);
        $t->contains('Para [ Str "split" , Space , Str "Paragraph." ]', $acceptedRoundTrip);
        $t->contains('Para [ Str "This" , Space , Str "is" , Space , Str "a" , Space , Str "split" ]', $rejectedRoundTrip);
        $t->contains('Para [ Str "Paragraph." ]', $rejectedRoundTrip);
        $t->true(!str_contains($acceptedRoundTrip, 'paragraph-insertion'), 'Accepted paragraph split should collapse to plain paragraphs');
        $t->true(!str_contains($acceptedRoundTrip, 'paragraph-deletion'), 'Accepted paragraph split should not keep deletion boundary spans');
        $t->true(!str_contains($rejectedRoundTrip, 'paragraph-insertion'), 'Rejected paragraph split should not keep insertion boundary spans');
        $t->true(!str_contains($rejectedRoundTrip, 'paragraph-deletion'), 'Rejected paragraph split should collapse to plain paragraphs');
        $t->true($acceptedFirst >= 0 && $acceptedSecond >= 0 && $acceptedFirst < $acceptedSecond, 'Accepted split decision should keep the split paragraph order');
        $t->true($rejectedFirst >= 0 && $rejectedSecond >= 0 && $rejectedFirst < $rejectedSecond, 'Rejected split decision should keep the rejected paragraph order');
        $t->true(!str_contains($acceptedBlocks, 'data-pandoc-paragraph-change'), 'Accepted review decision should not render residual paragraph-change metadata');
        $t->true(!str_contains($rejectedBlocks, 'data-pandoc-paragraph-change'), 'Rejected review decision should not render residual paragraph-change metadata');
    },
    'maps upstream native docx overlapping targets into wordpress anchor spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-overlapping-targets.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $firstLink = $parsed->children[0]->children[0] ?? new AstNode('missing');
        $anchor = $parsed->children[1]->children[0] ?? new AstNode('missing');
        $secondLink = $parsed->children[2]->children[0] ?? new AstNode('missing');
        $inlineText = static function (AstNode $node): string {
            $text = '';
            foreach ($node->children as $child) {
                $text .= (string) $child->attr('text', '');
            }

            return $text;
        };

        $t->same(3, count($parsed->children));
        $t->same('link', $firstLink->type);
        $t->same('#Fizz', $firstLink->attr('url'));
        $t->same('One link to one target.', $inlineText($firstLink));
        $t->same('span', $anchor->type);
        $t->same('Fizz', $anchor->attr('id'));
        $t->same(['anchor'], $anchor->attr('classes'));
        $t->same([], $anchor->children);
        $t->same('link', $secondLink->type);
        $t->same('#Fizz', $secondLink->attr('url'));
        $t->contains('Span ( "Fizz" , [ "anchor" ] , [  ] ) [  ]', $roundTrip);
        $t->contains('<a href="#Fizz">One link to one target.</a>', $blocks);
        $t->contains('<span id="Fizz" class="anchor" data-pandoc-anchor="empty-target"></span>This is a target with two names.', $blocks);
        $t->contains('<a href="#Fizz">Another link to the same target.</a>', $blocks);
        $t->true(!str_contains($blocks, '<span id="Fizz" class="anchor"></span>'), 'WordPress output should mark empty DOCX anchor targets for migration review');
    },
    'maps upstream native docx nested anchor labels into wordpress span labels' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-nested-anchors-header.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $tocHeading = $parsed->children[0] ?? new AstNode('missing');
        $shortToc = $parsed->children[1]->children[0] ?? new AstNode('missing');
        $nestedPageLink = $shortToc->children[4] ?? new AstNode('missing');

        $t->same(15, count($parsed->children));
        $t->same('heading', $tocHeading->type);
        $t->same(['TOC-Heading'], $tocHeading->attr('classes'));
        $t->same('link', $shortToc->type);
        $t->same('#short-instructions', $shortToc->attr('url'));
        $t->same('link', $nestedPageLink->type);
        $t->same('#short-instructions', $nestedPageLink->attr('url'));
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "Short" , Space , Str "instructions" , Space , Link', $roundTrip);
        $t->contains('<a href="#short-instructions">Short instructions <span>1</span></a>', $blocks);
        $t->contains('<a href="#some-instructions">Some instructions <span>1</span></a>', $blocks);
        $t->contains('<a href="#remote-folder-or-longlonglonglonglong-file-with-manymanymanymany-letters-inside-opening">Remote folder or longlonglonglonglong file with manymanymanymany letters inside opening <span>2</span></a>', $blocks);
        $t->contains('<a href="#remote-folder-or-longlonglonglonglong-file-with-manymanymanymany-letters-inside-closing">Remote folder or longlonglonglonglong file with manymanymanymany letters inside closing <span>2</span></a>', $blocks);
        $t->true(!str_contains($blocks, '<a href="#short-instructions">Short instructions <a'), 'WordPress output should not nest links inside DOCX-generated TOC labels');
        $t->true(!str_contains($blocks, '<a href="#some-instructions">Some instructions <a'), 'WordPress output should not nest links inside DOCX-generated TOC labels');
    },
    'maps upstream native docx raw bookmarks into wordpress bookmark spans' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-raw-bookmarks.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $rawInlines = [];
        foreach ($parsed->children as $block) {
            foreach ($block->children as $inline) {
                if ($inline->type === 'raw_inline') {
                    $rawInlines[] = $inline;
                }
            }
        }
        $bookmarkStart = $rawInlines[0] ?? new AstNode('missing');
        $bookmarkEnd = $rawInlines[1] ?? new AstNode('missing');

        $t->same(3, count($parsed->children));
        $t->same(2, count($rawInlines));
        $t->same('raw_inline', $bookmarkStart->type);
        $t->same('openxml', $bookmarkStart->attr('format'));
        $t->same('<w:bookmarkStart w:id="0" w:name="Aliquam"/>', $bookmarkStart->attr('text'));
        $t->same('raw_inline', $bookmarkEnd->type);
        $t->same('<w:bookmarkEnd w:id="0"/>', $bookmarkEnd->attr('text'));
        $t->contains('RawInline (Format "openxml") "<w:bookmarkStart', $roundTrip);
        $t->contains('RawInline (Format "openxml") "<w:bookmarkEnd', $roundTrip);
        $t->contains('<span class="pandoc-openxml-bookmark-start" data-pandoc-raw-format="openxml" data-pandoc-bookmark-id="0" data-pandoc-bookmark-name="Aliquam"></span>Aliquam', $blocks);
        $t->contains('<span class="pandoc-openxml-bookmark-end" data-pandoc-raw-format="openxml" data-pandoc-bookmark-id="0"></span>Pellentesque', $blocks);
        $t->true(!str_contains($blocks, '<w:bookmarkStart'), 'WordPress output should not leak raw OpenXML bookmark markup as HTML');
    },
    'maps upstream native docx raw openxml blocks into wordpress review code blocks' => static function (TestRunner $t): void {
        $native = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-raw-blocks.native');
        $parsed = (new NativeReader())->read($native);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($parsed);
        $blocks = (new WordPressBlockWriter())->write($parsed);
        $rawBlocks = array_values(array_filter(
            $parsed->children,
            static fn (AstNode $node): bool => $node->type === 'raw_block'
        ));

        $t->same(6, count($parsed->children));
        $t->same(3, count($rawBlocks));
        $t->same('openxml', $rawBlocks[0]->attr('format'));
        $t->contains('<w:tbl>', $rawBlocks[0]->attr('text'));
        $t->contains('RawBlock (Format "openxml") "<w:tbl>', $roundTrip);
        $t->contains('<pre class="wp-block-code pandoc-raw-openxml" data-pandoc-raw-format="openxml"><code class="language-xml">&lt;w:tbl&gt;', $blocks);
        $t->contains('&lt;w:tc&gt;', $blocks);
        $t->contains('<p>Ribosome</p>', $blocks);
        $t->contains('<p>Lysosome</p>', $blocks);
        $t->true(!str_contains($blocks, '<w:tbl>'), 'WordPress output should show OpenXML as escaped review code, not active HTML');
    },
    'maps upstream native docx notes and links inside notes into wordpress endnotes' => static function (TestRunner $t): void {
        $notesNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-notes.native');
        $linkNative = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-link-in-notes.native');
        $notesDocument = (new NativeReader())->read($notesNative);
        $linkDocument = (new NativeReader())->read($linkNative);
        $document = new AstNode('document', [], [
            ...$notesDocument->children,
            ...$linkDocument->children,
        ]);
        $roundTrip = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $docxNotes = array_values(array_filter(
            $notesDocument->children[1]->children,
            static fn (AstNode $node): bool => $node->type === 'note'
        ));
        $linkNote = array_values(array_filter(
            $linkDocument->children[0]->children,
            static fn (AstNode $node): bool => $node->type === 'note'
        ))[0] ?? new AstNode('missing');
        $noteLink = $linkNote->children[0]->children[0] ?? new AstNode('missing');

        $t->same(2, count($notesDocument->children));
        $t->same('heading', $notesDocument->children[0]->type);
        $t->same('a-footnote', $notesDocument->children[0]->attr('id'));
        $t->same(2, count($docxNotes));
        $t->same('My note.', $docxNotes[0]->children[0]->attr('text'));
        $t->same('This is an endnote at the end of the document.', $docxNotes[1]->children[0]->attr('text'));
        $t->same('note', $linkNote->type);
        $t->same('link', $noteLink->type);
        $t->same('http://wikipedia.org/', $noteLink->attr('url'));
        $t->contains('Note [ Para [ Str "My" , Space , Str "note." ]', $roundTrip);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "http://wikipedia.org/" ] ( "http://wikipedia.org/" , "" )', $roundTrip);
        $t->contains('<h2 id="a-footnote">A footnote</h2>', $blocks);
        $t->contains('<p>Test footnote.<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> Test endnote.<sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup></p>', $blocks);
        $t->contains('<li id="fn-1"><p>My note.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-2"><p>This is an endnote at the end of the document.</p> <a href="#fnref-2" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-3"><p><a href="http://wikipedia.org/">http://wikipedia.org/</a></p> <a href="#fnref-3" aria-label="Back to content">Back</a></li>', $blocks);
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
    'maps upstream html reader standalone del inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-del-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['strikeout', 'text', 'underline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('Remove deprecated shortcode', $paragraph->children[0]->children[0]->attr('text'));
        $t->same('replacement copy', $paragraph->children[2]->children[0]->attr('text'));
        $t->contains('<p><del>Remove deprecated shortcode</del> and keep <u>replacement copy</u>.</p>', $blocks);
    },
    'maps upstream html reader standalone progress inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-progress-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['raw_html_inline', 'text', 'raw_html_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<progress value="7" max="10">', $paragraph->children[0]->attr('html'));
        $t->same('70%', $paragraph->children[1]->attr('text'));
        $t->same('</progress>', $paragraph->children[2]->attr('html'));
        $t->contains('<p><progress value="7" max="10">70%</progress> import complete.</p>', $blocks);
    },
    'maps upstream html reader standalone map area inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-map-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same(['raw_html_inline', 'raw_html_inline', 'raw_html_inline', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<map name="legacy-image-map">', $paragraph->children[0]->attr('html'));
        $t->same('<area shape="rect" coords="0,0,80,40" href="/wp-admin/upload.php" alt="Media library">', $paragraph->children[1]->attr('html'));
        $t->same('</map>', $paragraph->children[2]->attr('html'));
        $t->contains('<p><map name="legacy-image-map"><area shape="rect" coords="0,0,80,40" href="/wp-admin/upload.php" alt="Media library"></map> keeps imported hotspots visible.</p>', $blocks);
    },
    'maps upstream html reader standalone audio source track inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-audio-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'raw_html_inline',
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<audio controls="" src="/wp-content/uploads/imported-sermon.mp3">', $paragraph->children[0]->attr('html'));
        $t->same('<source src="/wp-content/uploads/imported-sermon.ogg" type="audio/ogg">', $paragraph->children[1]->attr('html'));
        $t->same('<track kind="captions" src="/wp-content/uploads/imported-sermon.vtt" srclang="en" label="English captions">', $paragraph->children[2]->attr('html'));
        $t->same('Audio fallback', $paragraph->children[3]->attr('text'));
        $t->same('</audio>', $paragraph->children[4]->attr('html'));
        $t->contains('<p><audio controls="" src="/wp-content/uploads/imported-sermon.mp3"><source src="/wp-content/uploads/imported-sermon.ogg" type="audio/ogg"><track kind="captions" src="/wp-content/uploads/imported-sermon.vtt" srclang="en" label="English captions">Audio fallback</audio> remains playable after import.</p>', $blocks);
    },
    'maps upstream html reader standalone video source track inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-video-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'raw_html_inline',
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<video controls="" poster="/wp-content/uploads/imported-poster.jpg">', $paragraph->children[0]->attr('html'));
        $t->same('<source src="/wp-content/uploads/imported-tour.mp4" type="video/mp4">', $paragraph->children[1]->attr('html'));
        $t->same('<track kind="captions" src="/wp-content/uploads/imported-tour.vtt" srclang="en" label="English captions">', $paragraph->children[2]->attr('html'));
        $t->same('Video fallback', $paragraph->children[3]->attr('text'));
        $t->same('</video>', $paragraph->children[4]->attr('html'));
        $t->contains('<p><video controls="" poster="/wp-content/uploads/imported-poster.jpg"><source src="/wp-content/uploads/imported-tour.mp4" type="video/mp4"><track kind="captions" src="/wp-content/uploads/imported-tour.vtt" srclang="en" label="English captions">Video fallback</video> remains visible after import.</p>', $blocks);
    },
    'maps upstream html reader standalone object embed inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-object-embed-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<object data="/wp-content/uploads/imported-map.svg" type="image/svg+xml">', $paragraph->children[0]->attr('html'));
        $t->same('<embed src="/wp-content/uploads/imported-map-fallback.swf" type="application/x-shockwave-flash">', $paragraph->children[1]->attr('html'));
        $t->same('Interactive map fallback', $paragraph->children[2]->attr('text'));
        $t->same('</object>', $paragraph->children[3]->attr('html'));
        $t->contains('<p><object data="/wp-content/uploads/imported-map.svg" type="image/svg+xml"><embed src="/wp-content/uploads/imported-map-fallback.swf" type="application/x-shockwave-flash">Interactive map fallback</object> remains reviewable after import.</p>', $blocks);
    },
    'maps upstream html reader standalone applet inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-applet-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'text',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<applet code="LegacyMap.class" archive="/wp-content/uploads/legacy-map.jar">', $paragraph->children[0]->attr('html'));
        $t->same('Legacy applet fallback', $paragraph->children[1]->attr('text'));
        $t->same('</applet>', $paragraph->children[2]->attr('html'));
        $t->contains('<p><applet code="LegacyMap.class" archive="/wp-content/uploads/legacy-map.jar">Legacy applet fallback</applet> remains visible for review.</p>', $blocks);
    },
    'maps upstream html reader standalone svg inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-svg-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->contains('<svg id="migration-icon" class="source-icon" data-source="batch-42" viewbox="0 0 10 10">', $paragraph->children[0]->attr('html'));
        $t->contains('<title>Migration icon</title>', $paragraph->children[0]->attr('html'));
        $t->same(' remains raw for source review.', $paragraph->children[1]->attr('text'));
        $t->contains('<p><svg id="migration-icon" class="source-icon" data-source="batch-42" viewbox="0 0 10 10"><title>Migration icon</title><path d="M0 0h10v10H0z"></path></svg> remains raw for source review.</p>', $blocks);
        $t->true(!str_contains($blocks, 'data:image/svg+xml'), 'Standalone raw SVG handoff should preserve raw source SVG when raw HTML is enabled');
    },
    'maps upstream html reader standalone noscript inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-noscript-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'link',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<noscript>', $paragraph->children[0]->attr('html'));
        $t->same('/wp-content/uploads/import-log.txt', $paragraph->children[1]->attr('url'));
        $t->same('Import log fallback', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('</noscript>', $paragraph->children[2]->attr('html'));
        $t->contains('<p><noscript><a href="/wp-content/uploads/import-log.txt">Import log fallback</a></noscript> remains visible when scripts are unavailable.</p>', $blocks);
    },
    'maps upstream html reader standalone ins inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-ins-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'underline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('Inserted review note', $paragraph->children[0]->children[0]->attr('text'));
        $t->same(' remains visible for editorial audit.', $paragraph->children[1]->attr('text'));
        $t->contains('<p><u>Inserted review note</u> remains visible for editorial audit.</p>', $blocks);
    },
    'maps upstream html reader standalone button inline fragments' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-button-inline.html');
        $document = (new MarkdownReader())->read($fixture);
        $paragraph = $document->children[0];
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([
            'raw_html_inline',
            'strong',
            'raw_html_inline',
            'text',
        ], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('<button class="wp-action" data-source="classic-editor">', $paragraph->children[0]->attr('html'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('Publish', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('</button>', $paragraph->children[2]->attr('html'));
        $t->same(' remains actionable for migration review.', $paragraph->children[3]->attr('text'));
        $t->contains('RawInline (Format "html") "<button class=\\"wp-action\\" data-source=\\"classic-editor\\">"', $native);
        $t->contains('Strong [ Str "Publish" ]', $native);
        $t->contains('<p><button class="wp-action" data-source="classic-editor"><strong>Publish</strong></button> remains actionable for migration review.</p>', $blocks);
    },
];
