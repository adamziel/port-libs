<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$readFirstLink = static function (string $markdown): AstNode {
    $document = (new MarkdownReader())->read($markdown);
    foreach ($document->children as $block) {
        foreach ($block->children as $child) {
            if ($child->type === 'link') {
                return $child;
            }
        }
    }

    return new AstNode('missing');
};

$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'punct';
};

$findFirstNodeOfType = static function (AstNode $node, string $type) use (&$findFirstNodeOfType): ?AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $found = $findFirstNodeOfType($child, $type);
        if ($found !== null) {
            return $found;
        }
    }

    return null;
};

$readFirstNodeOfType = static function (string $markdown, string $type) use ($findFirstNodeOfType): AstNode {
    $document = (new MarkdownReader())->read($markdown);
    $node = $findFirstNodeOfType($document, $type);

    return $node ?? new AstNode('missing');
};

$inlineTypes = static fn (array $nodes): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
);

$plainInlineText = static function (array $nodes) use (&$plainInlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'raw_tex') {
            $text .= (string) $node->attr('tex', '');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainInlineText($node->children);
    }

    return $text;
};

$inlineText = $plainInlineText;

$tests = [];

$escapedPunctuationCases = [
    'bang' => ['\\!', '!'],
    'double quote' => ['\\"', '"'],
    'hash' => ['\\#', '#'],
    'dollar' => ['\\$', '$'],
    'percent' => ['\\%', '%'],
    'ampersand' => ['\\&', '&'],
    'apostrophe' => ["\\'", "'"],
    'open paren' => ['\\(', '('],
    'close paren' => ['\\)', ')'],
    'asterisk' => ['\\*', '*'],
    'plus' => ['\\+', '+'],
    'comma' => ['\\,', ','],
    'minus' => ['\\-', '-'],
    'period' => ['\\.', '.'],
    'slash' => ['\\/', '/'],
    'colon' => ['\\:', ':'],
    'semicolon' => ['\\;', ';'],
    'less than' => ['\\<', '<'],
    'equals' => ['\\=', '='],
    'greater than' => ['\\>', '>'],
    'question' => ['\\?', '?'],
    'at sign' => ['\\@', '@'],
    'caret' => ['\\^', '^'],
    'underscore' => ['\\_', '_'],
    'backtick' => ['\\`', '`'],
    'open brace' => ['\\{', '{'],
    'pipe' => ['\\|', '|'],
    'close brace' => ['\\}', '}'],
    'tilde' => ['\\~', '~'],
];

foreach ($escapedPunctuationCases as $name => [$escaped, $literal]) {
    $tests["maps upstream reference label escaped {$name}"] = static function (TestRunner $t) use ($readFirstLink, $slug, $name, $escaped, $literal): void {
        $url = '/escaped-reference-' . $slug($name);
        $titleLiteral = $literal === '"' ? '\\"' : $literal;
        $markdown = "[escaped {$escaped} label]\n\n[escaped {$literal} label]: {$url} \"Escaped {$titleLiteral} title\"";
        $link = $readFirstLink($markdown);

        $t->same('link', $link->type);
        $t->same($url, $link->attr('url'));
        $t->same("Escaped {$literal} title", $link->attr('title'));
        $t->same("escaped {$literal} label", $link->children[0]->attr('text'));
    };
}

