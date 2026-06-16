<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionItem = static fn (string $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([new AstNode('definition_term', [], [$text($term)])], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$div = static fn (array $children, array $attrs = []): AstNode => new AstNode('div', $attrs, $children);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$rawBlock = static fn (string $html): AstNode => new AstNode('raw_block', ['format' => 'html', 'text' => $html]);
$rawMarkdown = static fn (string $markdown): AstNode => new AstNode('raw_markdown', ['markdown' => $markdown]);

$document = static function (array $definitionChildren) use ($definition, $definitionItem, $definitionList): AstNode {
    return new AstNode('document', [], [
        $definitionList([
            $definitionItem('Term', [
                $definition($definitionChildren),
            ]),
        ]),
    ]);
};

$expectedDefinition = static function (string $body): string {
    $lines = ['Term', ':', ''];
    foreach (explode("\n", $body) as $line) {
        $lines[] = $line === '' ? '' : '    ' . $line;
    }

    return implode("\n", $lines);
};

$firstDefinitionChildren = static function (AstNode $document): array {
    $definition = $document->children[0]->children[0]->children[1] ?? null;

    return $definition instanceof AstNode ? $definition->children : [];
};

$formatProfiles = [
    'markdown' => [],
    'commonmark+definition_lists' => ['format' => 'commonmark+definition_lists'],
    'gfm+definition_lists' => ['format' => 'gfm+definition_lists'],
    'markdown strict+definition_lists' => ['format' => 'markdown_strict+definition_lists'],
    'markdown php extra' => ['format' => 'markdown_phpextra'],
];

$cases = [
    '01 nested definition list simple body' => [
        'children' => static fn (): array => [
            $definitionList([
                $definitionItem('Nested', [
                    $definition([$paragraph('Nested definition')]),
                ]),
            ]),
        ],
        'body' => "Nested\n:   Nested definition",
        'roundTripTypes' => ['definition_list'],
    ],
    '02 nested definition list repeated definitions' => [
        'children' => static fn (): array => [
            $definitionList([
                $definitionItem('Nested', [
                    $definition([$paragraph('first')]),
                    $definition([$paragraph('second')]),
                ]),
            ]),
        ],
        'body' => "Nested\n:   first\n:   second",
        'roundTripTypes' => ['definition_list'],
    ],
    '03 nested definition list repeated items' => [
        'children' => static fn (): array => [
            $definitionList([
                $definitionItem('Alpha', [
                    $definition([$paragraph('one')]),
                ]),
                $definitionItem('Beta', [
                    $definition([$paragraph('two')]),
                ]),
            ]),
        ],
        'body' => "Alpha\n:   one\n\nBeta\n:   two",
        'roundTripTypes' => ['definition_list'],
    ],
    '04 raw html comment body' => [
        'children' => static fn (): array => [$rawHtml('<!-- raw definition -->')],
        'body' => '<!-- raw definition -->',
        'roundTripTypes' => ['raw_html'],
    ],
    '05 raw html single div body' => [
        'children' => static fn (): array => [$rawHtml('<div>Raw definition</div>')],
        'body' => '<div>Raw definition</div>',
        'roundTripTypes' => ['div'],
    ],
    '06 raw html multiline section body' => [
        'children' => static fn (): array => [$rawHtml("<section>\n<p>Raw body</p>\n</section>")],
        'body' => "<section>\n<p>Raw body</p>\n</section>",
        'roundTripTypes' => ['raw_html'],
    ],
    '07 raw html pre code body' => [
        'children' => static fn (): array => [$rawHtml('<pre><code>alpha &amp; beta</code></pre>')],
        'body' => '<pre><code>alpha &amp; beta</code></pre>',
        'roundTripTypes' => ['code_block'],
    ],
    '08 raw html table body' => [
        'children' => static fn (): array => [$rawHtml('<table><tbody><tr><td>A</td></tr></tbody></table>')],
        'body' => '<table><tbody><tr><td>A</td></tr></tbody></table>',
        'roundTripTypes' => ['table'],
    ],
    '09 raw block html figure body' => [
        'children' => static fn (): array => [$rawBlock("<figure>\n<img src=\"a.png\" alt=\"A\">\n</figure>")],
        'body' => "<figure>\n<img src=\"a.png\" alt=\"A\">\n</figure>",
        'roundTripTypes' => ['raw_html'],
    ],
    '10 raw block html aside body' => [
        'children' => static fn (): array => [$rawBlock('<aside data-kind="note">Aside</aside>')],
        'body' => '<aside data-kind="note">Aside</aside>',
    ],
    '11 fenced div body uses html fallback outside pandoc markdown' => [
        'children' => static fn (): array => [$div([$paragraph('inside')], ['classes' => ['review']])],
        'body' => "::: {.review}\ninside\n:::",
        'htmlBody' => '<div class="review"><p>inside</p></div>',
    ],
    '12 attributed fenced div body uses html fallback outside pandoc markdown' => [
        'children' => static fn (): array => [
            $div([$paragraph('inside')], [
                'id' => 'box',
                'classes' => ['review'],
                'attributes' => ['data-kind' => 'definition'],
            ]),
        ],
        'body' => "::: {#box .review data-kind=\"definition\"}\ninside\n:::",
        'htmlBody' => '<div id="box" class="review" data-kind="definition"><p>inside</p></div>',
    ],
    '13 empty fenced div body uses html fallback outside pandoc markdown' => [
        'children' => static fn (): array => [$div([], ['classes' => ['empty']])],
        'body' => "::: {.empty}\n:::",
        'htmlBody' => '<div class="empty"></div>',
    ],
    '14 div body with nested list uses html fallback outside pandoc markdown' => [
        'children' => static fn (): array => [
            $div([
                $paragraph('lead'),
                $bulletList([
                    $listItem([$paragraph('item')]),
                ]),
            ], ['classes' => ['review']]),
        ],
        'body' => "::: {.review}\nlead\n\n- item\n:::",
        'htmlBody' => '<div class="review"><p>lead</p><ul><li><p>item</p></li></ul></div>',
    ],
    '15 div body with code block uses html fallback outside pandoc markdown' => [
        'children' => static fn (): array => [$div([$codeBlock('echo inside;')], ['classes' => ['code-review']])],
        'body' => "::: {.code-review}\n    echo inside;\n:::",
        'htmlBody' => '<div class="code-review"><pre><code>echo inside;</code></pre></div>',
    ],
    '16 raw markdown fenced body stays indented as definition content' => [
        'children' => static fn (): array => [$rawMarkdown("::: note\nbody\n:::")],
        'body' => "::: note\nbody\n:::",
    ],
];

$tests = [
    'records markdown writer definition-list block-body final harvest mapped case count' => static function (TestRunner $t) use ($cases, $formatProfiles): void {
        $t->same(80, count($cases) * count($formatProfiles));
    },
];

foreach ($formatProfiles as $profile => $options) {
    foreach ($cases as $name => $case) {
        $tests["maps upstream markdown writer definition-list block body final harvest {$profile} {$name}"] =
            static function (TestRunner $t) use ($case, $document, $expectedDefinition, $firstDefinitionChildren, $options, $profile): void {
                $body = $profile === 'markdown'
                    ? $case['body']
                    : ($case['htmlBody'] ?? $case['body']);
                $markdown = (new MarkdownWriter($options))->write($document($case['children']()));

                $t->same($expectedDefinition($body), $markdown);
                $lines = explode("\n", $markdown);
                $t->same('Term', $lines[0] ?? '');
                $t->same(':', $lines[1] ?? '');
                $t->same('', $lines[2] ?? 'missing detached definition spacer');
                $t->true(!str_starts_with($markdown, "Term\n:   "), 'Definition body must not remain on the marker line');

                if (isset($case['roundTripTypes'])) {
                    $roundTrip = (new MarkdownReader($options))->read($markdown);
                    $children = $firstDefinitionChildren($roundTrip);
                    $t->same($case['roundTripTypes'], array_map(static fn (AstNode $node): string => $node->type, $children));
                }
            };
    }
}

return $tests;
