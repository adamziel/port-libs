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
        $markdown = "[escaped {$escaped} label]\n\n[escaped {$literal} label]: {$url} \"Escaped {$literal} title\"";
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
