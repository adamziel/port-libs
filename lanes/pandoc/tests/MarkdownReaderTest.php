<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
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
        $t->same(1, count($notScript->children));
        $t->contains('a^b c^d, a~b c~d.', $notScript->children[0]->attr('text'));
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
        $t->same('pine.', $trees->children[6]->children[0]->attr('text'));
        $t->same('single', $speech->children[0]->attr('kind'));
        $t->same('double', $speech->children[0]->children[1]->attr('kind'));
        $t->contains("70\u{2019}s?", $speech->children[1]->attr('text'));
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
        $inlineNote = $paragraph->children[7];
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
        $t->same('emph', $paragraph->children[5]->type);
        $t->contains('[^my note]', $paragraph->children[6]->attr('text'));
        $t->same('note', $inlineNote->type);
        $t->same('emph', $inlineNoteParagraph->children[1]->type);
        $t->same('easier', $inlineNoteParagraph->children[1]->children[0]->attr('text'));
        $t->same('link', $inlineNoteParagraph->children[3]->type);
        $t->same('http://google.com', $inlineNoteParagraph->children[3]->attr('url'));
        $t->same('code', $inlineNoteParagraph->children[5]->type);
        $t->same(']', $inlineNoteParagraph->children[5]->attr('text'));
        $t->contains('[bracketed text].', $inlineNoteParagraph->children[6]->attr('text'));
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
    'writes wordpress structured html table sections from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Structured HTML import table:</p>', $blocks);
        $t->contains('<figure class="wp-block-table"><table id="nordics" data-source="wikipedia"><colgroup><col style="width:30%"/><col style="width:30%"/><col style="width:20%"/><col style="width:20%"/></colgroup><thead><tr><th style="text-align:center">Name</th><th style="text-align:center">Capital</th><th style="text-align:center">Population', $blocks);
        $t->contains('<tbody><tr><th style="text-align:center">Denmark</th><td style="text-align:left">Copenhagen</td>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">States belonging to the <em>Nordics.</em></figcaption>', $blocks);
        $t->contains('<tfoot><tr><td style="text-align:center">Total</td><td style="text-align:left"></td><td style="text-align:left">27,376,022</td><td style="text-align:left">1,258,336</td></tr></tfoot>', $blocks);
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
        $t->same('paragraph', $segmented->children[1]->children[0]->children[1]->children[0]->type);
        $t->same('12', $segmented->children[1]->children[0]->children[1]->children[0]->attr('text'));
        $t->same('table_body', $segmented->children[2]->type);
        $t->same(['batch' => 'review'], $segmented->children[2]->attr('attributes'));
        $t->contains('<thead><tr><th>Batch</th><th>Posts</th><th>Status</th></tr></thead><tbody><tr><td>May archive</td><td><p>12</p></td><td>Published</td></tr></tbody><tbody><tr><td>June archive</td><td>8</td><td>Needs media review</td></tr></tbody>', $blocks);
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
        $t->contains('<a href="https://example.test/audit?post=42&amp;status=ready">https://example.test/audit?post=42&amp;status=ready</a>', $blocks);
        $t->contains('<a href="mailto:importer@example.test">importer@example.test</a>', $blocks);
    },
    'writes wordpress image blocks and inline media from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('<figure class="wp-block-image"><img src="https://example.test/uploads/release-frame.jpg" alt="Release archive frame" title="Release archive frame"/><figcaption>Release archive frame</figcaption></figure>', $blocks);
        $t->contains('<p>Inline media audit: <img src="https://example.test/uploads/thumb.jpg" alt="thumbnail" title="Thumbnail title"/> remains in paragraph text.</p>', $blocks);
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
    'writes wordpress loose list paragraphs from migration follow-up steps' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<li><p>Record reviewer follow-up.</p><p>Confirm shortcode cleanup in the migration log.</p></li>', $blocks);
    },
    'writes wordpress imported fancy ordered lists with nested starts' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<ol start="2"><li>Confirm source identifiers</li><li>Schedule staged import<ol start="4"><li>Review roman checkpoint</li><li>Approve nested audit</li></ol></li></ol>', $blocks);
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
        $t->contains('<tbody><tr><th>Queue</th><th>Items</th><th>Status</th></tr><tr><td>Posts</td><td>42</td><td>Ready</td></tr><tr><td colspan="3">Review body-local headers before publish</td></tr></tbody><tfoot><tr><th>Total</th><td>42</td><td>Ready</td></tr></tfoot>', $blocks);
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
    },
    'writes wordpress strikeout superscript and subscript from import review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Chemistry note: H<sub>2</sub>O import and a<sup><em>draft</em></sup> status need <del>legacy cleanup</del>.</p>', $blocks);
    },
    'writes wordpress smart punctuation from import review notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains("<p>Migration editor said, \u{201C}Don\u{2019}t flatten \u{2018}legacy\u{2019} captions\u{2026}\u{201D} Keep dates 1987\u{2013}1999 and one\u{2014}two review notes.</p>", $blocks);
    },
    'writes wordpress math and raw tex preservation markup from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<span class="math inline">\(x \in y\)</span>', $blocks);
        $t->contains('<span class="pandoc-raw-tex">\cite[22-23]{smith.1899}</span>', $blocks);
        $t->contains('<span class="math display">\[\alpha + \omega \times x^2\]</span>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-tex">\begin{tabular}{|l|l|}\hline', $blocks);
        $t->contains('Field &amp; Value \\\\ \hline', $blocks);
    },
    'writes wordpress entity decoded text without double escaping import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>Entity import note: AT&amp;T sponsor text and 4 &lt; 5 comparator stay visible for review.</p>', $blocks);
        $t->same(false, str_contains($blocks, 'AT&amp;amp;T'));
    },
    'writes wordpress code block markup for migration snippets' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:code -->', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">do_shortcode(&#039;[legacy-gallery]&#039;);</code></pre>', $blocks);
    },
    'writes wordpress html reader pre code imports from import notes' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<p>HTML reader legacy code export:</p>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">do_shortcode(&#039;[legacy-carousel]&#039;);' . "\n" . 'echo esc_html($title);</code></pre>', $blocks);
    },
    'writes wordpress code block markup for tab-indented legacy snippets' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read("Legacy importer:\n\n\t\techo esc_html(\$title);");
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:code -->', $blocks);
        $t->contains('<pre class="wp-block-code"><code>    echo esc_html($title);</code></pre>', $blocks);
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
