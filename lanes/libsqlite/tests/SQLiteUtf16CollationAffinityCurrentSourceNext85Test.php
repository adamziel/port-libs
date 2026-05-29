<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;

$tests = [];

$enc = static fn (string $text, int|string $encoding): string => SQLiteUtf16CollationAffinityCursor::encodeText($text, $encoding);
$entry = static fn (int $rowid, mixed $value, string $encoding = 'UTF-16LE', array $payload = []): array => is_string($value)
    ? ['valueBytes' => $enc($value, $encoding), 'textEncoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    }, 'rowid' => $rowid, 'payload' => $payload + ['option_id' => $rowid, 'option_value' => $value]]
    : ['value' => $value, 'rowid' => $rowid, 'payload' => $payload + ['option_id' => $rowid, 'option_value' => $value]];

$numericEntries = [
    $entry(1, '2', 'UTF-16LE'),
    $entry(2, '02', 'UTF-16BE'),
    $entry(3, '2.0', 'UTF-16LE'),
    $entry(4, '10', 'UTF-16BE'),
    $entry(5, 'plugin_2', 'UTF-16LE'),
    $entry(6, 2),
    $entry(7, new SQLiteBlobValue('2')),
    $entry(8, null),
];

$numeric = static fn (mixed $probe = null): SQLiteUtf16CollationAffinityCursor => new SQLiteUtf16CollationAffinityCursor(
    $numericEntries,
    $probe ?? ['valueBytes' => $enc('2', 'UTF-16BE'), 'textEncoding' => 3],
    'NUMERIC',
    'NONE',
    'BINARY',
);

$numericCases = [
    'numeric seek lands on utf16le integer text' => [0, 'currentRowid', 1],
    'numeric current encoding is utf16le' => [0, 'currentEncoding', 'UTF-16LE'],
    'numeric probe coerces to integer' => [0, 'probeCoerced', 2],
    'numeric current coerces to integer' => [0, 'currentCoerced', 2],
    'numeric current coerced storage is integer' => [0, 'currentCoercedStorage', 'integer'],
    'numeric comparison ties first peer' => [0, 'comparisonToProbe', 0],
    'numeric next peer is utf16be leading zero' => [0, 'nextRowid', 2],
    'numeric next comparison also ties' => [0, 'nextComparisonToProbe', 0],
    'numeric second peer keeps utf16be encoding' => [1, 'currentEncoding', 'UTF-16BE'],
    'numeric real-looking text coerces to integer' => [2, 'currentCoercedStorage', 'integer'],
    'numeric integer storage peer is after utf16 peers' => [3, 'currentRowid', 6],
    'numeric integer peer equals probe' => [3, 'currentEqualsProbe', true],
    'numeric blob storage remains blob after numeric peers' => [4, 'currentStorage', 'blob'],
    'numeric ten compares after probe' => [5, 'comparisonToProbe', 1],
    'numeric text fallback sorts after numeric ten' => [6, 'currentRowid', 5],
];

foreach ($numericCases as $name => [$advance, $path, $expected]) {
    $tests['utf16 collation affinity current source nextEightFive numeric ' . $name] = static function (TestRunner $t) use ($numeric, $advance, $path, $expected): void {
        $cursor = $numeric();
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->currentNextPlan()[$path]);
    };
}

$textEntries = [
    $entry(10, 'Plugin_Alpha', 'UTF-16LE'),
    $entry(11, 'plugin_alpha', 'UTF-16BE'),
    $entry(12, 'plugin_alpha ', 'UTF-16LE'),
    $entry(13, 'plugin_beta', 'UTF-16BE'),
    $entry(14, 'plugin_Éclair', 'UTF-16LE'),
    $entry(15, 'plugin_éclair', 'UTF-16BE'),
    $entry(16, 'theme_alpha', 'UTF-8'),
];

$text = static fn (string $collation, mixed $probe): SQLiteUtf16CollationAffinityCursor => new SQLiteUtf16CollationAffinityCursor(
    $textEntries,
    $probe,
    'TEXT',
    'TEXT',
    $collation,
);

