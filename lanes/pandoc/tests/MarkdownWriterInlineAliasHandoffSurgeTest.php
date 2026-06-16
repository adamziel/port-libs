<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$aliasText = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$aliasNode = static fn (string $type, array $attrs = [], array $children = []): AstNode => new AstNode($type, $attrs, $children);
$aliasParagraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$aliasDocument = static fn (array $children): AstNode => new AstNode('document', [], $children);
$aliasInlineDocument = static fn (array $children): AstNode => $aliasDocument([$aliasParagraph($children)]);
$aliasCase = static fn (array $children, string $expected, array $options = []): array => [
    'document' => $aliasInlineDocument($children),
    'expected' => $expected,
    'options' => $options,
];

$cases = [
    'text value alias escapes emphasis marker' => $aliasCase([
        $aliasNode('text', ['value' => 'alpha * literal']),
    ], 'alpha \* literal'),
    'text literal alias escapes heading marker' => $aliasCase([
        $aliasNode('text', ['literal' => '# literal heading']),
    ], '\# literal heading'),
    'text content alias escapes ordered marker' => $aliasCase([
        $aliasNode('text', ['content' => '1. literal list']),
    ], '1\. literal list'),
    'text string alias escapes entity marker' => $aliasCase([
        $aliasNode('text', ['string' => 'AT&amp;T']),
    ], 'AT&amp;amp;T'),
    'code literal alias renders code span' => $aliasCase([
        $aliasNode('code', ['literal' => 'literal_code']),
    ], '`literal_code`'),
    'code code alias keeps attribute aliases' => $aliasCase([
        $aliasNode('code', ['code' => 'source:key', 'identifier' => 'code-id', 'class' => 'php review']),
    ], '`source:key`{#code-id .php .review}'),
    'math formula alias renders inline math' => $aliasCase([
        $aliasNode('math', ['formula' => 'x + y']),
    ], '$x + y$'),
    'display math formula alias keeps attribute aliases' => $aliasCase([
        $aliasNode('math', [
            'formula' => 'x = y',
            'display' => true,
            'identifier' => 'eq',
            'className' => 'math',
            'keyvals' => [['data-x', '1']],
        ]),
    ], '$$x = y$${#eq .math data-x="1"}'),
    'raw markdown format alias emits raw markdown' => $aliasCase([
        $aliasNode('raw_inline', ['rawFormat' => 'gfm', 'raw' => '[raw]{.packet}']),
    ], '[raw]{.packet}'),
    'raw html body alias emits raw html' => $aliasCase([
        $aliasNode('raw_inline', ['format' => 'html5', 'raw' => '<span data-x="1">raw</span>']),
    ], '<span data-x="1">raw</span>'),
    'raw tex content alias emits raw tex' => $aliasCase([
        $aliasNode('raw_inline', ['formatName' => 'latex', 'content' => '\LaTeX{}']),
    ], '\LaTeX{}'),
    'raw markdown node literal alias emits markdown' => $aliasCase([
        $aliasNode('raw_markdown', ['literal' => '*raw*']),
    ], '*raw*'),
    'link href alias renders explicit link' => $aliasCase([
        $aliasNode('link', ['href' => '/source'], [$aliasText('packet')]),
    ], '[packet](/source)'),
    'link target string alias renders explicit link' => $aliasCase([
        $aliasNode('link', ['target' => '/target'], [$aliasText('packet')]),
    ], '[packet](/target)'),
    'link destination tuple renders title' => $aliasCase([
        $aliasNode('link', ['destination' => ['/tuple', 'Tuple title']], [$aliasText('packet')]),
    ], '[packet](/tuple "Tuple title")'),
    'link target map renders title' => $aliasCase([
        $aliasNode('link', ['target' => ['href' => '/assoc', 'titleText' => 'Assoc title']], [$aliasText('packet')]),
    ], '[packet](/assoc "Assoc title")'),
    'link uri alias remains autolink' => $aliasCase([
        $aliasNode('link', ['uri' => 'https://example.test/source'], [$aliasText('https://example.test/source')]),
    ], '<https://example.test/source>'),
    'mailto href alias with class string remains email autolink' => $aliasCase([
        $aliasNode('link', ['href' => 'mailto:editor@example.test', 'class' => 'email'], [$aliasText('editor@example.test')]),
    ], '<editor@example.test>'),
    'link tooltip alias renders title' => $aliasCase([
        $aliasNode('link', ['href' => '/source', 'tooltip' => 'Tooltip'], [$aliasText('packet')]),
    ], '[packet](/source "Tooltip")'),
    'link identifier className and attributes map render attributes' => $aliasCase([
        $aliasNode('link', [
            'href' => '/source',
            'identifier' => 'link-id',
            'className' => 'tracked review',
            'attributes' => ['data-x' => '1'],
        ], [$aliasText('packet')]),
    ], '[packet](/source){#link-id .tracked .review data-x="1"}'),
    'link attributes list pairs render attributes' => $aliasCase([
        $aliasNode('link', ['href' => '/source', 'attributes' => [['data-a', 'A'], ['data-b', 'B']]], [$aliasText('packet')]),
    ], '[packet](/source){data-a="A" data-b="B"}'),
    'link keyvals named pairs render attributes' => $aliasCase([
        $aliasNode('link', ['href' => '/source', 'keyvals' => [['key' => 'data-k', 'value' => 'K'], ['name' => 'data-n', 'value' => 'N']]], [$aliasText('packet')]),
    ], '[packet](/source){data-k="K" data-n="N"}'),
    'link keyValues map merges with class string' => $aliasCase([
        $aliasNode('link', ['href' => '/source', 'class' => 'alpha beta', 'keyValues' => ['data-z' => 'Z']], [$aliasText('packet')]),
    ], '[packet](/source){.alpha .beta data-z="Z"}'),
    'link src alias renders asset target' => $aliasCase([
        $aliasNode('link', ['src' => 'media/asset.png', 'titleText' => 'Asset title'], [$aliasText('asset')]),
    ], '[asset](media/asset.png "Asset title")'),
    'link href newline destination is percent encoded' => $aliasCase([
        $aliasNode('link', ['href' => "/a\nb"], [$aliasText('packet')]),
    ], '[packet](/a%0Ab)'),
    'link href with spaces is angle wrapped' => $aliasCase([
        $aliasNode('link', ['href' => '/source packet'], [$aliasText('packet')]),
    ], '[packet](</source packet>)'),
    'reference link href alias emits definition' => $aliasCase([
        $aliasNode('link', ['href' => '/packet'], [$aliasText('Packet')]),
    ], "[Packet]\n\n  [Packet]: /packet", ['referenceLinks' => true]),
    'reference link target tuple keeps title' => $aliasCase([
        $aliasNode('link', ['target' => ['/packet', 'Packet title']], [$aliasText('Packet')]),
    ], "[Packet]\n\n  [Packet]: /packet \"Packet title\"", ['referenceLinks' => true]),
    'reference link attribute aliases emit definition attributes' => $aliasCase([
        $aliasNode('link', ['href' => '/packet', 'identifier' => 'packet-id', 'class' => 'tracked', 'keyvals' => [['data-id', '9']]], [$aliasText('Packet')]),
    ], "[Packet]\n\n  [Packet]: /packet {#packet-id .tracked data-id=\"9\"}", ['referenceLinks' => true]),
    'reference link href alias reuses repeated target' => $aliasCase([
        $aliasNode('link', ['href' => '/same'], [$aliasText('First')]),
        $aliasText(' and '),
        $aliasNode('link', ['href' => '/same'], [$aliasText('Second')]),
    ], "[First] and [Second][First]\n\n  [First]: /same", ['referenceLinks' => true]),
    'image src alias with altText renders image' => $aliasCase([
        $aliasNode('image', ['src' => 'media/a.png', 'altText' => 'Alt text']),
    ], '![Alt text](media/a.png)'),
    'image href alias with description renders alt' => $aliasCase([
        $aliasNode('image', ['href' => 'media/a.png', 'description' => 'Description']),
    ], '![Description](media/a.png)'),
    'image target tuple keeps title' => $aliasCase([
        $aliasNode('image', ['target' => ['media/a.png', 'Image title'], 'alt' => 'Alt']),
    ], '![Alt](media/a.png "Image title")'),
    'image child value matching target omits label' => $aliasCase([
        $aliasNode('image', ['src' => 'media/a.png'], [$aliasNode('text', ['value' => 'media/a.png'])]),
    ], '![](media/a.png)'),
    'image alternateText differing from label is preserved as attribute' => $aliasCase([
        $aliasNode('image', ['src' => 'media/a.png', 'alternateText' => 'Plain alt'], [$aliasText('Caption')]),
    ], '![Caption](media/a.png){alt="Plain alt"}'),
    'image identifier className and keyvals render attributes' => $aliasCase([
        $aliasNode('image', ['src' => 'media/a.png', 'altText' => 'Alt', 'identifier' => 'img', 'className' => 'hero', 'keyvals' => [['width', '640']]]),
    ], '![Alt](media/a.png){#img .hero width="640"}'),
    'image newline src destination is percent encoded' => $aliasCase([
        $aliasNode('image', ['src' => "media/a\nb.png", 'altText' => 'Alt']),
    ], '![Alt](media/a%0Ab.png)'),
    'image empty src remains angle wrapped' => $aliasCase([
        $aliasNode('image', ['src' => '', 'altText' => 'Alt']),
    ], '![Alt](<>)'),
    'wikilink href alias same target uses compact syntax' => $aliasCase([
        $aliasNode('link', ['href' => 'Runbook', 'class' => 'wikilink'], [$aliasText('Runbook')]),
    ], '[[Runbook]]'),
    'wikilink target alias with label uses pipe syntax' => $aliasCase([
        $aliasNode('link', ['target' => '/docs/runbook', 'class' => 'wikilink'], [$aliasText('Runbook')]),
    ], '[[Runbook|/docs/runbook]]'),
    'span identifier className and keyvals render attributes' => $aliasCase([
        $aliasNode('span', ['identifier' => 'span', 'className' => 'review', 'keyvals' => [['data-x', '1']]], [$aliasText('span')]),
    ], '[span]{#span .review data-x="1"}'),
    'span class string mark uses mark shorthand' => $aliasCase([
        $aliasNode('span', ['class' => 'mark'], [$aliasText('marked')]),
    ], '==marked=='),
    'abbreviation className and attributePairs emit definition' => $aliasCase([
        $aliasNode('span', ['className' => 'abbr', 'attributePairs' => [['title', 'Hypertext Markup Language']]], [$aliasText('HTML')]),
    ], "HTML\n\n*[HTML]: Hypertext Markup Language"),
    'small caps identifier keeps semantic class first' => $aliasCase([
        $aliasNode('small_caps', ['identifier' => 'caps', 'class' => 'review'], [$aliasText('Caps')]),
    ], '[Caps]{#caps .smallcaps .review}'),
    'underline identifier keeps semantic class' => $aliasCase([
        $aliasNode('underline', ['identifier' => 'u1'], [$aliasText('under')]),
    ], '[under]{#u1 .underline}'),
    'strikeout className falls back to attributed span' => $aliasCase([
        $aliasNode('strikeout', ['className' => 'review'], [$aliasText('gone')]),
    ], '[gone]{.strikeout .review}'),
    'superscript className falls back to attributed span' => $aliasCase([
        $aliasNode('superscript', ['className' => 'review'], [$aliasText('build 42')]),
    ], '[build 42]{.superscript .review}'),
    'subscript keyvals falls back to attributed span' => $aliasCase([
        $aliasNode('subscript', ['keyvals' => [['data-x', '1']]], [$aliasText('H 2 O')]),
    ], '[H 2 O]{.subscript data-x="1"}'),
    'note noteLabel alias reuses source label' => $aliasCase([
        $aliasText('note'),
        $aliasNode('note', ['noteLabel' => 'review'], [$aliasParagraph([$aliasText('body')])]),
    ], "note[^review]\n\n[^review]: body"),
    'note noteLabel alias collision gets suffix' => $aliasCase([
        $aliasNode('note', ['noteLabel' => 'n'], [$aliasParagraph([$aliasText('one')])]),
        $aliasText(' '),
        $aliasNode('note', ['noteLabel' => 'n'], [$aliasParagraph([$aliasText('two')])]),
    ], "[^n] [^n-2]\n\n[^n]: one\n\n[^n-2]: two"),
    'citation citationId alias renders normal citation' => $aliasCase([
        $aliasNode('citation', ['citationId' => 'doe2026']),
    ], '[@doe2026]'),
    'citation identifier alias with suppress constructor renders suppress author' => $aliasCase([
        $aliasNode('citation', ['identifier' => 'doe2026', 'citationModeConstructor' => 'SuppressAuthor']),
    ], '[-@doe2026]'),
    'citation author constructor with locatorText renders source suffix' => $aliasCase([
        $aliasNode('citation', ['citationId' => 'doe2026', 'citationModeConstructor' => 'AuthorInText', 'locatorText' => 'p. 42']),
    ], '@doe2026, p. 42'),
    'citationPrefix alias inlines keep markup' => $aliasCase([
        $aliasNode('citation', ['citationId' => 'doe2026', 'citationPrefix' => [$aliasNode('emph', [], [$aliasText('see')])]]),
    ], '[*see* @doe2026]'),
    'citationSuffix alias text escapes markdown punctuation' => $aliasCase([
        $aliasNode('citation', ['citationId' => 'doe2026', 'citationSuffix' => 'chapter *intro*']),
    ], '[@doe2026, chapter \*intro\*]'),
    'citation group uses citationId aliases and constructors' => $aliasCase([
        $aliasNode('citation_group', [], [
            $aliasNode('citation', ['citationId' => 'one2026']),
            $aliasNode('citation', ['citationId' => 'two2025', 'citationModeConstructor' => 'SuppressAuthor']),
        ]),
    ], '[@one2026; -@two2025]'),
];

$tests = [
    'records markdown writer inline alias handoff surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(56, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer inline alias handoff surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
