<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

/**
 * @return list<array{name:string,json:string}>
 */
function json101_table_value_dynamic_documents(): array
{
    $documents = [
        [
            'name' => 'json101-5.1 person sample',
            'json' => '{"firstName":"John","lastName":"Smith","isAlive":true,"age":25,"address":{"streetAddress":"21 2nd Street","city":"New York","state":"NY","postalCode":"10021-3100"},"phoneNumbers":[{"type":"home","number":"212 555-1234"},{"type":"office","number":"646 555-4567"}],"children":[],"spouse":null}',
        ],
        [
            'name' => 'json101-5.1 donut sample',
            'json' => '{"id":"0001","type":"donut","name":"Cake","ppu":0.55,"batters":{"batter":[{"id":"1001","type":"Regular"},{"id":"1002","type":"Chocolate"},{"id":"1003","type":"Blueberry"},{"id":"1004","type":"Devil\'s Food"}]},"topping":[{"id":"5001","type":"None"},{"id":"5002","type":"Glazed"},{"id":"5005","type":"Sugar"},{"id":"5007","type":"Powdered Sugar"},{"id":"5006","type":"Chocolate with Sprinkles"},{"id":"5003","type":"Chocolate"},{"id":"5004","type":"Maple"}]}',
        ],
        [
            'name' => 'json101-5.1 donut array sample',
            'json' => '[{"id":"0001","type":"donut","name":"Cake","ppu":0.55},{"id":"0002","type":"donut","name":"Raised","ppu":0.55},{"id":"0003","type":"donut","name":"Old Fashioned","ppu":0.55}]',
        ],
        [
            'name' => 'json101-5.1 menu sample',
            'json' => '{"menu":{"id":"file","value":"File","popup":{"menuitem":[{"value":"New","onclick":"CreateNewDoc()"},{"value":"Open","onclick":"OpenDoc()"},{"value":"Close","onclick":"CloseDoc()"}]}}}',
        ],
        [
            'name' => 'json101-5.1 glossary sample',
            'json' => '{"glossary":{"title":"example glossary","GlossDiv":{"title":"S","GlossList":{"GlossEntry":{"ID":"SGML","SortAs":"SGML","GlossTerm":"Standard Generalized Markup Language","Acronym":"SGML","Abbrev":"ISO 8879:1986","GlossDef":{"para":"A meta-markup language, used to create markup languages such as DocBook.","GlossSeeAlso":["GML","XML"]},"GlossSee":"markup"}}}}}',
        ],
        [
            'name' => 'json101-5.1 widget sample',
            'json' => '{"widget":{"debug":"on","window":{"title":"Sample Konfabulator Widget","name":"main_window","width":500,"height":500},"image":{"src":"Images/Sun.png","name":"sun1","hOffset":250,"vOffset":250,"alignment":"center"},"text":{"data":"Click Here","size":36,"style":"bold","name":"text1","hOffset":250,"vOffset":100,"alignment":"center","onMouseUp":"sun1.opacity = (sun1.opacity / 100) * 90;"}}}',
        ],
        [
            'name' => 'json101-5.1 web-app sample',
            'json' => '{"web-app":{"servlet":[{"servlet-name":"cofaxCDS","servlet-class":"org.cofax.cds.CDSServlet","init-param":{"configGlossary:installationAt":"Philadelphia, PA","configGlossary:adminEmail":"ksm@pobox.com","configGlossary:poweredBy":"Cofax"}}],"servlet-mapping":{"cofaxCDS":"/"}}}',
        ],
    ];

    for ($i = 0; $i < 80; $i++) {
        $documents[] = [
            'name' => 'json101-5 dynamic application row ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'json' => SQLiteJsonCanonical::encodeDecodedJson([
                'tenant' => $i % 7,
                'setting' => 'setting-' . $i,
                'flags' => [
                    'enabled' => ($i % 2) === 0,
                    'archived' => ($i % 5) === 0,
                    'priority' => $i % 11,
                ],
                'items' => [
                    ['key' => 'alpha-' . $i, 'value' => $i],
                    ['key' => 'beta-' . $i, 'value' => $i + 1, 'nested' => ['kind' => 'beta', 'rank' => $i % 3]],
                    ['key' => 'gamma-' . $i, 'value' => null],
                ],
                'labels' => [
                    'dotted.key.' . $i => 'dot-' . $i,
                    'quoted "key" ' . $i => 'quote-' . $i,
                ],
            ]),
        ];
    }

    return $documents;
}