$textCases = [
    'nocase uppercase seek lands on utf16le uppercase alpha' => ['NOCASE', 'PLUGIN_ALPHA', 0, 'currentRowid', 10],
    'nocase uppercase alpha equals probe' => ['NOCASE', 'PLUGIN_ALPHA', 0, 'currentEqualsProbe', true],
    'nocase next utf16be lowercase alpha also equals' => ['NOCASE', 'PLUGIN_ALPHA', 0, 'nextComparisonToProbe', 0],
    'nocase lower peer encoding is utf16be' => ['NOCASE', 'PLUGIN_ALPHA', 1, 'currentEncoding', 'UTF-16BE'],
    'nocase padded alpha compares after exact alpha' => ['NOCASE', 'PLUGIN_ALPHA', 2, 'comparisonToProbe', 1],
    'nocase e acute does not fold to uppercase e acute' => ['NOCASE', 'plugin_éclair', 0, 'currentRowid', 15],
    'nocase e acute current is utf16be' => ['NOCASE', 'plugin_éclair', 0, 'currentEncoding', 'UTF-16BE'],
    'binary uppercase sorts before lowercase seek target' => ['BINARY', 'plugin_alpha', 0, 'currentRowid', 11],
    'binary exact lowercase alpha equals probe' => ['BINARY', 'plugin_alpha', 0, 'comparisonToProbe', 0],
    'binary next padded lowercase alpha is after probe' => ['BINARY', 'plugin_alpha', 0, 'nextComparisonToProbe', 1],
    'rtrim padded probe lands on unpadded lowercase alpha' => ['RTRIM', 'plugin_alpha ', 0, 'currentRowid', 11],
    'rtrim unpadded alpha equals padded probe' => ['RTRIM', 'plugin_alpha ', 0, 'currentEqualsProbe', true],
    'rtrim next padded alpha equals same probe' => ['RTRIM', 'plugin_alpha ', 0, 'nextComparisonToProbe', 0],
    'rtrim padded peer keeps utf16le bytes' => ['RTRIM', 'plugin_alpha ', 1, 'currentBytesHex', '70006c007500670069006e005f0061006c007000680061002000'],
    'utf8 text encoding can share cursor with utf16 rows' => ['NOCASE', 'theme_alpha', 0, 'currentEncoding', 'UTF-8'],
];

foreach ($textCases as $name => [$collation, $probe, $advance, $path, $expected]) {
    $tests['utf16 collation affinity current source nextEightFive text ' . $name] = static function (TestRunner $t) use ($text, $collation, $probe, $advance, $path, $expected): void {
        $cursor = $text($collation, $probe);
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->currentNextPlan()[$path]);
    };
}

$tests['utf16 collation affinity current source nextEightFive remaining numeric rowids after seek'] = static function (TestRunner $t) use ($numeric): void {
    $t->same([1, 2, 3, 6, 7, 4, 5, 8], array_column($numeric()->remaining(), 'rowid'));
};

$tests['utf16 collation affinity current source nextEightFive remaining rtrim rowids after padded alpha seek'] = static function (TestRunner $t) use ($text): void {
    $t->same([11, 12, 13, 14, 15, 16], array_column($text('RTRIM', 'plugin_alpha ')->remaining(), 'rowid'));
};

$tests['utf16 collation affinity current source nextEightFive seek can replace probe with utf16be bytes'] = static function (TestRunner $t) use ($text, $enc): void {
    $cursor = $text('NOCASE', 'zzz');
    $cursor->seek(['valueBytes' => $enc('plugin_beta', 'UTF-16BE'), 'textEncoding' => 3]);
    $t->same(13, $cursor->currentNextPlan()['currentRowid']);
};

$tests['utf16 collation affinity current source nextEightFive seek high value reaches eof'] = static function (TestRunner $t) use ($text): void {
    $t->same(true, $text('BINARY', 'zzzz')->currentNextPlan()['eof']);
};

$tests['utf16 collation affinity current source nextEightFive eof keeps dependency tags'] = static function (TestRunner $t) use ($text): void {
    $t->same('sqlite-utf16-decode', $text('BINARY', 'zzzz')->currentNextPlan()['dependencies'][0]);
};

