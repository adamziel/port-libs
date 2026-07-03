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
        $t->same(false, in_array('markdown-caption-side-reordered', $markdownCodes, true));
        $t->same(true, in_array('markdown-caption-source-attributes-require-raw-html', $markdownCodes, true));
        $t->contains('<figcaption id="top-caption" class="wp-element-caption caption-title" data-origin="html-reader" style="caption-side: top; color: blue">Top <em>caption</em></figcaption><table id="top-caption-grid" data-source="html-reader">', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe caption event attributes must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports side captions as review required while preserving wordpress fallback placement' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="side-caption-grid" data-source="html-reader">
<caption id="side-caption" class="caption-title" data-origin="html-reader" style="caption-side: left; color: green" onclick="blocked()">Side <em>caption</em></caption>
<tbody><tr><th>Scope</th><td>Ready</td></tr></tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Side caption', $table->attr('caption'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('left', $packet['captions']['long']['captionSide'] ?? null);
        $t->same(false, $packet['captions']['long']['captionSideSupported'] ?? null);
        $t->same(true, $packet['captions']['long']['captionSideReviewRequired'] ?? null);
        $t->same('after-table', $packet['captions']['long']['captionPlacementFallback'] ?? null);
        $t->same('after-table', $packet['summary']['captionPlacement'] ?? null);
        $t->same('after-table', $packet['summary']['captionPlacementFallback'] ?? null);
        $t->same(true, $packet['summary']['captionAfterTable'] ?? null);
        $t->same(true, $packet['summary']['captionSideReviewRequired'] ?? null);

        $markdownDiagnostics = $packet['writerDowngrades']['markdown'] ?? [];
        $markdownCodes = array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $markdownDiagnostics);
        $t->same(true, in_array('markdown-caption-side-review-required', $markdownCodes, true));
        $t->same(true, in_array('markdown-caption-source-attributes-require-raw-html', $markdownCodes, true));
        $sideDiagnostics = array_values(array_filter(
            $markdownDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-caption-side-review-required'
        ));
        $t->same(1, count($sideDiagnostics));
        $t->same('left', $sideDiagnostics[0]['captionSide'] ?? null);
        $t->same('raw-html-caption-side', $sideDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('after-table', $sideDiagnostics[0]['captionPlacementFallback'] ?? null);

        $t->contains('<table id="side-caption-grid" data-source="html-reader">', $blocks);
        $t->contains('<figcaption id="side-caption" class="wp-element-caption caption-title" data-origin="html-reader" style="caption-side: left; color: green">Side <em>caption</em></figcaption>', $blocks);
        $t->true(strpos($blocks, '</table><figcaption') !== false, 'Side captions should keep the safe WordPress after-table fallback placement');
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe caption event attributes must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'uses legacy html caption align as caption-side fallback for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $topHtml = <<<'HTML'
<table id="legacy-align-top-grid" data-source="html-reader">
<caption id="legacy-align-top-caption" class="caption-title" data-origin="legacy-doc" align="top" onclick="blocked()">Legacy <em>top</em> caption</caption>
<tbody><tr><th>Scope</th><td>Ready</td></tr></tbody>
</table>
HTML;
        $sideHtml = <<<'HTML'
<table id="legacy-align-side-grid" data-source="html-reader">
<caption id="legacy-align-side-caption" class="caption-title" data-origin="legacy-doc" align="right" onclick="blocked()">Legacy <em>side</em> caption</caption>
<tbody><tr><th>Scope</th><td>Ready</td></tr></tbody>
</table>
HTML;

        $topDocument = (new MarkdownReader())->read($topHtml);
        $topTable = $topDocument->children[0];
        $topCaptionSource = $topTable->attr('captionSource');
        $topPacket = $topTable->attr('tableGeometry');
        $topBlocks = (new WordPressBlockWriter())->write($topDocument);

        $t->same('Legacy top caption', $topTable->attr('caption'));
        $t->same(true, is_array($topCaptionSource));
        $topCaptionSource = is_array($topCaptionSource) ? $topCaptionSource : [];
        $t->same('top', $topCaptionSource['captionSide'] ?? null);
        $t->same('align', $topCaptionSource['captionSideSource'] ?? null);
        $t->same('top', $topCaptionSource['sourceAttributes']['htmlAttributes']['align'] ?? null);
        $t->same('legacy-doc', $topCaptionSource['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);

        $t->same(true, is_array($topPacket));
        $topPacket = is_array($topPacket) ? $topPacket : [];
        $t->same('top', $topPacket['captions']['long']['captionSide'] ?? null);
        $t->same('align', $topPacket['captions']['long']['captionSideSource'] ?? null);
        $t->same('align', $topPacket['summary']['captionSideSource'] ?? null);
        $t->same('before-table', $topPacket['summary']['captionPlacement'] ?? null);
        $t->same(true, $topPacket['summary']['captionBeforeTable'] ?? null);
        $topMarkdownDiagnostics = $topPacket['writerDowngrades']['markdown'] ?? [];
        $topSideDiagnostics = array_values(array_filter(
            $topMarkdownDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-caption-side-reordered'
        ));
        $t->same(0, count($topSideDiagnostics));
        $t->contains('<figcaption id="legacy-align-top-caption" class="wp-element-caption caption-title" data-origin="legacy-doc">Legacy <em>top</em> caption</figcaption><table id="legacy-align-top-grid" data-source="html-reader">', $topBlocks);
        $t->true(!str_contains($topBlocks, 'align='), 'Legacy caption align should drive geometry without rendering obsolete figcaption align');
        $t->true(!str_contains($topBlocks, 'onclick='), 'Unsafe legacy caption event attributes must not render');
        json_encode($topPacket, JSON_THROW_ON_ERROR);

        $sideDocument = (new MarkdownReader())->read($sideHtml);
        $sideTable = $sideDocument->children[0];
        $sideCaptionSource = $sideTable->attr('captionSource');
        $sidePacket = $sideTable->attr('tableGeometry');
        $sideBlocks = (new WordPressBlockWriter())->write($sideDocument);

        $t->same('Legacy side caption', $sideTable->attr('caption'));
        $t->same(true, is_array($sideCaptionSource));
        $sideCaptionSource = is_array($sideCaptionSource) ? $sideCaptionSource : [];
        $t->same('right', $sideCaptionSource['captionSide'] ?? null);
        $t->same('align', $sideCaptionSource['captionSideSource'] ?? null);
        $t->same('right', $sideCaptionSource['sourceAttributes']['htmlAttributes']['align'] ?? null);

        $t->same(true, is_array($sidePacket));
        $sidePacket = is_array($sidePacket) ? $sidePacket : [];
        $t->same('right', $sidePacket['captions']['long']['captionSide'] ?? null);
        $t->same('align', $sidePacket['captions']['long']['captionSideSource'] ?? null);
        $t->same('align', $sidePacket['summary']['captionSideSource'] ?? null);
        $t->same(false, $sidePacket['summary']['captionSideSupported'] ?? null);
        $t->same(true, $sidePacket['summary']['captionSideReviewRequired'] ?? null);
        $t->same('after-table', $sidePacket['summary']['captionPlacementFallback'] ?? null);
        $sideMarkdownDiagnostics = $sidePacket['writerDowngrades']['markdown'] ?? [];
        $sideReviewDiagnostics = array_values(array_filter(
            $sideMarkdownDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-caption-side-review-required'
        ));
        $t->same(1, count($sideReviewDiagnostics));
        $t->same('right', $sideReviewDiagnostics[0]['captionSide'] ?? null);
        $t->same('align', $sideReviewDiagnostics[0]['captionSideSource'] ?? null);
        $t->same('after-table', $sideReviewDiagnostics[0]['captionPlacementFallback'] ?? null);
        $t->contains('<table id="legacy-align-side-grid" data-source="html-reader">', $sideBlocks);
        $t->contains('</table><figcaption id="legacy-align-side-caption" class="wp-element-caption caption-title" data-origin="legacy-doc">Legacy <em>side</em> caption</figcaption>', $sideBlocks);
        $t->true(!str_contains($sideBlocks, 'align='), 'Legacy side caption align should stay metadata-only in sanitized WordPress output');
        $t->true(!str_contains($sideBlocks, 'onclick='), 'Unsafe legacy side caption event attributes must not render');
        json_encode($sidePacket, JSON_THROW_ON_ERROR);
    },
    'counts html row header colspans as visual row-head columns' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="visual-rowhead-grid" data-source="html-reader">
<caption>Visual row head review</caption>
<thead>
<tr><th colspan="2">Source</th><th>Status</th></tr>
</thead>
<tbody data-section="body">
<tr><th colspan="2">Posts and pages</th><td>Ready</td></tr>
<tr><th colspan="2">Media assets</th><td>Review</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $body = $table->children[1] ?? null;
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('table_body', $body?->type ?? null);
        $t->same(3, TableGeometry::columnCount($table));
        $t->same(2, $body instanceof AstNode ? $body->attr('rowHeadColumns') : null);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(2, $packet['rowGroups'][1]['rowHeadColumns'] ?? null);
        $t->same(2, $packet['summary']['maxRowHeadColumns'] ?? null);
        $t->same(1, $packet['summary']['rowHeadGroupCount'] ?? null);
        $t->same('Posts and pages', $packet['coverage'][2]['text'] ?? null);
        $t->same([0, 1], $packet['coverage'][2]['columns'] ?? null);
        $t->same(2, $packet['coverage'][2]['colspan'] ?? null);
        $t->same(2, $packet['coverage'][2]['rowHeadColumns'] ?? null);
        $t->same(true, $packet['coverage'][2]['headerCell'] ?? null);
        $t->same('Ready', $packet['coverage'][3]['text'] ?? null);
        $t->same([2], $packet['coverage'][3]['columns'] ?? null);
        $t->same(false, $packet['coverage'][3]['headerCell'] ?? null);
        $t->contains('<tbody data-section="body"><tr><th colspan="2">Posts and pages</th><td>Ready</td></tr><tr><th colspan="2">Media assets</th><td>Review</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports row-head columns per html tbody group in writer handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="multi-rowhead-grid" data-source="html-reader">
<caption>Multiple body row-head review</caption>
<thead>
<tr><th>Group</th><th>Item</th><th>Status</th></tr>
</thead>
<tbody id="posts-body">
<tr><th colspan="2">Posts</th><td>Ready</td></tr>
<tr><th colspan="2">Pages</th><td>Review</td></tr>
</tbody>
<tbody id="media-body">
<tr><th>Images</th><td>7</td><td>Review</td></tr>
<tr><th>Video</th><td>2</td><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $firstBody = $table->children[1] ?? null;
        $secondBody = $table->children[2] ?? null;
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(3, TableGeometry::columnCount($table));
        $t->same(2, $firstBody instanceof AstNode ? $firstBody->attr('rowHeadColumns') : null);
        $t->same(1, $secondBody instanceof AstNode ? $secondBody->attr('rowHeadColumns') : null);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(['head', 'body', 'body1'], array_map(static fn (array $section): string => $section['section'], $packet['rowGroups'] ?? []));
        $t->same([2, 1], array_map(static fn (array $section): int => $section['rowHeadColumns'], array_slice($packet['rowGroups'] ?? [], 1)));
        $t->same(2, $packet['summary']['rowHeadGroupCount'] ?? null);
        $t->same(2, $packet['summary']['maxRowHeadColumns'] ?? null);
        $t->same(['body', 'body1'], $packet['summary']['rowHeadSections'] ?? null);
        $t->same([2, 1], $packet['summary']['rowHeadColumnCounts'] ?? null);
        $t->same(true, $packet['summary']['hasDifferingRowHeadColumns'] ?? null);
        $t->same([
            [
                'section' => 'body',
                'rowRange' => [1, 3],
                'rowCount' => 2,
                'rowRole' => 'body',
                'rowHeadColumns' => 2,
                'bodyIndex' => 0,
                'bodyOrdinal' => 0,
            ],
            [
                'section' => 'body1',
                'rowRange' => [3, 5],
                'rowCount' => 2,
                'rowRole' => 'body',
                'rowHeadColumns' => 1,
                'bodyIndex' => 1,
                'bodyOrdinal' => 1,
            ],
        ], $packet['summary']['rowHeadGroupRanges'] ?? null);

        $markdownBodyDiagnostics = array_values(array_filter(
            $packet['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-table-bodies-flattened'
        ));
        $t->same(1, count($markdownBodyDiagnostics));
        $markdownBodyDiagnostic = $markdownBodyDiagnostics[0] ?? [];
        $t->same([2, 1], $markdownBodyDiagnostic['bodySectionRowHeadColumns'] ?? null);
        $t->same(['body', 'body1'], $markdownBodyDiagnostic['rowHeadBodySections'] ?? null);
        $t->same([2, 1], $markdownBodyDiagnostic['rowHeadColumnCounts'] ?? null);
        $t->same(2, $markdownBodyDiagnostic['rowHeadGroupCount'] ?? null);
        $t->same(2, $markdownBodyDiagnostic['maxRowHeadColumns'] ?? null);
        $t->same(true, $markdownBodyDiagnostic['hasDifferingRowHeadColumns'] ?? null);
        $t->same($packet['summary']['rowHeadGroupRanges'] ?? null, $markdownBodyDiagnostic['rowHeadSectionRanges'] ?? null);

        $asciidocBodyDiagnostics = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'asciidoc'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'asciidoc-table-bodies-review-required'
        ));
        $t->same(1, count($asciidocBodyDiagnostics));
        $t->same([2, 1], ($asciidocBodyDiagnostics[0] ?? [])['bodySectionRowHeadColumns'] ?? null);
        $t->same($packet['summary']['rowHeadGroupRanges'] ?? null, ($asciidocBodyDiagnostics[0] ?? [])['rowHeadSectionRanges'] ?? null);

        $t->contains('<tbody id="posts-body"><tr><th colspan="2">Posts</th><td>Ready</td></tr><tr><th colspan="2">Pages</th><td>Review</td></tr></tbody>', $blocks);
        $t->contains('<tbody id="media-body"><tr><th>Images</th><td>7</td><td>Review</td></tr><tr><th>Video</th><td>2</td><td>Ready</td></tr></tbody>', $blocks);
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
        $t->same(0, $packet['coverage'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['coverage'][0]['sourceRowspanMode'] ?? null);
        $t->same(1, $packet['summary']['rowspanToEndCellCount'] ?? null);
        $t->same(true, $packet['summary']['hasRowspanToEndCells'] ?? null);
        $t->same(['body'], $packet['summary']['rowspanToEndSections'] ?? null);
        $t->same([
            ['row' => 0, 'column' => 0, 'covering' => 'anchor'],
            ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['row' => 2, 'column' => 0, 'covering' => 'rowspan'],
        ], $packet['coverage'][0]['occupiedSlots'] ?? null);
        $t->same(0, $packet['sections'][1]['rows'][0]['slots'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['sections'][1]['rows'][0]['slots'][0]['sourceRowspanMode'] ?? null);
        $t->same(0, $packet['sections'][1]['rows'][1]['slots'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['sections'][1]['rows'][1]['slots'][0]['sourceRowspanMode'] ?? null);
        $t->same(0, $packet['rowMatrix']['rows'][0]['cells'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['rowMatrix']['rows'][0]['cells'][0]['sourceRowspanMode'] ?? null);
        $t->same(0, $packet['flatGrid']['rows'][0]['cells'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['flatGrid']['rows'][0]['cells'][0]['sourceRowspanMode'] ?? null);
        $t->same(0, $packet['flatGrid']['rows'][1]['cells'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['flatGrid']['rows'][1]['cells'][0]['sourceRowspanMode'] ?? null);
        $t->same(0, $packet['flatGridFallbacks'][0]['slots'][0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $packet['flatGridFallbacks'][0]['slots'][0]['sourceRowspanMode'] ?? null);
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
        $t->same(0, $markdownRowspanDowngrades[0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $markdownRowspanDowngrades[0]['sourceRowspanMode'] ?? null);
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
        $t->same(0, $rstRowspanRequirements[0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $rstRowspanRequirements[0]['sourceRowspanMode'] ?? null);
        $t->same('grid-table', $rstRowspanRequirements[0]['requiredFeature'] ?? null);
        $latexRowspanRequirements = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'latex'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'latex-multirow-required'
        ));
        $t->same(1, count($latexRowspanRequirements));
        $t->same(true, $latexRowspanRequirements[0]['rowspanToEnd'] ?? null);
        $t->same(0, $latexRowspanRequirements[0]['sourceRowspanAttribute'] ?? null);
        $t->same('to-section-end', $latexRowspanRequirements[0]['sourceRowspanMode'] ?? null);
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
    'normalizes html column background metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="column-background-grid" data-source="html-reader">
<caption>Column background review</caption>
<colgroup data-source="legacy-doc" bgcolor="#FFF4CC" style="background-color: #e6ffed; background-image:url(javascript:alert(1))">
<col span="2" width="25%" data-origin="metric-columns" />
<col width="50%" bgcolor="yellow" style="background-color: rgb(230, 255, 237); background-image:url(javascript:alert(1))" data-origin="state-column" />
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

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $backgrounds = is_array($packet['columnBackgrounds'] ?? null) ? $packet['columnBackgrounds'] : [];
        $t->same(2, count($backgrounds));
        $t->same(true, $packet['summary']['hasColumnBackgrounds'] ?? null);
        $t->same(2, $packet['summary']['columnBackgroundCount'] ?? null);
        $t->same([0, 1, 2], $packet['summary']['columnBackgroundColumns'] ?? null);
        $t->same(['#e6ffed', 'rgb(230, 255, 237)'], $packet['summary']['columnBackgroundColors'] ?? null);
        $t->same(['style'], $packet['summary']['columnBackgroundSources'] ?? null);
        $t->same(['colgroup', 'col'], $packet['summary']['columnBackgroundSourceElements'] ?? null);

        $t->same([0, 1], $backgrounds[0]['columns'] ?? null);
        $t->same(0, $backgrounds[0]['startColumn'] ?? null);
        $t->same(2, $backgrounds[0]['endColumn'] ?? null);
        $t->same('colgroup', $backgrounds[0]['sourceElement'] ?? null);
        $t->same('#e6ffed', $backgrounds[0]['backgroundColor'] ?? null);
        $t->same('#fff4cc', $backgrounds[0]['legacyBackgroundColor'] ?? null);
        $t->same('#e6ffed', $backgrounds[0]['cssBackgroundColor'] ?? null);
        $t->same(['background-color' => '#e6ffed', 'bgcolor' => '#fff4cc'], $backgrounds[0]['attributes'] ?? null);
        $t->same('#FFF4CC', $backgrounds[0]['sourceAttributes']['htmlAttributes']['bgcolor'] ?? null);
        $t->same('background-color: #e6ffed; background-image:url(javascript:alert(1))', $backgrounds[0]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same([2], $backgrounds[1]['columns'] ?? null);
        $t->same(2, $backgrounds[1]['startColumn'] ?? null);
        $t->same(3, $backgrounds[1]['endColumn'] ?? null);
        $t->same('col', $backgrounds[1]['sourceElement'] ?? null);
        $t->same(1, $backgrounds[1]['colIndex'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[1]['backgroundColor'] ?? null);
        $t->same('yellow', $backgrounds[1]['legacyBackgroundColor'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[1]['cssBackgroundColor'] ?? null);
        $t->same('state-column', $backgrounds[1]['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);

        $markdownDiagnostics = array_values(array_filter(
            $packet['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'column-background'
        ));
        $t->same(1, count($markdownDiagnostics));
        $t->same('markdown-column-background-require-raw-html', $markdownDiagnostics[0]['code'] ?? null);
        $t->same('raw-html-column-background', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['columnBackgroundCount'] ?? null);
        $t->same([0, 1, 2], $markdownDiagnostics[0]['columns'] ?? null);
        $t->same(['#e6ffed', 'rgb(230, 255, 237)'], $markdownDiagnostics[0]['colors'] ?? null);
        $t->same(['colgroup', 'col'], $markdownDiagnostics[0]['sourceElements'] ?? null);

        $t->contains('<colgroup bgcolor="#FFF4CC" data-source="legacy-doc"><col data-origin="metric-columns" style="width:25%; background-color:#e6ffed"/><col data-origin="metric-columns" style="width:25%; background-color:#e6ffed"/><col bgcolor="yellow" data-origin="state-column" style="width:50%; background-color:rgb(230, 255, 237)"/></colgroup>', $blocks);
        $t->true(!str_contains($blocks, 'javascript:'), 'Unsafe column background URLs must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'normalizes html column border presentation for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="column-border-grid" data-source="html-reader">
<caption>Column border review</caption>
<colgroup data-source="legacy-doc" style="border-color: #336699; border-style: dashed; border-width: 2px; border-image:url(javascript:alert(1))">
<col span="2" width="25%" data-origin="metric-columns" />
<col width="50%" style="border-right: thick double green; border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123; border-image:url(javascript:alert(1))" data-origin="state-column" />
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

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $borders = is_array($packet['columnBorderPresentations'] ?? null) ? $packet['columnBorderPresentations'] : [];
        $t->same(2, count($borders));
        $t->same(true, $packet['summary']['hasColumnBorderPresentations'] ?? null);
        $t->same(2, $packet['summary']['columnBorderPresentationCount'] ?? null);
        $t->same([0, 1, 2], $packet['summary']['columnBorderPresentationColumns'] ?? null);
        $t->same(['#336699'], $packet['summary']['columnBorderPresentationColors'] ?? null);
        $t->same(['dashed'], $packet['summary']['columnBorderPresentationStyles'] ?? null);
        $t->same(['2px'], $packet['summary']['columnBorderPresentationWidths'] ?? null);
        $t->same(['colgroup', 'col'], $packet['summary']['columnBorderPresentationSourceElements'] ?? null);
        $t->same(2, $packet['summary']['columnBorderPresentationEdgeCount'] ?? null);
        $t->same(['right', 'bottom'], $packet['summary']['columnBorderPresentationEdges'] ?? null);
        $t->same(['green', '#112233'], $packet['summary']['columnBorderPresentationEdgeColors'] ?? null);
        $t->same(['double', 'dotted'], $packet['summary']['columnBorderPresentationEdgeStyles'] ?? null);
        $t->same(['thick', '3px'], $packet['summary']['columnBorderPresentationEdgeWidths'] ?? null);

        $t->same([0, 1], $borders[0]['columns'] ?? null);
        $t->same('colgroup', $borders[0]['sourceElement'] ?? null);
        $t->same('#336699', $borders[0]['borderColor'] ?? null);
        $t->same('dashed', $borders[0]['borderStyle'] ?? null);
        $t->same('2px', $borders[0]['borderWidth'] ?? null);
        $t->same(['border-color' => '#336699', 'border-style' => 'dashed', 'border-width' => '2px'], $borders[0]['attributes'] ?? null);
        $t->same('border-color: #336699; border-style: dashed; border-width: 2px; border-image:url(javascript:alert(1))', $borders[0]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same([2], $borders[1]['columns'] ?? null);
        $t->same('col', $borders[1]['sourceElement'] ?? null);
        $t->same(1, $borders[1]['colIndex'] ?? null);
        $t->same(2, count($borders[1]['borderEdges'] ?? []));
        $t->same('right', $borders[1]['borderEdges'][0]['edge'] ?? null);
        $t->same('thick double green', $borders[1]['borderEdges'][0]['value'] ?? null);
        $t->same('bottom', $borders[1]['borderEdges'][1]['edge'] ?? null);
        $t->same('#112233', $borders[1]['borderEdges'][1]['borderColor'] ?? null);
        $t->same('state-column', $borders[1]['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);

        $markdownDiagnostics = array_values(array_filter(
            $packet['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'column-border-presentation'
        ));
        $t->same(1, count($markdownDiagnostics));
        $t->same('markdown-column-border-presentation-require-raw-html', $markdownDiagnostics[0]['code'] ?? null);
        $t->same('raw-html-column-border-presentation', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['columnBorderPresentationCount'] ?? null);
        $t->same([0, 1, 2], $markdownDiagnostics[0]['columns'] ?? null);
        $t->same(['colgroup', 'col'], $markdownDiagnostics[0]['sourceElements'] ?? null);
        $t->same(['right', 'bottom'], $markdownDiagnostics[0]['edges'] ?? null);

        $t->contains('<colgroup data-source="legacy-doc"><col data-origin="metric-columns" style="width:25%; border-color:#336699; border-style:dashed; border-width:2px"/><col data-origin="metric-columns" style="width:25%; border-color:#336699; border-style:dashed; border-width:2px"/><col data-origin="state-column" style="width:50%; border-right:thick double green; border-bottom-width:3px; border-bottom-style:dotted; border-bottom-color:#112233"/></colgroup>', $blocks);
        $t->true(!str_contains($blocks, 'javascript:'), 'Unsafe column border URLs must not render');
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
    'treats html scope auto as computed header scope in geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="auto-scope-grid" data-source="html-reader">
<caption>Auto scope review</caption>
<thead>
<tr><th id="auto-document" scope="auto">Document</th><th id="auto-state" scope="auto">State</th></tr>
</thead>
<tbody>
<tr><th id="auto-posts" scope="auto">Posts</th><td>Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $associations = TableGeometry::headerAssociations($table, 'Auto Scope Grid');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(false, $packet['summary']['hasInvalidSourceScopes'] ?? null);
        $t->same(0, $packet['summary']['invalidSourceScopeCount'] ?? null);
        $t->same('auto', $packet['coverage'][0]['sourceAttributes']['htmlAttributes']['scope'] ?? null);
        $t->same('auto', $packet['coverage'][2]['sourceAttributes']['htmlAttributes']['scope'] ?? null);

        $t->same(3, $associations['summary']['headerCellCount'] ?? null);
        $t->same(['col', 'row'], $associations['summary']['headerScopes'] ?? null);
        $t->same('col', $associations['headerCells'][0]['scope'] ?? null);
        $t->same('col', $associations['headerCells'][1]['scope'] ?? null);
        $t->same('row', $associations['headerCells'][2]['scope'] ?? null);
        $t->true(!isset($associations['headerCells'][0]['sourceScope']), 'scope=auto must not override computed column scope');
        $t->true(!isset($associations['headerCells'][2]['sourceScope']), 'scope=auto must not override computed row scope');
        $t->same(['auto-state', 'auto-posts'], $associations['dataCells'][0]['headers'] ?? null);
        $t->same(['auto-state', 'auto-posts'], $packet['accessibility']['body:0:1:1']['headers'] ?? null);
        $t->same('col', $packet['accessibility']['head:0:0:0']['scope'] ?? null);
        $t->same('row', $packet['accessibility']['body:0:0:0']['scope'] ?? null);
        $t->same($associations, $packet['headerAssociations'] ?? null);

        $t->contains('<th scope="col" id="auto-document">Document</th><th scope="col" id="auto-state">State</th>', $blocks);
        $t->contains('<th scope="row" id="auto-posts">Posts</th><td headers="auto-state auto-posts">Ready</td>', $blocks);
        $t->true(!str_contains($blocks, 'scope="auto"'), 'scope=auto should be computed for WordPress output instead of preserved literally');
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($associations, JSON_THROW_ON_ERROR);
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
    'diagnoses malformed html colgroup span values while preserving normalized column geometry' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="malformed-column-span-grid" data-source="html-reader">
<caption>Malformed column span review</caption>
<colgroup span="0" style="width: 25%; text-align: right" data-origin="group-zero"></colgroup>
<colgroup data-origin="colgroup-explicit">
<col span="two" width="50%" align="center" data-origin="col-two" />
<col span="-2" style="width: 25%; text-align: left" data-origin="col-negative" />
</colgroup>
<tbody>
<tr><td>Posts</td><td>Ready</td><td>Review</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0] ?? null;
        $t->same('table', $table?->type ?? null);
        $table = $table instanceof AstNode ? $table : new AstNode('table');
        $diagnostics = TableGeometry::diagnostics($table);
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, TableGeometry::columnCount($table));
        $t->same(['right', 'center', 'left'], $table->attr('alignments'));
        $t->same([0.25, 0.5, 0.25], $table->attr('widths'));
        $columnSources = is_array($table->attr('columnSources')) ? $table->attr('columnSources') : [];
        $t->same(3, count($columnSources));
        $t->same(['colgroup', 'col', 'col'], array_map(static fn (array $source): string => (string) ($source['kind'] ?? ''), $columnSources));
        $t->same([1, 1, 1], array_map(static fn (array $source): int => (int) ($source['sourceSpan'] ?? 0), $columnSources));
        $t->same('0', $columnSources[0]['colgroupAttributes']['htmlAttributes']['span'] ?? null);
        $t->same('two', $columnSources[1]['colAttributes']['htmlAttributes']['span'] ?? null);
        $t->same('-2', $columnSources[2]['colAttributes']['htmlAttributes']['span'] ?? null);

        $t->same(3, count($diagnostics));
        $t->same(['html-column-span-normalized'], array_values(array_unique(array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $diagnostics))));
        $t->same(['colgroup', 'col', 'col'], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['sourceElement'] ?? ''), $diagnostics));
        $t->same(['0', 'two', '-2'], array_map(static fn (array $diagnostic): mixed => $diagnostic['rawValue'] ?? null, $diagnostics));
        $t->same([1, 1, 1], array_map(static fn (array $diagnostic): int => (int) ($diagnostic['normalizedSpan'] ?? 0), $diagnostics));
        $t->same([0, 1, 2], array_map(static fn (array $diagnostic): int => (int) ($diagnostic['column'] ?? -1), $diagnostics));
        $t->same([0, 1, 1], array_map(static fn (array $diagnostic): int => (int) ($diagnostic['colgroupIndex'] ?? -1), $diagnostics));
        $t->same([null, 0, 1], array_map(static fn (array $diagnostic): mixed => $diagnostic['colIndex'] ?? null, $diagnostics));

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same(['html-column-span-normalized'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(3, $packet['summary']['diagnosticCount'] ?? null);
        $t->same(true, $packet['summary']['hasNormalizedColumnSpans'] ?? null);
        $t->same(3, $packet['summary']['normalizedColumnSpanCount'] ?? null);
        $t->same(['colgroup', 'col'], $packet['summary']['normalizedColumnSpanSourceElements'] ?? null);
        $t->same(3, count($packet['columnGroups'] ?? []));
        $t->same('0', $packet['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['span'] ?? null);
        $t->same('two', $packet['columns'][1]['source']['colAttributes']['htmlAttributes']['span'] ?? null);
        $t->same('-2', $packet['columns'][2]['source']['colAttributes']['htmlAttributes']['span'] ?? null);
        $t->same($diagnostics, $packet['diagnostics'] ?? null);

        $t->contains('<colgroup data-origin="group-zero"><col style="width:25%"/></colgroup><colgroup data-origin="colgroup-explicit"><col data-origin="col-two" style="width:50%"/><col data-origin="col-negative" style="width:25%"/></colgroup>', $blocks);
        $t->contains('<tbody><tr><td style="text-align:right">Posts</td><td style="text-align:center">Ready</td><td style="text-align:left">Review</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, 'span="0"'), 'Malformed colgroup span must not leak into WordPress output');
        $t->true(!str_contains($blocks, 'span="two"'), 'Malformed col span text must not leak into WordPress output');
        $t->true(!str_contains($blocks, 'span="-2"'), 'Malformed negative col span must not leak into WordPress output');
        json_encode($packet, JSON_THROW_ON_ERROR);
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
        $t->same('docbook', $docbook->attr('sourceFormat'));
        $t->same(['top'], array_values(array_unique(array_map(static fn (array $coverage): string => (string) ($coverage['verticalAlignment'] ?? ''), $docbookPacket['coverage'] ?? []))));
        $t->same('top', $docbookPacket['sections'][1]['rows'][0]['slots'][0]['verticalAlignment'] ?? null);
        $t->contains('<td colspan="4" style="text-align:center"><strong>Migration Batch 42</strong></td>', $docbookBlocks);
        $t->contains('<td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">ready</td><td>editorial</td>', $docbookBlocks);
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
    'carries html table directionality into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="bidi-source-grid" data-source="html-reader" dir="rtl">
<caption>Direction source review</caption>
<thead dir="ltr">
<tr dir="rtl"><th>Scope</th><th dir="auto">State</th></tr>
</thead>
<tbody dir="rtl" data-section="body">
<tr><th>Posts</th><td>جاهز</td></tr>
<tr dir="ltr"><th>Media</th><td dir="auto">Review</td></tr>
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
        $directionDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-direction'
            ));
            $directionDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same('rtl', $table->attr('htmlAttributes')['dir'] ?? null);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('rtl', $packet['directionality']['table']['direction'] ?? null);
        $t->same('html-table-dir', $packet['directionality']['table']['source'] ?? null);
        $t->same(['ltr', 'rtl'], array_map(static fn (array $section): string => (string) ($section['direction'] ?? ''), $packet['directionality']['sections'] ?? []));
        $t->same(['head', 'body'], array_map(static fn (array $section): string => (string) ($section['section'] ?? ''), $packet['directionality']['sections'] ?? []));
        $t->same(['rtl', 'ltr'], array_map(static fn (array $row): string => (string) ($row['direction'] ?? ''), $packet['directionality']['rows'] ?? []));
        $t->same(['head', 'body'], array_map(static fn (array $row): string => (string) ($row['section'] ?? ''), $packet['directionality']['rows'] ?? []));
        $t->same(['rtl', 'auto', 'rtl', 'rtl', 'ltr', 'auto'], array_map(static fn (array $coverage): string => (string) ($coverage['direction'] ?? ''), $packet['coverage'] ?? []));
        $t->same(['row', 'cell', 'section', 'section', 'row', 'cell'], array_map(static fn (array $coverage): string => (string) ($coverage['directionSource'] ?? ''), $packet['coverage'] ?? []));
        $t->same('auto', $packet['directionality']['cells'][1]['direction'] ?? null);
        $t->same('cell', $packet['directionality']['cells'][1]['source'] ?? null);
        $t->same('State', $packet['directionality']['cells'][1]['text'] ?? null);
        $t->same('rtl', $packet['directionality']['cells'][3]['direction'] ?? null);
        $t->same('section', $packet['directionality']['cells'][3]['source'] ?? null);
        $t->same('ltr', $packet['directionality']['cells'][4]['direction'] ?? null);
        $t->same('row', $packet['directionality']['cells'][4]['source'] ?? null);
        $t->same(11, $packet['directionality']['summary']['directionRecordCount'] ?? null);
        $t->same(6, $packet['directionality']['summary']['directionalCellCount'] ?? null);
        $t->same(['auto', 'ltr', 'rtl'], $packet['directionality']['summary']['directions'] ?? null);
        $t->same(true, $packet['summary']['hasTableDirectionality'] ?? null);
        $t->same(6, $packet['summary']['directionalCellCount'] ?? null);
        $t->same(['auto', 'ltr', 'rtl'], $packet['summary']['tableDirections'] ?? null);

        $t->same([
            'markdown-table-direction-requires-raw-html',
            'asciidoc-table-direction-review-required',
            'latex-table-direction-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $directionDiagnostics['markdown'],
            $directionDiagnostics['asciidoc'],
            $directionDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-direction', $directionDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same(['auto', 'ltr', 'rtl'], $directionDiagnostics['markdown']['directions'] ?? null);
        $t->same(6, $directionDiagnostics['markdown']['directionalCellCount'] ?? null);

        $t->contains('<table id="bidi-source-grid" data-source="html-reader" dir="rtl">', $blocks);
        $t->contains('<thead dir="ltr"><tr dir="rtl"><th>Scope</th><th dir="auto">State</th></tr></thead>', $blocks);
        $t->contains('<tbody dir="rtl" data-section="body"><tr><th>Posts</th><td>جاهز</td></tr><tr dir="ltr"><th>Media</th><td dir="auto">Review</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'carries html table language and translation metadata into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="localized-source-grid" data-source="html-reader" lang="ar-eg" xml:lang="ar-EG" translate="no">
<caption lang="en">Localized source review</caption>
<thead lang="en">
<tr lang="fr"><th lang="fr">Portée</th><th translate="yes">State</th></tr>
</thead>
<tbody lang="ar" translate="no" data-section="body">
<tr><th>منشورات</th><td>جاهز</td></tr>
<tr lang="en" translate="yes"><th>Media</th><td lang="en-US" translate="no">Review</td></tr>
</tbody>
</table>
<table id="invalid-localized-grid" lang="bad lang" translate="maybe">
<tbody><tr><td>Invalid</td></tr></tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $invalidTable = $tables[1] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $localizationDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-localization'
            ));
            $localizationDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('ar-eg', $table->attr('htmlAttributes')['lang'] ?? null);
        $t->same('no', $table->attr('htmlAttributes')['translate'] ?? null);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('ar-EG', $packet['localization']['table']['language'] ?? null);
        $t->same('lang', $packet['localization']['table']['attribute'] ?? null);
        $t->same('no', $packet['localization']['table']['translate'] ?? null);
        $t->same('translate', $packet['localization']['table']['translateAttribute'] ?? null);
        $t->same(['en', 'ar'], array_map(static fn (array $section): string => (string) ($section['language'] ?? ''), $packet['localization']['sections'] ?? []));
        $t->same(['head', 'body'], array_map(static fn (array $section): string => (string) ($section['section'] ?? ''), $packet['localization']['sections'] ?? []));
        $t->same(['fr', 'en'], array_map(static fn (array $row): string => (string) ($row['language'] ?? ''), $packet['localization']['rows'] ?? []));
        $t->same(['head', 'body'], array_map(static fn (array $row): string => (string) ($row['section'] ?? ''), $packet['localization']['rows'] ?? []));
        $t->same(['fr', 'fr', 'ar', 'ar', 'en', 'en-US'], array_map(static fn (array $cell): string => (string) ($cell['language'] ?? ''), $packet['localization']['cells'] ?? []));
        $t->same(['cell', 'row', 'section', 'section', 'row', 'cell'], array_map(static fn (array $cell): string => (string) ($cell['languageSource'] ?? ''), $packet['localization']['cells'] ?? []));
        $t->same(['no', 'yes'], $packet['localization']['summary']['translateStates'] ?? null);
        $t->same(['ar', 'ar-EG', 'en', 'en-US', 'fr'], $packet['localization']['summary']['languages'] ?? null);
        $t->same(11, $packet['localization']['summary']['localizationRecordCount'] ?? null);
        $t->same(6, $packet['localization']['summary']['localizedCellCount'] ?? null);
        $t->same(2, $packet['localization']['summary']['explicitCellLanguageCount'] ?? null);
        $t->same(4, $packet['localization']['summary']['inheritedCellLanguageCount'] ?? null);
        $t->same(6, $packet['localization']['summary']['translatedCellCount'] ?? null);
        $t->same(true, $packet['summary']['hasTableLocalization'] ?? null);
        $t->same(6, $packet['summary']['localizedCellCount'] ?? null);
        $t->same(['ar', 'ar-EG', 'en', 'en-US', 'fr'], $packet['summary']['tableLanguages'] ?? null);
        $t->same(['no', 'yes'], $packet['summary']['tableTranslateStates'] ?? null);
        $t->same('fr', $packet['coverage'][0]['language'] ?? null);
        $t->same('cell', $packet['coverage'][0]['languageSource'] ?? null);
        $t->same('yes', $packet['coverage'][1]['translate'] ?? null);
        $t->same('cell', $packet['coverage'][1]['translateSource'] ?? null);
        $t->same('ar', $packet['coverage'][2]['language'] ?? null);
        $t->same('section', $packet['coverage'][2]['languageSource'] ?? null);
        $t->same('en-US', $packet['coverage'][5]['language'] ?? null);
        $t->same('cell', $packet['coverage'][5]['languageSource'] ?? null);
        $t->same('no', $packet['coverage'][5]['translate'] ?? null);
        $t->same('cell', $packet['coverage'][5]['translateSource'] ?? null);

        $t->same([
            'markdown-table-localization-requires-raw-html',
            'asciidoc-table-localization-review-required',
            'latex-table-localization-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $localizationDiagnostics['markdown'],
            $localizationDiagnostics['asciidoc'],
            $localizationDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-localization', $localizationDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same(['ar', 'ar-EG', 'en', 'en-US', 'fr'], $localizationDiagnostics['markdown']['languages'] ?? null);
        $t->same(['no', 'yes'], $localizationDiagnostics['markdown']['translateStates'] ?? null);
        $t->same(6, $localizationDiagnostics['markdown']['localizedCellCount'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->same(false, $invalidPacket['summary']['hasTableLocalization'] ?? null);
        $t->same([], $invalidPacket['localization']['summary']['languages'] ?? null);

        $t->contains('<table id="localized-source-grid" data-source="html-reader" lang="ar-EG" xml:lang="ar-EG" translate="no">', $blocks);
        $t->contains('<figcaption class="wp-element-caption" lang="en">Localized source review</figcaption>', $blocks);
        $t->contains('<thead lang="en"><tr lang="fr"><th lang="fr">Portée</th><th translate="yes">State</th></tr></thead>', $blocks);
        $t->contains('<tbody lang="ar" translate="no" data-section="body"><tr><th>منشورات</th><td>جاهز</td></tr><tr lang="en" translate="yes"><th>Media</th><td lang="en-US" translate="no">Review</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, 'lang="bad lang"'), 'Invalid table language should not render');
        $t->true(!str_contains($blocks, 'translate="maybe"'), 'Invalid translate state should not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes legacy html table cell spacing attributes for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="legacy-spacing-grid" data-source="html-reader" cellpadding="6" cellspacing="2">
<caption>Legacy spacing review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="legacy-spacing-invalid" cellpadding="6px" cellspacing="-1">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $invalidTable = $tables[1] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $spacingDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-spacing'
            ));
            $spacingDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'cellpadding' => '6',
            'cellspacing' => '2',
        ], $packet['tableSpacing']['attributes'] ?? null);
        $t->same('6', $packet['tableSpacing']['cellPadding'] ?? null);
        $t->same('2', $packet['tableSpacing']['cellSpacing'] ?? null);
        $t->same('6', $packet['tableSpacing']['sourceAttributes']['htmlAttributes']['cellpadding'] ?? null);
        $t->same('2', $packet['tableSpacing']['sourceAttributes']['htmlAttributes']['cellspacing'] ?? null);
        $t->same(true, $packet['summary']['hasTableSpacing'] ?? null);
        $t->same('6', $packet['summary']['tableCellPadding'] ?? null);
        $t->same('2', $packet['summary']['tableCellSpacing'] ?? null);
        $t->same(2, $packet['summary']['tableSpacingAttributeCount'] ?? null);
        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableSpacing', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableSpacing'] ?? null);

        $t->same([
            'markdown-table-spacing-requires-raw-html',
            'asciidoc-table-spacing-review-required',
            'latex-table-spacing-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $spacingDiagnostics['markdown'],
            $spacingDiagnostics['asciidoc'],
            $spacingDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-spacing', $spacingDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-spacing', $spacingDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'cellpadding' => '6',
            'cellspacing' => '2',
        ], $spacingDiagnostics['markdown']['attributes'] ?? null);
        $t->same('6', $spacingDiagnostics['markdown']['cellPadding'] ?? null);
        $t->same('2', $spacingDiagnostics['markdown']['cellSpacing'] ?? null);

        $t->contains('<table id="legacy-spacing-grid" data-source="html-reader" cellpadding="6" cellspacing="2">', $blocks);
        $t->true(!str_contains($blocks, 'cellpadding="6px"'));
        $t->true(!str_contains($blocks, 'cellspacing="-1"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table background attributes for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="background-color-grid" data-source="html-reader" bgcolor="#FFF4CC" style="background-color: #e6ffed; background-image:url(javascript:alert(1))">
<caption>Background color review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="background-rgb-grid" style="background-color: rgb(12, 34, 56)">
<tbody>
<tr><td>RGB</td><td>Preserved</td></tr>
</tbody>
</table>
<table id="background-invalid" bgcolor="expression(alert(1))" style="background-color: calc(1px); background-image:url(javascript:alert(1))">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $rgbTable = $tables[1] ?? null;
        $invalidTable = $tables[2] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($rgbTable instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$rgbTable instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $rgbPacket = $rgbTable->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $backgroundDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-background'
            ));
            $backgroundDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'background-color' => '#e6ffed',
            'bgcolor' => '#fff4cc',
        ], $packet['tableBackground']['attributes'] ?? null);
        $t->same('#e6ffed', $packet['tableBackground']['backgroundColor'] ?? null);
        $t->same('style', $packet['tableBackground']['backgroundColorSource'] ?? null);
        $t->same('#fff4cc', $packet['tableBackground']['legacyBackgroundColor'] ?? null);
        $t->same('#e6ffed', $packet['tableBackground']['cssBackgroundColor'] ?? null);
        $t->same('#FFF4CC', $packet['tableBackground']['sourceAttributes']['htmlAttributes']['bgcolor'] ?? null);
        $t->same(true, $packet['summary']['hasTableBackground'] ?? null);
        $t->same('#e6ffed', $packet['summary']['tableBackgroundColor'] ?? null);
        $t->same('style', $packet['summary']['tableBackgroundColorSource'] ?? null);
        $t->same(2, $packet['summary']['tableBackgroundAttributeCount'] ?? null);

        $t->same(true, is_array($rgbPacket));
        $rgbPacket = is_array($rgbPacket) ? $rgbPacket : [];
        $t->same('rgb(12, 34, 56)', $rgbPacket['tableBackground']['backgroundColor'] ?? null);
        $t->same('style', $rgbPacket['tableBackground']['backgroundColorSource'] ?? null);
        $t->same([
            'background-color' => 'rgb(12, 34, 56)',
        ], $rgbPacket['tableBackground']['attributes'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableBackground', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableBackground'] ?? null);

        $t->same([
            'markdown-table-background-requires-raw-html',
            'asciidoc-table-background-review-required',
            'latex-table-background-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $backgroundDiagnostics['markdown'],
            $backgroundDiagnostics['asciidoc'],
            $backgroundDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-background', $backgroundDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-background', $backgroundDiagnostics['markdown']['source'] ?? null);
        $t->same('#e6ffed', $backgroundDiagnostics['markdown']['backgroundColor'] ?? null);
        $t->same('style', $backgroundDiagnostics['markdown']['backgroundColorSource'] ?? null);
        $t->same(2, $backgroundDiagnostics['markdown']['attributeCount'] ?? null);

        $t->contains('<table id="background-color-grid" data-source="html-reader" bgcolor="#fff4cc" style="background-color:#e6ffed">', $blocks);
        $t->contains('<table id="background-rgb-grid" style="background-color:rgb(12, 34, 56)">', $blocks);
        $t->true(!str_contains($blocks, 'expression('));
        $t->true(!str_contains($blocks, 'background-image'));
        $t->true(!str_contains($blocks, 'calc(1px)'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($rgbPacket, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table width layout metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="layout-width-grid" data-source="html-reader" width="80%">
<caption>Layout width review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="layout-width-pixels" width="640">
<tbody>
<tr><td>Pixel width</td><td>Preserved</td></tr>
</tbody>
</table>
<table id="layout-width-invalid" width="calc(100% - 1px)">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $pixelTable = $tables[1] ?? null;
        $invalidTable = $tables[2] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($pixelTable instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$pixelTable instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $pixelPacket = $pixelTable->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $layoutDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-layout-width'
            ));
            $layoutDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'width' => '80%',
        ], $packet['tableLayout']['attributes'] ?? null);
        $t->same('80%', $packet['tableLayout']['width'] ?? null);
        $t->same('percent', $packet['tableLayout']['widthType'] ?? null);
        $t->same(80.0, $packet['tableLayout']['widthValue'] ?? null);
        $t->same('80%', $packet['tableLayout']['sourceAttributes']['htmlAttributes']['width'] ?? null);
        $t->same(true, $packet['summary']['hasTableLayout'] ?? null);
        $t->same('80%', $packet['summary']['tableWidth'] ?? null);
        $t->same('percent', $packet['summary']['tableWidthType'] ?? null);
        $t->same(1, $packet['summary']['tableLayoutAttributeCount'] ?? null);

        $t->same(true, is_array($pixelPacket));
        $pixelPacket = is_array($pixelPacket) ? $pixelPacket : [];
        $t->same('640', $pixelPacket['tableLayout']['width'] ?? null);
        $t->same('pixels', $pixelPacket['tableLayout']['widthType'] ?? null);
        $t->same(640.0, $pixelPacket['tableLayout']['widthValue'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableLayout', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableLayout'] ?? null);

        $t->same([
            'markdown-table-width-requires-raw-html',
            'asciidoc-table-width-review-required',
            'latex-table-width-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $layoutDiagnostics['markdown'],
            $layoutDiagnostics['asciidoc'],
            $layoutDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-width', $layoutDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-layout', $layoutDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'width' => '80%',
        ], $layoutDiagnostics['markdown']['attributes'] ?? null);
        $t->same('80%', $layoutDiagnostics['markdown']['width'] ?? null);
        $t->same('percent', $layoutDiagnostics['markdown']['widthType'] ?? null);

        $t->contains('<table id="layout-width-grid" data-source="html-reader" width="80%">', $blocks);
        $t->contains('<table id="layout-width-pixels" width="640">', $blocks);
        $t->true(!str_contains($blocks, 'width="calc(100% - 1px)"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($pixelPacket, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table height layout metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="layout-height-grid" data-source="html-reader" height="320">
<caption>Layout height review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="layout-height-percent" height="75%">
<tbody>
<tr><td>Percent height</td><td>Preserved</td></tr>
</tbody>
</table>
<table id="layout-height-invalid" height="calc(100% - 1px)">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $percentTable = $tables[1] ?? null;
        $invalidTable = $tables[2] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($percentTable instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$percentTable instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $percentPacket = $percentTable->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $layoutDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-layout-height'
            ));
            $layoutDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'height' => '320',
        ], $packet['tableLayout']['attributes'] ?? null);
        $t->same('320', $packet['tableLayout']['height'] ?? null);
        $t->same('pixels', $packet['tableLayout']['heightType'] ?? null);
        $t->same(320.0, $packet['tableLayout']['heightValue'] ?? null);
        $t->same('320', $packet['tableLayout']['sourceAttributes']['htmlAttributes']['height'] ?? null);
        $t->same(true, $packet['summary']['hasTableLayout'] ?? null);
        $t->same('320', $packet['summary']['tableHeight'] ?? null);
        $t->same('pixels', $packet['summary']['tableHeightType'] ?? null);
        $t->same(1, $packet['summary']['tableLayoutAttributeCount'] ?? null);

        $t->same(true, is_array($percentPacket));
        $percentPacket = is_array($percentPacket) ? $percentPacket : [];
        $t->same('75%', $percentPacket['tableLayout']['height'] ?? null);
        $t->same('percent', $percentPacket['tableLayout']['heightType'] ?? null);
        $t->same(75.0, $percentPacket['tableLayout']['heightValue'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableLayout', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableLayout'] ?? null);

        $t->same([
            'markdown-table-height-requires-raw-html',
            'asciidoc-table-height-review-required',
            'latex-table-height-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $layoutDiagnostics['markdown'],
            $layoutDiagnostics['asciidoc'],
            $layoutDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-height', $layoutDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-layout', $layoutDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'height' => '320',
        ], $layoutDiagnostics['markdown']['attributes'] ?? null);
        $t->same('320', $layoutDiagnostics['markdown']['height'] ?? null);
        $t->same('pixels', $layoutDiagnostics['markdown']['heightType'] ?? null);

        $t->contains('<table id="layout-height-grid" data-source="html-reader" height="320">', $blocks);
        $t->contains('<table id="layout-height-percent" height="75%">', $blocks);
        $t->true(!str_contains($blocks, 'height="calc(100% - 1px)"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($percentPacket, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table css table layout metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="layout-mode-grid" data-source="html-reader" style="table-layout: fixed; background-image:url(javascript:alert(1))">
<caption>Table layout mode review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="layout-mode-auto" style="table-layout: auto">
<tbody>
<tr><td>Auto layout</td><td>Preserved</td></tr>
</tbody>
</table>
<table id="layout-mode-invalid" style="table-layout: inherit">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $autoTable = $tables[1] ?? null;
        $invalidTable = $tables[2] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($autoTable instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$autoTable instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $autoPacket = $autoTable->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $layoutDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-layout-mode'
            ));
            $layoutDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'table-layout' => 'fixed',
        ], $packet['tableLayout']['attributes'] ?? null);
        $t->same('fixed', $packet['tableLayout']['layoutMode'] ?? null);
        $t->same('style', $packet['tableLayout']['layoutModeSource'] ?? null);
        $t->same('table-layout: fixed; background-image:url(javascript:alert(1))', $packet['tableLayout']['sourceAttributes']['htmlAttributes']['style'] ?? null);
        $t->same(true, $packet['summary']['hasTableLayout'] ?? null);
        $t->same('fixed', $packet['summary']['tableLayoutMode'] ?? null);
        $t->same('style', $packet['summary']['tableLayoutModeSource'] ?? null);
        $t->same(1, $packet['summary']['tableLayoutAttributeCount'] ?? null);

        $t->same(true, is_array($autoPacket));
        $autoPacket = is_array($autoPacket) ? $autoPacket : [];
        $t->same('auto', $autoPacket['tableLayout']['layoutMode'] ?? null);
        $t->same('style', $autoPacket['tableLayout']['layoutModeSource'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableLayout', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableLayout'] ?? null);

        $t->same([
            'markdown-table-layout-mode-requires-raw-html',
            'asciidoc-table-layout-mode-review-required',
            'latex-table-layout-mode-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $layoutDiagnostics['markdown'],
            $layoutDiagnostics['asciidoc'],
            $layoutDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-layout-mode', $layoutDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-layout-style', $layoutDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'table-layout' => 'fixed',
        ], $layoutDiagnostics['markdown']['attributes'] ?? null);
        $t->same('fixed', $layoutDiagnostics['markdown']['layoutMode'] ?? null);
        $t->same('style', $layoutDiagnostics['markdown']['layoutModeSource'] ?? null);

        $t->contains('<table id="layout-mode-grid" data-source="html-reader" style="table-layout:fixed">', $blocks);
        $t->contains('<table id="layout-mode-auto" style="table-layout:auto">', $blocks);
        $t->true(!str_contains($blocks, 'table-layout:inherit'));
        $t->true(!str_contains($blocks, 'background-image'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($autoPacket, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table border collapse metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="border-collapse-grid" data-source="html-reader" style="border-collapse: collapse; background-image:url(javascript:alert(1))">
<caption>Border collapse review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="border-collapse-separate" style="border-collapse: separate">
<tbody>
<tr><td>Separate borders</td><td>Preserved</td></tr>
</tbody>
</table>
<table id="border-collapse-invalid" style="border-collapse: inherit; background-image:url(javascript:alert(1))">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $separateTable = $tables[1] ?? null;
        $invalidTable = $tables[2] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($separateTable instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$separateTable instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $separatePacket = $separateTable->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $collapseDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-border-collapse'
            ));
            $collapseDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'border-collapse' => 'collapse',
        ], $packet['tableBorderCollapse']['attributes'] ?? null);
        $t->same('collapse', $packet['tableBorderCollapse']['borderCollapse'] ?? null);
        $t->same('style', $packet['tableBorderCollapse']['borderCollapseSource'] ?? null);
        $t->same('border-collapse: collapse; background-image:url(javascript:alert(1))', $packet['tableBorderCollapse']['sourceAttributes']['htmlAttributes']['style'] ?? null);
        $t->same(true, $packet['summary']['hasTableBorderCollapse'] ?? null);
        $t->same('collapse', $packet['summary']['tableBorderCollapse'] ?? null);
        $t->same('style', $packet['summary']['tableBorderCollapseSource'] ?? null);
        $t->same(1, $packet['summary']['tableBorderCollapseAttributeCount'] ?? null);

        $t->same(true, is_array($separatePacket));
        $separatePacket = is_array($separatePacket) ? $separatePacket : [];
        $t->same('separate', $separatePacket['tableBorderCollapse']['borderCollapse'] ?? null);
        $t->same('style', $separatePacket['tableBorderCollapse']['borderCollapseSource'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableBorderCollapse', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableBorderCollapse'] ?? null);

        $t->same([
            'markdown-table-border-collapse-requires-raw-html',
            'asciidoc-table-border-collapse-review-required',
            'latex-table-border-collapse-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $collapseDiagnostics['markdown'],
            $collapseDiagnostics['asciidoc'],
            $collapseDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-border-collapse', $collapseDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-border-collapse', $collapseDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'border-collapse' => 'collapse',
        ], $collapseDiagnostics['markdown']['attributes'] ?? null);
        $t->same('collapse', $collapseDiagnostics['markdown']['borderCollapse'] ?? null);
        $t->same('style', $collapseDiagnostics['markdown']['borderCollapseSource'] ?? null);
        $t->same(1, $collapseDiagnostics['markdown']['attributeCount'] ?? null);

        $t->contains('<table id="border-collapse-grid" data-source="html-reader" style="border-collapse:collapse">', $blocks);
        $t->contains('<table id="border-collapse-separate" style="border-collapse:separate">', $blocks);
        $t->true(!str_contains($blocks, 'border-collapse:inherit'));
        $t->true(!str_contains($blocks, 'background-image'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($separatePacket, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table border presentation metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="border-presentation-grid" data-source="html-reader" style="border-color: #336699; border-style: dashed; border-width: 2px; border-image:url(javascript:alert(1))">
<caption>Border presentation review</caption>
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
        $diagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-border-presentation'
            ));
            $diagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('html-table-border-presentation', $packet['tableBorderPresentation']['source'] ?? null);
        $t->same([
            'border-color' => '#336699',
            'border-style' => 'dashed',
            'border-width' => '2px',
        ], $packet['tableBorderPresentation']['attributes'] ?? null);
        $t->same('#336699', $packet['tableBorderPresentation']['borderColor'] ?? null);
        $t->same('dashed', $packet['tableBorderPresentation']['borderStyle'] ?? null);
        $t->same('2px', $packet['tableBorderPresentation']['borderWidth'] ?? null);
        $t->same(true, $packet['summary']['hasTableBorderPresentation'] ?? null);
        $t->same('#336699', $packet['summary']['tableBorderColor'] ?? null);
        $t->same('dashed', $packet['summary']['tableBorderStyle'] ?? null);
        $t->same('2px', $packet['summary']['tableBorderWidth'] ?? null);
        $t->same(3, $packet['summary']['tableBorderPresentationAttributeCount'] ?? null);

        $t->same([
            'markdown-table-border-presentation-requires-raw-html',
            'asciidoc-table-border-presentation-review-required',
            'latex-table-border-presentation-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $diagnostics['markdown'],
            $diagnostics['asciidoc'],
            $diagnostics['latex'],
        ]));
        $t->same('raw-html-table-border-presentation', $diagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-border-presentation', $diagnostics['markdown']['source'] ?? null);
        $t->same([
            'border-color' => '#336699',
            'border-style' => 'dashed',
            'border-width' => '2px',
        ], $diagnostics['markdown']['attributes'] ?? null);
        $t->contains('<table id="border-presentation-grid" data-source="html-reader" style="border-color:#336699; border-style:dashed; border-width:2px">', $blocks);
        $t->true(!str_contains($blocks, 'border-image'), 'Unsafe border image style declarations must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes legacy html table bordercolor metadata for geometry handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="legacy-bordercolor-grid" data-source="html-reader" bordercolor="#936">
<caption>Legacy bordercolor review</caption>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="mixed-bordercolor-grid" bordercolor="#993366" style="border-color: #336699; border-style: solid">
<tbody>
<tr><td>Media</td><td>Review</td></tr>
</tbody>
</table>
<table id="invalid-bordercolor-grid" bordercolor="expression(alert(1))">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $legacyTable = $tables[0] ?? null;
        $mixedTable = $tables[1] ?? null;
        $invalidTable = $tables[2] ?? null;
        $t->true($legacyTable instanceof AstNode);
        $t->true($mixedTable instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$legacyTable instanceof AstNode || !$mixedTable instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $legacyPacket = $legacyTable->attr('tableGeometry');
        $mixedPacket = $mixedTable->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($legacyTable, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $diagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-border-presentation'
            ));
            $diagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($legacyPacket));
        $legacyPacket = is_array($legacyPacket) ? $legacyPacket : [];
        $t->same('html-table-border-presentation', $legacyPacket['tableBorderPresentation']['source'] ?? null);
        $t->same(['bordercolor' => '#993366'], $legacyPacket['tableBorderPresentation']['attributes'] ?? null);
        $t->same('#993366', $legacyPacket['tableBorderPresentation']['borderColor'] ?? null);
        $t->same('bordercolor', $legacyPacket['tableBorderPresentation']['borderColorSource'] ?? null);
        $t->same('#993366', $legacyPacket['tableBorderPresentation']['legacyBorderColor'] ?? null);
        $t->same('#936', $legacyPacket['tableBorderPresentation']['sourceAttributes']['htmlAttributes']['bordercolor'] ?? null);
        $t->same(true, $legacyPacket['summary']['hasTableBorderPresentation'] ?? null);
        $t->same('#993366', $legacyPacket['summary']['tableBorderColor'] ?? null);
        $t->same('bordercolor', $legacyPacket['summary']['tableBorderColorSource'] ?? null);
        $t->same('#993366', $legacyPacket['summary']['tableLegacyBorderColor'] ?? null);
        $t->same('', $legacyPacket['summary']['tableCssBorderColor'] ?? null);
        $t->same(1, $legacyPacket['summary']['tableBorderPresentationAttributeCount'] ?? null);

        $t->same(true, is_array($mixedPacket));
        $mixedPacket = is_array($mixedPacket) ? $mixedPacket : [];
        $t->same([
            'border-color' => '#336699',
            'border-style' => 'solid',
            'bordercolor' => '#993366',
        ], $mixedPacket['tableBorderPresentation']['attributes'] ?? null);
        $t->same('#336699', $mixedPacket['tableBorderPresentation']['borderColor'] ?? null);
        $t->same('style', $mixedPacket['tableBorderPresentation']['borderColorSource'] ?? null);
        $t->same('#993366', $mixedPacket['tableBorderPresentation']['legacyBorderColor'] ?? null);
        $t->same('#336699', $mixedPacket['tableBorderPresentation']['cssBorderColor'] ?? null);
        $t->same('solid', $mixedPacket['tableBorderPresentation']['borderStyle'] ?? null);
        $t->same(3, $mixedPacket['summary']['tableBorderPresentationAttributeCount'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableBorderPresentation', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableBorderPresentation'] ?? null);

        $t->same([
            'markdown-table-border-presentation-requires-raw-html',
            'asciidoc-table-border-presentation-review-required',
            'latex-table-border-presentation-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $diagnostics['markdown'],
            $diagnostics['asciidoc'],
            $diagnostics['latex'],
        ]));
        $t->same('raw-html-table-border-presentation', $diagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-border-presentation', $diagnostics['markdown']['source'] ?? null);
        $t->same('bordercolor', $diagnostics['markdown']['borderColorSource'] ?? null);
        $t->same('#993366', $diagnostics['markdown']['legacyBorderColor'] ?? null);
        $t->same(['bordercolor' => '#993366'], $diagnostics['markdown']['attributes'] ?? null);

        $t->contains('<table id="legacy-bordercolor-grid" data-source="html-reader">', $blocks);
        $t->contains('<table id="mixed-bordercolor-grid" style="border-color:#336699; border-style:solid">', $blocks);
        $t->true(!str_contains($blocks, 'bordercolor='));
        $t->true(!str_contains($blocks, 'expression('));
        json_encode($legacyPacket, JSON_THROW_ON_ERROR);
        json_encode($mixedPacket, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes legacy html table placement alignment for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="placement-align-grid" data-source="html-reader" align="center">
<caption>Placement alignment review</caption>
<thead>
<tr><th>Scope</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
</table>
<table id="placement-align-invalid" align="middle">
<tbody>
<tr><td>Invalid</td><td>Dropped</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $tables = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $table = $tables[0] ?? null;
        $invalidTable = $tables[1] ?? null;
        $t->true($table instanceof AstNode);
        $t->true($invalidTable instanceof AstNode);
        if (!$table instanceof AstNode || !$invalidTable instanceof AstNode) {
            return;
        }

        $packet = $table->attr('tableGeometry');
        $invalidPacket = $invalidTable->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $alignmentDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'table-alignment'
            ));
            $alignmentDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same([
            'align' => 'center',
        ], $packet['tableAlignment']['attributes'] ?? null);
        $t->same('center', $packet['tableAlignment']['alignment'] ?? null);
        $t->same('center', $packet['tableAlignment']['sourceAttributes']['htmlAttributes']['align'] ?? null);
        $t->same(true, $packet['summary']['hasTableAlignment'] ?? null);
        $t->same('center', $packet['summary']['tableAlignment'] ?? null);
        $t->same(1, $packet['summary']['tableAlignmentAttributeCount'] ?? null);

        $t->same(true, is_array($invalidPacket));
        $invalidPacket = is_array($invalidPacket) ? $invalidPacket : [];
        $t->true(!array_key_exists('tableAlignment', $invalidPacket));
        $t->same(false, $invalidPacket['summary']['hasTableAlignment'] ?? null);

        $t->same([
            'markdown-table-alignment-requires-raw-html',
            'asciidoc-table-alignment-review-required',
            'latex-table-alignment-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $alignmentDiagnostics['markdown'],
            $alignmentDiagnostics['asciidoc'],
            $alignmentDiagnostics['latex'],
        ]));
        $t->same('raw-html-table-alignment', $alignmentDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-alignment', $alignmentDiagnostics['markdown']['source'] ?? null);
        $t->same([
            'align' => 'center',
        ], $alignmentDiagnostics['markdown']['attributes'] ?? null);
        $t->same('center', $alignmentDiagnostics['markdown']['alignment'] ?? null);

        $t->contains('<table id="placement-align-grid" data-source="html-reader" align="center">', $blocks);
        $t->true(!str_contains($blocks, 'align="middle"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($invalidPacket, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'carries html table cell nowrap layout policy into geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="cell-nowrap-grid" data-source="html-reader">
<caption>Cell nowrap review</caption>
<thead>
<tr><th nowrap="nowrap">Source label</th><th>Status</th><th>Wrap</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td nowrap="nowrap">Long unbroken review value</td><td nowrap="false">Review wraps</td></tr>
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
        $nowrapDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-nowrap'
            ));
            $nowrapDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $nowraps = is_array($packet['cellNoWraps'] ?? null) ? $packet['cellNoWraps'] : [];
        $t->same(2, count($nowraps));
        $t->same(true, $packet['summary']['hasCellNoWraps'] ?? null);
        $t->same(2, $packet['summary']['cellNoWrapCount'] ?? null);
        $t->same([0, 1], $packet['summary']['cellNoWrapColumns'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['cellNoWrapSections'] ?? null);
        $t->same('Source label', $nowraps[0]['text'] ?? null);
        $t->same(true, $nowraps[0]['headerCell'] ?? null);
        $t->same(0, $nowraps[0]['column'] ?? null);
        $t->same(['nowrap' => 'nowrap'], $nowraps[0]['htmlAttributes'] ?? null);
        $t->same('Long unbroken review value', $nowraps[1]['text'] ?? null);
        $t->same(1, $nowraps[1]['column'] ?? null);
        $t->same(['nowrap' => 'nowrap'], $nowraps[1]['htmlAttributes'] ?? null);

        $t->same([
            'markdown-cell-nowrap-require-raw-html',
            'asciidoc-cell-nowrap-review-required',
            'latex-cell-nowrap-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $nowrapDiagnostics['markdown'],
            $nowrapDiagnostics['asciidoc'],
            $nowrapDiagnostics['latex'],
        ]));
        $t->same('raw-html-cell-nowrap', $nowrapDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-cell-nowrap', $nowrapDiagnostics['markdown']['source'] ?? null);
        $t->same(2, $nowrapDiagnostics['markdown']['cellCount'] ?? null);
        $t->same([0, 1], $nowrapDiagnostics['markdown']['columns'] ?? null);
        $t->same(['head', 'body'], $nowrapDiagnostics['markdown']['sections'] ?? null);

        $t->contains('<th nowrap="nowrap">Source label</th><th>Status</th><th>Wrap</th>', $blocks);
        $t->contains('<td>Posts</td><td nowrap="nowrap">Long unbroken review value</td><td>Review wraps</td>', $blocks);
        $t->true(!str_contains($blocks, 'nowrap="false"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table cell dimensions for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="cell-dimension-grid" data-source="html-reader">
<caption>Cell dimension review</caption>
<thead>
<tr><th width="120">Source</th><th style="width:40%; height:35%">Status</th><th>Wrap</th></tr>
</thead>
<tbody>
<tr><td height="32">Posts</td><td width="50%" height="44">Ready</td><td width="0">Ignored</td></tr>
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
        $dimensionDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-dimensions'
            ));
            $dimensionDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $dimensions = is_array($packet['cellDimensions'] ?? null) ? $packet['cellDimensions'] : [];
        $t->same(4, count($dimensions));
        $t->same(true, $packet['summary']['hasCellDimensions'] ?? null);
        $t->same(4, $packet['summary']['cellDimensionCount'] ?? null);
        $t->same([0, 1], $packet['summary']['cellDimensionColumns'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['cellDimensionSections'] ?? null);
        $t->same(['pixels', 'percent'], $packet['summary']['cellDimensionWidthTypes'] ?? null);
        $t->same(['percent', 'pixels'], $packet['summary']['cellDimensionHeightTypes'] ?? null);
        $t->same(['width', 'style'], $packet['summary']['cellDimensionWidthSources'] ?? null);
        $t->same(['style', 'height'], $packet['summary']['cellDimensionHeightSources'] ?? null);
        $t->same('Source', $dimensions[0]['text'] ?? null);
        $t->same(true, $dimensions[0]['headerCell'] ?? null);
        $t->same('120', $dimensions[0]['width'] ?? null);
        $t->same('pixels', $dimensions[0]['widthType'] ?? null);
        $t->same(120.0, $dimensions[0]['widthValue'] ?? null);
        $t->same('width', $dimensions[0]['widthSource'] ?? null);
        $t->same(['width' => '120'], $dimensions[0]['attributes'] ?? null);
        $t->same('Status', $dimensions[1]['text'] ?? null);
        $t->same('40%', $dimensions[1]['width'] ?? null);
        $t->same('35%', $dimensions[1]['height'] ?? null);
        $t->same('style', $dimensions[1]['widthSource'] ?? null);
        $t->same('style', $dimensions[1]['heightSource'] ?? null);
        $t->same(['height' => '35%', 'width' => '40%'], $dimensions[1]['attributes'] ?? null);
        $t->same('Posts', $dimensions[2]['text'] ?? null);
        $t->same('32', $dimensions[2]['height'] ?? null);
        $t->same('height', $dimensions[2]['heightSource'] ?? null);
        $t->same('Ready', $dimensions[3]['text'] ?? null);
        $t->same('50%', $dimensions[3]['width'] ?? null);
        $t->same('44', $dimensions[3]['height'] ?? null);
        $t->same(['height' => '44', 'width' => '50%'], $dimensions[3]['attributes'] ?? null);

        $t->same([
            'markdown-cell-dimensions-require-raw-html',
            'asciidoc-cell-dimensions-review-required',
            'latex-cell-dimensions-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $dimensionDiagnostics['markdown'],
            $dimensionDiagnostics['asciidoc'],
            $dimensionDiagnostics['latex'],
        ]));
        $t->same('raw-html-cell-dimensions', $dimensionDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-cell-dimensions', $dimensionDiagnostics['markdown']['source'] ?? null);
        $t->same(4, $dimensionDiagnostics['markdown']['cellCount'] ?? null);
        $t->same([0, 1], $dimensionDiagnostics['markdown']['columns'] ?? null);
        $t->same(['head', 'body'], $dimensionDiagnostics['markdown']['sections'] ?? null);
        $t->same(['pixels', 'percent'], $dimensionDiagnostics['markdown']['widthTypes'] ?? null);
        $t->same(['percent', 'pixels'], $dimensionDiagnostics['markdown']['heightTypes'] ?? null);

        $t->contains('<th width="120">Source</th><th style="width:40%; height:35%">Status</th><th>Wrap</th>', $blocks);
        $t->contains('<td height="32">Posts</td><td width="50%" height="44">Ready</td><td>Ignored</td>', $blocks);
        $t->true(!str_contains($blocks, 'width="0"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table cell background metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="cell-background-grid" data-source="html-reader">
<caption>Cell background review</caption>
<thead>
<tr><th bgcolor="#FFF4CC">Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td style="background-color: #e6ffed">Posts</td><td bgcolor="yellow" style="background-color: rgb(230, 255, 237)">Ready</td></tr>
<tr><td>Media</td><td>Review</td></tr>
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
        $backgroundDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-background'
            ));
            $backgroundDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $backgrounds = is_array($packet['cellBackgrounds'] ?? null) ? $packet['cellBackgrounds'] : [];
        $t->same(3, count($backgrounds));
        $t->same(true, $packet['summary']['hasCellBackgrounds'] ?? null);
        $t->same(3, $packet['summary']['cellBackgroundCount'] ?? null);
        $t->same([0, 1], $packet['summary']['cellBackgroundColumns'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['cellBackgroundSections'] ?? null);
        $t->same(['#fff4cc', '#e6ffed', 'rgb(230, 255, 237)'], $packet['summary']['cellBackgroundColors'] ?? null);
        $t->same(['bgcolor', 'style'], $packet['summary']['cellBackgroundSources'] ?? null);

        $t->same('Source', $backgrounds[0]['text'] ?? null);
        $t->same(true, $backgrounds[0]['headerCell'] ?? null);
        $t->same(0, $backgrounds[0]['column'] ?? null);
        $t->same('#fff4cc', $backgrounds[0]['backgroundColor'] ?? null);
        $t->same('bgcolor', $backgrounds[0]['backgroundColorSource'] ?? null);
        $t->same(['bgcolor' => '#fff4cc'], $backgrounds[0]['attributes'] ?? null);
        $t->same('#FFF4CC', $backgrounds[0]['sourceAttributes']['htmlAttributes']['bgcolor'] ?? null);

        $t->same('Posts', $backgrounds[1]['text'] ?? null);
        $t->same(0, $backgrounds[1]['column'] ?? null);
        $t->same('#e6ffed', $backgrounds[1]['backgroundColor'] ?? null);
        $t->same('style', $backgrounds[1]['backgroundColorSource'] ?? null);
        $t->same(['background-color' => '#e6ffed'], $backgrounds[1]['attributes'] ?? null);
        $t->same('background-color: #e6ffed', $backgrounds[1]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same('Ready', $backgrounds[2]['text'] ?? null);
        $t->same(1, $backgrounds[2]['column'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[2]['backgroundColor'] ?? null);
        $t->same('style', $backgrounds[2]['backgroundColorSource'] ?? null);
        $t->same('yellow', $backgrounds[2]['legacyBackgroundColor'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[2]['cssBackgroundColor'] ?? null);
        $t->same([
            'background-color' => 'rgb(230, 255, 237)',
            'bgcolor' => 'yellow',
        ], $backgrounds[2]['attributes'] ?? null);

        $t->same([
            'markdown-cell-background-require-raw-html',
            'asciidoc-cell-background-review-required',
            'latex-cell-background-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $backgroundDiagnostics['markdown'],
            $backgroundDiagnostics['asciidoc'],
            $backgroundDiagnostics['latex'],
        ]));
        $t->same('raw-html-cell-background', $backgroundDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-cell-background', $backgroundDiagnostics['markdown']['source'] ?? null);
        $t->same(3, $backgroundDiagnostics['markdown']['cellCount'] ?? null);
        $t->same([0, 1], $backgroundDiagnostics['markdown']['columns'] ?? null);
        $t->same(['head', 'body'], $backgroundDiagnostics['markdown']['sections'] ?? null);
        $t->same(['#fff4cc', '#e6ffed', 'rgb(230, 255, 237)'], $backgroundDiagnostics['markdown']['colors'] ?? null);
        $t->same(['bgcolor', 'style'], $backgroundDiagnostics['markdown']['backgroundColorSources'] ?? null);

        $t->contains('<th bgcolor="#FFF4CC">Source</th><th>Status</th>', $blocks);
        $t->contains('<td style="background-color: #e6ffed">Posts</td><td bgcolor="yellow" style="background-color: rgb(230, 255, 237)">Ready</td>', $blocks);
        $t->contains('<td>Media</td><td>Review</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table row background metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="row-background-grid" data-source="html-reader">
<caption>Row background review</caption>
<thead>
<tr bgcolor="#FFF4CC"><th>Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr style="background-color: #e6ffed"><td>Posts</td><td>Ready</td></tr>
<tr bgcolor="yellow" style="background-color: rgb(230, 255, 237)"><td>Media</td><td>Review</td></tr>
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
        $backgroundDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'row-background'
            ));
            $backgroundDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $backgrounds = is_array($packet['rowBackgrounds'] ?? null) ? $packet['rowBackgrounds'] : [];
        $t->same(3, count($backgrounds));
        $t->same(true, $packet['summary']['hasRowBackgrounds'] ?? null);
        $t->same(3, $packet['summary']['rowBackgroundCount'] ?? null);
        $t->same([0, 1], $packet['summary']['rowBackgroundRows'] ?? null);
        $t->same([0, 1, 2], $packet['summary']['rowBackgroundGlobalRows'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['rowBackgroundSections'] ?? null);
        $t->same(['#fff4cc', '#e6ffed', 'rgb(230, 255, 237)'], $packet['summary']['rowBackgroundColors'] ?? null);
        $t->same(['bgcolor', 'style'], $packet['summary']['rowBackgroundSources'] ?? null);

        $t->same('SourceStatus', $backgrounds[0]['text'] ?? null);
        $t->same(true, $backgrounds[0]['headerRow'] ?? null);
        $t->same('head', $backgrounds[0]['section'] ?? null);
        $t->same(0, $backgrounds[0]['row'] ?? null);
        $t->same(0, $backgrounds[0]['globalRow'] ?? null);
        $t->same('#fff4cc', $backgrounds[0]['backgroundColor'] ?? null);
        $t->same('bgcolor', $backgrounds[0]['backgroundColorSource'] ?? null);
        $t->same(['bgcolor' => '#fff4cc'], $backgrounds[0]['attributes'] ?? null);
        $t->same('#FFF4CC', $backgrounds[0]['sourceAttributes']['htmlAttributes']['bgcolor'] ?? null);

        $t->same('PostsReady', $backgrounds[1]['text'] ?? null);
        $t->same(false, $backgrounds[1]['headerRow'] ?? null);
        $t->same('body', $backgrounds[1]['section'] ?? null);
        $t->same(0, $backgrounds[1]['row'] ?? null);
        $t->same(1, $backgrounds[1]['globalRow'] ?? null);
        $t->same('#e6ffed', $backgrounds[1]['backgroundColor'] ?? null);
        $t->same('style', $backgrounds[1]['backgroundColorSource'] ?? null);
        $t->same(['background-color' => '#e6ffed'], $backgrounds[1]['attributes'] ?? null);
        $t->same('background-color: #e6ffed', $backgrounds[1]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same('MediaReview', $backgrounds[2]['text'] ?? null);
        $t->same(1, $backgrounds[2]['row'] ?? null);
        $t->same(2, $backgrounds[2]['globalRow'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[2]['backgroundColor'] ?? null);
        $t->same('style', $backgrounds[2]['backgroundColorSource'] ?? null);
        $t->same('yellow', $backgrounds[2]['legacyBackgroundColor'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[2]['cssBackgroundColor'] ?? null);
        $t->same([
            'background-color' => 'rgb(230, 255, 237)',
            'bgcolor' => 'yellow',
        ], $backgrounds[2]['attributes'] ?? null);

        $t->same([
            'markdown-row-background-require-raw-html',
            'asciidoc-row-background-review-required',
            'latex-row-background-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $backgroundDiagnostics['markdown'],
            $backgroundDiagnostics['asciidoc'],
            $backgroundDiagnostics['latex'],
        ]));
        $t->same('raw-html-row-background', $backgroundDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-row-background', $backgroundDiagnostics['markdown']['source'] ?? null);
        $t->same(3, $backgroundDiagnostics['markdown']['rowCount'] ?? null);
        $t->same([0, 1], $backgroundDiagnostics['markdown']['rows'] ?? null);
        $t->same([0, 1, 2], $backgroundDiagnostics['markdown']['globalRows'] ?? null);
        $t->same(['head', 'body'], $backgroundDiagnostics['markdown']['sections'] ?? null);
        $t->same(['#fff4cc', '#e6ffed', 'rgb(230, 255, 237)'], $backgroundDiagnostics['markdown']['colors'] ?? null);

        $t->contains('<tr bgcolor="#FFF4CC"><th>Source</th><th>Status</th></tr>', $blocks);
        $t->contains('<tr style="background-color: #e6ffed"><td>Posts</td><td>Ready</td></tr>', $blocks);
        $t->contains('<tr bgcolor="yellow" style="background-color: rgb(230, 255, 237)"><td>Media</td><td>Review</td></tr>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table row border presentation metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="row-border-presentation-grid" data-source="html-reader">
<caption>Row border presentation review</caption>
<thead>
<tr style="border-color: #336699; border-style: dashed; border-width: 2px"><th>Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr style="border-right: thick double green"><td>Posts</td><td>Ready</td></tr>
<tr style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123"><td>Media</td><td>Review</td></tr>
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
        $borderDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'row-border-presentation'
            ));
            $borderDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $borders = is_array($packet['rowBorderPresentations'] ?? null) ? $packet['rowBorderPresentations'] : [];
        $t->same(3, count($borders));
        $t->same(true, $packet['summary']['hasRowBorderPresentations'] ?? null);
        $t->same(3, $packet['summary']['rowBorderPresentationCount'] ?? null);
        $t->same([0, 1], $packet['summary']['rowBorderPresentationRows'] ?? null);
        $t->same([0, 1, 2], $packet['summary']['rowBorderPresentationGlobalRows'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['rowBorderPresentationSections'] ?? null);
        $t->same(['#336699'], $packet['summary']['rowBorderPresentationColors'] ?? null);
        $t->same(['dashed'], $packet['summary']['rowBorderPresentationStyles'] ?? null);
        $t->same(['2px'], $packet['summary']['rowBorderPresentationWidths'] ?? null);
        $t->same(2, $packet['summary']['rowBorderPresentationEdgeCount'] ?? null);
        $t->same(true, $packet['summary']['hasRowBorderPresentationEdges'] ?? null);
        $t->same(['right', 'bottom'], $packet['summary']['rowBorderPresentationEdges'] ?? null);
        $t->same(['green', '#112233'], $packet['summary']['rowBorderPresentationEdgeColors'] ?? null);
        $t->same(['double', 'dotted'], $packet['summary']['rowBorderPresentationEdgeStyles'] ?? null);
        $t->same(['thick', '3px'], $packet['summary']['rowBorderPresentationEdgeWidths'] ?? null);

        $t->same('SourceStatus', $borders[0]['text'] ?? null);
        $t->same(true, $borders[0]['headerRow'] ?? null);
        $t->same('head', $borders[0]['section'] ?? null);
        $t->same(0, $borders[0]['row'] ?? null);
        $t->same(0, $borders[0]['globalRow'] ?? null);
        $t->same('#336699', $borders[0]['borderColor'] ?? null);
        $t->same('dashed', $borders[0]['borderStyle'] ?? null);
        $t->same('2px', $borders[0]['borderWidth'] ?? null);
        $t->same([
            'border-color' => '#336699',
            'border-style' => 'dashed',
            'border-width' => '2px',
        ], $borders[0]['attributes'] ?? null);
        $t->same('border-color: #336699; border-style: dashed; border-width: 2px', $borders[0]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same('PostsReady', $borders[1]['text'] ?? null);
        $t->same(0, $borders[1]['row'] ?? null);
        $t->same(1, $borders[1]['globalRow'] ?? null);
        $t->same('right', $borders[1]['borderEdges'][0]['edge'] ?? null);
        $t->same('thick double green', $borders[1]['borderEdges'][0]['value'] ?? null);
        $t->same('green', $borders[1]['borderEdges'][0]['borderColor'] ?? null);

        $t->same('MediaReview', $borders[2]['text'] ?? null);
        $t->same('bottom', $borders[2]['borderEdges'][0]['edge'] ?? null);
        $t->same([
            'border-bottom-color' => '#112233',
            'border-bottom-style' => 'dotted',
            'border-bottom-width' => '3px',
        ], $borders[2]['borderEdges'][0]['attributes'] ?? null);
        $t->same('#112233', $borders[2]['borderEdges'][0]['borderColor'] ?? null);

        $t->same([
            'markdown-row-border-presentation-require-raw-html',
            'asciidoc-row-border-presentation-review-required',
            'latex-row-border-presentation-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $borderDiagnostics['markdown'],
            $borderDiagnostics['asciidoc'],
            $borderDiagnostics['latex'],
        ]));
        $t->same('raw-html-row-border-presentation', $borderDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-row-border-presentation', $borderDiagnostics['markdown']['source'] ?? null);
        $t->same(3, $borderDiagnostics['markdown']['rowCount'] ?? null);
        $t->same([0, 1], $borderDiagnostics['markdown']['rows'] ?? null);
        $t->same([0, 1, 2], $borderDiagnostics['markdown']['globalRows'] ?? null);
        $t->same(['head', 'body'], $borderDiagnostics['markdown']['sections'] ?? null);
        $t->same(['#336699'], $borderDiagnostics['markdown']['colors'] ?? null);
        $t->same(['dashed'], $borderDiagnostics['markdown']['styles'] ?? null);
        $t->same(['2px'], $borderDiagnostics['markdown']['widths'] ?? null);
        $t->same(2, $borderDiagnostics['markdown']['edgeCount'] ?? null);
        $t->same(['right', 'bottom'], $borderDiagnostics['markdown']['edges'] ?? null);

        $t->contains('<tr style="border-color: #336699; border-style: dashed; border-width: 2px"><th>Source</th><th>Status</th></tr>', $blocks);
        $t->contains('<tr style="border-right: thick double green"><td>Posts</td><td>Ready</td></tr>', $blocks);
        $t->contains('<tr style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123"><td>Media</td><td>Review</td></tr>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table section presentation metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="section-presentation-grid" data-source="html-reader">
<caption>Section presentation review</caption>
<thead id="section-head" style="background-color: #FFF4CC; border-bottom: 2px solid #336699">
<tr><th>Source</th><th>Status</th></tr>
</thead>
<tbody id="section-body" bgcolor="yellow" style="border-color: #336699; border-style: dashed; border-width: 2px">
<tr><td>Posts</td><td>Ready</td></tr>
</tbody>
<tfoot id="section-foot" style="background-color: rgb(230, 255, 237); border-top-width: 3px; border-top-style: dotted; border-top-color: #123">
<tr><td>Total</td><td>Review</td></tr>
</tfoot>
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
        $backgroundDiagnostics = [];
        $borderDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $backgroundMatches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'section-background'
            ));
            $borderMatches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'section-border-presentation'
            ));
            $backgroundDiagnostics[$writer] = $backgroundMatches[0] ?? [];
            $borderDiagnostics[$writer] = $borderMatches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];

        $backgrounds = is_array($packet['sectionBackgrounds'] ?? null) ? $packet['sectionBackgrounds'] : [];
        $borders = is_array($packet['sectionBorderPresentations'] ?? null) ? $packet['sectionBorderPresentations'] : [];
        $t->same(3, count($backgrounds));
        $t->same(3, count($borders));
        $t->same(true, $packet['summary']['hasSectionBackgrounds'] ?? null);
        $t->same(true, $packet['summary']['hasSectionBorderPresentations'] ?? null);
        $t->same(['head', 'body', 'foot'], $packet['summary']['sectionBackgroundSections'] ?? null);
        $t->same(['#fff4cc', 'yellow', 'rgb(230, 255, 237)'], $packet['summary']['sectionBackgroundColors'] ?? null);
        $t->same(['style', 'bgcolor'], $packet['summary']['sectionBackgroundSources'] ?? null);
        $t->same(['head', 'body', 'foot'], $packet['summary']['sectionBorderPresentationSections'] ?? null);
        $t->same(['#336699'], $packet['summary']['sectionBorderPresentationColors'] ?? null);
        $t->same(['dashed'], $packet['summary']['sectionBorderPresentationStyles'] ?? null);
        $t->same(['2px'], $packet['summary']['sectionBorderPresentationWidths'] ?? null);
        $t->same(2, $packet['summary']['sectionBorderPresentationEdgeCount'] ?? null);
        $t->same(['bottom', 'top'], $packet['summary']['sectionBorderPresentationEdges'] ?? null);
        $t->same(['#336699', '#112233'], $packet['summary']['sectionBorderPresentationEdgeColors'] ?? null);
        $t->same(['solid', 'dotted'], $packet['summary']['sectionBorderPresentationEdgeStyles'] ?? null);
        $t->same(['2px', '3px'], $packet['summary']['sectionBorderPresentationEdgeWidths'] ?? null);

        $t->same('head', $backgrounds[0]['section'] ?? null);
        $t->same([0, 1], $backgrounds[0]['globalRowRange'] ?? null);
        $t->same('#fff4cc', $backgrounds[0]['backgroundColor'] ?? null);
        $t->same('style', $backgrounds[0]['backgroundColorSource'] ?? null);
        $t->same('section-head', $backgrounds[0]['sourceAttributes']['htmlAttributes']['id'] ?? null);
        $t->same('body', $backgrounds[1]['section'] ?? null);
        $t->same([1, 2], $backgrounds[1]['globalRowRange'] ?? null);
        $t->same('yellow', $backgrounds[1]['backgroundColor'] ?? null);
        $t->same('bgcolor', $backgrounds[1]['backgroundColorSource'] ?? null);
        $t->same('foot', $backgrounds[2]['section'] ?? null);
        $t->same([2, 3], $backgrounds[2]['globalRowRange'] ?? null);
        $t->same('rgb(230, 255, 237)', $backgrounds[2]['backgroundColor'] ?? null);

        $t->same('head', $borders[0]['section'] ?? null);
        $t->same([0, 1], $borders[0]['globalRowRange'] ?? null);
        $t->same('bottom', $borders[0]['borderEdges'][0]['edge'] ?? null);
        $t->same('2px solid #336699', $borders[0]['borderEdges'][0]['value'] ?? null);
        $t->same('body', $borders[1]['section'] ?? null);
        $t->same('#336699', $borders[1]['borderColor'] ?? null);
        $t->same('dashed', $borders[1]['borderStyle'] ?? null);
        $t->same('2px', $borders[1]['borderWidth'] ?? null);
        $t->same('foot', $borders[2]['section'] ?? null);
        $t->same('top', $borders[2]['borderEdges'][0]['edge'] ?? null);
        $t->same('#112233', $borders[2]['borderEdges'][0]['borderColor'] ?? null);

        $t->same([
            'markdown-section-background-require-raw-html',
            'asciidoc-section-background-review-required',
            'latex-section-background-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $backgroundDiagnostics['markdown'],
            $backgroundDiagnostics['asciidoc'],
            $backgroundDiagnostics['latex'],
        ]));
        $t->same('raw-html-section-background', $backgroundDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same(3, $backgroundDiagnostics['markdown']['sectionCount'] ?? null);
        $t->same(['head', 'body', 'foot'], $backgroundDiagnostics['markdown']['sections'] ?? null);
        $t->same(['#fff4cc', 'yellow', 'rgb(230, 255, 237)'], $backgroundDiagnostics['markdown']['colors'] ?? null);

        $t->same([
            'markdown-section-border-presentation-require-raw-html',
            'asciidoc-section-border-presentation-review-required',
            'latex-section-border-presentation-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $borderDiagnostics['markdown'],
            $borderDiagnostics['asciidoc'],
            $borderDiagnostics['latex'],
        ]));
        $t->same('raw-html-section-border-presentation', $borderDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same(3, $borderDiagnostics['markdown']['sectionCount'] ?? null);
        $t->same(2, $borderDiagnostics['markdown']['edgeCount'] ?? null);
        $t->same(['bottom', 'top'], $borderDiagnostics['markdown']['edges'] ?? null);

        $t->contains('<thead id="section-head" style="background-color: #FFF4CC; border-bottom: 2px solid #336699">', $blocks);
        $t->contains('<tbody id="section-body" bgcolor="yellow" style="border-color: #336699; border-style: dashed; border-width: 2px">', $blocks);
        $t->contains('<tfoot id="section-foot" style="background-color: rgb(230, 255, 237); border-top-width: 3px; border-top-style: dotted; border-top-color: #123">', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table cell border presentation metadata for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="cell-border-presentation-grid" data-source="html-reader">
<caption>Cell border presentation review</caption>
<thead>
<tr><th style="border-color: #336699; border-style: dashed; border-width: 2px">Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td style="border-color: rgb(51, 102, 153); border-style: solid">Posts</td><td style="border-width: thin medium thick 2px">Ready</td></tr>
<tr><td>Media</td><td>Review</td></tr>
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
        $borderDiagnostics = [];
        foreach (['markdown', 'asciidoc', 'latex'] as $writer) {
            $matches = array_values(array_filter(
                $downgradePacket['writerDowngrades'][$writer] ?? [],
                static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-border-presentation'
            ));
            $borderDiagnostics[$writer] = $matches[0] ?? [];
        }

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $borders = is_array($packet['cellBorderPresentations'] ?? null) ? $packet['cellBorderPresentations'] : [];
        $t->same(3, count($borders));
        $t->same(true, $packet['summary']['hasCellBorderPresentations'] ?? null);
        $t->same(3, $packet['summary']['cellBorderPresentationCount'] ?? null);
        $t->same([0, 1], $packet['summary']['cellBorderPresentationColumns'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['cellBorderPresentationSections'] ?? null);
        $t->same(['#336699', 'rgb(51, 102, 153)'], $packet['summary']['cellBorderPresentationColors'] ?? null);
        $t->same(['dashed', 'solid'], $packet['summary']['cellBorderPresentationStyles'] ?? null);
        $t->same(['2px', 'thin medium thick 2px'], $packet['summary']['cellBorderPresentationWidths'] ?? null);

        $t->same('Source', $borders[0]['text'] ?? null);
        $t->same(true, $borders[0]['headerCell'] ?? null);
        $t->same(0, $borders[0]['column'] ?? null);
        $t->same('#336699', $borders[0]['borderColor'] ?? null);
        $t->same('dashed', $borders[0]['borderStyle'] ?? null);
        $t->same('2px', $borders[0]['borderWidth'] ?? null);
        $t->same([
            'border-color' => '#336699',
            'border-style' => 'dashed',
            'border-width' => '2px',
        ], $borders[0]['attributes'] ?? null);
        $t->same('border-color: #336699; border-style: dashed; border-width: 2px', $borders[0]['sourceAttributes']['htmlAttributes']['style'] ?? null);

        $t->same('Posts', $borders[1]['text'] ?? null);
        $t->same(0, $borders[1]['column'] ?? null);
        $t->same('rgb(51, 102, 153)', $borders[1]['borderColor'] ?? null);
        $t->same('solid', $borders[1]['borderStyle'] ?? null);
        $t->same(['border-color' => 'rgb(51, 102, 153)', 'border-style' => 'solid'], $borders[1]['attributes'] ?? null);

        $t->same('Ready', $borders[2]['text'] ?? null);
        $t->same(1, $borders[2]['column'] ?? null);
        $t->same('thin medium thick 2px', $borders[2]['borderWidth'] ?? null);
        $t->same(['border-width' => 'thin medium thick 2px'], $borders[2]['attributes'] ?? null);

        $t->same([
            'markdown-cell-border-presentation-require-raw-html',
            'asciidoc-cell-border-presentation-review-required',
            'latex-cell-border-presentation-review-required',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), [
            $borderDiagnostics['markdown'],
            $borderDiagnostics['asciidoc'],
            $borderDiagnostics['latex'],
        ]));
        $t->same('raw-html-cell-border-presentation', $borderDiagnostics['markdown']['requiredFeature'] ?? null);
        $t->same('html-table-cell-border-presentation', $borderDiagnostics['markdown']['source'] ?? null);
        $t->same(3, $borderDiagnostics['markdown']['cellCount'] ?? null);
        $t->same([0, 1], $borderDiagnostics['markdown']['columns'] ?? null);
        $t->same(['head', 'body'], $borderDiagnostics['markdown']['sections'] ?? null);
        $t->same(['#336699', 'rgb(51, 102, 153)'], $borderDiagnostics['markdown']['colors'] ?? null);
        $t->same(['dashed', 'solid'], $borderDiagnostics['markdown']['styles'] ?? null);
        $t->same(['2px', 'thin medium thick 2px'], $borderDiagnostics['markdown']['widths'] ?? null);

        $t->contains('<th style="border-color: #336699; border-style: dashed; border-width: 2px">Source</th><th>Status</th>', $blocks);
        $t->contains('<td style="border-color: rgb(51, 102, 153); border-style: solid">Posts</td><td style="border-width: thin medium thick 2px">Ready</td>', $blocks);
        $t->contains('<td>Media</td><td>Review</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'normalizes html table cell side border provenance for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="cell-side-border-grid" data-source="html-reader">
<caption>Cell side border review</caption>
<thead>
<tr><th style="border-top: 2px dashed #336699; border-left: 1pt solid red">Source</th><th>Status</th></tr>
</thead>
<tbody>
<tr><td style="border-right: thick double green">Posts</td><td style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123">Ready</td></tr>
<tr><td>Media</td><td>Review</td></tr>
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
        $markdownDiagnostics = array_values(array_filter(
            $downgradePacket['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'cell-border-presentation'
        ));

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $borders = is_array($packet['cellBorderPresentations'] ?? null) ? $packet['cellBorderPresentations'] : [];
        $t->same(3, count($borders));
        $t->same(true, $packet['summary']['hasCellBorderPresentations'] ?? null);
        $t->same(3, $packet['summary']['cellBorderPresentationCount'] ?? null);
        $t->same(4, $packet['summary']['cellBorderPresentationEdgeCount'] ?? null);
        $t->same(true, $packet['summary']['hasCellBorderPresentationEdges'] ?? null);
        $t->same(['top', 'left', 'right', 'bottom'], $packet['summary']['cellBorderPresentationEdges'] ?? null);
        $t->same(['2px', '1pt', 'thick', '3px'], $packet['summary']['cellBorderPresentationEdgeWidths'] ?? null);
        $t->same(['dashed', 'solid', 'double', 'dotted'], $packet['summary']['cellBorderPresentationEdgeStyles'] ?? null);
        $t->same(['#336699', 'red', 'green', '#112233'], $packet['summary']['cellBorderPresentationEdgeColors'] ?? null);

        $t->same('Source', $borders[0]['text'] ?? null);
        $t->same([
            'border-left' => '1pt solid red',
            'border-top' => '2px dashed #336699',
        ], $borders[0]['attributes'] ?? null);
        $t->same(2, count($borders[0]['borderEdges'] ?? []));
        $t->same('top', $borders[0]['borderEdges'][0]['edge'] ?? null);
        $t->same('border-top', $borders[0]['borderEdges'][0]['property'] ?? null);
        $t->same('2px dashed #336699', $borders[0]['borderEdges'][0]['value'] ?? null);
        $t->same('2px', $borders[0]['borderEdges'][0]['borderWidth'] ?? null);
        $t->same('dashed', $borders[0]['borderEdges'][0]['borderStyle'] ?? null);
        $t->same('#336699', $borders[0]['borderEdges'][0]['borderColor'] ?? null);
        $t->same('left', $borders[0]['borderEdges'][1]['edge'] ?? null);
        $t->same('1pt solid red', $borders[0]['borderEdges'][1]['value'] ?? null);

        $t->same('right', $borders[1]['borderEdges'][0]['edge'] ?? null);
        $t->same('thick double green', $borders[1]['borderEdges'][0]['value'] ?? null);
        $t->same('bottom', $borders[2]['borderEdges'][0]['edge'] ?? null);
        $t->same([
            'border-bottom-color' => '#112233',
            'border-bottom-style' => 'dotted',
            'border-bottom-width' => '3px',
        ], $borders[2]['borderEdges'][0]['attributes'] ?? null);
        $t->same('#112233', $borders[2]['borderEdges'][0]['borderColor'] ?? null);

        $t->same(1, count($markdownDiagnostics));
        $t->same(4, $markdownDiagnostics[0]['edgeCount'] ?? null);
        $t->same(['top', 'left', 'right', 'bottom'], $markdownDiagnostics[0]['edges'] ?? null);
        $t->same('raw-html-cell-border-presentation', $markdownDiagnostics[0]['requiredFeature'] ?? null);

        $t->contains('<th style="border-top: 2px dashed #336699; border-left: 1pt solid red">Source</th><th>Status</th>', $blocks);
        $t->contains('<td style="border-right: thick double green">Posts</td><td style="border-bottom-width: 3px; border-bottom-style: dotted; border-bottom-color: #123">Ready</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
    'reports duplicate html source ids beyond header-only collisions for geometry and wordpress handoff' => static function (TestRunner $t): void {
        $html = <<<'HTML'
<table id="duplicate-source-id-grid" data-source="html-reader">
<caption>Duplicate source id review</caption>
<thead id="duplicate-source-section">
<tr id="duplicate-source-row"><th id="source-scope">Scope</th><th id="source-state">State</th></tr>
</thead>
<tbody id="duplicate-source-section">
<tr id="duplicate-source-row"><td id="duplicate-source-cell">Posts</td><td id="duplicate-source-cell">Ready</td></tr>
</tbody>
</table>
HTML;

        $document = (new MarkdownReader())->read($html);
        $table = $document->children[0];
        $packet = $table->attr('tableGeometry');
        $blocks = (new WordPressBlockWriter())->write($document);
        $downgradePacket = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
        ]);
        $markdownDiagnostics = array_values(array_filter(
            $downgradePacket['writerDowngrades']['markdown'] ?? [],
            static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? null) === 'duplicate-source-ids'
        ));

        $t->same('table', $table->type);
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $duplicateIds = is_array($packet['duplicateSourceIds'] ?? null) ? $packet['duplicateSourceIds'] : [];
        $diagnostics = is_array($packet['diagnostics'] ?? null) ? $packet['diagnostics'] : [];

        $t->same(true, $packet['summary']['hasDuplicateSourceIds'] ?? null);
        $t->same(3, $packet['summary']['duplicateSourceIdCount'] ?? null);
        $t->same(6, $packet['summary']['duplicateSourceIdLocationCount'] ?? null);
        $t->same(['duplicate-source-section', 'duplicate-source-row', 'duplicate-source-cell'], $packet['summary']['duplicateSourceIds'] ?? null);
        $t->same(['cell', 'row', 'section'], $packet['summary']['duplicateSourceIdScopes'] ?? null);
        $t->same(['table-source-id-duplicated'], $packet['summary']['diagnosticCodes'] ?? null);

        $t->same(3, count($duplicateIds));
        $t->same('duplicate-source-section', $duplicateIds[0]['id'] ?? null);
        $t->same(['section', 'section'], array_map(static fn (array $location): string => (string) ($location['scope'] ?? ''), $duplicateIds[0]['locations'] ?? []));
        $t->same(['head', 'body'], array_map(static fn (array $location): string => (string) ($location['section'] ?? ''), $duplicateIds[0]['locations'] ?? []));
        $t->same('duplicate-source-row', $duplicateIds[1]['id'] ?? null);
        $t->same(['row', 'row'], array_map(static fn (array $location): string => (string) ($location['scope'] ?? ''), $duplicateIds[1]['locations'] ?? []));
        $t->same([0, 0], array_map(static fn (array $location): int => (int) ($location['row'] ?? -1), $duplicateIds[1]['locations'] ?? []));
        $t->same('duplicate-source-cell', $duplicateIds[2]['id'] ?? null);
        $t->same(['cell', 'cell'], array_map(static fn (array $location): string => (string) ($location['scope'] ?? ''), $duplicateIds[2]['locations'] ?? []));
        $t->same([0, 1], array_map(static fn (array $location): int => (int) ($location['sourceCell'] ?? -1), $duplicateIds[2]['locations'] ?? []));
        $t->same(['Posts', 'Ready'], array_map(static fn (array $location): string => (string) ($location['text'] ?? ''), $duplicateIds[2]['locations'] ?? []));
        $t->same([false, false], array_map(static fn (array $location): bool => (bool) ($location['headerCell'] ?? true), $duplicateIds[2]['locations'] ?? []));

        $t->same(1, count($diagnostics));
        $t->same('table-source-id-duplicated', $diagnostics[0]['code'] ?? null);
        $t->same(3, $diagnostics[0]['duplicateIdCount'] ?? null);
        $t->same(6, $diagnostics[0]['duplicateLocationCount'] ?? null);
        $t->same(['duplicate-source-section', 'duplicate-source-row', 'duplicate-source-cell'], $diagnostics[0]['duplicateIds'] ?? null);
        $t->same($duplicateIds, $diagnostics[0]['duplicates'] ?? null);

        $t->same(1, count($markdownDiagnostics));
        $t->same('markdown-source-ids-duplicated', $markdownDiagnostics[0]['code'] ?? null);
        $t->same('raw-html-table-source-ids', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['duplicateIdCount'] ?? null);
        $t->same(['duplicate-source-section', 'duplicate-source-row', 'duplicate-source-cell'], $markdownDiagnostics[0]['duplicateIds'] ?? null);
        $t->same([], $downgradePacket['writerDowngrades']['wordpress'] ?? null);

        $t->contains('<thead id="duplicate-source-section"><tr id="duplicate-source-row"><th id="source-scope">Scope</th><th id="source-state">State</th></tr></thead>', $blocks);
        $t->contains('<tbody id="duplicate-source-section"><tr id="duplicate-source-row"><td id="duplicate-source-cell">Posts</td><td id="duplicate-source-cell">Ready</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($downgradePacket, JSON_THROW_ON_ERROR);
    },
];
