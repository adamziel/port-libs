<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    return [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'A', 'bytes' => 8, 'state' => 'live'],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'B', 'bytes' => 5, 'state' => 'live'],
            ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'key_value' => 'C', 'bytes' => 13, 'state' => 'stale'],
            ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'D', 'bytes' => 21, 'state' => 'stale'],
            ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'E', 'bytes' => 3, 'state' => 'queued'],
            ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'key_value' => 'F', 'bytes' => 34, 'state' => 'queued'],
            ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'G', 'bytes' => 2, 'state' => null],
            ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'H', 'bytes' => 55, 'state' => 'stale'],
        ],
        'app_setting_targets' => [
            ['target_id' => 1, 'tenant_id' => 1, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 40],
            ['target_id' => 2, 'tenant_id' => 1, 'key_name' => 'gamma', 'action' => 'refresh', 'priority' => 20],
            ['target_id' => 3, 'tenant_id' => 2, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 30],
            ['target_id' => 4, 'tenant_id' => 2, 'key_name' => 'gamma', 'action' => 'cleanup', 'priority' => 10],
            ['target_id' => 5, 'tenant_id' => 3, 'key_name' => 'beta', 'action' => 'cleanup', 'priority' => 50],
        ],
    ];
};

$execute = static fn (string $sql): array => SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);

$tests = [];

$tests['rowvalue update delete limit dynamic parity cites upstream limit and update-delete sources'] = static function (TestRunner $t): void {
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('limit-1.2.5', 'limit-1.2.5 negative offset is treated as zero');
    $t->contains('e_update.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test');
    $t->contains('e_delete.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test');
};

$updateNegativeOffset = "UPDATE app_settings SET (state, key_value, bytes) = ('refreshed', key_value || ':r', bytes + 7) WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT -1 OFFSET -3) RETURNING setting_id, tenant_id, key_name, state, key_value, bytes, (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT -1 OFFSET -3) AS selected_tuple ORDER BY bytes DESC LIMIT 2 OFFSET -4";
$deleteCommaNegativeOffset = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'cleanup' ORDER BY priority ASC LIMIT -2, -1) RETURNING setting_id, tenant_id, key_name, (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'cleanup' ORDER BY priority ASC LIMIT -2, -1) AS cleanup_tuple ORDER BY setting_id LIMIT -5, 1";
$updateCommaWindow = "UPDATE app_settings SET state = 'windowed', bytes = bytes + 1 WHERE load_policy = 'lazy' RETURNING setting_id, state, bytes ORDER BY bytes ASC LIMIT -2, 3";
$deleteOffsetWindow = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id, key_name, bytes ORDER BY bytes DESC LIMIT 2 OFFSET -1";
$updateExpressionLimit = "UPDATE app_settings SET state = 'expr', bytes = bytes + 2 WHERE load_policy = 'lazy' RETURNING setting_id, state, bytes ORDER BY bytes ASC LIMIT 5-1 OFFSET 2+1";
$deleteExpressionCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT 1+1, 16/4";
$deleteQuotedLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT '4' OFFSET '1.0'";
$updateNegativeProductLimit = "UPDATE app_settings SET state = 'all_lazy' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT 5*-1";
$updateSubqueryExpressionLimit = "UPDATE app_settings SET state = 'expr_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT 5-1 OFFSET 1+1) RETURNING setting_id, state ORDER BY setting_id LIMIT -1";
$updateSubqueryNegativeOffsetWindow = "UPDATE app_settings SET state = 'subquery_negative_offset' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 2 OFFSET -2) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteSubqueryCommaNegativeOffsetWindow = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT -4, 2) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateUnaryPlusLimit = "UPDATE app_settings SET state = 'unary_plus' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT +2 OFFSET +1";
$deleteUnaryPlusCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT +1, +2";

