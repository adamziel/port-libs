<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

/**
 * @param list<AstNode> $children
 */
function markdown_writer_surge_doc(array $children): AstNode
{
    return new AstNode('document', [], $children);
}

/**
 * @param list<AstNode> $children
 */
function markdown_writer_surge_para(array $children): AstNode
{
    return new AstNode('paragraph', [], $children);
}

function markdown_writer_surge_text(string $text): AstNode
{
    return new AstNode('text', ['text' => $text]);
}

/**
 * @param list<AstNode> $children
 * @param array<string, mixed> $attrs
 */
function markdown_writer_surge_inline(string $type, array $children = [], array $attrs = []): AstNode
{
    return new AstNode($type, $attrs, $children);
}

/**
 * @param list<AstNode> $inlines
 */
function markdown_writer_surge_inline_doc(array $inlines): AstNode
{
    return markdown_writer_surge_doc([markdown_writer_surge_para($inlines)]);
}

/**
 * @param array<string, mixed> $attrs
 * @param list<AstNode> $children
 */
function markdown_writer_surge_link(array $attrs, array $children): AstNode
{
    return new AstNode('link', $attrs, $children);
}

/**
 * @param array<string, mixed> $attrs
 * @param list<AstNode> $children
 */
function markdown_writer_surge_image(array $attrs, array $children = []): AstNode
{
    return new AstNode('image', $attrs, $children);
}

/**
 * @return array<string, array{document:AstNode, expected:string, options?:array<string, mixed>, roundTripTypes?:list<string>, roundTripText?:string}>
 */
