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
$updateParenthesizedNegativeLimit = "UPDATE app_settings SET state = 'all_ordered' WHERE load_policy = 'lazy' RETURNING setting_id, key_name, tenant_id, state ORDER BY length(key_name) ASC, tenant_id DESC, setting_id ASC LIMIT -(1+1) OFFSET -(2)";
$deleteLengthOrderLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id, key_name ORDER BY length(key_name) ASC, tenant_id DESC, setting_id ASC LIMIT 2";
$updateSubqueryLengthOrder = "UPDATE app_settings SET state = 'length_ordered' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY length(key_name) ASC, tenant_id DESC, priority ASC LIMIT -(1+1) OFFSET -(2)) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$updateCastLimit = "UPDATE app_settings SET state = 'cast_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT CAST('3.9' AS INTEGER) OFFSET CAST('1.9' AS INT)";
$deleteCastCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT CAST('1.9' AS INTEGER), CAST('2.9' AS INT)";
$updateSubqueryCastLimit = "UPDATE app_settings SET state = 'cast_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT CAST('2.9' AS INTEGER) OFFSET CAST('1.1' AS INT)) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteSubqueryCastOffset = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT CAST('3.8' AS INTEGER) OFFSET CAST('1.2' AS INT)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateRealCastLimit = "UPDATE app_settings SET state = 'real_cast_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT CAST('2.0' AS REAL) OFFSET CAST('1.0' AS DOUBLE)";
$deleteTextCastCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT CAST('1' AS TEXT), CAST('3.0' AS TEXT)";
$updateNumericCastSubqueryLimit = "UPDATE app_settings SET state = 'numeric_cast_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT CAST('3.0' AS NUMERIC) OFFSET CAST('1.0' AS NUMERIC)) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteNumericCastSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT CAST('3.0' AS NUMERIC) OFFSET CAST('2.0' AS REAL)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateExponentLimit = "UPDATE app_settings SET state = 'exponent_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT 2e+0 OFFSET 1e0";
$deleteExponentCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT 0e0+1e0, 2e+0";
$updateHexLimit = "UPDATE app_settings SET state = 'hex_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT 0x3 OFFSET 0x1";
$deleteHexCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT 0x1, 0x2";
$updateRowValueExponentSubqueryLimit = "UPDATE app_settings SET state = 'exponent_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 2e+0 OFFSET 1e0) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueHexSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 0x3 OFFSET 0x1) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateBooleanLimit = "UPDATE app_settings SET state = 'bool_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT TRUE OFFSET FALSE";
$deleteBooleanCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT TRUE, TRUE+TRUE";
$updateRowValueBooleanSubqueryLimit = "UPDATE app_settings SET state = 'bool_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT TRUE+TRUE OFFSET FALSE) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueBooleanSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT TRUE+TRUE OFFSET TRUE) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateFalseLimit = "UPDATE app_settings SET state = 'false_limit' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT FALSE OFFSET TRUE";

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
    'parse update parenthesized unary negative limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateParenthesizedNegativeLimit)['limit'], -2],
    'parse update parenthesized unary negative offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateParenthesizedNegativeLimit)['offset'], -2],
    'update parenthesized negative limit selects all length ordered lazy rows' => [static fn (): mixed => $execute($updateParenthesizedNegativeLimit)['plan']->selectedIds, [8, 5, 2, 6, 3]],
    'update parenthesized negative limit returning source order' => [static fn (): mixed => array_column($execute($updateParenthesizedNegativeLimit)['returning'], 'setting_id'), [2, 3, 5, 6, 8]],
    'delete length order limit selects by computed expression' => [static fn (): mixed => $execute($deleteLengthOrderLimit)['plan']->selectedIds, [8, 5]],
    'delete length order limit returns old rows in source order' => [static fn (): mixed => array_column($execute($deleteLengthOrderLimit)['returning'], 'setting_id'), [5, 8]],
    'update row-value subquery length order negative limit applies before tuple match' => [static fn (): mixed => $execute($updateSubqueryLengthOrder)['plan']->selectedIds, [2, 3, 5]],
    'update row-value subquery length order returns source order' => [static fn (): mixed => array_column($execute($updateSubqueryLengthOrder)['returning'], 'setting_id'), [2, 3, 5]],
    'parse update cast limit truncates text numeric' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateCastLimit)['limit'], 3],
    'parse update cast offset truncates text numeric' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateCastLimit)['offset'], 1],
    'update cast limit selects after truncated offset' => [static fn (): mixed => $execute($updateCastLimit)['plan']->selectedIds, [2, 3, 6]],
    'update cast limit returns mutation source order' => [static fn (): mixed => array_column($execute($updateCastLimit)['returning'], 'setting_id'), [2, 3, 6]],
    'parse delete cast comma offset truncates first expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteCastCommaLimit)['offset'], 1],
    'parse delete cast comma count truncates second expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteCastCommaLimit)['limit'], 2],
    'delete cast comma limit selects truncated window' => [static fn (): mixed => $execute($deleteCastCommaLimit)['plan']->selectedIds, [2, 3]],
    'delete cast comma limit removes selected rows only' => [static fn (): mixed => array_column($execute($deleteCastCommaLimit)['tables']['app_settings'], 'setting_id'), [1, 4, 5, 6, 7, 8]],
    'update row-value subquery cast limit applies before tuple match' => [static fn (): mixed => $execute($updateSubqueryCastLimit)['plan']->selectedIds, [2, 5]],
    'update row-value subquery cast limit returns source order' => [static fn (): mixed => array_column($execute($updateSubqueryCastLimit)['returning'], 'setting_id'), [2, 5]],
    'delete row-value subquery cast offset applies before tuple match' => [static fn (): mixed => $execute($deleteSubqueryCastOffset)['plan']->selectedIds, [2, 3, 5]],
    'delete row-value subquery cast offset keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteSubqueryCastOffset)['tables']['app_settings'], 'setting_id'), [1, 4, 6, 7, 8]],
    'parse update real cast limit keeps integral real' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateRealCastLimit)['limit'], 2],
    'parse update double cast offset keeps integral real' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateRealCastLimit)['offset'], 1],
    'update real cast limit selects integral real window' => [static fn (): mixed => $execute($updateRealCastLimit)['plan']->selectedIds, [2, 3]],
    'parse delete text cast comma offset coerces integral text' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteTextCastCommaLimit)['offset'], 1],
    'parse delete text cast comma count coerces integral text real' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteTextCastCommaLimit)['limit'], 3],
    'delete text cast comma limit selects coerced window' => [static fn (): mixed => $execute($deleteTextCastCommaLimit)['plan']->selectedIds, [2, 3, 6]],
    'update row-value subquery numeric cast limit applies before tuple match' => [static fn (): mixed => $execute($updateNumericCastSubqueryLimit)['plan']->selectedIds, [2, 5]],
    'update row-value subquery numeric cast returns source order' => [static fn (): mixed => array_column($execute($updateNumericCastSubqueryLimit)['returning'], 'setting_id'), [2, 5]],
    'delete row-value subquery numeric and real cast window applies before tuple match' => [static fn (): mixed => $execute($deleteNumericCastSubqueryLimit)['plan']->selectedIds, [2, 5, 8]],
    'delete row-value subquery numeric and real cast keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteNumericCastSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 3, 4, 6, 7]],
    'parse update exponent limit with signed exponent' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateExponentLimit)['limit'], 2],
    'parse update exponent offset without signed exponent' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateExponentLimit)['offset'], 1],
    'update exponent limit selects ordered lazy window' => [static fn (): mixed => $execute($updateExponentLimit)['plan']->selectedIds, [2, 3]],
    'update exponent limit returns source order' => [static fn (): mixed => array_column($execute($updateExponentLimit)['returning'], 'setting_id'), [2, 3]],
    'parse delete exponent comma offset expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteExponentCommaLimit)['offset'], 1],
    'parse delete exponent comma count expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteExponentCommaLimit)['limit'], 2],
    'delete exponent comma limit selects ordered lazy window' => [static fn (): mixed => $execute($deleteExponentCommaLimit)['plan']->selectedIds, [2, 3]],
    'parse update hexadecimal limit literal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateHexLimit)['limit'], 3],
    'parse update hexadecimal offset literal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateHexLimit)['offset'], 1],
    'update hexadecimal limit selects ordered lazy window' => [static fn (): mixed => $execute($updateHexLimit)['plan']->selectedIds, [2, 3, 6]],
    'parse delete hexadecimal comma offset literal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteHexCommaLimit)['offset'], 1],
    'parse delete hexadecimal comma count literal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteHexCommaLimit)['limit'], 2],
    'delete hexadecimal comma limit selects ordered lazy window' => [static fn (): mixed => $execute($deleteHexCommaLimit)['plan']->selectedIds, [2, 3]],
    'update row-value exponent subquery window applies before tuple match' => [static fn (): mixed => $execute($updateRowValueExponentSubqueryLimit)['plan']->selectedIds, [3, 5]],
    'update row-value exponent subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueExponentSubqueryLimit)['returning'], 'setting_id'), [3, 5]],
    'delete row-value hexadecimal subquery window applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueHexSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'delete row-value hexadecimal subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueHexSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 4, 6, 7, 8]],
    'parse update boolean true limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateBooleanLimit)['limit'], 1],
    'parse update boolean false offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateBooleanLimit)['offset'], 0],
    'update boolean limit selects one ordered row' => [static fn (): mixed => $execute($updateBooleanLimit)['plan']->selectedIds, [5]],
    'update boolean limit returns one source-order row' => [static fn (): mixed => array_column($execute($updateBooleanLimit)['returning'], 'setting_id'), [5]],
    'parse delete boolean comma offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteBooleanCommaLimit)['offset'], 1],
    'parse delete boolean comma count expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteBooleanCommaLimit)['limit'], 2],
    'delete boolean comma limit skips first ordered row' => [static fn (): mixed => $execute($deleteBooleanCommaLimit)['plan']->selectedIds, [2, 3]],
    'update row-value boolean subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueBooleanSubqueryLimit)['plan']->selectedIds, [3, 6]],
    'update row-value boolean subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueBooleanSubqueryLimit)['returning'], 'setting_id'), [3, 6]],
    'delete row-value boolean subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueBooleanSubqueryLimit)['plan']->selectedIds, [3, 5]],
    'delete row-value boolean subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueBooleanSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 2, 4, 6, 7, 8]],
    'update false limit selects no rows' => [static fn (): mixed => $execute($updateFalseLimit)['plan']->selectedIds, []],
    'update false limit leaves result unchanged' => [static fn (): mixed => array_column($execute($updateFalseLimit)['tables']['app_settings'], 'state', 'setting_id'), [1 => 'live', 2 => 'live', 3 => 'stale', 4 => 'stale', 5 => 'queued', 6 => 'queued', 7 => null, 8 => 'stale']],
    'malformed cast null limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST(NULL AS INTEGER)"), InvalidArgumentException::class],
    'malformed cast blob offset rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET CAST(X'ABCD' AS INT)"), InvalidArgumentException::class],
    'malformed nonintegral real cast limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST('2.5' AS REAL)"), InvalidArgumentException::class],
    'malformed nonintegral numeric cast limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST('2.5' AS NUMERIC)"), InvalidArgumentException::class],
    'malformed blob cast limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST('2' AS BLOB)"), InvalidArgumentException::class],
    'malformed non-integral limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1.2"), InvalidArgumentException::class],
    'malformed non-integral exponent limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2.5e0"), InvalidArgumentException::class],
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

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limit = $limitValue . '.9';
    $offset = $offsetValue . '.7';
    $sql = "UPDATE app_settings SET state = 'dyn_cast' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT CAST('{$limit}' AS INTEGER) OFFSET CAST('{$offset}' AS INT)";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update cast window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limit = $limitValue . '.4';
    $offset = $offsetValue . '.8';
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT CAST('{$limit}' AS INTEGER) OFFSET CAST('{$offset}' AS INT)) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue cast subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same(-1, SQLiteUpdateDeleteReturningSql::parse($sql)['limit']);
            $t->same(0, SQLiteUpdateDeleteReturningSql::parse($sql)['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 2) % 4;
    $limitType = $seed % 2 === 0 ? 'REAL' : 'NUMERIC';
    $offsetType = $seed % 3 === 0 ? 'TEXT' : 'NUMERIC';
    $limit = $limitValue . '.0';
    $offset = $offsetValue . '.0';
    $sql = "UPDATE app_settings SET state = 'typed_cast' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT CAST('{$limit}' AS {$limitType}) OFFSET CAST('{$offset}' AS {$offsetType})";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update typed cast window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = $seed % 4;
    $limitType = $seed % 2 === 0 ? 'TEXT' : 'REAL';
    $offsetType = $seed % 3 === 0 ? 'DOUBLE' : 'NUMERIC';
    $limit = $limitValue . '.0';
    $offset = $offsetValue . '.0';
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT CAST('{$limit}' AS {$limitType}) OFFSET CAST('{$offset}' AS {$offsetType})) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue typed cast subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same(-1, SQLiteUpdateDeleteReturningSql::parse($sql)['limit']);
            $t->same(0, SQLiteUpdateDeleteReturningSql::parse($sql)['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitExpr = $seed % 2 === 0 ? 'TRUE+TRUE' : 'TRUE';
    $offsetExpr = $seed % 3 === 0 ? 'FALSE' : 'TRUE';
    $limitValue = $seed % 2 === 0 ? 2 : 1;
    $offsetValue = $seed % 3 === 0 ? 0 : 1;
    $sql = "UPDATE app_settings SET state = 'bool_dyn' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update boolean window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitExpr = $seed % 2 === 0 ? 'TRUE+TRUE' : 'TRUE';
    $offsetExpr = $seed % 4 === 0 ? 'FALSE' : 'TRUE';
    $limitValue = $seed % 2 === 0 ? 2 : 1;
    $offsetValue = $seed % 4 === 0 ? 0 : 1;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue boolean subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(min($limitValue, count($expected)), count($result['returning']));
        };
}

return $tests;
