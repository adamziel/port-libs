<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inlineDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$case = static fn (array $children, string $expected, array $options = []): array => [
    'document' => $inlineDocument($children),
    'expected' => $expected,
    'options' => $options,
];

$emptyValueCases = [
    'uri link empty attribute keeps explicit link' => $case([
        $link(['url' => 'https://example.test/source', 'attributes' => ['data-empty' => '']], [$text('https://example.test/source')]),
    ], '[https://example.test/source](https://example.test/source){data-empty=""}'),
    'mailto link empty attribute keeps explicit link' => $case([
        $link(['url' => 'mailto:editor@example.test', 'attributes' => ['data-empty' => '']], [$text('editor@example.test')]),
    ], '[editor@example.test](mailto:editor@example.test){data-empty=""}'),
    'relative link empty attribute' => $case([
        $link(['url' => '/source', 'attributes' => ['data-empty' => '']], [$text('Source')]),
    ], '[Source](/source){data-empty=""}'),
    'link empty title attribute tuple' => $case([
        $link(['url' => '/source', 'attributes' => ['title' => '']], [$text('Source')]),
    ], '[Source](/source){title=""}'),
    'link multiple empty attributes' => $case([
        $link(['url' => '/source', 'attributes' => ['data-a' => '', 'data-b' => '']], [$text('Source')]),
    ], '[Source](/source){data-a="" data-b=""}'),
    'image empty data attribute' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'attributes' => ['data-empty' => '']]),
    ], '![Alt](media/a.png){data-empty=""}'),
    'image explicit empty alt attribute' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'attributes' => ['alt' => '']]),
    ], '![Alt](media/a.png){alt=""}'),
    'span empty data attribute' => $case([
        $inline('span', [$text('Marked')], ['attributes' => ['data-empty' => '']]),
    ], '[Marked]{data-empty=""}'),
    'mark span empty attribute disables shorthand' => $case([
        $inline('span', [$text('Marked')], ['classes' => ['mark'], 'attributes' => ['data-empty' => '']]),
    ], '[Marked]{.mark data-empty=""}'),
    'code empty data attribute' => $case([
        $inline('code', [], ['text' => 'source', 'attributes' => ['data-empty' => '']]),
    ], '`source`{data-empty=""}'),
    'code id with empty data attribute' => $case([
        $inline('code', [], ['text' => 'source', 'id' => 'code', 'attributes' => ['data-empty' => '']]),
    ], '`source`{#code data-empty=""}'),
    'inline math empty data attribute' => $case([
        $inline('math', [], ['text' => 'x', 'attributes' => ['data-empty' => '']]),
    ], '$x${data-empty=""}'),
    'display math empty data attribute' => $case([
        $inline('math', [], ['text' => 'x', 'display' => true, 'attributes' => ['data-empty' => '']]),
    ], '$$x$${data-empty=""}'),
    'underline empty data attribute' => $case([
        $inline('underline', [$text('under')], ['attributes' => ['data-empty' => '']]),
    ], '[under]{.underline data-empty=""}'),
    'small caps empty data attribute' => $case([
        $inline('small_caps', [$text('Caps')], ['attributes' => ['data-empty' => '']]),
    ], '[Caps]{.smallcaps data-empty=""}'),
    'strikeout empty data attribute' => $case([
        $inline('strikeout', [$text('gone')], ['attributes' => ['data-empty' => '']]),
    ], '[gone]{.strikeout data-empty=""}'),
    'superscript empty data attribute' => $case([
        $inline('superscript', [$text('build')], ['attributes' => ['data-empty' => '']]),
    ], '[build]{.superscript data-empty=""}'),
    'subscript empty data attribute' => $case([
        $inline('subscript', [$text('many')], ['attributes' => ['data-empty' => '']]),
    ], '[many]{.subscript data-empty=""}'),
    'reference link empty data attribute' => $case([
        $link(['url' => '/source', 'attributes' => ['data-empty' => '']], [$text('Source')]),
    ], "[Source]\n\n  [Source]: /source {data-empty=\"\"}", ['referenceLinks' => true]),
    'reference image empty data attribute' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'attributes' => ['data-empty' => '']]),
    ], "![Alt]\n\n  [Alt]: media/a.png {data-empty=\"\"}", ['referenceLinks' => true]),
];

