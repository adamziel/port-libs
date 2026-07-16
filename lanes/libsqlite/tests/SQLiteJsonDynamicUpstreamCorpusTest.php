<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;

$tests = [];

$documents = [
    'json101 top true' => 'true',
    'json101 top false' => 'false',
    'json101 top null' => 'null',
    'json101 top integer' => '123',
    'json101 top negative integer' => '-234',
    'json101 top real' => '34.5e+6',
    'json101 top empty text' => '""',
    'json101 top quote text' => '"\\""',
    'json101 top backslash text' => '"\\\\"',
    'json101 top alphabet text' => '"abcdefghijlmnopqrstuvwxyz"',
    'json101 top empty array' => '[]',
    'json101 top empty object' => '{}',
    'json101 mixed array' => '[true,false,null,123,-234,34.5e+6,{},[]]',
    'json101 nested object' => '{"a":true,"b":{"c":false}}',
    'json102 extract base' => '{"a":2,"c":[4,5,{"f":7}]}',
    'json102 extract multi' => '{"a":2,"c":[4,5],"f":7}',
    'json102 array length root' => '[1,2,3,4]',
    'json102 array length object' => '{"one":[1,2,3]}',
    'json101 person record' => '{"firstName":"John","lastName":"Smith","isAlive":true,"age":25,"address":{"streetAddress":"21 2nd Street","city":"New York","state":"NY","postalCode":"10021-3100"},"phoneNumbers":[{"type":"home","number":"212 555-1234"},{"type":"office","number":"646 555-4567"}],"children":[],"spouse":null}',
    'json101 donut record' => '{"id":"0001","type":"donut","name":"Cake","ppu":0.55,"batters":{"batter":[{"id":"1001","type":"Regular"},{"id":"1002","type":"Chocolate"},{"id":"1003","type":"Blueberry"},{"id":"1004","type":"Devil\'s Food"}]},"topping":[{"id":"5001","type":"None"},{"id":"5002","type":"Glazed"},{"id":"5005","type":"Sugar"},{"id":"5007","type":"Powdered Sugar"},{"id":"5006","type":"Chocolate with Sprinkles"},{"id":"5003","type":"Chocolate"},{"id":"5004","type":"Maple"}]}',
    'json101 donut array' => '[{"id":"0001","type":"donut","name":"Cake","ppu":0.55},{"id":"0002","type":"donut","name":"Raised","ppu":0.55},{"id":"0003","type":"donut","name":"Old Fashioned","ppu":0.55}]',
    'json101 menu record' => '{"menu":{"id":"file","value":"File","popup":{"menuitem":[{"value":"New","onclick":"CreateNewDoc()"},{"value":"Open","onclick":"OpenDoc()"},{"value":"Close","onclick":"CloseDoc()"}]}}}',
    'json101 glossary record' => '{"glossary":{"title":"example glossary","GlossDiv":{"title":"S","GlossList":{"GlossEntry":{"ID":"SGML","SortAs":"SGML","GlossTerm":"Standard Generalized Markup Language","Acronym":"SGML","Abbrev":"ISO 8879:1986","GlossDef":{"para":"A meta-markup language, used to create markup languages such as DocBook.","GlossSeeAlso":["GML","XML"]},"GlossSee":"markup"}}}}}',
    'json101 widget record' => '{"widget":{"debug":"on","window":{"title":"Sample Konfabulator Widget","name":"main_window","width":500,"height":500},"image":{"src":"Images/Sun.png","name":"sun1","hOffset":250,"vOffset":250,"alignment":"center"},"text":{"data":"Click Here","size":36,"style":"bold","name":"text1","hOffset":250,"vOffset":100,"alignment":"center","onMouseUp":"sun1.opacity = (sun1.opacity / 100) * 90;"}}}',
    'json101 web-app record' => '{"web-app":{"servlet":[{"servlet-name":"cofaxCDS","servlet-class":"org.cofax.cds.CDSServlet","init-param":{"configGlossary:installationAt":"Philadelphia, PA","configGlossary:adminEmail":"ksm@pobox.com","configGlossary:poweredBy":"Cofax"}}],"servlet-mapping":{"cofaxCDS":"/"}}}',
];

$paths = [
    '$',
    '$.a',
    '$.b',
    '$.b.c',
    '$.c',
    '$.c[0]',
    '$.c[2]',
    '$.c[2].f',
    '$[0]',
    '$[1]',
    '$[6]',
    '$[7]',
    '$.one',
    '$.missing',
    '$.address.city',
    '$.phoneNumbers',
    '$.phoneNumbers[0].type',
    '$.phoneNumbers[1].number',
    '$.children',
    '$.spouse',
    '$.batters.batter',
    '$.batters.batter[3].type',
    '$.topping[4].type',
    '$[2].name',
    '$.menu.popup.menuitem[1].onclick',
    '$.glossary.GlossDiv.GlossList.GlossEntry.GlossDef.GlossSeeAlso[1]',
    '$.widget.window.width',
    '$.widget.image.alignment',
    '$.widget.text.onMouseUp',
    '$.web-app.servlet[0].init-param.configGlossary:adminEmail',
];

