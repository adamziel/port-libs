<?php

declare(strict_types=1);

use PortLibs\Pandoc\CsvReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads csv headers quoted commas escaped quotes and multiline cells into a table' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'Name,Note,Count',
            '"Ada, Lovelace","Line one',
            'Line two",2',
            '"Escaped ""quote"""," padded ",3',
            '',
        ]);

        $document = (new CsvReader())->read($source);
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);
        $converterBlocks = PandocConverter::convert($source, 'csv', 'blocks');

        $t->same('csv', $meta['csvFormat']);
        $t->same(',', $meta['csvDelimiter']);
        $t->same('"', $meta['csvQuote']);
        $t->same(['string', 'string', 'integer'], $meta['csvColumnTypes']);
        $t->same(3, $meta['csvRowCount']);
        $t->same(3, $meta['csvColumnCount']);
        $t->same(2, $meta['csvDataRowCount']);
        $t->same('table', $table->type);
        $t->same(['default', 'default', 'default'], $table->attr('alignments'));
        $t->same('Name', $head->children[0]->children[0]->attr('text'));
        $t->same('Ada, Lovelace', $body->children[0]->children[0]->attr('text'));
        $t->same('linebreak', $body->children[0]->children[1]->children[0]->children[1]->type);
        $t->same('Escaped "quote"', $body->children[1]->children[0]->attr('text'));
        $t->same(' padded ', $body->children[1]->children[1]->attr('text'));
        $t->contains('<table data-pandoc-source="csv" data-csv-row-count="3" data-csv-column-count="3">', $blocks);
        $t->contains('<th data-csv-source-line="1" data-csv-source-column="1" data-csv-source-field="1">Name</th>', $blocks);
        $t->contains('<td data-csv-source-line="2" data-csv-source-column="1" data-csv-source-field="1" data-csv-type="string">Ada, Lovelace</td>', $blocks);
        $t->contains('<td data-csv-source-line="3" data-csv-source-column="11" data-csv-source-field="3" data-csv-type="integer">2</td>', $blocks);
        $t->contains('<td data-csv-source-line="4" data-csv-source-column="1" data-csv-source-field="1" data-csv-type="string">Escaped &quot;quote&quot;</td>', $blocks);
        $t->contains('Line one<br/>Line two', $converterBlocks);
    },
    'reads tsv without quote interpretation through the converter' => static function (TestRunner $t): void {
        $source = "Name\tNote\n\"literal quote\"\tA\tB\nPlain\t spaced \n";

        $document = PandocConverter::read($source, 'tsv');
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $body = $table->children[1];
        $blocks = PandocConverter::convert($source, 'tsv', 'blocks');

        $t->same('tsv', $meta['csvFormat']);
        $t->same("\t", $meta['csvDelimiter']);
        $t->same(null, $meta['csvQuote']);
        $t->same(3, $meta['csvRowCount']);
        $t->same(3, $meta['csvColumnCount']);
        $t->same(2, $meta['csvRaggedRowCount']);
        $t->same([
            ['row' => 1, 'columns' => 2, 'expectedColumns' => 3],
            ['row' => 3, 'columns' => 2, 'expectedColumns' => 3],
        ], $meta['csvRaggedRows']);
        $t->same('"literal quote"', $body->children[0]->children[0]->attr('text'));
        $t->same('A', $body->children[0]->children[1]->attr('text'));
        $t->same('B', $body->children[0]->children[2]->attr('text'));
        $t->same('spaced', $body->children[1]->children[1]->attr('text'));
        $t->contains('data-pandoc-source="tsv"', $blocks);
        $t->contains('&quot;literal quote&quot;', $blocks);
    },
    'reads csv dialect directives ragged rows and headerless options' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'sep=;',
            'Ada;Lovelace',
            'Grace;Hopper;COBOL',
        ]);

        $document = PandocConverter::read($source, 'csv', ['header' => false]);
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $blocks = PandocConverter::convert($source, 'csv', 'blocks', ['readerOptions' => ['header' => false]]);

        $t->same(';', $meta['csvDelimiter']);
        $t->same(false, $meta['csvHeader']);
        $t->same(2, $meta['csvRowCount']);
        $t->same(2, $meta['csvDataRowCount']);
        $t->same(3, $meta['csvColumnCount']);
        $t->same(1, $meta['csvRaggedRowCount']);
        $t->same([
            ['row' => 1, 'columns' => 2, 'expectedColumns' => 3],
        ], $meta['csvRaggedRows']);
        $t->same(['string', 'string', 'string'], $meta['csvColumnTypes']);
        $t->same('table_body', $table->children[0]->type);
        $t->same('Ada', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->contains('data-csv-missing-cell="true"></td>', $blocks);
        $t->contains('>Grace</td><td data-csv-source-line="2" data-csv-source-column="7" data-csv-source-field="2" data-csv-type="string">Hopper</td>', $blocks);
    },
    'detects semicolon csv without counting quoted commas as delimiters' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'Name;Note;Count',
            'Ada;"one, two, three, four, five, six";1',
            'Grace;"alpha, beta, gamma, delta, epsilon, zeta";2',
        ]);

        $document = PandocConverter::read($source, 'csv');
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $body = $table->children[1];
        $blocks = PandocConverter::convert($source, 'csv', 'blocks');
        $headerless = PandocConverter::read(implode("\n", array_slice(explode("\n", $source), 1)), 'csv', [
            'header' => false,
        ]);
        $headerlessMeta = $headerless->attr('meta');

        $t->same(';', $meta['csvDelimiter']);
        $t->same(3, $meta['csvColumnCount']);
        $t->same(0, $meta['csvRaggedRowCount']);
        $t->same(['string', 'string', 'integer'], $meta['csvColumnTypes']);
        $t->same('one, two, three, four, five, six', $body->children[0]->children[1]->attr('text'));
        $t->same('2', $body->children[1]->children[2]->attr('text'));
        $t->contains('<table data-pandoc-source="csv" data-csv-row-count="3" data-csv-column-count="3">', $blocks);
        $t->contains('data-csv-type="string">alpha, beta, gamma, delta, epsilon, zeta</td>', $blocks);
        $t->contains('data-csv-source-line="3" data-csv-source-column="50" data-csv-source-field="3" data-csv-type="integer">2</td>', $blocks);
        $t->same(';', $headerlessMeta['csvDelimiter']);
        $t->same(false, $headerlessMeta['csvHeader']);
        $t->same(3, $headerlessMeta['csvColumnCount']);
        $t->same(0, $headerlessMeta['csvRaggedRowCount']);
    },
    'reads csv comments alternate quote escape encoding and inferred types' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '# generated export',
            'name|active|score|published|note',
            "'Ada'|true|42|2026-06-26|'escaped \\'quote\\''",
            "'Grace'|false|3.14|2026-06-27|'pipe \\| value'",
        ]);
        $utf16 = "\xFF\xFE" . mb_convert_encoding($source, 'UTF-16LE', 'UTF-8');

        $document = PandocConverter::read($utf16, 'csv', [
            'delimiter' => '|',
            'quote' => "'",
            'escape' => '\\',
            'comment' => '#',
        ]);
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $body = $table->children[1];
        $blocks = PandocConverter::convert($utf16, 'csv', 'blocks', [
            'readerOptions' => [
                'delimiter' => '|',
                'quote' => "'",
                'escape' => '\\',
                'comment' => '#',
            ],
        ]);

        $t->same('UTF-16LE', $meta['csvEncoding']);
        $t->same('|', $meta['csvDelimiter']);
        $t->same("'", $meta['csvQuote']);
        $t->same('\\', $meta['csvEscape']);
        $t->same('#', $meta['csvComment']);
        $t->same(3, $meta['csvRowCount']);
        $t->same(['string', 'boolean', 'number', 'date', 'string'], $meta['csvColumnTypes']);
        $t->same('Ada', $body->children[0]->children[0]->attr('text'));
        $t->same("escaped 'quote'", $body->children[0]->children[4]->attr('text'));
        $t->same('pipe | value', $body->children[1]->children[4]->attr('text'));
        $t->contains('data-csv-type="boolean">true</td>', $blocks);
        $t->contains('data-csv-type="number">3.14</td>', $blocks);
        $t->contains('data-csv-type="date">2026-06-27</td>', $blocks);
    },
    'reports csv quote diagnostics and source provenance' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'Name,Note',
            'Ada,bad"quote',
            'Grace,"ok"x',
        ]);

        $document = PandocConverter::read($source, 'csv');
        $meta = $document->attr('meta');
        $table = $document->children[0];
        $body = $table->children[1];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, $meta['csvDiagnosticCount']);
        $t->same('stray-quote', $meta['csvDiagnostics'][0]['type']);
        $t->same(2, $meta['csvDiagnostics'][0]['row']);
        $t->same(8, $meta['csvDiagnostics'][0]['column']);
        $t->same('text-after-closing-quote', $meta['csvDiagnostics'][1]['type']);
        $t->same([
            ['row' => 1, 'lineStart' => 1, 'lineEnd' => 1, 'columnCount' => 2],
            ['row' => 2, 'lineStart' => 2, 'lineEnd' => 2, 'columnCount' => 2],
            ['row' => 3, 'lineStart' => 3, 'lineEnd' => 3, 'columnCount' => 2],
        ], $meta['csvSourceRows']);
        $t->same('2', $body->children[0]->attr('htmlAttributes')['data-csv-source-row']);
        $t->same('2', $body->children[0]->children[1]->attr('htmlAttributes')['data-csv-source-line']);
        $t->same('5', $body->children[0]->children[1]->attr('htmlAttributes')['data-csv-source-column']);
        $t->contains('<tr data-csv-source-row="2" data-csv-source-line-start="2" data-csv-source-line-end="2">', $blocks);
        $t->contains('data-csv-source-line="3" data-csv-source-column="7" data-csv-source-field="2" data-csv-type="string">okx</td>', $blocks);
    },
    'reports unclosed quote diagnostics and can keep blank rows' => static function (TestRunner $t): void {
        $malformed = PandocConverter::read("Name,Note\nAda,\"unterminated", 'csv');
        $keptBlank = PandocConverter::read("Name,Note\n\nAda,1\n", 'csv', [
            'blankLines' => 'keep',
        ]);
        $keptMeta = $keptBlank->attr('meta');

        $t->same('unclosed-quote', $malformed->attr('meta')['csvDiagnostics'][0]['type']);
        $t->same(0, $keptMeta['csvSkippedBlankRows']);
        $t->same('keep', $keptMeta['csvBlankLinePolicy']);
        $t->same(3, $keptMeta['csvRowCount']);
        $t->same('', $keptBlank->children[0]->children[1]->children[0]->children[0]->attr('text'));
    },
    'reads empty csv input as an empty document with table metadata' => static function (TestRunner $t): void {
        $document = PandocConverter::read('', 'csv');
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');

        $t->same([], $document->children);
        $t->same(0, $meta['csvRowCount']);
        $t->same(0, $meta['csvColumnCount']);
        $t->same('', $blocks);
    },
];
