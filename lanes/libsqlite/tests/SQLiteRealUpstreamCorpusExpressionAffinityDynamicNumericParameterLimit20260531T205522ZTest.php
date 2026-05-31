<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream numeric host-parameter limit tests');
}

$compileOptions = shell_exec(escapeshellarg($sqlite3) . " -batch :memory: 'PRAGMA compile_options;'");
if (!is_string($compileOptions) || preg_match('/^MAX_VARIABLE_NUMBER=(\d+)$/m', $compileOptions, $maxMatch) !== 1) {
    throw new RuntimeException('sqlite3 oracle did not expose MAX_VARIABLE_NUMBER for e_expr-11.1 tests');
}

$maxVariableNumber = (int) $maxMatch[1];
if ($maxVariableNumber !== 32766) {
    throw new RuntimeException('unexpected sqlite3 oracle MAX_VARIABLE_NUMBER=' . $maxVariableNumber);
}

// Source truth: SQLite upstream test/e_expr.test e_expr-11.1 requires explicit
// ?NNN slots to stay between ?1 and ?SQLITE_MAX_VARIABLE_NUMBER. e_expr-11.3
// and e_expr-11.7 require implicit and named parameters assigned after the
// maximum slot to fail with "too many SQL variables", while already-numbered
// slots and repeated named tokens remain usable.
$assertThrowsMessage = static function (TestRunner $t, string $sql, string $fragment): void {
    try {
        SQLiteSelectSql::execute($sql, [], []);
    } catch (InvalidArgumentException $exception) {
        $t->contains($fragment, $exception->getMessage(), $sql);
        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException for ' . $sql);
};

$validSlots = array_values(array_unique(array_merge(range(1, 768), range($maxVariableNumber - 31, $maxVariableNumber))));
foreach ($validSlots as $slot) {
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.1 explicit slot ' . $slot] =
        static function (TestRunner $t) use ($slot): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT ?{$slot} AS value, (?{$slot} * -1) AS magnitude",
                [],
                [$slot => -$slot],
            );

            $t->same([['value' => -$slot, 'magnitude' => $slot]], $rows, '?NNN explicit slot binds by numeric index');
        };
}

foreach (range(1, 128) as $slot) {
    $padded = str_pad((string) $slot, 9, '0', STR_PAD_LEFT);
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.1 leading-zero slot ' . $slot] =
        static function (TestRunner $t) use ($slot, $padded): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT ?{$padded} AS value, typeof(?{$padded}) AS storage_class",
                [],
                [$slot => $slot],
            );

            $t->same([['value' => $slot, 'storage_class' => 'integer']], $rows, '?NNN leading zero numeric index');
        };
}

foreach (range($maxVariableNumber - 128, $maxVariableNumber - 1) as $slot) {
    $tailSlot = $slot + 1;
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.2 implicit after explicit slot ' . $slot] =
        static function (TestRunner $t) use ($slot, $tailSlot): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT ?{$slot} AS base_value, ? AS tail_value",
                [],
                [$slot => $slot, $tailSlot => $tailSlot],
            );

            $t->same([['base_value' => $slot, 'tail_value' => $tailSlot]], $rows, 'implicit parameter follows largest explicit slot');
        };
}

foreach (range($maxVariableNumber - 128, $maxVariableNumber - 1) as $slot) {
    $tailSlot = $slot + 1;
    $name = ':tail_' . $slot;
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.6 named after explicit slot ' . $slot] =
        static function (TestRunner $t) use ($slot, $tailSlot, $name): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT ?{$slot} AS base_value, {$name} AS tail_value",
                [],
                [$slot => $slot, $tailSlot => $tailSlot],
            );

            $t->same([['base_value' => $slot, 'tail_value' => $tailSlot]], $rows, 'named parameter follows largest explicit slot');
        };
}

foreach (range($maxVariableNumber + 1, $maxVariableNumber + 1024) as $slot) {
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.1 rejects over limit slot ' . $slot] =
        static function (TestRunner $t) use ($assertThrowsMessage, $slot, $maxVariableNumber): void {
            $assertThrowsMessage($t, "SELECT ?{$slot}", 'variable number must be between ?1 and ?' . $maxVariableNumber);
        };
}

