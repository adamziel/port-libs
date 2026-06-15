<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inlineDoc = static fn (array $children): AstNode => $document([$paragraph($children)]);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);
$note = static fn (array $attrs, array $children = []): AstNode => new AstNode('note', $attrs, $children);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$case = static fn (array $children, string $expected, array $options = []): array => [
    'document' => $inlineDoc($children),
    'expected' => $expected,
    'options' => $options,
];

$cases = [
    'spaced uri autolink falls back to explicit link' => $case([
        $link(['url' => 'http://example.test/a b'], [$text('http://example.test/a b')]),
    ], '[http://example.test/a b](<http://example.test/a b>)'),
    'less-than uri autolink falls back to explicit link' => $case([
        $link(['url' => 'http://example.test/a<b'], [$text('http://example.test/a<b')]),
    ], '[http://example.test/a\\<b](<http://example.test/a\\<b>)'),
    'greater-than uri autolink falls back to explicit link' => $case([
        $link(['url' => 'http://example.test/a>b'], [$text('http://example.test/a>b')]),
    ], '[http://example.test/a\\>b](<http://example.test/a\\>b>)'),
    'mailto query autolink falls back to explicit link' => $case([
        $link(['url' => 'mailto:reviewer@example.test?subject=Review'], [$text('reviewer@example.test?subject=Review')]),
    ], '[reviewer@example.test?subject=Review](mailto:reviewer@example.test?subject=Review)'),
    'plain mailto plus tag remains email autolink' => $case([
        $link(['url' => 'mailto:reviewer+tag@example.test', 'classes' => ['email']], [$text('reviewer+tag@example.test')]),
    ], '<reviewer+tag@example.test>'),
    'uri fragment remains autolink' => $case([
        $link(['url' => 'https://example.test/source#section-1'], [$text('https://example.test/source#section-1')]),
    ], '<https://example.test/source#section-1>'),
    'percent encoded uri label remains autolink' => $case([
        $link(['url' => 'https://example.test/a%20b'], [$text('https://example.test/a b')]),
    ], '<https://example.test/a%20b>'),
    'extra uri class disables autolink shorthand' => $case([
        $link(['url' => '/review', 'classes' => ['uri', 'tracked']], [$text('https://example.test/source')]),
    ], '[https://example.test/source](/review){.uri .tracked}'),
    'tabbed link destination percent encodes control character' => $case([
        $link(['url' => "/review\tpacket"], [$text('tab')]),
    ], '[tab](/review%09packet)'),
    'image label matching target is omitted' => $case([
        $image(['url' => 'media/review.png'], [$text('media/review.png')]),
    ], '![](media/review.png)'),
    'link label code inline is preserved' => $case([
        $link(['url' => '/review'], [$text('use '), $inline('code', [], ['text' => 'wp code'])]),
    ], '[use `wp code`](/review)'),
    'image label code inline is preserved' => $case([
        $image(['url' => 'media/code.png'], [$text('use '), $inline('code', [], ['text' => 'wp code'])]),
    ], '![use `wp code`](media/code.png)'),
    'preferred note label is reused' => $case([
        $text('note'),
        $note(['label' => 'review'], [$paragraph([$text('body')])]),
    ], "note[^review]\n\n[^review]: body"),
    'duplicate preferred note labels get suffixes' => $case([
        $note(['label' => 'review'], [$paragraph([$text('one')])]),
        $text(' and '),
        $note(['label' => 'review'], [$paragraph([$text('two')])]),
    ], "[^review] and [^review-2]\n\n[^review]: one\n\n[^review-2]: two"),
    'case-insensitive preferred note collision gets suffix' => $case([
        $note(['label' => 'Review'], [$paragraph([$text('one')])]),
        $text(' and '),
        $note(['label' => 'review'], [$paragraph([$text('two')])]),
    ], "[^Review] and [^review-2]\n\n[^Review]: one\n\n[^review-2]: two"),
    'left bracket note label falls back to generated label' => $case([
        $note(['label' => 'bad[label'], [$paragraph([$text('body')])]),
    ], "[^1]\n\n[^1]: body"),
    'right bracket note label falls back to generated label' => $case([
        $note(['label' => 'bad]label'], [$paragraph([$text('body')])]),
    ], "[^1]\n\n[^1]: body"),
    'whitespace note label falls back to generated label' => $case([
        $note(['label' => 'bad label'], [$paragraph([$text('body')])]),
    ], "[^1]\n\n[^1]: body"),
    'empty note body keeps empty definition' => $case([
        $note([]),
    ], "[^1]\n\n[^1]:"),
    'multi paragraph note body indents continuation paragraph' => $case([
        $note([], [$paragraph([$text('First')]), $paragraph([$text('Second')])]),
    ], "[^1]\n\n[^1]: First\n\n    Second"),
    'author in text citation with source locator suffix' => $case([
        $citation(['id' => 'doe2026', 'mode' => 'author_in_text', 'locator' => 'p. 42']),
    ], '@doe2026, p. 42'),
    'author in text citation with note suffix' => $case([
        $citation(['id' => 'doe2026', 'mode' => 'author_in_text', 'suffix' => 'chapter *intro*']),
    ], '@doe2026 [chapter \\*intro\\*]'),
    'citation id with space uses braced form' => $case([
        $citation(['id' => 'doe 2026']),
    ], '[@{doe 2026}]'),
    'citation id with closing brace escapes brace' => $case([
        $citation(['id' => 'doe}2026']),
    ], '[@{doe\\}2026}]'),
    'citation id with backslash escapes backslash' => $case([
        $citation(['id' => 'doe\\2026']),
    ], '[@{doe\\\\2026}]'),
    'citation prefix inlines keep inline markup' => $case([
        $citation(['id' => 'doe2026', 'prefix' => [$inline('emph', [$text('see')])]]),
    ], '[*see* @doe2026]'),
    'citation locator inlines escape markdown punctuation' => $case([
        $citation(['id' => 'doe2026', 'locator' => [$text('p. 1|2')]]),
    ], '[@doe2026, p. 1\\|2]'),
    'citation group keeps mixed citation modes' => $case([
        new AstNode('citation_group', [], [
            $citation(['id' => 'doe2026', 'prefix' => 'see']),
            $citation(['id' => 'roe2025', 'mode' => 'suppress_author', 'locator' => 'sec. 2']),
        ]),
    ], '[see @doe2026; -@roe2025, sec. 2]'),
    'empty citation id remains braced and visible' => $case([
        $citation(['id' => '']),
    ], '[@{}]'),
    'explicit rendered citation source is passed through' => $case([
        $citation(['rendered' => '[already @rendered, p. 9]']),
    ], '[already @rendered, p. 9]'),
    'inline math escapes dollar body' => $case([
        $inline('math', [], ['text' => 'price $5']),
    ], '$price \\$5$'),
    'display math escapes dollar body before attributes' => $case([
        $inline('math', [], ['text' => 'x $ y', 'display' => true, 'id' => 'eq-review']),
    ], '$$x \\$ y$${#eq-review}'),
    'inline math escapes adjacent dollar run' => $case([
        $inline('math', [], ['text' => 'a $$ b']),
    ], '$a \\$\\$ b$'),
    'inline math keeps class attributes' => $case([
        $inline('math', [], ['text' => 'x + y', 'classes' => ['math']]),
    ], '$x + y${.math}'),
    'display math keeps id class and data attributes' => $case([
        $inline('math', [], [
            'text' => 'x = y',
            'display' => true,
            'id' => 'eq',
            'classes' => ['math'],
            'attributes' => ['data-source' => 'surge'],
        ]),
    ], '$$x = y$${#eq .math data-source="surge"}'),
    'emphasis trims delimiter edge spaces' => $case([
        $inline('emph', [$text(' edge ')]),
    ], ' *edge* '),
    'empty emphasis emits no delimiter noise' => $case([
        $inline('emph'),
    ], ''),
    'strong content escapes literal star' => $case([
        $inline('strong', [$text('a * b')]),
    ], '**a \\* b**'),
    'strikeout content escapes nested delimiter text' => $case([
        $inline('strikeout', [$text('a ~~ b')]),
    ], '~~a \\~\\~ b~~'),
    'superscript escapes spaces' => $case([
        $inline('superscript', [$text('build 42 rc')]),
    ], '^build\\ 42\\ rc^'),
    'subscript escapes spaces' => $case([
        $inline('subscript', [$text('many of them')]),
    ], '~many\\ of\\ them~'),
    'emoji span renders alias shorthand' => $case([
        $inline('span', [$text("\u{1F44D}")], ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'thumbsup']]),
    ], ':thumbsup:'),
    'emoji span falls back when glyph mismatches alias' => $case([
        $inline('span', [$text('not emoji')], ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'thumbsup']]),
    ], '[not emoji]{.emoji data-emoji="thumbsup"}'),
    'abbreviation span emits definition at document end' => $case([
        $inline('span', [$text('HTML')], ['classes' => ['abbr'], 'attributes' => ['title' => 'Hypertext Markup Language']]),
    ], "HTML\n\n*[HTML]: Hypertext Markup Language"),
    'raw inline unknown format is omitted' => $case([
        $inline('raw_inline', [], ['format' => 'rtf', 'text' => '{\\rtf1 raw}']),
    ], ''),
    'reference link after raw bracket conflict uses full suffix' => $case([
        $link(['url' => '/source'], [$text('Source')]),
        $inline('raw_markdown', [], ['text' => '[tail]']),
    ], "[Source][][tail]\n\n  [Source]: /source", ['referenceLinks' => true]),
    'reference link after softbreak bracket conflict uses full suffix' => $case([
        $link(['url' => '/source'], [$text('Source')]),
        new AstNode('softbreak'),
        $text('[tail]'),
    ], "[Source][]\n\\[tail\\]\n\n  [Source]: /source", ['referenceLinks' => true]),
    'reference link before citation uses full suffix' => $case([
        $link(['url' => '/source'], [$text('Source')]),
        $text(' '),
        $citation(['id' => 'doe2026']),
    ], "[Source][] [@doe2026]\n\n  [Source]: /source", ['referenceLinks' => true]),
    'reference target with title is reused' => $case([
        $link(['url' => '/one', 'title' => 'One'], [$text('Source')]),
        $text(' and '),
        $link(['url' => '/one', 'title' => 'One'], [$text('Again')]),
    ], "[Source] and [Again][Source]\n\n  [Source]: /one \"One\"", ['referenceLinks' => true]),
    'reference label normalizes softbreak whitespace' => $case([
        $link(['url' => '/packet'], [$text('Source'), new AstNode('softbreak'), $text('Packet')]),
    ], "[Source\nPacket]\n\n  [Source Packet]: /packet", ['referenceLinks' => true]),
    'reference definitions can flush at section boundary' => [
        'document' => $document([
            $paragraph([$link(['url' => '/one'], [$text('One')])]),
            new AstNode('heading', ['level' => 1], [$text('Next')]),
            $paragraph([$link(['url' => '/two'], [$text('Two')])]),
        ]),
        'expected' => "[One]\n\n  [One]: /one\n\n# Next\n\n[Two]\n\n  [Two]: /two",
        'options' => ['referenceLinks' => true, 'referenceLocation' => 'end_of_section'],
    ],
    'reference definitions can flush at block boundary' => [
        'document' => $document([
            $paragraph([$link(['url' => '/one'], [$text('One')])]),
            $paragraph([$link(['url' => '/two'], [$text('Two')])]),
        ]),
        'expected' => "[One]\n\n  [One]: /one\n\n[Two]\n\n  [Two]: /two",
        'options' => ['referenceLinks' => true, 'referenceLocation' => 'end_of_block'],
    ],
    'raw markdown in bracketed span stays raw' => $case([
        $inline('span', [$inline('raw_markdown', [], ['text' => '*raw*'])], ['classes' => ['review']]),
    ], '[*raw*]{.review}'),
    'linebreak in paragraph remains hard break' => $case([
        $text('a'),
        new AstNode('linebreak'),
        $text('b'),
    ], "a\\\nb"),
    'softbreak space option writes a space' => $case([
        $text('a'),
        new AstNode('softbreak'),
        $text('b'),
    ], 'a b', ['softBreak' => 'space']),
    'raw tex inline passes through tex payload' => $case([
        $inline('raw_tex', [], ['tex' => '\\LaTeX{}']),
    ], '\\LaTeX{}'),
];

$tests = [
    'records markdown writer inline completion surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(56, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer inline completion surge ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options'] ?? []))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
