<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps csv input into a native table ast with review evidence and exports' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv(implode("\n", [
            'source_id,title,published',
            '42,"Legacy, ""quoted"" title",true',
            "43,\"Two\nline title\",false",
            '',
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $geometry = $table->attr('tableGeometry');
        $markdown = (new MarkdownWriter())->write($document);
        $wordpress = (new WordPressBlockWriter())->write($document);
        $json = (new PandocJsonWriter())->toArray($document);

        $t->same('document', $document->type);
        $t->same('csv', $document->attr('sourceFormat'));
        $t->same('table', $table->type);
        $t->same('csv', $table->attr('sourceFormat'));
        $t->same(3, TableGeometry::columnCount($table));
        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(['test/command/01.csv', 'test/command/3533-rst-csv-tables.csv'], $packet['upstreamEvidence']['fixtures'] ?? null);
        $t->same('Legacy, "quoted" title', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("Two\nline title", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(3, $geometry['columnCount'] ?? null);
        $t->same('Legacy, "quoted" title', $geometry['coverage'][4]['text'] ?? null);
        $t->contains('| source_id | title                    | published |', $markdown);
        $t->contains('| 42        | Legacy, \\"quoted\\" title | true      |', $markdown);
        $t->contains('<th>source_id</th><th>title</th><th>published</th>', $wordpress);
        $t->contains('<td>Legacy, &quot;quoted&quot; title</td>', $wordpress);
        $t->same('Table', $json['blocks'][0]['t'] ?? null);
    },
    'maps tsv input into a native table ast and pads ragged rows' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readTsv(implode("\n", [
            "source_id\ttitle\tpublished",
            "42\tTabular title\ttrue",
            "43\tNeeds review",
            '',
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $markdown = (new MarkdownWriter())->write($document);
        $wordpress = (new WordPressBlockWriter())->write($document);

        $t->same('tsv', $document->attr('sourceFormat'));
        $t->same('tsv', $table->attr('sourceFormat'));
        $t->same(3, TableGeometry::columnCount($table));
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(8, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([2], $packet['raggedRows'] ?? null);
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('| 43        | Needs review  |           |', $markdown);
        $t->contains('<td>Needs review</td><td></td>', $wordpress);
    },
];
