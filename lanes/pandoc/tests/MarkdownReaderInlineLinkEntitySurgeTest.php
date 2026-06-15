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

return $tests;
