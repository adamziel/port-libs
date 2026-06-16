<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$s1 = ' ';
$s2 = '  ';
$s3 = '   ';
$s4 = '    ';

$paragraph = static function (string $source): AstNode {
    $document = (new MarkdownReader())->read($source);

    return $document->children[0] ?? new AstNode('missing');
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$tabExpansionCases = [
    'one column lead' => ["a\tb", 'a' . $s3 . 'b'],
    'two column lead' => ["ab\tcd", 'ab' . $s2 . 'cd'],
    'three column lead' => ["abc\tde", 'abc' . $s1 . 'de'],
    'four column lead' => ["abcd\te", 'abcd' . $s4 . 'e'],
    'five column lead' => ["abcde\tf", 'abcde' . $s3 . 'f'],
    'six column lead' => ["abcdef\tg", 'abcdef' . $s2 . 'g'],
    'seven column lead' => ["abcdefg\th", 'abcdefg' . $s1 . 'h'],
    'eight column lead' => ["abcdefgh\ti", 'abcdefgh' . $s4 . 'i'],
    'label colon' => ["value:\t42", 'value:' . $s2 . '42'],
    'field separator' => ["field\t:\tvalue", 'field' . $s3 . ':' . $s3 . 'value'],
    'middle text' => ["left\tmiddle\tright", 'left' . $s4 . 'middle' . $s2 . 'right'],
    'source space after tab' => ["one\t two", 'one' . $s2 . 'two'],
    'entity after tab' => ["x\t&amp; y", 'x' . $s3 . '& y'],
    'numeric after tab' => ["key\t&#35;hash", 'key' . $s1 . '#hash'],
    'nbsp after tab' => ["col\t&nbsp;end", 'col' . $s1 . "\u{00A0}" . 'end'],
    'entity before tab' => ["AT&amp;T\tcopy", 'AT&T' . $s4 . 'copy'],
    'hex before tab' => ["lambda &#x3bb;\tdone", 'lambda ' . "\u{03BB}" . $s2 . 'done'],
    'quoted after tab' => ["q\t&quot;x&quot;", 'q' . $s3 . '"x"'],
    'two tab stops' => ["two\ttabs\there", 'two' . $s1 . 'tabs' . $s4 . 'here'],
    'punctuation columns' => ["a.b\tc/d", 'a.b' . $s1 . 'c/d'],
];

$continuationCases = [
    'four-space continuation' => ["alpha\n    beta", 'alpha beta', ['text', 'softbreak', 'text']],
    'tab continuation' => ["alpha\n\tbeta", 'alpha beta', ['text', 'softbreak', 'text']],
    'one-space tab continuation' => ["alpha\n \tbeta", 'alpha beta', ['text', 'softbreak', 'text']],
    'two-space tab continuation' => ["alpha\n  \tbeta", 'alpha beta', ['text', 'softbreak', 'text']],
    'three-space tab continuation' => ["alpha\n   \tbeta", 'alpha beta', ['text', 'softbreak', 'text']],
    'entity continuation' => ["alpha\n    &amp; beta", 'alpha & beta', ['text', 'softbreak', 'text']],
    'nbsp continuation' => ["alpha\n\t&nbsp;beta", 'alpha ' . "\u{00A0}" . 'beta', ['text', 'softbreak', 'text']],
    'numeric continuation' => ["alpha\n\t&#35; beta", 'alpha # beta', ['text', 'softbreak', 'text']],
    'three-line space continuation' => ["alpha\n    beta\ngamma", 'alpha beta gamma', ['text', 'softbreak', 'text', 'softbreak', 'text']],
    'three-line tab continuation' => ["alpha\n\tbeta\n\tgamma", 'alpha beta gamma', ['text', 'softbreak', 'text', 'softbreak', 'text']],
    'mixed indentation continuation' => ["alpha\n    beta\n\tgamma", 'alpha beta gamma', ['text', 'softbreak', 'text', 'softbreak', 'text']],
    'trimmed source space continuation' => ["alpha\n    beta   ", 'alpha beta', ['text', 'softbreak', 'text']],
    'trimmed source tab continuation' => ["alpha\n\tbeta\t", 'alpha beta', ['text', 'softbreak', 'text']],
    'literal punctuation continuation' => ["alpha\n    # literal marker", 'alpha # literal marker', ['text', 'softbreak', 'text']],
    'literal list marker continuation' => ["alpha\n    - literal marker", 'alpha - literal marker', ['text', 'softbreak', 'text']],
    'literal ordered marker continuation' => ["alpha\n    1. literal marker", 'alpha 1. literal marker', ['text', 'softbreak', 'text']],
    'escaped punctuation continuation' => ["alpha\n    \\* literal star", 'alpha * literal star', ['text', 'softbreak', 'text']],
    'reference-looking continuation' => ["alpha\n    [literal]", 'alpha [literal]', ['text', 'softbreak', 'text']],
    'autolink-looking continuation' => ["alpha\n    <literal>", 'alpha <literal>', ['text', 'softbreak', 'text']],
    'nonbreaking continuation tail' => ["alpha\n    beta&nbsp;", 'alpha beta' . "\u{00A0}", ['text', 'softbreak', 'text']],
];

$hardBreakCases = [
    'space hard break' => ["alpha  \nbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'space hard break indented continuation' => ["alpha  \n    beta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'space hard break tab continuation' => ["alpha  \n\tbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'tab-derived hard break' => ["alpha\t\nbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'space-tab hard break' => ["alpha \t\nbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'tab-space hard break' => ["alpha\t \nbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'tab hard break entity continuation' => ["alpha\t\n    &amp; beta", 'alpha' . "\n" . '& beta', ['text', 'linebreak', 'text']],
    'tab hard break nbsp continuation' => ["alpha\t\n\t&nbsp;beta", 'alpha' . "\n" . "\u{00A0}" . 'beta', ['text', 'linebreak', 'text']],
    'backslash hard break' => ["alpha\\\nbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'backslash hard break indented continuation' => ["alpha\\\n    beta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'backslash hard break tab continuation' => ["alpha\\\n\tbeta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'backslash hard break entity continuation' => ["alpha\\\n    &copy; beta", 'alpha' . "\n" . "\u{00A9}" . ' beta', ['text', 'linebreak', 'text']],
    'three-space hard break' => ["alpha   \n beta", 'alpha' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'tab-derived hard break after punctuation' => ["alpha.\t\n beta", 'alpha.' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'tab-derived hard break after entity source' => ["AT&amp;T\t\nbeta", 'AT&T' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'tab-derived hard break after numeric source' => ["No. &#35;\t\nbeta", 'No. #' . "\n" . 'beta', ['text', 'linebreak', 'text']],
    'two consecutive hard breaks' => ["alpha\t\nbeta  \ngamma", 'alpha' . "\n" . 'beta' . "\n" . 'gamma', ['text', 'linebreak', 'text', 'linebreak', 'text']],
    'hard then soft break' => ["alpha\t\nbeta\ngamma", 'alpha' . "\n" . 'beta gamma', ['text', 'linebreak', 'text', 'softbreak', 'text']],
    'soft then hard break' => ["alpha\nbeta\t\ngamma", 'alpha beta' . "\n" . 'gamma', ['text', 'softbreak', 'text', 'linebreak', 'text']],
    'hard break with escaped continuation text' => ["alpha\t\n    \\# beta", 'alpha' . "\n" . '# beta', ['text', 'linebreak', 'text']],
];

$entityWhitespaceCases = [
    'ampersand between tabs' => ["A\t&amp;\tB", 'A' . $s3 . '&' . $s3 . 'B'],
    'nbsp between tabs' => ["A\t&nbsp;\tB", 'A' . $s3 . "\u{00A0}" . $s2 . 'B'],
    'numeric hash between tabs' => ["A\t&#35;\tB", 'A' . $s3 . '#' . $s3 . 'B'],
    'hex lambda between tabs' => ["A\t&#x3bb;\tB", 'A' . $s3 . "\u{03BB}" . $s1 . 'B'],
    'named copy after spaces' => ["A  \t&copy;", 'A' . $s3 . "\u{00A9}"],
    'invalid numeric after tab' => ["A\t&#0;", 'A' . $s3 . "\u{FFFD}"],
    'invalid hex after tab' => ["A\t&#x110000;", 'A' . $s3 . "\u{FFFD}"],
    'surrogate decimal after tab' => ["A\t&#55296;", 'A' . $s3 . "\u{FFFD}"],
    'surrogate hex after tab' => ["A\t&#xDFFF;", 'A' . $s3 . "\u{FFFD}"],
    'quote after tab' => ["A\t&quot;B&quot;", 'A' . $s3 . '"B"'],
    'apostrophe after tab' => ["A\t&apos;B&apos;", "A" . $s3 . "'B'"],
    'less greater after tab' => ["A\t&lt;B&gt;", 'A' . $s3 . '<B>'],
    'mdash after tab' => ["A\t&mdash;B", 'A' . $s3 . "\u{2014}" . 'B'],
    'ellipsis after tab' => ["A\t&hellip;B", 'A' . $s3 . "\u{2026}" . 'B'],
    'nbsp softbreak tab continuation' => ["A&nbsp;\n\tB", 'A' . "\u{00A0}" . ' B'],
    'entity softbreak tab continuation' => ["A&amp;\n\tB", 'A& B'],
    'numeric softbreak tab continuation' => ["A&#35;\n\tB", 'A# B'],
    'nbsp hardbreak tab continuation' => ["A&nbsp; \t\n\tB", 'A' . "\u{00A0}" . "\n" . 'B'],
    'entity hardbreak tab continuation' => ["A&amp;\t\n\tB", 'A&' . "\n" . 'B'],
    'numeric hardbreak tab continuation' => ["A&#35;\t\n\tB", 'A#' . "\n" . 'B'],
];

return [
    'maps upstream markdown paragraph tab expansion cases' => static function (TestRunner $t) use ($paragraph, $tabExpansionCases): void {
        foreach ($tabExpansionCases as $name => [$source, $expected]) {
            $node = $paragraph($source);

            $t->same('paragraph', $node->type, $name);
            $t->same($expected, $node->attr('text'), $name);
            $t->same('text', $node->children[0]->type ?? 'missing', $name);
            $t->same($expected, $node->children[0]->attr('text'), $name);
        }
    },

    'maps upstream markdown indented paragraph continuation cases' => static function (TestRunner $t) use ($childTypes, $continuationCases): void {
        foreach ($continuationCases as $name => [$source, $expectedText, $expectedTypes]) {
            $document = (new MarkdownReader())->read($source);
            $node = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children), $name . ' should remain one paragraph block');
            $t->same('paragraph', $node->type, $name);
            $t->same($expectedText, $node->attr('text'), $name);
            $t->same($expectedTypes, $childTypes($node), $name);
        }
    },

    'maps upstream markdown tab and backslash hard line break cases' => static function (TestRunner $t) use ($childTypes, $hardBreakCases): void {
        foreach ($hardBreakCases as $name => [$source, $expectedText, $expectedTypes]) {
            $document = (new MarkdownReader())->read($source);
            $node = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children), $name . ' should remain one paragraph block');
            $t->same('paragraph', $node->type, $name);
            $t->same($expectedText, $node->attr('text'), $name);
            $t->same($expectedTypes, $childTypes($node), $name);
        }
    },

    'maps upstream markdown entity whitespace normalization cases' => static function (TestRunner $t) use ($paragraph, $entityWhitespaceCases): void {
        foreach ($entityWhitespaceCases as $name => [$source, $expected]) {
            $node = $paragraph($source);

            $t->same('paragraph', $node->type, $name);
            $t->same($expected, $node->attr('text'), $name);
        }
    },

    'records markdown whitespace linebreak entity mapped-case count' => static function (TestRunner $t) use (
        $tabExpansionCases,
        $continuationCases,
        $hardBreakCases,
        $entityWhitespaceCases
    ): void {
        $t->same(
            80,
            count($tabExpansionCases)
                + count($continuationCases)
                + count($hardBreakCases)
                + count($entityWhitespaceCases)
        );
    },
];