$controlOnlyCases = [
    'span control only id is dropped' => $case([
        $inline('span', [$text('Marked')], ['id' => "\n"]),
    ], 'Marked'),
    'span control only class is dropped' => $case([
        $inline('span', [$text('Marked')], ['classes' => ["\t"]]),
    ], 'Marked'),
    'span control only attribute name is dropped' => $case([
        $inline('span', [$text('Marked')], ['attributes' => ["\n" => 'value']]),
    ], 'Marked'),
    'span all control only identifiers are dropped' => $case([
        $inline('span', [$text('Marked')], ['id' => "\n", 'classes' => ["\t"], 'attributes' => ["\r" => 'value']]),
    ], 'Marked'),
    'link control only id is dropped' => $case([
        $link(['url' => '/source', 'id' => "\n"], [$text('Source')]),
    ], '[Source](/source)'),
    'link control only class is dropped' => $case([
        $link(['url' => '/source', 'classes' => ["\t"]], [$text('Source')]),
    ], '[Source](/source)'),
    'link control only attribute name is dropped' => $case([
        $link(['url' => '/source', 'attributes' => ["\n" => 'value']], [$text('Source')]),
    ], '[Source](/source)'),
    'link bad identifiers keep valid empty attribute' => $case([
        $link(['url' => '/source', 'id' => "\n", 'classes' => ["\t"], 'attributes' => ["\r" => 'value', 'data-empty' => '']], [$text('Source')]),
    ], '[Source](/source){data-empty=""}'),
    'image control only id is dropped' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'id' => "\n"]),
    ], '![Alt](media/a.png)'),
    'image control only class is dropped' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'classes' => ["\t"]]),
    ], '![Alt](media/a.png)'),
    'image control only attribute name is dropped' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'attributes' => ["\n" => 'value']]),
    ], '![Alt](media/a.png)'),
    'code control only id is dropped' => $case([
        $inline('code', [], ['text' => 'source', 'id' => "\n"]),
    ], '`source`'),
    'code control only class is dropped' => $case([
        $inline('code', [], ['text' => 'source', 'classes' => ["\t"]]),
    ], '`source`'),
    'code control only attribute name is dropped' => $case([
        $inline('code', [], ['text' => 'source', 'attributes' => ["\n" => 'value']]),
    ], '`source`'),
    'math control only id is dropped' => $case([
        $inline('math', [], ['text' => 'x', 'id' => "\n"]),
    ], '$x$'),
    'math control only class is dropped' => $case([
        $inline('math', [], ['text' => 'x', 'classes' => ["\t"]]),
    ], '$x$'),
    'math control only attribute name is dropped' => $case([
        $inline('math', [], ['text' => 'x', 'attributes' => ["\n" => 'value']]),
    ], '$x$'),
    'reference link control only attribute name is dropped' => $case([
        $link(['url' => '/source', 'attributes' => ["\n" => 'value']], [$text('Source')]),
    ], "[Source]\n\n  [Source]: /source", ['referenceLinks' => true]),
    'abbreviation shorthand survives dropped control id' => $case([
        $inline('span', [$text('HTML')], ['id' => "\n", 'classes' => ['abbr'], 'attributes' => ['title' => 'Hypertext Markup Language']]),
    ], "HTML\n\n*[HTML]: Hypertext Markup Language"),
    'mark shorthand survives dropped control id' => $case([
        $inline('span', [$text('Marked')], ['id' => "\n", 'classes' => ['mark']]),
    ], '==Marked=='),
];

