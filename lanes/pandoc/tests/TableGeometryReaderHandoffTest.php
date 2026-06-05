<?php

declare(strict_types=1);

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
];
