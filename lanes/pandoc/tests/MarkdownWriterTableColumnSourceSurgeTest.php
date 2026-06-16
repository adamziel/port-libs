<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$cell = static function (string $value) use ($text): AstNode {
    return new AstNode('table_cell', ['text' => $value], [$text($value)]);
};
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
$head = static fn (array $rows): AstNode => new AstNode('table_head', [], $rows);
$body = static fn (array $rows): AstNode => new AstNode('table_body', [], $rows);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $table): string => (new MarkdownWriter())->write($document([$table]));

$source = static function (
    int $column,
    array $colAttributes = [],
    array $colgroupAttributes = [],
    ?int $colgroupIndex = null
): array {
    $source = [
        'kind' => $colAttributes === [] ? 'colgroup' : 'col',
        'column' => $column,
        'sourceSpan' => 1,
    ];
    if ($colAttributes !== []) {
        $source['colAttributes'] = $colAttributes;
    }
    if ($colgroupAttributes !== []) {
        $source['colgroupAttributes'] = $colgroupAttributes;
    }
    if ($colgroupIndex !== null) {
        $source['colgroupIndex'] = $colgroupIndex;
    }

    return $source;
};

$table = static function (array $columnSources, array $attrs = []) use ($head, $body, $row, $cell): AstNode {
    $attrs = array_replace([
        'alignments' => ['left', 'right', 'center'],
        'widths' => [0.20, 0.30, 0.50],
        'columnSources' => $columnSources,
    ], $attrs);

    return new AstNode('table', $attrs, [
        $head([$row([$cell('Metric'), $cell('Value'), $cell('State')])]),
        $body([$row([$cell('Posts'), $cell('42'), $cell('Ready')])]),
    ]);
};

$tests = [];

