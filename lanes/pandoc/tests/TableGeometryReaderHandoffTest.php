<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'attaches table geometry review packets to structured html reader tables' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="review-grid" data-source="html-reader">
<caption>Importer <em>review</em></caption>
<thead>
<tr><th colspan="2">Scope</th><th>Status</th></tr>
</thead>
<tbody>
<tr><th rowspan="2">Posts</th><td>42</td><td>Ready</td></tr>
<tr><td>7</td><td>Needs media</td></tr>
</tbody>
<tfoot>
<tr><td>Total</td><td>49</td><td>Review</td></tr>
</tfoot>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $geometry = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(3, TableGeometry::columnCount($table));
        $t->same(true, is_array($geometry));
        $geometry = is_array($geometry) ? $geometry : [];
        $t->same('Importer review', $geometry['caption'] ?? null);
        $t->same(3, $geometry['columnCount'] ?? null);
        $t->same(['head', 'body', 'foot'], array_map(static fn (array $section): string => $section['section'], $geometry['sections'] ?? []));
        $t->same([1, 2, 1], array_map(static fn (array $section): int => $section['rowCount'], $geometry['sections'] ?? []));
        $t->same(10, $geometry['summary']['cellCount'] ?? null);
        $t->same(3, $geometry['summary']['headerCellCount'] ?? null);
        $t->same(2, $geometry['summary']['coveredSlotCount'] ?? null);
        $t->same('Scope', $geometry['coverage'][0]['text'] ?? null);
        $t->same([0, 1], $geometry['coverage'][0]['columns'] ?? null);
        $t->same('Posts', $geometry['coverage'][2]['text'] ?? null);
        $t->same(true, $geometry['coverage'][2]['headerCell'] ?? null);
        $t->same('7', $geometry['coverage'][5]['text'] ?? null);
        $t->same(1, $geometry['coverage'][5]['column'] ?? null);
        $t->same('covered', $geometry['sections'][1]['rows'][1]['slots'][0]['kind'] ?? null);
        $t->same('rowspan', $geometry['sections'][1]['rows'][1]['slots'][0]['covering'] ?? null);
        $t->same('review-grid-head-r1c1', $geometry['accessibility']['head:0:0:0']['id'] ?? null);
        $t->same('colgroup', $geometry['accessibility']['head:0:0:0']['scope'] ?? null);
        $t->same(['review-grid-head-r1c1', 'review-grid-body-r1c1'], $geometry['accessibility']['body:1:0:0']['headers'] ?? null);
        $t->same(['review-grid-head-r1c3', 'review-grid-body-r1c1'], $geometry['accessibility']['body:1:1:1']['headers'] ?? null);
        $t->contains('<table id="review-grid" data-source="html-reader">', $blocks);
        $t->contains('<th colspan="2">Scope</th><th>Status</th>', $blocks);
        $t->contains('<tr><td>7</td><td>Needs media</td></tr>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Importer <em>review</em></figcaption>', $blocks);
        json_encode($geometry, JSON_THROW_ON_ERROR);
    },
    'attaches table geometry review packets to markdown and docbook table reader paths' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $firstTable = static function (array $nodes): ?AstNode {
            foreach ($nodes as $node) {
                if ($node instanceof AstNode && $node->type === 'table') {
                    return $node;
                }
            }

            return null;
        };

        $pipeTable = $firstTable($reader->read(implode("\n", [
            '| Item | Count | State |',
            '|:-----|------:|:----:|',
            '| Posts | 42 | Ready |',
            '| Media | 7 | Review |',
            '',
            ': Import metrics',
        ]))->children);
        $simpleTable = $firstTable($reader->read(implode("\n", [
            'Simple source totals:',
            '',
            '    Field Count    Status',
            '  ------- ----- ---------',
            '    Posts 42    Ready',
            '    Media 7     Needs alt text',
            '',
            '  : Legacy simple-table summary.',
        ]))->children);
        $gridTable = $firstTable($reader->read(implode("\n", [
            '+------------------+-----------+------------+',
            '| Source           | Count     | Status     |',
            '+=================:+:==========+:==========:+',
            '| Posts            | 42        | ready      |',
            '+------------------+-----------+------------+',
            '| Media files      | 108       | needs alt  |',
            '|                  |           | text       |',
            '+------------------+-----------+------------+',
        ]))->children);
        $latexTable = $firstTable($reader->read(<<<'LATEX'
\begin{table}
\caption[Batch 42]{Long source table caption for reviewer handoff.}
\begin{tabular}{lr}
Posts & 42 \\
Media & 7 \\
\end{tabular}
\end{table}
LATEX)->children);
        $docbookTable = $firstTable($reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-docbook-table.xml'))->children);

        foreach ([
            'pipe' => $pipeTable,
            'simple' => $simpleTable,
            'grid' => $gridTable,
            'latex' => $latexTable,
            'docbook' => $docbookTable,
        ] as $label => $table) {
            $t->true($table !== null, $label . ' table should parse through the native table path');
            $packet = $table?->attr('tableGeometry');
            $t->same(true, is_array($packet), $label . ' table should carry a tableGeometry packet');
            json_encode($packet, JSON_THROW_ON_ERROR);
        }

        $pipeGeometry = $pipeTable?->attr('tableGeometry');
        $simpleGeometry = $simpleTable?->attr('tableGeometry');
        $gridGeometry = $gridTable?->attr('tableGeometry');
        $latexGeometry = $latexTable?->attr('tableGeometry');
        $docbookGeometry = $docbookTable?->attr('tableGeometry');

        $t->same('Import metrics', $pipeGeometry['caption'] ?? null);
        $t->same(3, $pipeGeometry['columnCount'] ?? null);
        $t->same(['head', 'body'], array_map(static fn (array $section): string => $section['section'], $pipeGeometry['sections'] ?? []));
        $t->same('Posts', $pipeGeometry['coverage'][3]['text'] ?? null);
        $t->same(['left'], $pipeGeometry['coverage'][3]['columnAlignments'] ?? null);
        $t->same('Review', $pipeGeometry['coverage'][8]['text'] ?? null);
        $t->same(0, $pipeGeometry['summary']['diagnosticCount'] ?? null);

        $t->same('Legacy simple-table summary.', $simpleGeometry['caption'] ?? null);
        $t->same(['right', 'default', 'right'], array_map(static fn (array $column): string => $column['alignment'], $simpleGeometry['columns'] ?? []));
        $t->same(9, $simpleGeometry['summary']['cellCount'] ?? null);
        $t->same('Media', $simpleGeometry['coverage'][6]['text'] ?? null);

        $t->same(3, $gridGeometry['columnCount'] ?? null);
        $t->same([0.2638888888888889, 0.16666666666666666, 0.18055555555555555], array_map(static fn (array $column): ?float => $column['width'], $gridGeometry['columns'] ?? []));
        $t->same('Media files', $gridGeometry['coverage'][6]['text'] ?? null);
        $t->same(9, $gridGeometry['summary']['cellCount'] ?? null);

        $t->same('Long source table caption for reviewer handoff.', $latexGeometry['caption'] ?? null);
        $t->same(['left', 'right'], array_map(static fn (array $column): string => $column['alignment'], $latexGeometry['columns'] ?? []));
        $t->same('Media', $latexGeometry['coverage'][2]['text'] ?? null);

        $t->same(4, $docbookGeometry['columnCount'] ?? null);
        $t->same([0.25, 0.25, 0.25, 0.25], array_map(static fn (array $column): ?float => $column['width'], $docbookGeometry['columns'] ?? []));
        $t->same(['head', 'body', 'foot'], array_map(static fn (array $section): string => $section['section'], $docbookGeometry['sections'] ?? []));
        $t->same('Migration Batch 42', $docbookGeometry['coverage'][0]['text'] ?? null);
        $t->same([0, 1, 2, 3], $docbookGeometry['coverage'][0]['columns'] ?? null);
        $t->same('Follow-up attachments', $docbookGeometry['coverage'][9]['text'] ?? null);
        $t->same(13, $docbookGeometry['summary']['coveredSlotCount'] ?? null);
        $t->same([], $docbookGeometry['summary']['diagnosticCodes'] ?? null);
    },
    'rolls nested html table reader geometry into parent coverage packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<!doctype html>