$cases = [
    'parse update negative offset retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateNegativeOffset)['offset'], -4],
    'parse update negative limit retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateNegativeOffset)['limit'], 2],
    'parse delete comma negative offset retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteCommaNegativeOffset)['offset'], -5],
    'parse delete comma negative count retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteCommaNegativeOffset)['limit'], 1],
    'update subquery negative offset selects all refresh tuples' => [static fn (): mixed => $execute($updateNegativeOffset)['plan']->toArray()['qualified_rows'], 3],
    'update outer negative offset clamps to first ordered rows' => [static fn (): mixed => $execute($updateNegativeOffset)['plan']->selectedIds, [3, 2]],
    'update returning follows source mutation order after ordered selection' => [static fn (): mixed => array_column($execute($updateNegativeOffset)['returning'], 'setting_id'), [2, 3]],
    'update returning tuple flags true' => [static fn (): mixed => array_column($execute($updateNegativeOffset)['returning'], 'selected_tuple'), [1, 1]],
    'update returning values are refreshed' => [static fn (): mixed => array_column($execute($updateNegativeOffset)['returning'], 'key_value'), ['B:r', 'C:r']],
    'update result mutates selected rows only' => [static fn (): mixed => array_column($execute($updateNegativeOffset)['tables']['app_settings'], 'state', 'setting_id'), [1 => 'live', 2 => 'refreshed', 3 => 'refreshed', 4 => 'stale', 5 => 'queued', 6 => 'queued', 7 => null, 8 => 'stale']],
    'update rowid column summary generic setting id' => [static fn (): mixed => $execute($updateNegativeOffset)['plan']->toArray()['rowid_column'], 'setting_id'],
    'delete subquery comma negative offset selects cleanup tuples' => [static fn (): mixed => $execute($deleteCommaNegativeOffset)['plan']->toArray()['qualified_rows'], 2],
    'delete outer comma negative offset clamps to first row' => [static fn (): mixed => $execute($deleteCommaNegativeOffset)['plan']->selectedIds, [6]],
    'delete returning tuple flag true' => [static fn (): mixed => $execute($deleteCommaNegativeOffset)['returning'][0]['cleanup_tuple'], 1],
    'delete result removes selected cleanup row only' => [static fn (): mixed => array_column($execute($deleteCommaNegativeOffset)['tables']['app_settings'], 'setting_id'), [1, 2, 3, 4, 5, 7, 8]],
    'update comma negative offset clamps to first lazy rows' => [static fn (): mixed => $execute($updateCommaWindow)['plan']->selectedIds, [5, 2, 3]],
    'update comma negative offset returning source order' => [static fn (): mixed => array_column($execute($updateCommaWindow)['returning'], 'setting_id'), [2, 3, 5]],
    'delete offset negative clamps to first descending rows' => [static fn (): mixed => $execute($deleteOffsetWindow)['plan']->selectedIds, [8, 6]],
    'delete offset negative returning source order' => [static fn (): mixed => array_column($execute($deleteOffsetWindow)['returning'], 'setting_id'), [6, 8]],
    'parse update expression limit subtraction' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateExpressionLimit)['limit'], 4],
    'parse update expression offset addition' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateExpressionLimit)['offset'], 3],
    'parse delete expression comma offset addition' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteExpressionCommaLimit)['offset'], 2],
    'parse delete expression comma count division' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteExpressionCommaLimit)['limit'], 4],
    'parse delete quoted integer limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteQuotedLimit)['limit'], 4],
    'parse delete quoted integral offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteQuotedLimit)['offset'], 1],
    'update expression limit offset selects tail lazy rows' => [static fn (): mixed => $execute($updateExpressionLimit)['plan']->selectedIds, [6, 8]],
    'update expression limit returning source order' => [static fn (): mixed => array_column($execute($updateExpressionLimit)['returning'], 'setting_id'), [6, 8]],
    'delete expression comma limit skips first two lazy rows' => [static fn (): mixed => $execute($deleteExpressionCommaLimit)['plan']->selectedIds, [3, 6, 8]],
    'delete quoted limit offset skips first lazy row' => [static fn (): mixed => $execute($deleteQuotedLimit)['plan']->selectedIds, [2, 3, 6, 8]],
    'update negative product limit means no limit' => [static fn (): mixed => $execute($updateNegativeProductLimit)['plan']->selectedIds, [5, 2, 3, 6, 8]],
    'update row-value subquery expression limit applies before tuple match' => [static fn (): mixed => $execute($updateSubqueryExpressionLimit)['plan']->selectedIds, [2]],
    'update row-value subquery negative offset clamps before tuple match' => [static fn (): mixed => $execute($updateSubqueryNegativeOffsetWindow)['plan']->selectedIds, [3, 6]],
    'update row-value subquery negative offset returning source order' => [static fn (): mixed => array_column($execute($updateSubqueryNegativeOffsetWindow)['returning'], 'setting_id'), [3, 6]],
    'delete row-value subquery comma negative offset clamps before tuple match' => [static fn (): mixed => $execute($deleteSubqueryCommaNegativeOffsetWindow)['plan']->selectedIds, [3, 6]],
    'delete row-value subquery comma negative offset result keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteSubqueryCommaNegativeOffsetWindow)['tables']['app_settings'], 'setting_id'), [1, 2, 4, 5, 7, 8]],
    'parse update unary plus limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateUnaryPlusLimit)['limit'], 2],
    'parse update unary plus offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateUnaryPlusLimit)['offset'], 1],
    'update unary plus limit selects after positive offset' => [static fn (): mixed => $execute($updateUnaryPlusLimit)['plan']->selectedIds, [2, 3]],
    'parse delete unary plus comma offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteUnaryPlusCommaLimit)['offset'], 1],
    'parse delete unary plus comma count' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteUnaryPlusCommaLimit)['limit'], 2],
    'delete unary plus comma limit selects after positive offset' => [static fn (): mixed => $execute($deleteUnaryPlusCommaLimit)['plan']->selectedIds, [2, 3]],
    'malformed non-integral limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1.2"), InvalidArgumentException::class],
    'malformed null offset rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET NULL"), InvalidArgumentException::class],
    'malformed missing generic rowid rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($updateNegativeOffset, ['app_settings' => [['tenant_id' => 1, 'key_name' => 'alpha']]], 'setting_id'), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $offset = -($seed % 5) - 1;
    $limit = ($seed % 4) + 1;
    $sql = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$limit} OFFSET {$offset}";
    $expected = array_slice([5, 2, 3, 6, 8], 0, $limit);

    $tests[sprintf('rowvalue update delete limit dynamic parity delete negative offset seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $offset): void {
            $result = $execute($sql);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $offset = -($seed % 7) - 1;
    $limit = ($seed % 3) + 2;
    $sql = "UPDATE app_settings SET state = 'dyn', bytes = bytes + {$seed} WHERE load_policy = 'lazy' RETURNING setting_id, bytes ORDER BY bytes ASC LIMIT {$offset}, {$limit}";
    $expected = array_slice([5, 2, 3, 6, 8], 0, $limit);

    $tests[sprintf('rowvalue update delete limit dynamic parity update comma negative offset seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $offset): void {
            $result = $execute($sql);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['plan']->selectedRows, 'setting_id'));
        };
}

return $tests;