$wpRows = [
    ['option_id' => 21, 'option_value_bytes' => $enc('02', 'UTF-16LE'), 'text_encoding' => 2, 'autoload' => 'yes'],
    ['option_id' => 22, 'option_value_bytes' => $enc('10', 'UTF-16BE'), 'text_encoding' => 3, 'autoload' => 'yes'],
    ['option_id' => 23, 'option_value_bytes' => $enc('Plugin_Alpha ', 'UTF-16LE'), 'text_encoding' => 2, 'autoload' => 'no'],
    ['option_id' => 24, 'option_value' => 2, 'autoload' => 'yes'],
];

$tests['utf16 collation affinity current source nextEightFive wordpress numeric seek includes utf16 and integer peers'] = static function (TestRunner $t) use ($wpRows, $enc): void {
    $rows = SQLiteUtf16CollationAffinityCursor::wordpressOptionValueSeek($wpRows, ['valueBytes' => $enc('2', 'UTF-16BE'), 'textEncoding' => 3], 'NUMERIC', 'NONE');
    $t->same([21, 24, 22, 23], array_column($rows, 'rowid'));
};

$tests['utf16 collation affinity current source nextEightFive wordpress text seek reports decoded payload'] = static function (TestRunner $t) use ($wpRows): void {
    $rows = SQLiteUtf16CollationAffinityCursor::wordpressOptionValueSeek($wpRows, 'Plugin_Alpha', 'TEXT', 'TEXT', 'RTRIM');
    $t->same('Plugin_Alpha ', $rows[0]['value']);
};

$tests['utf16 collation affinity current source nextEightFive wordpress text seek reports utf16le encoding'] = static function (TestRunner $t) use ($wpRows): void {
    $rows = SQLiteUtf16CollationAffinityCursor::wordpressOptionValueSeek($wpRows, 'Plugin_Alpha', 'TEXT', 'TEXT', 'RTRIM');
    $t->same('UTF-16LE', $rows[0]['encoding']);
};

$tests['utf16 collation affinity current source nextEightFive encoder rejects malformed utf8'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityCursor::encodeText("plugin_\xc3", 'UTF-16LE'));
};

$tests['utf16 collation affinity current source nextEightFive rejects odd utf16 source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['valueBytes' => "\x70", 'textEncoding' => 2, 'rowid' => 1, 'payload' => []]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive rejects high surrogate source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['valueBytes' => "\x3d\xd8", 'textEncoding' => 2, 'rowid' => 1, 'payload' => []]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive rejects low surrogate source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['valueBytes' => "\xdc\x00", 'textEncoding' => 3, 'rowid' => 1, 'payload' => []]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive rejects unsupported encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['valueBytes' => 'p', 'textEncoding' => 4, 'rowid' => 1, 'payload' => []]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive rejects unsupported affinity'] = static function (TestRunner $t) use ($numericEntries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor($numericEntries, 2, 'VECTOR'));
};

$tests['utf16 collation affinity current source nextEightFive rejects unsupported collation'] = static function (TestRunner $t) use ($textEntries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor($textEntries, 'p', 'TEXT', 'TEXT', 'WP_LOCALE'));
};

$tests['utf16 collation affinity current source nextEightFive rejects missing payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['value' => 'p', 'rowid' => 1]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive rejects non integer rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['value' => 'p', 'rowid' => '1', 'payload' => []]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive rejects non string bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16CollationAffinityCursor([['valueBytes' => 10, 'textEncoding' => 2, 'rowid' => 1, 'payload' => []]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive wordpress rejects missing option value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityCursor::wordpressOptionValueSeek([['option_id' => 1]], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive wordpress rejects missing encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityCursor::wordpressOptionValueSeek([['option_id' => 1, 'option_value_bytes' => 'p']], 'p'));
};

$tests['utf16 collation affinity current source nextEightFive wordpress rejects non integer option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityCursor::wordpressOptionValueSeek([['option_id' => '1', 'option_value' => 'p']], 'p'));
};

return $tests;
