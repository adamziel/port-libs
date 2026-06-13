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
    'honors no-header option for csv and tsv table imports' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $csvDocument = $reader->readCsv(implode("\n", [
            '42,"Legacy, ""quoted"" title",true',
            '43,Needs review,false',
            '',
        ]), ['header' => false]);
        $tsvDocument = $reader->readTsv(implode("\n", [
            "A\t10",
            "B\t20",
            '',
        ]), ['header' => false]);
        $csvTable = $csvDocument->children[0];
        $tsvTable = $tsvDocument->children[0];
        $csvPacket = $csvTable->attr('delimitedText');
        $tsvPacket = $tsvTable->attr('delimitedText');
        $csvMarkdown = (new MarkdownWriter())->write($csvDocument);
        $csvWordpress = (new WordPressBlockWriter())->write($csvDocument);
        $csvJson = (new PandocJsonWriter())->toArray($csvDocument);

        $t->same('table_head', $csvTable->children[0]->type);
        $t->same(0, count($csvTable->children[0]->children));
        $t->same('table_body', $csvTable->children[1]->type);
        $t->same(2, count($csvTable->children[1]->children));
        $t->same(false, $csvPacket['headerRow'] ?? null);
        $t->same('none', $csvPacket['headerOption'] ?? null);
        $t->same('generated', $csvPacket['headerSource'] ?? null);
        $t->same(2, $csvPacket['rowCount'] ?? null);
        $t->same(2, $csvPacket['bodyRowCount'] ?? null);
        $t->same(['column1', 'column2', 'column3'], $csvPacket['columnNames'] ?? null);
        $t->same(['column1', 'column2', 'column3'], $csvTable->attr('columnNames'));
        $t->same('delimited-text-header-disabled', $csvPacket['diagnostics'][0]['code'] ?? null);
        $t->same('42', $csvTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Legacy, "quoted" title', $csvTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('43', $csvTable->children[1]->children[1]->children[0]->attr('text'));
        $t->contains('| 42  | Legacy, \\"quoted\\" title | true  |', $csvMarkdown);
        $t->contains('<tbody><tr><td>42</td><td>Legacy, &quot;quoted&quot; title</td><td>true</td></tr>', $csvWordpress);
        $t->true(!str_contains($csvWordpress, '<thead>'));
        $t->same([], $csvJson['blocks'][0]['c'][3][1] ?? null);

        $t->same(false, $tsvPacket['headerRow'] ?? null);
        $t->same('tab', $tsvPacket['delimiter'] ?? null);
        $t->same(['column1', 'column2'], $tsvPacket['columnNames'] ?? null);
        $t->same(2, $tsvPacket['rowCount'] ?? null);
        $t->same(2, $tsvPacket['bodyRowCount'] ?? null);
        $t->same('A', $tsvTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('20', $tsvTable->children[1]->children[1]->children[1]->attr('text'));
    },
    'records csv diagnostics for multiline quotes trailing delimiters and partial eof records' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv(implode("\n", [
            'id,note,status,',
            "1,\"two\nline\",ok,",
            "2,\"open\nlast",
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $codes = array_column($packet['diagnostics'] ?? [], 'code');

        $t->same('csv', $packet['format'] ?? null);
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(10, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([2], $packet['raggedRows'] ?? null);
        $t->same(false, $packet['finalRecordTerminated'] ?? null);
        $t->same(2, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([1, 2], $packet['multilineQuotedRows'] ?? null);
        $t->same(2, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 1], $packet['trailingDelimiterRows'] ?? null);
        $t->same(true, $packet['unterminatedQuoteAtEof'] ?? null);
        $t->same(2, $packet['unterminatedQuoteRow'] ?? null);
        $t->same(true, $packet['partialFinalRecord'] ?? null);
        $t->same(2, $packet['partialFinalRecordRow'] ?? null);
        $t->same(2, $packet['partialFinalRecordFieldCount'] ?? null);
        $t->same([
            'delimited-text-multiline-quoted-field',
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-unterminated-quote-eof',
            'delimited-text-partial-final-record',
        ], $codes);
        $t->same("two\nline", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same("open\nlast", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
    },
    'records tsv diagnostics for multiline quotes trailing delimiters and partial eof records' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readTsv(implode("\n", [
            "id\tnote\tstatus\t",
            "1\t\"two\nline\"\tok\t",
            "2\t\"open\nlast",
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $codes = array_column($packet['diagnostics'] ?? [], 'code');

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(10, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([2], $packet['raggedRows'] ?? null);
        $t->same(false, $packet['finalRecordTerminated'] ?? null);
        $t->same(2, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([1, 2], $packet['multilineQuotedRows'] ?? null);
        $t->same(2, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 1], $packet['trailingDelimiterRows'] ?? null);
        $t->same(true, $packet['unterminatedQuoteAtEof'] ?? null);
        $t->same(2, $packet['unterminatedQuoteRow'] ?? null);
        $t->same(true, $packet['partialFinalRecord'] ?? null);
        $t->same(2, $packet['partialFinalRecordRow'] ?? null);
        $t->same(2, $packet['partialFinalRecordFieldCount'] ?? null);
        $t->same([
            'delimited-text-multiline-quoted-field',
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-unterminated-quote-eof',
            'delimited-text-partial-final-record',
        ], $codes);
        $t->same("two\nline", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same("open\nlast", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
    },
    'rejects non-boolean delimited text header option' => static function (TestRunner $t): void {
        $message = '';
        try {
            (new DelimitedTextReader())->readCsv("a,b\n", ['header' => 'false']);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        $t->contains('header option must be a boolean', $message);
    },
];
