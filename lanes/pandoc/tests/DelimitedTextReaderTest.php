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
        $t->same('"', $packet['quote'] ?? null);
        $t->same(true, array_key_exists('escape', $packet));
        $t->same(null, $packet['escape']);
        $t->same('quoted-fields', $packet['dialect']['quoteMode'] ?? null);
        $t->same('none', $packet['dialect']['escapeMode'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same(0, $packet['partialRecordCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
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
    'records csv and tsv control character row repair interaction summaries' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $csvDocument = $reader->readCsv("\xEF\xBB\xBF" . implode("\n", [
            'id,title,note',
            '1,"quoted' . "\x00" . 'short"',
            '2,plain' . "\x1F" . 'field,ok,extra',
            '3,tail,done',
            '',
        ]), ['sourcePath' => 'imports/control-repair.csv']);
        $tsvDocument = $reader->readTsv(implode("\n", [
            "key\tvalue\tflag",
            'alpha' . "\t" . 'plain' . "\x1B" . 'field',
            'beta' . "\t" . '"quoted' . "\x07" . 'field"' . "\t" . 'ok' . "\t" . 'extra',
            "gamma\t30\t",
            '',
        ]), ['sourcePath' => 'imports/control-repair.tsv']);
        $csvTable = $csvDocument->children[0];
        $tsvTable = $tsvDocument->children[0];
        $csvPacket = $csvTable->attr('delimitedText');
        $tsvPacket = $tsvTable->attr('delimitedText');
        $csvWidth = $csvPacket['rowWidthSummary'] ?? [];
        $tsvWidth = $tsvPacket['rowWidthSummary'] ?? [];
        $csvRepair = $csvPacket['rowRepairSummary'] ?? [];
        $tsvRepair = $tsvPacket['rowRepairSummary'] ?? [];
        $csvControl = $csvPacket['controlCharacters'] ?? [];
        $tsvControl = $tsvPacket['controlCharacters'] ?? [];
        $csvControlRepair = $csvPacket['controlRepairSummary'] ?? [];
        $tsvControlRepair = $tsvPacket['controlRepairSummary'] ?? [];
        $csvSamples = $csvControl['samples'] ?? [];
        $tsvSamples = $tsvControl['samples'] ?? [];
        $csvCodes = array_map(static fn (array $diagnostic): string => $diagnostic['code'], $csvPacket['diagnostics'] ?? []);
        $tsvCodes = array_map(static fn (array $diagnostic): string => $diagnostic['code'], $tsvPacket['diagnostics'] ?? []);

        $t->same('csv', $csvPacket['format'] ?? null);
        $t->same(['id', 'title', 'note', ''], $csvPacket['columnNames'] ?? null);
        $t->same([3, 2, 4, 3], $csvWidth['rowWidths'] ?? null);
        $t->same([0, 1, 2, 3], $csvWidth['sourceRowIndexes'] ?? null);
        $t->same(false, $csvWidth['strict']['consistent'] ?? null);
        $t->same(2, $csvWidth['strict']['mismatchCount'] ?? null);
        $t->same('source-row-1', $csvWidth['strict']['mismatches'][0]['rowLabel'] ?? null);
        $t->same(1, $csvWidth['strict']['mismatches'][0]['missingFields'] ?? null);
        $t->same(1, $csvWidth['strict']['mismatches'][1]['extraFields'] ?? null);
        $t->same(3, $csvWidth['relaxed']['paddedRowCount'] ?? null);
        $t->same(2, $csvWidth['header']['mismatchCount'] ?? null);
        $t->same('relaxed-pad-to-wide-row', $csvRepair['policy'] ?? null);
        $t->same(4, $csvRepair['repairedColumnCount'] ?? null);
        $t->same(3, $csvRepair['changedRowCount'] ?? null);
        $t->same('padded', $csvRepair['rows'][1]['repair'] ?? null);
        $t->same(2, $csvRepair['rows'][1]['originalColumnCount'] ?? null);
        $t->same(4, $csvRepair['rows'][1]['repairedColumnCount'] ?? null);
        $t->same(2, $csvRepair['rows'][1]['missingFieldsAdded'] ?? null);
        $t->same('unchanged', $csvRepair['rows'][2]['repair'] ?? null);
        $t->same([
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
            'delimited-text-control-characters',
        ], $csvCodes);

        $t->same('report-c0-del-controls-except-ht-lf-cr', $csvControl['policy'] ?? null);
        $t->same('imports/control-repair.csv', $csvControl['sourcePath'] ?? null);
        $t->same(2, $csvControl['totalCount'] ?? null);
        $t->same(1, $csvControl['nulCount'] ?? null);
        $t->same(['U+0000' => 1, 'U+001F' => 1], $csvControl['byCodepoint'] ?? null);
        $t->same(1, $csvControl['fieldQuoteBuckets']['quoted']['controlCount'] ?? null);
        $t->same([0], $csvControl['fieldQuoteBuckets']['quoted']['sampleIndexes'] ?? null);
        $t->same(1, $csvControl['fieldQuoteBuckets']['unquoted']['controlCount'] ?? null);
        $t->same([1], $csvControl['fieldQuoteBuckets']['unquoted']['sampleIndexes'] ?? null);
        $t->same(1, $csvSamples[0]['positionBeforeRepair']['sourceRow'] ?? null);
        $t->same(1, $csvSamples[0]['positionBeforeRepair']['sourceColumn'] ?? null);
        $t->same(1, $csvSamples[0]['positionAfterRepair']['row'] ?? null);
        $t->same(1, $csvSamples[0]['positionAfterRepair']['columnIndex'] ?? null);
        $t->same(true, $csvSamples[0]['positionAfterRepair']['columnWithinRepairedWidth'] ?? null);
        $t->same('padded', $csvSamples[0]['rowRepair']['repair'] ?? null);
        $t->same(2, $csvSamples[0]['rowRepair']['missingFieldsAdded'] ?? null);
        $t->same(true, $csvSamples[0]['fieldQuoted'] ?? null);
        $t->same('00', $csvSamples[0]['byteHex'] ?? null);
        $t->same('NUL', $csvSamples[0]['name'] ?? null);
        $t->same(2, $csvSamples[1]['positionBeforeRepair']['sourceRow'] ?? null);
        $t->same(1, $csvSamples[1]['positionBeforeRepair']['sourceColumn'] ?? null);
        $t->same('unchanged', $csvSamples[1]['rowRepair']['repair'] ?? null);
        $t->same(false, $csvSamples[1]['fieldQuoted'] ?? null);
        $t->same('1F', $csvSamples[1]['byteHex'] ?? null);
        $t->same('US', $csvSamples[1]['name'] ?? null);
        $t->same('annotate-controls-after-relaxed-row-repair', $csvControlRepair['policy'] ?? null);
        $t->same('imports/control-repair.csv', $csvControlRepair['sourcePath'] ?? null);
        $t->same(1, $csvControlRepair['controlsInPaddedRows'] ?? null);
        $t->same(1, $csvControlRepair['controlsInChangedRows'] ?? null);
        $t->same(1, $csvControlRepair['controlsInUnchangedRows'] ?? null);
        $t->same(['padded' => 1, 'unchanged' => 1, 'truncated' => 0, 'unmapped' => 0], $csvControlRepair['byRepair'] ?? null);
        $t->same('padded', $csvControlRepair['sourceRows'][0]['repair'] ?? null);
        $t->same([1], $csvControlRepair['sourceRows'][0]['columns'] ?? null);
        $t->same('quoted' . "\x00" . 'short', $csvTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $csvTable->children[1]->children[0]->children[2]->attr('text'));
        $t->same('extra', $csvTable->children[1]->children[1]->children[3]->attr('text'));

        $t->same('tsv', $tsvPacket['format'] ?? null);
        $t->same('tab', $tsvPacket['delimiter'] ?? null);
        $t->same([3, 2, 4, 3], $tsvWidth['rowWidths'] ?? null);
        $t->same([3], $tsvWidth['trailingEmptyFieldRows'] ?? null);
        $t->same(2, $tsvWidth['strict']['mismatchCount'] ?? null);
        $t->same(3, $tsvWidth['relaxed']['paddedRowCount'] ?? null);
        $t->same(2, $tsvWidth['header']['mismatchCount'] ?? null);
        $t->same('relaxed-pad-to-wide-row', $tsvRepair['policy'] ?? null);
        $t->same(4, $tsvRepair['repairedColumnCount'] ?? null);
        $t->same(3, $tsvRepair['changedRowCount'] ?? null);
        $t->same('padded', $tsvRepair['rows'][1]['repair'] ?? null);
        $t->same(2, $tsvRepair['rows'][1]['missingFieldsAdded'] ?? null);
        $t->same('unchanged', $tsvRepair['rows'][2]['repair'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-trailing-empty-fields-preserved',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
            'delimited-text-control-characters',
        ], $tsvCodes);

        $t->same('imports/control-repair.tsv', $tsvControl['sourcePath'] ?? null);
        $t->same(2, $tsvControl['totalCount'] ?? null);
        $t->same(['U+0007' => 1, 'U+001B' => 1], $tsvControl['byCodepoint'] ?? null);
        $t->same(0, $tsvControl['fieldQuoteBuckets']['quoted']['controlCount'] ?? null);
        $t->same([], $tsvControl['fieldQuoteBuckets']['quoted']['sampleIndexes'] ?? null);
        $t->same(2, $tsvControl['fieldQuoteBuckets']['unquoted']['controlCount'] ?? null);
        $t->same([0, 1], $tsvControl['fieldQuoteBuckets']['unquoted']['sampleIndexes'] ?? null);
        $t->same(1, $tsvSamples[0]['positionBeforeRepair']['sourceRow'] ?? null);
        $t->same(1, $tsvSamples[0]['positionBeforeRepair']['sourceColumn'] ?? null);
        $t->same('padded', $tsvSamples[0]['rowRepair']['repair'] ?? null);
        $t->same(2, $tsvSamples[0]['rowRepair']['missingFieldsAdded'] ?? null);
        $t->same(false, $tsvSamples[0]['fieldQuoted'] ?? null);
        $t->same('1B', $tsvSamples[0]['byteHex'] ?? null);
        $t->same('ESC', $tsvSamples[0]['name'] ?? null);
        $t->same(2, $tsvSamples[1]['positionAfterRepair']['row'] ?? null);
        $t->same(1, $tsvSamples[1]['positionAfterRepair']['columnIndex'] ?? null);
        $t->same('unchanged', $tsvSamples[1]['rowRepair']['repair'] ?? null);
        $t->same(false, $tsvSamples[1]['fieldQuoted'] ?? null);
        $t->same('07', $tsvSamples[1]['byteHex'] ?? null);
        $t->same('BEL', $tsvSamples[1]['name'] ?? null);
        $t->same(1, $tsvControlRepair['controlsInPaddedRows'] ?? null);
        $t->same(1, $tsvControlRepair['controlsInChangedRows'] ?? null);
        $t->same(1, $tsvControlRepair['controlsInUnchangedRows'] ?? null);
        $t->same(['padded' => 1, 'unchanged' => 1, 'truncated' => 0, 'unmapped' => 0], $tsvControlRepair['byRepair'] ?? null);
        $t->same('plain' . "\x1B" . 'field', $tsvTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $tsvTable->children[1]->children[0]->children[2]->attr('text'));
        $t->same('"quoted' . "\x07" . 'field"', $tsvTable->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $tsvTable->children[1]->children[2]->children[2]->attr('text'));
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
    'records csv quote and escape diagnostics while preserving partial records' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv(implode("\n", [
            'id,title,note',
            '1,"Doubled ""quote"" value","Backslash \"quote\" marker"',
            '2,unquoted "literal" quote,ok',
            '3,"partial quoted field',
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $codes = array_column($packet['diagnostics'] ?? [], 'code');

        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(2, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(2, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(1, $packet['unclosedQuoteCount'] ?? null);
        $t->same(1, $packet['partialRecordCount'] ?? null);
        $t->same(10, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-unterminated-quote-eof',
            'delimited-text-partial-final-record',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
            'delimited-text-backslash-quote-preserved',
            'delimited-text-backslash-quote-preserved',
            'delimited-text-quote-in-unquoted-field',
            'delimited-text-quote-in-unquoted-field',
            'delimited-text-unclosed-quoted-field',
        ], $codes);
        $t->same('Doubled "quote" value', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('Backslash \"quote\" marker', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('unquoted "literal" quote', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('partial quoted field', $table->children[1]->children[2]->children[1]->attr('text'));
    },
    'keeps tsv quotes literal and isolates tab delimiter behavior' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $csvDocument = $reader->readCsv("id,title\n1,\"tab\tstays in csv field\"\n");
        $tsvDocument = $reader->readTsv(implode("\n", [
            "id\tnote",
            "1\t\"literal \"\"quotes\"\" stay\"",
            "2\tcomma, stays in tsv field",
            '',
        ]));
        $csvTable = $csvDocument->children[0];
        $tsvTable = $tsvDocument->children[0];
        $csvPacket = $csvTable->attr('delimitedText');
        $tsvPacket = $tsvTable->attr('delimitedText');

        $t->same(',', $csvPacket['delimiter'] ?? null);
        $t->same(2, $csvPacket['columnCount'] ?? null);
        $t->same("tab\tstays in csv field", $csvTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('tab', $tsvPacket['delimiter'] ?? null);
        $t->same(true, array_key_exists('quote', $tsvPacket));
        $t->same(null, $tsvPacket['quote']);
        $t->same('literal', $tsvPacket['dialect']['quoteMode'] ?? null);
        $t->same('none', $tsvPacket['dialect']['escapeMode'] ?? null);
        $t->same(0, $tsvPacket['quotedFieldCount'] ?? null);
        $t->same(0, $tsvPacket['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $tsvPacket['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $tsvPacket['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $tsvPacket['diagnosticCount'] ?? null);
        $t->same('"literal ""quotes"" stay"', $tsvTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('comma, stays in tsv field', $tsvTable->children[1]->children[1]->children[1]->attr('text'));
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
            'delimited-text-trailing-empty-fields-preserved',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
            'delimited-text-unclosed-quoted-field',
        ], $codes);
        $t->same("two\nline", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same("open\nlast", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
    },
    'records tsv diagnostics for trailing delimiters and partial literal quote records' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readTsv(implode("\n", [
            "id\tnote\tstatus\t",
            "1\tliteral quote \"two\tok\t",
            "2\tpartial",
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
        $t->same(0, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([], $packet['multilineQuotedRows'] ?? null);
        $t->same(0, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 1], $packet['trailingDelimiterRows'] ?? null);
        $t->same(false, $packet['unterminatedQuoteAtEof'] ?? null);
        $t->same(null, $packet['unterminatedQuoteRow'] ?? null);
        $t->same(true, $packet['partialFinalRecord'] ?? null);
        $t->same(2, $packet['partialFinalRecordRow'] ?? null);
        $t->same(2, $packet['partialFinalRecordFieldCount'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-partial-final-record',
            'delimited-text-trailing-empty-fields-preserved',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], $codes);
        $t->same('literal quote "two', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('partial', $table->children[1]->children[1]->children[1]->attr('text'));
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
    'infers delimited text format from extension and row profiles' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $tsvDocument = $reader->readAuto(implode("\n", [
            "name\tqty",
            "A\t10",
            '',
        ]), ['sourcePath' => 'inventory.TSV']);
        $csvDocument = $reader->read(implode("\n", [
            'sku,count',
            'A-1,10',
            '',
        ]), 'auto');
        $tsvPacket = $tsvDocument->children[0]->attr('delimitedText');
        $csvPacket = $csvDocument->children[0]->attr('delimitedText');

        $t->same('tsv', $tsvDocument->attr('sourceFormat'));
        $t->same('tab', $tsvPacket['delimiter'] ?? null);
        $t->same([
            'requestedFormat' => 'auto',
            'selectedFormat' => 'tsv',
            'source' => 'source-path',
            'sourceValue' => 'inventory.TSV',
            'confidence' => 'extension',
            'candidateScores' => [],
        ], $tsvPacket['formatInference'] ?? null);
        $t->same('delimited-text-format-inferred', $tsvPacket['diagnostics'][0]['code'] ?? null);
        $t->same('A', $tsvDocument->children[0]->children[1]->children[0]->children[0]->attr('text'));

        $t->same('csv', $csvDocument->attr('sourceFormat'));
        $t->same(',', $csvPacket['delimiter'] ?? null);
        $t->same('auto', $csvPacket['formatInference']['requestedFormat'] ?? null);
        $t->same('csv', $csvPacket['formatInference']['selectedFormat'] ?? null);
        $t->same('content', $csvPacket['formatInference']['source'] ?? null);
        $t->same('high', $csvPacket['formatInference']['confidence'] ?? null);
        $t->same(2, $csvPacket['formatInference']['candidateScores']['csv']['multicolumnRows'] ?? null);
        $t->same(0, $csvPacket['formatInference']['candidateScores']['tsv']['multicolumnRows'] ?? null);
        $t->same('delimited-text-format-inferred', $csvPacket['diagnostics'][0]['code'] ?? null);
        $t->same('10', $csvDocument->children[0]->children[1]->children[0]->children[1]->attr('text'));
    },
    'records csv relaxed row repair provenance without changing padded table output' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv("\xEF\xBB\xBF" . implode("\n", [
            'id,title,published',
            '42,"Legacy, ""quoted"" title",true',
            "43,\"Two\nline title\",false,extra",
            '44,Needs review',
            '',
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $widthSummary = $packet['rowWidthSummary'] ?? [];
        $repairSummary = $packet['rowRepairSummary'] ?? [];
        $codes = array_map(static fn (array $diagnostic): string => $diagnostic['code'], $packet['diagnostics'] ?? []);

        $t->same('csv', $packet['format'] ?? null);
        $t->same(['id', 'title', 'published', ''], $packet['columnNames'] ?? null);
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['raggedRowCount'] ?? null);
        $t->same([0, 1, 3], $packet['raggedRows'] ?? null);
        $t->same([3, 3, 4, 2], $widthSummary['rowWidths'] ?? null);
        $t->same([0, 1, 2, 3], $widthSummary['sourceRowIndexes'] ?? null);
        $t->same([
            ['width' => 2, 'count' => 1],
            ['width' => 3, 'count' => 2],
            ['width' => 4, 'count' => 1],
        ], $widthSummary['widthCounts'] ?? null);
        $t->same(false, $widthSummary['strict']['consistent'] ?? null);
        $t->same(2, $widthSummary['strict']['mismatchCount'] ?? null);
        $t->same('source-row-2', $widthSummary['strict']['mismatches'][0]['rowLabel'] ?? null);
        $t->same(1, $widthSummary['strict']['mismatches'][0]['extraFields'] ?? null);
        $t->same(1, $widthSummary['strict']['mismatches'][1]['missingFields'] ?? null);
        $t->same(3, $widthSummary['relaxed']['paddedRowCount'] ?? null);
        $t->same(2, $widthSummary['header']['mismatchCount'] ?? null);
        $t->same('relaxed-pad-to-wide-row', $repairSummary['policy'] ?? null);
        $t->same([3, 3, 4, 2], $repairSummary['originalColumnCounts'] ?? null);
        $t->same(4, $repairSummary['repairedColumnCount'] ?? null);
        $t->same(3, $repairSummary['changedRowCount'] ?? null);
        $t->same(3, $repairSummary['paddedRowCount'] ?? null);
        $t->same(0, $repairSummary['truncatedRowCount'] ?? null);
        $t->same('padded', $repairSummary['rows'][0]['repair'] ?? null);
        $t->same(3, $repairSummary['rows'][0]['originalColumnCount'] ?? null);
        $t->same(4, $repairSummary['rows'][0]['repairedColumnCount'] ?? null);
        $t->same(1, $repairSummary['rows'][0]['missingFieldsAdded'] ?? null);
        $t->same('unchanged', $repairSummary['rows'][2]['repair'] ?? null);
        $t->same('padded', $repairSummary['rows'][3]['repair'] ?? null);
        $t->same(2, $repairSummary['rows'][3]['missingFieldsAdded'] ?? null);
        $t->same([
            'delimited-text-multiline-quoted-field',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], $codes);
        $t->same('', $table->children[0]->children[0]->children[3]->attr('text'));
        $t->same("Two\nline title", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('extra', $table->children[1]->children[1]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));
    },
    'records tsv blank rows trailing empty fields and repair summaries' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readTsv(implode("\n", [
            "key\tvalue\t",
            '',
            "alpha\t10\t",
            "beta\t20",
            "gamma\t30\t",
            '',
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $widthSummary = $packet['rowWidthSummary'] ?? [];
        $repairSummary = $packet['rowRepairSummary'] ?? [];
        $codes = array_map(static fn (array $diagnostic): string => $diagnostic['code'], $packet['diagnostics'] ?? []);

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(11, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['blankRowCount'] ?? null);
        $t->same([1], $packet['blankRows'] ?? null);
        $t->same([3, 3, 2, 3], $widthSummary['rowWidths'] ?? null);
        $t->same([0, 2, 3, 4], $widthSummary['sourceRowIndexes'] ?? null);
        $t->same([0, 2, 4], $widthSummary['trailingEmptyFieldRows'] ?? null);
        $t->same(false, $widthSummary['strict']['consistent'] ?? null);
        $t->same(1, $widthSummary['strict']['mismatchCount'] ?? null);
        $t->same(3, $widthSummary['strict']['mismatches'][0]['sourceRow'] ?? null);
        $t->same(1, $widthSummary['strict']['mismatches'][0]['missingFields'] ?? null);
        $t->same(3, $widthSummary['relaxed']['columnCount'] ?? null);
        $t->same(1, $widthSummary['relaxed']['paddedRowCount'] ?? null);
        $t->same('source-row-3', $widthSummary['relaxed']['paddedRows'][0]['rowLabel'] ?? null);
        $t->same(1, $widthSummary['header']['mismatchCount'] ?? null);
        $t->same('relaxed-pad-to-wide-row', $repairSummary['policy'] ?? null);
        $t->same([3, 3, 2, 3], $repairSummary['originalColumnCounts'] ?? null);
        $t->same(3, $repairSummary['repairedColumnCount'] ?? null);
        $t->same(1, $repairSummary['changedRowCount'] ?? null);
        $t->same(1, $repairSummary['paddedRowCount'] ?? null);
        $t->same(0, $repairSummary['truncatedRowCount'] ?? null);
        $t->same('padded', $repairSummary['paddedRows'][0]['repair'] ?? null);
        $t->same(2, $repairSummary['paddedRows'][0]['originalColumnCount'] ?? null);
        $t->same(3, $repairSummary['paddedRows'][0]['repairedColumnCount'] ?? null);
        $t->same(1, $repairSummary['paddedRows'][0]['missingFieldsAdded'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-blank-rows-skipped',
            'delimited-text-trailing-empty-fields-preserved',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], $codes);
        $t->same('', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(3, count($table->children[1]->children));
    },
];