$normalizedCases = [
    'link id newline normalizes to escaped space' => $case([
        $link(['url' => '/source', 'id' => "review\nlink"], [$text('Source')]),
    ], '[Source](/source){#review\ link}'),
    'link class tab normalizes to escaped space' => $case([
        $link(['url' => '/source', 'classes' => ["needs\treview"]], [$text('Source')]),
    ], '[Source](/source){.needs\ review}'),
    'link attribute name newline with empty value' => $case([
        $link(['url' => '/source', 'attributes' => ["data\nreview" => '']], [$text('Source')]),
    ], '[Source](/source){data\ review=""}'),
    'span id nul normalizes to escaped space' => $case([
        $inline('span', [$text('Marked')], ['id' => "review\x00span"]),
    ], '[Marked]{#review\ span}'),
    'span class del normalizes to escaped space' => $case([
        $inline('span', [$text('Marked')], ['classes' => ["needs\x7Freview"]]),
    ], '[Marked]{.needs\ review}'),
    'span attribute name tab with empty value' => $case([
        $inline('span', [$text('Marked')], ['attributes' => ["data\treview" => '']]),
    ], '[Marked]{data\ review=""}'),
    'code attribute name tab with empty value' => $case([
        $inline('code', [], ['text' => 'source', 'attributes' => ["data\treview" => '']]),
    ], '`source`{data\ review=""}'),
    'math attribute name tab with empty value' => $case([
        $inline('math', [], ['text' => 'x', 'attributes' => ["data\treview" => '']]),
    ], '$x${data\ review=""}'),
    'image attribute name tab with empty value' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'attributes' => ["data\treview" => '']]),
    ], '![Alt](media/a.png){data\ review=""}'),
    'reference attribute name tab with empty value' => $case([
        $link(['url' => '/source', 'attributes' => ["data\treview" => '']], [$text('Source')]),
    ], "[Source]\n\n  [Source]: /source {data\ review=\"\"}", ['referenceLinks' => true]),
    'classes drop empty after normalization but keep valid class' => $case([
        $inline('span', [$text('Marked')], ['classes' => ['', "\n", 'review']]),
    ], '[Marked]{.review}'),
    'id trims outer whitespace' => $case([
        $inline('span', [$text('Marked')], ['id' => ' review ']),
    ], '[Marked]{#review}'),
    'attribute name trims outer whitespace' => $case([
        $inline('span', [$text('Marked')], ['attributes' => [' data ' => 'value']]),
    ], '[Marked]{data="value"}'),
    'image classes normalize and filter together' => $case([
        $image(['url' => 'media/a.png', 'alt' => 'Alt', 'classes' => [' hero ', "\n", "wide\tshot"]]),
    ], '![Alt](media/a.png){.hero .wide\ shot}'),
    'span preserves empty and zero attribute values' => $case([
        $inline('span', [$text('Marked')], ['attributes' => ['data-empty' => '', 'data-zero' => '0']]),
    ], '[Marked]{data-empty="" data-zero="0"}'),
    'reference target reuse includes empty attribute signature' => $case([
        $link(['url' => '/same', 'attributes' => ['data-empty' => '']], [$text('One')]),
        $space(),
        $link(['url' => '/same', 'attributes' => ['data-empty' => '']], [$text('Two')]),
    ], "[One] [Two][One]\n\n  [One]: /same {data-empty=\"\"}", ['referenceLinks' => true]),
    'reference empty attribute keeps target distinct from plain target' => $case([
        $link(['url' => '/same'], [$text('One')]),
        $space(),
        $link(['url' => '/same', 'attributes' => ['data-empty' => '']], [$text('Two')]),
    ], "[One] [Two]\n\n  [One]: /same\n  [Two]: /same {data-empty=\"\"}", ['referenceLinks' => true]),
    'wikilink with empty attribute falls back to explicit link' => $case([
        $link(['url' => 'Page', 'classes' => ['wikilink'], 'attributes' => ['data-empty' => '']], [$text('Page')]),
    ], '[Page](Page){.wikilink data-empty=""}'),
    'wikilink shorthand survives dropped control id' => $case([
        $link(['url' => 'Page', 'id' => "\n", 'classes' => ['wikilink']], [$text('Page')]),
    ], '[[Page]]'),
    'link normalized attribute value stays on one line' => $case([
        $link(['url' => '/source', 'attributes' => ["data\treview" => "Line\nTwo"]], [$text('Source')]),
    ], '[Source](/source){data\ review="Line Two"}'),
];

$cases = $emptyValueCases + $controlOnlyCases + $normalizedCases;

$tests = [
    'records markdown writer inline attribute completion mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(60, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer inline attribute completion ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
            $t->true(!str_contains($markdown, '{#}'), 'writer must not emit empty id attribute syntax');
            $t->true(!str_contains($markdown, '{.}'), 'writer must not emit empty class attribute syntax');
            $t->true(!str_contains($markdown, '{="'), 'writer must not emit empty attribute-name syntax');
        };
}

return $tests;
