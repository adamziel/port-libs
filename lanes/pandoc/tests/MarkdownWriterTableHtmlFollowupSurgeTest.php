<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$cell = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$head = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_head', $attrs, $rows);
$body = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_body', $attrs, $rows);
$foot = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_foot', $attrs, $rows);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter())->write($document([$node]));

$table = static function (array $sections, array $attrs = []): AstNode {
    $htmlAttributes = ['data-pandoc-writer' => 'html'];
    if (isset($attrs['htmlAttributes']) && is_array($attrs['htmlAttributes'])) {
        $htmlAttributes = array_merge($htmlAttributes, $attrs['htmlAttributes']);
    }
    $attrs['htmlAttributes'] = $htmlAttributes;
    $attrs += [
        'alignments' => ['left', 'right', 'center', 'default'],
        'widths' => [0.25, 0.25, 0.25, 0.25],
    ];

    return new AstNode('table', $attrs, $sections);
};

$scopeCases = [
    'head colspan left scope colgroup' => [
        'table' => static fn () => $table([
            $head([$row([$cell('Document', ['colspan' => 2]), $cell('State')])]),
        ]),
        'contains' => ['<th scope="colgroup" colspan="2" style="text-align:left">Document</th>'],
    ],
    'head single cell keeps column scope' => [
        'table' => static fn () => $table([
            $head([$row([$cell('Document'), $cell('Items')])]),
        ]),
        'contains' => ['<th scope="col" style="text-align:left">Document</th>', '<th scope="col" style="text-align:right">Items</th>'],
    ],
    'head colspan center alignment keeps colgroup' => [
        'table' => static fn () => $table([
            $head([$row([$cell('Scope', ['align' => 'center', 'colspan' => 3])])]),
        ]),
        'contains' => ['<th scope="colgroup" colspan="3" style="text-align:center">Scope</th>'],
    ],
    'head colspan default alignment omits style' => [
        'table' => static fn () => $table([
            $head([$row([$cell('Unstyled', ['colspan' => 2])])]),
        ], ['alignments' => ['default', 'default']]),
        'contains' => ['<th scope="colgroup" colspan="2">Unstyled</th>'],
    ],
    'body row head rowspan computes rowgroup' => [
        'table' => static fn () => $table([
            $body([
                $row([$cell('Posts', ['rowspan' => 2]), $cell('42')]),
                $row([$cell('7')]),
            ], ['rowHeadColumns' => 1]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="rowgroup" rowspan="2" style="text-align:left">Posts</th>'],
    ],
    'body row head without span computes row' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Media'), $cell('7')])], ['rowHeadColumns' => 1]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Media</th>'],
    ],
    'body second row head column computes row' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Batch'), $cell('Images'), $cell('3')])], ['rowHeadColumns' => 2]),
        ], ['alignments' => ['left', 'left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Images</th>'],
    ],
    'body row head source scope is preserved' => [
        'table' => static fn () => $table([
            $body([
                $row([$cell('Source', ['rowspan' => 2, 'attributes' => ['scope' => 'row']]), $cell('42')]),
                $row([$cell('7')]),
            ], ['rowHeadColumns' => 1]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" rowspan="2" style="text-align:left">Source</th>'],
    ],
    'explicit body header rowspan computes rowgroup' => [
        'table' => static fn () => $table([
            $body([
                $row([$cell('Group', ['header' => true, 'rowspan' => 2]), $cell('Ready')]),
                $row([$cell('Review')]),
            ]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="rowgroup" rowspan="2" style="text-align:left">Group</th>'],
    ],
    'explicit body header without span computes row' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Group', ['header' => true]), $cell('Ready')])]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Group</th>'],
    ],
    'explicit body header colspan keeps row scope' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Merged label', ['header' => true, 'colspan' => 2])])]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" colspan="2" style="text-align:left">Merged label</th>'],
    ],
    'explicit body header source rowgroup is preserved' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Legacy group', ['header' => true, 'attributes' => ['scope' => 'rowgroup']]), $cell('Ready')])]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="rowgroup" style="text-align:left">Legacy group</th>'],
    ],
    'body headRows colspan computes colgroup' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Posts'), $cell('42')])], [
                'headRows' => [$row([$cell('Queue scope', ['colspan' => 2])])],
            ]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="colgroup" colspan="2" style="text-align:left">Queue scope</th>'],
    ],
    'body headRows single header computes col' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Posts'), $cell('42')])], [
                'headRows' => [$row([$cell('Queue'), $cell('Count')])],
            ]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="col" style="text-align:right">Count</th>'],
    ],
    'tfoot header row computes column scope' => [
        'table' => static fn () => $table([
            $foot([$row([$cell('Total', ['header' => true]), $cell('49')])]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Total</th>'],
    ],
    'thead source colgroup is preserved' => [
        'table' => static fn () => $table([
            $head([$row([$cell('Source group', ['colspan' => 2, 'attributes' => ['scope' => 'colgroup']])])]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="colgroup" colspan="2" style="text-align:left">Source group</th>'],
    ],
    'direct header row colspan computes colgroup' => [
        'table' => static fn () => $table([
            $row([$cell('Direct heading', ['colspan' => 2])], ['header' => true]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="colgroup" colspan="2" style="text-align:left">Direct heading</th>'],
    ],
    'direct header row single cell computes col' => [
        'table' => static fn () => $table([
            $row([$cell('Direct'), $cell('Value')], ['header' => true]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="col" style="text-align:left">Direct</th>', '<th scope="col" style="text-align:right">Value</th>'],
    ],
    'direct explicit header cell computes row' => [
        'table' => static fn () => $table([
            $row([$cell('Direct row head', ['header' => true]), $cell('Value')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Direct row head</th>'],
    ],
    'direct explicit header rowspan computes rowgroup' => [
        'table' => static fn () => $table([
            $row([$cell('Direct group', ['header' => true, 'rowspan' => 2]), $cell('One')]),
            $row([$cell('Two')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="rowgroup" rowspan="2" style="text-align:left">Direct group</th>'],
    ],
];

$directRowCases = [
    'direct rows render an implicit tbody' => [
        'table' => static fn () => $table([$row([$cell('Posts'), $cell('42')])], ['alignments' => ['left', 'right']]),
        'contains' => ['<tbody>', '<td style="text-align:left">Posts</td><td style="text-align:right">42</td>'],
    ],
    'direct rows append after explicit bodies before foot' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Body'), $cell('1')])]),
            $row([$cell('Direct'), $cell('2')]),
            $foot([$row([$cell('Foot'), $cell('3')])]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['Body</td><td style="text-align:right">1</td></tr>', 'Direct</td><td style="text-align:right">2</td></tr>', '<tfoot>'],
    ],
    'direct row attributes stay on tr' => [
        'table' => static fn () => $table([
            $row([$cell('Row'), $cell('Attrs')], ['id' => 'direct-row', 'classes' => ['review'], 'attributes' => ['data-row' => 'direct']]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<tr id="direct-row" class="review" data-row="direct">'],
    ],
    'direct row colspan is preserved' => [
        'table' => static fn () => $table([$row([$cell('Merged direct', ['colspan' => 2])])], ['alignments' => ['left', 'right']]),
        'contains' => ['<td colspan="2" style="text-align:left">Merged direct</td>'],
    ],
    'direct row rowspan is preserved' => [
        'table' => static fn () => $table([
            $row([$cell('Group', ['rowspan' => 2]), $cell('One')]),
            $row([$cell('Two')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<td rowspan="2" style="text-align:left">Group</td>', '<td style="text-align:right">Two</td>'],
    ],
    'direct row inline span renders html' => [
        'table' => static fn () => $table([
            $row([$cell([new AstNode('span', ['classes' => ['review']], [$text('Label')])]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<span class="review">Label</span>'],
    ],
    'direct row paragraph block renders html paragraph' => [
        'table' => static fn () => $table([
            $row([$cell([$paragraph([$text('Paragraph direct')])]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<p>Paragraph direct</p>'],
    ],
    'direct row link renders anchor' => [
        'table' => static fn () => $table([
            $row([$cell([new AstNode('link', ['url' => '/review', 'title' => 'Review'], [$text('Review')])]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<a href="/review" title="Review">Review</a>'],
    ],
    'direct row image renders img' => [
        'table' => static fn () => $table([
            $row([$cell([new AstNode('image', ['url' => 'media/direct.png', 'alt' => 'Direct image'])]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<img src="media/direct.png" alt="Direct image" />'],
    ],
    'direct row caption still renders caption element' => [
        'table' => static fn () => $table([$row([$cell('Posts'), $cell('42')])], ['alignments' => ['left', 'right'], 'caption' => 'Direct row caption']),
        'contains' => ['<caption>Direct row caption</caption>'],
    ],
    'direct row table attributes stay on table' => [
        'table' => static fn () => $table([$row([$cell('Posts'), $cell('42')])], ['id' => 'direct-table', 'classes' => ['wide'], 'attributes' => ['data-source' => 'direct']]),
        'contains' => ['<table id="direct-table" class="wide" data-pandoc-writer="html" data-source="direct">'],
    ],
    'direct row softbreak renders break' => [
        'table' => static fn () => $table([
            $row([$cell([$text('Line one'), new AstNode('softbreak'), $text('Line two')]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['Line one<br />Line two'],
    ],
    'direct row list block renders list html' => [
        'table' => static fn () => $table([
            $row([$cell([new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<ul><li><p>One</p></li></ul>'],
    ],
    'direct row unsafe event attribute is omitted' => [
        'table' => static fn () => $table([
            $row([$cell('Safe', ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['data-safe="yes"'],
        'forbid' => ['onclick='],
    ],
    'direct row nested raw html block is preserved' => [
        'table' => static fn () => $table([
            $row([$cell([new AstNode('raw_html', ['html' => '<aside data-note="direct">Raw</aside>'])]), $cell('Ready')]),
        ], ['alignments' => ['left', 'right']]),
        'contains' => ['<aside data-note="direct">Raw</aside>'],
    ],
];

$shortCaptionCases = [
    'bullet list short caption blocks flatten' => [
        'shortBlocks' => [new AstNode('bullet_list', [], [
            new AstNode('list_item', [], [$paragraph([$text('One')])]),
            new AstNode('list_item', [], [$paragraph([$text('Two')])]),
        ])],
        'expected' => 'data-pandoc-short-caption="One Two"',
    ],
    'ordered list short caption blocks flatten' => [
        'shortBlocks' => [new AstNode('ordered_list', ['start' => 3], [
            new AstNode('list_item', [], [$paragraph([$text('Three')])]),
            new AstNode('list_item', [], [$paragraph([$text('Four')])]),
        ])],
        'expected' => 'data-pandoc-short-caption="Three Four"',
    ],
    'blockquote short caption blocks flatten' => [
        'shortBlocks' => [new AstNode('blockquote', [], [$paragraph([$text('Quoted label')])])],
        'expected' => 'data-pandoc-short-caption="Quoted label"',
    ],
    'code block short caption text is preserved' => [
        'shortBlocks' => [new AstNode('code_block', ['text' => 'code caption'])],
        'expected' => 'data-pandoc-short-caption="code caption"',
    ],
    'div short caption blocks flatten' => [
        'shortBlocks' => [new AstNode('div', ['classes' => ['short']], [$paragraph([$text('Div caption')])])],
        'expected' => 'data-pandoc-short-caption="Div caption"',
    ],
    'heading short caption blocks flatten' => [
        'shortBlocks' => [new AstNode('heading', ['level' => 3], [$text('Heading'), $space(), new AstNode('strong', [], [$text('label')])])],
        'expected' => 'data-pandoc-short-caption="Heading label"',
    ],
    'plain span short caption blocks flatten' => [
        'shortBlocks' => [$plain([$text('Plain '), new AstNode('span', ['classes' => ['review']], [$text('span')])])],
        'expected' => 'data-pandoc-short-caption="Plain span"',
    ],
    'paragraph hardbreak short caption blocks flatten' => [
        'shortBlocks' => [$paragraph([$text('Alpha'), new AstNode('linebreak'), $text('Beta')])],
        'expected' => 'data-pandoc-short-caption="Alpha Beta"',
    ],
    'raw markdown short caption block text is preserved' => [
        'shortBlocks' => [new AstNode('raw_markdown', ['text' => 'raw short'])],
        'expected' => 'data-pandoc-short-caption="raw short"',
    ],
    'raw html short caption block is escaped in attribute' => [
        'shortBlocks' => [new AstNode('raw_html', ['html' => '<em>Short</em>'])],
        'expected' => 'data-pandoc-short-caption="&lt;em&gt;Short&lt;/em&gt;"',
    ],
    'table cell short caption block flattens inlines' => [
        'shortBlocks' => [new AstNode('table_cell', [], [$text('Cell'), $space(), $text('Short')])],
        'expected' => 'data-pandoc-short-caption="Cell Short"',
    ],
    'inline code inside short caption blocks flattens to text' => [
        'shortBlocks' => [$paragraph([$text('Use'), $space(), new AstNode('code', ['text' => 'code'])])],
        'expected' => 'data-pandoc-short-caption="Use code"',
    ],
    'nested blockquote list short caption flattens' => [
        'shortBlocks' => [new AstNode('blockquote', [], [new AstNode('bullet_list', [], [
            new AstNode('list_item', [], [$paragraph([$text('Nested')])]),
        ])])],
        'expected' => 'data-pandoc-short-caption="Nested"',
    ],
    'nested div blockquote short caption flattens' => [
        'shortBlocks' => [new AstNode('div', [], [new AstNode('blockquote', [], [$paragraph([$text('Deep short')])])])],
        'expected' => 'data-pandoc-short-caption="Deep short"',
    ],
    'image-only short caption block falls back to surrounding text' => [
        'shortBlocks' => [$paragraph([$text('Image'), $space(), new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt label'])])],
        'expected' => 'data-pandoc-short-caption="Image"',
    ],
];

$tests = [];
$mappedCases = $scopeCases + $directRowCases;
foreach ($shortCaptionCases as $label => $case) {
    $mappedCases['short caption ' . $label] = [
        'table' => static fn () => $table([
            $body([$row([$cell('Posts'), $cell('42')])]),
        ], [
            'caption' => 'Long caption',
            'shortCaptionBlocks' => $case['shortBlocks'],
        ]),
        'contains' => [$case['expected'], '<caption '],
    ];
}

foreach ($mappedCases as $label => $case) {
    $tests["maps upstream markdown writer html followup {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
            foreach ($case['forbid'] ?? [] as $forbidden) {
                $t->true(!str_contains($markdown, $forbidden), "HTML table output should not contain {$forbidden}");
            }
        };
}

$tests['records upstream markdown writer html followup mapped-case count'] =
    static function (TestRunner $t) use ($mappedCases): void {
        $t->same(50, count($mappedCases));
    };

return $tests;