$entityCases = [
    'amp use' => ['AT&amp;T', 'AT&T', 'AT&T'],
    'amp definition' => ['AT&T', 'AT&amp;T', 'AT&T'],
    'copyright named use' => ['Copyright &copy;', "Copyright \u{00A9}", "Copyright \u{00A9}"],
    'copyright named definition' => ["Copyright \u{00A9}", 'Copyright &copy;', "Copyright \u{00A9}"],
    'registered named use' => ['Registered &reg;', "Registered \u{00AE}", "Registered \u{00AE}"],
    'registered named definition' => ["Registered \u{00AE}", 'Registered &reg;', "Registered \u{00AE}"],
    'euro named use' => ['Price &euro;', "Price \u{20AC}", "Price \u{20AC}"],
    'euro named definition' => ["Price \u{20AC}", 'Price &euro;', "Price \u{20AC}"],
    'lambda hex use' => ['Greek &#x3bb;', "Greek \u{03BB}", "Greek \u{03BB}"],
    'lambda hex definition' => ["Greek \u{03BB}", 'Greek &#x3bb;', "Greek \u{03BB}"],
    'lambda decimal use' => ['Decimal &#955;', "Decimal \u{03BB}", "Decimal \u{03BB}"],
    'lambda decimal definition' => ["Decimal \u{03BB}", 'Decimal &#955;', "Decimal \u{03BB}"],
    'less than use' => ['Less &lt; marker', 'Less < marker', 'Less < marker'],
    'less than definition' => ['Less < marker', 'Less &lt; marker', 'Less < marker'],
    'greater than use' => ['Greater &gt; marker', 'Greater > marker', 'Greater > marker'],
    'greater than definition' => ['Greater > marker', 'Greater &gt; marker', 'Greater > marker'],
    'quote use' => ['Quote &quot; marker', 'Quote " marker', 'Quote " marker'],
    'quote definition' => ['Quote " marker', 'Quote &quot; marker', 'Quote " marker'],
    'apostrophe use' => ['Apostrophe &apos; marker', "Apostrophe ' marker", "Apostrophe ' marker"],
    'mdash use' => ['Dash &mdash; marker', "Dash \u{2014} marker", "Dash \u{2014} marker"],
    'ellipsis definition' => ["Ellipsis \u{2026} marker", 'Ellipsis &hellip; marker', "Ellipsis \u{2026} marker"],
];

foreach ($entityCases as $name => [$useLabel, $definitionLabel, $expectedText]) {
    $tests["maps upstream reference label entity {$name}"] = static function (TestRunner $t) use ($readFirstLink, $slug, $name, $useLabel, $definitionLabel, $expectedText): void {
        $url = '/entity-reference-' . $slug($name);
        $markdown = "[{$useLabel}]\n\n[{$definitionLabel}]: {$url} \"Entity {$name}\"";
        $link = $readFirstLink($markdown);

        $t->same('link', $link->type);
        $t->same($url, $link->attr('url'));
        $t->same("Entity {$name}", $link->attr('title'));
        $t->same($expectedText, $link->children[0]->attr('text'));
    };
}

$codeSpanBracketLabelCases = [
    'close bracket only' => ['`]`', ']'],
    'open bracket only' => ['`[`', '['],
    'bracket pair' => ['`[]`', '[]'],
    'double close run' => ['`]]`', ']]'],
    'double open run' => ['`[[`', '[['],
    'mixed bracket run' => ['`[x]`', '[x]'],
    'text before close' => ['alpha `]` beta', 'alpha ] beta'],
    'text before open' => ['alpha `[` beta', 'alpha [ beta'],
    'two code spans' => ['left `]` right `[`', 'left ] right ['],
    'trailing close code' => ['trail `]`', 'trail ]'],
    'leading open code' => ['`[` lead', '[ lead'],
    'multitick close' => ['``]``', ']'],
    'multitick open' => ['``[``', '['],
    'multitick pair' => ['``[]``', '[]'],
    'inner backtick close' => ['`` `]` ``', '`]`'],
    'inner backtick open' => ['`` `[` ``', '`[`'],
    'spaced bracket pair' => ['`` [] ``', '[]'],
    'code before nested word' => ['code `]` [word]', 'code ] [word]'],
    'nested word before code' => ['[word] code `]`', '[word] code ]'],
    'multiple close code spans' => ['`]` and `]` again', '] and ] again'],
];

