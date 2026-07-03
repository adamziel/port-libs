<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;
use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$upstreamCsvCommandFixture = static function (): array {
    $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-current-csv-reader/csv.md');
    if (preg_match('/% pandoc -f csv -t native\n(?P<input>.*?)\n\^D\n(?P<native>\[ Table.*\n\])\n```/s', $fixture, $matches) !== 1) {
        throw new RuntimeException('Unable to parse checked-in upstream CSV command fixture');
    }

    return [
        'input' => $matches['input'] . "\n",
        'native' => $matches['native'],
    ];
};

$generatedTsvNativeFixture = static function (string $name = 'simple'): array {
    $root = dirname(__DIR__) . '/fixtures/generated-current-tsv-reader';

    return [
        'input' => (string) file_get_contents($root . '/' . $name . '.tsv'),
        'native' => (string) file_get_contents($root . '/' . $name . '.native'),
    ];
};

$nativeTokenStream = static function (string $native): string {
    $native = (string) preg_replace('/\[\s*\]/', '[]', $native);
    $native = (string) preg_replace('/\(\s*""\s*,\s*\[\]\s*,\s*\[\]\s*\)/', '("",[],[])', $native);

    return (string) preg_replace('/\s+/', ' ', trim($native));
};

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
        $t->same([
            'test/command/csv.md',
            'test/command/01.csv',
        ], $packet['upstreamEvidence']['fixtures'] ?? null);
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
    'matches pinned upstream csv command reader semantics' => static function (TestRunner $t) use ($upstreamCsvCommandFixture, $nativeTokenStream): void {
        $fixture = $upstreamCsvCommandFixture();
        $document = (new DelimitedTextReader())->readCsv($fixture['input']);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');

        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->contains(DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $packet['upstreamEvidence']['source'] ?? '');
        $t->same(['Fruit', 'Price', 'Quantity'], $table->attr('columnNames'));
        $t->same('Apple', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('25 cents', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('"Navel" Orange', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('35 cents', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same([], $table->children[1]->children[2]->children[0]->children);
        $t->same('', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same([], $table->children[1]->children[2]->children[1]->children);
        $t->same('45', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "\"Navel\"" , Space , Str "Orange" ]', $native);
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture();
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2, $generatedEvidence['sampleCount'] ?? null);
        $t->same(4, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('simple.tsv', $generatedEvidence['checkedInFixtures'][0]['name'] ?? null);
        $t->same('fcee0aed5a2fde11bbd19f2fc4445357a0d7bbd9c9962df6630fed4b6178ff8e', $generatedEvidence['checkedInFixtures'][0]['checkedInFile']['sha256'] ?? null);
        $t->same(['Fruit', 'Price', 'Quantity'], $table->attr('columnNames'));
        $t->same('"Navel" Orange', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same('45', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "\"Navel\"" , Space , Str "Orange" ]', $native);
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv quote trailing native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('quote-trailing');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2, $generatedEvidence['sampleCount'] ?? null);
        $t->same('quote-trailing.tsv', $generatedEvidence['checkedInFixtures'][2]['name'] ?? null);
        $t->same('c5694bc5e74a5920c4752369bd967be614f3d7f8fde6395bcd05c9b5f22d85dd', $generatedEvidence['checkedInFixtures'][2]['checkedInFile']['sha256'] ?? null);
        $t->same('quote-trailing.native', $generatedEvidence['checkedInFixtures'][3]['name'] ?? null);
        $t->same('51b8ce6dc3164f654f50f7fc1597e2788b04a2b634a32a3f52d51951b68260b6', $generatedEvidence['checkedInFixtures'][3]['checkedInFile']['sha256'] ?? null);
        $t->same(['id', 'note', 'status', ''], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(15, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([2], $packet['raggedRows'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same([0, 1, 3], $packet['trailingDelimiterRows'] ?? null);
        $t->same('literal "quotes" stay', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('comma, pipe | stay', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same('final', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "literal" , Space , Str "\"quotes\"" , Space , Str "stay" ]', $native);
        $t->contains('Plain [ Str "comma," , Space , Str "pipe" , Space , Str "|" , Space , Str "stay" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches pinned upstream csv parser option fixtures' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $commaDocument = $reader->readCsv(
            "\"Albatross\", 2.99, \"On a stick!\"\n"
            . "\"Crunchy Frog\", 1.49, \"If we took the bones out, it wouldn't be\n"
            . "crunchy, now would it?\"\n",
            ['header' => false]
        );
        $spaceDocument = $reader->readCsv(implode("\n", [
            "'' 'a' 'b'",
            "'cat''s' 3 4",
            "'dog''s' 2 3",
            '',
        ]), [
            'delimiter' => 'space',
            'quote' => "'",
        ]);
        $escapeDocument = $reader->readCsv("\"1\",\"\\\"\"\n", [
            'escape' => '\\',
            'header' => false,
        ]);
        $keepSpaceDocument = $reader->readCsv("\"A\",  B\n", [
            'header' => false,
            'keepSpace' => true,
        ]);
        $semicolonDocument = $reader->readCsv("\"Column1\";\"Column2\"\n\"Data1\";\"- data1\n\n- data2\"", [
            'delimiter' => 'semicolon',
        ]);

        $commaTable = $commaDocument->children[0];
        $commaPacket = $commaTable->attr('delimitedText');
        $multilineCell = $commaTable->children[1]->children[1]->children[2];
        $multilineInlines = $multilineCell->children[0]->children;
        $spaceTable = $spaceDocument->children[0];
        $spacePacket = $spaceTable->attr('delimitedText');
        $escapeTable = $escapeDocument->children[0];
        $escapePacket = $escapeTable->attr('delimitedText');
        $keepSpaceTable = $keepSpaceDocument->children[0];
        $keepSpacePacket = $keepSpaceTable->attr('delimitedText');
        $semicolonTable = $semicolonDocument->children[0];
        $semicolonCell = $semicolonTable->children[1]->children[0]->children[1];

        $t->same(false, $commaPacket['headerRow'] ?? null);
        $t->same('2.99', $commaTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('On a stick!', $commaTable->children[1]->children[0]->children[2]->attr('text'));
        $t->same('Crunchy Frog', $commaTable->children[1]->children[1]->children[0]->attr('text'));
        $t->same("If we took the bones out, it wouldn't be\ncrunchy, now would it?", $multilineCell->attr('text'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $multilineInlines));
        $t->same("If we took the bones out, it wouldn't be", $multilineInlines[0]->attr('text'));
        $t->same('crunchy, now would it?', $multilineInlines[2]->attr('text'));
        $t->same([1], $commaPacket['multilineQuotedRows'] ?? null);
        $t->same(1, $commaPacket['multilineFieldCount'] ?? null);

        $t->same(' ', $spacePacket['delimiter'] ?? null);
        $t->same('space', $spacePacket['delimiterName'] ?? null);
        $t->same("'", $spacePacket['quote'] ?? null);
        $t->same('', $spaceTable->children[0]->children[0]->children[0]->attr('text'));
        $t->same('a', $spaceTable->children[0]->children[0]->children[1]->attr('text'));
        $t->same('b', $spaceTable->children[0]->children[0]->children[2]->attr('text'));
        $t->same("cat's", $spaceTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same("dog's", $spaceTable->children[1]->children[1]->children[0]->attr('text'));

        $t->same('\\', $escapePacket['escape'] ?? null);
        $t->same('escape-character', $escapePacket['dialect']['escapeMode'] ?? null);
        $t->same('"', $escapeTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same(1, $escapePacket['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $escapePacket['doubledQuoteEscapeCount'] ?? null);

        $t->same(true, $keepSpacePacket['dialect']['keepSpace'] ?? null);
        $t->same('  B', $keepSpaceTable->children[1]->children[0]->children[1]->attr('text'));

        $t->same(';', $semicolonTable->attr('delimitedText')['delimiter'] ?? null);
        $t->same(['Column1', 'Column2'], $semicolonTable->attr('columnNames'));
        $t->same('Data1', $semicolonTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same("- data1\n\n- data2", $semicolonCell->attr('text'));
        $t->same(['text', 'linebreak', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $semicolonCell->children[0]->children));
    },
    'records csv and tsv upstream evidence boundaries separately' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $csvPacket = $reader->readCsv("Fruit,Price\nApple,25 cents\n")->children[0]->attr('delimitedText');
        $tsvPacket = $reader->readTsv("Fruit\tPrice\nApple\t25 cents\n")->children[0]->attr('delimitedText');
        $csvEvidence = $csvPacket['upstreamEvidence'] ?? [];
        $tsvEvidence = $tsvPacket['upstreamEvidence'] ?? [];

        $t->same('csv', $csvEvidence['reader'] ?? null);
        $t->same(2, $csvEvidence['denominator'] ?? null);
        $t->same('direct-reader-fixtures', $csvEvidence['denominatorScope'] ?? null);
        $t->same('csv', $csvEvidence['selectedDirectFixtureFormat'] ?? null);
        $t->same(2, $csvEvidence['directFixtureDenominator'] ?? null);
        $t->same(2, $csvEvidence['directFixtureCount'] ?? null);
        $t->same([
            'test/command/csv.md',
            'test/command/01.csv',
        ], $csvEvidence['directFixtures'] ?? null);
        $t->same($csvEvidence['directFixtures'] ?? null, $csvEvidence['fixtures'] ?? null);
        $t->same(2, $csvEvidence['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $csvEvidence['tsvDirectFixtureDenominator'] ?? null);
        $t->same(5, $csvEvidence['parserOptionFixtureCount'] ?? null);
        $t->same([
            'comma-delimiter-no-header',
            'space-delimiter-single-quote',
            'backslash-escaped-quote',
            'keep-space-after-delimiter',
            'semicolon-delimiter-multiline-cell',
        ], $csvEvidence['parserOptionFixtures'] ?? null);
        $t->same(2, $csvEvidence['integrationFixtureCount'] ?? null);
        $t->same([
            'test/command/3533-rst-csv-tables.csv',
            'test/command/3533-rst-csv-tables.md',
        ], $csvEvidence['integrationFixtures'] ?? null);
        $t->true(in_array('direct-csv-command-reader', $csvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('rst-csv-table-integration-requires-rst-reader', $csvEvidence['openGaps'] ?? [], true));
        $t->same('not-run', $csvEvidence['runnerEvidence']['status'] ?? null);
        $t->same(false, $csvEvidence['runnerEvidence']['executed'] ?? null);
        $t->same(null, $csvEvidence['runnerEvidence']['command'] ?? null);
        $t->same('upstream-haskell-runner', $csvEvidence['notRunEvidence'][0]['scope'] ?? null);
        $t->true(in_array('upstream-runner-not-run', $csvEvidence['openGaps'] ?? [], true));
        $csvStatic = $csvEvidence['staticCurrentEvidence'] ?? [];
        $t->same('valid-checked-in-current-delimited-text-reader-evidence', $csvStatic['validation']['status'] ?? null);
        $t->same([], $csvStatic['validation']['issues'] ?? null);
        $t->same(2, $csvStatic['readerDenominator']['csvDirectFixtureCount'] ?? null);
        $t->same(0, $csvStatic['readerDenominator']['tsvDirectFixtureCount'] ?? null);
        $t->same(2, $csvStatic['checkedInFixtureCount'] ?? null);
        $t->same('test/command/csv.md', $csvStatic['checkedInFixtures'][0]['upstreamPath'] ?? null);
        $t->same('42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6', $csvStatic['checkedInFixtures'][0]['checkedInFile']['sha256'] ?? null);
        $t->same(2719, $csvStatic['checkedInFixtures'][0]['checkedInFile']['bytes'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $csvStatic['generatedTsvNativeStaticEvidence']['validation']['status'] ?? null);
        $t->same(2, $csvStatic['generatedTsvNativeStaticEvidence']['sampleCount'] ?? null);
        $t->same(0, $csvStatic['generatedTsvNativeStaticEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence(['staticCurrentEvidence' => $csvStatic]));

        $t->same('tsv', $tsvEvidence['reader'] ?? null);
        $t->same(0, $tsvEvidence['denominator'] ?? null);
        $t->same('direct-reader-fixtures', $tsvEvidence['denominatorScope'] ?? null);
        $t->same([], $tsvEvidence['fixtures'] ?? null);
        $t->same('tsv', $tsvEvidence['selectedDirectFixtureFormat'] ?? null);
        $t->same(0, $tsvEvidence['directFixtureDenominator'] ?? null);
        $t->same(0, $tsvEvidence['directFixtureCount'] ?? null);
        $t->same([], $tsvEvidence['directFixtures'] ?? null);
        $t->same(2, $tsvEvidence['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $tsvEvidence['tsvDirectFixtureDenominator'] ?? null);
        $t->same(0, $tsvEvidence['parserOptionFixtureCount'] ?? null);
        $t->same(2, $tsvEvidence['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $tsvEvidence['generatedNativeParityEvidence']['validation']['status'] ?? null);
        $t->same('simple', $tsvEvidence['generatedNativeParityEvidence']['samples'][0]['name'] ?? null);
        $t->same('quote-trailing', $tsvEvidence['generatedNativeParityEvidence']['samples'][1]['name'] ?? null);
        $t->true(in_array('tsv-tab-delimiter-reader', $tsvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('generated-tsv-native-parity-sample', $tsvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('no-dedicated-upstream-tsv-command-fixture-in-pinned-corpus', $tsvEvidence['openGaps'] ?? [], true));
        $t->true(in_array('upstream-runner-not-run', $tsvEvidence['openGaps'] ?? [], true));
        $t->same('not-run', $tsvEvidence['runnerEvidence']['status'] ?? null);
        $t->same(false, $tsvEvidence['runnerEvidence']['executed'] ?? null);
        $t->same(null, $tsvEvidence['runnerEvidence']['resultArtifact'] ?? null);
        $t->same('upstream-haskell-runner', $tsvEvidence['notRunEvidence'][0]['scope'] ?? null);
        $t->true(in_array('TSV is an upstream input token but the pinned command corpus evidence is CSV-only.', $tsvEvidence['claimBoundaries'] ?? [], true));
        $t->same($csvEvidence['staticCurrentEvidence'] ?? null, $tsvEvidence['staticCurrentEvidence'] ?? null);
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
    'reads csv and tsv through the pandoc converter registry' => static function (TestRunner $t): void {
        $csvDocument = PandocConverter::read(implode("\n", [
            'name,qty',
            'Alpha,10',
            '',
        ]), 'csv');
        $tsvBlocks = PandocConverter::convert(implode("\n", [
            "name\tqty",
            "Beta\t20",
            '',
        ]), 'tsv', 'blocks');

        $t->true(PandocConverter::canRead('csv'));
        $t->true(PandocConverter::canRead('tsv'));
        $t->same('csv', $csvDocument->attr('sourceFormat'));
        $t->same('table', $csvDocument->children[0]->type);
        $t->same('Alpha', $csvDocument->children[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<th>name</th><th>qty</th>', $tsvBlocks);
        $t->contains('<td>Beta</td><td>20</td>', $tsvBlocks);
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
            'delimited-text-input-prefix-utf8-bom',
            'delimited-text-input-prefix-null-byte',
            'delimited-text-input-prefix-control-character',
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
            'delimited-text-input-prefix-control-character',
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
        $t->contains('| 42 | Legacy, \\"quoted\\" title | true  |', $csvMarkdown);
        $t->contains('<tbody><tr><td>42</td><td>Legacy, &quot;quoted&quot; title</td><td>true</td></tr>', $csvWordpress);
        $t->true(!str_contains($csvWordpress, '<thead>'));
        $csvHead = $csvJson['blocks'][0]['c'][3]['c'] ?? $csvJson['blocks'][0]['c'][3];
        $t->same([], $csvHead[1] ?? null);

        $t->same(false, $tsvPacket['headerRow'] ?? null);
        $t->same('tab', $tsvPacket['delimiter'] ?? null);
        $t->same(['column1', 'column2'], $tsvPacket['columnNames'] ?? null);
        $t->same(2, $tsvPacket['rowCount'] ?? null);
        $t->same(2, $tsvPacket['bodyRowCount'] ?? null);
        $t->same('A', $tsvTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('20', $tsvTable->children[1]->children[1]->children[1]->attr('text'));
    },
    'records csv bom and leading whitespace prefix diagnostics without losing the header row' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv("\xEF\xBB\xBF  \r\nsource_id,title\n42,Post\n", [
            'extension' => '.csv',
            'sourcePath' => 'exports/posts.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $prefix = $packet['inputPrefix'] ?? [];
        $codes = array_column($packet['diagnostics'] ?? [], 'code');

        $t->same('csv', $document->attr('sourceFormat'));
        $t->same(['source_id', 'title'], $packet['columnNames'] ?? null);
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same('42', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Post', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('bounded-input-prefix-review', $prefix['policy'] ?? null);
        $t->same('utf-8', $prefix['encoding'] ?? null);
        $t->same('utf-8', $prefix['bom'] ?? null);
        $t->same(3, $prefix['bomByteCount'] ?? null);
        $t->same(4, $prefix['leadingWhitespaceByteCount'] ?? null);
        $t->same(1, $prefix['leadingWhitespaceLineCount'] ?? null);
        $t->same(7, $prefix['firstContentOffset'] ?? null);
        $t->same(2, $prefix['firstContentLine'] ?? null);
        $t->same(31, $prefix['inputByteCount'] ?? null);
        $t->same(64, $prefix['inspectionByteLimit'] ?? null);
        $t->same(31, $prefix['inspectedByteCount'] ?? null);
        $t->same(false, $prefix['inspectionTruncated'] ?? null);
        $t->same(32, $prefix['prefixPreviewByteLimit'] ?? null);
        $t->same(31, $prefix['prefixPreviewByteCount'] ?? null);
        $t->same('efbbbf20200d0a', substr((string) ($prefix['prefixPreviewHex'] ?? ''), 0, 14));
        $t->same(0, $prefix['nullByteCount'] ?? null);
        $t->same(0, $prefix['controlCharacterCount'] ?? null);
        $t->same([
            'requestedFormat' => 'csv',
            'selectedFormat' => 'csv',
            'sourcePath' => 'exports/posts.csv',
            'sourcePathExtension' => 'csv',
            'sourcePathFormat' => 'csv',
            'extension' => '.csv',
            'extensionFormat' => 'csv',
            'contextFormats' => ['csv'],
            'formatMatchesContext' => true,
            'contextConflict' => false,
        ], $prefix['formatContext'] ?? null);
        $t->same([
            'delimited-text-input-prefix-utf8-bom',
            'delimited-text-input-prefix-leading-whitespace',
        ], $codes);
    },
    'records tsv null-byte and control-character prefix diagnostics with source path context' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readTsv("name\tqty\nA\x00\t10\nB\x1F\t20\n", [
            'sourcePath' => 'exports/inventory.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $prefix = $packet['inputPrefix'] ?? [];
        $codes = array_column($packet['diagnostics'] ?? [], 'code');

        $t->same('tsv', $document->attr('sourceFormat'));
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(['name', 'qty'], $packet['columnNames'] ?? null);
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same("A\x00", $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same("B\x1F", $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('none', $prefix['bom'] ?? null);
        $t->same(0, $prefix['leadingWhitespaceByteCount'] ?? null);
        $t->same(1, $prefix['nullByteCount'] ?? null);
        $t->same([
            [
                'offset' => 10,
                'hex' => '00',
                'name' => 'NUL',
            ],
        ], $prefix['nullBytes'] ?? null);
        $t->same(1, $prefix['controlCharacterCount'] ?? null);
        $t->same([
            [
                'offset' => 16,
                'hex' => '1F',
                'name' => 'US',
            ],
        ], $prefix['controlCharacters'] ?? null);
        $t->same([
            'requestedFormat' => 'tsv',
            'selectedFormat' => 'tsv',
            'sourcePath' => 'exports/inventory.tsv',
            'sourcePathExtension' => 'tsv',
            'sourcePathFormat' => 'tsv',
            'extension' => null,
            'extensionFormat' => null,
            'contextFormats' => ['tsv'],
            'formatMatchesContext' => true,
            'contextConflict' => false,
        ], $prefix['formatContext'] ?? null);
        $t->same([
            'delimited-text-input-prefix-null-byte',
            'delimited-text-input-prefix-control-character',
            'delimited-text-control-characters',
        ], $codes);
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
        $diagnosticsByCode = [];
        foreach ($packet['diagnostics'] ?? [] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']] = $diagnostic;
        }

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
            'delimited-text-input-prefix-utf8-bom',
            'delimited-text-multiline-quoted-field',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], $codes);
        $strictDiagnostic = $diagnosticsByCode['delimited-text-strict-row-width-mismatch'] ?? [];
        $relaxedDiagnostic = $diagnosticsByCode['delimited-text-row-widths-uneven'] ?? [];
        $headerDiagnostic = $diagnosticsByCode['delimited-text-header-width-mismatch'] ?? [];
        $t->same('header-row', $strictDiagnostic['policy'] ?? null);
        $t->same(3, $strictDiagnostic['expectedColumnCount'] ?? null);
        $t->same(2, $strictDiagnostic['mismatchCount'] ?? null);
        $t->same(['source-row-2', 'source-row-3'], array_column($strictDiagnostic['mismatches'] ?? [], 'rowLabel'));
        $t->same('pad-to-wide-row', $relaxedDiagnostic['policy'] ?? null);
        $t->same(4, $relaxedDiagnostic['columnCount'] ?? null);
        $t->same(3, $relaxedDiagnostic['paddedRowCount'] ?? null);
        $t->same(['source-row-0', 'source-row-1', 'source-row-3'], array_column($relaxedDiagnostic['paddedRows'] ?? [], 'rowLabel'));
        $t->same(3, $headerDiagnostic['headerColumnCount'] ?? null);
        $t->same([3, 4, 2], $headerDiagnostic['dataColumnCounts'] ?? null);
        $t->same(2, $headerDiagnostic['mismatchCount'] ?? null);
        $t->same(['source-row-2', 'source-row-3'], array_column($headerDiagnostic['mismatches'] ?? [], 'rowLabel'));
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
        $diagnosticsByCode = [];
        foreach ($packet['diagnostics'] ?? [] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']] = $diagnostic;
        }

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
        $blankDiagnostic = $diagnosticsByCode['delimited-text-blank-rows-skipped'] ?? [];
        $trailingDiagnostic = $diagnosticsByCode['delimited-text-trailing-empty-fields-preserved'] ?? [];
        $strictDiagnostic = $diagnosticsByCode['delimited-text-strict-row-width-mismatch'] ?? [];
        $relaxedDiagnostic = $diagnosticsByCode['delimited-text-row-widths-uneven'] ?? [];
        $headerDiagnostic = $diagnosticsByCode['delimited-text-header-width-mismatch'] ?? [];
        $t->same(1, $blankDiagnostic['blankRowCount'] ?? null);
        $t->same([1], $blankDiagnostic['rows'] ?? null);
        $t->same([0, 2, 4], $trailingDiagnostic['rows'] ?? null);
        $t->same('header-row', $strictDiagnostic['policy'] ?? null);
        $t->same(3, $strictDiagnostic['expectedColumnCount'] ?? null);
        $t->same(1, $strictDiagnostic['mismatchCount'] ?? null);
        $t->same(['source-row-3'], array_column($strictDiagnostic['mismatches'] ?? [], 'rowLabel'));
        $t->same('pad-to-wide-row', $relaxedDiagnostic['policy'] ?? null);
        $t->same(3, $relaxedDiagnostic['columnCount'] ?? null);
        $t->same(1, $relaxedDiagnostic['paddedRowCount'] ?? null);
        $t->same(['source-row-3'], array_column($relaxedDiagnostic['paddedRows'] ?? [], 'rowLabel'));
        $t->same(3, $headerDiagnostic['headerColumnCount'] ?? null);
        $t->same([3, 2, 3], $headerDiagnostic['dataColumnCounts'] ?? null);
        $t->same(1, $headerDiagnostic['mismatchCount'] ?? null);
        $t->same(['source-row-3'], array_column($headerDiagnostic['mismatches'] ?? [], 'rowLabel'));
        $t->same('', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(3, count($table->children[1]->children));
    },
];