<html>
<body>
<table>
 <tr>
  <td>
    <table>
      <tr>
        <td>a1</td>
        <td>
          <table><tr><td>1</td><td>2</td></tr></table>
        </td>
      </tr>
    </table>
  </td>
  <td>b</td>
 </tr>
 <tr>
   <td>c</td><td>d</td>
 </tr>
</table>
</body>
</html>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(2, $packet['summary']['nestedTableCount'] ?? null);
        $t->same(1, $packet['summary']['nestedTableCellCount'] ?? null);
        $t->same('a112', $packet['coverage'][0]['text'] ?? null);
        $t->same(2, count($packet['coverage'][0]['nestedTables'] ?? []));
        $t->same('', $packet['coverage'][0]['nestedTables'][0]['caption'] ?? null);
        $t->same(2, $packet['coverage'][0]['nestedTables'][0]['columnCount'] ?? null);
        $t->same(2, $packet['coverage'][0]['nestedTables'][0]['cellCount'] ?? null);
        $t->same(1, $packet['coverage'][0]['nestedTables'][0]['nestedTableCount'] ?? null);
        $t->same(2, $packet['coverage'][0]['nestedTables'][1]['cellCount'] ?? null);
        $t->same(0, $packet['coverage'][0]['nestedTables'][1]['nestedTableCount'] ?? null);
        $t->contains('<td><table><colgroup><col style="width:50%"/><col style="width:50%"/></colgroup><tbody><tr><td>a1</td><td><table><colgroup><col style="width:50%"/><col style="width:50%"/></colgroup><tbody><tr><td>1</td><td>2</td></tr></tbody></table></td></tr></tbody></table></td><td>b</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
