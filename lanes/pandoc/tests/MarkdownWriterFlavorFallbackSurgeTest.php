<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);
$writeDocument = static fn (array $blocks, array $options = []): string => (new MarkdownWriter($options))->write($document($blocks));
$writeParagraph = static fn (array $children, array $options = []): string => $writeDocument([$paragraph($children)], $options);

$cases = [];
$case = static fn (AstNode $document, string $expected, array $options = []): array => [
    'document' => $document,
    'expected' => $expected,
    'options' => $options,
];
$paragraphCase = static fn (array $children, string $expected, array $options = []): array => $case(
    $document([$paragraph($children)]),
    $expected,
    $options
);
$blockCase = static fn (AstNode $block, string $expected, array $options = []): array => $case(
    $document([$block]),
    $expected,
    $options
);

$commonmarkOptions = ['format' => 'commonmark'];
$gfmOptions = ['format' => 'gfm'];

$commonmarkFallbacks = [
    'strikeout uses html del' => $paragraphCase([
        $inline('strikeout', [$text('gone')]),
    ], '<del>gone</del>', $commonmarkOptions),
    'superscript uses html sup' => $paragraphCase([
        $inline('superscript', [$text('build 42')]),
    ], '<sup>build 42</sup>', $commonmarkOptions),
    'subscript uses html sub' => $paragraphCase([
        $inline('subscript', [$text('H2O')]),
    ], '<sub>H2O</sub>', $commonmarkOptions),
    'small caps uses semantic span' => $paragraphCase([
        $inline('small_caps', [$text('Caps')]),
    ], '<span class="smallcaps">Caps</span>', $commonmarkOptions),
    'underline uses semantic span' => $paragraphCase([
        $inline('underline', [$text('under')]),
    ], '<span class="underline">under</span>', $commonmarkOptions),
    'mark span uses html mark class' => $paragraphCase([
        $inline('span', [$text('marked')], ['classes' => ['mark']]),
    ], '<span class="mark">marked</span>', $commonmarkOptions),
    'attributed span uses html span' => $paragraphCase([
        $inline('span', [$text('packet')], ['id' => 'review', 'classes' => ['note'], 'attributes' => ['data-kind' => 'handoff']]),
    ], '<span id="review" class="note" data-kind="handoff">packet</span>', $commonmarkOptions),
    'emoji metadata span stays html' => $paragraphCase([
        $inline('span', [$text('ok')], ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'thumbsup']]),
    ], '<span class="emoji" data-emoji="thumbsup">ok</span>', $commonmarkOptions),
    'abbreviation stays html span' => $paragraphCase([
        $inline('span', [$text('HTML')], ['classes' => ['abbr'], 'attributes' => ['title' => 'Hypertext Markup Language']]),
    ], '<span class="abbr" title="Hypertext Markup Language">HTML</span>', $commonmarkOptions),
    'attributed inline code uses html code' => $paragraphCase([
        $inline('code', [], ['text' => 'echo', 'id' => 'src', 'classes' => ['php']]),
    ], '<code id="src" class="php">echo</code>', $commonmarkOptions),
    'inline math uses html math span' => $paragraphCase([
        $inline('math', [], ['text' => 'x + y']),
    ], '<span class="math inline">x + y</span>', $commonmarkOptions),
    'display math uses html math span' => $paragraphCase([
        $inline('math', [], ['text' => 'x = y', 'display' => true]),
    ], '<span class="math display">x = y</span>', $commonmarkOptions),
    'attributed link uses html anchor' => $paragraphCase([
        $link(['url' => '/source', 'id' => 'source', 'classes' => ['tracked'], 'attributes' => ['data-id' => '42']], [$text('source')]),
    ], '<a id="source" class="tracked" data-id="42" href="/source">source</a>', $commonmarkOptions),
    'attributed image uses html image' => $paragraphCase([
        $image(['url' => 'media/hero.png', 'alt' => 'Hero', 'id' => 'hero', 'classes' => ['wide'], 'attributes' => ['width' => '640']]),
    ], '<img id="hero" class="wide" width="640" src="media/hero.png" alt="Hero" />', $commonmarkOptions),
    'wikilink falls back to html anchor' => $paragraphCase([
        $link(['url' => '/Page', 'classes' => ['wikilink']], [$text('Page')]),
    ], '<a class="wikilink" href="/Page">Page</a>', $commonmarkOptions),
    'reference link with attributes stays html anchor' => $paragraphCase([
        $link(['url' => '/source', 'id' => 'source'], [$text('source')]),
    ], '<a id="source" href="/source">source</a>', ['format' => 'commonmark', 'referenceLinks' => true]),
    'raw tex inline is dropped' => $paragraphCase([
        $text('a'),
        $inline('raw_tex', [], ['text' => '\\LaTeX{}']),
        $text('b'),
    ], 'ab', $commonmarkOptions),
    'raw tex block is dropped' => $blockCase(
        new AstNode('raw_tex', ['text' => '\\LaTeX{}']),
        '',
        $commonmarkOptions
    ),
    'raw html inline is preserved' => $paragraphCase([
        new AstNode('raw_html_inline', ['html' => '<span>raw</span>']),
    ], '<span>raw</span>', $commonmarkOptions),
    'raw html block is preserved' => $blockCase(
        new AstNode('raw_html', ['html' => '<div>raw</div>']),
        '<div>raw</div>',
        $commonmarkOptions
    ),
];

