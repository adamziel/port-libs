<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$lineText = static fn (string $value, string $attr = 'text'): AstNode => new AstNode('line', [$attr => $value]);
$lineChildren = static fn (array $children, array $attrs = []): AstNode => new AstNode('line', $attrs, $children);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$heading = static fn (string $text): AstNode => new AstNode('heading', ['level' => 2], [new AstNode('text', ['text' => $text])]);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);

$singleLineCase = static fn (AstNode $line, string $expectedContent, array $options = []): array => [
    'document' => $document([$lineBlock([$line])]),
    'expected' => $expectedContent === '' ? '|' : '| ' . $expectedContent,
    'options' => $options,
];

$sourceTextCases = [
    'empty source text line remains bare pipe' => $singleLineCase($lineText(''), ''),
    'heading marker source text is escaped' => $singleLineCase($lineText('# imported heading literal'), '\\# imported heading literal'),
    'compact heading marker source text is escaped' => $singleLineCase($lineText('##'), '\\##'),
    'dash bullet source text is escaped' => $singleLineCase($lineText('- imported bullet literal'), '\\- imported bullet literal'),
    'plus bullet source text is escaped' => $singleLineCase($lineText('+ imported bullet literal'), '\\+ imported bullet literal'),
    'star bullet source text is escaped' => $singleLineCase($lineText('* imported bullet literal'), '\\* imported bullet literal'),
    'decimal period source text is escaped' => $singleLineCase($lineText('1. imported ordered literal'), '1\\. imported ordered literal'),
    'decimal paren source text is escaped' => $singleLineCase($lineText('12) imported ordered literal'), '12\\) imported ordered literal'),
    'colon definition source text is escaped' => $singleLineCase($lineText(': imported definition literal'), '\\: imported definition literal'),
    'tilde definition source text is escaped' => $singleLineCase($lineText('~ imported definition literal'), '\\~ imported definition literal'),
    'bare citation source text is escaped' => $singleLineCase($lineText('@doe imported citation literal'), '\\@doe imported citation literal'),
    'braced citation source text is escaped' => $singleLineCase($lineText('@{doe, 2026} imported citation literal'), '\\@{doe, 2026} imported citation literal'),
    'entity-looking ampersand source text is escaped' => $singleLineCase($lineText('AT&amp;T imported entity literal'), 'AT&amp;amp;T imported entity literal'),
    'ellipsis source text is escaped under smart extension' => $singleLineCase($lineText('Ellipses... stay literal'), 'Ellipses\\... stay literal'),
    'en dash source text is escaped under smart extension' => $singleLineCase($lineText('range 5--7 stays literal'), 'range 5\\--7 stays literal'),
    'em dash source text is escaped under smart extension' => $singleLineCase($lineText('dash a---b stays literal'), 'dash a\\-\\-\\-b stays literal'),
    'fenced div source text is escaped' => $singleLineCase($lineText('::: imported div fence literal'), '\\::: imported div fence literal'),
    'image opener source text is escaped' => $singleLineCase($lineText('![not an image]'), '\\![not an image\\]'),
    'strikeout delimiter source text is escaped' => $singleLineCase($lineText('~~not deleted~~'), '\\~\\~not deleted\\~\\~'),
    'intraword underscore source text is preserved' => $singleLineCase($lineText('alpha_beta_gamma'), 'alpha_beta_gamma'),
    'standalone underscore source text is escaped' => $singleLineCase($lineText('alpha _ beta'), 'alpha \\_ beta'),
    'angle comparison source text is escaped' => $singleLineCase($lineText('5 < 6 > 4'), '5 \\< 6 \\> 4'),
    'quotes in source text are escaped' => $singleLineCase($lineText('literal \'single\' and "double"'), 'literal \\\'single\\\' and \\"double\\"'),
    'dollar and pipe source text are escaped' => $singleLineCase($lineText('price $5 | source'), 'price \\$5 \\| source'),
    'backslash source text is escaped' => $singleLineCase($lineText('path C:\\source'), 'path C:\\\\source'),
    'bracket source text is escaped' => $singleLineCase($lineText('[brackets] remain text'), '\\[brackets\\] remain text'),
    'emphasis delimiter source text is escaped' => $singleLineCase($lineText('*literal* marker'), '\\*literal\\* marker'),
    'strong delimiter source text is escaped' => $singleLineCase($lineText('**literal** marker'), '\\*\\*literal\\*\\* marker'),
    'nonbreaking source indentation becomes line-block spaces' => $singleLineCase($lineText(str_repeat("\xC2\xA0", 4) . 'indented continuation'), '    indented continuation'),
    'source value alias is escaped' => $singleLineCase($lineText('# alias heading', 'value'), '\\# alias heading'),
    'source literal alias is escaped' => $singleLineCase($lineText('literal *marker*', 'literal'), 'literal \\*marker\\*'),
    'source content alias is escaped' => $singleLineCase($lineText('1. content ordered', 'content'), '1\\. content ordered'),
    'source string alias is escaped' => $singleLineCase($lineText('AT&amp;T string', 'string'), 'AT&amp;amp;T string'),
];

