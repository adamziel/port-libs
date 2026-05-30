<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityRangeCurrentSourceCursor;
use PortLibs\LibSqlite\SQLiteBlobValue;

$tests = [];

$entries = [
    ['key' => null, 'rowid' => 1, 'payload' => ['option_name' => 'nullish']],
    ['key' => '001', 'rowid' => 2, 'payload' => ['option_name' => 'leading_zero']],
    ['key' => '2', 'rowid' => 3, 'payload' => ['option_name' => 'two_text']],
    ['key' => 2, 'rowid' => 4, 'payload' => ['option_name' => 'two_int']],
    ['key' => '2.0', 'rowid' => 5, 'payload' => ['option_name' => 'two_real_text']],
    ['key' => '09', 'rowid' => 6, 'payload' => ['option_name' => 'nine']],
    ['key' => '10', 'rowid' => 7, 'payload' => ['option_name' => 'ten']],
    ['key' => 10.0, 'rowid' => 8, 'payload' => ['option_name' => 'ten_real']],
    ['key' => '10e0', 'rowid' => 9, 'payload' => ['option_name' => 'ten_exp']],
    ['key' => 'not-a-number', 'rowid' => 10, 'payload' => ['option_name' => 'text']],
    ['key' => new SQLiteBlobValue('5'), 'rowid' => 11, 'payload' => ['option_name' => 'blob']],
];

$numericCursor = static fn (): SQLiteAffinityRangeCurrentSourceCursor => new SQLiteAffinityRangeCurrentSourceCursor($entries, 2, 10, 'NUMERIC', 'BINARY');

$numericPlanCases = [
    'seek lands on first numeric two text row' => [0, 'currentRowid', 3],
    'seek exposes integer peer as next row' => [0, 'nextRowid', 4],
    'current lower comparison is equal after numeric affinity' => [0, 'currentLowerComparison', 0],
    'current upper comparison is below exclusive ten' => [0, 'currentUpperComparison', -1],
    'current storage remains text before affinity coercion' => [0, 'currentStorage', 'text'],
    'integer peer remains in range' => [1, 'currentInRange', true],
    'real text peer remains in range' => [2, 'currentInRange', true],
    'nine text remains in range' => [3, 'currentRowid', 6],
    'ten text is exclusive upper boundary' => [4, 'currentInRange', false],
    'ten text upper comparison is equal' => [4, 'currentUpperComparison', 0],
    'ten real next peer is also outside range' => [4, 'nextInRange', false],
    'affinity reports numeric' => [0, 'affinity', 'NUMERIC'],
    'collation reports binary' => [0, 'collation', 'BINARY'],
    'lower bound coerces to integer storage' => [0, 'lowerStorage', 'integer'],
    'upper bound coerces to integer storage' => [0, 'upperStorage', 'integer'],
];

foreach ($numericPlanCases as $name => [$advance, $path, $expected]) {
    $tests['affinity collation range current source next83 numeric plan ' . $name] = static function (TestRunner $t) use ($numericCursor, $advance, $path, $expected): void {
        $cursor = $numericCursor();
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->currentNextPlan()[$path]);
    };
}

$tests['affinity collation range current source next83 numeric matched rows exclude null text ten and blob'] = static function (TestRunner $t) use ($numericCursor): void {
    $t->same([3, 4, 5, 6], array_column($numericCursor()->matchedRows(), 'rowid'));
};

$tests['affinity collation range current source next83 numeric matched storage remains original'] = static function (TestRunner $t) use ($numericCursor): void {
    $t->same(['text', 'integer', 'text', 'text'], array_column($numericCursor()->matchedRows(), 'storage'));
};

$nameEntries = [
    ['key' => 'Alpha', 'rowid' => 20, 'payload' => ['option_name' => 'Alpha']],
    ['key' => 'alpha ', 'rowid' => 21, 'payload' => ['option_name' => 'alpha ']],
    ['key' => 'ALPHA', 'rowid' => 22, 'payload' => ['option_name' => 'ALPHA']],
    ['key' => 'beta', 'rowid' => 23, 'payload' => ['option_name' => 'beta']],
    ['key' => 'Beta ', 'rowid' => 24, 'payload' => ['option_name' => 'Beta ']],
    ['key' => 'delta', 'rowid' => 25, 'payload' => ['option_name' => 'delta']],
    ['key' => 'éclair', 'rowid' => 26, 'payload' => ['option_name' => 'éclair']],
    ['key' => new SQLiteBlobValue('beta'), 'rowid' => 27, 'payload' => ['option_name' => 'blob_beta']],
];

$nocaseCursor = static fn (): SQLiteAffinityRangeCurrentSourceCursor => new SQLiteAffinityRangeCurrentSourceCursor($nameEntries, 'alpha', 'delta', 'TEXT', 'NOCASE');
$rtrimCursor = static fn (): SQLiteAffinityRangeCurrentSourceCursor => new SQLiteAffinityRangeCurrentSourceCursor($nameEntries, 'alpha', 'delta', 'TEXT', 'RTRIM');