foreach ($codeSpanBracketLabelCases as $name => [$label, $expectedText]) {
    $tests["maps upstream inline link code-span bracket label {$name}"] = static function (TestRunner $t) use ($readFirstLink, $plainInlineText, $slug, $name, $label, $expectedText): void {
        $url = '/code-bracket-label-' . $slug($name);
        $markdown = "[{$label}]({$url} \"Code {$name}\")";
        $link = $readFirstLink($markdown);
        $childTypes = array_map(static fn (AstNode $node): string => $node->type, $link->children);

        $t->same('link', $link->type, $name);
        $t->same($url, $link->attr('url'), $name);
        $t->same("Code {$name}", $link->attr('title'), $name);
        $t->same($expectedText, $plainInlineText($link->children), $name);
        $t->true(in_array('code', $childTypes, true), $name);
    };
}

$rawHtmlBracketLabelCases = [
    'span close attribute' => ['<span data-close=]>alpha</span>'],
    'span open attribute' => ['<span data-open=[>alpha</span>'],
    'span both attribute' => ['<span data-pair=[]>alpha</span>'],
    'custom close attribute' => ['<x-review data-close=]>alpha</x-review>'],
    'custom open attribute' => ['<x-review data-open=[>alpha</x-review>'],
    'anchor close query' => ['<a href=/search?close]>alpha</a>'],
    'anchor open query' => ['<a href=/search?open[>alpha</a>'],
    'image close alt' => ['<img alt=]/>'],
    'image open alt' => ['<img alt=[/>'],
    'input close value' => ['<input value=]>'],
    'input open value' => ['<input value=[>'],
    'br close data' => ['<br data-break=]>after'],
    'wbr open data' => ['<wbr data-break=[>after'],
    'delete close attribute' => ['<del data-close=]>alpha</del>'],
    'insert open attribute' => ['<ins data-open=[>alpha</ins>'],
    'cdata close bracket' => ['<![CDATA[ ] ]]>tail'],
    'cdata open bracket' => ['<![CDATA[ [ data ]]>tail'],
    'processing close bracket' => ['<?review ] ?>tail'],
    'declaration close bracket' => ['<!DECL ]>tail'],
    'mixed html bracket sources' => ['<span data-close=]>x</span><x-open data-open=[>'],
];

foreach ($rawHtmlBracketLabelCases as $name => [$label]) {
    $tests["maps upstream inline link raw-html bracket label {$name}"] = static function (TestRunner $t) use ($readFirstLink, $plainInlineText, $slug, $name, $label): void {
        $url = '/html-bracket-label-' . $slug($name);
        $markdown = "[{$label}]({$url} \"Html {$name}\")";
        $link = $readFirstLink($markdown);

        $t->same('link', $link->type, $name);
        $t->same($url, $link->attr('url'), $name);
        $t->same("Html {$name}", $link->attr('title'), $name);
        $t->same($label, $plainInlineText($link->children), $name);
    };
}

$angleDestinationParenCases = [
    'close paren path' => ['<https://example.test/a)b>', 'https://example.test/a)b'],
    'close paren query' => ['<https://example.test/search?q=a)b>', 'https://example.test/search?q=a)b'],
    'close paren fragment' => ['<https://example.test/page#frag)ment>', 'https://example.test/page#frag)ment'],
    'double close path' => ['<https://example.test/a)b)c>', 'https://example.test/a)b)c'],
    'leading close segment' => ['<https://example.test/)lead>', 'https://example.test/)lead'],
    'space before close' => ['<https://example.test/a b)c>', 'https://example.test/a%20b)c'],
    'relative close path' => ['</docs/a)b>', '/docs/a)b'],
    'fragment close path' => ['<#section)one>', '#section)one'],
    'mailto close local' => ['<mailto:review)team@example.test>', 'mailto:review)team@example.test'],
    'doi close suffix' => ['<doi:10.1000/foo)bar>', 'doi:10.1000/foo)bar'],
    'ftp close path' => ['<ftp://example.test/a)b>', 'ftp://example.test/a)b'],
    'urn close component' => ['<urn:review:a)b>', 'urn:review:a)b'],
    'nested literal close' => ['<https://example.test/(a)b)c>', 'https://example.test/(a)b)c'],
    'encoded then close' => ['<https://example.test/a%20b)c>', 'https://example.test/a%20b)c'],
    'semicolon close query' => ['<https://example.test/a;b)c>', 'https://example.test/a;b)c'],
    'colon close path' => ['<https://example.test/a:b)c>', 'https://example.test/a:b)c'],
    'at close path' => ['<https://example.test/a@b)c>', 'https://example.test/a@b)c'],
    'tilde close path' => ['<https://example.test/a~b)c>', 'https://example.test/a~b)c'],
    'underscore close path' => ['<https://example.test/a_b)c>', 'https://example.test/a_b)c'],
    'dash close path' => ['<https://example.test/a-b)c>', 'https://example.test/a-b)c'],
];

