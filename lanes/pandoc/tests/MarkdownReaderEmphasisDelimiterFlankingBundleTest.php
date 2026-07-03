<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return list<string>
 */
$describeInlines = static function (array $nodes) use (&$describeInlines): array {
    $described = [];
    foreach ($nodes as $node) {
        if ($node->type === 'text') {
            $described[] = 'text:' . $node->attr('text', '');
            continue;
        }

        if ($node->type === 'emph' || $node->type === 'strong') {
            $described[] = $node->type . '(' . implode('|', $describeInlines($node->children)) . ')';
            continue;
        }

        $described[] = $node->type;
    }

    return $described;
};

$readParagraph = static fn (string $markdown): AstNode => (new MarkdownReader())->read($markdown)->children[0] ?? new AstNode('missing');

$literalCases = [
    'asterisk opener followed by space' => '* foo bar*',
    'asterisk closer preceded by space' => '*foo bar *',
    'strong asterisk opener followed by space' => '** foo bar**',
    'strong asterisk closer preceded by space' => '**foo bar **',
    'underscore opener followed by space' => '_ foo bar_',
    'underscore closer preceded by space' => '_foo bar _',
    'strong underscore opener followed by space' => '__ foo bar__',
    'strong underscore closer preceded by space' => '__foo bar __',
    'midword asterisk before space' => 'a* foo*',
    'midword strong asterisk before space' => 'a** foo**',
    'midword underscore before space' => 'a_ foo_',
    'midword strong underscore before space' => 'a__ foo__',
    'asterisk closer after internal space' => '*foo *bar',
    'strong asterisk closer after internal space' => '**foo **bar',
    'underscore closer after internal space' => '_foo _bar',
    'strong underscore closer after internal space' => '__foo __bar',
    'asterisk opener before internal space' => 'foo* bar* baz',
    'strong asterisk opener before internal space' => 'foo** bar** baz',
    'underscore opener before internal space' => 'foo_ bar_ baz',
    'strong underscore opener before internal space' => 'foo__ bar__ baz',
    'spaced asterisk pair' => '* foo *',
    'spaced strong asterisk pair' => '** foo **',
    'spaced underscore pair' => '_ foo _',
    'spaced strong underscore pair' => '__ foo __',
    'intraword underscore single pair' => 'foo_bar_baz',
    'intraword underscore strong pair' => 'foo__bar__baz',
    'leading strong underscore followed by word character' => '__foo__bar',
    'word followed by trailing strong underscore' => 'foo__bar__',
];

