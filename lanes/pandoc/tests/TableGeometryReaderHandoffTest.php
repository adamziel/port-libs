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
    'carries html table section row and cell attributes into geometry review packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="source-grid" class="wp-import needs-review" data-origin="html-reader" aria-label="Review source table">
<caption>Attributed source grid</caption>
<thead id="source-head" data-section="thead">
<tr data-row="head-1"><th id="source-scope" class="header-cell" data-origin="docx">Scope</th><th data-origin="manual">State</th></tr>
</thead>
<tbody id="source-body" data-section="tbody">
<tr data-row="body-1"><td title="Imported posts" data-origin="docx">Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('source-grid', $packet['sourceAttributes']['id'] ?? null);
        $t->same(['wp-import', 'needs-review'], $packet['sourceAttributes']['classes'] ?? null);
        $t->same('html-reader', $packet['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('Review source table', $packet['sourceAttributes']['htmlAttributes']['aria-label'] ?? null);
        $t->same('source-head', $packet['sections'][0]['sourceAttributes']['id'] ?? null);
        $t->same('thead', $packet['sections'][0]['sourceAttributes']['htmlAttributes']['data-section'] ?? null);
        $t->same('head-1', $packet['sections'][0]['rows'][0]['sourceAttributes']['htmlAttributes']['data-row'] ?? null);
        $t->same('source-body', $packet['sections'][1]['sourceAttributes']['id'] ?? null);
        $t->same('tbody', $packet['sections'][1]['sourceAttributes']['htmlAttributes']['data-section'] ?? null);
        $t->same('body-1', $packet['sections'][1]['rows'][0]['sourceAttributes']['htmlAttributes']['data-row'] ?? null);
        $t->same('source-scope', $packet['coverage'][0]['sourceAttributes']['id'] ?? null);
        $t->same(['header-cell'], $packet['coverage'][0]['sourceAttributes']['classes'] ?? null);
        $t->same('docx', $packet['coverage'][0]['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('manual', $packet['coverage'][1]['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('Imported posts', $packet['coverage'][2]['sourceAttributes']['htmlAttributes']['title'] ?? null);
        $t->true(!array_key_exists('sourceAttributes', $packet['coverage'][3]), 'Reader cells without source attributes should stay compact');
        $t->contains('<table id="source-grid" class="wp-import needs-review" data-origin="html-reader" aria-label="Review source table">', $blocks);
        $t->contains('<thead id="source-head" data-section="thead"><tr data-row="head-1"><th id="source-scope" class="header-cell" data-origin="docx">Scope</th><th data-origin="manual">State</th></tr></thead>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'expands html rowspan zero through the current tbody geometry group' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="rowspan-zero-grid" data-source="html-reader">
<tbody id="posts-body">
<tr data-row="posts-total"><th rowspan="0" align="left">Posts</th><td align="right">42</td></tr>
<tr data-row="posts-media"><td align="right">7</td><td>Needs media</td></tr>
<tr data-row="posts-review"><td align="right">3</td><td>Review</td></tr>
</tbody>
<tbody id="pages-body">
<tr data-row="pages-total"><th>Pages</th><td align="right">5</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(3, TableGeometry::columnCount($table));
        $t->same([1 / 3, 1 / 3, 1 / 3], $table->attr('widths'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(['head', 'body', 'body1'], array_map(static fn (array $section): string => $section['section'], $packet['sections'] ?? []));
        $t->same([0, 3, 1], array_map(static fn (array $section): int => $section['rowCount'], $packet['sections'] ?? []));
        $t->same('posts-body', $packet['sections'][1]['sourceAttributes']['id'] ?? null);
        $t->same('pages-body', $packet['sections'][2]['sourceAttributes']['id'] ?? null);
        $t->same(2, $packet['sections'][1]['summary']['coveredSlotCount'] ?? null);
        $t->same(0, $packet['sections'][2]['summary']['coveredSlotCount'] ?? null);
        $t->same(1, $packet['sections'][1]['summary']['missingSlotCount'] ?? null);
        $t->same('Posts', $packet['coverage'][0]['text'] ?? null);
        $t->same(3, $packet['coverage'][0]['rowspan'] ?? null);
        $t->same(3, $packet['coverage'][0]['rawRowspan'] ?? null);
        $t->same(true, $packet['coverage'][0]['rowspanToEnd'] ?? null);
        $t->same([
            ['row' => 0, 'column' => 0, 'covering' => 'anchor'],
            ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['row' => 2, 'column' => 0, 'covering' => 'rowspan'],
        ], $packet['coverage'][0]['occupiedSlots'] ?? null);
        $t->same('7', $packet['coverage'][2]['text'] ?? null);
        $t->same(1, $packet['coverage'][2]['column'] ?? null);
        $t->same('Needs media', $packet['coverage'][3]['text'] ?? null);
        $t->same(2, $packet['coverage'][3]['column'] ?? null);
        $t->same('Pages', $packet['coverage'][6]['text'] ?? null);
        $t->same('body1', $packet['coverage'][6]['section'] ?? null);
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(['markdown-rowspan-flattened'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->contains('<figure class="wp-block-table"><table id="rowspan-zero-grid" data-source="html-reader"><colgroup><col style="width:33.3333%"/><col style="width:33.3333%"/><col style="width:33.3333%"/></colgroup><tbody id="posts-body"><tr data-row="posts-total"><th rowspan="3" style="text-align:left">Posts</th><td style="text-align:right">42</td></tr><tr data-row="posts-media"><td style="text-align:right">7</td><td>Needs media</td></tr><tr data-row="posts-review"><td style="text-align:right">3</td><td>Review</td></tr></tbody><tbody id="pages-body"><tr data-row="pages-total"><th>Pages</th><td style="text-align:right">5</td><td>Ready</td></tr></tbody></table></figure>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'expands html colgroup span width and alignment metadata into geometry packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-alignment-grid" data-source="html-reader">
<caption>Colgroup alignment review</caption>
<colgroup>
<col span="2" style="width: 25%; text-align: right" />
<col width="50%" align="center" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
<tr><td>Media</td><td>7</td><td>Review</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(3, TableGeometry::columnCount($table));
        $t->same(['right', 'right', 'center'], $table->attr('alignments'));
        $t->same([0.25, 0.25, 0.5], $table->attr('widths'));
        $t->same(['right', 'right', 'center'], TableGeometry::alignments($table, 3));
        $t->same([0.25, 0.25, 0.5], array_map(static fn (array $column): ?float => $column['width'], TableGeometry::columnSpecs($table, 3)));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(['right', 'right', 'center'], array_map(static fn (array $column): string => $column['alignment'], $packet['columns'] ?? []));
        $t->same([0.25, 0.25, 0.5], array_map(static fn (array $column): ?float => $column['width'], $packet['columns'] ?? []));
        $t->same(['right'], $packet['coverage'][3]['columnAlignments'] ?? null);
        $t->same([0.25], $packet['coverage'][3]['widths'] ?? null);
        $t->same(['right'], $packet['coverage'][4]['columnAlignments'] ?? null);
        $t->same([0.25], $packet['coverage'][4]['widths'] ?? null);
        $t->same(['center'], $packet['coverage'][5]['columnAlignments'] ?? null);
        $t->same([0.5], $packet['coverage'][5]['widths'] ?? null);
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:50%"/></colgroup>', $blocks);
        $t->contains('<thead><tr><th style="text-align:right">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td style="text-align:right">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr><td style="text-align:right">Media</td><td style="text-align:right">7</td><td style="text-align:center">Review</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'carries html colgroup span provenance into table geometry review packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-provenance-grid" data-source="html-reader">
<caption>Colgroup provenance review</caption>
<colgroup data-source="legacy-doc">
<col span="2" style="width: 25%; text-align: right" data-origin="col-a" />
<col width="50%" align="center" data-origin="col-b" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(true, is_array($table->attr('columnSources')), 'HTML reader should keep expanded source column provenance on the table AST');
        $columnSources = is_array($table->attr('columnSources')) ? $table->attr('columnSources') : [];
        $t->same(['col', 'col', 'col'], array_map(static fn (array $source): string => (string) ($source['kind'] ?? ''), $columnSources));
        $t->same([0, 0, 0], array_map(static fn (array $source): int => (int) ($source['colgroupIndex'] ?? -1), $columnSources));
        $t->same([0, 0, 1], array_map(static fn (array $source): int => (int) ($source['colIndex'] ?? -1), $columnSources));
        $t->same([0, 1, 0], array_map(static fn (array $source): int => (int) ($source['spanOffset'] ?? -1), $columnSources));
        $t->same([2, 2, 1], array_map(static fn (array $source): int => (int) ($source['sourceSpan'] ?? 0), $columnSources));
        $t->same('legacy-doc', $columnSources[0]['colgroupAttributes']['htmlAttributes']['data-source'] ?? null);
        $t->same('col-a', $columnSources[1]['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('width: 25%; text-align: right', $columnSources[0]['colAttributes']['htmlAttributes']['style'] ?? null);
        $t->same('50%', $columnSources[2]['colAttributes']['htmlAttributes']['width'] ?? null);

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(2, count($packet['columnGroups'] ?? []));
        $t->same([0, 1], $packet['columnGroups'][0]['columns'] ?? null);
        $t->same(0, $packet['columnGroups'][0]['startColumn'] ?? null);
        $t->same(2, $packet['columnGroups'][0]['endColumn'] ?? null);
        $t->same(2, $packet['columnGroups'][0]['span'] ?? null);
        $t->same('col', $packet['columnGroups'][0]['kind'] ?? null);
        $t->same(0, $packet['columnGroups'][0]['colgroupIndex'] ?? null);
        $t->same(0, $packet['columnGroups'][0]['colIndex'] ?? null);
        $t->same([0, 1], $packet['columnGroups'][0]['spanOffsets'] ?? null);
        $t->same([0.25, 0.25], $packet['columnGroups'][0]['widths'] ?? null);
        $t->same(['right', 'right'], $packet['columnGroups'][0]['alignments'] ?? null);
        $t->same('legacy-doc', $packet['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['data-source'] ?? null);
        $t->same('col-a', $packet['columnGroups'][0]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same([2], $packet['columnGroups'][1]['columns'] ?? null);
        $t->same(1, $packet['columnGroups'][1]['colIndex'] ?? null);
        $t->same([0], $packet['columnGroups'][1]['spanOffsets'] ?? null);
        $t->same('col-b', $packet['columnGroups'][1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same(2, $packet['summary']['columnGroupCount'] ?? null);
        $t->same(true, $packet['summary']['hasColumnGroups'] ?? null);
        $t->same('col', $packet['columns'][0]['source']['kind'] ?? null);
        $t->same(0, $packet['columns'][1]['source']['colIndex'] ?? null);
        $t->same(1, $packet['columns'][1]['source']['spanOffset'] ?? null);
        $t->same('col-b', $packet['columns'][2]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('col-a', $packet['coverage'][3]['columnSources'][0]['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('col-b', $packet['coverage'][5]['columnSources'][0]['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:50%"/></colgroup>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'groups html colgroup element span runs in table geometry packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-span-grid" data-source="html-reader">
<caption>Colgroup span review</caption>
<colgroup span="2" style="width: 30%; text-align: right" data-origin="group-span"></colgroup>
<colgroup data-origin="single-group">
<col style="width: 40%; text-align: center" data-origin="single-col" />
</colgroup>
<tbody>
<tr><td>Posts</td><td>Media</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(3, TableGeometry::columnCount($table));
        $t->same(['right', 'right', 'center'], $table->attr('alignments'));
        $t->same([0.3, 0.3, 0.4], $table->attr('widths'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(2, count($packet['columnGroups'] ?? []));
        $t->same('colgroup', $packet['columnGroups'][0]['kind'] ?? null);
        $t->same([0, 1], $packet['columnGroups'][0]['columns'] ?? null);
        $t->same(0, $packet['columnGroups'][0]['startColumn'] ?? null);
        $t->same(2, $packet['columnGroups'][0]['endColumn'] ?? null);
        $t->same(2, $packet['columnGroups'][0]['span'] ?? null);
        $t->same(2, $packet['columnGroups'][0]['sourceSpan'] ?? null);
        $t->same([0, 1], $packet['columnGroups'][0]['spanOffsets'] ?? null);
        $t->same('group-span', $packet['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->true(!array_key_exists('colIndex', $packet['columnGroups'][0]), 'Element-level colgroup spans should not invent child col indexes');
        $t->same('col', $packet['columnGroups'][1]['kind'] ?? null);
        $t->same([2], $packet['columnGroups'][1]['columns'] ?? null);
        $t->same('single-col', $packet['columnGroups'][1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same(2, $packet['summary']['columnGroupCount'] ?? null);
        $t->same(true, $packet['summary']['hasColumnGroups'] ?? null);
        $t->same('group-span', $packet['coverage'][0]['columnSources'][0]['colgroupAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('single-col', $packet['coverage'][2]['columnSources'][0]['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->contains('<colgroup><col style="width:30%"/><col style="width:30%"/><col style="width:40%"/></colgroup>', $blocks);
        $t->contains('<tbody><tr><td style="text-align:right">Posts</td><td style="text-align:right">Media</td><td style="text-align:center">Ready</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports html colgroup count mismatches while preserving usable geometry metadata' => static function (TestRunner $t): void {
        $underdeclaredHtml = <<<'HTML'
<table id="colgroup-underdeclared-grid" data-source="html-reader">
<caption>Colgroup mismatch review</caption>
<colgroup data-source="legacy-doc">
<col span="2" style="width: 20%; text-align: right" data-origin="declared-pair" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML;
        $overdeclaredHtml = <<<'HTML'
<table id="colgroup-overdeclared-grid" data-source="html-reader">
<caption>Colgroup extra review</caption>
<colgroup data-source="legacy-doc">
<col span="3" style="width: 25%; text-align: center" data-origin="declared-extra" />
</colgroup>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $underdeclared = (new MarkdownReader())->read($underdeclaredHtml)->children[0];
        $overdeclared = (new MarkdownReader())->read($overdeclaredHtml)->children[0];
        $underPacket = $underdeclared->attr('tableGeometry');
        $overPacket = $overdeclared->attr('tableGeometry');
        $underBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$underdeclared]));
        $overBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$overdeclared]));

        $t->same(3, TableGeometry::columnCount($underdeclared));
        $t->same(['right', 'right', 'default'], $underdeclared->attr('alignments'));
        $t->same([0.2, 0.2, null], $underdeclared->attr('widths'));
        $t->same(true, is_array($underdeclared->attr('columnSources')));
        $underSources = is_array($underdeclared->attr('columnSources')) ? $underdeclared->attr('columnSources') : [];
        $t->same(2, count($underSources));
        $t->same('declared-pair', $underSources[1]['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same(true, is_array($underPacket));
        $underPacket = is_array($underPacket) ? $underPacket : [];
        $t->same(['html-colgroup-underdeclares-columns'], $underPacket['summary']['diagnosticCodes'] ?? null);
        $t->same('html-colgroup-underdeclares-columns', $underPacket['diagnostics'][0]['code'] ?? null);
        $t->same(2, $underPacket['diagnostics'][0]['sourceColumns'] ?? null);
        $t->same(3, $underPacket['diagnostics'][0]['tableColumns'] ?? null);
        $t->same([2], $underPacket['diagnostics'][0]['missingColumns'] ?? null);
        $t->same('col', $underPacket['columns'][0]['source']['kind'] ?? null);
        $t->same('declared-pair', $underPacket['columns'][1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->true(!array_key_exists('source', $underPacket['columns'][2] ?? []), 'Missing HTML colgroup columns should remain explicit default columns without source provenance');
        $t->same(['right'], $underPacket['coverage'][3]['columnAlignments'] ?? null);
        $t->same([0.2], $underPacket['coverage'][3]['widths'] ?? null);
        $t->same('declared-pair', $underPacket['coverage'][4]['columnSources'][0]['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->true(!array_key_exists('columnSources', $underPacket['coverage'][5] ?? []), 'Cells in missing source columns should not inherit stale colgroup provenance');
        $t->contains('<thead><tr><th style="text-align:right">Scope</th><th style="text-align:right">Items</th><th>State</th></tr></thead>', $underBlocks);
        $t->true(!str_contains($underBlocks, '<colgroup>'), 'Incomplete source widths should not emit a misleading WordPress colgroup');

        $t->same(3, TableGeometry::columnCount($overdeclared));
        $t->same(['center', 'center', 'center'], $overdeclared->attr('alignments'));
        $t->same([0.25, 0.25, 0.25], $overdeclared->attr('widths'));
        $t->same(true, is_array($overPacket));
        $overPacket = is_array($overPacket) ? $overPacket : [];
        $t->same(['html-colgroup-overdeclares-columns'], $overPacket['summary']['diagnosticCodes'] ?? null);
        $t->same([[0, 1, 2]], array_map(static fn (array $group): array => $group['columns'], $overPacket['columnGroups'] ?? []));
        $t->same([0, 1, 2], $overPacket['columnGroups'][0]['spanOffsets'] ?? null);
        $t->same(3, $overPacket['columnGroups'][0]['sourceSpan'] ?? null);
        $t->same(1, $overPacket['summary']['columnGroupCount'] ?? null);
        $t->same(3, $overPacket['diagnostics'][0]['sourceColumns'] ?? null);
        $t->same(2, $overPacket['diagnostics'][0]['tableColumns'] ?? null);
        $t->same([2], $overPacket['diagnostics'][0]['extraColumns'] ?? null);
        $t->same('missing', $overPacket['sections'][1]['rows'][0]['slots'][2]['kind'] ?? null);
        $t->same('declared-extra', $overPacket['columns'][2]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:25%"/></colgroup>', $overBlocks);
        $t->contains('<tbody><tr><td style="text-align:center">Posts</td><td style="text-align:center">Ready</td></tr></tbody>', $overBlocks);
        json_encode($underPacket, JSON_THROW_ON_ERROR);
        json_encode($overPacket, JSON_THROW_ON_ERROR);
    },
];