foreach ($angleDestinationParenCases as $name => [$destination, $expectedUrl]) {
    $tests["maps upstream inline link angle destination paren {$name}"] = static function (TestRunner $t) use ($readFirstLink, $slug, $name, $destination, $expectedUrl): void {
        $markdown = "[angle {$name}]({$destination} \"Angle {$name}\")";
        $link = $readFirstLink($markdown);

        $t->same('link', $link->type, $name);
        $t->same($expectedUrl, $link->attr('url'), $name);
        $t->same("Angle {$name}", $link->attr('title'), $name);
        $t->same("angle {$name}", $link->children[0]->attr('text'), $name);
    };
}

$tests['maps upstream entity normalized reference labels through wordpress handoff'] = static function (TestRunner $t): void {
    $document = (new MarkdownReader())->read(implode("\n", [
        '[AT&amp;T] and [Greek &#x3bb;].',
        '',
        '[AT&T]: /entity-reference-amp "Amp title"',
        '[Greek ' . "\u{03BB}" . ']: /entity-reference-lambda "Lambda title"',
    ]));
    $blocks = (new WordPressBlockWriter())->write($document);

    $t->contains('<a href="/entity-reference-amp" title="Amp title">AT&amp;T</a>', $blocks);
    $t->contains('<a href="/entity-reference-lambda" title="Lambda title">Greek ' . "\u{03BB}" . '</a>', $blocks);
};

