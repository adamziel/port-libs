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
    'carries html caption source attributes into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table>
<caption id="source-caption" class="caption-title imported" data-origin="html-reader" aria-label="Caption provenance" style="caption-side: bottom; color: red" onclick="blocked()">Caption <em>source</em></caption>
<tbody>
<tr><th>Scope</th><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $captionSource = $table->attr('captionSource');
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $markdownCodes = array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics);

        $t->same('Caption source', $table->attr('caption'));
        $t->same(true, is_array($captionSource));
        $captionSource = is_array($captionSource) ? $captionSource : [];
        $t->same('caption', $captionSource['element'] ?? null);
        $t->same(0, $captionSource['childIndex'] ?? null);
        $t->same('before-table-sections', $captionSource['position'] ?? null);
        $t->same('bottom', $captionSource['captionSide'] ?? null);
        $t->same('source-caption', $captionSource['sourceAttributes']['id'] ?? null);
        $t->same(['caption-title', 'imported'], $captionSource['sourceAttributes']['classes'] ?? null);
        $t->same('html-reader', $captionSource['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('Caption provenance', $captionSource['sourceAttributes']['htmlAttributes']['aria-label'] ?? null);
        $t->same('caption-side: bottom; color: red', $captionSource['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('source-caption', $packet['captions']['long']['sourceAttributes']['id'] ?? null);
        $t->same(['caption-title', 'imported'], $packet['captions']['long']['sourceAttributes']['classes'] ?? null);
        $t->same('before-table-sections', $packet['captions']['long']['sourcePosition'] ?? null);
        $t->same(0, $packet['captions']['long']['sourceChildIndex'] ?? null);
        $t->same('bottom', $packet['captions']['long']['captionSide'] ?? null);
        $t->same(true, $packet['summary']['hasCaptionSourceAttributes'] ?? null);
        $t->same('caption', $packet['summary']['captionSourceElement'] ?? null);
        $t->same('before-table-sections', $packet['summary']['captionSourcePosition'] ?? null);
        $t->same(0, $packet['summary']['captionSourceChildIndex'] ?? null);
        $t->same('bottom', $packet['summary']['captionSide'] ?? null);

        $t->same(true, in_array('markdown-caption-source-attributes-require-raw-html', $markdownCodes, true));
        $t->same('raw-html-caption-attributes', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('source-caption', $markdownDiagnostics[0]['sourceAttributes']['id'] ?? null);
        $t->contains('<figcaption id="source-caption" class="wp-element-caption caption-title imported" data-origin="html-reader" aria-label="Caption provenance" style="caption-side: bottom; color: red">Caption <em>source</em></figcaption>', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe caption event attributes must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'places explicit html top captions before wordpress table output' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="top-caption-grid" data-source="html-reader">
<caption id="top-caption" class="caption-title" data-origin="html-reader" style="caption-side: top; color: blue">Top <em>caption</em></caption>
<tbody><tr><th>Scope</th><td>Ready</td></tr></tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Top caption', $table->attr('caption'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('top', $packet['captions']['long']['captionSide'] ?? null);
        $t->same('before-table', $packet['captions']['long']['captionPlacement'] ?? null);
        $t->same(true, $packet['captions']['long']['captionBeforeTable'] ?? null);
        $t->same(true, $packet['summary']['captionBeforeTable'] ?? null);
        $t->same(false, $packet['summary']['captionAfterTable'] ?? null);
        $markdownCodes = array_map(static fn (array $diagnostic): string => $diagnostic['code'], $packet['writerDowngrades']['markdown'] ?? []);
        $t->same(true, in_array('markdown-caption-side-reordered', $markdownCodes, true));
        $t->same(true, in_array('markdown-caption-source-attributes-require-raw-html', $markdownCodes, true));
        $t->contains('<figcaption id="top-caption" class="wp-element-caption caption-title" data-origin="html-reader" style="caption-side: top; color: blue">Top <em>caption</em></figcaption><table id="top-caption-grid" data-source="html-reader">', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe caption event attributes must not render');
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
        $t->same(['head', 'body', 'body1'], array_map(static fn (array $section): string => $section['section'], $packet['rowGroups'] ?? []));
        $t->same([0, 3, 1], array_map(static fn (array $section): int => $section['rowCount'], $packet['rowGroups'] ?? []));
        $t->same(2, $packet['summary']['bodyGroupCount'] ?? null);
        $t->same(true, $packet['summary']['hasMultipleBodyGroups'] ?? null);
        $t->same(4, $packet['summary']['bodyRowCount'] ?? null);
        $t->same(0, $packet['summary']['tableHeadRowCount'] ?? null);
        $t->same('posts-body', $packet['sections'][1]['sourceAttributes']['id'] ?? null);
        $t->same('pages-body', $packet['sections'][2]['sourceAttributes']['id'] ?? null);
        $t->same('posts-body', $packet['rowGroups'][1]['sourceAttributes']['id'] ?? null);
        $t->same('pages-body', $packet['rowGroups'][2]['sourceAttributes']['id'] ?? null);
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
        $t->same(['markdown-column-widths-approximated', 'markdown-table-bodies-flattened', 'markdown-row-headers-flattened', 'markdown-rowspan-flattened'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $markdownRowspanDowngrades = array_values(array_filter(
            $packet['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-rowspan-flattened'
        ));
        $t->same(1, count($markdownRowspanDowngrades));
        $t->same(true, $markdownRowspanDowngrades[0]['rowspanToEnd'] ?? null);
        $t->same(3, $markdownRowspanDowngrades[0]['rowspan'] ?? null);
        $t->same(3, $markdownRowspanDowngrades[0]['rawRowspan'] ?? null);
        $t->same([
            ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['row' => 2, 'column' => 0, 'covering' => 'rowspan'],
        ], $markdownRowspanDowngrades[0]['flattenedSlots'] ?? null);
        $rstRowspanRequirements = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'rst-grid-table'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'rst-grid-table-required'
        ));
        $t->same(1, count($rstRowspanRequirements));
        $t->same(true, $rstRowspanRequirements[0]['rowspanToEnd'] ?? null);
        $t->same('grid-table', $rstRowspanRequirements[0]['requiredFeature'] ?? null);
        $latexRowspanRequirements = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'latex'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'latex-multirow-required'
        ));
        $t->same(1, count($latexRowspanRequirements));
        $t->same(true, $latexRowspanRequirements[0]['rowspanToEnd'] ?? null);
        $t->same('multirow', $latexRowspanRequirements[0]['requiredFeature'] ?? null);
        $t->contains('<figure class="wp-block-table"><table id="rowspan-zero-grid" data-source="html-reader"><colgroup><col style="width:33.3333%"/><col style="width:33.3333%"/><col style="width:33.3333%"/></colgroup><tbody id="posts-body"><tr data-row="posts-total"><th rowspan="3" style="text-align:left">Posts</th><td style="text-align:right">42</td></tr><tr data-row="posts-media"><td style="text-align:right">7</td><td>Needs media</td></tr><tr data-row="posts-review"><td style="text-align:right">3</td><td>Review</td></tr></tbody><tbody id="pages-body"><tr data-row="pages-total"><th>Pages</th><td style="text-align:right">5</td><td>Ready</td></tr></tbody></table></figure>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'expands html colgroup span width and alignment metadata into geometry packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-alignment-grid" data-source="html-reader">
<caption>Colgroup alignment review</caption>
<colgroup>
<col span="2" style="width: 25%; text-align: right; vertical-align: bottom" />
<col width="50%" align="center" valign="top" />
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
        $t->same('bottom', $table->children[0]->children[0]->children[0]->attr('valign'));
        $t->same('bottom', $table->children[1]->children[0]->children[1]->attr('valign'));
        $t->same('top', $table->children[1]->children[1]->children[2]->attr('valign'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(['right', 'right', 'center'], array_map(static fn (array $column): string => $column['alignment'], $packet['columns'] ?? []));
        $t->same([0.25, 0.25, 0.5], array_map(static fn (array $column): ?float => $column['width'], $packet['columns'] ?? []));
        $t->same('bottom', $packet['columns'][0]['source']['verticalAlignment'] ?? null);
        $t->same('top', $packet['columns'][2]['source']['verticalAlignment'] ?? null);
        $t->same(['right'], $packet['coverage'][3]['columnAlignments'] ?? null);
        $t->same([0.25], $packet['coverage'][3]['widths'] ?? null);
        $t->same('bottom', $packet['coverage'][3]['verticalAlignment'] ?? null);
        $t->same(['right'], $packet['coverage'][4]['columnAlignments'] ?? null);
        $t->same([0.25], $packet['coverage'][4]['widths'] ?? null);
        $t->same('bottom', $packet['coverage'][4]['verticalAlignment'] ?? null);
        $t->same(['center'], $packet['coverage'][5]['columnAlignments'] ?? null);
        $t->same([0.5], $packet['coverage'][5]['widths'] ?? null);
        $t->same('top', $packet['coverage'][5]['verticalAlignment'] ?? null);
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->contains('<colgroup><col style="width:25%"/><col style="width:25%"/><col valign="top" style="width:50%"/></colgroup>', $blocks);
        $t->contains('<thead><tr><th style="text-align:right; vertical-align:bottom">Scope</th><th style="text-align:right; vertical-align:bottom">Items</th><th style="text-align:center; vertical-align:top">State</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td style="text-align:right; vertical-align:bottom">Posts</td><td style="text-align:right; vertical-align:bottom">42</td><td style="text-align:center; vertical-align:top">Ready</td></tr><tr><td style="text-align:right; vertical-align:bottom">Media</td><td style="text-align:right; vertical-align:bottom">7</td><td style="text-align:center; vertical-align:top">Review</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'applies html colgroup vertical alignment without overriding cell or row metadata' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-valign-precedence">
<colgroup>
<col style="vertical-align: bottom" />
<col valign="top" />
</colgroup>
<tbody>
<tr valign="middle"><td>Row wins</td><td>Row state</td></tr>
<tr><td style="vertical-align: middle">Cell wins</td><td>Column wins</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('middle', $table->children[1]->children[0]->children[0]->attr('valign'));
        $t->same('middle', $table->children[1]->children[0]->children[1]->attr('valign'));
        $t->same('middle', $table->children[1]->children[1]->children[0]->attr('valign'));
        $t->same('top', $table->children[1]->children[1]->children[1]->attr('valign'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('bottom', $packet['columns'][0]['source']['verticalAlignment'] ?? null);
        $t->same('top', $packet['columns'][1]['source']['verticalAlignment'] ?? null);
        $t->same('middle', $packet['coverage'][0]['verticalAlignment'] ?? null);
        $t->same('middle', $packet['coverage'][1]['verticalAlignment'] ?? null);
        $t->same('middle', $packet['coverage'][2]['verticalAlignment'] ?? null);
        $t->same('top', $packet['coverage'][3]['verticalAlignment'] ?? null);
        $t->same('vertical-align: middle', $packet['coverage'][2]['sourceAttributes']['htmlAttributes']['style'] ?? null);
        $t->contains('<tbody><tr valign="middle"><td style="vertical-align:middle">Row wins</td><td style="vertical-align:middle">Row state</td></tr><tr><td style="vertical-align: middle">Cell wins</td><td style="vertical-align:top">Column wins</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'carries html colgroup span provenance into table geometry review packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-provenance-grid" data-source="html-reader">
<caption>Colgroup provenance review</caption>
<colgroup data-source="legacy-doc" onclick="blocked()">
<col span="2" style="width: 25%; text-align: right" data-origin="col-a" onclick="blocked()" />
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
        $t->contains('<colgroup data-source="legacy-doc"><col data-origin="col-a" style="width:25%"/><col data-origin="col-a" style="width:25%"/><col data-origin="col-b" style="width:50%"/></colgroup>', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe colgroup and col event attributes must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'carries html column decimal alignment provenance into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="decimal-alignment-grid" data-source="html-reader">
<caption>Decimal alignment review</caption>
<colgroup data-source="legacy-doc" align="char" char="." charoff="2">
<col span="2" width="25%" data-origin="amount-columns" />
<col width="50%" align="char" char="," charoff="1" data-origin="rate-column" />
</colgroup>
<thead>
<tr><th>Source</th><th>Amount</th><th>Rate</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42.50</td><td>1,25</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(['default', 'default', 'default'], $table->attr('alignments'));
        $t->same([0.25, 0.25, 0.5], $table->attr('widths'));
        $columnSources = is_array($table->attr('columnSources')) ? $table->attr('columnSources') : [];
        $t->same('char', $columnSources[0]['colgroupAttributes']['htmlAttributes']['align'] ?? null);
        $t->same('.', $columnSources[1]['colgroupAttributes']['htmlAttributes']['char'] ?? null);
        $t->same('2', $columnSources[1]['colgroupAttributes']['htmlAttributes']['charoff'] ?? null);
        $t->same('char', $columnSources[2]['colAttributes']['htmlAttributes']['align'] ?? null);
        $t->same(',', $columnSources[2]['colAttributes']['htmlAttributes']['char'] ?? null);
        $t->same('1', $columnSources[2]['colAttributes']['htmlAttributes']['charoff'] ?? null);

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(2, count($packet['columnDecimalAlignments'] ?? []));
        $t->same([0, 1], $packet['columnDecimalAlignments'][0]['columns'] ?? null);
        $t->same('colgroup', $packet['columnDecimalAlignments'][0]['sourceElement'] ?? null);
        $t->same('.', $packet['columnDecimalAlignments'][0]['char'] ?? null);
        $t->same('2', $packet['columnDecimalAlignments'][0]['charoff'] ?? null);
        $t->same([2], $packet['columnDecimalAlignments'][1]['columns'] ?? null);
        $t->same('col', $packet['columnDecimalAlignments'][1]['sourceElement'] ?? null);
        $t->same(',', $packet['columnDecimalAlignments'][1]['char'] ?? null);
        $t->same('1', $packet['columnDecimalAlignments'][1]['charoff'] ?? null);
        $t->same(2, $packet['summary']['columnDecimalAlignmentCount'] ?? null);
        $t->same(true, $packet['summary']['hasColumnDecimalAlignments'] ?? null);
        $t->same([0, 1, 2], $packet['summary']['columnDecimalAlignmentColumns'] ?? null);
        $t->same(['.', ','], $packet['summary']['columnDecimalAlignmentChars'] ?? null);
        $t->same(['2', '1'], $packet['summary']['columnDecimalAlignmentOffsets'] ?? null);

        $markdownDiagnostics = $packet['writerDowngrades']['markdown'] ?? [];
        $markdownCodes = array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $markdownDiagnostics);
        $t->same(true, in_array('markdown-column-char-alignment-require-raw-html', $markdownCodes, true));
        $decimalDiagnostics = array_values(array_filter(
            $markdownDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-column-char-alignment-require-raw-html'
        ));
        $t->same(1, count($decimalDiagnostics));
        $t->same('raw-html-column-char-alignment', $decimalDiagnostics[0]['requiredFeature'] ?? null);
        $t->same([0, 1, 2], $decimalDiagnostics[0]['columns'] ?? null);
        $t->same(['.', ','], $decimalDiagnostics[0]['chars'] ?? null);
        $t->same(['2', '1'], $decimalDiagnostics[0]['charOffsets'] ?? null);
        $t->same('colgroup', $decimalDiagnostics[0]['alignments'][0]['sourceElement'] ?? null);
        $t->same('col', $decimalDiagnostics[0]['alignments'][1]['sourceElement'] ?? null);

        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoc');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'latex');
        $t->same(true, in_array('asciidoc-column-char-alignment-review-required', array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $asciidocDiagnostics), true));
        $t->same(true, in_array('latex-column-char-alignment-review-required', array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $latexDiagnostics), true));

        $t->contains('<colgroup align="char" char="." charoff="2" data-source="legacy-doc"><col data-origin="amount-columns" style="width:25%"/><col data-origin="amount-columns" style="width:25%"/><col align="char" char="," charoff="1" data-origin="rate-column" style="width:50%"/></colgroup>', $blocks);
        $t->contains('<tbody><tr><td>Posts</td><td>42.50</td><td>1,25</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'carries html cell decimal alignment provenance into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="cell-decimal-alignment-grid" data-source="html-reader">
<caption>Cell decimal alignment review</caption>
<thead>
<tr><th align="char" char="." charoff="2">Amount</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td align="char" char="." charoff="1">42.50</td><td>Ready</td></tr>
<tr><td align="char" char="," charoff="3">7,25</td><td>Review</td></tr>
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

        $headerCell = $table->children[0]->children[0]->children[0] ?? null;
        $bodyCell = $table->children[1]->children[0]->children[0] ?? null;
        $t->same('char', $headerCell instanceof AstNode ? ($headerCell->attr('htmlAttributes')['align'] ?? null) : null);
        $t->same('.', $bodyCell instanceof AstNode ? ($bodyCell->attr('htmlAttributes')['char'] ?? null) : null);
        $t->same('1', $bodyCell instanceof AstNode ? ($bodyCell->attr('htmlAttributes')['charoff'] ?? null) : null);

        $t->same(3, count($packet['cellDecimalAlignments'] ?? []));
        $t->same('html-table-cell-char-alignment', $packet['cellDecimalAlignments'][0]['source'] ?? null);
        $t->same('head', $packet['cellDecimalAlignments'][0]['section'] ?? null);
        $t->same(0, $packet['cellDecimalAlignments'][0]['column'] ?? null);
        $t->same([0], $packet['cellDecimalAlignments'][0]['columns'] ?? null);
        $t->same('Amount', $packet['cellDecimalAlignments'][0]['text'] ?? null);
        $t->same('.', $packet['cellDecimalAlignments'][0]['char'] ?? null);
        $t->same('2', $packet['cellDecimalAlignments'][0]['charoff'] ?? null);
        $t->same(',', $packet['cellDecimalAlignments'][2]['char'] ?? null);
        $t->same('3', $packet['cellDecimalAlignments'][2]['charoff'] ?? null);
        $t->same('char', $packet['coverage'][0]['decimalAlignment']['alignment'] ?? null);
        $t->same('.', $packet['coverage'][2]['decimalAlignment']['char'] ?? null);
        $t->same(',', $packet['coverage'][4]['decimalAlignment']['char'] ?? null);
        $t->same(3, $packet['summary']['cellDecimalAlignmentCount'] ?? null);
        $t->same(true, $packet['summary']['hasCellDecimalAlignments'] ?? null);
        $t->same([0], $packet['summary']['cellDecimalAlignmentColumns'] ?? null);
        $t->same(['.', ','], $packet['summary']['cellDecimalAlignmentChars'] ?? null);
        $t->same(['2', '1', '3'], $packet['summary']['cellDecimalAlignmentOffsets'] ?? null);

        $markdownDiagnostics = $packet['writerDowngrades']['markdown'] ?? [];
        $markdownCodes = array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $markdownDiagnostics);
        $t->same(true, in_array('markdown-cell-char-alignment-require-raw-html', $markdownCodes, true));
        $cellDiagnostics = array_values(array_filter(
            $markdownDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-cell-char-alignment-require-raw-html'
        ));
        $t->same(1, count($cellDiagnostics));
        $t->same('raw-html-cell-char-alignment', $cellDiagnostics[0]['requiredFeature'] ?? null);
        $t->same([0], $cellDiagnostics[0]['columns'] ?? null);
        $t->same(['.', ','], $cellDiagnostics[0]['chars'] ?? null);
        $t->same(['2', '1', '3'], $cellDiagnostics[0]['charOffsets'] ?? null);
        $t->same('Amount', $cellDiagnostics[0]['cells'][0]['text'] ?? null);
        $t->same('7,25', $cellDiagnostics[0]['cells'][2]['text'] ?? null);

        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoc');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'latex');
        $t->same(true, in_array('asciidoc-cell-char-alignment-review-required', array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $asciidocDiagnostics), true));
        $t->same(true, in_array('latex-cell-char-alignment-review-required', array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $latexDiagnostics), true));

        $t->contains('<thead><tr><th align="char" char="." charoff="2">Amount</th><th>Status</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td align="char" char="." charoff="1">42.50</td><td>Ready</td></tr><tr><td align="char" char="," charoff="3">7,25</td><td>Review</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'preserves safe html colgroup and col provenance in wordpress output' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="wordpress-colgroup-source" data-source="html-reader">
<caption>WordPress colgroup provenance</caption>
<colgroup id="source-cols" class="audit-cols" data-source="legacy-doc" aria-label="Column provenance" title="review columns" style="width: 100%" onclick="blocked()">
<col span="2" style="width: 33%; text-align: right" data-origin="scope-columns" title="Scope columns" onclick="blocked()" />
<col width="34%" data-origin="status-column" aria-label="Status column" valign="top" />
</colgroup>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same([0.33, 0.33, 0.34], $table->attr('widths'));
        $t->same(['right', 'right', 'default'], $table->attr('alignments'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('source-cols', $packet['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same('legacy-doc', $packet['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['data-source'] ?? null);
        $t->same('scope-columns', $packet['columnGroups'][0]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same('status-column', $packet['columnGroups'][1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->contains('<colgroup id="source-cols" class="audit-cols" aria-label="Column provenance" data-source="legacy-doc" title="review columns"><col data-origin="scope-columns" title="Scope columns" style="width:33%"/><col data-origin="scope-columns" title="Scope columns" style="width:33%"/><col aria-label="Status column" data-origin="status-column" valign="top" style="width:34%"/></colgroup>', $blocks);
        $t->contains('<tbody><tr><td style="text-align:right">Posts</td><td style="text-align:right">42</td><td style="vertical-align:top">Ready</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe colgroup and col event attributes must not render');
        $t->true(!str_contains($blocks, 'width="34%"'), 'Raw source col width attributes should not override normalized geometry widths');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'carries html table summary accessibility metadata into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="summary-source-grid" summary="Legacy source table describes post counts by import state." data-source="html-reader">
<caption>Summary source review</caption>
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

        $t->same('table', $table->type);
        $t->same('Legacy source table describes post counts by import state.', $table->attr('htmlAttributes')['summary'] ?? null);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('Legacy source table describes post counts by import state.', $packet['sourceSummary']['text'] ?? null);
        $t->same('html-table-summary', $packet['sourceSummary']['source'] ?? null);
        $t->same('summary', $packet['sourceSummary']['attribute'] ?? null);
        $t->same(true, $packet['summary']['hasSourceSummary'] ?? null);
        $t->same('Legacy source table describes post counts by import state.', $packet['summary']['sourceSummaryText'] ?? null);

        $markdownSummaryDiagnostics = array_values(array_filter(
            $packet['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-table-summary-require-raw-html'
        ));
        $t->same(1, count($markdownSummaryDiagnostics));
        $t->same('table-summary', $markdownSummaryDiagnostics[0]['reason'] ?? null);
        $t->same('raw-html-table-summary', $markdownSummaryDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('html-table-summary', $markdownSummaryDiagnostics[0]['source'] ?? null);
        $t->same('Legacy source table describes post counts by import state.', $markdownSummaryDiagnostics[0]['summaryText'] ?? null);

        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $t->same(['asciidoc-table-summary-review-required'], array_values(array_filter(
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $asciidocDiagnostics),
            static fn (string $code): bool => $code === 'asciidoc-table-summary-review-required'
        )));
        $t->same(['latex-table-summary-review-required'], array_values(array_filter(
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $latexDiagnostics),
            static fn (string $code): bool => $code === 'latex-table-summary-review-required'
        )));

        $t->contains('<table id="summary-source-grid" summary="Legacy source table describes post counts by import state." data-source="html-reader">', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Summary source review</figcaption>', $blocks);
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
        $t->contains('<colgroup data-origin="group-span"><col style="width:30%"/><col style="width:30%"/></colgroup><colgroup data-origin="single-group"><col data-origin="single-col" style="width:40%"/></colgroup>', $blocks);
        $t->contains('<tbody><tr><td style="text-align:right">Posts</td><td style="text-align:right">Media</td><td style="text-align:center">Ready</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'applies source colgroup scoped headers across parsed html colgroups' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="colgroup-source-scope-grid" data-source="html-reader">
<caption>Colgroup source scope review</caption>
<colgroup id="import-columns" data-origin="legacy-doc" span="2"></colgroup>
<colgroup id="state-column" data-origin="legacy-doc"></colgroup>
<thead>
<tr><th id="source-import-scope" scope="colgroup">Import scope</th><th id="source-items" scope="col">Items</th><th id="source-state" scope="col">State</th></tr>
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
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Colgroup Source Scope Grid');
        $associations = TableGeometry::headerAssociations($table, 'Colgroup Source Scope Grid');
        $matrix = TableGeometry::rowMatrix($table, 'Colgroup Source Scope Grid');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('colgroup', $accessibility['head:0:0:0']['scope'] ?? null);
        $t->same([0, 1], $accessibility['head:0:0:0']['columns'] ?? null);
        $t->same([0, 1], $accessibility['head:0:0:0']['sourceColumnGroup']['columns'] ?? null);
        $t->same('import-columns', $accessibility['head:0:0:0']['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same(['source-import-scope'], $accessibility['body:0:0:0']['headers'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $accessibility['body:0:1:1']['headers'] ?? null);
        $t->same(['source-state'], $accessibility['body:0:2:2']['headers'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $accessibility['body:1:1:1']['headers'] ?? null);

        $t->same(3, $associations['summary']['headerCellCount'] ?? null);
        $t->same(6, $associations['summary']['dataCellCount'] ?? null);
        $t->same(6, $associations['summary']['associatedDataCellCount'] ?? null);
        $t->same(8, $associations['summary']['associationCount'] ?? null);
        $t->same(['colgroup', 'col'], $associations['summary']['headerScopes'] ?? null);
        $t->same('colgroup', $associations['headerCells'][0]['scope'] ?? null);
        $t->same('colgroup', $associations['headerCells'][0]['sourceScope'] ?? null);
        $t->same([0, 1], $associations['headerCells'][0]['columns'] ?? null);
        $t->same([0, 1], $associations['headerCells'][0]['sourceColumnGroup']['columns'] ?? null);
        $t->same('import-columns', $associations['headerCells'][0]['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same(['source-import-scope'], $associations['dataCells'][0]['headers'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $associations['dataCells'][1]['headers'] ?? null);
        $t->same(['source-state'], $associations['dataCells'][2]['headers'] ?? null);

        $t->same([0, 1], $matrix['rows'][0]['headerCells'][0]['columns'] ?? null);
        $t->same('import-columns', $matrix['rows'][0]['headerCells'][0]['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $matrix['rows'][1]['dataCells'][1]['headers'] ?? null);
        $t->same($associations, $packet['headerAssociations'] ?? null);
        $t->same(8, $packet['summary']['headerAssociationCount'] ?? null);
        $t->same([0, 1], $packet['accessibility']['head:0:0:0']['columns'] ?? null);
        $t->contains('<colgroup id="import-columns" data-origin="legacy-doc"><col style="width:33.3333%"/><col style="width:33.3333%"/></colgroup><colgroup id="state-column" data-origin="legacy-doc"><col style="width:33.3333%"/></colgroup>', $blocks);
        $t->contains('<th id="source-import-scope" scope="colgroup">Import scope</th><th id="source-items" scope="col">Items</th><th id="source-state" scope="col">State</th>', $blocks);
        $t->contains('<td>Posts</td><td>42</td><td>Ready</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($associations, JSON_THROW_ON_ERROR);
        json_encode($matrix, JSON_THROW_ON_ERROR);
    },
    'carries html table header axis metadata into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="axis-source-grid" data-source="html-reader">
<caption>Axis source review</caption>
<thead>
<tr><th id="axis-document" axis="document, import" scope="col">Document</th><th id="axis-state" axis="state review" scope="col">State</th></tr>
</thead>
<tbody>
<tr><th id="axis-posts" axis="content-type" scope="row">Posts</th><td headers="axis-document axis-state axis-posts">Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $associations = TableGeometry::headerAssociations($table, 'Axis Source Grid');
        $rowHeaderMap = TableGeometry::rowHeaderMap($table, 'Axis Source Grid');
        $matrix = TableGeometry::rowMatrix($table, 'Axis Source Grid');
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdownAxisDiagnostics = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'markdown'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-header-axis-require-raw-html'
        ));
        $asciidocAxisDiagnostics = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'asciidoc'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'asciidoc-header-axis-review-required'
        ));
        $latexAxisDiagnostics = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'latex'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'latex-header-axis-review-required'
        ));

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('document, import', $packet['coverage'][0]['sourceAttributes']['htmlAttributes']['axis'] ?? null);
        $t->same('state review', $packet['coverage'][1]['sourceAttributes']['htmlAttributes']['axis'] ?? null);
        $t->same('content-type', $packet['coverage'][2]['sourceAttributes']['htmlAttributes']['axis'] ?? null);

        $t->same(3, $associations['summary']['headerAxisCount'] ?? null);
        $t->same(true, $associations['summary']['hasHeaderAxes'] ?? null);
        $t->same(['document', 'import', 'state', 'review', 'content-type'], $associations['summary']['headerAxes'] ?? null);
        $t->same(['document', 'import'], $associations['headerCells'][0]['axis'] ?? null);
        $t->same(['state', 'review'], $associations['headerCells'][1]['axis'] ?? null);
        $t->same(['content-type'], $associations['headerCells'][2]['axis'] ?? null);
        $t->same(['axis-document', 'axis-state', 'axis-posts'], $associations['dataCells'][0]['sourceHeaders'] ?? null);
        $t->same(['document', 'import'], $associations['dataCells'][0]['sourceHeaderReferences'][0]['targetAxis'] ?? null);
        $t->same(['state', 'review'], $associations['dataCells'][0]['sourceHeaderReferences'][1]['targetAxis'] ?? null);
        $t->same(['content-type'], $associations['dataCells'][0]['sourceHeaderReferences'][2]['targetAxis'] ?? null);

        $t->same(['content-type'], $rowHeaderMap['rows'][0]['headers'][0]['axis'] ?? null);
        $t->same(['document', 'import'], $matrix['rows'][0]['headerCells'][0]['axis'] ?? null);
        $t->same(['content-type'], $matrix['rows'][1]['headerCells'][0]['axis'] ?? null);
        $t->same(['axis-document', 'axis-state', 'axis-posts'], $matrix['rows'][1]['dataCells'][0]['sourceHeaders'] ?? null);
        $t->same(['document', 'import'], $matrix['rows'][1]['dataCells'][0]['sourceHeaderReferences'][0]['targetAxis'] ?? null);

        $t->same(3, $packet['summary']['headerAxisCount'] ?? null);
        $t->same(true, $packet['summary']['hasHeaderAxes'] ?? null);
        $t->same(['document', 'import', 'state', 'review', 'content-type'], $packet['summary']['headerAxes'] ?? null);
        $t->same($associations, $packet['headerAssociations'] ?? null);
        $t->same($rowHeaderMap, $packet['rowHeaderMap'] ?? null);
        $t->same($matrix, $packet['rowMatrix'] ?? null);

        $t->same(1, count($markdownAxisDiagnostics));
        $t->same('header-axis', $markdownAxisDiagnostics[0]['reason'] ?? null);
        $t->same('raw-html-table-header-axis', $markdownAxisDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(['document', 'import', 'state', 'review', 'content-type'], $markdownAxisDiagnostics[0]['axes'] ?? null);
        $t->same(['document', 'import'], $markdownAxisDiagnostics[0]['headerCells'][0]['axis'] ?? null);
        $t->same(1, count($asciidocAxisDiagnostics));
        $t->same('header-axis-review', $asciidocAxisDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(1, count($latexAxisDiagnostics));
        $t->same('table-header-axis-comments', $latexAxisDiagnostics[0]['requiredFeature'] ?? null);

        $t->contains('<th id="axis-document" axis="document, import" scope="col">Document</th>', $blocks);
        $t->contains('<th id="axis-posts" axis="content-type" scope="row">Posts</th><td headers="axis-document axis-state axis-posts">Ready</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($associations, JSON_THROW_ON_ERROR);
        json_encode($rowHeaderMap, JSON_THROW_ON_ERROR);
        json_encode($matrix, JSON_THROW_ON_ERROR);
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
        $t->contains('<colgroup data-source="legacy-doc"><col data-origin="declared-extra" style="width:25%"/><col data-origin="declared-extra" style="width:25%"/><col data-origin="declared-extra" style="width:25%"/></colgroup>', $overBlocks);
        $t->contains('<tbody><tr><td style="text-align:center">Posts</td><td style="text-align:center">Ready</td></tr></tbody>', $overBlocks);
        json_encode($underPacket, JSON_THROW_ON_ERROR);
        json_encode($overPacket, JSON_THROW_ON_ERROR);
    },
    'inherits html row group and row table alignment into geometry packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="inherited-alignment-grid" data-source="html-reader">
<caption>Inherited alignment review</caption>
<thead align="center">
<tr><th>Scope</th><th style="text-align: right">Items</th><th>State</th></tr>
</thead>
<tbody style="text-align: right" data-section="body">
<tr data-row="posts"><th>Posts</th><td>42</td><td align="center">Ready</td></tr>
<tr style="text-align: left" data-row="media"><th>Media</th><td>7</td><td>Review</td></tr>
</tbody>
<tfoot align="center">
<tr><td>Total</td><td>49</td><td>Review</td></tr>
</tfoot>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(['default', 'default', 'default'], $table->attr('alignments'));
        $t->same('center', $table->children[0]->children[0]->children[0]->attr('align'));
        $t->same('right', $table->children[0]->children[0]->children[1]->attr('align'));
        $t->same('center', $table->children[0]->children[0]->children[2]->attr('align'));
        $t->same('right', $table->children[1]->children[0]->children[0]->attr('align'));
        $t->same('right', $table->children[1]->children[0]->children[1]->attr('align'));
        $t->same('center', $table->children[1]->children[0]->children[2]->attr('align'));
        $t->same('left', $table->children[1]->children[1]->children[0]->attr('align'));
        $t->same('left', $table->children[1]->children[1]->children[1]->attr('align'));
        $t->same('left', $table->children[1]->children[1]->children[2]->attr('align'));
        $t->same('center', $table->children[2]->children[0]->children[0]->attr('align'));
        $t->same('center', $table->children[2]->children[0]->children[1]->attr('align'));
        $t->same('center', $table->children[2]->children[0]->children[2]->attr('align'));
        $t->same(1, $table->children[1]->attr('rowHeadColumns'));

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same([
            'center',
            'right',
            'center',
            'right',
            'right',
            'center',
            'left',
            'left',
            'left',
            'center',
            'center',
            'center',
        ], array_map(static fn (array $coverage): string => (string) ($coverage['alignment'] ?? ''), $packet['coverage'] ?? []));
        $t->same('center', $packet['coverage'][0]['alignment'] ?? null);
        $t->same('right', $packet['coverage'][4]['alignment'] ?? null);
        $t->same('left', $packet['coverage'][8]['alignment'] ?? null);
        $t->same('center', $packet['coverage'][10]['alignment'] ?? null);
        $t->same(true, $packet['coverage'][3]['headerCell'] ?? null);
        $t->same('body', $packet['coverage'][3]['rowRole'] ?? null);
        $t->same(1, $packet['coverage'][3]['rowHeadColumns'] ?? null);
        $t->contains('<thead><tr><th style="text-align:center">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead>', $blocks);
        $t->contains('<tbody data-section="body"><tr data-row="posts"><th style="text-align:right">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr data-row="media"><th style="text-align:left">Media</th><td style="text-align:left">7</td><td style="text-align:left">Review</td></tr></tbody>', $blocks);
        $t->contains('<tfoot><tr><td style="text-align:center">Total</td><td style="text-align:center">49</td><td style="text-align:center">Review</td></tr></tfoot>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'carries html and docbook vertical alignment into geometry packets' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="vertical-alignment-grid" data-source="html-reader">
<caption>Vertical alignment review</caption>
<thead valign="top">
<tr><th>Scope</th><th style="vertical-align: bottom">State</th></tr>
</thead>
<tbody data-section="body" valign="baseline">
<tr><td valign="middle">Posts</td><td>Ready</td></tr>
<tr style="vertical-align: top"><td>Total</td><td>Review</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $docbook = (new MarkdownReader())->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-docbook-table.xml'))->children[0];
        $docbookPacket = $docbook->attr('tableGeometry');
        $docbookBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$docbook]));

        $t->same('table', $table->type);
        $t->same('top', $table->children[0]->children[0]->children[0]->attr('valign'));
        $t->same('bottom', $table->children[0]->children[0]->children[1]->attr('valign'));
        $t->same('middle', $table->children[1]->children[0]->children[0]->attr('valign'));
        $t->same('baseline', $table->children[1]->children[0]->children[1]->attr('valign'));
        $t->same('top', $table->children[1]->children[1]->children[0]->attr('valign'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'top',
            'bottom',
            'middle',
            'baseline',
            'top',
            'top',
        ], array_map(static fn (array $coverage): string => (string) ($coverage['verticalAlignment'] ?? ''), $packet['coverage'] ?? []));
        $t->same('top', $packet['sections'][0]['rows'][0]['slots'][0]['verticalAlignment'] ?? null);
        $t->same('baseline', $packet['sections'][1]['rows'][0]['slots'][1]['verticalAlignment'] ?? null);
        $t->same('middle', $packet['coverage'][2]['sourceAttributes']['htmlAttributes']['valign'] ?? null);
        $t->contains('<thead valign="top"><tr><th style="vertical-align:top">Scope</th><th style="vertical-align: bottom">State</th></tr></thead>', $blocks);
        $t->contains('<tbody data-section="body" valign="baseline"><tr><td valign="middle">Posts</td><td style="vertical-align:baseline">Ready</td></tr><tr style="vertical-align: top"><td style="vertical-align:top">Total</td><td style="vertical-align:top">Review</td></tr></tbody>', $blocks);

        $t->same(true, is_array($docbookPacket));
        $docbookPacket = is_array($docbookPacket) ? $docbookPacket : [];
        $t->same('top', $docbook->children[1]->children[0]->children[0]->attr('valign'));
        $t->same(['top'], array_values(array_unique(array_map(static fn (array $coverage): string => (string) ($coverage['verticalAlignment'] ?? ''), $docbookPacket['coverage'] ?? []))));
        $t->same('top', $docbookPacket['sections'][1]['rows'][0]['slots'][0]['verticalAlignment'] ?? null);
        $t->contains('<td colspan="4" style="text-align:center; vertical-align:top"><strong>Migration Batch 42</strong></td>', $docbookBlocks);
        $t->contains('<td style="text-align:left; vertical-align:top">Posts</td><td style="text-align:right; vertical-align:top">42</td><td style="text-align:center; vertical-align:top">ready</td><td style="vertical-align:top">editorial</td>', $docbookBlocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($docbookPacket, JSON_THROW_ON_ERROR);
    },
    'normalizes legacy html table frame rules and border geometry' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="legacy-frame-grid" data-source="html-reader" frame="void" rules="groups" border="1">
<caption>Legacy frame review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $tableFrameDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-frame'
            ));
            $tableFrameDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('void', $packet['tableFrame']['frame'] ?? null);
        $t->same('groups', $packet['tableFrame']['rules'] ?? null);
        $t->same('1', $packet['tableFrame']['border'] ?? null);
        $t->same([
            'border' => '1',
            'frame' => 'void',
            'rules' => 'groups',
        ], $packet['tableFrame']['attributes'] ?? null);
        $t->same(true, $packet['summary']['hasTableFrame'] ?? null);
        $t->same('void', $packet['summary']['tableFrame'] ?? null);
        $t->same('groups', $packet['summary']['tableRules'] ?? null);
        $t->same('1', $packet['summary']['tableBorder'] ?? null);
        $t->same('void', $packet['sourceAttributes']['htmlAttributes']['frame'] ?? null);
        $t->same('groups', $packet['sourceAttributes']['htmlAttributes']['rules'] ?? null);
        $t->same('1', $packet['sourceAttributes']['htmlAttributes']['border'] ?? null);
        $t->same([
            'markdown-table-frame-requires-raw-html',
            'asciidoc-table-frame-review-required',
            'latex-table-frame-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $tableFrameDiagnostics['markdown'],
            $tableFrameDiagnostics['asciidoc'],
            $tableFrameDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-frame', $tableFrameDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-attributes', $tableFrameDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'border' => '1',
            'frame' => 'void',
            'rules' => 'groups',
        ], $tableFrameDiagnostics['markdown']['attributes'] ?? null);
        $t->contains('<table id="legacy-frame-grid" data-source="html-reader" border="1" frame="void" rules="groups">', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
];
