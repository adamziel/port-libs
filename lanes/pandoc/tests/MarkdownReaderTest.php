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
        $t->contains('<!-- Preserve migration audit marker -->', $blocks);
        $t->contains('<hr class="legacy-import-divider" />', $blocks);
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
    'writes wordpress code block markup for migration snippets' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
        $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($fixture));

        $t->contains('<!-- wp:code -->', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-php">do_shortcode(&#039;[legacy-gallery]&#039;);</code></pre>', $blocks);
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