$positiveCases = [
    'simple asterisk emphasis' => ['*foo bar*', ['emph(text:foo bar)']],
    'simple asterisk strong' => ['**foo bar**', ['strong(text:foo bar)']],
    'simple underscore emphasis' => ['_foo bar_', ['emph(text:foo bar)']],
    'simple underscore strong' => ['__foo bar__', ['strong(text:foo bar)']],
    'asterisk emphasis between words' => ['foo *bar* baz', ['text:foo ', 'emph(text:bar)', 'text: baz']],
    'asterisk strong between words' => ['foo **bar** baz', ['text:foo ', 'strong(text:bar)', 'text: baz']],
    'underscore emphasis between words' => ['foo _bar_ baz', ['text:foo ', 'emph(text:bar)', 'text: baz']],
    'underscore strong between words' => ['foo __bar__ baz', ['text:foo ', 'strong(text:bar)', 'text: baz']],
    'asterisk emphasis before punctuation' => ['*bar*.', ['emph(text:bar)', 'text:.']],
    'asterisk strong before punctuation' => ['**bar**.', ['strong(text:bar)', 'text:.']],
    'underscore emphasis before punctuation' => ['_bar_.', ['emph(text:bar)', 'text:.']],
    'underscore strong before punctuation' => ['__bar__.', ['strong(text:bar)', 'text:.']],
    'asterisk emphasis inside parentheses' => ['(*bar*)', ['text:(', 'emph(text:bar)', 'text:)']],
    'asterisk strong inside parentheses' => ['(**bar**)', ['text:(', 'strong(text:bar)', 'text:)']],
    'underscore emphasis inside parentheses' => ['(_bar_)', ['text:(', 'emph(text:bar)', 'text:)']],
    'underscore strong inside parentheses' => ['(__bar__)', ['text:(', 'strong(text:bar)', 'text:)']],
    'hyphen before underscore emphasis' => ['foo-_bar_', ['text:foo-', 'emph(text:bar)']],
    'hyphen before underscore strong' => ['foo-__bar__', ['text:foo-', 'strong(text:bar)']],
    'punctuation after underscore emphasis' => ['_bar_-foo', ['emph(text:bar)', 'text:-foo']],
    'punctuation after underscore strong' => ['__bar__-foo', ['strong(text:bar)', 'text:-foo']],
    'edge emphasis keeps intraword underscore literal' => ['*foo_bar*', ['emph(text:foo_bar)']],
    'edge strong keeps intraword underscore literal' => ['**foo_bar**', ['strong(text:foo_bar)']],
    'edge underscore emphasis keeps inner underscore literal' => ['_foo_bar_', ['emph(text:foo_bar)']],
    'edge underscore strong keeps inner underscore literal' => ['__foo_bar__', ['strong(text:foo_bar)']],
    'nested asterisk strong inside emphasis' => ['*foo **bar** baz*', ['emph(text:foo |strong(text:bar)|text: baz)']],
    'nested asterisk emphasis inside strong' => ['**foo *bar* baz**', ['strong(text:foo |emph(text:bar)|text: baz)']],
    'nested underscore strong inside emphasis' => ['_foo __bar__ baz_', ['emph(text:foo |strong(text:bar)|text: baz)']],
    'nested underscore emphasis inside strong' => ['__foo _bar_ baz__', ['strong(text:foo |emph(text:bar)|text: baz)']],
    'triple asterisk strong emphasis' => ['***foo***', ['strong(emph(text:foo))']],
    'triple underscore strong emphasis' => ['___foo___', ['strong(emph(text:foo))']],
    'asterisk emphasis skips delimiter in code span' => ['*foo `*` bar*', ['emph(text:foo |code|text: bar)']],
    'asterisk strong skips delimiter in code span' => ['**foo `**` bar**', ['strong(text:foo |code|text: bar)']],
];

$tests = [];

$tests['maps commonmark emphasis delimiter flanking literal guards'] =
    static function (TestRunner $t) use ($literalCases, $readParagraph, $describeInlines): void {
        foreach ($literalCases as $name => $markdown) {
            $source = 'x ' . $markdown;
            $paragraph = $readParagraph($source);

            $t->same('paragraph', $paragraph->type, $name);
            $t->same(['text:' . $source], $describeInlines($paragraph->children), $name);
        }
    };

$tests['maps commonmark emphasis delimiter flanking positive runs'] =
    static function (TestRunner $t) use ($positiveCases, $readParagraph, $describeInlines): void {
        foreach ($positiveCases as $name => [$markdown, $expected]) {
            $paragraph = $readParagraph($markdown);

            $t->same('paragraph', $paragraph->type, $name);
            $t->same($expected, $describeInlines($paragraph->children), $name);
        }
    };

$tests['maps commonmark emphasis delimiter surge through wordpress handoff'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            '*foo **bar** baz*',
            'foo **bar** baz and foo _bar_ baz',
            'x * foo bar* and __foo bar __ stay literal',
        ]));
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<p><em>foo <strong>bar</strong> baz</em></p>', $blocks);
        $t->contains('<p>foo <strong>bar</strong> baz and foo <em>bar</em> baz</p>', $blocks);
        $t->contains('<p>x * foo bar* and __foo bar __ stay literal</p>', $blocks);
    };

$tests['records commonmark emphasis delimiter bundle mapped-case count'] =
    static function (TestRunner $t) use ($literalCases, $positiveCases): void {
        $t->same(60, count($literalCases) + count($positiveCases));
    };

return $tests;