$inlinePayloadCases = [
    'line child plain text' => $singleLineCase($lineChildren([$text('plain payload')]), 'plain payload'),
    'line child emphasis' => $singleLineCase($lineChildren([$text('alpha '), $inline('emph', [$text('em')])]), 'alpha *em*'),
    'line child strong' => $singleLineCase($lineChildren([$inline('strong', [$text('strong')])]), '**strong**'),
    'line child inline code' => $singleLineCase($lineChildren([$text('use '), $inline('code', [], ['text' => 'wp code'])]), 'use `wp code`'),
    'line child inline code expands backticks' => $singleLineCase($lineChildren([$inline('code', [], ['text' => 'wp `meta` key'])]), '`` wp `meta` key ``'),
    'line child explicit link' => $singleLineCase($lineChildren([$link(['url' => '/packet'], [$text('packet')])]), '[packet](/packet)'),
    'line child autolink' => $singleLineCase($lineChildren([$link(['url' => 'https://example.test/source'], [$text('https://example.test/source')])]), '<https://example.test/source>'),
    'line child titled link' => $singleLineCase($lineChildren([$link(['url' => '/packet', 'title' => 'Packet "title"'], [$text('packet')])]), '[packet](/packet "Packet \\"title\\"")'),
    'line child attributed link' => $singleLineCase($lineChildren([$link([
        'url' => '/review',
        'id' => 'review-link',
        'classes' => ['tracked'],
        'attributes' => ['data-x' => '1'],
    ], [$text('review')])]), '[review](/review){#review-link .tracked data-x="1"}'),
    'line child simple image' => $singleLineCase($lineChildren([$image(['url' => 'media/review.png', 'alt' => 'Review image'])]), '![Review image](media/review.png)'),
    'line child image destination with spaces' => $singleLineCase($lineChildren([$image(['url' => 'media/review image.png', 'alt' => 'Review image'])]), '![Review image](<media/review image.png>)'),
    'line child inline math' => $singleLineCase($lineChildren([$inline('math', [], ['text' => 'x + y'])]), '$x + y$'),
    'line child display math' => $singleLineCase($lineChildren([$inline('math', [], ['text' => 'x = y', 'display' => true])]), '$$x = y$$'),
    'line child raw markdown' => $singleLineCase($lineChildren([$inline('raw_markdown', [], ['markdown' => '[raw]{.packet}'])]), '[raw]{.packet}'),
    'line child raw html' => $singleLineCase($lineChildren([$inline('raw_html_inline', [], ['html' => '<span data-x="1">raw</span>'])]), '<span data-x="1">raw</span>'),
    'line child raw tex' => $singleLineCase($lineChildren([$inline('raw_inline', [], ['format' => 'latex', 'tex' => '\\LaTeX{}'])]), '\\LaTeX{}'),
    'line child strikeout' => $singleLineCase($lineChildren([$inline('strikeout', [$text('gone')])]), '~~gone~~'),
    'line child superscript' => $singleLineCase($lineChildren([$inline('superscript', [$text('build 42')])]), '^build\\ 42^'),
    'line child subscript' => $singleLineCase($lineChildren([$inline('subscript', [$text('H2O')])]), '~H2O~'),
    'line child underline' => $singleLineCase($lineChildren([$inline('underline', [$text('under')])]), '[under]{.underline}'),
    'line child small caps' => $singleLineCase($lineChildren([$inline('small_caps', [$text('Caps')])]), '[Caps]{.smallcaps}'),
    'line child span attributes' => $singleLineCase($lineChildren([$inline('span', [$text('span')], ['classes' => ['review'], 'attributes' => ['data-kind' => 'line']])]), '[span]{.review data-kind="line"}'),
    'line child mark span shorthand' => $singleLineCase($lineChildren([$inline('span', [$text('mark')], ['classes' => ['mark']])]), '==mark=='),
    'line child mixed inline payload' => $singleLineCase($lineChildren([
        $text('Review '),
        $inline('strong', [$text('packet')]),
        $space(),
        $link(['url' => '/packet'], [$text('source')]),
    ]), 'Review **packet** [source](/packet)'),
    'line child commonmark explicit line blocks' => $singleLineCase(
        $lineChildren([$inline('span', [$text('span')], ['classes' => ['review']])]),
        '[span]{.review}',
        ['format' => 'commonmark+line_blocks+bracketed_spans']
    ),
];