foreach ($commonmarkFallbacks as $name => $item) {
    $cases['commonmark flavor fallback ' . $name] = $item;
}

$gfmFallbacks = [
    'strikeout remains gfm syntax' => $paragraphCase([
        $inline('strikeout', [$text('gone')]),
    ], '~~gone~~', $gfmOptions),
    'superscript uses html sup' => $paragraphCase([
        $inline('superscript', [$text('build 42')]),
    ], '<sup>build 42</sup>', $gfmOptions),
    'subscript uses html sub' => $paragraphCase([
        $inline('subscript', [$text('H2O')]),
    ], '<sub>H2O</sub>', $gfmOptions),
    'attributed link uses html anchor' => $paragraphCase([
        $link(['url' => '/source', 'classes' => ['tracked']], [$text('source')]),
    ], '<a class="tracked" href="/source">source</a>', $gfmOptions),
    'attributed image uses html image' => $paragraphCase([
        $image(['url' => 'media/hero.png', 'alt' => 'Hero', 'classes' => ['wide']]),
    ], '<img class="wide" src="media/hero.png" alt="Hero" />', $gfmOptions),
    'inline code attributes use html code' => $paragraphCase([
        $inline('code', [], ['text' => 'echo', 'classes' => ['php']]),
    ], '<code class="php">echo</code>', $gfmOptions),
    'inline math uses html span' => $paragraphCase([
        $inline('math', [], ['text' => 'x + y']),
    ], '<span class="math inline">x + y</span>', $gfmOptions),
    'mark uses html span' => $paragraphCase([
        $inline('span', [$text('marked')], ['classes' => ['mark']]),
    ], '<span class="mark">marked</span>', $gfmOptions),
    'wikilink uses html anchor' => $paragraphCase([
        $link(['url' => '/Page', 'classes' => ['wikilink']], [$text('Page')]),
    ], '<a class="wikilink" href="/Page">Page</a>', $gfmOptions),
    'raw tex inline is dropped' => $paragraphCase([
        $inline('raw_tex', [], ['text' => '\\TeX{}']),
    ], '', $gfmOptions),
    'raw html inline is preserved' => $paragraphCase([
        new AstNode('raw_html_inline', ['html' => '<kbd>Esc</kbd>']),
    ], '<kbd>Esc</kbd>', $gfmOptions),
    'emoji extension keeps shortcode when glyph metadata matches' => $paragraphCase([
        $inline('span', [$text("\u{1F44D}")], ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'thumbsup']]),
    ], ':thumbsup:', $gfmOptions),
];

foreach ($gfmFallbacks as $name => $item) {
    $cases['gfm flavor fallback ' . $name] = $item;
}

$overrideCases = [
    'commonmark plus strikeout keeps markdown delimiter' => $paragraphCase([
        $inline('strikeout', [$text('gone')]),
    ], '~~gone~~', ['format' => 'commonmark+strikeout']),
    'commonmark plus superscript keeps caret delimiter' => $paragraphCase([
        $inline('superscript', [$text('build 42')]),
    ], '^build\\ 42^', ['format' => 'commonmark+superscript']),
    'commonmark plus subscript keeps tilde delimiter' => $paragraphCase([
        $inline('subscript', [$text('H2O')]),
    ], '~H2O~', ['format' => 'commonmark+subscript']),
    'commonmark plus tex math keeps dollar math' => $paragraphCase([
        $inline('math', [], ['text' => 'x + y']),
    ], '$x + y$', ['format' => 'commonmark+tex_math_dollars']),
    'commonmark plus link attributes keeps tuple' => $paragraphCase([
        $link(['url' => '/source', 'id' => 'source'], [$text('source')]),
    ], '[source](/source){#source}', ['format' => 'commonmark+link_attributes']),
    'commonmark plus code attributes keeps tuple' => $paragraphCase([
        $inline('code', [], ['text' => 'echo', 'classes' => ['php']]),
    ], '`echo`{.php}', ['format' => 'commonmark+inline_code_attributes']),
    'commonmark plus bracketed spans keeps span tuple' => $paragraphCase([
        $inline('span', [$text('packet')], ['classes' => ['note']]),
    ], '[packet]{.note}', ['format' => 'commonmark+bracketed_spans']),
    'commonmark plus mark keeps mark delimiter' => $paragraphCase([
        $inline('span', [$text('marked')], ['classes' => ['mark']]),
    ], '==marked==', ['format' => 'commonmark+mark']),
    'commonmark plus raw tex keeps tex inline' => $paragraphCase([
        $inline('raw_tex', [], ['text' => '\\LaTeX{}']),
    ], '\\LaTeX{}', ['format' => 'commonmark+raw_tex']),
    'extension list disables raw html inline' => $paragraphCase([
        new AstNode('raw_html_inline', ['html' => '<span>raw</span>']),
    ], '', ['format' => 'commonmark', 'extensions' => ['raw_html' => false]]),
    'gfm extension list disables strikeout' => $paragraphCase([
        $inline('strikeout', [$text('gone')]),
    ], '<del>gone</del>', ['format' => 'gfm', 'extensions' => ['-strikeout']]),
    'gfm extension list enables tex math' => $paragraphCase([
        $inline('math', [], ['text' => 'x + y']),
    ], '$x + y$', ['format' => 'gfm', 'extensions' => ['+tex_math_dollars']]),
];

