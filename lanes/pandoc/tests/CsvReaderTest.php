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
        $t->contains('<th>Name</th><th>Note</th><th>Count</th>', $blocks);
        $t->contains('<td data-csv-type="string">Ada, Lovelace</td><td data-csv-type="string">Line one<br/>Line two</td><td data-csv-type="integer">2</td>', $blocks);
        $t->contains('<td data-csv-type="string">Escaped &quot;quote&quot;</td><td data-csv-type="string"> padded </td><td data-csv-type="integer">3</td>', $blocks);
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
        $t->same(['string', 'string', 'string'], $meta['csvColumnTypes']);
        $t->same('table_body', $table->children[0]->type);
        $t->same('Ada', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->contains('<tbody><tr><td data-csv-type="string">Ada</td><td data-csv-type="string">Lovelace</td><td data-csv-type="string"></td></tr><tr><td data-csv-type="string">Grace</td><td data-csv-type="string">Hopper</td><td data-csv-type="string">COBOL</td></tr></tbody>', $blocks);
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
        $t->contains('<td data-csv-type="boolean">true</td>', $blocks);
        $t->contains('<td data-csv-type="number">3.14</td>', $blocks);
        $t->contains('<td data-csv-type="date">2026-06-27</td>', $blocks);
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