$nocaseCases = [
    'nocase starts at Alpha' => [0, 'currentRowid', 20],
    'nocase alpha lower comparison equal' => [0, 'currentLowerComparison', 0],
    'nocase keeps padded alpha inside range' => [1, 'currentInRange', true],
    'nocase upper alpha peer stays below delta' => [2, 'currentUpperComparison', -1],
    'nocase beta row stays in range' => [3, 'currentRowid', 23],
    'nocase padded Beta row stays in range' => [4, 'currentInRange', true],
    'nocase delta is exclusive' => [5, 'currentInRange', false],
    'nocase delta upper comparison equal' => [5, 'currentUpperComparison', 0],
    'nocase unicode after delta is outside' => [6, 'currentInRange', false],
    'nocase blob sorts after text and is outside text range' => [7, 'currentStorage', 'blob'],
];

foreach ($nocaseCases as $name => [$advance, $path, $expected]) {
    $tests['affinity collation range current source next83 nocase plan ' . $name] = static function (TestRunner $t) use ($nocaseCursor, $advance, $path, $expected): void {
        $cursor = $nocaseCursor();
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->currentNextPlan()[$path]);
    };
}

$tests['affinity collation range current source next83 nocase matched rows include case peers'] = static function (TestRunner $t) use ($nocaseCursor): void {
    $t->same([20, 22, 21, 23, 24], array_column($nocaseCursor()->matchedRows(), 'rowid'));
};

$tests['affinity collation range current source next83 rtrim does not fold uppercase alpha into lower range'] = static function (TestRunner $t) use ($rtrimCursor): void {
    $t->same([21, 23], array_column($rtrimCursor()->matchedRows(), 'rowid'));
};

$tests['affinity collation range current source next83 rtrim trims padded alpha at lower boundary'] = static function (TestRunner $t) use ($rtrimCursor): void {
    $plan = $rtrimCursor()->currentNextPlan();
    $t->same(21, $plan['currentRowid']);
    $t->same(0, $plan['currentLowerComparison']);
};

$wpRows = [
    ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => '10', 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'home', 'option_value' => '02', 'autoload' => 'yes'],
    ['option_id' => 103, 'option_name' => 'blog_public', 'option_value' => '1', 'autoload' => 'yes'],
    ['option_id' => 104, 'option_name' => 'cron', 'option_value' => 'not-a-number', 'autoload' => 'yes'],
    ['option_id' => 105, 'option_name' => 'plugin_alpha', 'option_value' => '9', 'autoload' => 'no'],
    ['option_id' => 106, 'option_name' => 'PLUGIN_ALPHA', 'option_value' => '09', 'autoload' => 'yes'],
    ['option_id' => 107, 'option_name' => 'theme_mods_twenty', 'option_value' => '10e0', 'autoload' => 'yes'],
];

$tests['affinity collation range current source next83 application numeric autoload range'] = static function (TestRunner $t) use ($wpRows): void {
    $rows = SQLiteAffinityRangeCurrentSourceCursor::optionRowRange($wpRows, 'option_value', 2, 10, 'NUMERIC', 'BINARY', ['autoload' => 'yes']);
    $t->same([102, 106], array_column($rows, 'rowid'));
};

$tests['affinity collation range current source next83 application nocase option name range'] = static function (TestRunner $t) use ($wpRows): void {
    $rows = SQLiteAffinityRangeCurrentSourceCursor::optionRowRange($wpRows, 'option_name', 'plugin_', 'theme_', 'TEXT', 'NOCASE');
    $t->same([105, 106, 101], array_column($rows, 'rowid'));
};

$tests['affinity collation range current source next83 application filter excludes non autoload peer'] = static function (TestRunner $t) use ($wpRows): void {
    $rows = SQLiteAffinityRangeCurrentSourceCursor::optionRowRange($wpRows, 'option_name', 'plugin_', 'theme_', 'TEXT', 'NOCASE', ['autoload' => 'yes']);
    $t->same([106, 101], array_column($rows, 'rowid'));
};

$tests['affinity collation range current source next83 eof after upper range keeps next null'] = static function (TestRunner $t) use ($numericCursor): void {
    $cursor = $numericCursor();
    for ($i = 0; $i < 20; $i++) {
        $cursor->next();
    }
    $plan = $cursor->currentNextPlan();
    $t->same(true, $plan['eof']);
    $t->same(null, $plan['nextRowid']);
};

$tests['affinity collation range current source next83 rejects unsupported affinity'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteAffinityRangeCurrentSourceCursor($entries, 1, 2, 'VECTOR', 'BINARY'));
};

$tests['affinity collation range current source next83 rejects unsupported collation'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteAffinityRangeCurrentSourceCursor($entries, 1, 2, 'NUMERIC', 'UNICODE'));
};

$tests['affinity collation range current source next83 rejects missing application column'] = static function (TestRunner $t) use ($wpRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAffinityRangeCurrentSourceCursor::optionRowRange($wpRows, 'missing_column', 1, 2));
};

$tests['affinity collation range current source next83 rejects non scalar row key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteAffinityRangeCurrentSourceCursor([['key' => [], 'rowid' => 1]], 1, 2));
};

return $tests;
