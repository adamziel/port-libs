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

$generatedCsvNativeFixture = static function (string $name = 'quoted-multiline'): array {
    $root = dirname(__DIR__) . '/fixtures/generated-current-csv-reader';

    return [
        'input' => (string) file_get_contents($root . '/' . $name . '.csv'),
        'native' => (string) file_get_contents($root . '/' . $name . '.native'),
    ];
};

$nativeTokenStream = static function (string $native): string {
    $native = str_replace('\\12', '\\f', $native);
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
    'matches upstream csv header width when body rows are wider or shorter' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv("a,b\n1,2,3\n4\n");
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $body = $table->children[1];
        $native = PandocConverter::write($document, 'native');

        $t->same(['a', 'b'], $table->attr('columnNames'));
        $t->same(2, TableGeometry::columnCount($table));
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(3, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(['1', '2'], array_map(static fn (AstNode $cell): string => $cell->attr('text'), $body->children[0]->children));
        $t->same(['4', ''], array_map(static fn (AstNode $cell): string => $cell->attr('text'), $body->children[1]->children));
        $t->same('header-row-width', $packet['rowRepairSummary']['policy'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['truncatedRowCount'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->true(!str_contains($native, 'Str "3"'));
    },
    'matches generated csv quoted multiline native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture();
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['staticCurrentEvidence']['generatedCsvNativeStaticEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same(2, $generatedEvidence['csvDirectFixtureDenominator'] ?? null);
        $t->same([], $generatedEvidence['samples'][0]['readerOptions'] ?? null);
        $t->same('quoted-multiline.csv', $generatedEvidence['checkedInFixtures'][0]['name'] ?? null);
        $t->same('a038fe6edd54cf98e2b3afaf14dd4e5cbdbbdb86ab2b62d9bd60cd783ce3324e', $generatedEvidence['checkedInFixtures'][0]['checkedInFile']['sha256'] ?? null);
        $t->same(['id', 'title', 'note', 'flag'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(14, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same([2], $packet['trailingDelimiterRows'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([3], $packet['raggedRows'] ?? null);
        $t->same('Legacy, "quoted" title', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("two\nline", $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[3]->attr('text'));
        $t->contains('Plain [ Str "Legacy," , Space , Str "\"quoted\"" , Space , Str "title" ]', $native);
        $t->contains('Plain [ Str "two" , LineBreak , Str "line" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv post delimiter space native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('post-delimiter-space');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['staticCurrentEvidence']['generatedCsvNativeStaticEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('post-delimiter-space.csv', $generatedEvidence['checkedInFixtures'][2]['name'] ?? null);
        $t->same('109867931d7a1d37a49d565c175d085415b378800e2acd2d4ec8f1c24935601f', $generatedEvidence['checkedInFixtures'][2]['checkedInFile']['sha256'] ?? null);
        $t->same('post-delimiter-space.native', $generatedEvidence['checkedInFixtures'][3]['name'] ?? null);
        $t->same('766278b6bf6c85a71a50a50df5c8ee776c7e774020897f8f39e34d9841a9c8d1', $generatedEvidence['checkedInFixtures'][3]['checkedInFile']['sha256'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(5, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['quotedLineBreakCount'] ?? null);
        $t->same(0, $packet['multilineFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('title', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('trimmed after comma', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('alpha, beta', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('quote "inside"', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('closing quote ignores spaces', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "alpha," , Space , Str "beta" ]', $native);
        $t->contains('Plain [ Str "quote" , Space , Str "\"inside\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv post delimiter tab native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('post-delimiter-tab');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('post-delimiter-tab.csv', $generatedEvidence['checkedInFixtures'][76]['name'] ?? null);
        $t->same('330ad95f24c4f732fb00c829df8ba209be3972e1c424a4734c60d34e535fbc43', $generatedEvidence['checkedInFixtures'][76]['checkedInFile']['sha256'] ?? null);
        $t->same('post-delimiter-tab.native', $generatedEvidence['checkedInFixtures'][77]['name'] ?? null);
        $t->same('c9c3e78d71ded4a8b14f2fe66631b11f58a914683a18ef59dc51f99f3c4e7215', $generatedEvidence['checkedInFixtures'][77]['checkedInFile']['sha256'] ?? null);
        $t->same('post-delimiter-tab', $generatedEvidence['samples'][38]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][38]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quotedLineBreakCount'] ?? null);
        $t->same(0, $packet['multilineFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('title', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('note', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('tab after comma', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('plain', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('unquoted tab trim', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('quoted note', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "tab" , Space , Str "after" , Space , Str "comma" ]', $native);
        $t->contains('Plain [ Str "unquoted" , Space , Str "tab" , Space , Str "trim" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv backslash escaped quote native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('backslash-escaped-quote');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv',
            'escape' => '\\',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same('\\', $packet['escape'] ?? null);
        $t->same('escape-character', $packet['dialect']['escapeMode'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('backslash-escaped-quote.csv', $generatedEvidence['checkedInFixtures'][4]['name'] ?? null);
        $t->same('ae11512ae25941072ef5c297914c544a0815f2a2aba9527a9c80ca1ac5aa406e', $generatedEvidence['checkedInFixtures'][4]['checkedInFile']['sha256'] ?? null);
        $t->same('backslash-escaped-quote.native', $generatedEvidence['checkedInFixtures'][5]['name'] ?? null);
        $t->same('0a512d33990f2629025b2eaae15e34d070fe5e985926e6d2d06d2937ac8ef1b5', $generatedEvidence['checkedInFixtures'][5]['checkedInFile']['sha256'] ?? null);
        $t->same(['escape' => '\\'], $generatedEvidence['samples'][2]['readerOptions'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(6, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('"', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('left " right', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "\"" ]', $native);
        $t->contains('Plain [ Str "left" , Space , Str "\"" , Space , Str "right" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quoted linebreak native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-linebreak');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-linebreak.csv', $generatedEvidence['checkedInFixtures'][6]['name'] ?? null);
        $t->same('b017e1cc1434c3422538e1b16fb240ae2c35b0bda12041f568cf5da7921b0476', $generatedEvidence['checkedInFixtures'][6]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-linebreak.native', $generatedEvidence['checkedInFixtures'][7]['name'] ?? null);
        $t->same('84472dfb9a0d40daf8c8c38cd50892cd2e13e8118e133ebfcac3720a16ae54f8', $generatedEvidence['checkedInFixtures'][7]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-linebreak', $generatedEvidence['samples'][3]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][3]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(true, $packet['finalRecordTerminated'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same("alpha\nbeta", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('gamma', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "alpha" , LineBreak , Str "beta" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches pandoc 3.10 csv quoted crlf cell native parity without inflating corpus counts' => static function (TestRunner $t) use ($nativeTokenStream): void {
        $input = "id,note,status\n1,\"alpha\r\nbeta\",ok\n2,gamma,done\n";
        $expectedPandocNative = <<<'NATIVE'
[ Table
    ( "" , [] , [] )
    (Caption Nothing [])
    [ ( AlignDefault , ColWidthDefault )
    , ( AlignDefault , ColWidthDefault )
    , ( AlignDefault , ColWidthDefault )
    ]
    (TableHead
       ( "" , [] , [] )
       [ Row
           ( "" , [] , [] )
           [ Cell
               ( "" , [] , [] )
               AlignDefault
               (RowSpan 1)
               (ColSpan 1)
               [ Plain [ Str "id" ] ]
           , Cell
               ( "" , [] , [] )
               AlignDefault
               (RowSpan 1)
               (ColSpan 1)
               [ Plain [ Str "note" ] ]
           , Cell
               ( "" , [] , [] )
               AlignDefault
               (RowSpan 1)
               (ColSpan 1)
               [ Plain [ Str "status" ] ]
           ]
       ])
    [ TableBody
        ( "" , [] , [] )
        (RowHeadColumns 0)
        []
        [ Row
            ( "" , [] , [] )
            [ Cell
                ( "" , [] , [] )
                AlignDefault
                (RowSpan 1)
                (ColSpan 1)
                [ Plain [ Str "1" ] ]
            , Cell
                ( "" , [] , [] )
                AlignDefault
                (RowSpan 1)
                (ColSpan 1)
                [ Plain [ Str "alpha" , LineBreak , Str "beta" ] ]
            , Cell
                ( "" , [] , [] )
                AlignDefault
                (RowSpan 1)
                (ColSpan 1)
                [ Plain [ Str "ok" ] ]
            ]
        , Row
            ( "" , [] , [] )
            [ Cell
                ( "" , [] , [] )
                AlignDefault
                (RowSpan 1)
                (ColSpan 1)
                [ Plain [ Str "2" ] ]
            , Cell
                ( "" , [] , [] )
                AlignDefault
                (RowSpan 1)
                (ColSpan 1)
                [ Plain [ Str "gamma" ] ]
            , Cell
                ( "" , [] , [] )
                AlignDefault
                (RowSpan 1)
                (ColSpan 1)
                [ Plain [ Str "done" ] ]
            ]
        ]
    ]
    (TableFoot ( "" , [] , [] ) [])
]
NATIVE;

        $document = (new DelimitedTextReader())->readCsv($input);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];
        $linebreakCell = $table->children[1]->children[0]->children[1];

        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['inputPrefix']['carriageReturnNormalization']['removedCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-multiline-quoted-field'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same("alpha\nbeta", $linebreakCell->attr('text'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $linebreakCell->children[0]->children));
        $t->same('alpha', $linebreakCell->children[0]->children[0]->attr('text'));
        $t->same('beta', $linebreakCell->children[0]->children[2]->attr('text'));
        $t->contains('Plain [ Str "alpha" , LineBreak , Str "beta" ]', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same($nativeTokenStream($expectedPandocNative), $nativeTokenStream($native));
    },
    'matches pandoc 3.10 csv quoted blank-line cell generated native parity fixture' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-blank-line-cell');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];
        $blankLineCell = $table->children[1]->children[0]->children[1];

        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(6, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedLineBreakCount'] ?? null);
        $t->same(2, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-multiline-quoted-field'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same("alpha\n\nbeta", $blankLineCell->attr('text'));
        $t->same(
            ['text', 'linebreak', 'linebreak', 'text'],
            array_map(static fn (AstNode $node): string => $node->type, $blankLineCell->children[0]->children)
        );
        $t->same('alpha', $blankLineCell->children[0]->children[0]->attr('text'));
        $t->same('beta', $blankLineCell->children[0]->children[3]->attr('text'));
        $t->contains('Plain [ Str "alpha" , LineBreak , LineBreak , Str "beta" ]', $native);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-blank-line-cell.csv', $generatedEvidence['checkedInFixtures'][78]['name'] ?? null);
        $t->same('125c8c5b1b014ada9f163e5c1e0d90abb4392728cb01e6950c312b362e9d90eb', $generatedEvidence['checkedInFixtures'][78]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-blank-line-cell.native', $generatedEvidence['checkedInFixtures'][79]['name'] ?? null);
        $t->same('c246fe7f10c8983792119be048df8360dd3382315dd136775370944206725ef9', $generatedEvidence['checkedInFixtures'][79]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-blank-line-cell', $generatedEvidence['samples'][39]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][39]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv no header ragged native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('no-header-ragged');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv',
            'header' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('no-header-ragged.csv', $generatedEvidence['checkedInFixtures'][8]['name'] ?? null);
        $t->same('178c37d0389b55262ee5a906f2d6a83f914da8bfd819fd37718206065baf876d', $generatedEvidence['checkedInFixtures'][8]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-ragged.native', $generatedEvidence['checkedInFixtures'][9]['name'] ?? null);
        $t->same('2e6f817cfdf74fb6876cc386ea863d0b5469e2f5c72da6aac8c521fc9fabc8d0', $generatedEvidence['checkedInFixtures'][9]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-ragged', $generatedEvidence['samples'][4]['name'] ?? null);
        $t->same(['header' => false], $generatedEvidence['samples'][4]['readerOptions'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same('none', $packet['headerOption'] ?? null);
        $t->same(['column1', 'column2', 'column3', 'column4'], $table->attr('columnNames'));
        $t->same(0, count($table->children[0]->children));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['raggedRowCount'] ?? null);
        $t->same([0, 1], $packet['raggedRows'] ?? null);
        $t->same([3, 2, 4], $packet['rowWidthSummary']['rowWidths'] ?? null);
        $t->same(2, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same('alpha, beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('extra', $table->children[1]->children[2]->children[3]->attr('text'));
        $t->contains('TableHead ( "" , [  ] , [  ] ) [  ]', $native);
        $t->contains('Plain [ Str "alpha," , Space , Str "beta" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv bom leading whitespace native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('bom-leading-whitespace');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $prefix = $packet['inputPrefix'] ?? [];
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('bom-leading-whitespace.csv', $generatedEvidence['checkedInFixtures'][10]['name'] ?? null);
        $t->same('6812293a42d8d68da5c184020b3a3a4a579b6f77125080bf40486b8e433f3aec', $generatedEvidence['checkedInFixtures'][10]['checkedInFile']['sha256'] ?? null);
        $t->same('bom-leading-whitespace.native', $generatedEvidence['checkedInFixtures'][11]['name'] ?? null);
        $t->same('9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36', $generatedEvidence['checkedInFixtures'][11]['checkedInFile']['sha256'] ?? null);
        $t->same('bom-leading-whitespace', $generatedEvidence['samples'][5]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][5]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same('utf-8', $prefix['bom'] ?? null);
        $t->same(3, $prefix['bomByteCount'] ?? null);
        $t->same(1, $prefix['leadingWhitespaceByteCount'] ?? null);
        $t->same(1, $prefix['leadingWhitespaceLineCount'] ?? null);
        $t->same(4, $prefix['firstContentOffset'] ?? null);
        $t->same(2, $prefix['firstContentLine'] ?? null);
        $t->same('efbbbf0a', substr((string) ($prefix['prefixPreviewHex'] ?? ''), 0, 8));
        $t->same([
            'delimited-text-input-prefix-utf8-bom',
            'delimited-text-input-prefix-leading-whitespace',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('Post', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('BOM stripped', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "BOM" , Space , Str "stripped" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv text after closing quote native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('text-after-closing-quote');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('text-after-closing-quote.csv', $generatedEvidence['checkedInFixtures'][12]['name'] ?? null);
        $t->same('baa94e35273deb1680660c255569262f9258132d2f97c7550b082f9676e991a6', $generatedEvidence['checkedInFixtures'][12]['checkedInFile']['sha256'] ?? null);
        $t->same('text-after-closing-quote.native', $generatedEvidence['checkedInFixtures'][13]['name'] ?? null);
        $t->same('8e33c870e16bb77dc144c177673e3313dce9415c80bda3c9b13123466d42442e', $generatedEvidence['checkedInFixtures'][13]['checkedInFile']['sha256'] ?? null);
        $t->same('text-after-closing-quote', $generatedEvidence['samples'][6]['name'] ?? null);
        $t->same(['strictParsing' => false], $generatedEvidence['samples'][6]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(2, $packet['textAfterClosingQuoteCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-text-after-closing-quote',
            'delimited-text-text-after-closing-quote',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('alphatail', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('warn "inside"suffix', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "alphatail" ]', $native);
        $t->contains('Plain [ Str "warn" , Space , Str "\"inside\"suffix" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv trailing empty fields native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('trailing-empty-fields');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('trailing-empty-fields.csv', $generatedEvidence['checkedInFixtures'][14]['name'] ?? null);
        $t->same('2f8e15547906de3b9b95a5d354e039809382171b9d64366d751d8e493b5553d5', $generatedEvidence['checkedInFixtures'][14]['checkedInFile']['sha256'] ?? null);
        $t->same('trailing-empty-fields.native', $generatedEvidence['checkedInFixtures'][15]['name'] ?? null);
        $t->same('86ca6197ec2c3178474e08e68f8deac8996f0fc7f994a803ec1a399e56f9f849', $generatedEvidence['checkedInFixtures'][15]['checkedInFile']['sha256'] ?? null);
        $t->same('trailing-empty-fields', $generatedEvidence['samples'][7]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][7]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status', ''], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(3, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 1, 2], $packet['trailingDelimiterRows'] ?? null);
        $t->same([0, 1, 2], $packet['rowWidthSummary']['trailingEmptyFieldRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-trailing-empty-fields-preserved',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('', $table->children[0]->children[0]->children[3]->attr('text'));
        $t->same('alpha, beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('quote "inside"', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[3]->attr('text'));
        $t->contains('Plain [ Str "alpha," , Space , Str "beta" ]', $native);
        $t->contains('Plain [ Str "quote" , Space , Str "\"inside\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv crlf rows native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('crlf-rows');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same(3, substr_count($fixture['input'], "\r\n"));
        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('crlf-rows.csv', $generatedEvidence['checkedInFixtures'][16]['name'] ?? null);
        $t->same('9936f7d7046f8e486617541749ff65707d43e463b88577ee8c187615f7c7bc9d', $generatedEvidence['checkedInFixtures'][16]['checkedInFile']['sha256'] ?? null);
        $t->same('crlf-rows.native', $generatedEvidence['checkedInFixtures'][17]['name'] ?? null);
        $t->same('95a70343048b4accc704b7ba0613fce1dfea60c0f719eadadb9c2c73761f2c76', $generatedEvidence['checkedInFixtures'][17]['checkedInFile']['sha256'] ?? null);
        $t->same('crlf-rows', $generatedEvidence['samples'][8]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][8]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(true, $packet['finalRecordTerminated'] ?? null);
        $t->same([0, 1, 2], $packet['rowWidthSummary']['sourceRowIndexes'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('Alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('Beta', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "Alpha" ]', $native);
        $t->contains('Plain [ Str "done" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv unquoted whitespace and empty quoted field native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('unquoted-space-empty-quoted');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('unquoted-space-empty-quoted.csv', $generatedEvidence['checkedInFixtures'][18]['name'] ?? null);
        $t->same('f59f8d34be7b452806cfd54e49584047e6156c6791b7df067d7452ba697ddba7', $generatedEvidence['checkedInFixtures'][18]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-space-empty-quoted.native', $generatedEvidence['checkedInFixtures'][19]['name'] ?? null);
        $t->same('2460dd7891857c3927c5f229fbd819afe432604a92606a61f3cb5b87d6bcd3d7', $generatedEvidence['checkedInFixtures'][19]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-space-empty-quoted', $generatedEvidence['samples'][9]['name'] ?? null);
        $t->same(['strictParsing' => false], $generatedEvidence['samples'][9]['readerOptions'] ?? null);
        $t->same(['label', 'raw', 'empty', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['textAfterClosingQuoteCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(' alpha', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('trailing space ', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same([], $table->children[1]->children[0]->children[2]->children);
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same([], $table->children[1]->children[1]->children[2]->children);
        $t->contains('Plain [ Space , Str "alpha" ]', $native);
        $t->contains('Plain [ Str "trailing" , Space , Str "space" , Space ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv comment-looking data native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('comment-looking-data');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('comment-looking-data.csv', $generatedEvidence['checkedInFixtures'][20]['name'] ?? null);
        $t->same('cbfda6df02a13b5ba96fcd6ab171b5083c20ef97af65e858ae110032eb9f51c8', $generatedEvidence['checkedInFixtures'][20]['checkedInFile']['sha256'] ?? null);
        $t->same('comment-looking-data.native', $generatedEvidence['checkedInFixtures'][21]['name'] ?? null);
        $t->same('dcb0f03da9d7ec90de5ce244b3e3002b4f41cc18a9f10314189bcb457823bab6', $generatedEvidence['checkedInFixtures'][21]['checkedInFile']['sha256'] ?? null);
        $t->same('comment-looking-data', $generatedEvidence['samples'][10]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][10]['readerOptions'] ?? null);
        $t->same(['marker', 'value', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('#not-a-comment', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('# stays data', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same(';also-data', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('# quoted, comma', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('//literal', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->contains('Plain [ Str "#not-a-comment" ]', $native);
        $t->contains('Plain [ Str "#" , Space , Str "quoted," , Space , Str "comma" ]', $native);
        $t->contains('Plain [ Str "//literal" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv no header edge delimiters native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('no-header-edge-delimiters');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv',
            'header' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('no-header-edge-delimiters.csv', $generatedEvidence['checkedInFixtures'][22]['name'] ?? null);
        $t->same('fecf7f0f3ba6bd37411f4c8ebcd36ffedf3a9c8f1e52213fdd044ae4decc0fb1', $generatedEvidence['checkedInFixtures'][22]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-edge-delimiters.native', $generatedEvidence['checkedInFixtures'][23]['name'] ?? null);
        $t->same('43066e049b19a9f9f6a210b3e25981d07a01915ba784dd86d8427fbf109408c9', $generatedEvidence['checkedInFixtures'][23]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-edge-delimiters', $generatedEvidence['samples'][11]['name'] ?? null);
        $t->same(['header' => false], $generatedEvidence['samples'][11]['readerOptions'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same('none', $packet['headerOption'] ?? null);
        $t->same(['column1', 'column2', 'column3', 'column4'], $table->attr('columnNames'));
        $t->same(0, count($table->children[0]->children));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 1], $packet['trailingDelimiterRows'] ?? null);
        $t->same([0, 1], $packet['rowWidthSummary']['trailingEmptyFieldRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(3, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-header-disabled',
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-trailing-empty-fields-preserved',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('beta,gamma', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same('tail', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same('extra', $table->children[1]->children[2]->children[3]->attr('text'));
        $t->contains('TableHead ( "" , [  ] , [  ] ) [  ]', $native);
        $t->contains('Plain [ Str "beta,gamma" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv single quote dialect native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('single-quote-dialect');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv',
            'quote' => '\'',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same('\'', $packet['quote'] ?? null);
        $t->same('\'', $packet['dialect']['quote'] ?? null);
        $t->same('quoted-fields', $packet['dialect']['quoteMode'] ?? null);
        $t->same('none', $packet['dialect']['escapeMode'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('single-quote-dialect.csv', $generatedEvidence['checkedInFixtures'][24]['name'] ?? null);
        $t->same('d59a5e83a298313470b808ba0381a51e3eacb0d50f317719717999e3009c1c2d', $generatedEvidence['checkedInFixtures'][24]['checkedInFile']['sha256'] ?? null);
        $t->same('single-quote-dialect.native', $generatedEvidence['checkedInFixtures'][25]['name'] ?? null);
        $t->same('9c05ec1d28eeda63e95a2f99d84cd0ce4bd6413c6b786efb5c973f86dcdb79b6', $generatedEvidence['checkedInFixtures'][25]['checkedInFile']['sha256'] ?? null);
        $t->same('single-quote-dialect', $generatedEvidence['samples'][12]['name'] ?? null);
        $t->same(['quote' => '\''], $generatedEvidence['samples'][12]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(4, $packet['quotedFieldCount'] ?? null);
        $t->same(3, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('Alpha, Beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('Owner\'s choice', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('single \'quote\' kept', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(' spaced value ', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "Alpha," , Space , Str "Beta" ]', $native);
        $t->contains('Plain [ Str "Owner\'s" , Space , Str "choice" ]', $native);
        $t->contains('Plain [ Str "single" , Space , Str "\'quote\'" , Space , Str "kept" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv semicolon delimiter multiline native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('semicolon-delimiter-multiline-cell');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv',
            'delimiter' => 'semicolon',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(';', $packet['delimiter'] ?? null);
        $t->same('semicolon', $packet['delimiterName'] ?? null);
        $t->same('semicolon', $packet['dialect']['delimiterName'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('semicolon-delimiter-multiline-cell.csv', $generatedEvidence['checkedInFixtures'][26]['name'] ?? null);
        $t->same('c383ab2b385dcae671a50b2b226051d74d738aaa627dd9c4393af0d39b863336', $generatedEvidence['checkedInFixtures'][26]['checkedInFile']['sha256'] ?? null);
        $t->same('semicolon-delimiter-multiline-cell.native', $generatedEvidence['checkedInFixtures'][27]['name'] ?? null);
        $t->same('32ddacd1d7a77be7516423cc0d67ade520cf024bac92b03607dda08267dfad2f', $generatedEvidence['checkedInFixtures'][27]['checkedInFile']['sha256'] ?? null);
        $t->same('semicolon-delimiter-multiline-cell', $generatedEvidence['samples'][13]['name'] ?? null);
        $t->same(['delimiter' => 'semicolon'], $generatedEvidence['samples'][13]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note', 'status'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(16, $packet['fieldCount'] ?? null);
        $t->same(4, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-multiline-quoted-field'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('Alpha; Beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("first\nsecond", $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('uses, comma', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('semi; "quoted"', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "Alpha;" , Space , Str "Beta" ]', $native);
        $t->contains('Plain [ Str "first" , LineBreak , Str "second" ]', $native);
        $t->contains('Plain [ Str "semi;" , Space , Str "\"quoted\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'strips csv carriage returns before parsing like upstream pandoc sources' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('cr-only-rows');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same(3, substr_count($fixture['input'], "\r"));
        $t->same(0, substr_count($fixture['input'], "\n"));
        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('cr-only-rows.csv', $generatedEvidence['checkedInFixtures'][28]['name'] ?? null);
        $t->same('fca94752c9fdfbe612a0a998c33a2ba3d5fd816db58ab9648bd41d9318bf3624', $generatedEvidence['checkedInFixtures'][28]['checkedInFile']['sha256'] ?? null);
        $t->same('cr-only-rows.native', $generatedEvidence['checkedInFixtures'][29]['name'] ?? null);
        $t->same('e3bad4c4dc164b635eec375b48010d2b7cecd6e94274b5cc90484e24276f6a91', $generatedEvidence['checkedInFixtures'][29]['checkedInFile']['sha256'] ?? null);
        $t->same('cr-only-rows', $generatedEvidence['samples'][14]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][14]['readerOptions'] ?? null);
        $t->same(3, $packet['inputPrefix']['carriageReturnNormalization']['removedCount'] ?? null);
        $t->same('pandoc-sources-remove-carriage-returns', $packet['inputPrefix']['carriageReturnNormalization']['policy'] ?? null);
        $t->same(['id', 'title', 'status1', 'Alpha', 'ok2', 'Beta, CR', 'done'], $table->attr('columnNames'));
        $t->same(1, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(7, $packet['columnCount'] ?? null);
        $t->same(7, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same(false, $packet['finalRecordTerminated'] ?? null);
        $t->same([0], $packet['rowWidthSummary']['sourceRowIndexes'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same([], $table->children[1]->children);
        $t->contains('Plain [ Str "Beta," , Space , Str "CR" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv unterminated quote eof native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('unterminated-quote-eof');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same(3, substr_count($fixture['input'], "\n"));
        $t->same(0, substr_count($fixture['input'], "\r"));
        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('unterminated-quote-eof.csv', $generatedEvidence['checkedInFixtures'][30]['name'] ?? null);
        $t->same('272c4e0c03e402d21e2b808459fc913dd3eacc2e7c9dafdfb6f506c8127eb747', $generatedEvidence['checkedInFixtures'][30]['checkedInFile']['sha256'] ?? null);
        $t->same('unterminated-quote-eof.native', $generatedEvidence['checkedInFixtures'][31]['name'] ?? null);
        $t->same('754ba8a6135cf7f7064b714cb6a33990958865e0a5ee04532710a74cc395e74b', $generatedEvidence['checkedInFixtures'][31]['checkedInFile']['sha256'] ?? null);
        $t->same('unterminated-quote-eof', $generatedEvidence['samples'][15]['name'] ?? null);
        $t->same(['strictParsing' => false], $generatedEvidence['samples'][15]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(5, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(1, $packet['unclosedQuoteCount'] ?? null);
        $t->same(1, $packet['partialRecordCount'] ?? null);
        $t->same(true, $packet['finalRecordTerminated'] ?? null);
        $t->same(true, $packet['unterminatedQuoteAtEof'] ?? null);
        $t->same(1, $packet['unterminatedQuoteRow'] ?? null);
        $t->same(false, $packet['partialFinalRecord'] ?? null);
        $t->same(1, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(2, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(2, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([1], $packet['raggedRows'] ?? null);
        $t->same([3, 2], $packet['rowWidthSummary']['rowWidths'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->same(6, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-multiline-quoted-field',
            'delimited-text-unterminated-quote-eof',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
            'delimited-text-unclosed-quoted-field',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('1', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same("alpha\nbeta,open\n", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same([], $table->children[1]->children[0]->children[2]->children);
        $t->contains('Plain [ Str "alpha" , LineBreak , Str "beta,open" , LineBreak ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv duplicate header labels native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('duplicate-header-labels');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('duplicate-header-labels.csv', $generatedEvidence['checkedInFixtures'][32]['name'] ?? null);
        $t->same('d0627dffb43d149d884fba447424eed9544c36f9885516afd3e2a04e807c101f', $generatedEvidence['checkedInFixtures'][32]['checkedInFile']['sha256'] ?? null);
        $t->same('duplicate-header-labels.native', $generatedEvidence['checkedInFixtures'][33]['name'] ?? null);
        $t->same('7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e', $generatedEvidence['checkedInFixtures'][33]['checkedInFile']['sha256'] ?? null);
        $t->same('duplicate-header-labels', $generatedEvidence['samples'][16]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][16]['readerOptions'] ?? null);
        $t->same(['id', 'status', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('status', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('status', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('new', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('queued', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "queued" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv keep space after comma native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('keep-space-after-comma');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv',
            'keepSpace' => true,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(true, $packet['dialect']['keepSpace'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('keep-space-after-comma.csv', $generatedEvidence['checkedInFixtures'][34]['name'] ?? null);
        $t->same('68e6bdf13bdb5129562eca08ba28a7516377821d8d2cf951f2927ae923dfb656', $generatedEvidence['checkedInFixtures'][34]['checkedInFile']['sha256'] ?? null);
        $t->same('keep-space-after-comma.native', $generatedEvidence['checkedInFixtures'][35]['name'] ?? null);
        $t->same('5a110b2e35a46a8a3e98961b0a68baf210d015a374c99fdd04c60dfee641c721', $generatedEvidence['checkedInFixtures'][35]['checkedInFile']['sha256'] ?? null);
        $t->same('keep-space-after-comma', $generatedEvidence['samples'][17]['name'] ?? null);
        $t->same(['keepSpace' => true], $generatedEvidence['samples'][17]['readerOptions'] ?? null);
        $t->same(['label', ' raw', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(' padded after comma', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same(' beta', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('value with trailing ', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(' lead and trail ', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Space , Str "raw" ]', $native);
        $t->contains('Plain [ Space , Str "padded" , Space , Str "after" , Space , Str "comma" ]', $native);
        $t->contains('Plain [ Space , Str "beta" ]', $native);
        $t->contains('Plain [ Space , Str "lead" , Space , Str "and" , Space , Str "trail" , Space ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv space delimiter single quote native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('space-delimiter-single-quote');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv',
            'delimiter' => 'space',
            'quote' => '\'',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(' ', $packet['delimiter'] ?? null);
        $t->same('space', $packet['delimiterName'] ?? null);
        $t->same('\'', $packet['quote'] ?? null);
        $t->same('quoted-fields', $packet['dialect']['quoteMode'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('space-delimiter-single-quote.csv', $generatedEvidence['checkedInFixtures'][36]['name'] ?? null);
        $t->same('577165de4a8e2beaee7ef748dc7686c9a283f71e730f8d2e21be94e16cde65f4', $generatedEvidence['checkedInFixtures'][36]['checkedInFile']['sha256'] ?? null);
        $t->same('space-delimiter-single-quote.native', $generatedEvidence['checkedInFixtures'][37]['name'] ?? null);
        $t->same('594390fc80d43bada7903e66a771be44bbef23b24a7f11a2e9ac87e96bc542dd', $generatedEvidence['checkedInFixtures'][37]['checkedInFile']['sha256'] ?? null);
        $t->same('space-delimiter-single-quote', $generatedEvidence['samples'][18]['name'] ?? null);
        $t->same(['delimiter' => 'space', 'quote' => '\''], $generatedEvidence['samples'][18]['readerOptions'] ?? null);
        $t->same(['id', 'item name', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(1, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('item name', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('alpha beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("owner's pick", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('queued', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('plain', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "item" , Space , Str "name" ]', $native);
        $t->contains('Plain [ Str "owner\'s" , Space , Str "pick" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv blank row skipped native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('blank-row-skipped');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('blank-row-skipped.csv', $generatedEvidence['checkedInFixtures'][38]['name'] ?? null);
        $t->same('4d721ac02e32060a616d3fef61083cc6f88adae5ace5ced3d77fe5f6fb966321', $generatedEvidence['checkedInFixtures'][38]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-row-skipped.native', $generatedEvidence['checkedInFixtures'][39]['name'] ?? null);
        $t->same('cf931bb22f5eeb8934579b99d4109e60801dd40e9f48e4e78a4e24038bc07a5f', $generatedEvidence['checkedInFixtures'][39]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-row-skipped', $generatedEvidence['samples'][19]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][19]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['blankRowCount'] ?? null);
        $t->same([2], $packet['blankRows'] ?? null);
        $t->same([0, 1, 3, 4], $packet['rowWidthSummary']['sourceRowIndexes'] ?? null);
        $t->same([2], $packet['rowRepairSummary']['blankRows'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-blank-rows-skipped'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('Alpha, Beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('Beta', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('after blank', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same([], $table->children[1]->children[2]->children[1]->children);
        $t->same('empty title', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "Alpha," , Space , Str "Beta" ]', $native);
        $t->contains('Plain [ Str "after" , Space , Str "blank" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv backslash escaped nonquote native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('backslash-escaped-nonquote');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.csv',
            'escape' => '\\',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same('\\', $packet['escape'] ?? null);
        $t->same('escape-character', $packet['dialect']['escapeMode'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('backslash-escaped-nonquote.csv', $generatedEvidence['checkedInFixtures'][40]['name'] ?? null);
        $t->same('e93eadf2bb257f0e678680ac6e9e2c5b6895410c70e91b414e727da53b8cbd43', $generatedEvidence['checkedInFixtures'][40]['checkedInFile']['sha256'] ?? null);
        $t->same('backslash-escaped-nonquote.native', $generatedEvidence['checkedInFixtures'][41]['name'] ?? null);
        $t->same('155fe9867cd9cca831158d85716c5ef1368c60fddd8edad116b8e067ab465eb9', $generatedEvidence['checkedInFixtures'][41]['checkedInFile']['sha256'] ?? null);
        $t->same('backslash-escaped-nonquote', $generatedEvidence['samples'][20]['name'] ?? null);
        $t->same(['escape' => '\\'], $generatedEvidence['samples'][20]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('comma, inside', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('slash \\ kept', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('path C:\\Temp', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "comma," , Space , Str "inside" ]', $native);
        $t->contains('Plain [ Str "slash" , Space , Str "\\\\" , Space , Str "kept" ]', $native);
        $t->contains('Plain [ Str "path" , Space , Str "C:\\\\Temp" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv pipe delimiter quoted field native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('pipe-delimiter-quoted-field');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.csv',
            'delimiter' => 'pipe',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same('|', $packet['delimiter'] ?? null);
        $t->same('pipe', $packet['delimiterName'] ?? null);
        $t->same('pipe', $packet['dialect']['delimiterName'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('pipe-delimiter-quoted-field.csv', $generatedEvidence['checkedInFixtures'][42]['name'] ?? null);
        $t->same('260877bbb70ff332d8bcff85e829231f71de1dc6d3584fca014e1b3861aab6f8', $generatedEvidence['checkedInFixtures'][42]['checkedInFile']['sha256'] ?? null);
        $t->same('pipe-delimiter-quoted-field.native', $generatedEvidence['checkedInFixtures'][43]['name'] ?? null);
        $t->same('2df2bf05bc29b8b1484e85435e332eff22e71e81aab2c46c2ce3c8caf75d939b', $generatedEvidence['checkedInFixtures'][43]['checkedInFile']['sha256'] ?? null);
        $t->same('pipe-delimiter-quoted-field', $generatedEvidence['samples'][21]['name'] ?? null);
        $t->same(['delimiter' => 'pipe'], $generatedEvidence['samples'][21]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('Alpha | Beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('pipe stays inside quotes', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('quote "inside"', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('comma, semicolon; stay', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('plain', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same('final', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "Alpha" , Space , Str "|" , Space , Str "Beta" ]', $native);
        $t->contains('Plain [ Str "quote" , Space , Str "\"inside\"" ]', $native);
        $t->contains('Plain [ Str "comma," , Space , Str "semicolon;" , Space , Str "stay" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quote disabled literal native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quote-disabled-literal');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.csv',
            'quote' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(null, $packet['quote'] ?? null);
        $t->same('literal', $packet['dialect']['quoteMode'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(8, $packet['upstreamEvidence']['parserOptionFixtureCount'] ?? null);
        $t->true(in_array('quote-disabled-literal', $packet['upstreamEvidence']['parserOptionFixtures'] ?? [], true));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quote-disabled-literal.csv', $generatedEvidence['checkedInFixtures'][44]['name'] ?? null);
        $t->same('d660c2016f15d2181c677dd6545d768f579d6cffcaed5909292260420cf8efde', $generatedEvidence['checkedInFixtures'][44]['checkedInFile']['sha256'] ?? null);
        $t->same('quote-disabled-literal.native', $generatedEvidence['checkedInFixtures'][45]['name'] ?? null);
        $t->same('0f5b9311d4ace127a447f0ab12474ca032d67db6ea57300ed95cd995d4ff8d5e', $generatedEvidence['checkedInFixtures'][45]['checkedInFile']['sha256'] ?? null);
        $t->same('quote-disabled-literal', $generatedEvidence['samples'][22]['name'] ?? null);
        $t->same(['quote' => false], $generatedEvidence['samples'][22]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(4, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(13, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([1], $packet['raggedRows'] ?? null);
        $t->same(3, $packet['diagnosticCount'] ?? null);
        $t->same('"literal', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('comma"', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same(3, count($table->children[1]->children[0]->children));
        $t->same('"literal ""quote"""', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('plain "middle" quote', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "\"literal" ]', $native);
        $t->contains('Plain [ Str "comma\"" ]', $native);
        $t->contains('Plain [ Str "\"literal" , Space , Str "\"\"quote\"\"\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv blank input native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('blank-input');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.csv',
            'strictParsing' => false,
        ]);
        $packet = $document->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('document', $document->type);
        $t->same('csv', $document->attr('sourceFormat'));
        $t->same(0, count($document->children));
        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(0, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(0, $packet['columnCount'] ?? null);
        $t->same(0, $packet['fieldCount'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same([], $packet['columnNames'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-input-prefix-leading-whitespace'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(1, $packet['inputPrefix']['leadingWhitespaceByteCount'] ?? null);
        $t->same(1, $packet['inputPrefix']['leadingWhitespaceLineCount'] ?? null);
        $t->same(2, $packet['inputPrefix']['firstContentLine'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('blank-input.csv', $generatedEvidence['checkedInFixtures'][46]['name'] ?? null);
        $t->same('01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b', $generatedEvidence['checkedInFixtures'][46]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-input.native', $generatedEvidence['checkedInFixtures'][47]['name'] ?? null);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $generatedEvidence['checkedInFixtures'][47]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-input', $generatedEvidence['samples'][23]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][23]['readerOptions'] ?? null);
        $t->same('[]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv unicode safe native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('unicode-safe');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('unicode-safe.csv', $generatedEvidence['checkedInFixtures'][48]['name'] ?? null);
        $t->same('fc76c7b95aec02b9c85b4f435682cab9b5003be0a0f698117ec062e80ea59929', $generatedEvidence['checkedInFixtures'][48]['checkedInFile']['sha256'] ?? null);
        $t->same('unicode-safe.native', $generatedEvidence['checkedInFixtures'][49]['name'] ?? null);
        $t->same('d4e72fa00d0fcb0f7b1ea4bd44561f5aaadb710f0420b5bc7f78cf0c72a277fe', $generatedEvidence['checkedInFixtures'][49]['checkedInFile']['sha256'] ?? null);
        $t->same('unicode-safe', $generatedEvidence['samples'][24]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][24]['readerOptions'] ?? null);
        $t->same(['label', 'description', 'flag'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(0, $packet['controlCharacters']['totalCount'] ?? null);
        $t->same("Caf\xC3\xA9 and snowman \xE2\x98\x83", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("\xE6\x9D\xB1\xE4\xBA\xAC and rocket \xF0\x9F\x9A\x80", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('safe', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "Caf\233" , Space , Str "and" , Space , Str "snowman" , Space , Str "\9731" ]', $native);
        $t->contains('Plain [ Str "\26481\20140" , Space , Str "and" , Space , Str "rocket" , Space , Str "\128640" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quote in unquoted field native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quote-in-unquoted-field');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quote-in-unquoted-field.csv', $generatedEvidence['checkedInFixtures'][50]['name'] ?? null);
        $t->same('83cdb32eeb44e162f294a30313f3652df81a16df4a298969cb80ecef0277f8d4', $generatedEvidence['checkedInFixtures'][50]['checkedInFile']['sha256'] ?? null);
        $t->same('quote-in-unquoted-field.native', $generatedEvidence['checkedInFixtures'][51]['name'] ?? null);
        $t->same('bf2d71e0867ca7b1487c59cff7bf7912d03783dc646003bc3eb0f7a44a3eb9f1', $generatedEvidence['checkedInFixtures'][51]['checkedInFile']['sha256'] ?? null);
        $t->same('quote-in-unquoted-field', $generatedEvidence['samples'][25]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][25]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(1, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-quote-in-unquoted-field'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same('prefix"suffix', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('kept', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('plain', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "prefix\"suffix" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv header only native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('header-only');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-only.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('header-only.csv', $generatedEvidence['checkedInFixtures'][52]['name'] ?? null);
        $t->same('8d10b9e38497ef13bc091e1574b71423a614593e489bd5af9943f946a0296dad', $generatedEvidence['checkedInFixtures'][52]['checkedInFile']['sha256'] ?? null);
        $t->same('header-only.native', $generatedEvidence['checkedInFixtures'][53]['name'] ?? null);
        $t->same('6c1d2eed4478d45205fe2f2fb63b3ba282aad8c27f37b5a01168ba689bee0f00', $generatedEvidence['checkedInFixtures'][53]['checkedInFile']['sha256'] ?? null);
        $t->same('header-only', $generatedEvidence['samples'][26]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][26]['readerOptions'] ?? null);
        $t->same(['name', 'status', 'owner'], $table->attr('columnNames'));
        $t->same(1, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(3, $packet['fieldCount'] ?? null);
        $t->same(true, $packet['headerRow'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(0, count($table->children[1]->children));
        $t->contains('Plain [ Str "owner" ]', $native);
        $t->contains('TableBody ( "" , [  ] , [  ] ) (RowHeadColumns 0) [  ] [  ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv leading whitespace record native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('leading-whitespace-record');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];
        $codes = array_column($packet['diagnostics'] ?? [], 'code');
        $tsvDocument = (new DelimitedTextReader())->readTsv(" \nA\tB\n1\t2\n");
        $tsvTable = $tsvDocument->children[0];
        $tsvPacket = $tsvTable->attr('delimitedText');

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('leading-whitespace-record.csv', $generatedEvidence['checkedInFixtures'][54]['name'] ?? null);
        $t->same('f3365cd5dd45cc2aee1135d4c538390734856d50e5fccf2417b7b8a0568dde89', $generatedEvidence['checkedInFixtures'][54]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-whitespace-record.native', $generatedEvidence['checkedInFixtures'][55]['name'] ?? null);
        $t->same('a18a1b109ba5943baea04ee1f42bbae8e5d4121d3250745ea61e6178f0324846', $generatedEvidence['checkedInFixtures'][55]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-whitespace-record', $generatedEvidence['samples'][27]['name'] ?? null);
        $t->same(['strictParsing' => true], $generatedEvidence['samples'][27]['readerOptions'] ?? null);
        $t->same([' '], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(1, $packet['columnCount'] ?? null);
        $t->same(2, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(2, $packet['raggedRowCount'] ?? null);
        $t->same(' ', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('a', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('1', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same([
            'delimited-text-input-prefix-leading-whitespace',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], $codes);
        $t->contains('Plain [ Space ]', $native);
        $t->true(!str_contains($native, 'Str "b"'));
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));

        $t->same('tsv', $tsvPacket['format'] ?? null);
        $t->same([' '], $tsvTable->attr('columnNames'));
        $t->same(1, $tsvPacket['columnCount'] ?? null);
        $t->same(2, $tsvPacket['sourceMaxFieldCount'] ?? null);
        $t->same('A', $tsvTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('1', $tsvTable->children[1]->children[1]->children[0]->attr('text'));
    },
    'matches generated csv leading blank whitespace native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('leading-blank-whitespace');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.csv',
        ]);
        $packet = $document->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same([], $document->children);
        $t->same(0, $packet['rowCount'] ?? null);
        $t->same(0, $packet['columnCount'] ?? null);
        $t->same(2, $packet['blankRowCount'] ?? null);
        $t->same([0, 1], $packet['blankRows'] ?? null);
        $t->same([
            'delimited-text-input-prefix-leading-whitespace',
            'delimited-text-blank-rows-skipped',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('leading-blank-whitespace.csv', $generatedEvidence['checkedInFixtures'][56]['name'] ?? null);
        $t->same('009966d20c582967816f9721a10b558b07333c88849bff11176b5140e746191e', $generatedEvidence['checkedInFixtures'][56]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-blank-whitespace.native', $generatedEvidence['checkedInFixtures'][57]['name'] ?? null);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $generatedEvidence['checkedInFixtures'][57]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-blank-whitespace', $generatedEvidence['samples'][28]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][28]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quoted final vtab whitespace native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-final-vtab-whitespace');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(4, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['textAfterClosingQuoteCount'] ?? null);
        $t->same(0, $packet['closingQuoteTrailingWhitespaceCount'] ?? null);
        $t->same(0, $packet['closingQuoteTrailingRecordWhitespaceCount'] ?? null);
        $t->same(1, $packet['inputPrefix']['controlCharacterCount'] ?? null);
        $t->same(1, $packet['controlCharacters']['totalCount'] ?? null);
        $t->same([
            'delimited-text-input-prefix-control-character',
            'delimited-text-control-characters',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('1', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('ok', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-final-vtab-whitespace.csv', $generatedEvidence['checkedInFixtures'][58]['name'] ?? null);
        $t->same('295f211324039598c36a9f427e0c9075833fe2b835459f0a65b62936dfcdaaa4', $generatedEvidence['checkedInFixtures'][58]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-final-vtab-whitespace.native', $generatedEvidence['checkedInFixtures'][59]['name'] ?? null);
        $t->same('8f33457b985b91dafdf573244515410fb7d5d43a1004a86ad42845a323c55aff', $generatedEvidence['checkedInFixtures'][59]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-final-vtab-whitespace', $generatedEvidence['samples'][29]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][29]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv unquoted final form feed native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('unquoted-final-formfeed');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];
        $noteCell = $table->children[1]->children[0]->children[1];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(4, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(1, $packet['inputPrefix']['controlCharacterCount'] ?? null);
        $t->same(1, $packet['controlCharacters']['totalCount'] ?? null);
        $t->same("2\f", $noteCell->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $noteCell->children[0]->children));
        $t->same("2\f", $noteCell->children[0]->children[0]->attr('text'));
        $t->contains('Plain [ Str "2\12" ]', $native);
        $t->true(!str_contains($native, 'Str "2" , LineBreak'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('unquoted-final-formfeed.csv', $generatedEvidence['checkedInFixtures'][60]['name'] ?? null);
        $t->same('80650824fca0b4705c51a54aa7328f4ed13db4a51c55ac3603e7fa55ce295beb', $generatedEvidence['checkedInFixtures'][60]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-final-formfeed.native', $generatedEvidence['checkedInFixtures'][61]['name'] ?? null);
        $t->same('a3fbb8cf65627ffdb520bb05437dd79096ccf633cffc8d6537b920738e1db792', $generatedEvidence['checkedInFixtures'][61]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-final-formfeed', $generatedEvidence['samples'][30]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][30]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv space only record native parity fixture without stripping it in recovery mode' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('space-only-record');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.csv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same([' '], $table->attr('columnNames'));
        $t->same(1, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(1, $packet['columnCount'] ?? null);
        $t->same(1, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same([], $packet['blankRows'] ?? null);
        $t->same(2, $packet['inputPrefix']['leadingWhitespaceByteCount'] ?? null);
        $t->same(' ', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same(['delimited-text-input-prefix-leading-whitespace'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('space-only-record.csv', $generatedEvidence['checkedInFixtures'][62]['name'] ?? null);
        $t->same('e16f1596201850fd4a63680b27f603cb64e67176159be3d8ed78a4403fdb1700', $generatedEvidence['checkedInFixtures'][62]['checkedInFile']['sha256'] ?? null);
        $t->same('space-only-record.native', $generatedEvidence['checkedInFixtures'][63]['name'] ?? null);
        $t->same('2643d68a231e44eded9e7ea0647254e3435024cf2dba73ffe924bcdffcab2ae3', $generatedEvidence['checkedInFixtures'][63]['checkedInFile']['sha256'] ?? null);
        $t->same('space-only-record', $generatedEvidence['samples'][31]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][31]['readerOptions'] ?? null);
        $t->contains('Plain [ Space ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quoted trailing linebreak native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-trailing-linebreak');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-linebreak.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $body = $table->children[1];
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(5, $packet['rowCount'] ?? null);
        $t->same(4, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(10, $packet['fieldCount'] ?? null);
        $t->same(4, $packet['quotedFieldCount'] ?? null);
        $t->same(7, $packet['quotedLineBreakCount'] ?? null);
        $t->same(4, $packet['multilineFieldCount'] ?? null);
        $t->same([1, 2, 3, 4], $packet['multilineQuotedRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same('trailing', $body->children[0]->children[1]->attr('text'));
        $t->same("trailing\n", $body->children[0]->children[1]->attr('sourceText'));
        $t->same('', $body->children[1]->children[1]->attr('text'));
        $t->same("\n", $body->children[1]->children[1]->attr('sourceText'));
        $t->same([], $body->children[1]->children[1]->children);
        $t->same("alpha\n\nbeta", $body->children[2]->children[1]->attr('text'));
        $t->same("alpha\n\nbeta\n", $body->children[2]->children[1]->attr('sourceText'));
        $t->same("two\n", $body->children[3]->children[1]->attr('text'));
        $t->same("two\n\n", $body->children[3]->children[1]->attr('sourceText'));
        $t->contains('Plain [ Str "trailing" ]', $native);
        $t->true(!str_contains($native, 'Str "trailing" , LineBreak'));
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->contains('Plain [ Str "alpha" , LineBreak , LineBreak , Str "beta" ]', $native);
        $t->contains('Plain [ Str "two" , LineBreak ]', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-trailing-linebreak.csv', $generatedEvidence['checkedInFixtures'][64]['name'] ?? null);
        $t->same('c806b117273e5a54d3c91a0ada3051854672d049e6a9b62362c0665b5969a56b', $generatedEvidence['checkedInFixtures'][64]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-trailing-linebreak.native', $generatedEvidence['checkedInFixtures'][65]['name'] ?? null);
        $t->same('124132d6e2d241254ed916f8a2d21439fc3e16acc306cee80c98b2fa43f7c2bb', $generatedEvidence['checkedInFixtures'][65]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-trailing-linebreak', $generatedEvidence['samples'][32]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][32]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv leading empty fields native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('leading-empty-fields');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $body = $table->children[1];
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(['', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same([], $table->children[0]->children[0]->children[0]->children);
        $t->same('', $body->children[0]->children[0]->attr('text'));
        $t->same([], $body->children[0]->children[0]->children);
        $t->same('alpha', $body->children[0]->children[1]->attr('text'));
        $t->same('open', $body->children[0]->children[2]->attr('text'));
        $t->same('', $body->children[1]->children[0]->attr('text'));
        $t->same('beta', $body->children[1]->children[1]->attr('text'));
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->contains('Plain [ Str "alpha" ]', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('leading-empty-fields.csv', $generatedEvidence['checkedInFixtures'][66]['name'] ?? null);
        $t->same('ae9dd96c1d786a3a17e28f8b12a8209d8ccce05339b099ab2ea9ffbc8024e82a', $generatedEvidence['checkedInFixtures'][66]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-empty-fields.native', $generatedEvidence['checkedInFixtures'][67]['name'] ?? null);
        $t->same('7b88f2fd371de6bda7ed4d4c0de1bfbb9eb7726231cdf6551918735c60c33bf1', $generatedEvidence['checkedInFixtures'][67]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-empty-fields', $generatedEvidence['samples'][33]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][33]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quoted header fields native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-header-fields');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $body = $table->children[1];
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(['id, code', 'display "name"', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('id, code', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('display "name"', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same(true, $table->children[0]->children[0]->children[0]->attr('sourceQuoted'));
        $t->same(true, $table->children[0]->children[0]->children[1]->attr('sourceQuoted'));
        $t->same('Alpha', $body->children[0]->children[1]->attr('text'));
        $t->same('done', $body->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "id," , Space , Str "code" ]', $native);
        $t->contains('Plain [ Str "display" , Space , Str "\"name\"" ]', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-header-fields.csv', $generatedEvidence['checkedInFixtures'][68]['name'] ?? null);
        $t->same('20a690bd9ec550c7bef7124c2be17ec2adcdfde55e227f174527154fa2fb005e', $generatedEvidence['checkedInFixtures'][68]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-header-fields.native', $generatedEvidence['checkedInFixtures'][69]['name'] ?? null);
        $t->same('7dc4b37ae154cc6c61fe2044fd857198c7cddeedb11dd703760bfc056ab74525', $generatedEvidence['checkedInFixtures'][69]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-header-fields', $generatedEvidence['samples'][34]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][34]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv unquoted tab cell native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('unquoted-tab-cell');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(4, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same("alpha\tomega", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->contains('Plain [ Str "alpha" , Space , Str "omega" ]', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('unquoted-tab-cell.csv', $generatedEvidence['checkedInFixtures'][70]['name'] ?? null);
        $t->same('05913c8dfbf085e9a4bce6e7cb78a0cf21bcc730c8c9e99a46ef568095febaea', $generatedEvidence['checkedInFixtures'][70]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-tab-cell.native', $generatedEvidence['checkedInFixtures'][71]['name'] ?? null);
        $t->same('af548f35ca48115c87452df2017c558d88a0b9ffae7923419d3572d8894c9099', $generatedEvidence['checkedInFixtures'][71]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-tab-cell', $generatedEvidence['samples'][35]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][35]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quoted empty fields native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-empty-fields');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.csv',
        ]);
        $table = $document->children[0];
        $body = $table->children[1];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(['id', 'empty', 'quoted', 'tail'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('', $body->children[0]->children[1]->attr('text'));
        $t->same('', $body->children[0]->children[2]->attr('text'));
        $t->same('', $body->children[1]->children[1]->attr('text'));
        $t->same(' spaced ', $body->children[1]->children[2]->attr('text'));
        $t->same(true, $body->children[0]->children[2]->attr('sourceQuoted'));
        $t->same(true, $body->children[1]->children[1]->attr('sourceQuoted'));
        $t->contains('Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1) []', $nativeTokenStream($native));
        $t->contains('Plain [ Space , Str "spaced" , Space ]', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-empty-fields.csv', $generatedEvidence['checkedInFixtures'][72]['name'] ?? null);
        $t->same('e1f1ebedf2b64fc3de8e758427aed94132e6ac506d235fe6962543c8b6e12a30', $generatedEvidence['checkedInFixtures'][72]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-empty-fields.native', $generatedEvidence['checkedInFixtures'][73]['name'] ?? null);
        $t->same('b54f94d0f28e8d0591abad246ebcf26ea9f486560bd98c5ccb5bb16385c2b21f', $generatedEvidence['checkedInFixtures'][73]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-empty-fields', $generatedEvidence['samples'][36]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][36]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv leading whitespace before quote native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('leading-whitespace-before-quote');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.csv',
        ]);
        $table = $document->children[0];
        $body = $table->children[1];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(4, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(4, $packet['diagnosticCount'] ?? null);
        $t->same(array_fill(0, 4, 'delimited-text-quote-in-unquoted-field'), array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(' "alpha"', $body->children[0]->children[0]->attr('text'));
        $t->same(false, $body->children[0]->children[0]->attr('sourceQuoted'));
        $t->same('trimmed after comma', $body->children[0]->children[1]->attr('text'));
        $t->same(true, $body->children[0]->children[1]->attr('sourceQuoted'));
        $t->same("\t\"tab lead\"", $body->children[1]->children[0]->attr('text'));
        $t->same('quote "inside"', $body->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Space , Str "\"alpha\"" ]', $native);
        $t->contains('Plain [ Str "trimmed" , Space , Str "after" , Space , Str "comma" ]', $nativeTokenStream($native));
        $t->contains('Plain [ Space , Str "\"tab" , Space , Str "lead\"" ]', $native);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('leading-whitespace-before-quote.csv', $generatedEvidence['checkedInFixtures'][74]['name'] ?? null);
        $t->same('de435cced6dfe6d32ffb53fb28ed4f9c2202b6c0900c82ddc5edd351576c03a3', $generatedEvidence['checkedInFixtures'][74]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-whitespace-before-quote.native', $generatedEvidence['checkedInFixtures'][75]['name'] ?? null);
        $t->same('0ed2ba01e1eaca6d5b82d7b75f1b13087f27c4fe5ff2ab9d1e137c3d284e01e5', $generatedEvidence['checkedInFixtures'][75]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-whitespace-before-quote', $generatedEvidence['samples'][37]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][37]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv blank leading header native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('blank-leading-header');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['', 'name', 'status'], $table->attr('columnNames'));
        $t->same('', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same([], $table->children[0]->children[0]->children[0]->children);
        $t->same('name', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('status', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('1', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('open', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('2', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('beta', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('blank-leading-header.csv', $generatedEvidence['checkedInFixtures'][80]['name'] ?? null);
        $t->same('e3578702642814615d38649dc6885e5862cb727ea9e418b076d8a9d2cc592525', $generatedEvidence['checkedInFixtures'][80]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-leading-header.native', $generatedEvidence['checkedInFixtures'][81]['name'] ?? null);
        $t->same('32cb6fb96be43fdaabf0372ffa28ca17fad44ee8789d764fed8fd86df97ad847', $generatedEvidence['checkedInFixtures'][81]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-leading-header', $generatedEvidence['samples'][40]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][40]['readerOptions'] ?? null);
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv pre delimiter space native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('pre-delimiter-space');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id ', 'name ', 'note'], $table->attr('columnNames'));
        $t->same('id ', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('name ', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('note', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('1 ', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Alice ', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('keeps left', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('2 ', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('Bob ', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('pre-delimiter-space.csv', $generatedEvidence['checkedInFixtures'][82]['name'] ?? null);
        $t->same('cf31b6c9a903bdc7743edd7be575dc1da5987b6dbd18525f8bf5c88d7cb61d0b', $generatedEvidence['checkedInFixtures'][82]['checkedInFile']['sha256'] ?? null);
        $t->same('pre-delimiter-space.native', $generatedEvidence['checkedInFixtures'][83]['name'] ?? null);
        $t->same('aecb3a09cd5c535db2000bca63f7c477e3dd8d3757631268783730ab2ebad7a8', $generatedEvidence['checkedInFixtures'][83]['checkedInFile']['sha256'] ?? null);
        $t->same('pre-delimiter-space', $generatedEvidence['samples'][41]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][41]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "id" , Space ]', $native);
        $t->contains('Plain [ Str "Alice" , Space ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv markdown syntax literal native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('markdown-syntax-literal');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same('**bold** and `code`', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('[link](https://example.com)', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('markdown-syntax-literal.csv', $generatedEvidence['checkedInFixtures'][84]['name'] ?? null);
        $t->same('54efc59a5af4fdbb175321dedea7de7df6e5c1257dc9d58201a4a9eebe8a06ed', $generatedEvidence['checkedInFixtures'][84]['checkedInFile']['sha256'] ?? null);
        $t->same('markdown-syntax-literal.native', $generatedEvidence['checkedInFixtures'][85]['name'] ?? null);
        $t->same('b53dd045777e3aeb09537e85c060f30529302052616c31565652ecf15c66f773', $generatedEvidence['checkedInFixtures'][85]['checkedInFile']['sha256'] ?? null);
        $t->same('markdown-syntax-literal', $generatedEvidence['samples'][42]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][42]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "**bold**" , Space , Str "and" , Space , Str "`code`" ]', $native);
        $t->contains('Plain [ Str "[link](https://example.com)" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv formula looking literal native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('formula-looking-literals');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id', 'formula', 'note'], $table->attr('columnNames'));
        $t->same('=SUM(A1:A2)', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('literal formula', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('@lookup(value)', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('+prefix preserved', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(4, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('formula-looking-literals.csv', $generatedEvidence['checkedInFixtures'][86]['name'] ?? null);
        $t->same('25b3fd258d1d8eac491f5c337c1e9dd68020586339d0a7254df05c74f0b21f12', $generatedEvidence['checkedInFixtures'][86]['checkedInFile']['sha256'] ?? null);
        $t->same('formula-looking-literals.native', $generatedEvidence['checkedInFixtures'][87]['name'] ?? null);
        $t->same('cd5252c5c890122ababd2e5566f4ae9a02fcf1d23d6c718c6bf77cb72e42ffa5', $generatedEvidence['checkedInFixtures'][87]['checkedInFile']['sha256'] ?? null);
        $t->same('formula-looking-literals', $generatedEvidence['samples'][43]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][43]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "=SUM(A1:A2)" ]', $native);
        $t->contains('Plain [ Str "@lookup(value)" ]', $native);
        $t->contains('Plain [ Str "+prefix" , Space , Str "preserved" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quote-only cells native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-quotes-only');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-quotes-only.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['csvDirectFixtureDenominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id', 'quote', 'note'], $table->attr('columnNames'));
        $t->same('"', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('single quote char', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('""', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('two quote chars', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('before " after', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same('embedded quote', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(4, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-quotes-only.csv', $generatedEvidence['checkedInFixtures'][90]['name'] ?? null);
        $t->same('63ef7ddd79d41205fa8bc5a8eaa5dcd0be980be3529ea9fc4abbddfbd4b1179c', $generatedEvidence['checkedInFixtures'][90]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-quotes-only.native', $generatedEvidence['checkedInFixtures'][91]['name'] ?? null);
        $t->same('6595eba846246fa0a76e818374a36a2356f7ecd97dd4001704e12829e5773ec9', $generatedEvidence['checkedInFixtures'][91]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-quotes-only', $generatedEvidence['samples'][45]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][45]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "\\"" ]', $native);
        $t->contains('Plain [ Str "\\"\\"" ]', $native);
        $t->contains('Plain [ Str "before" , Space , Str "\\"" , Space , Str "after" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv interior empty header native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('interior-empty-header');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/interior-empty-header.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('interior-empty-header.csv', $generatedEvidence['checkedInFixtures'][92]['name'] ?? null);
        $t->same('95252bfc868a3c78b6ba0690c43b6627228f3139242197d9bc9fa05eca0465cc', $generatedEvidence['checkedInFixtures'][92]['checkedInFile']['sha256'] ?? null);
        $t->same('interior-empty-header.native', $generatedEvidence['checkedInFixtures'][93]['name'] ?? null);
        $t->same('b9a001598d1b933ad675d41970f1c8c25f7212a48ccadb846045fea433a4bb93', $generatedEvidence['checkedInFixtures'][93]['checkedInFile']['sha256'] ?? null);
        $t->same('interior-empty-header', $generatedEvidence['samples'][46]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][46]['readerOptions'] ?? null);
        $t->same(['id', '', 'status'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv header width truncation native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('header-width-truncates-extra-fields');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-width-truncates-extra-fields.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('header-width-truncates-extra-fields.csv', $generatedEvidence['checkedInFixtures'][94]['name'] ?? null);
        $t->same('d816429cc1d19aefb09df7dabad0470d59ffb0164db8c56122f3306571735a49', $generatedEvidence['checkedInFixtures'][94]['checkedInFile']['sha256'] ?? null);
        $t->same('header-width-truncates-extra-fields.native', $generatedEvidence['checkedInFixtures'][95]['name'] ?? null);
        $t->same('365fea12f7cbf4904d4e15db99b416c4555a997646a8cd90ec693c33c076bb4e', $generatedEvidence['checkedInFixtures'][95]['checkedInFile']['sha256'] ?? null);
        $t->same('header-width-truncates-extra-fields', $generatedEvidence['samples'][47]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][47]['readerOptions'] ?? null);
        $t->same(['a', 'b'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(3, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(6, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['raggedRowCount'] ?? null);
        $t->same([1, 2], $packet['raggedRows'] ?? null);
        $t->same('header-row-width', $packet['rowRepairSummary']['policy'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['truncatedRowCount'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->same(3, $packet['diagnosticCount'] ?? null);
        $t->same(['1', '2'], array_map(static fn (AstNode $cell): string => $cell->attr('text'), $table->children[1]->children[0]->children));
        $t->same(['4', ''], array_map(static fn (AstNode $cell): string => $cell->attr('text'), $table->children[1]->children[1]->children));
        $t->contains('Plain [ Str "1" ]', $native);
        $t->contains('Plain [ Str "2" ]', $native);
        $t->true(!str_contains($native, 'Str "3"'));
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated csv quoted edge spaces native parity fixture without inflating csv denominator' => static function (TestRunner $t) use ($generatedCsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedCsvNativeFixture('quoted-edge-spaces');
        $document = (new DelimitedTextReader())->readCsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-edge-spaces.csv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(2, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(2 * DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-edge-spaces.csv', $generatedEvidence['checkedInFixtures'][96]['name'] ?? null);
        $t->same('abc9c092cd5343f3609e738a60ced32cc878e6aa6c20518e4218bce5de67e6d2', $generatedEvidence['checkedInFixtures'][96]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-edge-spaces.native', $generatedEvidence['checkedInFixtures'][97]['name'] ?? null);
        $t->same('186662f425cf75dee71c2aaa88f204249922cf5a0fadb3020d4501dff983c052', $generatedEvidence['checkedInFixtures'][97]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-edge-spaces', $generatedEvidence['samples'][48]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][48]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(4, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(' leading', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('trailing ', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(' both ', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same(' spaced status ', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Space , Str "leading" ]', $native);
        $t->contains('Plain [ Str "trailing" , Space ]', $native);
        $t->contains('Plain [ Space , Str "spaced" , Space , Str "status" , Space ]', $native);
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
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
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
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
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
    'matches generated tsv unicode safe native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('unicode-safe');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same('unicode-safe.tsv', $generatedEvidence['checkedInFixtures'][4]['name'] ?? null);
        $t->same('cd7a0f7e2c4737a1884c0ff3ec73bf6a5990fbdfb6ba1b588b6a6d9202ab3e02', $generatedEvidence['checkedInFixtures'][4]['checkedInFile']['sha256'] ?? null);
        $t->same('unicode-safe.native', $generatedEvidence['checkedInFixtures'][5]['name'] ?? null);
        $t->same('e7d3ea0f37e8d3b0613155eaaf480edf042cd5e22aa4291866ae8a0e627fe990', $generatedEvidence['checkedInFixtures'][5]['checkedInFile']['sha256'] ?? null);
        $t->same(['label', 'description', 'flag'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(0, $packet['controlCharacters']['totalCount'] ?? null);
        $t->same("Caf\xC3\xA9 and snowman \xE2\x98\x83", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("\xE6\x9D\xB1\xE4\xBA\xAC and rocket \xF0\x9F\x9A\x80", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('safe\\x1F', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "Caf\233" , Space , Str "and" , Space , Str "snowman" , Space , Str "\9731" ]', $native);
        $t->contains('Plain [ Str "\26481\20140" , Space , Str "and" , Space , Str "rocket" , Space , Str "\128640" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv ragged blank fields native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('ragged-blank-fields');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same('ragged-blank-fields.tsv', $generatedEvidence['checkedInFixtures'][6]['name'] ?? null);
        $t->same('3eb62cad900b02542011bfcb6ffa891856dbf398aa7e7174785264494258c9d4', $generatedEvidence['checkedInFixtures'][6]['checkedInFile']['sha256'] ?? null);
        $t->same('ragged-blank-fields.native', $generatedEvidence['checkedInFixtures'][7]['name'] ?? null);
        $t->same('3dff8bc1804021464a9c00917917904cef8c259d3933410507bb0a6961899bce', $generatedEvidence['checkedInFixtures'][7]['checkedInFile']['sha256'] ?? null);
        $t->same(['name', 'status', 'note'], $table->attr('columnNames'));
        $t->same(5, $packet['rowCount'] ?? null);
        $t->same(4, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(4, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(14, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['raggedRowCount'] ?? null);
        $t->same([2, 3, 4], $packet['raggedRows'] ?? null);
        $t->same([], $packet['trailingDelimiterRows'] ?? null);
        $t->same([3, 3, 2, 2, 4], $packet['rowWidthSummary']['rowWidths'] ?? null);
        $t->same('header-row-width', $packet['rowRepairSummary']['policy'] ?? null);
        $t->same(2, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['truncatedRowCount'] ?? null);
        $t->same(3, count($table->children[0]->children[0]->children));
        $t->same('', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same(3, count($table->children[1]->children[3]->children));
        $t->contains('Plain [ Str "in" , Space , Str "review" ]', $native);
        $t->contains('Plain [ Str "wider" ]', $native);
        $t->true(!str_contains($native, 'Str "extra"'));
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv no header native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('no-header');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv',
            'header' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('no-header.tsv', $generatedEvidence['checkedInFixtures'][8]['name'] ?? null);
        $t->same('0553e41c6e8a6257ad01d8dfad5c1ffecfb495a58273b38b1115ddb5635449bd', $generatedEvidence['checkedInFixtures'][8]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header.native', $generatedEvidence['checkedInFixtures'][9]['name'] ?? null);
        $t->same('9d9356cfcfb719fb3093faf108a3f70cbf15dfb3921b37420d8d6a3eef3caf46', $generatedEvidence['checkedInFixtures'][9]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header', $generatedEvidence['samples'][4]['name'] ?? null);
        $t->same(['header' => false], $generatedEvidence['samples'][4]['readerOptions'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same('none', $packet['headerOption'] ?? null);
        $t->same(['column1', 'column2', 'column3'], $table->attr('columnNames'));
        $t->same(0, count($table->children[0]->children));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(8, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([2], $packet['raggedRows'] ?? null);
        $t->same('A-1', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('needs review', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('C-3', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('TableHead ( "" , [  ] , [  ] ) [  ]', $native);
        $t->contains('Plain [ Str "needs" , Space , Str "review" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv bom leading whitespace native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('bom-leading-whitespace');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $prefix = $packet['inputPrefix'] ?? [];
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('bom-leading-whitespace.tsv', $generatedEvidence['checkedInFixtures'][10]['name'] ?? null);
        $t->same('d10a56e1e3d9cdf0abb8c3f800d45a8bace164a4ff015c72dad5b5206b55f451', $generatedEvidence['checkedInFixtures'][10]['checkedInFile']['sha256'] ?? null);
        $t->same('bom-leading-whitespace.native', $generatedEvidence['checkedInFixtures'][11]['name'] ?? null);
        $t->same('9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36', $generatedEvidence['checkedInFixtures'][11]['checkedInFile']['sha256'] ?? null);
        $t->same('bom-leading-whitespace', $generatedEvidence['samples'][5]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][5]['readerOptions'] ?? null);
        $t->same(['id', 'title', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same('utf-8', $prefix['bom'] ?? null);
        $t->same(3, $prefix['bomByteCount'] ?? null);
        $t->same(1, $prefix['leadingWhitespaceByteCount'] ?? null);
        $t->same(1, $prefix['leadingWhitespaceLineCount'] ?? null);
        $t->same(4, $prefix['firstContentOffset'] ?? null);
        $t->same(2, $prefix['firstContentLine'] ?? null);
        $t->same('efbbbf0a', substr((string) ($prefix['prefixPreviewHex'] ?? ''), 0, 8));
        $t->same([
            'delimited-text-input-prefix-utf8-bom',
            'delimited-text-input-prefix-leading-whitespace',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('Post', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('BOM stripped', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "BOM" , Space , Str "stripped" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv blank row literal punctuation native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('blank-row-literal-punctuation');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv',
            'strictParsing' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('blank-row-literal-punctuation.tsv', $generatedEvidence['checkedInFixtures'][12]['name'] ?? null);
        $t->same('3971c352574fb88bf49073fab5e73d309c3e50d23c169250aec22e8ed3e0c4d8', $generatedEvidence['checkedInFixtures'][12]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-row-literal-punctuation.native', $generatedEvidence['checkedInFixtures'][13]['name'] ?? null);
        $t->same('29623a127b4bc0bf3f17b351bfa9f712a1ecbd2d24741d3c2f6aa0475e250023', $generatedEvidence['checkedInFixtures'][13]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-row-literal-punctuation', $generatedEvidence['samples'][6]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][6]['readerOptions'] ?? null);
        $t->same(['id', 'name', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['blankRowCount'] ?? null);
        $t->same([2], $packet['blankRows'] ?? null);
        $t->same([0, 1, 3], $packet['rowWidthSummary']['sourceRowIndexes'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-blank-rows-skipped'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('"Alpha"', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('comma, pipe |', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "\"Alpha\"" ]', $native);
        $t->contains('Plain [ Str "comma," , Space , Str "pipe" , Space , Str "|" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv comment looking data native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('comment-looking-data');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('comment-looking-data.tsv', $generatedEvidence['checkedInFixtures'][14]['name'] ?? null);
        $t->same('a52c8e6587c36a1deb6d86bce90910eb138f9ed983ba66c6336eca055f0e9d04', $generatedEvidence['checkedInFixtures'][14]['checkedInFile']['sha256'] ?? null);
        $t->same('comment-looking-data.native', $generatedEvidence['checkedInFixtures'][15]['name'] ?? null);
        $t->same('52a97c04e576bedd6bec2609850c3a65c3a90fc165326d9ab11beae1f447cc2e', $generatedEvidence['checkedInFixtures'][15]['checkedInFile']['sha256'] ?? null);
        $t->same('comment-looking-data', $generatedEvidence['samples'][7]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][7]['readerOptions'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same('#1', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('# starts row data', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('literal # in field', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('"quoted" hash # stays', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "#1" ]', $native);
        $t->contains('Plain [ Str "#" , Space , Str "starts" , Space , Str "row" , Space , Str "data" ]', $native);
        $t->contains('Plain [ Str "\"quoted\"" , Space , Str "hash" , Space , Str "#" , Space , Str "stays" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv no header edge delimiters native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('no-header-edge-delimiters');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv',
            'header' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('no-header-edge-delimiters.tsv', $generatedEvidence['checkedInFixtures'][16]['name'] ?? null);
        $t->same('0e90d36fbdce51c4ee0557fa0d1526d849493f30d408675cc445094b7ae79e45', $generatedEvidence['checkedInFixtures'][16]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-edge-delimiters.native', $generatedEvidence['checkedInFixtures'][17]['name'] ?? null);
        $t->same('1e219ae43ee7ef40c4b05ba0565a1e1f7b127a3b6ddda615ce5d9e87622446a4', $generatedEvidence['checkedInFixtures'][17]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-edge-delimiters', $generatedEvidence['samples'][8]['name'] ?? null);
        $t->same(['header' => false], $generatedEvidence['samples'][8]['readerOptions'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same('none', $packet['headerOption'] ?? null);
        $t->same(['column1', 'column2', 'column3', 'column4'], $table->attr('columnNames'));
        $t->same(0, count($table->children[0]->children));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(4, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['raggedRowCount'] ?? null);
        $t->same([0, 2, 3], $packet['raggedRows'] ?? null);
        $t->same([], $packet['trailingDelimiterRows'] ?? null);
        $t->same(0, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([3, 4, 3, 2], $packet['rowWidthSummary']['rowWidths'] ?? null);
        $t->same(3, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->same(0, $packet['rowRepairSummary']['truncatedRowCount'] ?? null);
        $t->same('', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('beta', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('left', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('mid', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('last', $table->children[1]->children[1]->children[3]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same('leading', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same('middle', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[3]->children[3]->attr('text'));
        $t->contains('Plain [ Str "alpha" ]', $native);
        $t->contains('Plain [ Str "trailing" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv csv quoted literal native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('csv-quoted-literal');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('csv-quoted-literal.tsv', $generatedEvidence['checkedInFixtures'][18]['name'] ?? null);
        $t->same('1c28f3c034a65a005034ae5806e4d035eecd9704c6cf1055b2f0c041e96719be', $generatedEvidence['checkedInFixtures'][18]['checkedInFile']['sha256'] ?? null);
        $t->same('csv-quoted-literal.native', $generatedEvidence['checkedInFixtures'][19]['name'] ?? null);
        $t->same('419fb3357404e8b572bf42e5fe3cc32c410f4b69566b282295a7039490ab6fdc', $generatedEvidence['checkedInFixtures'][19]['checkedInFile']['sha256'] ?? null);
        $t->same('csv-quoted-literal', $generatedEvidence['samples'][9]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][9]['readerOptions'] ?? null);
        $t->same(['id', 'payload', 'comment'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('"alpha, beta"', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('"outer quotes stay"', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('"embedded ""quote"""', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('backslash \" stay', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('"comma, ""quote"", tail"', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "\"alpha," , Space , Str "beta\"" ]', $native);
        $t->contains('Plain [ Str "\"embedded" , Space , Str "\"\"quote\"\"\"" ]', $native);
        $t->contains('Plain [ Str "backslash" , Space , Str "\\\\\"" , Space , Str "stay" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv keep space after tab native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('keep-space-after-tab');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv',
            'keepSpace' => true,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(true, $packet['dialect']['keepSpace'] ?? null);
        $t->same('literal', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('keep-space-after-tab.tsv', $generatedEvidence['checkedInFixtures'][20]['name'] ?? null);
        $t->same('4a015006efd98569714058528747683dd5e3a384a0a9615d7d7ebce3bcd8e603', $generatedEvidence['checkedInFixtures'][20]['checkedInFile']['sha256'] ?? null);
        $t->same('keep-space-after-tab.native', $generatedEvidence['checkedInFixtures'][21]['name'] ?? null);
        $t->same('88ffc2cd12c0dd74592bceeb20821ec9a38c10f87e9b60a808ca03569c9c1026', $generatedEvidence['checkedInFixtures'][21]['checkedInFile']['sha256'] ?? null);
        $t->same('keep-space-after-tab', $generatedEvidence['samples'][10]['name'] ?? null);
        $t->same(['keepSpace' => true], $generatedEvidence['samples'][10]['readerOptions'] ?? null);
        $t->same(['label', 'raw', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('  padded after tab', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same(' beta', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('value with trailing  ', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('"literal quotes"', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same(' lead and trail ', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Space , Str "padded" , Space , Str "after" , Space , Str "tab" ]', $native);
        $t->contains('Plain [ Space , Str "beta" ]', $native);
        $t->contains('Plain [ Str "\"literal" , Space , Str "quotes\"" ]', $native);
        $t->contains('Plain [ Space , Str "lead" , Space , Str "and" , Space , Str "trail" , Space ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv crlf rows native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('crlf-rows');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->true(str_contains($fixture['input'], "\r\n"));
        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('crlf-rows.tsv', $generatedEvidence['checkedInFixtures'][22]['name'] ?? null);
        $t->same('1ee34fc2887a5be7359dd06425faa9e15c47cc7fd65ea5b475119cf159951eb4', $generatedEvidence['checkedInFixtures'][22]['checkedInFile']['sha256'] ?? null);
        $t->same('crlf-rows.native', $generatedEvidence['checkedInFixtures'][23]['name'] ?? null);
        $t->same('ae90f3b65232ccb820321bacbc03f1f45224cfcfdb7eb2614315e124d91905e0', $generatedEvidence['checkedInFixtures'][23]['checkedInFile']['sha256'] ?? null);
        $t->same('crlf-rows', $generatedEvidence['samples'][11]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][11]['readerOptions'] ?? null);
        $t->same(['id', 'name', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('Alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('ready', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('Beta', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "Alpha" ]', $native);
        $t->contains('Plain [ Str "done" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv quoted tabs and newlines native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('quoted-tabs-and-newlines');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv',
            'quote' => '"',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same('"', $packet['quote'] ?? null);
        $t->same('quoted-fields', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-tabs-and-newlines.tsv', $generatedEvidence['checkedInFixtures'][24]['name'] ?? null);
        $t->same('063ef586c65fd208bfb670a711edbd004501bb484fe5facbed94c6f898bb6f79', $generatedEvidence['checkedInFixtures'][24]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-tabs-and-newlines.native', $generatedEvidence['checkedInFixtures'][25]['name'] ?? null);
        $t->same('dbfdd6519302270f48a6831a9e0594d7779e14922b9f8fd120eee2a7204d2b5b', $generatedEvidence['checkedInFixtures'][25]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-tabs-and-newlines', $generatedEvidence['samples'][12]['name'] ?? null);
        $t->same(['quote' => '"'], $generatedEvidence['samples'][12]['readerOptions'] ?? null);
        $t->same(['id', 'payload', 'note'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(3, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same(1, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([2], $packet['multilineQuotedRows'] ?? null);
        $t->same(1, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-multiline-quoted-field'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same("alpha\tbeta", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("two\nlines", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('embedded "quote"', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->contains('Plain [ Str "alpha" , Space , Str "beta" ]', $native);
        $t->contains('Plain [ Str "two" , LineBreak , Str "lines" ]', $native);
        $t->contains('Plain [ Str "embedded" , Space , Str "\"quote\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv blank leading header native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('blank-leading-header');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('blank-leading-header.tsv', $generatedEvidence['checkedInFixtures'][26]['name'] ?? null);
        $t->same('c2fd8d6c08e7858885d36a4d57a4f79f473418772f1c9f5c6f128b6fbba9858c', $generatedEvidence['checkedInFixtures'][26]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-leading-header.native', $generatedEvidence['checkedInFixtures'][27]['name'] ?? null);
        $t->same('36321b161eb2743b361b6e5f2d8062b2de6d006969f64290fcbb84bb3d180ed2', $generatedEvidence['checkedInFixtures'][27]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-leading-header', $generatedEvidence['samples'][13]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][13]['readerOptions'] ?? null);
        $t->same(['', 'name'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(6, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([], $packet['trailingDelimiterRows'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same([], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('name', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('Alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('Beta', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->contains('Plain [ Str "Alpha" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv basic status native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('basic-status');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('basic-status.tsv', $generatedEvidence['checkedInFixtures'][28]['name'] ?? null);
        $t->same('d05b3c50b6780930533f48d3e8192cb4a50ee2f15dec69d75984d10f43dba22d', $generatedEvidence['checkedInFixtures'][28]['checkedInFile']['sha256'] ?? null);
        $t->same('basic-status.native', $generatedEvidence['checkedInFixtures'][29]['name'] ?? null);
        $t->same('71b49eeb3ed15b82ae55464884fd30a7bf4191dbd04fb2625bea3a862896c4a9', $generatedEvidence['checkedInFixtures'][29]['checkedInFile']['sha256'] ?? null);
        $t->same('basic-status', $generatedEvidence['samples'][14]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][14]['readerOptions'] ?? null);
        $t->same(['name', 'status', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('Alpha', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('ready', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('plain row', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('Beta', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('final check', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "plain" , Space , Str "row" ]', $native);
        $t->contains('Plain [ Str "final" , Space , Str "check" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv header only native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('header-only');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('header-only.tsv', $generatedEvidence['checkedInFixtures'][30]['name'] ?? null);
        $t->same('46486ef39ea30bfa8f03905b713e20d76b78ee760e4e586931fd5008db45abe6', $generatedEvidence['checkedInFixtures'][30]['checkedInFile']['sha256'] ?? null);
        $t->same('header-only.native', $generatedEvidence['checkedInFixtures'][31]['name'] ?? null);
        $t->same('6c1d2eed4478d45205fe2f2fb63b3ba282aad8c27f37b5a01168ba689bee0f00', $generatedEvidence['checkedInFixtures'][31]['checkedInFile']['sha256'] ?? null);
        $t->same('header-only', $generatedEvidence['samples'][15]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][15]['readerOptions'] ?? null);
        $t->same(['name', 'status', 'owner'], $table->attr('columnNames'));
        $t->same(1, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(3, $packet['fieldCount'] ?? null);
        $t->same(true, $packet['headerRow'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(0, count($table->children[1]->children));
        $t->contains('Plain [ Str "owner" ]', $native);
        $t->contains('TableBody ( "" , [  ] , [  ] ) (RowHeadColumns 0) [  ] [  ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv no header internal trailing empty native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('no-header-internal-trailing-empty');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.tsv',
            'header' => false,
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('no-header-internal-trailing-empty.tsv', $generatedEvidence['checkedInFixtures'][32]['name'] ?? null);
        $t->same('4147bfbde51a4e832fe461334bc8657c055dca86d4b274dee8c3adab32cab9cd', $generatedEvidence['checkedInFixtures'][32]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-internal-trailing-empty.native', $generatedEvidence['checkedInFixtures'][33]['name'] ?? null);
        $t->same('c3fade20df04245e26fd3e54990284f7e1a8750c882c2557ec520c75faab46f5', $generatedEvidence['checkedInFixtures'][33]['checkedInFile']['sha256'] ?? null);
        $t->same('no-header-internal-trailing-empty', $generatedEvidence['samples'][16]['name'] ?? null);
        $t->same(['header' => false], $generatedEvidence['samples'][16]['readerOptions'] ?? null);
        $t->same(['column1', 'column2', 'column3', 'column4'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(4, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([1, 2], $packet['trailingDelimiterRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(3, $packet['diagnosticCount'] ?? null);
        $t->same('', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('omega', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('left', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('mid', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[3]->attr('text'));
        $t->same('solo', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[3]->attr('text'));
        $t->contains('Plain [ Str "omega" ]', $native);
        $t->contains('Plain [ Str "solo" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv blank input native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('blank-input');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.tsv',
            'strictParsing' => false,
        ]);
        $packet = $document->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('document', $document->type);
        $t->same('tsv', $document->attr('sourceFormat'));
        $t->same(0, count($document->children));
        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(0, $packet['columnCount'] ?? null);
        $t->same(0, $packet['fieldCount'] ?? null);
        $t->same(false, $packet['headerRow'] ?? null);
        $t->same([], $packet['columnNames'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(['delimited-text-input-prefix-leading-whitespace'], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(1, $packet['inputPrefix']['leadingWhitespaceByteCount'] ?? null);
        $t->same(1, $packet['inputPrefix']['leadingWhitespaceLineCount'] ?? null);
        $t->same(2, $packet['inputPrefix']['firstContentLine'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('blank-input.tsv', $generatedEvidence['checkedInFixtures'][34]['name'] ?? null);
        $t->same('01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b', $generatedEvidence['checkedInFixtures'][34]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-input.native', $generatedEvidence['checkedInFixtures'][35]['name'] ?? null);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $generatedEvidence['checkedInFixtures'][35]['checkedInFile']['sha256'] ?? null);
        $t->same('blank-input', $generatedEvidence['samples'][17]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][17]['readerOptions'] ?? null);
        $t->same('[]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv duplicate header labels native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('duplicate-header-labels');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('duplicate-header-labels.tsv', $generatedEvidence['checkedInFixtures'][36]['name'] ?? null);
        $t->same('d973ebe3ce9f9aab73fecd99f1c85e901f0f572089d69deb6f7eb9dee79d0e23', $generatedEvidence['checkedInFixtures'][36]['checkedInFile']['sha256'] ?? null);
        $t->same('duplicate-header-labels.native', $generatedEvidence['checkedInFixtures'][37]['name'] ?? null);
        $t->same('7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e', $generatedEvidence['checkedInFixtures'][37]['checkedInFile']['sha256'] ?? null);
        $t->same('duplicate-header-labels', $generatedEvidence['samples'][18]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][18]['readerOptions'] ?? null);
        $t->same(['id', 'status', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('status', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('status', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same('new', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('queued', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "queued" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv escaped quote dialect native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('escaped-quote-dialect');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.tsv',
            'quote' => '"',
            'escape' => '\\',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same('"', $packet['quote'] ?? null);
        $t->same('\\', $packet['escape'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('escaped-quote-dialect.tsv', $generatedEvidence['checkedInFixtures'][38]['name'] ?? null);
        $t->same('1fb627d196a256264e209d4f63d92bf9a40cac52241775abc794679b549fdc4f', $generatedEvidence['checkedInFixtures'][38]['checkedInFile']['sha256'] ?? null);
        $t->same('escaped-quote-dialect.native', $generatedEvidence['checkedInFixtures'][39]['name'] ?? null);
        $t->same('858da6b66210ba88c7f74932964abd6a7c35a89464ce20fb855da8d5be4fffe6', $generatedEvidence['checkedInFixtures'][39]['checkedInFile']['sha256'] ?? null);
        $t->same('escaped-quote-dialect', $generatedEvidence['samples'][19]['name'] ?? null);
        $t->same(['quote' => '"', 'escape' => '\\'], $generatedEvidence['samples'][19]['readerOptions'] ?? null);
        $t->same(['id', 'payload', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(0, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('left "quote" right', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("tab\tinside", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "left" , Space , Str "\\"quote\\"" , Space , Str "right" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv literal quote tab split native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('literal-quote-tab-split');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(null, $packet['quote'] ?? null);
        $t->same('literal', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('literal-quote-tab-split.tsv', $generatedEvidence['checkedInFixtures'][40]['name'] ?? null);
        $t->same('00fa66e3f5a260829bf083772aeea977b1bafda332a62dee7a6b54027cd28bdc', $generatedEvidence['checkedInFixtures'][40]['checkedInFile']['sha256'] ?? null);
        $t->same('literal-quote-tab-split.native', $generatedEvidence['checkedInFixtures'][41]['name'] ?? null);
        $t->same('2dcb1348c01e9fd601db48b537d48593b033a8d45ed9641619e569e925f1582e', $generatedEvidence['checkedInFixtures'][41]['checkedInFile']['sha256'] ?? null);
        $t->same('literal-quote-tab-split', $generatedEvidence['samples'][20]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][20]['readerOptions'] ?? null);
        $t->same(['id', 'payload', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(4, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(10, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([1], $packet['raggedRows'] ?? null);
        $t->same(3, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(3, count($table->children[0]->children[0]->children));
        $t->same('"left', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('right"', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same(3, count($table->children[1]->children[0]->children));
        $t->same(3, count($table->children[1]->children[1]->children));
        $t->contains('Plain [ Str "\\"left" ]', $native);
        $t->contains('Plain [ Str "right\\"" ]', $native);
        $t->true(!str_contains($native, 'Str "wide"'));
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv literal quote newline split native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('literal-quote-newline-split');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(null, $packet['quote'] ?? null);
        $t->same('literal', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $generatedEvidence['sampleCount'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('literal-quote-newline-split.tsv', $generatedEvidence['checkedInFixtures'][46]['name'] ?? null);
        $t->same('c98a20cd63e456e0276d69e70c746980935c1495982f25f3dbec73c03b38bd36', $generatedEvidence['checkedInFixtures'][46]['checkedInFile']['sha256'] ?? null);
        $t->same('literal-quote-newline-split.native', $generatedEvidence['checkedInFixtures'][47]['name'] ?? null);
        $t->same('6f2619681b13663971b27c589612e272dd26627a167e9a7a53bee2972899f617', $generatedEvidence['checkedInFixtures'][47]['checkedInFile']['sha256'] ?? null);
        $t->same('literal-quote-newline-split', $generatedEvidence['samples'][23]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][23]['readerOptions'] ?? null);
        $t->same(['id', 'text'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(2, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(5, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(1, $packet['raggedRowCount'] ?? null);
        $t->same([2], $packet['raggedRows'] ?? null);
        $t->same(3, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('"a', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('b"', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('padded', $table->children[1]->children[1]->children[1]->attr('sourceFieldStatus'));
        $t->contains('Plain [ Str "\\"a" ]', $native);
        $t->contains('Plain [ Str "b\\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv leading blank whitespace native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('leading-blank-whitespace');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.tsv',
        ]);
        $packet = $document->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same([], $document->children);
        $t->same(0, $packet['rowCount'] ?? null);
        $t->same(0, $packet['columnCount'] ?? null);
        $t->same(2, $packet['blankRowCount'] ?? null);
        $t->same([0, 1], $packet['blankRows'] ?? null);
        $t->same([
            'delimited-text-input-prefix-leading-whitespace',
            'delimited-text-blank-rows-skipped',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('leading-blank-whitespace.tsv', $generatedEvidence['checkedInFixtures'][42]['name'] ?? null);
        $t->same('009966d20c582967816f9721a10b558b07333c88849bff11176b5140e746191e', $generatedEvidence['checkedInFixtures'][42]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-blank-whitespace.native', $generatedEvidence['checkedInFixtures'][43]['name'] ?? null);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $generatedEvidence['checkedInFixtures'][43]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-blank-whitespace', $generatedEvidence['samples'][21]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][21]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv unquoted final form feed native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('unquoted-final-formfeed');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];
        $noteCell = $table->children[1]->children[0]->children[1];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(4, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['inputPrefix']['controlCharacterCount'] ?? null);
        $t->same(1, $packet['controlCharacters']['totalCount'] ?? null);
        $t->same("2\f", $noteCell->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $noteCell->children[0]->children));
        $t->same("2\f", $noteCell->children[0]->children[0]->attr('text'));
        $t->contains('Plain [ Str "2\12" ]', $native);
        $t->true(!str_contains($native, 'Str "2" , LineBreak'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('unquoted-final-formfeed.tsv', $generatedEvidence['checkedInFixtures'][44]['name'] ?? null);
        $t->same('a329477fc79b06ee10cd8743544b6e627804200a3c411eba3d14db095444bbf4', $generatedEvidence['checkedInFixtures'][44]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-final-formfeed.native', $generatedEvidence['checkedInFixtures'][45]['name'] ?? null);
        $t->same('a3fbb8cf65627ffdb520bb05437dd79096ccf633cffc8d6537b920738e1db792', $generatedEvidence['checkedInFixtures'][45]['checkedInFile']['sha256'] ?? null);
        $t->same('unquoted-final-formfeed', $generatedEvidence['samples'][22]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][22]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv leading empty fields native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('leading-empty-fields');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['', 'note', 'status'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same('', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('done', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('leading-empty-fields.tsv', $generatedEvidence['checkedInFixtures'][48]['name'] ?? null);
        $t->same('ab4dfed4760d46c5f0dd14b82aa08366dc8b906e748a5e0f7188fa6df4b4d818', $generatedEvidence['checkedInFixtures'][48]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-empty-fields.native', $generatedEvidence['checkedInFixtures'][49]['name'] ?? null);
        $t->same('ed543e7867c79895214721849592da8962289f4a8e9d853be8d6bc04f13fc562', $generatedEvidence['checkedInFixtures'][49]['checkedInFile']['sha256'] ?? null);
        $t->same('leading-empty-fields', $generatedEvidence['samples'][24]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][24]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv trailing empty fields native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('trailing-empty-fields');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['name', 'note', 'flag'], $table->attr('columnNames'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([1, 2], $packet['trailingDelimiterRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same([], $packet['raggedRows'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-trailing-empty-fields-preserved',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same('alpha', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('gamma', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('ok', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "gamma" ]', $native);
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('trailing-empty-fields.tsv', $generatedEvidence['checkedInFixtures'][50]['name'] ?? null);
        $t->same('3f45d3086a898528498ee69696f28d6ee6876ec891d66806d1609ebc5fc2dcc7', $generatedEvidence['checkedInFixtures'][50]['checkedInFile']['sha256'] ?? null);
        $t->same('trailing-empty-fields.native', $generatedEvidence['checkedInFixtures'][51]['name'] ?? null);
        $t->same('5765a3463ad42f0e48295e67e3276bf0d6a0d3d0013e131a492379637a40ebbb', $generatedEvidence['checkedInFixtures'][51]['checkedInFile']['sha256'] ?? null);
        $t->same('trailing-empty-fields', $generatedEvidence['samples'][25]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][25]['readerOptions'] ?? null);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv literal quote header native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('literal-quote-header');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(null, $packet['quote'] ?? null);
        $t->same('literal', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('literal-quote-header.tsv', $generatedEvidence['checkedInFixtures'][52]['name'] ?? null);
        $t->same('bb618e2a1e983dea0842ad93f813bdd7d15e5d00590f868d8d6d2218f92fee3d', $generatedEvidence['checkedInFixtures'][52]['checkedInFile']['sha256'] ?? null);
        $t->same('literal-quote-header.native', $generatedEvidence['checkedInFixtures'][53]['name'] ?? null);
        $t->same('ebcd237669082f27a9e47a4602cc46a711a83b158c0e2ffd2006e5ef61c98e64', $generatedEvidence['checkedInFixtures'][53]['checkedInFile']['sha256'] ?? null);
        $t->same('literal-quote-header', $generatedEvidence['samples'][26]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][26]['readerOptions'] ?? null);
        $t->same(['"id"', '"note"', '"status"'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('"alpha"', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('"done"', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->contains('Plain [ Str "\"id\"" ]', $native);
        $t->contains('Plain [ Str "\"alpha\"" ]', $native);
        $t->contains('Plain [ Str "\"done\"" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv markdown syntax literal native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('markdown-syntax-literal');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id', 'note', 'status'], $table->attr('columnNames'));
        $t->same('**bold** and `code`', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('[link](https://example.com)', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('markdown-syntax-literal.tsv', $generatedEvidence['checkedInFixtures'][54]['name'] ?? null);
        $t->same('307f323f10c85aa81a984dcfb7fc8adc4f9f4d17e551064b3276134bae710d9d', $generatedEvidence['checkedInFixtures'][54]['checkedInFile']['sha256'] ?? null);
        $t->same('markdown-syntax-literal.native', $generatedEvidence['checkedInFixtures'][55]['name'] ?? null);
        $t->same('b53dd045777e3aeb09537e85c060f30529302052616c31565652ecf15c66f773', $generatedEvidence['checkedInFixtures'][55]['checkedInFile']['sha256'] ?? null);
        $t->same('markdown-syntax-literal', $generatedEvidence['samples'][27]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][27]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "**bold**" , Space , Str "and" , Space , Str "`code`" ]', $native);
        $t->contains('Plain [ Str "[link](https://example.com)" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv interior empty header native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('interior-empty-header');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id', '', 'status'], $table->attr('columnNames'));
        $t->same('', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same([], $table->children[0]->children[0]->children[1]->children);
        $t->same('alpha', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('gamma', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(1, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([2], $packet['trailingDelimiterRows'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-trailing-empty-fields-preserved',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('interior-empty-header.tsv', $generatedEvidence['checkedInFixtures'][56]['name'] ?? null);
        $t->same('1c97e1d22017dd23c5b20edb6fd2049cbfdfd33ec2fbaf52ed1e6135462ffd5c', $generatedEvidence['checkedInFixtures'][56]['checkedInFile']['sha256'] ?? null);
        $t->same('interior-empty-header.native', $generatedEvidence['checkedInFixtures'][57]['name'] ?? null);
        $t->same('6facd7076f0094d0ffe96c17e9b9c774dc60b73b38ac3691928939b1a55fd285', $generatedEvidence['checkedInFixtures'][57]['checkedInFile']['sha256'] ?? null);
        $t->same('interior-empty-header', $generatedEvidence['samples'][28]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][28]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "gamma" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv trailing empty header native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('trailing-empty-header');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(['id', 'status', ''], $table->attr('columnNames'));
        $t->same('', $table->children[0]->children[0]->children[2]->attr('text'));
        $t->same([], $table->children[0]->children[0]->children[2]->children);
        $t->same('open', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
        $t->same('closed', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 2], $packet['trailingDelimiterRows'] ?? null);
        $t->same(0, $packet['quotedFieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same(0, $packet['blankRowCount'] ?? null);
        $t->same(2, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-trailing-empty-fields-preserved',
        ], array_column($packet['diagnostics'] ?? [], 'code'));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('trailing-empty-header.tsv', $generatedEvidence['checkedInFixtures'][58]['name'] ?? null);
        $t->same('32f7df1adadfd010a05d9ddd9dbf1705050471da4682cc0d37aa8b81ead666cd', $generatedEvidence['checkedInFixtures'][58]['checkedInFile']['sha256'] ?? null);
        $t->same('trailing-empty-header.native', $generatedEvidence['checkedInFixtures'][59]['name'] ?? null);
        $t->same('b22eac43664c1917ebbcd2a9b6053cab9cf1afb4785ccefe8cd1602eda3682b3', $generatedEvidence['checkedInFixtures'][59]['checkedInFile']['sha256'] ?? null);
        $t->same('trailing-empty-header', $generatedEvidence['samples'][29]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][29]['readerOptions'] ?? null);
        $t->contains('Plain [ Str "closed" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv quoted softbreak native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('quoted-softbreak');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-softbreak.tsv',
            'quote' => '"',
            'cellLineBreak' => 'softbreak',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same('"', $packet['quote'] ?? null);
        $t->same('quoted-fields', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('quoted-softbreak.tsv', $generatedEvidence['checkedInFixtures'][60]['name'] ?? null);
        $t->same('01d5ec9046ee9de78dbc8fdd589b7250fa5925a3d9077d3ff7c941cb1c2c97a1', $generatedEvidence['checkedInFixtures'][60]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-softbreak.native', $generatedEvidence['checkedInFixtures'][61]['name'] ?? null);
        $t->same('db9489756eeaacaeb268e403036dddbf10fb9dc35872123331ff01ca1594a810', $generatedEvidence['checkedInFixtures'][61]['checkedInFile']['sha256'] ?? null);
        $t->same('quoted-softbreak', $generatedEvidence['samples'][30]['name'] ?? null);
        $t->same(['quote' => '"', 'cellLineBreak' => 'softbreak'], $generatedEvidence['samples'][30]['readerOptions'] ?? null);
        $t->same(['id', 'payload', 'note'], $table->attr('columnNames'));
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same(1, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same("two\nlines", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("alpha\tbeta", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('Plain [ Str "two" , SoftBreak , Str "lines" ]', $native);
        $t->contains('Plain [ Str "alpha" , Space , Str "beta" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches generated tsv post delimiter space native parity fixture without upstream tsv denominator' => static function (TestRunner $t) use ($generatedTsvNativeFixture, $nativeTokenStream): void {
        $fixture = $generatedTsvNativeFixture('post-delimiter-space');
        $document = (new DelimitedTextReader())->readTsv($fixture['input'], [
            'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/post-delimiter-space.tsv',
        ]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');
        $generatedEvidence = $packet['upstreamEvidence']['generatedNativeParityEvidence'] ?? [];

        $t->same('tsv', $packet['format'] ?? null);
        $t->same('tab', $packet['delimiter'] ?? null);
        $t->same(null, $packet['quote'] ?? null);
        $t->same(false, $packet['dialect']['keepSpace'] ?? null);
        $t->same('literal', $packet['dialect']['quoteMode'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(0, $packet['upstreamEvidence']['tsvDirectFixtureDenominator'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $packet['upstreamEvidence']['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $generatedEvidence['validation']['status'] ?? null);
        $t->same(64, $generatedEvidence['checkedInFixtureCount'] ?? null);
        $t->same('post-delimiter-space.tsv', $generatedEvidence['checkedInFixtures'][62]['name'] ?? null);
        $t->same('e5367f8cd34b279f8f8fc0c5ec78ee042509bc00647c69ee698092d463374496', $generatedEvidence['checkedInFixtures'][62]['checkedInFile']['sha256'] ?? null);
        $t->same('post-delimiter-space.native', $generatedEvidence['checkedInFixtures'][63]['name'] ?? null);
        $t->same('45407755e5d9da0a6d4656f255a9cf0f5b20f924116e4143b964577b56464b10', $generatedEvidence['checkedInFixtures'][63]['checkedInFile']['sha256'] ?? null);
        $t->same('post-delimiter-space', $generatedEvidence['samples'][31]['name'] ?? null);
        $t->same([], $generatedEvidence['samples'][31]['readerOptions'] ?? null);
        $t->same(['id', 'note'], $table->attr('columnNames'));
        $t->same(2, $packet['rowCount'] ?? null);
        $t->same(1, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(4, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['diagnosticCount'] ?? null);
        $t->same('alpha beta', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->contains('Plain [ Str "alpha" , Space , Str "beta" ]', $native);
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
        $t->same(8, $csvEvidence['parserOptionFixtureCount'] ?? null);
        $t->same([
            'comma-delimiter-no-header',
            'space-delimiter-single-quote',
            'backslash-escaped-quote',
            'backslash-escaped-nonquote',
            'keep-space-after-delimiter',
            'semicolon-delimiter-multiline-cell',
            'pipe-delimiter-quoted-field',
            'quote-disabled-literal',
        ], $csvEvidence['parserOptionFixtures'] ?? null);
        $t->same(2, $csvEvidence['integrationFixtureCount'] ?? null);
        $t->same([
            'test/command/3533-rst-csv-tables.csv',
            'test/command/3533-rst-csv-tables.md',
        ], $csvEvidence['integrationFixtures'] ?? null);
        $adjacent = $csvEvidence['adjacentFixtureEvidence'] ?? [];
        $t->same('csv-adjacent-rst-csv-table-fixture-evidence', $adjacent['kind'] ?? null);
        $t->same('adjacent-rst-reader-fixtures-not-direct-delimited-text', $adjacent['relationship'] ?? null);
        $t->same('rst', $adjacent['reader'] ?? null);
        $t->same('csv-table', $adjacent['directive'] ?? null);
        $t->same(2, $adjacent['fixtureCount'] ?? null);
        $t->same(0, $adjacent['csvDirectFixtureDenominatorImpact'] ?? null);
        $t->same(0, $adjacent['tsvDirectFixtureDenominatorImpact'] ?? null);
        $t->same([
            'test/command/3533-rst-csv-tables.csv',
            'test/command/3533-rst-csv-tables.md',
        ], array_column($adjacent['fixtures'] ?? [], 'path'));
        $t->same([false, false], array_column($adjacent['fixtures'] ?? [], 'directDelimitedTextReaderFixture'));
        $t->contains('RST csv-table fixture pair is not counted as direct CSV or TSV reader fixtures', implode(' ', $adjacent['claimBoundaries']['doesAssert'] ?? []));
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, $csvEvidence['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-csv-native-parity-evidence', $csvEvidence['generatedNativeParityEvidence']['validation']['status'] ?? null);
        $t->same('quoted-multiline', $csvEvidence['generatedNativeParityEvidence']['samples'][0]['name'] ?? null);
        $t->same('post-delimiter-space', $csvEvidence['generatedNativeParityEvidence']['samples'][1]['name'] ?? null);
        $t->same('backslash-escaped-quote', $csvEvidence['generatedNativeParityEvidence']['samples'][2]['name'] ?? null);
        $t->same(['escape' => '\\'], $csvEvidence['generatedNativeParityEvidence']['samples'][2]['readerOptions'] ?? null);
        $t->same('quoted-linebreak', $csvEvidence['generatedNativeParityEvidence']['samples'][3]['name'] ?? null);
        $t->same('no-header-ragged', $csvEvidence['generatedNativeParityEvidence']['samples'][4]['name'] ?? null);
        $t->same(['header' => false], $csvEvidence['generatedNativeParityEvidence']['samples'][4]['readerOptions'] ?? null);
        $t->same('bom-leading-whitespace', $csvEvidence['generatedNativeParityEvidence']['samples'][5]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][5]['readerOptions'] ?? null);
        $t->same('text-after-closing-quote', $csvEvidence['generatedNativeParityEvidence']['samples'][6]['name'] ?? null);
        $t->same(['strictParsing' => false], $csvEvidence['generatedNativeParityEvidence']['samples'][6]['readerOptions'] ?? null);
        $t->same('trailing-empty-fields', $csvEvidence['generatedNativeParityEvidence']['samples'][7]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][7]['readerOptions'] ?? null);
        $t->same('crlf-rows', $csvEvidence['generatedNativeParityEvidence']['samples'][8]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][8]['readerOptions'] ?? null);
        $t->same('unquoted-space-empty-quoted', $csvEvidence['generatedNativeParityEvidence']['samples'][9]['name'] ?? null);
        $t->same(['strictParsing' => false], $csvEvidence['generatedNativeParityEvidence']['samples'][9]['readerOptions'] ?? null);
        $t->same('comment-looking-data', $csvEvidence['generatedNativeParityEvidence']['samples'][10]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][10]['readerOptions'] ?? null);
        $t->same('no-header-edge-delimiters', $csvEvidence['generatedNativeParityEvidence']['samples'][11]['name'] ?? null);
        $t->same(['header' => false], $csvEvidence['generatedNativeParityEvidence']['samples'][11]['readerOptions'] ?? null);
        $t->same('single-quote-dialect', $csvEvidence['generatedNativeParityEvidence']['samples'][12]['name'] ?? null);
        $t->same(['quote' => '\''], $csvEvidence['generatedNativeParityEvidence']['samples'][12]['readerOptions'] ?? null);
        $t->same('semicolon-delimiter-multiline-cell', $csvEvidence['generatedNativeParityEvidence']['samples'][13]['name'] ?? null);
        $t->same(['delimiter' => 'semicolon'], $csvEvidence['generatedNativeParityEvidence']['samples'][13]['readerOptions'] ?? null);
        $t->same('cr-only-rows', $csvEvidence['generatedNativeParityEvidence']['samples'][14]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][14]['readerOptions'] ?? null);
        $t->same('unterminated-quote-eof', $csvEvidence['generatedNativeParityEvidence']['samples'][15]['name'] ?? null);
        $t->same(['strictParsing' => false], $csvEvidence['generatedNativeParityEvidence']['samples'][15]['readerOptions'] ?? null);
        $t->same('duplicate-header-labels', $csvEvidence['generatedNativeParityEvidence']['samples'][16]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][16]['readerOptions'] ?? null);
        $t->same('keep-space-after-comma', $csvEvidence['generatedNativeParityEvidence']['samples'][17]['name'] ?? null);
        $t->same(['keepSpace' => true], $csvEvidence['generatedNativeParityEvidence']['samples'][17]['readerOptions'] ?? null);
        $t->same('space-delimiter-single-quote', $csvEvidence['generatedNativeParityEvidence']['samples'][18]['name'] ?? null);
        $t->same(['delimiter' => 'space', 'quote' => '\''], $csvEvidence['generatedNativeParityEvidence']['samples'][18]['readerOptions'] ?? null);
        $t->same('blank-row-skipped', $csvEvidence['generatedNativeParityEvidence']['samples'][19]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][19]['readerOptions'] ?? null);
        $t->same('backslash-escaped-nonquote', $csvEvidence['generatedNativeParityEvidence']['samples'][20]['name'] ?? null);
        $t->same(['escape' => '\\'], $csvEvidence['generatedNativeParityEvidence']['samples'][20]['readerOptions'] ?? null);
        $t->same('pipe-delimiter-quoted-field', $csvEvidence['generatedNativeParityEvidence']['samples'][21]['name'] ?? null);
        $t->same(['delimiter' => 'pipe'], $csvEvidence['generatedNativeParityEvidence']['samples'][21]['readerOptions'] ?? null);
        $t->same('quote-disabled-literal', $csvEvidence['generatedNativeParityEvidence']['samples'][22]['name'] ?? null);
        $t->same(['quote' => false], $csvEvidence['generatedNativeParityEvidence']['samples'][22]['readerOptions'] ?? null);
        $t->same('blank-input', $csvEvidence['generatedNativeParityEvidence']['samples'][23]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][23]['readerOptions'] ?? null);
        $t->same('leading-blank-whitespace', $csvEvidence['generatedNativeParityEvidence']['samples'][28]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][28]['readerOptions'] ?? null);
        $t->same('quoted-final-vtab-whitespace', $csvEvidence['generatedNativeParityEvidence']['samples'][29]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][29]['readerOptions'] ?? null);
        $t->same('unquoted-final-formfeed', $csvEvidence['generatedNativeParityEvidence']['samples'][30]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][30]['readerOptions'] ?? null);
        $t->same('quoted-header-fields', $csvEvidence['generatedNativeParityEvidence']['samples'][34]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][34]['readerOptions'] ?? null);
        $t->same('unquoted-tab-cell', $csvEvidence['generatedNativeParityEvidence']['samples'][35]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][35]['readerOptions'] ?? null);
        $t->same('quoted-empty-fields', $csvEvidence['generatedNativeParityEvidence']['samples'][36]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][36]['readerOptions'] ?? null);
        $t->same('leading-whitespace-before-quote', $csvEvidence['generatedNativeParityEvidence']['samples'][37]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][37]['readerOptions'] ?? null);
        $t->same('post-delimiter-tab', $csvEvidence['generatedNativeParityEvidence']['samples'][38]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][38]['readerOptions'] ?? null);
        $t->same('header-width-truncates-extra-fields', $csvEvidence['generatedNativeParityEvidence']['samples'][47]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][47]['readerOptions'] ?? null);
        $t->same('quoted-edge-spaces', $csvEvidence['generatedNativeParityEvidence']['samples'][48]['name'] ?? null);
        $t->same([], $csvEvidence['generatedNativeParityEvidence']['samples'][48]['readerOptions'] ?? null);
        $t->true(in_array('direct-csv-command-reader', $csvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('csv-closing-quote-record-whitespace-strictness', $csvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('generated-csv-native-parity-sample', $csvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('rst-csv-table-integration-via-rst-reader', $csvEvidence['closedGaps'] ?? [], true));
        $t->same('not-run', $csvEvidence['runnerEvidence']['status'] ?? null);
        $t->same(false, $csvEvidence['runnerEvidence']['executed'] ?? null);
        $t->same(null, $csvEvidence['runnerEvidence']['command'] ?? null);
        $t->same('planned-not-run', $csvEvidence['runnerEvidence']['commandPlanStatus'] ?? null);
        $t->same('upstream-runner-command-plan', $csvEvidence['runnerEvidence']['commandPlan']['kind'] ?? null);
        $t->same('planned-not-run', $csvEvidence['runnerEvidence']['commandPlan']['status'] ?? null);
        $t->same('hydrated Pandoc upstream checkout root', $csvEvidence['runnerEvidence']['commandPlan']['workingDirectory'] ?? null);
        $t->same('offline', $csvEvidence['runnerEvidence']['commandPlan']['networkMode'] ?? null);
        $t->same(3, $csvEvidence['runnerEvidence']['commandPlan']['commandCount'] ?? null);
        $t->same('upstream-runner-non-execution-boundary', $csvEvidence['runnerEvidence']['executionBoundary']['kind'] ?? null);
        $t->same('plan-only-not-run', $csvEvidence['runnerEvidence']['executionBoundary']['status'] ?? null);
        $t->same(true, $csvEvidence['runnerEvidence']['executionBoundary']['planOnly'] ?? null);
        $t->same(0, $csvEvidence['runnerEvidence']['executionBoundary']['executedCommandCount'] ?? null);
        $t->same([], $csvEvidence['runnerEvidence']['executionBoundary']['executedCommands'] ?? null);
        $t->same(false, $csvEvidence['runnerEvidence']['executionBoundary']['upstreamRunnerParityClaimed'] ?? null);
        $t->same('test:test-pandoc', $csvEvidence['runnerEvidence']['target']['testSuite'] ?? null);
        $t->same(['Command:', 'csv.md', '#1'], $csvEvidence['runnerEvidence']['target']['tastyGroupPath'] ?? null);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $csvEvidence['runnerEvidence']['target']['tastyPattern'] ?? null);
        $t->same('csv', $csvEvidence['runnerEvidence']['target']['selectedDirectFixtureFormat'] ?? null);
        $t->same(false, $csvEvidence['runnerEvidence']['target']['tsvDirectFixtureAvailable'] ?? null);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $csvEvidence['runnerEvidence']['futureCommands'][1]['arguments'][8] ?? null);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $csvEvidence['runnerEvidence']['futureCommands'][2]['arguments'][7] ?? null);
        $t->same('hydrated Pandoc upstream checkout root', $csvEvidence['runnerEvidence']['futureCommands'][2]['workingDirectory'] ?? null);
        $t->true(in_array('.port-libs/pandoc-runner/logs/delimited-text-targeted-run.txt', $csvEvidence['runnerEvidence']['requiredTranscripts'] ?? [], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/delimited-text-targeted-run/result.json', $csvEvidence['runnerEvidence']['requiredArtifacts'] ?? [], true));
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRunnerPlanEvidence($csvEvidence['runnerEvidence'] ?? []));
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
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $csvStatic['generatedTsvNativeStaticEvidence']['sampleCount'] ?? null);
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
        $t->same([], $tsvEvidence['adjacentFixtureEvidence'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, $tsvEvidence['generatedNativeParitySampleCount'] ?? null);
        $t->same('valid-checked-in-generated-tsv-native-parity-evidence', $tsvEvidence['generatedNativeParityEvidence']['validation']['status'] ?? null);
        $t->same('simple', $tsvEvidence['generatedNativeParityEvidence']['samples'][0]['name'] ?? null);
        $t->same('quote-trailing', $tsvEvidence['generatedNativeParityEvidence']['samples'][1]['name'] ?? null);
        $t->same('unicode-safe', $tsvEvidence['generatedNativeParityEvidence']['samples'][2]['name'] ?? null);
        $t->same('ragged-blank-fields', $tsvEvidence['generatedNativeParityEvidence']['samples'][3]['name'] ?? null);
        $t->same('no-header', $tsvEvidence['generatedNativeParityEvidence']['samples'][4]['name'] ?? null);
        $t->same(['header' => false], $tsvEvidence['generatedNativeParityEvidence']['samples'][4]['readerOptions'] ?? null);
        $t->same('bom-leading-whitespace', $tsvEvidence['generatedNativeParityEvidence']['samples'][5]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][5]['readerOptions'] ?? null);
        $t->same('blank-row-literal-punctuation', $tsvEvidence['generatedNativeParityEvidence']['samples'][6]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][6]['readerOptions'] ?? null);
        $t->same('comment-looking-data', $tsvEvidence['generatedNativeParityEvidence']['samples'][7]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][7]['readerOptions'] ?? null);
        $t->same('no-header-edge-delimiters', $tsvEvidence['generatedNativeParityEvidence']['samples'][8]['name'] ?? null);
        $t->same(['header' => false], $tsvEvidence['generatedNativeParityEvidence']['samples'][8]['readerOptions'] ?? null);
        $t->same('csv-quoted-literal', $tsvEvidence['generatedNativeParityEvidence']['samples'][9]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][9]['readerOptions'] ?? null);
        $t->same('keep-space-after-tab', $tsvEvidence['generatedNativeParityEvidence']['samples'][10]['name'] ?? null);
        $t->same(['keepSpace' => true], $tsvEvidence['generatedNativeParityEvidence']['samples'][10]['readerOptions'] ?? null);
        $t->same('crlf-rows', $tsvEvidence['generatedNativeParityEvidence']['samples'][11]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][11]['readerOptions'] ?? null);
        $t->same('quoted-tabs-and-newlines', $tsvEvidence['generatedNativeParityEvidence']['samples'][12]['name'] ?? null);
        $t->same(['quote' => '"'], $tsvEvidence['generatedNativeParityEvidence']['samples'][12]['readerOptions'] ?? null);
        $t->same('blank-leading-header', $tsvEvidence['generatedNativeParityEvidence']['samples'][13]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][13]['readerOptions'] ?? null);
        $t->same('basic-status', $tsvEvidence['generatedNativeParityEvidence']['samples'][14]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][14]['readerOptions'] ?? null);
        $t->same('header-only', $tsvEvidence['generatedNativeParityEvidence']['samples'][15]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][15]['readerOptions'] ?? null);
        $t->same('no-header-internal-trailing-empty', $tsvEvidence['generatedNativeParityEvidence']['samples'][16]['name'] ?? null);
        $t->same(['header' => false], $tsvEvidence['generatedNativeParityEvidence']['samples'][16]['readerOptions'] ?? null);
        $t->same('blank-input', $tsvEvidence['generatedNativeParityEvidence']['samples'][17]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][17]['readerOptions'] ?? null);
        $t->same('duplicate-header-labels', $tsvEvidence['generatedNativeParityEvidence']['samples'][18]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][18]['readerOptions'] ?? null);
        $t->same('escaped-quote-dialect', $tsvEvidence['generatedNativeParityEvidence']['samples'][19]['name'] ?? null);
        $t->same(['quote' => '"', 'escape' => '\\'], $tsvEvidence['generatedNativeParityEvidence']['samples'][19]['readerOptions'] ?? null);
        $t->same('literal-quote-tab-split', $tsvEvidence['generatedNativeParityEvidence']['samples'][20]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][20]['readerOptions'] ?? null);
        $t->same('leading-blank-whitespace', $tsvEvidence['generatedNativeParityEvidence']['samples'][21]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][21]['readerOptions'] ?? null);
        $t->same('unquoted-final-formfeed', $tsvEvidence['generatedNativeParityEvidence']['samples'][22]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][22]['readerOptions'] ?? null);
        $t->same('literal-quote-newline-split', $tsvEvidence['generatedNativeParityEvidence']['samples'][23]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][23]['readerOptions'] ?? null);
        $t->same('leading-empty-fields', $tsvEvidence['generatedNativeParityEvidence']['samples'][24]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][24]['readerOptions'] ?? null);
        $t->same('trailing-empty-fields', $tsvEvidence['generatedNativeParityEvidence']['samples'][25]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][25]['readerOptions'] ?? null);
        $t->same('literal-quote-header', $tsvEvidence['generatedNativeParityEvidence']['samples'][26]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][26]['readerOptions'] ?? null);
        $t->same('markdown-syntax-literal', $tsvEvidence['generatedNativeParityEvidence']['samples'][27]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][27]['readerOptions'] ?? null);
        $t->same('interior-empty-header', $tsvEvidence['generatedNativeParityEvidence']['samples'][28]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][28]['readerOptions'] ?? null);
        $t->same('trailing-empty-header', $tsvEvidence['generatedNativeParityEvidence']['samples'][29]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][29]['readerOptions'] ?? null);
        $t->same('quoted-softbreak', $tsvEvidence['generatedNativeParityEvidence']['samples'][30]['name'] ?? null);
        $t->same(['quote' => '"', 'cellLineBreak' => 'softbreak'], $tsvEvidence['generatedNativeParityEvidence']['samples'][30]['readerOptions'] ?? null);
        $t->same('post-delimiter-space', $tsvEvidence['generatedNativeParityEvidence']['samples'][31]['name'] ?? null);
        $t->same([], $tsvEvidence['generatedNativeParityEvidence']['samples'][31]['readerOptions'] ?? null);
        $t->true(in_array('tsv-tab-delimiter-reader', $tsvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('generated-tsv-native-parity-sample', $tsvEvidence['closedGaps'] ?? [], true));
        $t->true(in_array('no-dedicated-upstream-tsv-command-fixture-in-pinned-corpus', $tsvEvidence['openGaps'] ?? [], true));
        $t->true(in_array('upstream-runner-not-run', $tsvEvidence['openGaps'] ?? [], true));
        $t->same('not-run', $tsvEvidence['runnerEvidence']['status'] ?? null);
        $t->same(false, $tsvEvidence['runnerEvidence']['executed'] ?? null);
        $t->same(null, $tsvEvidence['runnerEvidence']['resultArtifact'] ?? null);
        $t->same('planned-not-run', $tsvEvidence['runnerEvidence']['commandPlanStatus'] ?? null);
        $t->same('upstream-runner-command-plan', $tsvEvidence['runnerEvidence']['commandPlan']['kind'] ?? null);
        $t->same('hydrated Pandoc upstream checkout root', $tsvEvidence['runnerEvidence']['commandPlan']['workingDirectory'] ?? null);
        $t->same('upstream-runner-non-execution-boundary', $tsvEvidence['runnerEvidence']['executionBoundary']['kind'] ?? null);
        $t->same('plan-only-not-run', $tsvEvidence['runnerEvidence']['executionBoundary']['status'] ?? null);
        $t->same(true, $tsvEvidence['runnerEvidence']['executionBoundary']['planOnly'] ?? null);
        $t->same(0, $tsvEvidence['runnerEvidence']['executionBoundary']['executedCommandCount'] ?? null);
        $t->same(false, $tsvEvidence['runnerEvidence']['executionBoundary']['upstreamRunnerParityClaimed'] ?? null);
        $t->same(['Command:', 'csv.md', '#1'], $tsvEvidence['runnerEvidence']['target']['tastyGroupPath'] ?? null);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $tsvEvidence['runnerEvidence']['target']['tastyPattern'] ?? null);
        $t->same(false, $tsvEvidence['runnerEvidence']['target']['tsvDirectFixtureAvailable'] ?? null);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRunnerPlanEvidence($tsvEvidence['runnerEvidence'] ?? []));
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
        $t->same(['id', 'title', 'note'], $csvPacket['columnNames'] ?? null);
        $t->same([3, 2, 4, 3], $csvWidth['rowWidths'] ?? null);
        $t->same([0, 1, 2, 3], $csvWidth['sourceRowIndexes'] ?? null);
        $t->same(false, $csvWidth['strict']['consistent'] ?? null);
        $t->same(2, $csvWidth['strict']['mismatchCount'] ?? null);
        $t->same('source-row-1', $csvWidth['strict']['mismatches'][0]['rowLabel'] ?? null);
        $t->same(1, $csvWidth['strict']['mismatches'][0]['missingFields'] ?? null);
        $t->same(1, $csvWidth['strict']['mismatches'][1]['extraFields'] ?? null);
        $t->same(3, $csvWidth['relaxed']['columnCount'] ?? null);
        $t->same(1, $csvWidth['relaxed']['paddedRowCount'] ?? null);
        $t->same(1, $csvWidth['relaxed']['truncatedRowCount'] ?? null);
        $t->same(2, $csvWidth['header']['mismatchCount'] ?? null);
        $t->same('header-row-width', $csvRepair['policy'] ?? null);
        $t->same(3, $csvRepair['repairedColumnCount'] ?? null);
        $t->same(2, $csvRepair['changedRowCount'] ?? null);
        $t->same('padded', $csvRepair['rows'][1]['repair'] ?? null);
        $t->same(2, $csvRepair['rows'][1]['originalColumnCount'] ?? null);
        $t->same(3, $csvRepair['rows'][1]['repairedColumnCount'] ?? null);
        $t->same(1, $csvRepair['rows'][1]['missingFieldsAdded'] ?? null);
        $t->same('truncated', $csvRepair['rows'][2]['repair'] ?? null);
        $t->same(1, $csvRepair['rows'][2]['extraFieldsDropped'] ?? null);
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
        $t->same(1, $csvSamples[0]['rowRepair']['missingFieldsAdded'] ?? null);
        $t->same(true, $csvSamples[0]['fieldQuoted'] ?? null);
        $t->same('00', $csvSamples[0]['byteHex'] ?? null);
        $t->same('NUL', $csvSamples[0]['name'] ?? null);
        $t->same(2, $csvSamples[1]['positionBeforeRepair']['sourceRow'] ?? null);
        $t->same(1, $csvSamples[1]['positionBeforeRepair']['sourceColumn'] ?? null);
        $t->same('truncated', $csvSamples[1]['rowRepair']['repair'] ?? null);
        $t->same(false, $csvSamples[1]['fieldQuoted'] ?? null);
        $t->same('1F', $csvSamples[1]['byteHex'] ?? null);
        $t->same('US', $csvSamples[1]['name'] ?? null);
        $t->same('annotate-controls-after-relaxed-row-repair', $csvControlRepair['policy'] ?? null);
        $t->same('imports/control-repair.csv', $csvControlRepair['sourcePath'] ?? null);
        $t->same(1, $csvControlRepair['controlsInPaddedRows'] ?? null);
        $t->same(2, $csvControlRepair['controlsInChangedRows'] ?? null);
        $t->same(0, $csvControlRepair['controlsInUnchangedRows'] ?? null);
        $t->same(['padded' => 1, 'unchanged' => 0, 'truncated' => 1, 'unmapped' => 0], $csvControlRepair['byRepair'] ?? null);
        $t->same('padded', $csvControlRepair['sourceRows'][0]['repair'] ?? null);
        $t->same([1], $csvControlRepair['sourceRows'][0]['columns'] ?? null);
        $t->same('quoted' . "\x00" . 'short', $csvTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $csvTable->children[1]->children[0]->children[2]->attr('text'));
        $t->same(3, count($csvTable->children[1]->children[1]->children));

        $t->same('tsv', $tsvPacket['format'] ?? null);
        $t->same('tab', $tsvPacket['delimiter'] ?? null);
        $t->same([3, 2, 4, 3], $tsvWidth['rowWidths'] ?? null);
        $t->same([3], $tsvWidth['trailingEmptyFieldRows'] ?? null);
        $t->same(2, $tsvWidth['strict']['mismatchCount'] ?? null);
        $t->same(1, $tsvWidth['relaxed']['paddedRowCount'] ?? null);
        $t->same(1, $tsvWidth['relaxed']['truncatedRowCount'] ?? null);
        $t->same(2, $tsvWidth['header']['mismatchCount'] ?? null);
        $t->same('header-row-width', $tsvRepair['policy'] ?? null);
        $t->same(3, $tsvRepair['repairedColumnCount'] ?? null);
        $t->same(2, $tsvRepair['changedRowCount'] ?? null);
        $t->same('padded', $tsvRepair['rows'][1]['repair'] ?? null);
        $t->same(1, $tsvRepair['rows'][1]['missingFieldsAdded'] ?? null);
        $t->same('truncated', $tsvRepair['rows'][2]['repair'] ?? null);
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
        $t->same(1, $tsvSamples[0]['rowRepair']['missingFieldsAdded'] ?? null);
        $t->same(false, $tsvSamples[0]['fieldQuoted'] ?? null);
        $t->same('1B', $tsvSamples[0]['byteHex'] ?? null);
        $t->same('ESC', $tsvSamples[0]['name'] ?? null);
        $t->same(2, $tsvSamples[1]['positionAfterRepair']['row'] ?? null);
        $t->same(1, $tsvSamples[1]['positionAfterRepair']['columnIndex'] ?? null);
        $t->same('truncated', $tsvSamples[1]['rowRepair']['repair'] ?? null);
        $t->same(false, $tsvSamples[1]['fieldQuoted'] ?? null);
        $t->same('07', $tsvSamples[1]['byteHex'] ?? null);
        $t->same('BEL', $tsvSamples[1]['name'] ?? null);
        $t->same(1, $tsvControlRepair['controlsInPaddedRows'] ?? null);
        $t->same(2, $tsvControlRepair['controlsInChangedRows'] ?? null);
        $t->same(0, $tsvControlRepair['controlsInUnchangedRows'] ?? null);
        $t->same(['padded' => 1, 'unchanged' => 0, 'truncated' => 1, 'unmapped' => 0], $tsvControlRepair['byRepair'] ?? null);
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
            'strictParsing' => false,
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
    'records csv quote and escape diagnostics in explicit recovery mode' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv(implode("\n", [
            'id,title,note',
            '1,"Doubled ""quote"" value","Backslash \"quote\" marker"',
            '2,unquoted "literal" quote,ok',
            '',
        ]), ['strictParsing' => false]);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $codes = array_column($packet['diagnostics'] ?? [], 'code');

        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(2, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(2, $packet['quoteInUnquotedFieldCount'] ?? null);
        $t->same(0, $packet['unclosedQuoteCount'] ?? null);
        $t->same(0, $packet['partialRecordCount'] ?? null);
        $t->same(4, $packet['diagnosticCount'] ?? null);
        $t->same([
            'delimited-text-backslash-quote-preserved',
            'delimited-text-backslash-quote-preserved',
            'delimited-text-quote-in-unquoted-field',
            'delimited-text-quote-in-unquoted-field',
        ], $codes);
        $t->same(1, $packet['diagnostics'][0]['sourceLine'] ?? null);
        $t->same(2, $packet['diagnostics'][0]['sourceLineNumber'] ?? null);
        $t->same(2, $packet['diagnostics'][0]['column'] ?? null);
        $t->same(
            strpos('1,"Doubled ""quote"" value","Backslash \"quote\" marker"', '\\"'),
            $packet['diagnostics'][0]['sourceByteColumn'] ?? null
        );
        $t->same('byte-column', $packet['diagnostics'][0]['sourceLocationUnit'] ?? null);
        $t->same(2, $packet['diagnostics'][2]['sourceLine'] ?? null);
        $t->same(3, $packet['diagnostics'][2]['sourceLineNumber'] ?? null);
        $t->same(1, $packet['diagnostics'][2]['column'] ?? null);
        $t->same(
            strpos('2,unquoted "literal" quote,ok', '"'),
            $packet['diagnostics'][2]['sourceByteColumn'] ?? null
        );
        $t->same('Doubled "quote" value', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('Backslash \"quote\" marker', $table->children[1]->children[0]->children[2]->attr('text'));
        $t->same('unquoted "literal" quote', $table->children[1]->children[1]->children[1]->attr('text'));
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
            '2,open',
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
        $t->same(1, $packet['multilineQuotedFieldCount'] ?? null);
        $t->same([1], $packet['multilineQuotedRows'] ?? null);
        $t->same(1, $packet['quotedFieldNewlineCount'] ?? null);
        $t->same(2, $packet['trailingDelimiterRowCount'] ?? null);
        $t->same([0, 1], $packet['trailingDelimiterRows'] ?? null);
        $t->same(false, $packet['unterminatedQuoteAtEof'] ?? null);
        $t->same(null, $packet['unterminatedQuoteRow'] ?? null);
        $t->same(true, $packet['partialFinalRecord'] ?? null);
        $t->same(2, $packet['partialFinalRecordRow'] ?? null);
        $t->same(2, $packet['partialFinalRecordFieldCount'] ?? null);
        $t->same([
            'delimited-text-multiline-quoted-field',
            'delimited-text-trailing-delimiter-empty-field',
            'delimited-text-partial-final-record',
            'delimited-text-trailing-empty-fields-preserved',
            'delimited-text-strict-row-width-mismatch',
            'delimited-text-row-widths-uneven',
            'delimited-text-header-width-mismatch',
        ], $codes);
        $t->same("two\nline", $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[0]->children[3]->attr('text'));
        $t->same('open', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[1]->children[2]->attr('text'));
    },
    'rejects csv quoted-field errors that local pandoc rejects' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $cases = [
            [
                "a,b\n1,\"open\n",
                'quoted field reaches end of input before a closing quote',
            ],
            [
                "a,b\n1,\"x\"z\n",
                'expected delimiter, line break, or end of input',
            ],
            [
                "a,b,c\n1,\"x\" ,y\n",
                'delimiter must immediately follow the quoted field',
            ],
            [
                "a,b\n1,\"x\"  \n2,y\n",
                'whitespace after a closing quote before a line break',
            ],
            [
                "h\n\"\"\n",
                'empty first field',
            ],
            [
                "\"\"\n",
                'empty first field',
            ],
        ];

        foreach ($cases as [$source, $expectedMessage]) {
            $message = '';
            try {
                $reader->readCsv($source);
            } catch (InvalidArgumentException $exception) {
                $message = $exception->getMessage();
            }
            $t->contains($expectedMessage, $message);
        }

        $validDocument = $reader->readCsv("a,b,c\n1,\"x\",y\n");
        $validTable = $validDocument->children[0];
        $t->same('x', $validTable->children[1]->children[0]->children[1]->attr('text'));
        $t->same('y', $validTable->children[1]->children[0]->children[2]->attr('text'));

        $trailingWhitespaceDocument = $reader->readCsv("a,b\n1,\"x\"  \n");
        $trailingWhitespacePacket = $trailingWhitespaceDocument->children[0]->attr('delimitedText');
        $t->same('x', $trailingWhitespaceDocument->children[0]->children[1]->children[0]->children[1]->attr('text'));
        $t->same(0, $trailingWhitespacePacket['closingQuoteTrailingRecordWhitespaceCount'] ?? null);

        $recoveredTrailingWhitespaceDocument = $reader->readCsv("a,b\n1,\"x\"  \n2,y\n", [
            'strictParsing' => false,
        ]);
        $recoveredTrailingWhitespacePacket = $recoveredTrailingWhitespaceDocument->children[0]->attr('delimitedText');
        $t->same('x', $recoveredTrailingWhitespaceDocument->children[0]->children[1]->children[0]->children[1]->attr('text'));
        $t->same('y', $recoveredTrailingWhitespaceDocument->children[0]->children[1]->children[1]->children[1]->attr('text'));
        $t->same(1, $recoveredTrailingWhitespacePacket['closingQuoteTrailingRecordWhitespaceCount'] ?? null);
        $t->same(['delimited-text-closing-quote-trailing-record-whitespace'], array_column($recoveredTrailingWhitespacePacket['diagnostics'] ?? [], 'code'));
        $t->same(1, $recoveredTrailingWhitespacePacket['diagnostics'][0]['row'] ?? null);
        $t->same(1, $recoveredTrailingWhitespacePacket['diagnostics'][0]['column'] ?? null);
        $t->same(2, $recoveredTrailingWhitespacePacket['diagnostics'][0]['sourceLineNumber'] ?? null);
        $t->same(8, $recoveredTrailingWhitespacePacket['diagnostics'][0]['sourceByteColumnNumber'] ?? null);

        $recoveredEmptyFirstFieldDocument = $reader->readCsv("h\n\"\"\n", [
            'strictParsing' => false,
        ]);
        $recoveredEmptyFirstFieldTable = $recoveredEmptyFirstFieldDocument->children[0];
        $recoveredEmptyFirstFieldPacket = $recoveredEmptyFirstFieldTable->attr('delimitedText');
        $recoveredEmptyFirstFieldDiagnostics = $recoveredEmptyFirstFieldPacket['diagnostics'] ?? [];
        $recoveredEmptyFirstFieldDiagnostic = $recoveredEmptyFirstFieldDiagnostics[1] ?? [];
        $t->same(['h'], $recoveredEmptyFirstFieldTable->attr('columnNames'));
        $t->same(1, $recoveredEmptyFirstFieldPacket['rowCount'] ?? null);
        $t->same(0, $recoveredEmptyFirstFieldPacket['bodyRowCount'] ?? null);
        $t->same(1, $recoveredEmptyFirstFieldPacket['blankRowCount'] ?? null);
        $t->same([1], $recoveredEmptyFirstFieldPacket['blankRows'] ?? null);
        $t->same(1, $recoveredEmptyFirstFieldPacket['emptyQuotedFirstFieldWithoutDelimiterCount'] ?? null);
        $t->same([
            'delimited-text-blank-rows-skipped',
            'delimited-text-empty-first-field-without-delimiter',
        ], array_column($recoveredEmptyFirstFieldDiagnostics, 'code'));
        $t->same(1, $recoveredEmptyFirstFieldDiagnostic['row'] ?? null);
        $t->same(0, $recoveredEmptyFirstFieldDiagnostic['column'] ?? null);
        $t->same(2, $recoveredEmptyFirstFieldDiagnostic['sourceLineNumber'] ?? null);
        $t->same(3, $recoveredEmptyFirstFieldDiagnostic['sourceByteColumnNumber'] ?? null);

        $emptyFirstFieldWithDelimiterDocument = $reader->readCsv("h\n\"\",\n");
        $emptyFirstFieldWithDelimiterPacket = $emptyFirstFieldWithDelimiterDocument->children[0]->attr('delimitedText');
        $t->same(0, $emptyFirstFieldWithDelimiterPacket['emptyQuotedFirstFieldWithoutDelimiterCount'] ?? null);
        $t->same('', $emptyFirstFieldWithDelimiterDocument->children[0]->children[1]->children[0]->children[0]->attr('text'));

        $tsvDocument = $reader->readTsv("a\tb\n1\t\"x\"z\n");
        $t->same('"x"z', $tsvDocument->children[0]->children[1]->children[0]->children[1]->attr('text'));

        $autoTsvDocument = $reader->readAuto("\"x\"z\tb\n1\t2\n");
        $t->same('tsv', $autoTsvDocument->attr('sourceFormat'));
        $t->same('"x"z', $autoTsvDocument->children[0]->children[0]->children[0]->children[0]->attr('text'));
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
    'rejects malformed csv and tsv records by default while preserving explicit recovery mode' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $cases = [
            ['csv', "a,b\n\n1,2\n", 'blank record'],
            ['csv', "\xEF\xBB\xBF\n a,b\n1,2\n", 'blank record'],
            ['csv', "a,b\n\"x\"tail,2\n", 'text after a closing quote'],
            ['csv', "a,b\n1,\"x\\\"y\"\n", 'backslash before a quote'],
            ['csv', "a,b\n\"x\n", 'quoted field reaches end of input'],
            ['tsv', "a\tb\n\n1\t2\n", 'blank record'],
        ];

        foreach ($cases as [$format, $input, $expectedMessage]) {
            $message = '';
            try {
                $format === 'tsv' ? $reader->readTsv($input) : $reader->readCsv($input);
            } catch (InvalidArgumentException $exception) {
                $message = $exception->getMessage();
            }
            $t->contains($expectedMessage, $message);
        }

        $bomDocument = $reader->readCsv("\xEF\xBB\xBFa,b\n1,2\n");
        $relaxedDocument = $reader->readCsv("a,b\n\n1,2\n", ['strictParsing' => false]);
        $relaxedPacket = $relaxedDocument->children[0]->attr('delimitedText');

        $t->same(['a', 'b'], $bomDocument->children[0]->attr('columnNames'));
        $t->same(1, $relaxedPacket['blankRowCount'] ?? null);
        $t->same(['delimited-text-blank-rows-skipped'], array_column($relaxedPacket['diagnostics'] ?? [], 'code'));
    },
    'allows trailing blank records in strict csv and tsv parsing like upstream eof whitespace' => static function (TestRunner $t): void {
        $reader = new DelimitedTextReader();
        $csvDocument = $reader->readCsv("a,b\n1,2\n\n\n");
        $tsvDocument = $reader->readTsv("a\tb\n1\t2\n\n");
        $csvTable = $csvDocument->children[0];
        $tsvTable = $tsvDocument->children[0];
        $csvPacket = $csvTable->attr('delimitedText');
        $tsvPacket = $tsvTable->attr('delimitedText');

        $t->same(['a', 'b'], $csvTable->attr('columnNames'));
        $t->same(2, $csvPacket['rowCount'] ?? null);
        $t->same(1, $csvPacket['bodyRowCount'] ?? null);
        $t->same(2, $csvPacket['blankRowCount'] ?? null);
        $t->same([2, 3], $csvPacket['blankRows'] ?? null);
        $t->same([0, 1], $csvPacket['rowWidthSummary']['sourceRowIndexes'] ?? null);
        $t->same(['delimited-text-blank-rows-skipped'], array_column($csvPacket['diagnostics'] ?? [], 'code'));
        $t->same('1', $csvTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('2', $csvTable->children[1]->children[0]->children[1]->attr('text'));

        $t->same(['a', 'b'], $tsvTable->attr('columnNames'));
        $t->same(2, $tsvPacket['rowCount'] ?? null);
        $t->same(1, $tsvPacket['bodyRowCount'] ?? null);
        $t->same(1, $tsvPacket['blankRowCount'] ?? null);
        $t->same([2], $tsvPacket['blankRows'] ?? null);
        $t->same([0, 1], $tsvPacket['rowWidthSummary']['sourceRowIndexes'] ?? null);
        $t->same(['delimited-text-blank-rows-skipped'], array_column($tsvPacket['diagnostics'] ?? [], 'code'));
        $t->same('1', $tsvTable->children[1]->children[0]->children[0]->attr('text'));
        $t->same('2', $tsvTable->children[1]->children[0]->children[1]->attr('text'));
    },
    'rejects non-boolean delimited text strict parsing option' => static function (TestRunner $t): void {
        $message = '';
        try {
            (new DelimitedTextReader())->readCsv("a,b\n", ['strictParsing' => 'false']);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        $t->contains('strictParsing option must be a boolean', $message);
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
    'records csv header row width repair provenance without widening table output' => static function (TestRunner $t): void {
        $sourceText = implode("\n", [
            'id,title,published',
            '42,"Legacy, ""quoted"" title",true',
            "43,\"Two\nline title\",false,extra",
            '44,Needs review',
            '',
        ]);
        $document = (new DelimitedTextReader())->readCsv("\xEF\xBB\xBF" . $sourceText);
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
        $t->same(['id', 'title', 'published'], $packet['columnNames'] ?? null);
        $t->same(4, $packet['rowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(4, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(12, $packet['fieldCount'] ?? null);
        $t->same(2, $packet['raggedRowCount'] ?? null);
        $t->same([2, 3], $packet['raggedRows'] ?? null);
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
        $t->same(3, $widthSummary['relaxed']['columnCount'] ?? null);
        $t->same(1, $widthSummary['relaxed']['paddedRowCount'] ?? null);
        $t->same(1, $widthSummary['relaxed']['truncatedRowCount'] ?? null);
        $t->same(2, $widthSummary['header']['mismatchCount'] ?? null);
        $t->same('header-row-width', $repairSummary['policy'] ?? null);
        $t->same([3, 3, 4, 2], $repairSummary['originalColumnCounts'] ?? null);
        $t->same(3, $repairSummary['repairedColumnCount'] ?? null);
        $t->same(2, $repairSummary['changedRowCount'] ?? null);
        $t->same(1, $repairSummary['paddedRowCount'] ?? null);
        $t->same(1, $repairSummary['truncatedRowCount'] ?? null);
        $t->same('unchanged', $repairSummary['rows'][0]['repair'] ?? null);
        $t->same(3, $repairSummary['rows'][0]['originalColumnCount'] ?? null);
        $t->same(3, $repairSummary['rows'][0]['repairedColumnCount'] ?? null);
        $t->same(0, $repairSummary['rows'][0]['missingFieldsAdded'] ?? null);
        $t->same('truncated', $repairSummary['rows'][2]['repair'] ?? null);
        $t->same('padded', $repairSummary['rows'][3]['repair'] ?? null);
        $t->same(1, $repairSummary['rows'][3]['missingFieldsAdded'] ?? null);
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
        $t->same('header-row-width', $relaxedDiagnostic['policy'] ?? null);
        $t->same(3, $relaxedDiagnostic['columnCount'] ?? null);
        $t->same(1, $relaxedDiagnostic['paddedRowCount'] ?? null);
        $t->same(1, $relaxedDiagnostic['truncatedRowCount'] ?? null);
        $t->same(['source-row-3'], array_column($relaxedDiagnostic['paddedRows'] ?? [], 'rowLabel'));
        $t->same(['source-row-2'], array_column($relaxedDiagnostic['truncatedRows'] ?? [], 'rowLabel'));
        $t->same(3, $headerDiagnostic['headerColumnCount'] ?? null);
        $t->same([3, 4, 2], $headerDiagnostic['dataColumnCounts'] ?? null);
        $t->same(2, $headerDiagnostic['mismatchCount'] ?? null);
        $t->same(['source-row-2', 'source-row-3'], array_column($headerDiagnostic['mismatches'] ?? [], 'rowLabel'));
        $t->same(3, count($table->children[0]->children[0]->children));
        $t->same("Two\nline title", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(3, count($table->children[1]->children[1]->children));
        $t->same('', $table->children[1]->children[2]->children[2]->attr('text'));

        $headerTitle = $table->children[0]->children[0]->children[1];
        $quotedTitle = $table->children[1]->children[0]->children[1];
        $multilineTitle = $table->children[1]->children[1]->children[1];
        $paddedPublished = $table->children[1]->children[2]->children[2];
        $sourceSlice = static fn (AstNode $cell): string => substr(
            $sourceText,
            (int) $cell->attr('sourceStartOffset'),
            (int) $cell->attr('sourceEndOffset') - (int) $cell->attr('sourceStartOffset')
        );

        $t->same('retained', $headerTitle->attr('sourceFieldStatus'));
        $t->same(0, $headerTitle->attr('sourceRow'));
        $t->same(1, $headerTitle->attr('sourceField'));
        $t->same(false, $headerTitle->attr('sourceQuoted'));
        $t->same(0, $headerTitle->attr('sourceStartLine'));
        $t->same(1, $headerTitle->attr('sourceStartLineNumber'));
        $t->same(3, $headerTitle->attr('sourceStartByteColumn'));
        $t->same(4, $headerTitle->attr('sourceStartByteColumnNumber'));
        $t->same(0, $headerTitle->attr('sourceEndLine'));
        $t->same(8, $headerTitle->attr('sourceEndByteColumn'));
        $t->same('byte-column', $headerTitle->attr('sourceLocationUnit'));
        $t->same('exclusive', $headerTitle->attr('sourceEndOffsetPolicy'));
        $t->same('title', $sourceSlice($headerTitle));
        $t->same('retained', $quotedTitle->attr('sourceFieldStatus'));
        $t->same(1, $quotedTitle->attr('sourceRow'));
        $t->same(2, $quotedTitle->attr('sourceRowNumber'));
        $t->same(1, $quotedTitle->attr('sourceField'));
        $t->same(2, $quotedTitle->attr('sourceFieldNumber'));
        $t->same(true, $quotedTitle->attr('sourceQuoted'));
        $t->same(false, $quotedTitle->attr('sourceMultiline'));
        $t->same(1, $quotedTitle->attr('sourceStartLine'));
        $t->same(2, $quotedTitle->attr('sourceStartLineNumber'));
        $t->same(3, $quotedTitle->attr('sourceStartByteColumn'));
        $t->same(1, $quotedTitle->attr('sourceEndLine'));
        $t->same(29, $quotedTitle->attr('sourceEndByteColumn'));
        $t->same('"Legacy, ""quoted"" title"', $sourceSlice($quotedTitle));
        $t->same('truncated', $multilineTitle->attr('rowRepair'));
        $t->same('retained', $multilineTitle->attr('sourceFieldStatus'));
        $t->same(2, $multilineTitle->attr('sourceRow'));
        $t->same(3, $multilineTitle->attr('sourceRowNumber'));
        $t->same(1, $multilineTitle->attr('sourceField'));
        $t->same(true, $multilineTitle->attr('sourceQuoted'));
        $t->same(true, $multilineTitle->attr('sourceMultiline'));
        $t->same(2, $multilineTitle->attr('sourceStartLine'));
        $t->same(3, $multilineTitle->attr('sourceStartLineNumber'));
        $t->same(3, $multilineTitle->attr('sourceStartByteColumn'));
        $t->same(3, $multilineTitle->attr('sourceEndLine'));
        $t->same(11, $multilineTitle->attr('sourceEndByteColumn'));
        $t->same('"Two' . "\n" . 'line title"', $sourceSlice($multilineTitle));
        $t->same('padded', $paddedPublished->attr('rowRepair'));
        $t->same('padded', $paddedPublished->attr('sourceFieldStatus'));
        $t->same(3, $paddedPublished->attr('sourceRow'));
        $t->same(4, $paddedPublished->attr('sourceRowNumber'));
        $t->same(null, $paddedPublished->attr('sourceField'));
        $t->same(null, $paddedPublished->attr('sourceStartOffset'));
        $t->same(null, $paddedPublished->attr('sourceStartLine'));
    },
    'records tsv blank rows trailing empty fields and repair summaries' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readTsv(implode("\n", [
            "key\tvalue\t",
            '',
            "alpha\t10\t",
            "beta\t20",
            "gamma\t30\t",
            '',
        ]), ['strictParsing' => false]);
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
        $t->same('header-row-width', $repairSummary['policy'] ?? null);
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
        $t->same('header-row-width', $relaxedDiagnostic['policy'] ?? null);
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