function markdown_writer_inline_surge_cases(): array
{
    $textCase = static fn (string $text, string $expected): array => [
        'document' => markdown_writer_surge_inline_doc([markdown_writer_surge_text($text)]),
        'expected' => $expected,
        'roundTripTypes' => ['paragraph'],
        'roundTripText' => $text,
    ];
    $inlineCase = static fn (array $inlines, string $expected, array $options = []): array => [
        'document' => markdown_writer_surge_inline_doc($inlines),
        'expected' => $expected,
        'options' => $options,
    ];

    return [
        'escapes atx heading marker text' => $textCase('# imported heading literal', '\\# imported heading literal'),
        'escapes compact atx heading marker text' => $textCase('##', '\\##'),
        'escapes dash bullet marker text' => $textCase('- imported bullet literal', '\\- imported bullet literal'),
        'escapes plus bullet marker text' => $textCase('+ imported bullet literal', '\\+ imported bullet literal'),
        'escapes star bullet marker text' => $textCase('* imported bullet literal', '\\* imported bullet literal'),
        'escapes decimal period list marker punctuation' => $textCase('1. imported ordered literal', '1\\. imported ordered literal'),
        'escapes decimal paren list marker punctuation' => $textCase('12) imported ordered literal', '12\\) imported ordered literal'),
        'escapes colon definition marker text' => $textCase(': imported definition literal', '\\: imported definition literal'),
        'escapes tilde definition marker text' => $textCase('~ imported definition literal', '\\~ imported definition literal'),
        'escapes citation-looking leading at sign' => $textCase('@doe imported citation literal', '\\@doe imported citation literal'),
        'escapes braced citation-looking leading at sign' => $textCase('@{doe, 2026} imported citation literal', '\\@{doe, 2026} imported citation literal'),
        'escapes entity-looking ampersand text' => [
            'document' => markdown_writer_surge_inline_doc([markdown_writer_surge_text('AT&amp;T imported entity literal')]),
            'expected' => 'AT\\&amp;T imported entity literal',
        ],
        'escapes ellipses smart punctuation trigger' => $textCase('Ellipses... stay literal', 'Ellipses\\... stay literal'),
        'escapes en dash trigger' => $textCase('range 5--7 stays literal', 'range 5\\--7 stays literal'),
        'escapes em dash trigger' => [
            'document' => markdown_writer_surge_inline_doc([markdown_writer_surge_text('dash a---b stays literal')]),
            'expected' => 'dash a\\-\\-\\-b stays literal',
        ],
        'escapes fenced div colon run' => $textCase('::: imported div fence literal', '\\::: imported div fence literal'),
        'escapes image opener in text' => $textCase('![not an image]', '\\![not an image\\]'),
        'escapes strikeout delimiter in text' => $textCase('~~not deleted~~', '\\~~not deleted\\~~'),
        'preserves intraword underscore text' => $textCase('alpha_beta_gamma', 'alpha_beta_gamma'),
        'escapes standalone underscore text' => $textCase('alpha _ beta', 'alpha \\_ beta'),
        'escapes angle comparison text' => $textCase('5 < 6 > 4', '5 \\< 6 \\> 4'),
        'escapes quotes in text' => $textCase('literal \'single\' and "double"', 'literal \\\'single\\\' and \\"double\\"'),
        'escapes dollar and pipe text' => $textCase('price $5 | source', 'price \\$5 \\| source'),
        'escapes backslash text' => $textCase('path C:\\source', 'path C:\\\\source'),
        'renders uri autolink' => $inlineCase([
            markdown_writer_surge_link(['url' => 'https://example.test/source'], [markdown_writer_surge_text('https://example.test/source')]),
        ], '<https://example.test/source>'),
        'renders mailto autolink as email' => $inlineCase([
            markdown_writer_surge_link(['url' => 'mailto:reviewer@example.test'], [markdown_writer_surge_text('reviewer@example.test')]),
        ], '<reviewer@example.test>'),
        'keeps titled uri as explicit link' => $inlineCase([
            markdown_writer_surge_link(['url' => 'https://example.test/source', 'title' => 'Source'], [markdown_writer_surge_text('https://example.test/source')]),
        ], '[https://example.test/source](https://example.test/source "Source")'),
        'keeps attributed uri as explicit link' => $inlineCase([
            markdown_writer_surge_link([
                'url' => 'https://example.test/source',
                'classes' => ['uri'],
                'attributes' => ['data-source' => 'batch-1'],
            ], [markdown_writer_surge_text('https://example.test/source')]),
        ], '[https://example.test/source](https://example.test/source){.uri data-source="batch-1"}'),
        'renders nested strong link label' => $inlineCase([
            markdown_writer_surge_link(['url' => '/packet'], [
                markdown_writer_surge_text('review '),
                markdown_writer_surge_inline('strong', [markdown_writer_surge_text('packet')]),
            ]),
        ], '[review **packet**](/packet)'),
        'escapes link title quotes and backslashes' => $inlineCase([
            markdown_writer_surge_link(['url' => '/source', 'title' => 'Review "quote" \\ path'], [markdown_writer_surge_text('label')]),
        ], '[label](/source "Review \\"quote\\" \\\\ path")'),
        'renders spaced link destination in angles' => $inlineCase([
            markdown_writer_surge_link(['url' => '/source packet'], [markdown_writer_surge_text('label')]),
        ], '[label](</source packet>)'),
        'renders parenthesized link destination in angles' => $inlineCase([
            markdown_writer_surge_link(['url' => '/source(packet)'], [markdown_writer_surge_text('label')]),
        ], '[label](</source(packet)>)'),
        'renders empty link destination in angles' => $inlineCase([
            markdown_writer_surge_link(['url' => ''], [markdown_writer_surge_text('empty')]),
        ], '[empty](<>)'),
        'renders link attributes after destination' => $inlineCase([
            markdown_writer_surge_link([
                'url' => '/review',
                'id' => 'review-link',
                'classes' => ['source', 'tracked'],
                'attributes' => ['data-id' => '42', 'title' => 'inline attr'],
            ], [markdown_writer_surge_text('review')]),
        ], '[review](/review){#review-link .source .tracked data-id="42" title="inline attr"}'),
        'escapes brackets inside link label' => $inlineCase([
            markdown_writer_surge_link(['url' => '/url'], [markdown_writer_surge_text('a [b] label')]),
        ], '[a \\[b\\] label](/url)'),
        'renders simple image alt text' => $inlineCase([
            markdown_writer_surge_image(['url' => 'media/review.png', 'alt' => 'Review image']),
        ], '![Review image](media/review.png)'),
        'renders empty image alt text' => $inlineCase([
            markdown_writer_surge_image(['url' => 'media/review.png']),
        ], '![](media/review.png)'),
        'renders image title and attributes' => $inlineCase([
            markdown_writer_surge_image([
                'url' => 'media/review.png',
                'alt' => 'Review image',
                'title' => 'Review "image"',
                'id' => 'img-review',
                'classes' => ['hero'],
                'attributes' => ['width' => '640'],
            ]),
        ], '![Review image](media/review.png "Review \\"image\\""){#img-review .hero width="640"}'),
        'renders image label inline markup' => $inlineCase([
            markdown_writer_surge_image(['url' => 'media/review.png'], [
                markdown_writer_surge_inline('strong', [markdown_writer_surge_text('Bold')]),
                markdown_writer_surge_text(' alt'),
            ]),
        ], '![**Bold** alt](media/review.png)'),
        'preserves differing image alt as attribute' => $inlineCase([
            markdown_writer_surge_image(['url' => 'media/review.png', 'alt' => 'Plain alt'], [
                markdown_writer_surge_text('Caption label'),
            ]),
        ], '![Caption label](media/review.png){alt="Plain alt"}'),
        'renders image destination with spaces in angles' => $inlineCase([
            markdown_writer_surge_image(['url' => 'media/review image.png', 'alt' => 'Review image']),
        ], '![Review image](<media/review image.png>)'),
        'renders inline code without extra spaces' => $inlineCase([
            markdown_writer_surge_inline('code', [], ['text' => 'plain_code']),
        ], '`plain_code`'),
        'renders inline code with backtick delimiter expansion' => $inlineCase([
            markdown_writer_surge_inline('code', [], ['text' => 'wp `meta` key']),
        ], '`` wp `meta` key ``'),
        'renders inline code preserving edge spaces' => $inlineCase([
            markdown_writer_surge_inline('code', [], ['text' => ' edge spaced ']),
        ], '`  edge spaced  `'),
        'renders attributed inline code' => $inlineCase([
            markdown_writer_surge_inline('code', [], ['text' => 'source:key', 'id' => 'code-key', 'classes' => ['php']]),
        ], '`source:key`{#code-key .php}'),
        'renders inline math' => $inlineCase([
            markdown_writer_surge_inline('math', [], ['text' => 'x + y']),
        ], '$x + y$'),
        'renders display math with attributes' => $inlineCase([
            markdown_writer_surge_inline('math', [], ['text' => 'x = y', 'display' => true, 'id' => 'eq-review']),
        ], '$$x = y$${#eq-review}'),
        'passes through markdown raw inline' => $inlineCase([
            markdown_writer_surge_inline('raw_markdown', [], ['markdown' => '[raw]{.markdown}']),
        ], '[raw]{.markdown}'),
        'passes through html raw inline' => $inlineCase([
            markdown_writer_surge_inline('raw_html_inline', [], ['html' => '<span data-x="1">raw</span>']),
        ], '<span data-x="1">raw</span>'),
        'passes through generic html raw inline format' => $inlineCase([
            markdown_writer_surge_inline('raw_inline', [], ['format' => 'html5', 'html' => '<em>raw</em>']),
        ], '<em>raw</em>'),
        'passes through generic latex raw inline format' => $inlineCase([
            markdown_writer_surge_inline('raw_inline', [], ['format' => 'latex', 'tex' => '\\LaTeX{}']),
        ], '\\LaTeX{}'),
        'renders mark span shorthand' => $inlineCase([
            markdown_writer_surge_inline('span', [markdown_writer_surge_text('marked')], ['classes' => ['mark']]),
        ], '==marked=='),
        'falls back attributed mark span containing delimiter' => $inlineCase([
            markdown_writer_surge_inline('span', [markdown_writer_surge_text('a==b')], ['classes' => ['mark']]),
        ], '[a==b]{.mark}'),
        'renders small caps span class' => $inlineCase([
            markdown_writer_surge_inline('small_caps', [markdown_writer_surge_text('Caps')]),
        ], '[Caps]{.smallcaps}'),
        'renders underline span class' => $inlineCase([
            markdown_writer_surge_inline('underline', [markdown_writer_surge_text('under')]),
        ], '[under]{.underline}'),
        'renders attributed strikeout as span' => $inlineCase([
            markdown_writer_surge_inline('strikeout', [markdown_writer_surge_text('gone')], ['id' => 'removed']),
        ], '[gone]{#removed .strikeout}'),
        'renders plain strikeout delimiters' => $inlineCase([
            markdown_writer_surge_inline('strikeout', [markdown_writer_surge_text('gone')]),
        ], '~~gone~~'),
        'renders superscript with escaped spaces' => $inlineCase([
            markdown_writer_surge_inline('superscript', [markdown_writer_surge_text('build 42')]),
        ], '^build\\ 42^'),
        'renders subscript with escaped spaces' => $inlineCase([
            markdown_writer_surge_inline('subscript', [markdown_writer_surge_text('many of them')]),
        ], '~many\\ of\\ them~'),
        'renders single smart quote inline' => $inlineCase([
            markdown_writer_surge_inline('quoted', [markdown_writer_surge_text('quoted')], ['kind' => 'single']),
        ], '‘quoted’'),
        'renders double smart quote inline' => $inlineCase([
            markdown_writer_surge_inline('quoted', [markdown_writer_surge_text('quoted')], ['kind' => 'double']),
        ], '“quoted”'),
        'renders author in text citation' => $inlineCase([
            markdown_writer_surge_inline('citation', [], ['id' => 'doe2026', 'mode' => 'author_in_text']),
        ], '@doe2026'),
        'renders suppress author citation' => $inlineCase([
            markdown_writer_surge_inline('citation', [], ['id' => 'doe2026', 'mode' => 'suppress_author']),
        ], '[-@doe2026]'),
        'renders citation prefix and locator' => $inlineCase([
            markdown_writer_surge_inline('citation', [], ['id' => 'doe2026', 'prefix' => 'see', 'locator' => 'p. 42']),
        ], '[see @doe2026, p. 42]'),
        'renders citation group' => $inlineCase([
            markdown_writer_surge_inline('citation_group', [
                markdown_writer_surge_inline('citation', [], ['id' => 'doe2026']),
                markdown_writer_surge_inline('citation', [], ['id' => 'roe2025', 'mode' => 'suppress_author']),
            ]),
        ], '[@doe2026; -@roe2025]'),
        'renders footnote definition at document end' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_text('note'),
                markdown_writer_surge_inline('note', [
                    markdown_writer_surge_para([markdown_writer_surge_text('footnote body')]),
                ]),
            ]),
            'expected' => "note[^1]\n\n[^1]: footnote body",
        ],
        'renders simple shortcut reference link' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link(['url' => '/source'], [markdown_writer_surge_text('Source')]),
            ]),
            'expected' => "[Source]\n\n  [Source]: /source",
            'options' => ['referenceLinks' => true],
        ],
        'renders reference link full form before bracket conflict' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link(['url' => '/source'], [markdown_writer_surge_text('Source')]),
                markdown_writer_surge_text('[tail]'),
            ]),
            'expected' => "[Source][]\\[tail\\]\n\n  [Source]: /source",
            'options' => ['referenceLinks' => true],
        ],
        'renders duplicate reference labels with generated suffix' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link(['url' => '/one'], [markdown_writer_surge_text('Source')]),
                markdown_writer_surge_text(' and '),
                markdown_writer_surge_link(['url' => '/two'], [markdown_writer_surge_text('Source')]),
            ]),
            'expected' => "[Source] and [Source][1]\n\n  [Source]: /one\n  [1]: /two",
            'options' => ['referenceLinks' => true],
        ],
        'reuses reference label for repeated target' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link(['url' => '/one'], [markdown_writer_surge_text('Source')]),
                markdown_writer_surge_text(' and '),
                markdown_writer_surge_link(['url' => '/one'], [markdown_writer_surge_text('Again')]),
            ]),
            'expected' => "[Source] and [Again][Source]\n\n  [Source]: /one",
            'options' => ['referenceLinks' => true],
        ],
        'renders generated reference label for empty label' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link(['url' => '/empty'], []),
            ]),
            'expected' => "[][1]\n\n  [1]: /empty",
            'options' => ['referenceLinks' => true],
        ],
        'renders generated reference label for bracketed label text' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link(['url' => '/bad'], [markdown_writer_surge_text('Bad [label]')]),
            ]),
            'expected' => "[Bad \\[label\\]][1]\n\n  [1]: /bad",
            'options' => ['referenceLinks' => true],
        ],
        'renders reference definition title and attributes' => [
            'document' => markdown_writer_surge_inline_doc([
                markdown_writer_surge_link([
                    'url' => '/packet',
                    'title' => 'Packet',
                    'id' => 'packet-link',
                    'classes' => ['tracked'],
                    'attributes' => ['data-id' => '9'],
                ], [markdown_writer_surge_text('Packet')]),
            ]),
            'expected' => "[Packet]\n\n  [Packet]: /packet \"Packet\" {#packet-link .tracked data-id=\"9\"}",
            'options' => ['referenceLinks' => true],
        ],
        'renders end of block reference definitions' => [
            'document' => markdown_writer_surge_doc([
                markdown_writer_surge_para([
                    markdown_writer_surge_link(['url' => '/one'], [markdown_writer_surge_text('One')]),
                ]),
                markdown_writer_surge_para([
                    markdown_writer_surge_link(['url' => '/two'], [markdown_writer_surge_text('Two')]),
                ]),
            ]),
            'expected' => "[One]\n\n  [One]: /one\n\n[Two]\n\n  [Two]: /two",
            'options' => ['referenceLinks' => true, 'referenceLocation' => 'end_of_block'],
        ],
    ];
}

$tests = [];
foreach (markdown_writer_inline_surge_cases() as $name => $case) {
    $tests['maps upstream markdown writer inline/link/escape surge ' . $name] = static function (TestRunner $t) use ($case): void {
        $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

        $t->same($case['expected'], $markdown);

        if (isset($case['roundTripTypes'])) {
            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same($case['roundTripTypes'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
        }

        if (isset($case['roundTripText'])) {
            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same($case['roundTripText'], $roundTrip->children[0]->attr('text'));
        }
    };
}

return $tests;