$cases = [
    'colgroup id attribute' => [
        'sources' => [$source(0, [], ['id' => 'source-columns'])],
        'contains' => ['<colgroup id="source-columns">'],
    ],
    'colgroup class attribute' => [
        'sources' => [$source(0, [], ['classes' => ['legacy-columns']])],
        'contains' => ['<colgroup class="legacy-columns">'],
    ],
    'colgroup data attribute' => [
        'sources' => [$source(0, [], ['attributes' => ['data-origin' => 'legacy-doc']])],
        'contains' => ['<colgroup data-origin="legacy-doc">'],
    ],
    'colgroup html data attribute' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['data-source' => 'html-reader']])],
        'contains' => ['<colgroup data-source="html-reader">'],
    ],
    'colgroup language direction title role attributes' => [
        'sources' => [$source(0, [], ['attributes' => ['lang' => 'en', 'dir' => 'ltr', 'title' => 'Column group', 'role' => 'presentation']])],
        'contains' => ['<colgroup lang="en" dir="ltr" title="Column group" role="presentation">'],
    ],
    'colgroup aria attribute' => [
        'sources' => [$source(0, [], ['attributes' => ['aria-label' => 'Source columns']])],
        'contains' => ['<colgroup aria-label="Source columns">'],
    ],
    'colgroup html class merges parsed class' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']])],
        'contains' => ['<colgroup class="source review">'],
    ],
    'colgroup html id takes precedence' => [
        'sources' => [$source(0, [], ['id' => 'parsed-columns', 'htmlAttributes' => ['id' => 'html-columns']])],
        'contains' => ['<colgroup id="html-columns">'],
        'forbid' => ['parsed-columns'],
    ],
    'colgroup uppercase html attribute normalizes' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['DATA-SOURCE' => 'Reader']])],
        'contains' => ['<colgroup data-source="Reader">'],
    ],
    'colgroup decimal alignment is preserved' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['align' => 'char', 'data-origin' => 'legacy-doc']])],
        'contains' => ['<colgroup align="char" data-origin="legacy-doc">'],
    ],
    'colgroup non decimal alignment is omitted' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['align' => 'left', 'data-origin' => 'legacy-doc']])],
        'contains' => ['<colgroup data-origin="legacy-doc">'],
        'forbid' => ['<colgroup align="left"'],
    ],
    'colgroup source span is omitted' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['span' => '0', 'data-origin' => 'legacy-doc']])],
        'contains' => ['<colgroup data-origin="legacy-doc">'],
        'forbid' => ['span="0"'],
    ],
    'colgroup source width is omitted' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['width' => '90%', 'data-origin' => 'legacy-doc']])],
        'contains' => ['<colgroup data-origin="legacy-doc">'],
        'forbid' => ['width="90%"'],
    ],
    'colgroup vertical alignment is omitted' => [
        'sources' => [$source(0, [], ['htmlAttributes' => ['valign' => 'top', 'data-origin' => 'legacy-doc']])],
        'contains' => ['<colgroup data-origin="legacy-doc">'],
        'forbid' => ['valign="top"'],
    ],
    'colgroup event handler is omitted' => [
        'sources' => [$source(0, [], ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']])],
        'contains' => ['<colgroup data-safe="yes">'],
        'forbid' => ['onclick='],
    ],
    'colgroup without widths still emits source metadata' => [
        'sources' => [$source(0, ['attributes' => ['data-column' => 'metric']])],
        'attrs' => ['widths' => []],
        'contains' => ['<col data-column="metric" data-pandoc-align="left" />'],
        'forbid' => ['style="width:'],
    ],
    'split colgroup indexes render separate groups' => [
        'sources' => [
            $source(0, ['attributes' => ['data-col' => 'a']], ['attributes' => ['data-group' => 'a']], 0),
            $source(1, ['attributes' => ['data-col' => 'b']], ['attributes' => ['data-group' => 'b']], 1),
        ],
        'contains' => ['<colgroup data-group="a">', '<colgroup data-group="b">'],
    ],
    'same colgroup index keeps adjacent columns grouped' => [
        'sources' => [
            $source(0, ['attributes' => ['data-col' => 'a1']], ['attributes' => ['data-group' => 'a']], 0),
            $source(1, ['attributes' => ['data-col' => 'a2']], ['attributes' => ['data-group' => 'a']], 0),
        ],
        'contains' => ["<colgroup data-group=\"a\">\n    <col data-col=\"a1\"", "\n    <col data-col=\"a2\""],
    ],
    'matching colgroup attributes keep adjacent columns grouped' => [
        'sources' => [
            $source(0, ['attributes' => ['data-col' => 'first']], ['attributes' => ['data-group' => 'shared']]),
            $source(1, ['attributes' => ['data-col' => 'second']], ['attributes' => ['data-group' => 'shared']]),
        ],
        'contains' => ["<colgroup data-group=\"shared\">\n    <col data-col=\"first\"", "\n    <col data-col=\"second\""],
    ],
    'missing middle source remains in default group' => [
        'sources' => [
            $source(0, ['attributes' => ['data-col' => 'first']]),
            $source(2, ['attributes' => ['data-col' => 'third']]),
        ],
        'contains' => ['<col data-col="first"', '<col style="width:30%" data-pandoc-align="right" />', '<col data-col="third"'],
    ],
    'col id attribute' => [
        'sources' => [$source(0, ['id' => 'metric-col'])],
        'contains' => ['<col id="metric-col" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col html id takes precedence' => [
        'sources' => [$source(0, ['id' => 'parsed-col', 'htmlAttributes' => ['id' => 'html-col']])],
        'contains' => ['<col id="html-col" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['parsed-col'],
    ],
    'col class attribute' => [
        'sources' => [$source(0, ['classes' => ['metric-column']])],
        'contains' => ['<col class="metric-column" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col html class merges parsed class' => [
        'sources' => [$source(0, ['htmlAttributes' => ['class' => 'source'], 'classes' => ['metric-column']])],
        'contains' => ['<col class="source metric-column" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col data attribute' => [
        'sources' => [$source(0, ['attributes' => ['data-origin' => 'reader']])],
        'contains' => ['<col data-origin="reader" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col html data attribute' => [
        'sources' => [$source(0, ['htmlAttributes' => ['data-html' => 'kept']])],
        'contains' => ['<col data-html="kept" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col language and direction attributes' => [
        'sources' => [$source(0, ['attributes' => ['lang' => 'es', 'dir' => 'ltr']])],
        'contains' => ['<col lang="es" dir="ltr" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col title and role attributes' => [
        'sources' => [$source(0, ['attributes' => ['title' => 'Metric column', 'role' => 'presentation']])],
        'contains' => ['<col title="Metric column" role="presentation" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col aria attribute' => [
        'sources' => [$source(0, ['attributes' => ['aria-label' => 'Metric']])],
        'contains' => ['<col aria-label="Metric" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col xml language attribute' => [
        'sources' => [$source(0, ['attributes' => ['xml:lang' => 'fr']])],
        'contains' => ['<col xml:lang="fr" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col vertical alignment is preserved' => [
        'sources' => [$source(0, ['htmlAttributes' => ['valign' => 'top']])],
        'contains' => ['<col valign="top" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col decimal alignment is preserved' => [
        'sources' => [$source(0, ['htmlAttributes' => ['align' => 'char']])],
        'contains' => ['<col align="char" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col non decimal alignment is omitted' => [
        'sources' => [$source(0, ['htmlAttributes' => ['align' => 'right', 'data-origin' => 'reader']])],
        'contains' => ['<col data-origin="reader" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['<col align="right"'],
    ],
    'col source span is omitted' => [
        'sources' => [$source(0, ['htmlAttributes' => ['span' => '3', 'data-origin' => 'reader']])],
        'contains' => ['<col data-origin="reader" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['span="3"'],
    ],
    'col source width is omitted in favor of normalized width' => [
        'sources' => [$source(0, ['htmlAttributes' => ['width' => '90%', 'data-origin' => 'reader']])],
        'contains' => ['<col data-origin="reader" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['width="90%"'],
    ],
    'col safe style merges normalized width' => [
        'sources' => [$source(0, ['htmlAttributes' => ['style' => 'background-color:#fff4cc']])],
        'contains' => ['<col style="background-color:#fff4cc; width:20%" data-pandoc-align="left" />'],
    ],
    'col unsafe style drops source style but keeps normalized width' => [
        'sources' => [$source(0, ['htmlAttributes' => ['style' => 'background:url(javascript:bad)', 'data-safe' => 'yes']])],
        'contains' => ['<col data-safe="yes" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['background:url'],
    ],
    'col event handler is omitted' => [
        'sources' => [$source(0, ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']])],
        'contains' => ['<col data-safe="yes" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['onclick='],
    ],
    'col boolean data attribute is normalized' => [
        'sources' => [$source(0, ['attributes' => ['data-enabled' => true]])],
        'contains' => ['<col data-enabled="1" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col numeric data attribute is normalized' => [
        'sources' => [$source(0, ['attributes' => ['data-count' => 42]])],
        'contains' => ['<col data-count="42" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col quoted data attribute is escaped' => [
        'sources' => [$source(0, ['attributes' => ['data-label' => 'a "quoted" value']])],
        'contains' => ['<col data-label="a &quot;quoted&quot; value" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col duplicate class is deduplicated' => [
        'sources' => [$source(0, ['htmlAttributes' => ['class' => 'metric'], 'classes' => ['metric', 'review']])],
        'contains' => ['<col class="metric review" style="width:20%" data-pandoc-align="left" />'],
    ],
    'second column receives right alignment metadata' => [
        'sources' => [$source(1, ['attributes' => ['data-column' => 'value']])],
        'contains' => ['<col data-column="value" style="width:30%" data-pandoc-align="right" />'],
    ],
    'third column receives center alignment metadata' => [
        'sources' => [$source(2, ['attributes' => ['data-column' => 'state']])],
        'contains' => ['<col data-column="state" style="width:50%" data-pandoc-align="center" />'],
    ],
    'default alignment omits data alignment metadata' => [
        'sources' => [$source(0, ['attributes' => ['data-column' => 'metric']])],
        'attrs' => ['alignments' => ['default', 'default', 'default']],
        'contains' => ['<col data-column="metric" style="width:20%" />'],
        'forbid' => ['data-pandoc-align'],
    ],
    'col class without widths emits class only' => [
        'sources' => [$source(0, ['classes' => ['metric-column']])],
        'attrs' => ['widths' => []],
        'contains' => ['<col class="metric-column" data-pandoc-align="left" />'],
        'forbid' => ['style="width:'],
    ],
    'colgroup and col attributes are both preserved' => [
        'sources' => [$source(0, ['attributes' => ['data-column' => 'metric']], ['attributes' => ['data-group' => 'source']], 0)],
        'contains' => ['<colgroup data-group="source">', '<col data-column="metric" style="width:20%" data-pandoc-align="left" />'],
    ],
    'col html and parsed attributes merge without overriding html data' => [
        'sources' => [$source(0, ['htmlAttributes' => ['data-source' => 'html'], 'attributes' => ['data-source' => 'parsed', 'data-kind' => 'metric']])],
        'contains' => ['<col data-source="html" data-kind="metric" style="width:20%" data-pandoc-align="left" />'],
        'forbid' => ['data-source="parsed"'],
    ],
    'existing column alignment metadata is not overwritten' => [
        'sources' => [$source(1, ['htmlAttributes' => ['data-pandoc-align' => 'source', 'data-column' => 'value']])],
        'contains' => ['<col data-pandoc-align="source" data-column="value" style="width:30%" />'],
        'forbid' => ['data-pandoc-align="right"'],
    ],
    'col style with trailing semicolon merges normalized width once' => [
        'sources' => [$source(0, ['htmlAttributes' => ['style' => 'color:red;']])],
        'contains' => ['<col style="color:red; width:20%" data-pandoc-align="left" />'],
    ],
];

foreach ($cases as $label => $case) {
    $tests["maps upstream markdown writer table column source {$label}"] =
        static function (TestRunner $t) use ($case, $table, $writeDocument): void {
            $markdown = $writeDocument($table($case['sources'], $case['attrs'] ?? []));

            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }

            foreach ($case['forbid'] ?? [] as $unexpected) {
                $t->true(!str_contains($markdown, $unexpected), "Column source output should not contain {$unexpected}");
            }
        };
}

return $tests;