$structuralCases = [
    'two source text lines' => [
        'document' => $document([$lineBlock([$lineText('first line'), $lineText('second line')])]),
        'expected' => "| first line\n| second line",
    ],
    'source text lines preserve empty middle line' => [
        'document' => $document([$lineBlock([$lineText('first line'), $lineText(''), $lineText('second line')])]),
        'expected' => "| first line\n|\n| second line",
    ],
    'mixed source text and child inline lines' => [
        'document' => $document([$lineBlock([$lineText('# source heading'), $lineChildren([$text('child '), $inline('emph', [$text('line')])])])]),
        'expected' => "| \\# source heading\n| child *line*",
    ],
    'paragraph before line block keeps block separator' => [
        'document' => $document([$paragraph([$text('Lead paragraph')]), $lineBlock([$lineText('verse line')])]),
        'expected' => "Lead paragraph\n\n| verse line",
    ],
    'heading before line block keeps block separator' => [
        'document' => $document([$heading('Line block section'), $lineBlock([$lineText('verse line')])]),
        'expected' => "## Line block section\n\n| verse line",
    ],
    'line block before paragraph keeps block separator' => [
        'document' => $document([$lineBlock([$lineText('verse line')]), $paragraph([$text('After paragraph')])]),
        'expected' => "| verse line\n\nAfter paragraph",
    ],
    'blockquote line block prefixes every line' => [
        'document' => $document([$blockquote([$lineBlock([$lineText('quoted line'), $lineText('# literal heading')])])]),
        'expected' => "> | quoted line\n> | \\# literal heading",
    ],
    'commonmark plus line blocks writes pipe lines' => [
        'document' => $document([$lineBlock([$lineText('commonmark line')])]),
        'expected' => '| commonmark line',
        'options' => ['format' => 'commonmark+line_blocks'],
    ],
    'gfm plus line blocks writes pipe lines' => [
        'document' => $document([$lineBlock([$lineText('gfm line')])]),
        'expected' => '| gfm line',
        'options' => ['format' => 'gfm+line_blocks'],
    ],
    'commonmark x default keeps line blocks' => [
        'document' => $document([$lineBlock([$lineText('commonmark x line')])]),
        'expected' => '| commonmark x line',
        'options' => ['format' => 'commonmark_x'],
    ],
    'source value alias in multiline block' => [
        'document' => $document([$lineBlock([$lineText('value line', 'value'), $lineText('* literal', 'literal')])]),
        'expected' => "| value line\n| \\* literal",
    ],
    'child nonbreaking indentation becomes spaces' => [
        'document' => $document([$lineBlock([$lineChildren([$text(str_repeat("\xC2\xA0", 3) . 'child indent')])])]),
        'expected' => '|    child indent',
    ],
];

$cases = $sourceTextCases + $inlinePayloadCases + $structuralCases;

$findFirstLineBlock = static function (AstNode $node) use (&$findFirstLineBlock): ?AstNode {
    if ($node->type === 'line_block') {
        return $node;
    }

    foreach ($node->children as $child) {
        $lineBlockNode = $findFirstLineBlock($child);
        if ($lineBlockNode instanceof AstNode) {
            return $lineBlockNode;
        }
    }

    return null;
};

$tests = [
    'records markdown writer line block completion surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(70, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer line block completion surge ' . $label] =
        static function (TestRunner $t) use ($case, $findFirstLineBlock): void {
            $options = $case['options'] ?? [];
            $markdown = (new MarkdownWriter($options))->write($case['document']);

            $t->same($case['expected'], $markdown);

            if (($case['roundTrip'] ?? true) !== true) {
                return;
            }

            $readerOptions = $case['readerOptions'] ?? $options;
            if ($readerOptions === [] || (($readerOptions['format'] ?? null) !== null && str_contains((string) $readerOptions['format'], 'line_blocks'))) {
                $roundTrip = (new MarkdownReader($readerOptions))->read($markdown);
                $lineBlockNode = $findFirstLineBlock($roundTrip);

                $t->true($lineBlockNode instanceof AstNode, 'Round trip should keep a line_block for ' . $markdown);
            }
        };
}

return $tests;