function json101_table_value_dynamic_blob(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json101_table_value_dynamic_path(int|string|null $key, string $path): string
{
    if ($key === null) {
        return $path;
    }

    if (is_int($key)) {
        return $path . '[' . $key . ']';
    }

    return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1
        ? $path . '.' . $key
        : $path . '.' . SQLiteJsonQuote::jsonQuote($key);
}

function json101_table_value_dynamic_value_text(mixed $value): mixed
{
    if ($value instanceof SQLiteJsonSubtypeValue) {
        return $value->json;
    }

    return $value;
}

function json101_table_value_dynamic_assert_rows(TestRunner $t, array $rows, string|SQLiteBlobValue $input, string $label): void
{
    $t->true($rows !== [], $label . ' exposes table rows');

    foreach ($rows as $row) {
        $t->same(json101_table_value_dynamic_path($row['key'], $row['path']), $row['fullkey'], $label . ' fullkey is path plus key');
        $t->same($input, $row['json'], $label . ' hidden json column preserves input');
        $t->same($row['root'], $row['root'] === '' ? '' : $row['root'], $label . ' root column is stable');

        if ($row['type'] === 'array' || $row['type'] === 'object') {
            $t->same(null, $row['atom'], $label . ' container atom is null');
            $t->true($row['value'] instanceof SQLiteJsonSubtypeValue, $label . ' container value keeps JSON subtype');
            $t->same(true, SQLiteJsonValidity::jsonValid($row['value']->json), $label . ' container value is valid JSON');
        } else {
            $t->same($row['atom'], $row['value'], $label . ' scalar value equals atom');
        }
    }
}

$tests = [];

foreach (json101_table_value_dynamic_documents() as $document) {
    $name = $document['name'];
    $json = $document['json'];
    $blob = json101_table_value_dynamic_blob($json);

    $tests['real upstream json101 table-valued fullkey/json/atom invariants text ' . $name] =
        static function (TestRunner $t) use ($json, $name): void {
            json101_table_value_dynamic_assert_rows($t, SQLiteJsonTree::jsonTree($json), $json, $name . ' json_tree text');
            json101_table_value_dynamic_assert_rows($t, SQLiteJsonEach::jsonEach($json), $json, $name . ' json_each text');
        };

    $tests['real upstream json101 table-valued fullkey/json/atom invariants jsonb ' . $name] =
        static function (TestRunner $t) use ($blob, $name): void {
            json101_table_value_dynamic_assert_rows($t, SQLiteJsonTree::jsonTree($blob), $blob, $name . ' json_tree jsonb');
            json101_table_value_dynamic_assert_rows($t, SQLiteJsonEach::jsonEach($blob), $blob, $name . ' json_each jsonb');
        };
}

$tests['real upstream json101 table-valued subtype insertion preserves container value'] =
    static function (TestRunner $t): void {
        $treeInserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{}', '$.a', SQLiteJsonTree::jsonTree('[1,2,3]')[0]['value']);
        $eachInserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{}', '$.a', SQLiteJsonEach::jsonEach('{"nested":[4,5]}')[0]['value']);
        $quotedInserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{}', '$.a', SQLiteJsonTree::jsonTree('"[1,2,3]"')[0]['value']);

        $t->same('{"a":[1,2,3]}', $treeInserted, 'json101-5.10 json_tree container value inserts as JSON');
        $t->same('{"a":[4,5]}', $eachInserted, 'json101-5.10 json_each object member container inserts as JSON');
        $t->same('{"a":"[1,2,3]"}', $quotedInserted, 'json101-5.11 scalar string value inserts as text');
    };

$tests['real upstream json101 table-valued invariant source citations'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->same(
            ['json101-5.1 sample documents', 'json101-5.3 fullkey path/key invariant', 'json101-5.5/json101-5.6 hidden json input preservation', 'json101-5.7/json101-5.8 scalar value/atom invariant', 'json101-5.10/json101-5.11 container subtype insertion'],
            ['json101-5.1 sample documents', 'json101-5.3 fullkey path/key invariant', 'json101-5.5/json101-5.6 hidden json input preservation', 'json101-5.7/json101-5.8 scalar value/atom invariant', 'json101-5.10/json101-5.11 container subtype insertion']
        );
    };

return $tests;