$detailedCodeSpanBracketLabelCases = [
    'inline code close bracket' => ['kind' => 'link', 'markdown' => '[`]`](/code-close)', 'url' => '/code-close', 'text' => ']', 'types' => ['code']],
    'inline code open bracket' => ['kind' => 'link', 'markdown' => '[`[`](/code-open)', 'url' => '/code-open', 'text' => '[', 'types' => ['code']],
    'inline code balanced brackets' => ['kind' => 'link', 'markdown' => '[`[x]`](/code-balanced)', 'url' => '/code-balanced', 'text' => '[x]', 'types' => ['code']],
    'inline mixed close bracket' => ['kind' => 'link', 'markdown' => '[pre `]` post](/code-mixed-close)', 'url' => '/code-mixed-close', 'text' => 'pre ] post', 'types' => ['text', 'code', 'text']],
    'inline mixed open bracket' => ['kind' => 'link', 'markdown' => '[pre `[` post](/code-mixed-open)', 'url' => '/code-mixed-open', 'text' => 'pre [ post', 'types' => ['text', 'code', 'text']],
    'inline mixed balanced brackets' => ['kind' => 'link', 'markdown' => '[pre `[x]` post](/code-mixed-balanced)', 'url' => '/code-mixed-balanced', 'text' => 'pre [x] post', 'types' => ['text', 'code', 'text']],
    'inline double tick close bracket' => ['kind' => 'link', 'markdown' => '[``]` bracket``](/code-double-tick)', 'url' => '/code-double-tick', 'text' => ']` bracket', 'types' => ['code']],
    'inline code link syntax' => ['kind' => 'link', 'markdown' => '[`[link](x)`](/code-link-syntax)', 'url' => '/code-link-syntax', 'text' => '[link](x)', 'types' => ['code']],
    'inline code escaped close bracket' => ['kind' => 'link', 'markdown' => '[`\\]`](/code-escaped-close)', 'url' => '/code-escaped-close', 'text' => '\\]', 'types' => ['code']],
    'inline code then emphasis' => ['kind' => 'link', 'markdown' => '[`]` *em*](/code-emph)', 'url' => '/code-emph', 'text' => '] em', 'types' => ['code', 'text', 'emph']],
    'inline strong then code' => ['kind' => 'link', 'markdown' => '[**strong** `[`](/code-strong)', 'url' => '/code-strong', 'text' => 'strong [', 'types' => ['strong', 'text', 'code']],
    'inline softbreak before code bracket' => ['kind' => 'link', 'markdown' => "[line\n`]`](/code-softbreak)", 'url' => '/code-softbreak', 'text' => "line\n]", 'types' => ['text', 'softbreak', 'code']],
    'inline hardbreak before code bracket' => ['kind' => 'link', 'markdown' => "[line  \n`]`](/code-hardbreak)", 'url' => '/code-hardbreak', 'text' => "line\n]", 'types' => ['text', 'linebreak', 'code']],
    'inline title decodes entity after code bracket' => ['kind' => 'link', 'markdown' => '[`]`](/code-title "A &amp; B")', 'url' => '/code-title', 'title' => 'A & B', 'text' => ']', 'types' => ['code']],
    'inline angle destination after code bracket' => ['kind' => 'link', 'markdown' => '[`[`](<a b>)', 'url' => 'a%20b', 'text' => '[', 'types' => ['code']],
    'reference code close bracket' => ['kind' => 'link', 'markdown' => "[`]`][ref-close]\n\n[ref-close]: /ref-close \"Close title\"", 'url' => '/ref-close', 'title' => 'Close title', 'text' => ']', 'types' => ['code']],
    'reference code open bracket' => ['kind' => 'link', 'markdown' => "[`[`][ref-open]\n\n[ref-open]: /ref-open", 'url' => '/ref-open', 'text' => '[', 'types' => ['code']],
    'reference code balanced brackets' => ['kind' => 'link', 'markdown' => "[`[x]`][ref-balanced]\n\n[ref-balanced]: /ref-balanced", 'url' => '/ref-balanced', 'text' => '[x]', 'types' => ['code']],
    'reference mixed close bracket' => ['kind' => 'link', 'markdown' => "[pre `]` post][ref-mixed-close]\n\n[ref-mixed-close]: /ref-mixed-close", 'url' => '/ref-mixed-close', 'text' => 'pre ] post', 'types' => ['text', 'code', 'text']],
    'reference mixed open bracket' => ['kind' => 'link', 'markdown' => "[pre `[` post][ref-mixed-open]\n\n[ref-mixed-open]: /ref-mixed-open", 'url' => '/ref-mixed-open', 'text' => 'pre [ post', 'types' => ['text', 'code', 'text']],
    'reference mixed balanced brackets' => ['kind' => 'link', 'markdown' => "[pre `[x]` post][ref-mixed-balanced]\n\n[ref-mixed-balanced]: /ref-mixed-balanced", 'url' => '/ref-mixed-balanced', 'text' => 'pre [x] post', 'types' => ['text', 'code', 'text']],
    'reference code link syntax' => ['kind' => 'link', 'markdown' => "[`[link](x)`][ref-link-syntax]\n\n[ref-link-syntax]: /ref-link-syntax", 'url' => '/ref-link-syntax', 'text' => '[link](x)', 'types' => ['code']],
    'reference code escaped close bracket' => ['kind' => 'link', 'markdown' => "[`\\]`][ref-escaped-close]\n\n[ref-escaped-close]: /ref-escaped-close", 'url' => '/ref-escaped-close', 'text' => '\\]', 'types' => ['code']],
    'reference code then emphasis' => ['kind' => 'link', 'markdown' => "[`]` *em*][ref-emph]\n\n[ref-emph]: /ref-emph", 'url' => '/ref-emph', 'text' => '] em', 'types' => ['code', 'text', 'emph']],
    'reference strong then code' => ['kind' => 'link', 'markdown' => "[**strong** `[`][ref-strong]\n\n[ref-strong]: /ref-strong", 'url' => '/ref-strong', 'text' => 'strong [', 'types' => ['strong', 'text', 'code']],
    'reference softbreak before code bracket' => ['kind' => 'link', 'markdown' => "[line\n`]`][ref-softbreak]\n\n[ref-softbreak]: /ref-softbreak", 'url' => '/ref-softbreak', 'text' => "line\n]", 'types' => ['text', 'softbreak', 'code']],
    'reference hardbreak before code bracket' => ['kind' => 'link', 'markdown' => "[line  \n`]`][ref-hardbreak]\n\n[ref-hardbreak]: /ref-hardbreak", 'url' => '/ref-hardbreak', 'text' => "line\n]", 'types' => ['text', 'linebreak', 'code']],
    'reference angle destination after code bracket' => ['kind' => 'link', 'markdown' => "[`[`][ref-angle]\n\n[ref-angle]: <a b> \"Angle title\"", 'url' => 'a%20b', 'title' => 'Angle title', 'text' => '[', 'types' => ['code']],
    'reference entity title after code bracket' => ['kind' => 'link', 'markdown' => "[`]`][ref-entity]\n\n[ref-entity]: /ref-entity \"A &amp; B\"", 'url' => '/ref-entity', 'title' => 'A & B', 'text' => ']', 'types' => ['code']],
    'reference empty destination after code bracket' => ['kind' => 'link', 'markdown' => "[`]`][ref-empty]\n\n[ref-empty]: <> \"Empty title\"", 'url' => '', 'title' => 'Empty title', 'text' => ']', 'types' => ['code']],
    'image code close bracket' => ['kind' => 'image', 'markdown' => '![`]`](/img-close "Close image")', 'url' => '/img-close', 'title' => 'Close image', 'text' => ']', 'types' => ['code']],
    'image code open bracket' => ['kind' => 'image', 'markdown' => '![`[`](/img-open)', 'url' => '/img-open', 'text' => '[', 'types' => ['code']],
    'image code balanced brackets' => ['kind' => 'image', 'markdown' => '![`[x]`](/img-balanced)', 'url' => '/img-balanced', 'text' => '[x]', 'types' => ['code']],
    'image mixed close bracket' => ['kind' => 'image', 'markdown' => '![alt `]` text](/img-mixed-close)', 'url' => '/img-mixed-close', 'text' => 'alt ] text', 'types' => ['text', 'code', 'text']],
    'image mixed open bracket' => ['kind' => 'image', 'markdown' => '![alt `[` text](/img-mixed-open)', 'url' => '/img-mixed-open', 'text' => 'alt [ text', 'types' => ['text', 'code', 'text']],
    'image mixed balanced brackets' => ['kind' => 'image', 'markdown' => '![alt `[x]` text](/img-mixed-balanced)', 'url' => '/img-mixed-balanced', 'text' => 'alt [x] text', 'types' => ['text', 'code', 'text']],
    'image reference code close bracket' => ['kind' => 'image', 'markdown' => "![`]`][img-ref-close]\n\n[img-ref-close]: /img-ref-close \"Ref image\"", 'url' => '/img-ref-close', 'title' => 'Ref image', 'text' => ']', 'types' => ['code']],
    'image reference code open bracket' => ['kind' => 'image', 'markdown' => "![`[`][img-ref-open]\n\n[img-ref-open]: /img-ref-open", 'url' => '/img-ref-open', 'text' => '[', 'types' => ['code']],
    'image reference angle destination' => ['kind' => 'image', 'markdown' => "![`[`][img-ref-angle]\n\n[img-ref-angle]: <img path> \"Image angle\"", 'url' => 'img%20path', 'title' => 'Image angle', 'text' => '[', 'types' => ['code']],
    'image title decodes entity after code bracket' => ['kind' => 'image', 'markdown' => '![`]`](/img-title "A &amp; B")', 'url' => '/img-title', 'title' => 'A & B', 'text' => ']', 'types' => ['code']],
    'nested double bracket inline link' => ['kind' => 'link', 'markdown' => '[[foo]](/nested-one)', 'url' => '/nested-one', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket suffix inline link' => ['kind' => 'link', 'markdown' => '[[foo] suffix](/nested-suffix)', 'url' => '/nested-suffix', 'text' => '[foo] suffix', 'types' => ['text'], 'classes' => null],
    'nested double bracket deep inline link' => ['kind' => 'link', 'markdown' => '[[foo [bar]]](/nested-deep)', 'url' => '/nested-deep', 'text' => '[foo [bar]]', 'types' => ['text'], 'classes' => null],
    'nested double bracket angle destination' => ['kind' => 'link', 'markdown' => '[[foo]](<nested path>)', 'url' => 'nested%20path', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket titled inline link' => ['kind' => 'link', 'markdown' => '[[foo]](/nested-title "Nested &amp; title")', 'url' => '/nested-title', 'title' => 'Nested & title', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket empty destination' => ['kind' => 'link', 'markdown' => '[[foo]](<> "Empty nested")', 'url' => '', 'title' => 'Empty nested', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket reference link' => ['kind' => 'link', 'markdown' => "[[foo]][nested-ref]\n\n[nested-ref]: /nested-ref", 'url' => '/nested-ref', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket titled reference link' => ['kind' => 'link', 'markdown' => "[[foo]][nested-title-ref]\n\n[nested-title-ref]: /nested-title-ref \"Nested ref title\"", 'url' => '/nested-title-ref', 'title' => 'Nested ref title', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket empty reference link' => ['kind' => 'link', 'markdown' => "[[foo]][nested-empty-ref]\n\n[nested-empty-ref]: <> \"Empty nested ref\"", 'url' => '', 'title' => 'Empty nested ref', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
    'nested double bracket angle reference link' => ['kind' => 'link', 'markdown' => "[[foo]][nested-angle-ref]\n\n[nested-angle-ref]: <nested ref path> \"Angle nested ref\"", 'url' => 'nested%20ref%20path', 'title' => 'Angle nested ref', 'text' => '[foo]', 'types' => ['text'], 'classes' => null],
];

$tests['maps upstream code-span bracket labels and nested double bracket links'] =
    static function (TestRunner $t) use ($detailedCodeSpanBracketLabelCases, $readFirstNodeOfType, $inlineTypes, $inlineText): void {
        $mapped = 0;
        foreach ($detailedCodeSpanBracketLabelCases as $name => $case) {
            $node = $readFirstNodeOfType($case['markdown'], $case['kind']);

            $t->same($case['kind'], $node->type, $name . ' node type');
            $t->same($case['url'], $node->attr('url'), $name . ' url');
            $t->same($case['title'] ?? null, $node->attr('title'), $name . ' title');
            if (array_key_exists('classes', $case)) {
                $t->same($case['classes'], $node->attr('classes'), $name . ' classes');
            }

            $t->same($case['types'], $inlineTypes($node->children), $name . ' inline types');
            $t->same($case['text'], $inlineText($node->children), $name . ' inline text');
            if ($case['kind'] === 'image') {
                $t->same($case['text'], $node->attr('caption'), $name . ' image caption');
                $t->same($case['text'], $node->attr('alt'), $name . ' image alt');
            }
            $mapped++;
        }

        $t->same(50, $mapped);
    };

$tests['records markdown inline link entity surge mapped-case count'] = static function (TestRunner $t) use ($escapedPunctuationCases, $entityCases, $codeSpanBracketLabelCases, $rawHtmlBracketLabelCases, $angleDestinationParenCases): void {
    $t->same(
        110,
        count($escapedPunctuationCases)
            + count($entityCases)
            + count($codeSpanBracketLabelCases)
            + count($rawHtmlBracketLabelCases)
            + count($angleDestinationParenCases)
    );
};

return $tests;