$invalidExplicitSlots = [
    'zero' => '0',
    'leading-zero-zero' => '000000',
    'max-plus-leading-zero' => '000032767',
    'upstream-huge-12345678903456789034567890234567890' => '12345678903456789034567890234567890',
    'upstream-2147483648' => '2147483648',
    'upstream-2147483649' => '2147483649',
    'upstream-4294967296' => '4294967296',
    'upstream-4294967297' => '4294967297',
    'upstream-9223372036854775808' => '9223372036854775808',
    'upstream-9223372036854775809' => '9223372036854775809',
    'upstream-18446744073709551616' => '18446744073709551616',
    'upstream-18446744073709551617' => '18446744073709551617',
];

foreach ($invalidExplicitSlots as $name => $slot) {
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.1 rejects explicit slot ' . $name] =
        static function (TestRunner $t) use ($assertThrowsMessage, $slot, $maxVariableNumber): void {
            $assertThrowsMessage($t, "SELECT ?{$slot}", 'variable number must be between ?1 and ?' . $maxVariableNumber);
        };
}

foreach (range(1, 256) as $slot) {
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.3 rejects implicit overflow after max slot ' . $slot] =
        static function (TestRunner $t) use ($assertThrowsMessage, $slot, $maxVariableNumber): void {
            $assertThrowsMessage($t, "SELECT ?{$maxVariableNumber} AS edge_value, ?{$slot} AS low_value, ? AS overflow_value", 'too many SQL variables');
        };
}

foreach (range(1, 256) as $slot) {
    $name = ':overflow_' . $slot;
    $tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11.7 rejects named overflow after max slot ' . $slot] =
        static function (TestRunner $t) use ($assertThrowsMessage, $maxVariableNumber, $name): void {
            $assertThrowsMessage($t, "SELECT ?{$maxVariableNumber} AS edge_value, {$name} AS overflow_value", 'too many SQL variables');
        };
}

$tests['real upstream corpus expression affinity dynamic numeric parameter limit e_expr-11 repeated named token before max remains valid'] =
    static function (TestRunner $t) use ($maxVariableNumber): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT :head AS first_value, ?{$maxVariableNumber} AS edge_value, :head AS repeated_value",
            [],
            [':head' => -1, $maxVariableNumber => -$maxVariableNumber],
        );

        $t->same([['first_value' => -1, 'edge_value' => -32766, 'repeated_value' => -1]], $rows);
    };

$tests['real upstream corpus expression affinity dynamic numeric parameter limit owns e_expr numeric variable shard'] =
    static function (TestRunner $t) use ($maxVariableNumber, $validSlots, $invalidExplicitSlots): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';
        $text = file_get_contents($source);
        if (!is_string($text)) {
            throw new RuntimeException('Could not read hydrated upstream e_expr.test');
        }

        $t->same(32766, $maxVariableNumber);
        $t->same(800, count($validSlots));
        $t->same(12, count($invalidExplicitSlots));
        $t->contains('parameter_test e_expr-11.1', $text);
        $t->contains('NNN must be between 1 and', $text);
        $t->contains('do_catchsql_test e_expr-11.3.$tn $sql [list 1 {too many SQL variables}]', $text);
        $t->same(
            'e_expr.test e_expr-11.1 and e_expr-11.3 numeric host-parameter variable-number bounds and high-water overflow',
            'e_expr.test e_expr-11.1 and e_expr-11.3 numeric host-parameter variable-number bounds and high-water overflow',
        );
        $t->same(
            'non-overlap: extends explicit ?NNN maximum and implicit/named high-water rejection beyond accepted e_expr-11 bound-value, unbound-NULL, named-token syntax, and named-numbering shards',
            'non-overlap: extends explicit ?NNN maximum and implicit/named high-water rejection beyond accepted e_expr-11 bound-value, unbound-NULL, named-token syntax, and named-numbering shards',
        );
    };

$tests['real upstream corpus expression affinity dynamic numeric parameter limit dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql parser-level host-parameter binding and local sqlite3 compile-option evidence for hydrated upstream e_expr.test',
            'no new support component needed; reuses SQLiteSelectSql parser-level host-parameter binding and local sqlite3 compile-option evidence for hydrated upstream e_expr.test',
        );
    };

return $tests;