$decode = static fn (string $json): mixed => json_decode($json, false, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

$pathSegments = static function (string $path): array {
    if ($path === '$') {
        return [];
    }

    $segments = [];
    $offset = 1;
    $length = strlen($path);
    while ($offset < $length) {
        if ($path[$offset] === '.') {
            $offset++;
            $end = $offset;
            while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                $end++;
            }
            $segments[] = ['member', substr($path, $offset, $end - $offset)];
            $offset = $end;
            continue;
        }
        if ($path[$offset] === '[') {
            $end = strpos($path, ']', $offset);
            if ($end === false) {
                throw new RuntimeException("Malformed test path: {$path}");
            }
            $segments[] = ['index', (int) substr($path, $offset + 1, $end - $offset - 1)];
            $offset = $end + 1;
            continue;
        }

        throw new RuntimeException("Malformed test path: {$path}");
    }

    return $segments;
};

$locate = static function (mixed $value, string $path) use ($pathSegments): array {
    foreach ($pathSegments($path) as [$type, $key]) {
        if ($type === 'member') {
            if ($value instanceof stdClass) {
                if (!property_exists($value, $key)) {
                    return ['found' => false, 'value' => null];
                }
                $value = $value->{$key};
                continue;
            }
            if (!is_array($value) || array_is_list($value) || !array_key_exists($key, $value)) {
                return ['found' => false, 'value' => null];
            }
            $value = $value[$key];
            continue;
        }

        if (!is_array($value) || !array_is_list($value) || !array_key_exists($key, $value)) {
            return ['found' => false, 'value' => null];
        }
        $value = $value[$key];
    }

    return ['found' => true, 'value' => $value];
};

$typeName = static function (mixed $value): string {
    if ($value === null) {
        return 'null';
    }
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if (is_int($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'real';
    }
    if (is_string($value)) {
        return 'text';
    }
    if ($value instanceof stdClass) {
        return 'object';
    }

    return array_is_list($value) ? 'array' : 'object';
};

$canonical = static function (mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
};

$extractExpected = static function (array $located) use ($canonical): mixed {
    if (!$located['found']) {
        return null;
    }

    $value = $located['value'];
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }

    return $canonical($value);
};

$jsonbNormalize = static function (mixed $value) use (&$jsonbNormalize): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $jsonbNormalize(SQLiteJsonB::decode($value->bytes));
    }
    if ($value instanceof stdClass) {
        return array_map($jsonbNormalize, get_object_vars($value));
    }
    if (is_array($value)) {
        return array_map($jsonbNormalize, $value);
    }

    return $value;
};

foreach ($documents as $documentName => $json) {
    $decoded = $decode($json);
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($decoded));

    foreach ($paths as $path) {
        $located = $locate($decoded, $path);
        $expectedType = $located['found'] ? $typeName($located['value']) : null;
        $expectedLength = $located['found'] && is_array($located['value']) && array_is_list($located['value'])
            ? count($located['value'])
            : ($located['found'] ? 0 : null);
        $expectedExtract = $extractExpected($located);
        $expectedJsonbExtract = $located['found'] && (is_array($located['value']) || is_object($located['value']))
            ? $jsonbNormalize($located['value'])
            : $expectedExtract;

        $tests["upstream json dynamic json101/json102 type text {$documentName} {$path}"] = static function (TestRunner $t) use ($json, $path, $expectedType): void {
            $t->same($expectedType, SQLiteJsonInspection::jsonType($json, $path));
        };

        $tests["upstream json dynamic json101/json102 type jsonb {$documentName} {$path}"] = static function (TestRunner $t) use ($jsonb, $path, $expectedType): void {
            $t->same($expectedType, SQLiteJsonInspection::jsonType($jsonb, $path));
        };

        $tests["upstream json dynamic json101/json102 array length text {$documentName} {$path}"] = static function (TestRunner $t) use ($json, $path, $expectedLength): void {
            $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($json, $path));
        };

        $tests["upstream json dynamic json101/json102 array length jsonb {$documentName} {$path}"] = static function (TestRunner $t) use ($jsonb, $path, $expectedLength): void {
            $t->same($expectedLength, SQLiteJsonInspection::jsonArrayLength($jsonb, $path));
        };

        $tests["upstream json dynamic json101/json102 extract text {$documentName} {$path}"] = static function (TestRunner $t) use ($json, $path, $expectedExtract): void {
            $t->same($expectedExtract, SQLiteJsonExtract::extract($json, $path));
        };

        $tests["upstream json dynamic json101/json102 extract jsonb input {$documentName} {$path}"] = static function (TestRunner $t) use ($jsonb, $path, $expectedExtract): void {
            $t->same($expectedExtract, SQLiteJsonExtract::extract($jsonb, $path));
        };

        $tests["upstream json dynamic json101/json102 jsonb extract text {$documentName} {$path}"] = static function (TestRunner $t) use ($json, $path, $expectedJsonbExtract, $jsonbNormalize): void {
            $t->same($expectedJsonbExtract, $jsonbNormalize(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json, $path)));
        };

        $tests["upstream json dynamic json101/json102 jsonb extract jsonb {$documentName} {$path}"] = static function (TestRunner $t) use ($jsonb, $path, $expectedJsonbExtract, $jsonbNormalize): void {
            $t->same($expectedJsonbExtract, $jsonbNormalize(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, $path)));
        };
    }
}

return $tests;