foreach ($overrideCases as $name => $item) {
    $cases['markdown flavor extension override ' . $name] = $item;
}

$rawInlineTargets = [
    'commonmark' => [
        'markdown' => '*generic*',
        'pandoc' => '*pandoc*',
        'commonmark' => '*commonmark*',
        'gfm' => '',
        'markdown_github' => '',
        'html' => '<span>html</span>',
        'latex' => '',
        'context' => '',
    ],
    'gfm' => [
        'markdown' => '*generic*',
        'pandoc' => '*pandoc*',
        'commonmark' => '*commonmark*',
        'gfm' => '*gfm*',
        'markdown_github' => '*github*',
        'html' => '<span>html</span>',
        'latex' => '',
        'context' => '',
    ],
    'markdown' => [
        'markdown' => '*generic*',
        'pandoc' => '*pandoc*',
        'markdown_strict' => '*strict*',
        'markdown_phpextra' => '*extra*',
        'markdown_mmd' => '*mmd*',
        'commonmark' => '',
        'gfm' => '',
        'html' => '<span>html</span>',
        'latex' => '\\LaTeX{}',
        'context' => '\\starttext',
    ],
];

foreach ($rawInlineTargets as $target => $formats) {
    foreach ($formats as $format => $expected) {
        $attrs = ['format' => $format, 'text' => $expected !== '' ? $expected : 'dropped'];
        if ($format === 'html') {
            $attrs['html'] = $attrs['text'];
        } elseif ($format === 'latex' || $format === 'context') {
            $attrs['tex'] = $attrs['text'];
        } else {
            $attrs['markdown'] = $attrs['text'];
        }

        $cases["raw inline target {$target} format {$format}"] = $paragraphCase([
            new AstNode('raw_inline', $attrs),
        ], $expected, ['format' => $target]);
    }
}

$rawBlockTargets = [
    'commonmark' => [
        'markdown' => 'generic block',
        'pandoc' => 'pandoc block',
        'commonmark' => 'commonmark block',
        'gfm' => '',
        'markdown_github' => '',
        'html' => '<div>html block</div>',
        'latex' => '',
        'context' => '',
    ],
    'gfm' => [
        'markdown' => 'generic block',
        'pandoc' => 'pandoc block',
        'commonmark' => 'commonmark block',
        'gfm' => 'gfm block',
        'markdown_github' => 'github block',
        'html' => '<div>html block</div>',
        'latex' => '',
        'context' => '',
    ],
    'markdown' => [
        'markdown' => 'generic block',
        'pandoc' => 'pandoc block',
        'markdown_strict' => 'strict block',
        'markdown_phpextra' => 'extra block',
        'markdown_mmd' => 'mmd block',
        'commonmark' => '',
        'gfm' => '',
        'html' => '<div>html block</div>',
        'latex' => '\\LaTeX{}',
        'context' => '\\starttext',
    ],
];

foreach ($rawBlockTargets as $target => $formats) {
    foreach ($formats as $format => $expected) {
        $attrs = ['format' => $format, 'text' => $expected !== '' ? $expected : 'dropped'];
        if ($format === 'html') {
            $attrs['html'] = $attrs['text'];
        } elseif ($format === 'latex' || $format === 'context') {
            $attrs['tex'] = $attrs['text'];
        } else {
            $attrs['markdown'] = $attrs['text'];
        }

        $cases["raw block target {$target} format {$format}"] = $blockCase(
            new AstNode('raw_block', $attrs),
            $expected,
            ['format' => $target]
        );
    }
}

$tests = [
    'records markdown writer flavor fallback surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(96, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer flavor fallback surge ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
