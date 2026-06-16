<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value, string $field = 'text'): AstNode => new AstNode('text', [$field => $value]);
$node = static fn (string $type, array $attrs = [], array $children = []): AstNode => new AstNode($type, $attrs, $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$case = static fn (array $children, string $expected, array $options): array => [
    'document' => $document([$paragraph($children)]),
    'expected' => $expected,
    'options' => $options,
];

$commonmark = ['format' => 'commonmark'];
$gfm = ['format' => 'gfm'];

$cases = [];

$commonmarkCases = [
    'commonmark strikeout value text escapes html' => $case([
        $node('strikeout', [], [$text('gone & literal', 'value')]),
    ], '<del>gone &amp; literal</del>', $commonmark),
    'commonmark strikeout nested code literal' => $case([
        $node('strikeout', [], [$node('code', ['literal' => 'wp_code'])]),
    ], '<del><code>wp_code</code></del>', $commonmark),
    'commonmark superscript literal text' => $case([
        $node('superscript', [], [$text('build 42', 'literal')]),
    ], '<sup>build 42</sup>', $commonmark),
    'commonmark superscript alias attributes' => $case([
        $node('superscript', ['identifier' => 'sup-id', 'class' => 'review', 'keyvals' => [['data-x', '1']]], [$text('build', 'value')]),
    ], '<sup id="sup-id" class="review" data-x="1">build</sup>', $commonmark),
    'commonmark subscript content text' => $case([
        $node('subscript', [], [$text('H2O', 'content')]),
    ], '<sub>H2O</sub>', $commonmark),
    'commonmark subscript alias attributes' => $case([
        $node('subscript', ['identifier' => 'sub-id', 'className' => 'chem', 'keyValues' => ['data-kind' => 'formula']], [$text('H2O', 'string')]),
    ], '<sub id="sub-id" class="chem" data-kind="formula">H2O</sub>', $commonmark),
    'commonmark small caps alias attributes' => $case([
        $node('small_caps', ['identifier' => 'caps-id', 'class' => 'source', 'keyvals' => [['data-x', '1']]], [$text('Caps', 'value')]),
    ], '<span id="caps-id" class="smallcaps source" data-x="1">Caps</span>', $commonmark),
    'commonmark underline alias attributes' => $case([
        $node('underline', ['identifier' => 'under-id', 'class' => 'source'], [$text('under', 'literal')]),
    ], '<span id="under-id" class="underline source">under</span>', $commonmark),
    'commonmark mark span class alias' => $case([
        $node('span', ['class' => 'mark'], [$text('Marked', 'value')]),
    ], '<span class="mark">Marked</span>', $commonmark),
    'commonmark generic span alias attributes' => $case([
        $node('span', ['identifier' => 'span-id', 'class' => 'review', 'keyvals' => [['data-x', '1']]], [$text('Span', 'literal')]),
    ], '<span id="span-id" class="review" data-x="1">Span</span>', $commonmark),
    'commonmark abbreviation span attribute pairs' => $case([
        $node('span', ['class' => 'abbr', 'attributePairs' => [['title', 'Hypertext Markup Language']]], [$text('HTML', 'value')]),
    ], '<span class="abbr" title="Hypertext Markup Language">HTML</span>', $commonmark),
    'commonmark emoji metadata data attributes' => $case([
        $node('span', ['class' => 'emoji', 'dataAttributes' => ['data-emoji' => 'thumbsup']], [$text('ok', 'value')]),
    ], '<span class="emoji" data-emoji="thumbsup">ok</span>', $commonmark),
    'commonmark code code alias attributes' => $case([
        $node('code', ['code' => 'echo &', 'identifier' => 'code-id', 'class' => 'php review', 'keyValues' => ['data-x' => '1']]),
    ], '<code id="code-id" class="php review" data-x="1">echo &amp;</code>', $commonmark),
    'commonmark code literal escapes html' => $case([
        $node('code', ['literal' => '<tag attr="x">', 'class' => 'html']),
    ], '<code class="html">&lt;tag attr=&quot;x&quot;&gt;</code>', $commonmark),
    'commonmark code content merges html attributes' => $case([
        $node('code', ['content' => 'source', 'htmlAttributes' => ['class' => 'from-html'], 'class' => 'from-alias']),
    ], '<code class="from-html from-alias">source</code>', $commonmark),
    'commonmark math formula alias' => $case([
        $node('math', ['formula' => 'x < y']),
    ], '<span class="math inline">x &lt; y</span>', $commonmark),
    'commonmark math alias attributes' => $case([
        $node('math', ['formula' => 'x + y', 'identifier' => 'eq-id', 'class' => 'review', 'keyvals' => [['data-x', '1']]]),
    ], '<span id="eq-id" class="math inline review" data-x="1">x + y</span>', $commonmark),
    'commonmark display math math alias attributes' => $case([
        $node('math', ['math' => 'x = y', 'display' => true, 'identifier' => 'eq-display', 'className' => 'source']),
    ], '<span id="eq-display" class="math display source">x = y</span>', $commonmark),
    'commonmark link href title aliases' => $case([
        $node('link', ['href' => '/source', 'titleText' => 'Source title', 'identifier' => 'link-id', 'class' => 'tracked', 'keyvals' => [['data-id', '42']]], [$text('Source', 'value')]),
    ], '<a id="link-id" class="tracked" data-id="42" href="/source" title="Source title">Source</a>', $commonmark),
    'commonmark link target map aliases' => $case([
        $node('link', ['target' => ['href' => '/assoc', 'titleText' => 'Assoc title'], 'className' => 'tracked'], [$text('Assoc', 'literal')]),
    ], '<a class="tracked" href="/assoc" title="Assoc title">Assoc</a>', $commonmark),
    'commonmark link destination tuple aliases' => $case([
        $node('link', ['destination' => ['/tuple', 'Tuple title'], 'identifier' => 'tuple-id'], [$text('Tuple', 'content')]),
    ], '<a id="tuple-id" href="/tuple" title="Tuple title">Tuple</a>', $commonmark),
    'commonmark uri link alias with attributes' => $case([
        $node('link', ['uri' => 'https://example.test/source', 'class' => 'uri tracked'], [$text('https://example.test/source', 'value')]),
    ], '<a class="uri tracked" href="https://example.test/source">https://example.test/source</a>', $commonmark),
    'commonmark image src aliases' => $case([
        $node('image', ['src' => 'media/hero.png', 'altText' => 'Hero', 'titleText' => 'Hero title', 'identifier' => 'hero', 'className' => 'wide', 'keyvals' => [['width', '640']]]),
    ], '<img id="hero" class="wide" width="640" src="media/hero.png" alt="Hero" title="Hero title" />', $commonmark),
    'commonmark image target tuple aliases' => $case([
        $node('image', ['target' => ['media/tuple.png', 'Tuple image'], 'alternateText' => 'Tuple alt', 'class' => 'wide']),
    ], '<img class="wide" src="media/tuple.png" alt="Tuple alt" title="Tuple image" />', $commonmark),
    'commonmark image child text alt fallback' => $case([
        $node('image', ['href' => 'media/child.png', 'class' => 'asset'], [$text('Child alt', 'value')]),
    ], '<img class="asset" src="media/child.png" alt="Child alt" />', $commonmark),
    'commonmark wikilink class alias fallback' => $case([
        $node('link', ['href' => 'Runbook', 'class' => 'wikilink'], [$text('Runbook', 'value')]),
    ], '<a class="wikilink" href="Runbook">Runbook</a>', $commonmark),
    'commonmark reference link aliases fallback' => $case([
        $node('link', ['href' => '/source', 'identifier' => 'source'], [$text('Source', 'value')]),
    ], '<a id="source" href="/source">Source</a>', ['format' => 'commonmark', 'referenceLinks' => true]),
    'commonmark raw html inline literal alias' => $case([
        $node('raw_html_inline', ['literal' => '<span data-x="1">raw</span>']),
    ], '<span data-x="1">raw</span>', $commonmark),
    'commonmark raw html inline content alias' => $case([
        $node('raw_html_inline', ['content' => '<kbd>Esc</kbd>']),
    ], '<kbd>Esc</kbd>', $commonmark),
    'commonmark raw inline format name html alias' => $case([
        $node('raw_inline', ['formatName' => 'html', 'raw' => '<em>raw</em>']),
    ], '<em>raw</em>', $commonmark),
    'commonmark raw inline raw format html alias' => $case([
        $node('raw_inline', ['rawFormat' => 'html5', 'html' => '<strong>raw</strong>']),
    ], '<strong>raw</strong>', $commonmark),
    'commonmark raw inline format html text alias' => $case([
        $node('raw_inline', ['format' => 'html', 'text' => '<b>raw</b>']),
    ], '<b>raw</b>', $commonmark),
    'commonmark raw inline latex alias drops tex' => $case([
        $node('raw_inline', ['formatName' => 'latex', 'tex' => '\\LaTeX{}']),
    ], '', $commonmark),
    'commonmark raw tex content alias drops tex' => $case([
        $node('raw_tex', ['content' => '\\TeX{}']),
    ], '', $commonmark),
    'commonmark nested span inline aliases' => $case([
        $node('span', ['class' => 'review'], [
            $node('emph', [], [$text('em', 'value')]),
            $node('strong', [], [$text('strong', 'literal')]),
        ]),
    ], '<span class="review"><em>em</em><strong>strong</strong></span>', $commonmark),
    'commonmark anchor nested code alias' => $case([
        $node('link', ['href' => '/code', 'class' => 'tracked'], [$text('Use ', 'value'), $node('code', ['code' => 'wp_code'])]),
    ], '<a class="tracked" href="/code">Use <code>wp_code</code></a>', $commonmark),
    'commonmark span directional aliases' => $case([
        $node('span', ['identifier' => 'dir-span', 'class' => 'review', 'dir' => 'rtl', 'lang' => 'ar'], [$text('direction', 'value')]),
    ], '<span id="dir-span" class="review" dir="rtl" lang="ar">direction</span>', $commonmark),
];

foreach ($commonmarkCases as $label => $item) {
    $cases[$label] = $item;
}

$gfmCases = [
    'gfm superscript value fallback' => $case([
        $node('superscript', [], [$text('build 42', 'value')]),
    ], '<sup>build 42</sup>', $gfm),
    'gfm subscript content fallback' => $case([
        $node('subscript', [], [$text('H2O', 'content')]),
    ], '<sub>H2O</sub>', $gfm),
    'gfm small caps alias attributes' => $case([
        $node('small_caps', ['identifier' => 'caps', 'class' => 'source'], [$text('Caps', 'literal')]),
    ], '<span id="caps" class="smallcaps source">Caps</span>', $gfm),
    'gfm underline alias attributes' => $case([
        $node('underline', ['identifier' => 'u', 'class' => 'source'], [$text('under', 'value')]),
    ], '<span id="u" class="underline source">under</span>', $gfm),
    'gfm code code alias attributes' => $case([
        $node('code', ['code' => 'echo', 'identifier' => 'code', 'class' => 'php']),
    ], '<code id="code" class="php">echo</code>', $gfm),
    'gfm math formula alias' => $case([
        $node('math', ['formula' => 'x + y']),
    ], '<span class="math inline">x + y</span>', $gfm),
    'gfm display math attrs alias' => $case([
        $node('math', ['formula' => 'x = y', 'display' => true, 'identifier' => 'eq', 'class' => 'source']),
    ], '<span id="eq" class="math display source">x = y</span>', $gfm),
    'gfm attributed link href alias' => $case([
        $node('link', ['href' => '/source', 'class' => 'tracked'], [$text('Source', 'value')]),
    ], '<a class="tracked" href="/source">Source</a>', $gfm),
    'gfm attributed image src alias' => $case([
        $node('image', ['src' => 'media/hero.png', 'altText' => 'Hero', 'class' => 'wide']),
    ], '<img class="wide" src="media/hero.png" alt="Hero" />', $gfm),
    'gfm wikilink class alias fallback' => $case([
        $node('link', ['href' => 'Runbook', 'class' => 'wikilink'], [$text('Runbook', 'value')]),
    ], '<a class="wikilink" href="Runbook">Runbook</a>', $gfm),
    'gfm mark span class alias' => $case([
        $node('span', ['class' => 'mark'], [$text('Marked', 'value')]),
    ], '<span class="mark">Marked</span>', $gfm),
    'gfm abbreviation span aliases' => $case([
        $node('span', ['class' => 'abbr', 'attributePairs' => [['title', 'Hypertext Markup Language']]], [$text('HTML', 'value')]),
    ], '<span class="abbr" title="Hypertext Markup Language">HTML</span>', $gfm),
    'gfm raw html inline raw alias' => $case([
        $node('raw_html_inline', ['raw' => '<kbd>Esc</kbd>']),
    ], '<kbd>Esc</kbd>', $gfm),
    'gfm raw inline format name html alias' => $case([
        $node('raw_inline', ['formatName' => 'html', 'raw' => '<em>raw</em>']),
    ], '<em>raw</em>', $gfm),
    'gfm raw tex content drops tex' => $case([
        $node('raw_tex', ['content' => '\\TeX{}']),
    ], '', $gfm),
    'gfm generic span aliases' => $case([
        $node('span', ['identifier' => 'span', 'class' => 'review', 'keyvals' => [['data-x', '1']]], [$text('Span', 'value')]),
    ], '<span id="span" class="review" data-x="1">Span</span>', $gfm),
    'gfm link target tuple title alias' => $case([
        $node('link', ['target' => ['/target', 'Target title'], 'class' => 'tracked'], [$text('Target', 'value')]),
    ], '<a class="tracked" href="/target" title="Target title">Target</a>', $gfm),
    'gfm image child alt alias' => $case([
        $node('image', ['href' => 'media/child.png', 'class' => 'wide'], [$text('Child alt', 'value')]),
    ], '<img class="wide" src="media/child.png" alt="Child alt" />', $gfm),
    'gfm disabled strikeout value fallback' => $case([
        $node('strikeout', [], [$text('gone', 'value')]),
    ], '<del>gone</del>', ['format' => 'gfm', 'extensions' => ['-strikeout']]),
    'gfm disabled strikeout attrs fallback' => $case([
        $node('strikeout', ['identifier' => 'gone', 'class' => 'review'], [$text('gone', 'literal')]),
    ], '<del id="gone" class="review">gone</del>', ['format' => 'gfm', 'extensions' => ['-strikeout']]),
];

foreach ($gfmCases as $label => $item) {
    $cases[$label] = $item;
}

$markdownDisabledCases = [
    'markdown disabled link attributes href alias' => $case([
        $node('link', ['href' => '/source', 'identifier' => 'source', 'class' => 'tracked'], [$text('Source', 'value')]),
    ], '<a id="source" class="tracked" href="/source">Source</a>', ['format' => 'markdown', 'extensions' => ['-link_attributes']]),
    'markdown disabled link attributes target map alias' => $case([
        $node('link', ['target' => ['href' => '/assoc', 'titleText' => 'Assoc title'], 'class' => 'tracked'], [$text('Assoc', 'value')]),
    ], '<a class="tracked" href="/assoc" title="Assoc title">Assoc</a>', ['format' => 'markdown', 'extensions' => ['-link_attributes']]),
    'markdown disabled link attributes destination tuple alias' => $case([
        $node('link', ['destination' => ['/tuple', 'Tuple title'], 'identifier' => 'tuple'], [$text('Tuple', 'value')]),
    ], '<a id="tuple" href="/tuple" title="Tuple title">Tuple</a>', ['format' => 'markdown', 'extensions' => ['-link_attributes']]),
    'markdown disabled image attributes src alias' => $case([
        $node('image', ['src' => 'media/hero.png', 'altText' => 'Hero', 'identifier' => 'hero', 'class' => 'wide']),
    ], '<img id="hero" class="wide" src="media/hero.png" alt="Hero" />', ['format' => 'markdown', 'extensions' => ['-link_attributes']]),
    'markdown disabled image attributes target tuple alias' => $case([
        $node('image', ['target' => ['media/tuple.png', 'Tuple image'], 'alternateText' => 'Tuple alt', 'class' => 'wide']),
    ], '<img class="wide" src="media/tuple.png" alt="Tuple alt" title="Tuple image" />', ['format' => 'markdown', 'extensions' => ['-link_attributes']]),
    'markdown disabled inline code attributes code alias' => $case([
        $node('code', ['code' => 'echo', 'identifier' => 'code', 'class' => 'php']),
    ], '<code id="code" class="php">echo</code>', ['format' => 'markdown', 'extensions' => ['-inline_code_attributes']]),
    'markdown disabled inline code attributes literal alias' => $case([
        $node('code', ['literal' => '<code>', 'class' => 'php']),
    ], '<code class="php">&lt;code&gt;</code>', ['format' => 'markdown', 'extensions' => ['-inline_code_attributes']]),
    'markdown disabled tex math formula alias' => $case([
        $node('math', ['formula' => 'x + y', 'identifier' => 'eq']),
    ], '<span id="eq" class="math inline">x + y</span>', ['format' => 'markdown', 'extensions' => ['-tex_math_dollars']]),
    'markdown disabled tex math display alias' => $case([
        $node('math', ['math' => 'x = y', 'display' => true, 'class' => 'source']),
    ], '<span class="math display source">x = y</span>', ['format' => 'markdown', 'extensions' => ['-tex_math_dollars']]),
    'markdown disabled bracketed spans generic alias' => $case([
        $node('span', ['identifier' => 'span', 'class' => 'review', 'keyvals' => [['data-x', '1']]], [$text('Span', 'value')]),
    ], '<span id="span" class="review" data-x="1">Span</span>', ['format' => 'markdown', 'extensions' => ['-bracketed_spans']]),
    'markdown disabled bracketed spans nested aliases' => $case([
        $node('span', ['class' => 'review'], [$node('emph', [], [$text('em', 'value')])]),
    ], '<span class="review"><em>em</em></span>', ['format' => 'markdown', 'extensions' => ['-bracketed_spans']]),
    'markdown disabled bracketed spans small caps alias' => $case([
        $node('small_caps', ['identifier' => 'caps', 'class' => 'source'], [$text('Caps', 'value')]),
    ], '<span id="caps" class="smallcaps source">Caps</span>', ['format' => 'markdown', 'extensions' => ['-bracketed_spans']]),
    'markdown disabled underline attrs alias' => $case([
        $node('underline', ['identifier' => 'u', 'class' => 'source'], [$text('under', 'value')]),
    ], '<span id="u" class="underline source">under</span>', ['format' => 'markdown', 'extensions' => ['-underline']]),
    'markdown disabled strikeout plain alias' => $case([
        $node('strikeout', [], [$text('gone', 'value')]),
    ], '<del>gone</del>', ['format' => 'markdown', 'extensions' => ['-strikeout']]),
    'markdown disabled strikeout attrs alias' => $case([
        $node('strikeout', ['identifier' => 'gone', 'class' => 'review'], [$text('gone', 'literal')]),
    ], '<del id="gone" class="review">gone</del>', ['format' => 'markdown', 'extensions' => ['-strikeout']]),
    'markdown disabled superscript plain alias' => $case([
        $node('superscript', [], [$text('build', 'value')]),
    ], '<sup>build</sup>', ['format' => 'markdown', 'extensions' => ['-superscript']]),
    'markdown disabled superscript attrs alias' => $case([
        $node('superscript', ['identifier' => 'sup', 'class' => 'review'], [$text('build', 'literal')]),
    ], '<sup id="sup" class="review">build</sup>', ['format' => 'markdown', 'extensions' => ['-superscript']]),
    'markdown disabled subscript plain alias' => $case([
        $node('subscript', [], [$text('H2O', 'value')]),
    ], '<sub>H2O</sub>', ['format' => 'markdown', 'extensions' => ['-subscript']]),
    'markdown disabled subscript attrs alias' => $case([
        $node('subscript', ['identifier' => 'sub', 'class' => 'review'], [$text('H2O', 'literal')]),
    ], '<sub id="sub" class="review">H2O</sub>', ['format' => 'markdown', 'extensions' => ['-subscript']]),
    'markdown disabled raw html inline value alias' => $case([
        $node('raw_html_inline', ['value' => '<span>raw</span>']),
    ], '', ['format' => 'markdown', 'extensions' => ['-raw_html']]),
    'markdown disabled raw inline html alias' => $case([
        $node('raw_inline', ['formatName' => 'html', 'raw' => '<span>raw</span>']),
    ], '', ['format' => 'markdown', 'extensions' => ['-raw_html']]),
    'markdown disabled raw inline latex alias' => $case([
        $node('raw_inline', ['formatName' => 'latex', 'tex' => '\\LaTeX{}']),
    ], '', ['format' => 'markdown', 'extensions' => ['-raw_tex']]),
    'markdown disabled raw tex literal alias' => $case([
        $node('raw_tex', ['literal' => '\\TeX{}']),
    ], '', ['format' => 'markdown', 'extensions' => ['-raw_tex']]),
];

foreach ($markdownDisabledCases as $label => $item) {
    $cases[$label] = $item;
}

$tests = [
    'records markdown writer inline html fallback alias surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(80, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer inline html fallback alias surge ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
