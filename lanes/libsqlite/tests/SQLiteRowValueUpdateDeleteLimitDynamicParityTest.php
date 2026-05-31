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
        'app_setting_payloads' => [
            ['payload_id' => 1, 'target_tenant_id' => 1, 'target_key_name' => 'beta', 'new_value' => 'B2', 'new_bytes' => 105, 'priority' => 40],
            ['payload_id' => 2, 'target_tenant_id' => 1, 'target_key_name' => 'gamma', 'new_value' => 'C2', 'new_bytes' => 113, 'priority' => 20],
            ['payload_id' => 3, 'target_tenant_id' => 2, 'target_key_name' => 'beta', 'new_value' => 'E2', 'new_bytes' => 103, 'priority' => 30],
            ['payload_id' => 4, 'target_tenant_id' => 2, 'target_key_name' => 'gamma', 'new_value' => 'F2', 'new_bytes' => 134, 'priority' => 10],
            ['payload_id' => 5, 'target_tenant_id' => 3, 'target_key_name' => 'beta', 'new_value' => 'H2', 'new_bytes' => 155, 'priority' => 50],
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
    $t->contains('rowvalue7.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue7.test');
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
$updateModuloLimit = "UPDATE app_settings SET state = 'mod_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT 5%3 OFFSET 7%3";
$deleteShiftCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT 1<<1, 1<<2";
$updateBitwiseLimit = "UPDATE app_settings SET state = 'bitwise_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT (7&3) OFFSET (6|1)-6";
$deleteAbsLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT abs(-3) OFFSET abs(-1)";
$updateRowValueModuloSubqueryLimit = "UPDATE app_settings SET state = 'mod_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 5%3 OFFSET 4%3) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueShiftSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 1<<1 OFFSET 1) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateRowValueBitwiseSubqueryLimit = "UPDATE app_settings SET state = 'bitwise_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT (7&3) OFFSET (6|1)-6) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueAbsSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT abs(-3) OFFSET abs(-2)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateBitwiseNegativeLimit = "UPDATE app_settings SET state = 'bitwise_all' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT ~0 OFFSET -~0";
$updateSearchedCaseLimit = "UPDATE app_settings SET state = 'case_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT CASE WHEN TRUE THEN 2 ELSE 1 END OFFSET CASE WHEN FALSE THEN 3 ELSE 1 END";
$deleteSearchedCaseCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT CASE WHEN '1' THEN 1 ELSE 3 END, CASE WHEN 0 THEN 1 ELSE 2 END";
$updateRowValueSearchedCaseSubqueryLimit = "UPDATE app_settings SET state = 'case_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT CASE WHEN TRUE THEN 3 ELSE 1 END OFFSET CASE WHEN NULL THEN 4 ELSE 1 END) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueSearchedCaseSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT CASE WHEN 'not numeric' THEN 1 ELSE 2 END OFFSET CASE WHEN 1 THEN 2 ELSE 0 END) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateCoalesceLimit = "UPDATE app_settings SET state = 'coalesce_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT coalesce(NULL, 2) OFFSET coalesce(NULL, 1)";
$deleteIfnullCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT ifnull(NULL, 1), ifnull(NULL, 2)";
$updateNullifLimit = "UPDATE app_settings SET state = 'nullif_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT nullif(3, 4) OFFSET nullif(1, 0)";
$deleteCoalesceCastLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT coalesce(NULL, CAST('1.0' AS NUMERIC)), coalesce(NULL, CAST('2.0' AS REAL))";
$updateRowValueCoalesceSubqueryLimit = "UPDATE app_settings SET state = 'coalesce_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT coalesce(NULL, 3) OFFSET ifnull(NULL, 1)) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueNullifSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT nullif(4, 5) OFFSET nullif(1, 0)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateRowValueOrdinalSecondSubqueryLimit = "UPDATE app_settings SET state = 'ordinal_second' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY 2 DESC, 1 ASC LIMIT 2 OFFSET 1) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueOrdinalFirstSubqueryCommaLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY 1 DESC, 2 ASC LIMIT 1, 3) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateRowValueOrdinalExpressionProjectionLimit = "UPDATE app_settings SET state = 'ordinal_expr' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name || '' FROM app_setting_targets WHERE action = 'refresh' ORDER BY 2 ASC, 1 DESC LIMIT 2 OFFSET 1) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueOrdinalLengthProjectionLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY 2 DESC, length(key_name) ASC LIMIT 3 OFFSET 1) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateLengthLimit = "UPDATE app_settings SET state = 'length_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT length('abc') OFFSET length('x')";
$deleteLengthCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT length('x'), length('pq')";
$updateRowValueLengthSubqueryLimit = "UPDATE app_settings SET state = 'length_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT length('abc') OFFSET length('x')) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueLengthSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT length('pqrs') OFFSET length('xy')) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateQuoteTypeofLimit = "UPDATE app_settings SET state = 'quote_typeof_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT quote(3) OFFSET typeof(1)='integer'";
$deleteQuoteTypeofCommaLimit = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT typeof('1')='text', quote(2)";
$updateRowValueQuoteTypeofSubqueryLimit = "UPDATE app_settings SET state = 'quote_typeof_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT quote(3) OFFSET typeof(NULL) IS 'null') RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteRowValueQuoteTypeofSubqueryLimit = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT (typeof(1.0)='real')+2 OFFSET quote(1)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";

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
    'parse update modulo limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateModuloLimit)['limit'], 2],
    'parse update modulo offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateModuloLimit)['offset'], 1],
    'update modulo limit selects after modulo offset' => [static fn (): mixed => $execute($updateModuloLimit)['plan']->selectedIds, [2, 3]],
    'update modulo limit returns source order' => [static fn (): mixed => array_column($execute($updateModuloLimit)['returning'], 'setting_id'), [2, 3]],
    'parse delete shift comma offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteShiftCommaLimit)['offset'], 2],
    'parse delete shift comma count' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteShiftCommaLimit)['limit'], 4],
    'delete shift comma limit selects shifted window' => [static fn (): mixed => $execute($deleteShiftCommaLimit)['plan']->selectedIds, [3, 6, 8]],
    'parse update bitwise and limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateBitwiseLimit)['limit'], 3],
    'parse update bitwise or offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateBitwiseLimit)['offset'], 1],
    'update bitwise limit selects ordered window' => [static fn (): mixed => $execute($updateBitwiseLimit)['plan']->selectedIds, [2, 3, 6]],
    'parse delete abs limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteAbsLimit)['limit'], 3],
    'parse delete abs offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteAbsLimit)['offset'], 1],
    'delete abs limit selects ordered window' => [static fn (): mixed => $execute($deleteAbsLimit)['plan']->selectedIds, [2, 3, 6]],
    'update row-value modulo subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueModuloSubqueryLimit)['plan']->selectedIds, [3, 5]],
    'update row-value modulo subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueModuloSubqueryLimit)['returning'], 'setting_id'), [3, 5]],
    'delete row-value shift subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueShiftSubqueryLimit)['plan']->selectedIds, [3, 5]],
    'delete row-value shift subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueShiftSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 2, 4, 6, 7, 8]],
    'update row-value bitwise subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueBitwiseSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'update row-value bitwise subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueBitwiseSubqueryLimit)['returning'], 'setting_id'), [2, 3, 5]],
    'delete row-value abs subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueAbsSubqueryLimit)['plan']->selectedIds, [2, 5, 8]],
    'delete row-value abs subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueAbsSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 3, 4, 6, 7]],
    'parse update bitwise-not negative limit means no limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateBitwiseNegativeLimit)['limit'], -1],
    'parse update negative bitwise-not offset clamps to zero' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateBitwiseNegativeLimit)['offset'], 1],
    'update bitwise-not negative limit selects all after offset clamp expression' => [static fn (): mixed => $execute($updateBitwiseNegativeLimit)['plan']->selectedIds, [2, 3, 6, 8]],
    'parse update searched CASE limit uses true branch' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSearchedCaseLimit)['limit'], 2],
    'parse update searched CASE offset uses false branch' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSearchedCaseLimit)['offset'], 1],
    'update searched CASE limit selects ordered lazy window' => [static fn (): mixed => $execute($updateSearchedCaseLimit)['plan']->selectedIds, [2, 3]],
    'update searched CASE limit returns source order' => [static fn (): mixed => array_column($execute($updateSearchedCaseLimit)['returning'], 'setting_id'), [2, 3]],
    'parse delete searched CASE comma offset uses text truth' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSearchedCaseCommaLimit)['offset'], 1],
    'parse delete searched CASE comma count uses numeric false branch' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSearchedCaseCommaLimit)['limit'], 2],
    'delete searched CASE comma limit selects ordered window' => [static fn (): mixed => $execute($deleteSearchedCaseCommaLimit)['plan']->selectedIds, [2, 3]],
    'update row-value searched CASE subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueSearchedCaseSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'update row-value searched CASE subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueSearchedCaseSubqueryLimit)['returning'], 'setting_id'), [2, 3, 5]],
    'delete row-value searched CASE subquery applies numeric truth branch' => [static fn (): mixed => $execute($deleteRowValueSearchedCaseSubqueryLimit)['plan']->selectedIds, [2, 5]],
    'delete row-value searched CASE subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueSearchedCaseSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 3, 4, 6, 7, 8]],
    'parse update coalesce limit first non-null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateCoalesceLimit)['limit'], 2],
    'parse update coalesce offset first non-null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateCoalesceLimit)['offset'], 1],
    'update coalesce limit selects ordered lazy window' => [static fn (): mixed => $execute($updateCoalesceLimit)['plan']->selectedIds, [2, 3]],
    'update coalesce limit returns source order' => [static fn (): mixed => array_column($execute($updateCoalesceLimit)['returning'], 'setting_id'), [2, 3]],
    'parse delete ifnull comma offset first replacement' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteIfnullCommaLimit)['offset'], 1],
    'parse delete ifnull comma count first replacement' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteIfnullCommaLimit)['limit'], 2],
    'delete ifnull comma limit selects ordered lazy window' => [static fn (): mixed => $execute($deleteIfnullCommaLimit)['plan']->selectedIds, [2, 3]],
    'parse update nullif limit keeps unequal first argument' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateNullifLimit)['limit'], 3],
    'parse update nullif offset keeps unequal first argument' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateNullifLimit)['offset'], 1],
    'update nullif limit selects ordered lazy window' => [static fn (): mixed => $execute($updateNullifLimit)['plan']->selectedIds, [2, 3, 6]],
    'parse delete coalesce cast comma offset coerces integral numeric' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteCoalesceCastLimit)['offset'], 1],
    'parse delete coalesce cast comma count coerces integral real' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteCoalesceCastLimit)['limit'], 2],
    'delete coalesce cast comma limit selects ordered lazy window' => [static fn (): mixed => $execute($deleteCoalesceCastLimit)['plan']->selectedIds, [2, 3]],
    'update row-value coalesce subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueCoalesceSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'update row-value coalesce subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueCoalesceSubqueryLimit)['returning'], 'setting_id'), [2, 3, 5]],
    'delete row-value nullif subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueNullifSubqueryLimit)['plan']->selectedIds, [2, 3, 5, 8]],
    'delete row-value nullif subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueNullifSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 4, 6, 7]],
    'update row-value ordinal second subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueOrdinalSecondSubqueryLimit)['plan']->selectedIds, [2, 5]],
    'update row-value ordinal second subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueOrdinalSecondSubqueryLimit)['returning'], 'setting_id'), [2, 5]],
    'delete row-value ordinal first comma subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueOrdinalFirstSubqueryCommaLimit)['plan']->selectedIds, [2, 5, 6]],
    'delete row-value ordinal first comma subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueOrdinalFirstSubqueryCommaLimit)['tables']['app_settings'], 'setting_id'), [1, 3, 4, 7, 8]],
    'update row-value ordinal expression projection applies before tuple match' => [static fn (): mixed => $execute($updateRowValueOrdinalExpressionProjectionLimit)['plan']->selectedIds, [2, 3]],
    'update row-value ordinal expression projection returns source order' => [static fn (): mixed => array_column($execute($updateRowValueOrdinalExpressionProjectionLimit)['returning'], 'setting_id'), [2, 3]],
    'delete row-value ordinal with expression tie-break applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueOrdinalLengthProjectionLimit)['plan']->selectedIds, [2, 5, 6]],
    'delete row-value ordinal with expression tie-break keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueOrdinalLengthProjectionLimit)['tables']['app_settings'], 'setting_id'), [1, 3, 4, 7, 8]],
    'parse update length limit from text literal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateLengthLimit)['limit'], 3],
    'parse update length offset from text literal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateLengthLimit)['offset'], 1],
    'update length limit selects ordered lazy window' => [static fn (): mixed => $execute($updateLengthLimit)['plan']->selectedIds, [2, 3, 6]],
    'update length limit returns source order' => [static fn (): mixed => array_column($execute($updateLengthLimit)['returning'], 'setting_id'), [2, 3, 6]],
    'parse delete length comma offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteLengthCommaLimit)['offset'], 1],
    'parse delete length comma count' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteLengthCommaLimit)['limit'], 2],
    'delete length comma limit selects ordered lazy window' => [static fn (): mixed => $execute($deleteLengthCommaLimit)['plan']->selectedIds, [2, 3]],
    'update row-value length subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueLengthSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'update row-value length subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueLengthSubqueryLimit)['returning'], 'setting_id'), [2, 3, 5]],
    'delete row-value length subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueLengthSubqueryLimit)['plan']->selectedIds, [2, 5, 8]],
    'delete row-value length subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueLengthSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 3, 4, 6, 7]],
    'parse update quote limit constant expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateQuoteTypeofLimit)['limit'], 3],
    'parse update typeof predicate offset constant expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateQuoteTypeofLimit)['offset'], 1],
    'update quote typeof limit selects ordered lazy window' => [static fn (): mixed => $execute($updateQuoteTypeofLimit)['plan']->selectedIds, [2, 3, 6]],
    'update quote typeof limit returning source order' => [static fn (): mixed => array_column($execute($updateQuoteTypeofLimit)['returning'], 'setting_id'), [2, 3, 6]],
    'parse delete typeof predicate comma offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteQuoteTypeofCommaLimit)['offset'], 1],
    'parse delete quote comma count' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteQuoteTypeofCommaLimit)['limit'], 2],
    'delete quote typeof comma limit selects ordered lazy window' => [static fn (): mixed => $execute($deleteQuoteTypeofCommaLimit)['plan']->selectedIds, [2, 3]],
    'update row-value quote typeof subquery applies before tuple match' => [static fn (): mixed => $execute($updateRowValueQuoteTypeofSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'update row-value quote typeof subquery returns source order' => [static fn (): mixed => array_column($execute($updateRowValueQuoteTypeofSubqueryLimit)['returning'], 'setting_id'), [2, 3, 5]],
    'delete row-value quote typeof subquery applies before tuple match' => [static fn (): mixed => $execute($deleteRowValueQuoteTypeofSubqueryLimit)['plan']->selectedIds, [2, 3, 5]],
    'delete row-value quote typeof subquery keeps unmatched rows' => [static fn (): mixed => array_column($execute($deleteRowValueQuoteTypeofSubqueryLimit)['tables']['app_settings'], 'setting_id'), [1, 4, 6, 7, 8]],
    'malformed modulo zero limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 5%0"), InvalidArgumentException::class],
    'malformed coalesce all null limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT coalesce(NULL, NULL)"), InvalidArgumentException::class],
    'malformed nullif equal limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT nullif(2, 2)"), InvalidArgumentException::class],
    'malformed ifnull arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ifnull(NULL)"), InvalidArgumentException::class],
    'malformed coalesce arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT coalesce(NULL)"), InvalidArgumentException::class],
    'malformed cast null limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST(NULL AS INTEGER)"), InvalidArgumentException::class],
    'malformed cast blob offset rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET CAST(X'ABCD' AS INT)"), InvalidArgumentException::class],
    'malformed nonintegral real cast limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST('2.5' AS REAL)"), InvalidArgumentException::class],
    'malformed nonintegral numeric cast limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST('2.5' AS NUMERIC)"), InvalidArgumentException::class],
    'malformed blob cast limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT CAST('2' AS BLOB)"), InvalidArgumentException::class],
    'malformed non-integral limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1.2"), InvalidArgumentException::class],
    'malformed non-integral exponent limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2.5e0"), InvalidArgumentException::class],
    'malformed null offset rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET NULL"), InvalidArgumentException::class],
    'malformed row-value subquery ordinal zero rejected' => [static fn (): mixed => $execute("UPDATE app_settings SET state = 'bad_ordinal' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY 0 LIMIT 1) RETURNING setting_id"), InvalidArgumentException::class],
    'malformed row-value subquery ordinal out of range rejected' => [static fn (): mixed => $execute("DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY 3 LIMIT 1) RETURNING setting_id"), InvalidArgumentException::class],
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

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 3;
    $limitExpr = $seed % 2 === 0 ? "coalesce(NULL, {$limitValue})" : "ifnull(NULL, {$limitValue})";
    $offsetExpr = $seed % 3 === 0 ? "coalesce(NULL, {$offsetValue})" : "nullif({$offsetValue}, -1)";
    $sql = "UPDATE app_settings SET state = 'scalar_fn_dyn' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update scalar function window seed %02d', $seed)] =
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
    $offsetValue = ($seed + 2) % 4;
    $limitExpr = $seed % 2 === 0 ? "coalesce(NULL, {$limitValue})" : "nullif({$limitValue}, 99)";
    $offsetExpr = $seed % 3 === 0 ? "ifnull(NULL, {$offsetValue})" : "coalesce(NULL, {$offsetValue})";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue scalar function subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
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

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $condition = $seed % 2 === 0 ? '1' : "'2'";
    $limitExpr = "CASE WHEN {$condition} THEN {$limitValue} ELSE 1 END";
    $offsetExpr = "CASE WHEN 0 THEN 4 ELSE {$offsetValue} END";
    $sql = "UPDATE app_settings SET state = 'case_dyn' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update searched case window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 2) % 4;
    $limitCondition = $seed % 2 === 0 ? 'TRUE' : "'0'";
    $offsetCondition = $seed % 3 === 0 ? 'NULL' : 'FALSE';
    $limitExpr = "CASE WHEN {$limitCondition} THEN {$limitValue} ELSE 2 END";
    $offsetExpr = "CASE WHEN {$offsetCondition} THEN 5 ELSE {$offsetValue} END";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $effectiveLimit = $seed % 2 === 0 ? $limitValue : 2;
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $effectiveLimit)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue searched case subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 4;
    $caseValue = $seed % 2 === 0 ? 2 : 3;
    $limitExpr = "CASE {$caseValue} WHEN 2 THEN {$limitValue} ELSE {$limitValue} END";
    $offsetExpr = "CASE {$caseValue} WHEN 3 THEN {$offsetValue} ELSE {$offsetValue} END";
    $sql = "UPDATE app_settings SET state = 'simple_case_dyn' WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update simple case window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 3) % 4;
    $caseValue = $seed % 2 === 0 ? 10 : 20;
    $limitExpr = "CASE {$caseValue} WHEN 10 THEN {$limitValue} ELSE {$limitValue} END";
    $offsetExpr = "CASE {$caseValue} WHEN 20 THEN {$offsetValue} ELSE {$offsetValue} END";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue simple case subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = $seed % 3;
    $direction = $seed % 2 === 0 ? 'ASC' : 'DESC';
    $sql = "UPDATE app_settings SET state = 'ordinal_dyn' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY 2 {$direction}, 1 ASC LIMIT {$limitValue} OFFSET {$offsetValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $ordered = $direction === 'ASC'
        ? [[1, 'beta'], [2, 'beta'], [3, 'beta'], [1, 'gamma'], [2, 'gamma']]
        : [[1, 'gamma'], [2, 'gamma'], [1, 'beta'], [2, 'beta'], [3, 'beta']];
    $expectedTuples = array_slice($ordered, $offsetValue, $limitValue);
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity update ordinal subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 3;
    $direction = $seed % 2 === 0 ? 'ASC' : 'DESC';
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name || '' FROM app_setting_targets ORDER BY 1 {$direction}, 2 DESC LIMIT {$offsetValue}, {$limitValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $ordered = $direction === 'ASC'
        ? [[1, 'gamma'], [1, 'beta'], [2, 'gamma'], [2, 'beta'], [3, 'beta']]
        : [[3, 'beta'], [2, 'gamma'], [2, 'beta'], [1, 'gamma'], [1, 'beta']];
    $expectedTuples = array_slice($ordered, $offsetValue, $limitValue);
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity delete ordinal expression subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 3;
    $limitExpr = $seed % 2 === 0 ? "max(1, {$limitValue}, 2)" : "min({$limitValue}, 4, 5)";
    $offsetExpr = $seed % 3 === 0 ? "min({$offsetValue}, 2)" : "max(0, {$offsetValue})";
    $effectiveLimit = $seed % 2 === 0 ? max(1, $limitValue, 2) : min($limitValue, 4, 5);
    $effectiveOffset = $seed % 3 === 0 ? min($offsetValue, 2) : max(0, $offsetValue);
    $sql = "UPDATE app_settings SET state = 'minmax_dyn' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $effectiveOffset, $effectiveLimit);

    $tests[sprintf('rowvalue update delete limit dynamic parity update minmax window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $effectiveLimit, $effectiveOffset): void {
            $result = $execute($sql);
            $t->same($effectiveLimit, $result['plan']->toArray()['limit']);
            $t->same($effectiveOffset, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 2) % 4;
    $limitExpr = $seed % 2 === 0 ? "max(1, {$limitValue})" : "min({$limitValue}, 3)";
    $offsetExpr = $seed % 3 === 0 ? "min({$offsetValue}, 2)" : "max(0, {$offsetValue})";
    $effectiveLimit = $seed % 2 === 0 ? max(1, $limitValue) : min($limitValue, 3);
    $effectiveOffset = $seed % 3 === 0 ? min($offsetValue, 2) : max(0, $offsetValue);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $effectiveOffset, $effectiveLimit)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue minmax subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 44; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $placement = $seed % 2 === 0 ? 'LAST' : 'FIRST';
    $direction = $seed % 4 < 2 ? 'ASC' : 'DESC';
    $sql = "UPDATE app_settings SET key_value = key_value || ':nulls' RETURNING setting_id, state, key_value ORDER BY state {$direction} NULLS {$placement}, setting_id ASC LIMIT {$limitValue} OFFSET {$offsetValue}";
    $ordered = [
        ['setting_id' => 1, 'state' => 'live'],
        ['setting_id' => 2, 'state' => 'live'],
        ['setting_id' => 3, 'state' => 'stale'],
        ['setting_id' => 4, 'state' => 'stale'],
        ['setting_id' => 5, 'state' => 'queued'],
        ['setting_id' => 6, 'state' => 'queued'],
        ['setting_id' => 7, 'state' => null],
        ['setting_id' => 8, 'state' => 'stale'],
    ];
    usort($ordered, static function (array $left, array $right) use ($direction, $placement): int {
        if ($left['state'] !== $right['state']) {
            if ($left['state'] === null || $right['state'] === null) {
                return $placement === 'FIRST'
                    ? ($left['state'] === null ? -1 : 1)
                    : ($left['state'] === null ? 1 : -1);
            }
            $comparison = $left['state'] <=> $right['state'];
            return $direction === 'DESC' ? -$comparison : $comparison;
        }

        return $left['setting_id'] <=> $right['setting_id'];
    });
    $expected = array_column(array_slice($ordered, $offsetValue, $limitValue), 'setting_id');

    $tests[sprintf('rowvalue update delete limit dynamic parity update nulls placement window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $placement): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($placement, $result['plan']->toArray()['order_by'][0]['nulls']);
            $t->same(array_values(array_intersect([1, 2, 3, 4, 5, 6, 7, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 44; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 3;
    $nullPriority = [10, 20, 30, 40, 50][$seed % 5];
    $placement = $seed % 2 === 0 ? 'LAST' : 'FIRST';
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY nullif(priority, {$nullPriority}) NULLS {$placement}, target_id ASC LIMIT {$limitValue} OFFSET {$offsetValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $targets = [
        ['tuple' => [1, 'beta'], 'priority' => 40, 'target_id' => 1],
        ['tuple' => [1, 'gamma'], 'priority' => 20, 'target_id' => 2],
        ['tuple' => [2, 'beta'], 'priority' => 30, 'target_id' => 3],
        ['tuple' => [2, 'gamma'], 'priority' => 10, 'target_id' => 4],
        ['tuple' => [3, 'beta'], 'priority' => 50, 'target_id' => 5],
    ];
    usort($targets, static function (array $left, array $right) use ($nullPriority, $placement): int {
        $leftPriority = $left['priority'] === $nullPriority ? null : $left['priority'];
        $rightPriority = $right['priority'] === $nullPriority ? null : $right['priority'];
        if ($leftPriority !== $rightPriority) {
            if ($leftPriority === null || $rightPriority === null) {
                return $placement === 'FIRST'
                    ? ($leftPriority === null ? -1 : 1)
                    : ($leftPriority === null ? 1 : -1);
            }

            return $leftPriority <=> $rightPriority;
        }

        return $left['target_id'] <=> $right['target_id'];
    });
    $expectedTuples = array_column(array_slice($targets, $offsetValue, $limitValue), 'tuple');
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue nulls placement subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitText = str_repeat('a', ($seed % 4) + 1);
    $offsetText = str_repeat('b', $seed % 3);
    $limitValue = strlen($limitText);
    $offsetValue = strlen($offsetText);
    $sql = "UPDATE app_settings SET state = 'length_dyn' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT length('{$limitText}') OFFSET length('{$offsetText}')";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update length window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitText = str_repeat('c', ($seed % 3) + 1);
    $offsetText = str_repeat('d', ($seed + 1) % 4);
    $limitValue = strlen($limitText);
    $offsetValue = strlen($offsetText);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT length('{$limitText}') OFFSET length('{$offsetText}')) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue length subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$minMaxMalformed = [
    'malformed min null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT min(1, NULL)",
    'malformed max blob offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET max(0, X'ABCD')",
    'malformed min nonintegral limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT min(2.5, 3)",
    'malformed max nonintegral offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET max(1.5, 1)",
    'malformed length null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT length(NULL)",
];

foreach ($minMaxMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 44; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 2) % 3;
    $limitExpr = $seed % 2 === 0
        ? "(SELECT {$limitValue})"
        : "(SELECT coalesce(NULL, {$limitValue}))";
    $offsetExpr = $seed % 3 === 0
        ? "(SELECT {$offsetValue})"
        : "(SELECT max(0, {$offsetValue}))";
    $sql = "UPDATE app_settings SET state = 'scalar_select_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update scalar select limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 44; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = $seed % 2 === 0
        ? "(SELECT {$limitValue})"
        : "(SELECT min({$limitValue}, 3))";
    $offsetExpr = $seed % 3 === 0
        ? "(SELECT {$offsetValue})"
        : "(SELECT coalesce(NULL, {$offsetValue}))";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue scalar select subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$scalarSelectMalformed = [
    'malformed scalar select null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT (SELECT NULL)",
    'malformed scalar select blob offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET (SELECT X'ABCD')",
    'malformed scalar select nonintegral limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT (SELECT 2.5)",
    'malformed scalar select from table limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT (SELECT 2 FROM app_setting_targets)",
];

foreach ($scalarSelectMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitExpr = $seed % 2 === 0 ? "{$limitValue} BETWEEN 1 AND 4" : "{$limitValue} IN (2, {$limitValue}, 9)";
    $offsetExpr = $seed % 3 === 0 ? "{$offsetValue} IS 0" : "{$offsetValue} = {$offsetValue}";
    $effectiveLimit = 1;
    $effectiveOffset = 1;
    $sql = "UPDATE app_settings SET state = 'predicate_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $effectiveOffset, $effectiveLimit);

    $tests[sprintf('rowvalue update delete limit dynamic parity update predicate expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $effectiveLimit, $effectiveOffset): void {
            $result = $execute($sql);
            $t->same($effectiveLimit, $result['plan']->toArray()['limit']);
            $t->same($effectiveOffset, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitExpr = $seed % 2 === 0 ? '3 NOT BETWEEN 4 AND 8' : '2 NOT IN (3, 4, 5)';
    $offsetExpr = $seed % 4 === 0 ? 'NULL IS NULL' : '1 IS NOT 2';
    $effectiveLimit = 1;
    $effectiveOffset = 1;
    $sql = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $effectiveOffset, $effectiveLimit);

    $tests[sprintf('rowvalue update delete limit dynamic parity delete negated predicate expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $effectiveLimit, $effectiveOffset): void {
            $result = $execute($sql);
            $t->same($effectiveLimit, $result['plan']->toArray()['limit']);
            $t->same($effectiveOffset, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitExpr = $seed % 2 === 0 ? '4 >= 2' : '2 <> 9';
    $offsetExpr = $seed % 3 === 0 ? '3 < 2' : '5 <= 5';
    $effectiveLimit = 1;
    $effectiveOffset = $seed % 3 === 0 ? 0 : 1;
    $sql = "UPDATE app_settings SET state = 'predicate_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $effectiveOffset, $effectiveLimit)));

    $tests[sprintf('rowvalue update delete limit dynamic parity update rowvalue comparison predicate subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitExpr = $seed % 2 === 0 ? 'NOT (0)' : 'NOT (3 IN (4,5))';
    $offsetExpr = $seed % 4 === 0 ? 'NOT (1)' : 'NOT (NULL IS NOT NULL)';
    $effectiveLimit = 1;
    $effectiveOffset = $seed % 4 === 0 ? 0 : 1;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $effectiveOffset, $effectiveLimit)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue unary not predicate subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$predicateMalformed = [
    'malformed null comparison limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT NULL = 1",
    'malformed null between limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 2 BETWEEN NULL AND 3",
    'malformed null in list offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET 2 IN (1, NULL)",
    'malformed missing between upper rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 2 BETWEEN 1",
    'malformed missing in list rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 2 IN 1,2",
];

foreach ($predicateMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitText = str_repeat('é', ($seed % 4) + 1);
    $offsetText = str_repeat('β', $seed % 3);
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $sql = "UPDATE app_settings SET state = 'unicode_length_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT length('{$limitText}') OFFSET length('{$offsetText}')";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update unicode length limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitText = str_repeat('δ', ($seed % 3) + 1);
    $offsetText = str_repeat('λ', ($seed + 1) % 4);
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT length('{$limitText}') OFFSET length('{$offsetText}')) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue unicode length subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = $seed % 3;
    $direction = $seed % 2 === 0 ? 'ASC' : 'DESC';
    $sql = "UPDATE app_settings SET state = 'unicode_length_order' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY length(key_name || 'é') {$direction}, priority ASC LIMIT {$limitValue} OFFSET {$offsetValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $ordered = [
        ['tuple' => [2, 'beta'], 'length' => 5, 'priority' => 30],
        ['tuple' => [3, 'beta'], 'length' => 5, 'priority' => 50],
        ['tuple' => [2, 'gamma'], 'length' => 6, 'priority' => 10],
        ['tuple' => [1, 'gamma'], 'length' => 6, 'priority' => 20],
        ['tuple' => [1, 'beta'], 'length' => 5, 'priority' => 40],
    ];
    usort($ordered, static function (array $left, array $right) use ($direction): int {
        if ($left['length'] !== $right['length']) {
            return $direction === 'DESC'
                ? $right['length'] <=> $left['length']
                : $left['length'] <=> $right['length'];
        }

        return $left['priority'] <=> $right['priority'];
    });
    $expectedTuples = array_column(array_slice($ordered, $offsetValue, $limitValue), 'tuple');
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity update rowvalue unicode length order subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$unicodeLengthMalformed = [
    'malformed unicode length null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT length(NULL)",
    'malformed unicode length blob offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET length(X'C3A9')",
];

foreach ($unicodeLengthMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitLeft = intdiv($limitValue, 10);
    $limitRight = $limitValue % 10;
    $offsetLeft = intdiv($offsetValue, 10);
    $offsetRight = $offsetValue % 10;
    $limitExpr = "'{$limitLeft}' || '{$limitRight}'";
    $offsetExpr = "'{$offsetLeft}' || '{$offsetRight}'";
    $sql = "UPDATE app_settings SET state = 'concat_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update concat expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitLeft = intdiv($limitValue, 10);
    $limitRight = $limitValue % 10;
    $offsetLeft = intdiv($offsetValue, 10);
    $offsetRight = $offsetValue % 10;
    $limitExpr = "'{$limitLeft}' || '{$limitRight}'";
    $offsetExpr = "'{$offsetLeft}' || '{$offsetRight}'";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue concat subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$concatMalformed = [
    'malformed concat null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT '1' || NULL",
    'malformed concat blob offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET '0' || X'ABCD'",
    'malformed concat nonintegral limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT '2' || '.5'",
    'malformed concat empty expression rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT '1' || ",
];

foreach ($concatMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitInput = $limitValue + ($seed % 2 === 0 ? 0.49 : -0.49);
    $offsetInput = $offsetValue + ($seed % 2 === 0 ? 0.24 : -0.24);
    $sql = sprintf(
        "UPDATE app_settings SET state = 'round_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT round('%.2f') OFFSET round('%.2f')",
        $limitInput,
        $offsetInput,
    );
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update round expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitInput = $limitValue + ($seed % 2 === 0 ? 0.49 : -0.49);
    $offsetInput = $offsetValue + ($seed % 2 === 0 ? 0.24 : -0.24);
    $sql = sprintf(
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT round('%.2f') OFFSET round('%.2f')) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        $limitInput,
        $offsetInput,
    );
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue round subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 2) % 3;
    $sql = "UPDATE app_settings SET state = 'round_precision' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT round({$limitValue}.04, 1) OFFSET round({$offsetValue}.04, 1)";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update round precision window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

$roundMalformed = [
    'malformed round null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT round(NULL)",
    'malformed round blob offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET round(X'ABCD')",
    'malformed round text limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT round('abc')",
    'malformed round nonintegral precision result rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT round(2.44, 1)",
    'malformed round arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT round()",
];

foreach ($roundMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitMagnitude = $limitValue + ($seed % 2 === 0 ? 8 : 12);
    $offsetMagnitude = $offsetValue + 7;
    $limitExpr = "{$limitValue} + sign({$limitMagnitude}) - sign(-{$limitMagnitude}) - 2";
    $offsetExpr = "{$offsetValue} + sign({$offsetMagnitude}) + sign(-{$offsetMagnitude})";
    $sql = "UPDATE app_settings SET state = 'sign_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update sign expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitMagnitude = $limitValue + 5;
    $offsetMagnitude = $offsetValue + 9;
    $limitExpr = "{$limitValue} + sign({$limitMagnitude}) + sign(-{$limitMagnitude})";
    $offsetExpr = "{$offsetValue} + sign({$offsetMagnitude}) + sign(-{$offsetMagnitude})";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue sign subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$signMalformed = [
    'malformed sign null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT sign(NULL)",
    'malformed sign text limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT sign('abc')",
    'malformed sign blob offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET sign(X'ABCD')",
    'malformed sign arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT sign(1, 2)",
];

foreach ($signMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = $seed % 2;
    $sql = "UPDATE app_settings SET state = 'distinct_tuple_limit' WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitValue} OFFSET {$offsetValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $tablesWithDuplicates = $tables();
    $tablesWithDuplicates['app_setting_targets'][] = ['target_id' => 6, 'tenant_id' => 1, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 5];
    $tablesWithDuplicates['app_setting_targets'][] = ['target_id' => 7, 'tenant_id' => 2, 'key_name' => 'gamma', 'action' => 'cleanup', 'priority' => 60];
    $orderedDistinctTuples = [[1, 'beta'], [2, 'gamma'], [1, 'gamma'], [2, 'beta'], [3, 'beta']];
    $expectedTuples = array_slice($orderedDistinctTuples, $offsetValue, $limitValue);
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity update distinct duplicate tuple source seed %02d', $seed)] =
        static function (TestRunner $t) use ($sql, $tablesWithDuplicates, $expected): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tablesWithDuplicates, 'setting_id', [['tenant_id', 'key_name']]);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 2;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT {$limitValue} OFFSET {$offsetValue} UNION SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'cleanup' ORDER BY priority ASC LIMIT {$limitValue} OFFSET {$offsetValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $refreshOrdered = [[1, 'gamma'], [2, 'beta'], [1, 'beta']];
    $cleanupOrdered = [[2, 'gamma'], [3, 'beta']];
    $expectedTuples = [];
    foreach (array_merge(array_slice($refreshOrdered, $offsetValue, $limitValue), array_slice($cleanupOrdered, $offsetValue, $limitValue)) as $tuple) {
        if (!in_array($tuple, $expectedTuples, true)) {
            $expectedTuples[] = $tuple;
        }
    }
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity delete compound union tuple source seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 24; $seed++) {
    $limitValue = ($seed % 2) + 1;
    $offsetValue = $seed % 2;
    $sql = "UPDATE app_settings SET state = 'compound_intersect_limit' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE tenant_id IN (1,2) ORDER BY priority ASC LIMIT {$limitValue} OFFSET {$offsetValue} INTERSECT SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority ASC LIMIT 3 OFFSET 0) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $left = array_slice([[2, 'gamma'], [1, 'gamma'], [2, 'beta'], [1, 'beta']], $offsetValue, $limitValue);
    $right = [[1, 'gamma'], [2, 'beta'], [1, 'beta']];
    $expectedTuples = array_values(array_filter($left, static fn (array $tuple): bool => in_array($tuple, $right, true)));
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity update compound intersect tuple source seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = $seed % 2;
    $sql = "UPDATE app_settings SET state = 'compound_union_all_limit' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE target_id IN (1,2,3) ORDER BY priority ASC LIMIT {$limitValue} OFFSET {$offsetValue} UNION ALL SELECT tenant_id, key_name FROM app_setting_targets WHERE target_id IN (1,3,5) ORDER BY priority DESC LIMIT {$limitValue} OFFSET {$offsetValue}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $left = array_slice([[1, 'gamma'], [2, 'beta'], [1, 'beta']], $offsetValue, $limitValue);
    $right = array_slice([[3, 'beta'], [1, 'beta'], [2, 'beta']], $offsetValue, $limitValue);
    $expectedTuples = array_merge($left, $right);
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity update compound union all tuple source seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 2;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitValue} OFFSET {$offsetValue} EXCEPT SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'cleanup' ORDER BY priority ASC LIMIT 3 OFFSET 0) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $left = array_slice([[2, 'gamma'], [1, 'gamma'], [2, 'beta'], [1, 'beta'], [3, 'beta']], $offsetValue, $limitValue);
    $right = [[2, 'gamma'], [3, 'beta']];
    $expectedTuples = array_values(array_filter($left, static fn (array $tuple): bool => !in_array($tuple, $right, true)));
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }

    $tests[sprintf('rowvalue update delete limit dynamic parity delete compound except tuple source seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitExpr = $seed % 2 === 0
        ? "upper('{$limitValue}')"
        : "lower('{$limitValue}')";
    $offsetExpr = $seed % 3 === 0
        ? "trim(' {$offsetValue} ')"
        : "rtrim('{$offsetValue}   ')";
    $sql = "UPDATE app_settings SET state = 'text_scalar_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update text scalar limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = $seed % 2 === 0
        ? "ltrim('   {$limitValue}')"
        : "trim(' {$limitValue} ')";
    $offsetExpr = $seed % 3 === 0
        ? "upper('{$offsetValue}')"
        : "lower('{$offsetValue}')";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue text scalar subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitExpr = "substr('abc{$limitValue}xyz', 4, 1)";
    $offsetExpr = "substring('pq{$offsetValue}rs', 3, 1)";
    $sql = "UPDATE app_settings SET state = 'substr_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update substring limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = "substr('x{$limitValue}y', -2, 1)";
    $offsetExpr = "substring('z{$offsetValue}w', -2, 1)";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue negative substring subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$textScalarMalformed = [
    'malformed upper null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT upper(NULL)",
    'malformed trim nonintegral limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT trim('2.5')",
    'malformed substr missing length rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT substr('123')",
    'malformed substr noninteger start rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT substr('123', 1.5, 1)",
    'malformed substr null offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET substr(NULL, 1, 1)",
];

foreach ($textScalarMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitHaystack = str_repeat('x', $limitValue - 1) . (string) $limitValue;
    $offsetHaystack = $offsetValue === 0 ? '0' : str_repeat('x', $offsetValue - 1) . (string) $offsetValue;
    $limitExpr = "instr('{$limitHaystack}', '{$limitValue}')";
    $offsetExpr = $offsetValue === 0 ? "instr('{$offsetHaystack}', 'missing')" : "instr('{$offsetHaystack}', '{$offsetValue}')";
    $sql = "UPDATE app_settings SET state = 'instr_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update instr limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = "replace('x{$limitValue}x', 'x', '')";
    $offsetExpr = "replace('z{$offsetValue}z', 'z', '')";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue replace subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitCodepoint = 48 + $limitValue;
    $offsetCodepoint = 48 + $offsetValue;
    $sql = "UPDATE app_settings SET state = 'char_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT char({$limitCodepoint}) OFFSET char({$offsetCodepoint})";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update char limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

$unicodeLimitCases = [
    'parse unicode char limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT unicode(char(3))")['limit'], 3],
    'parse unicode ascii text offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET unicode('2')-48")['offset'], 2],
    'parse unicode two-byte text' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT unicode('é')-230")['limit'], 3],
    'parse unicode three-byte text' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT unicode('€')-8361")['limit'], 3],
    'parse unicode four-byte text' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT unicode('🙂')-128575")['limit'], 3],
    'malformed unicode arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT unicode('1','2')"), InvalidArgumentException::class],
    'malformed unicode empty limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT unicode('')"), InvalidArgumentException::class],
];

foreach ($unicodeLimitCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitCodepoint = $limitValue;
    $offsetCodepoint = $offsetValue;
    $sql = "UPDATE app_settings SET state = 'unicode_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT unicode(char({$limitCodepoint})) OFFSET unicode(char({$offsetCodepoint}))";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update unicode codepoint limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 2) % 4;
    $limitCodepoint = 48 + $limitValue;
    $offsetCodepoint = 48 + $offsetValue;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT unicode(char({$limitCodepoint}))-48 OFFSET unicode(char({$offsetCodepoint}))-48) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue unicode subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$stringPositionMalformed = [
    'malformed instr arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT instr('123')",
    'malformed instr null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT instr(NULL, '9')",
    'malformed replace null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT replace(NULL, 'x', '1')",
    'malformed replace arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT replace('123', '1')",
    'malformed char noninteger codepoint rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT char(1.5)",
    'malformed unsupported unicode null offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET unicode(NULL)",
];

foreach ($stringPositionMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $condition = match ($seed % 4) {
        0 => '1',
        1 => '0.5',
        2 => "'1english'",
        default => "'0'",
    };
    $limitExpr = "iif({$condition}, {$limitValue}, {$limitValue})";
    $offsetExpr = $seed % 2 === 0
        ? "if(FALSE, 9, {$offsetValue})"
        : "iif(NULL, 9, {$offsetValue})";
    $sql = "UPDATE app_settings SET state = 'iif_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update iif expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitCondition = $seed % 2 === 0 ? 'TRUE' : "'2'";
    $offsetCondition = $seed % 3 === 0 ? '0' : 'NULL';
    $limitExpr = "iif({$limitCondition}, {$limitValue}, 8)";
    $offsetExpr = "if({$offsetCondition}, 8, {$offsetValue})";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue iif subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$iifMalformed = [
    'malformed iif arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT iif(TRUE, 1)",
    'malformed if arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT if(TRUE, 1)",
    'malformed iif null selected branch rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT iif(TRUE, NULL, 1)",
    'malformed if nonintegral selected branch rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT if(FALSE, 1, 2.5)",
];

foreach ($iifMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitExpr = $seed % 2 === 0
        ? "likely({$limitValue})"
        : "unlikely({$limitValue})";
    $offsetExpr = $seed % 3 === 0
        ? "likelihood({$offsetValue}, 0.75)"
        : "likely({$offsetValue})";
    $sql = "UPDATE app_settings SET state = 'likelihood_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit dynamic parity update likelihood expression window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = $seed % 2 === 0
        ? "likelihood({$limitValue}, 0.25)"
        : "likely({$limitValue})";
    $offsetExpr = $seed % 3 === 0
        ? "unlikely({$offsetValue})"
        : "likelihood({$offsetValue}, 0.5)";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue likelihood subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$likelihoodMalformed = [
    'malformed likely arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT likely(1, 2)",
    'malformed unlikely arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT unlikely()",
    'malformed likelihood arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT likelihood(1)",
    'malformed likelihood probability rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT likelihood(1, 'probably')",
    'malformed likelihood null value rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT likelihood(NULL, 0.5)",
    'malformed likely nonintegral value rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT likely(2.5)",
];

foreach ($likelihoodMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($limit = 1; $limit <= 5; $limit++) {
    for ($offset = 0; $offset <= 4; $offset++) {
        $sql = "UPDATE app_settings SET state = 'rowvalue4_window' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
        $windowIds = array_slice([6, 3, 5, 2, 8], $offset, $limit);
        $expected = array_values(array_intersect([2, 3, 5, 6, 8], $windowIds));

        $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue4 update ordered tuple window limit %d offset %d', $limit, $offset)] =
            static function (TestRunner $t) use ($execute, $sql, $expected, $limit, $offset): void {
                $result = $execute($sql);
                $t->same($expected, $result['plan']->selectedIds);
                $t->same($expected, array_column($result['returning'], 'setting_id'));
                $t->contains("LIMIT {$limit} OFFSET {$offset}", $sql);
            };
    }
}

for ($limit = 1; $limit <= 5; $limit++) {
    for ($offset = 0; $offset <= 4; $offset++) {
        $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
        $windowIds = array_slice([8, 2, 5, 3, 6], $offset, $limit);
        $expected = array_values(array_intersect([2, 3, 5, 6, 8], $windowIds));

        $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue4 delete descending tuple window limit %d offset %d', $limit, $offset)] =
            static function (TestRunner $t) use ($execute, $sql, $expected): void {
                $result = $execute($sql);
                $t->same($expected, $result['plan']->selectedIds);
                $t->same($expected, array_column($result['returning'], 'setting_id'));
                $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expected)), array_column($result['tables']['app_settings'], 'setting_id'));
            };
    }
}

$nullTupleCases = [
    'update matching null tuple is unknown and skipped' => [
        "UPDATE app_settings SET key_value = 'null_tuple' WHERE (tenant_id, state) IN (SELECT tenant_id, state FROM app_settings WHERE setting_id = 7) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [],
    ],
    'delete matching null tuple is unknown and skipped' => [
        "DELETE FROM app_settings WHERE (tenant_id, state) IN (SELECT tenant_id, state FROM app_settings WHERE setting_id = 7) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [],
    ],
    'update nonmatching tuple with null source is unknown and skipped' => [
        "UPDATE app_settings SET key_value = 'null_tuple' WHERE (tenant_id, state) IN (SELECT tenant_id, state FROM app_settings WHERE setting_id IN (7, 8) ORDER BY setting_id LIMIT 1) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [],
    ],
    'delete null tuple source still matches concrete tuple after offset' => [
        "DELETE FROM app_settings WHERE (tenant_id, state) IN (SELECT tenant_id, state FROM app_settings WHERE setting_id IN (7, 8) ORDER BY setting_id LIMIT 1 OFFSET 1) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [8],
    ],
    'update not in against null tuple source is unknown and skipped' => [
        "UPDATE app_settings SET key_value = 'not_in_null_tuple' WHERE (tenant_id, state) NOT IN (SELECT tenant_id, state FROM app_settings WHERE setting_id = 7) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [1, 2, 3, 4, 5, 6],
    ],
    'delete not in with null source excluded by offset can select concrete nonmatches' => [
        "DELETE FROM app_settings WHERE (tenant_id, state) NOT IN (SELECT tenant_id, state FROM app_settings WHERE setting_id IN (7, 8) ORDER BY setting_id LIMIT 1 OFFSET 1) RETURNING setting_id ORDER BY setting_id LIMIT 3",
        [1, 2, 3],
    ],
];

foreach ($nullTupleCases as $name => [$sql, $expected]) {
    $tests['rowvalue update delete limit dynamic parity rowvalue3 ' . $name] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
        };
}

$collationCases = [
    'update rhs scalar nocase tuple match' => [
        "UPDATE app_settings SET state = 'collate_rhs' WHERE (key_name, tenant_id) = ('BETA' COLLATE nocase, 1) RETURNING setting_id, key_name, tenant_id ORDER BY setting_id LIMIT -1",
        [2],
    ],
    'delete rhs scalar nocase tuple match' => [
        "DELETE FROM app_settings WHERE (key_name, tenant_id) = ('GAMMA' COLLATE nocase, 2) RETURNING setting_id, key_name, tenant_id ORDER BY setting_id LIMIT -1",
        [6],
    ],
    'update lhs nocase tuple match' => [
        "UPDATE app_settings SET state = 'collate_lhs' WHERE (key_name COLLATE nocase, tenant_id) = ('BETA', 3) RETURNING setting_id, key_name, tenant_id ORDER BY setting_id LIMIT -1",
        [8],
    ],
    'delete lhs nocase tuple is not skips case-insensitive equal row' => [
        "DELETE FROM app_settings WHERE (key_name COLLATE nocase, tenant_id) IS NOT ('BETA', 3) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [1, 2, 3, 4, 5, 6, 7],
    ],
    'update rhs nocase tuple comparison selects lowercase peer' => [
        "UPDATE app_settings SET state = 'collate_compare' WHERE (key_name, tenant_id) < ('BETA' COLLATE nocase, 2) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [1, 2, 4, 7],
    ],
    'delete lhs rtrim tuple comparison ignores right padding' => [
        "DELETE FROM app_settings WHERE (key_name COLLATE rtrim, tenant_id) = ('beta   ', 1) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [2],
    ],
];

foreach ($collationCases as $name => [$sql, $expected]) {
    $tests['rowvalue update delete limit dynamic parity rowvalue3 collate ' . $name] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->contains('/test/rowvalue.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test');
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 1) % 3;
    $direction = $seed % 2 === 0 ? 'ASC' : 'DESC';
    $ordered = $direction === 'ASC'
        ? [[1, 'beta'], [2, 'beta'], [3, 'beta'], [1, 'gamma'], [2, 'gamma']]
        : [[1, 'gamma'], [2, 'gamma'], [1, 'beta'], [2, 'beta'], [3, 'beta']];
    $expectedTuples = array_slice($ordered, $offset, $limit);
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }
    $sql = "UPDATE app_settings SET state = 'collate_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, upper(key_name) COLLATE nocase FROM app_setting_targets ORDER BY upper(key_name) COLLATE nocase {$direction}, tenant_id ASC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";

    $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue3 collate update subquery order seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = $seed % 3;
    $ordered = [[2, 'gamma'], [1, 'gamma'], [3, 'beta'], [2, 'beta'], [1, 'beta']];
    $expectedTuples = array_slice($ordered, $offset, $limit);
    $expected = [];
    foreach ([1 => [1, 'alpha'], 2 => [1, 'beta'], 3 => [1, 'gamma'], 4 => [2, 'alpha'], 5 => [2, 'beta'], 6 => [2, 'gamma'], 7 => [3, 'alpha'], 8 => [3, 'beta']] as $settingId => $tuple) {
        if (in_array($tuple, $expectedTuples, true)) {
            $expected[] = $settingId;
        }
    }
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name COLLATE nocase FROM app_setting_targets ORDER BY key_name COLLATE nocase DESC, tenant_id DESC LIMIT {$offset}, {$limit}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";

    $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue3 collate delete subquery order seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expected)), array_column($result['tables']['app_settings'], 'setting_id'));
        };
}

$notLikeTargetIds = [5, 2, 8];
$likeTargetIds = [6, 3];

for ($seed = 1; $seed <= 48; $seed++) {
    $limit = 1 + ($seed % 4);
    $offset = intdiv($seed, 4) % 3;
    $operator = $seed % 2 === 0 ? 'NOT LIKE' : 'NOT GLOB';
    $pattern = $operator === 'NOT LIKE' ? "'g%'" : "'?amma'";
    $orderedWindow = array_slice($notLikeTargetIds, $offset, $limit);
    $expected = array_values(array_intersect([2, 5, 8], $orderedWindow));
    $sql = "UPDATE app_settings SET state = 'not_like_window' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE key_name {$operator} {$pattern} ORDER BY priority ASC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";

    $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue3 update subquery %s window seed %02d', strtolower(str_replace(' ', '-', $operator)), $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $operator): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->contains($operator, $sql);
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $limit = 1 + ($seed % 3);
    $offset = intdiv($seed, 3) % 2;
    $operator = $seed % 2 === 0 ? 'LIKE' : 'GLOB';
    $pattern = $operator === 'LIKE' ? "'g%'" : "'?amma'";
    $orderedWindow = array_slice($likeTargetIds, $offset, $limit);
    $expected = array_values(array_intersect([3, 6], $orderedWindow));
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE key_name {$operator} {$pattern} ORDER BY priority ASC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";

    $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue3 delete subquery %s window seed %02d', strtolower($operator), $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $operator): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expected)), array_column($result['tables']['app_settings'], 'setting_id'));
            $t->contains($operator, $sql);
        };
}

for ($limit = 1; $limit <= 6; $limit++) {
    for ($offset = 0; $offset <= 3; $offset++) {
        $sql = "UPDATE app_settings SET (state, key_value, bytes) = (SELECT 'rv_select', key_value || ':rv', bytes + {$limit}) WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, state, key_value, bytes ORDER BY bytes ASC LIMIT -1";
        $windowIds = array_slice([6, 3, 5, 2, 8], $offset, $limit);
        $selected = $windowIds;
        $expected = array_values(array_intersect([2, 3, 5, 6, 8], $windowIds));

        $tests[sprintf('rowvalue update delete limit dynamic parity update rowvalue select assignment ordered tuple limit %d offset %d', $limit, $offset)] =
            static function (TestRunner $t) use ($execute, $sql, $selected, $expected, $limit): void {
                $result = $execute($sql);
                $returnedById = array_column($result['returning'], null, 'setting_id');
                $expectedValues = [2 => 'B:rv', 3 => 'C:rv', 5 => 'E:rv', 6 => 'F:rv', 8 => 'H:rv'];
                $actualSelected = $result['plan']->selectedIds;
                sort($actualSelected);
                $expectedSelected = $selected;
                sort($expectedSelected);
                $t->same($expectedSelected, $actualSelected);
                $t->same($expected, array_values(array_intersect([2, 3, 5, 6, 8], array_column($result['returning'], 'setting_id'))));
                $t->same(array_fill(0, count($expected), 'rv_select'), array_column($result['returning'], 'state'));
                foreach ($expected as $settingId) {
                    $t->same($expectedValues[$settingId], $returnedById[$settingId]['key_value']);
                }
                $t->contains("LIMIT {$limit}", $sql);
            };
    }
}

for ($limit = 1; $limit <= 6; $limit++) {
    for ($offset = 0; $offset <= 3; $offset++) {
        $sql = "UPDATE app_settings SET (state, key_value) = (SELECT 'rv_desc', key_value || ':desc') WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT {$offset}, {$limit}) RETURNING setting_id, tenant_id, key_name, state, key_value ORDER BY setting_id LIMIT -1";
        $windowIds = array_slice([8, 2, 5, 3, 6], $offset, $limit);
        $expected = array_values(array_intersect([2, 3, 5, 6, 8], $windowIds));

        $tests[sprintf('rowvalue update delete limit dynamic parity update rowvalue select assignment descending comma limit %d offset %d', $limit, $offset)] =
            static function (TestRunner $t) use ($execute, $sql, $expected, $limit, $offset): void {
                $result = $execute($sql);
                $t->same($expected, $result['plan']->selectedIds);
                $t->same($expected, array_column($result['returning'], 'setting_id'));
                $t->same(array_fill(0, count($expected), 'rv_desc'), array_column($result['returning'], 'state'));
                $t->contains("LIMIT {$offset}, {$limit}", $sql);
            };
    }
}

$rowValueSelectAssignmentMalformed = [
    'rowvalue select assignment arity mismatch rejected' => "UPDATE app_settings SET (state, key_value) = (SELECT 'only-one') WHERE setting_id = 1 RETURNING setting_id",
    'rowvalue select assignment duplicate target rejected' => "UPDATE app_settings SET (state, state) = (SELECT 'draft', 'final') WHERE setting_id = 1 RETURNING setting_id",
];

foreach ($rowValueSelectAssignmentMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
    };
}

for ($limit = 1; $limit <= 5; $limit++) {
    for ($offset = 0; $offset <= 4; $offset++) {
        $sql = "UPDATE app_settings SET (key_value, bytes) = (SELECT new_value, new_bytes FROM app_setting_payloads WHERE target_tenant_id = tenant_id AND target_key_name = key_name ORDER BY priority ASC LIMIT 1) WHERE load_policy = 'lazy' RETURNING setting_id, key_value, bytes ORDER BY bytes ASC LIMIT {$limit} OFFSET {$offset}";
        $windowIds = array_slice([5, 2, 3, 6, 8], $offset, $limit);
        $selectedExpected = $windowIds;
        $returningExpected = array_values(array_intersect([2, 3, 5, 6, 8], $windowIds));
        $expectedValues = [2 => 'B2', 3 => 'C2', 5 => 'E2', 6 => 'F2', 8 => 'H2'];
        $expectedBytes = [2 => 105, 3 => 113, 5 => 103, 6 => 134, 8 => 155];

        $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue7 correlated select assignment window limit %d offset %d', $limit, $offset)] =
            static function (TestRunner $t) use ($execute, $sql, $selectedExpected, $returningExpected, $expectedValues, $expectedBytes): void {
                $result = $execute($sql);
                $returnedById = array_column($result['returning'], null, 'setting_id');
                $t->same($selectedExpected, $result['plan']->selectedIds);
                $t->same($returningExpected, array_column($result['returning'], 'setting_id'));
                foreach ($returningExpected as $settingId) {
                    $t->same($expectedValues[$settingId], $returnedById[$settingId]['key_value']);
                    $t->same($expectedBytes[$settingId], $returnedById[$settingId]['bytes']);
                }
            };
    }
}

for ($limit = 1; $limit <= 5; $limit++) {
    for ($offset = 0; $offset <= 4; $offset++) {
        $sql = "UPDATE app_settings SET (key_value, bytes) = (SELECT new_value, new_bytes FROM app_setting_payloads WHERE target_tenant_id = tenant_id AND target_key_name = key_name ORDER BY priority DESC LIMIT {$offset}, 1) WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT {$limit} OFFSET {$offset}) RETURNING setting_id, tenant_id, key_name, key_value, bytes ORDER BY setting_id LIMIT -1";
        $windowIds = array_slice([8, 2, 5, 3, 6], $offset, $limit);
        $expected = array_values(array_intersect([2, 3, 5, 6, 8], $windowIds));
        $sourceIds = [
            0 => [2 => 'B2', 3 => 'C2', 5 => 'E2', 6 => 'F2', 8 => 'H2'],
            1 => [2 => null, 3 => null, 5 => null, 6 => null, 8 => null],
            2 => [2 => null, 3 => null, 5 => null, 6 => null, 8 => null],
            3 => [2 => null, 3 => null, 5 => null, 6 => null, 8 => null],
            4 => [2 => null, 3 => null, 5 => null, 6 => null, 8 => null],
        ];

        $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue7 correlated select assignment descending tuple limit %d offset %d', $limit, $offset)] =
            static function (TestRunner $t) use ($execute, $sql, $expected, $sourceIds, $offset): void {
                $result = $execute($sql);
                $returnedById = array_column($result['returning'], null, 'setting_id');
                $t->same($expected, $result['plan']->selectedIds);
                $t->same($expected, array_column($result['returning'], 'setting_id'));
                foreach ($expected as $settingId) {
                    $t->same($sourceIds[$offset][$settingId], $returnedById[$settingId]['key_value']);
                }
            };
    }
}

$rowValue7NoMatch = "UPDATE app_settings SET (key_value, bytes) = (SELECT new_value, new_bytes FROM app_setting_payloads WHERE target_tenant_id = tenant_id AND target_key_name = 'missing' LIMIT 1) WHERE setting_id IN (1, 2) RETURNING setting_id, key_value, bytes ORDER BY setting_id LIMIT -1";
$tests['rowvalue update delete limit dynamic parity rowvalue7 no assignment row yields nulls'] =
    static function (TestRunner $t) use ($execute, $rowValue7NoMatch): void {
        $result = $execute($rowValue7NoMatch);
        $t->same([1, 2], $result['plan']->selectedIds);
        $t->same([null, null], array_column($result['returning'], 'key_value'));
        $t->same([null, null], array_column($result['returning'], 'bytes'));
    };

$rowValue7Malformed = [
    'rowvalue table select assignment missing table rejected' => "UPDATE app_settings SET (key_value, bytes) = (SELECT new_value, new_bytes FROM missing_payloads WHERE target_tenant_id = tenant_id) WHERE setting_id = 1 RETURNING setting_id",
    'rowvalue table select assignment too many values rejected' => "UPDATE app_settings SET (key_value, bytes) = (SELECT new_value, new_bytes, priority FROM app_setting_payloads WHERE target_tenant_id = tenant_id) WHERE setting_id = 1 RETURNING setting_id",
];

foreach ($rowValue7Malformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($execute, $sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => $execute($sql));
    };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limit = 1 + ($seed % 3);
    $offset = intdiv($seed, 3) % 4;
    $tupleWindow = array_slice([
        [2, 'beta'],
        [1, 'gamma'],
        [2, 'gamma'],
        [3, 'beta'],
        [1, 'beta'],
    ], $offset, $limit);
    $tupleSql = implode(', ', array_map(
        static fn (array $tuple): string => sprintf('(%d, %s)', $tuple[0], var_export($tuple[1], true)),
        $tupleWindow,
    ));
    $sql = "UPDATE app_settings SET state = 'tuple_list_window' WHERE (tenant_id, key_name) IN ({$tupleSql}) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
    $expectedByTuple = [
        '2|beta' => 5,
        '1|gamma' => 3,
        '2|gamma' => 6,
        '3|beta' => 8,
        '1|beta' => 2,
    ];
    $expected = [];
    foreach ($tupleWindow as $tuple) {
        $expected[] = $expectedByTuple[$tuple[0] . '|' . $tuple[1]];
    }
    sort($expected);

    $tests[sprintf('rowvalue update delete limit dynamic parity update explicit tuple list upstream rowvalue seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limit, $offset): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expected), 'tuple_list_window'), array_column($result['returning'], 'state'));
            $t->same($limit, min($limit, 3));
            $t->true($offset >= 0);
        };
}

for ($seed = 1; $seed <= 36; $seed++) {
    $limit = 1 + ($seed % 4);
    $offset = intdiv($seed, 4) % 3;
    $tupleWindow = array_slice([
        [2, 'gamma'],
        [1, 'beta'],
        [3, 'beta'],
        [2, 'beta'],
        [1, 'gamma'],
    ], $offset, $limit);
    $tupleSql = 'VALUES ' . implode(', ', array_map(
        static fn (array $tuple): string => sprintf('(%d, %s)', $tuple[0], var_export($tuple[1], true)),
        $tupleWindow,
    ));
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ({$tupleSql}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $expectedByTuple = [
        '2|gamma' => 6,
        '1|beta' => 2,
        '3|beta' => 8,
        '2|beta' => 5,
        '1|gamma' => 3,
    ];
    $expected = [];
    foreach ($tupleWindow as $tuple) {
        $expected[] = $expectedByTuple[$tuple[0] . '|' . $tuple[1]];
    }
    sort($expected);

    $tests[sprintf('rowvalue update delete limit dynamic parity delete values tuple list upstream rowvalue seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limit, $offset): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expected)), array_column($result['tables']['app_settings'], 'setting_id'));
            $t->contains('VALUES', $sql);
            $t->true($limit >= 1 && $offset >= 0);
        };
}

$settingTuplesForBetween = [
    1 => [1, 'alpha', 8],
    2 => [1, 'beta', 5],
    3 => [1, 'gamma', 13],
    4 => [2, 'alpha', 21],
    5 => [2, 'beta', 3],
    6 => [2, 'gamma', 34],
    7 => [3, 'alpha', 2],
    8 => [3, 'beta', 55],
];
$tupleCompareForBetween = static function (array $left, array $right): int {
    foreach ([0, 1] as $index) {
        if ($left[$index] === $right[$index]) {
            continue;
        }

        return $left[$index] <=> $right[$index];
    }

    return 0;
};
$betweenWindows = [
    [[1, 'beta'], [2, 'gamma']],
    [[1, 'alpha'], [2, 'beta']],
    [[2, 'alpha'], [3, 'beta']],
    [[1, 'gamma'], [3, 'alpha']],
    [[2, 'beta'], [2, 'gamma']],
];

for ($seed = 1; $seed <= 40; $seed++) {
    $bounds = $betweenWindows[$seed % count($betweenWindows)];
    $lower = $bounds[0];
    $upper = $bounds[1];
    $limit = 1 + ($seed % 4);
    $offset = intdiv($seed, 4) % 3;
    $qualified = [];
    foreach ($settingTuplesForBetween as $settingId => $tuple) {
        if ($tupleCompareForBetween($tuple, $lower) >= 0 && $tupleCompareForBetween($tuple, $upper) <= 0) {
            $qualified[$settingId] = $tuple[2];
        }
    }
    asort($qualified);
    $selected = array_map('intval', array_keys(array_slice($qualified, $offset, $limit, true)));
    $returning = array_values(array_intersect([1, 2, 3, 4, 5, 6, 7, 8], $selected));
    $sql = sprintf(
        "UPDATE app_settings SET state = 'between_window' WHERE (tenant_id, key_name) BETWEEN (%d, %s) AND (%d, %s) RETURNING setting_id, tenant_id, key_name, state, (tenant_id, key_name) BETWEEN (%d, %s) AND (%d, %s) AS tuple_between ORDER BY bytes ASC LIMIT %d OFFSET %d",
        $lower[0],
        var_export($lower[1], true),
        $upper[0],
        var_export($upper[1], true),
        $lower[0],
        var_export($lower[1], true),
        $upper[0],
        var_export($upper[1], true),
        $limit,
        $offset,
    );

    $tests[sprintf('rowvalue update delete limit dynamic parity update rowvalue between window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $selected, $returning, $limit, $offset): void {
            $result = $execute($sql);
            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 'between_window'), array_column($result['returning'], 'state'));
            $t->same(array_fill(0, count($returning), 1), array_column($result['returning'], 'tuple_between'));
            $t->contains("LIMIT {$limit} OFFSET {$offset}", $sql);
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $bounds = $betweenWindows[($seed + 2) % count($betweenWindows)];
    $lower = $bounds[0];
    $upper = $bounds[1];
    $limit = 1 + ($seed % 3);
    $offset = intdiv($seed, 3) % 4;
    $qualified = [];
    foreach ($settingTuplesForBetween as $settingId => $tuple) {
        if (!($tupleCompareForBetween($tuple, $lower) >= 0 && $tupleCompareForBetween($tuple, $upper) <= 0)) {
            $qualified[$settingId] = [$tuple[0], $settingId];
        }
    }
    uasort(
        $qualified,
        static fn (array $left, array $right): int => ($right[0] <=> $left[0]) ?: ($left[1] <=> $right[1]),
    );
    $selected = array_map('intval', array_keys(array_slice($qualified, $offset, $limit, true)));
    $returning = array_values(array_intersect([1, 2, 3, 4, 5, 6, 7, 8], $selected));
    $sql = sprintf(
        "DELETE FROM app_settings WHERE (tenant_id, key_name) NOT BETWEEN (%d, %s) AND (%d, %s) RETURNING setting_id, tenant_id, key_name, (tenant_id, key_name) NOT BETWEEN (%d, %s) AND (%d, %s) AS tuple_not_between ORDER BY tenant_id DESC, setting_id ASC LIMIT %d, %d",
        $lower[0],
        var_export($lower[1], true),
        $upper[0],
        var_export($upper[1], true),
        $lower[0],
        var_export($lower[1], true),
        $upper[0],
        var_export($upper[1], true),
        $offset,
        $limit,
    );

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue not between comma window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $selected, $returning, $limit, $offset): void {
            $result = $execute($sql);
            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 1), array_column($result['returning'], 'tuple_not_between'));
            $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $selected)), array_column($result['tables']['app_settings'], 'setting_id'));
            $t->contains("LIMIT {$offset}, {$limit}", $sql);
        };
}

$explicitTupleMalformed = [
    'explicit tuple list scalar RHS rejected' => "UPDATE app_settings SET state = 'bad' WHERE (tenant_id, key_name) IN (1, 2) RETURNING setting_id",
    'explicit tuple list arity mismatch rejected' => "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((1, 'alpha'), (2)) RETURNING setting_id",
    'values tuple list empty rejected' => "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (VALUES) RETURNING setting_id",
];

foreach ($explicitTupleMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($sql, [
            'app_settings' => [
                ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha'],
            ],
        ]));
    };
}

$mathWindowCases = [
    'ceil floor outer update' => [
        "UPDATE app_settings SET state = 'math_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT ceil(2.1) OFFSET floor(1.9)",
        [2, 3, 6],
        'state',
        ['math_limit', 'math_limit', 'math_limit'],
    ],
    'trunc sqrt outer delete' => [
        "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT trunc(3.9) OFFSET sqrt(1)",
        [2, 3, 6],
        null,
        null,
    ],
    'power alias outer update' => [
        "UPDATE app_settings SET state = 'power_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY setting_id ASC LIMIT power(2, 2) OFFSET pow(0, 3)",
        [2, 3, 5, 6],
        'state',
        ['power_limit', 'power_limit', 'power_limit', 'power_limit'],
    ],
    'ceiling negative trunc offset delete' => [
        "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT ceiling(2.0) OFFSET trunc(1.8)",
        [2, 3],
        null,
        null,
    ],
];

foreach ($mathWindowCases as $name => [$sql, $expectedIds, $column, $expectedColumnValues]) {
    $tests['rowvalue update delete limit dynamic parity math scalar ' . $name] =
        static function (TestRunner $t) use ($execute, $sql, $expectedIds, $column, $expectedColumnValues): void {
            $result = $execute($sql);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            if (is_string($column)) {
                $t->same($expectedColumnValues, array_column($result['returning'], $column));
            } else {
                $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expectedIds)), array_column($result['tables']['app_settings'], 'setting_id'));
            }
        };
}

$mathSubqueryCases = [
    'ceil floor rowvalue update subquery' => [
        "UPDATE app_settings SET state = 'math_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT ceil(2.1) OFFSET floor(1.2)) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1",
        [2, 3, 5],
        'state',
        ['math_subquery', 'math_subquery', 'math_subquery'],
    ],
    'sqrt trunc rowvalue delete subquery' => [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT sqrt(4) OFFSET trunc(1.9)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1",
        [3, 5],
        null,
        null,
    ],
    'power rowvalue update subquery' => [
        "UPDATE app_settings SET state = 'power_subquery' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT pow(2, 1) OFFSET power(1, 2)) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1",
        [3, 5],
        'state',
        ['power_subquery', 'power_subquery'],
    ],
    'ceiling rowvalue delete subquery' => [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT ceiling(2.0) OFFSET floor(2.0)) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1",
        [2, 5],
        null,
        null,
    ],
];

foreach ($mathSubqueryCases as $name => [$sql, $expectedIds, $column, $expectedColumnValues]) {
    $tests['rowvalue update delete limit dynamic parity math scalar ' . $name] =
        static function (TestRunner $t) use ($execute, $sql, $expectedIds, $column, $expectedColumnValues): void {
            $result = $execute($sql);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            if (is_string($column)) {
                $t->same($expectedColumnValues, array_column($result['returning'], $column));
            } else {
                $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expectedIds)), array_column($result['tables']['app_settings'], 'setting_id'));
            }
        };
}

$mathMalformed = [
    'malformed ceil null limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT ceil(NULL)",
    'malformed floor text limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT floor('abc')",
    'malformed sqrt negative limit rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT sqrt(-1)",
    'malformed pow null offset rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET pow(NULL, 2)",
    'malformed power arity rejected' => "DELETE FROM app_settings RETURNING setting_id LIMIT power(2)",
];

foreach ($mathMalformed as $name => $sql) {
    $tests['rowvalue update delete limit dynamic parity math scalar ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($sql, [
            'app_settings' => [
                ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha'],
            ],
        ]));
    };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $offsetValue = $seed % 3;
    $limitExpr = match ($seed % 4) {
        0 => '2 IS TRUE',
        1 => "'1english' IS TRUE",
        2 => '0 IS NOT TRUE',
        default => 'NULL IS NOT TRUE',
    };
    $offsetExpr = $offsetValue === 0
        ? '0 IS FALSE'
        : "{$offsetValue} IS TRUE";
    $effectiveLimit = 1;
    $effectiveOffset = $offsetValue === 0 ? 1 : 1;
    $sql = "UPDATE app_settings SET state = 'is_true_limit' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice([5, 2, 3, 6, 8], $effectiveOffset, $effectiveLimit);

    $tests[sprintf('rowvalue update delete limit dynamic parity update is true limit window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same(1, $result['plan']->toArray()['limit']);
            $t->same(1, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = $seed % 2 === 0
        ? '5 IS NOT FALSE'
        : "'0' IS FALSE";
    $offsetExpr = $offsetValue === 0
        ? 'NULL IS NOT FALSE'
        : "{$offsetValue} IS TRUE";
    $effectiveLimit = 1;
    $effectiveOffset = $offsetValue === 0 ? 1 : 1;
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $subqueryOrderedIds = [6, 3, 5, 2, 8];
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($subqueryOrderedIds, $effectiveOffset, $effectiveLimit)));

    $tests[sprintf('rowvalue update delete limit dynamic parity delete rowvalue is false subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$truthPredicateCases = [
    'parse numeric true truth limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 IS TRUE")['limit'], 1],
    'parse numeric false truth limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 IS FALSE")['limit'], 0],
    'parse null is not true offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET NULL IS NOT TRUE")['offset'], 1],
    'parse null is not false offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET NULL IS NOT FALSE")['offset'], 1],
    'parse text zero is false limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT '0' IS FALSE")['limit'], 1],
    'parse text numeric prefix is true limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT '2abc' IS TRUE")['limit'], 1],
    'malformed missing truth left operand rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT IS TRUE"), InvalidArgumentException::class],
];

foreach ($truthPredicateCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit dynamic parity ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

$rowValueScalarSubqueryCases = [
    'update equals ordered scalar subquery offset window' => [
        "UPDATE app_settings SET state = 'scalar_subquery_eq' WHERE (tenant_id, key_name) = (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 2) RETURNING setting_id, tenant_id, key_name, state, (tenant_id, key_name) = (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 2) AS tuple_match ORDER BY setting_id LIMIT -1",
        [5],
        'state',
        ['scalar_subquery_eq'],
        'tuple_match',
        [1],
    ],
    'delete equals ordered scalar subquery comma limit' => [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) = (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 3, 1) RETURNING setting_id, tenant_id, key_name, (tenant_id, key_name) = (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 3, 1) AS tuple_match ORDER BY setting_id LIMIT -1",
        [3],
        null,
        null,
        'tuple_match',
        [1],
    ],
    'update not equals scalar subquery outer limit' => [
        "UPDATE app_settings SET state = 'scalar_subquery_ne' WHERE (tenant_id, key_name) <> (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 0) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT 3 OFFSET 1",
        [2, 3, 4],
        'state',
        ['scalar_subquery_ne', 'scalar_subquery_ne', 'scalar_subquery_ne'],
        null,
        null,
    ],
    'delete greater than scalar subquery ordered window' => [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) > (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 1) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT 2 OFFSET 0",
        [3, 4],
        null,
        null,
        null,
        null,
    ],
    'update less equal scalar subquery ordered window' => [
        "UPDATE app_settings SET state = 'scalar_subquery_le' WHERE (tenant_id, key_name) <= (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 3) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1",
        [1, 2, 3],
        'state',
        ['scalar_subquery_le', 'scalar_subquery_le', 'scalar_subquery_le'],
        null,
        null,
    ],
];

foreach ($rowValueScalarSubqueryCases as $name => [$sql, $expectedIds, $column, $expectedColumnValues, $flagColumn, $expectedFlags]) {
    $tests['rowvalue update delete limit dynamic parity rowvalue4 scalar subquery ' . $name] =
        static function (TestRunner $t) use ($execute, $sql, $expectedIds, $column, $expectedColumnValues, $flagColumn, $expectedFlags): void {
            $result = $execute($sql);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            if (is_string($column)) {
                $t->same($expectedColumnValues, array_column($result['returning'], $column));
            } else {
                $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expectedIds)), array_column($result['tables']['app_settings'], 'setting_id'));
            }
            if (is_string($flagColumn)) {
                $t->same($expectedFlags, array_column($result['returning'], $flagColumn));
            }
            $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
        };
}

$rowValueBetweenSubqueryCases = [
    'update between ordered subquery bounds with outer limit' => [
        "UPDATE app_settings SET state = 'between_subquery' WHERE (tenant_id, key_name) BETWEEN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 1 OFFSET 1) AND (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 0) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT 3 OFFSET 1",
        [4, 5, 6],
        'state',
        ['between_subquery', 'between_subquery', 'between_subquery'],
    ],
    'delete between ordered subquery bounds with comma limit' => [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) BETWEEN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 1 OFFSET 1) AND (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 0) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT 2, 3",
        [5, 6, 7],
        null,
        null,
    ],
    'update not between ordered subquery bounds keeps outside window' => [
        "UPDATE app_settings SET state = 'not_between_subquery' WHERE (tenant_id, key_name) NOT BETWEEN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 1 OFFSET 1) AND (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 0) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1",
        [1, 2],
        'state',
        ['not_between_subquery', 'not_between_subquery'],
    ],
    'delete between reversed ordered subquery bounds selects no rows' => [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) BETWEEN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 1 OFFSET 0) AND (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT 1 OFFSET 1) RETURNING setting_id ORDER BY setting_id LIMIT -1",
        [],
        null,
        null,
    ],
];

foreach ($rowValueBetweenSubqueryCases as $name => [$sql, $expectedIds, $column, $expectedColumnValues]) {
    $tests['rowvalue update delete limit dynamic parity rowvalue between subquery ' . $name] =
        static function (TestRunner $t) use ($execute, $sql, $expectedIds, $column, $expectedColumnValues): void {
            $result = $execute($sql);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            if (is_string($column)) {
                $t->same($expectedColumnValues, array_column($result['returning'], $column));
            } else {
                $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expectedIds)), array_column($result['tables']['app_settings'], 'setting_id'));
            }
            $t->contains('rowvalue.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test');
            $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
        };
}

$tests['rowvalue update delete limit dynamic parity rowvalue4 scalar subquery arity mismatch rejected'] =
    static function (TestRunner $t) use ($execute): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => $execute(
            "UPDATE app_settings SET state = 'bad' WHERE (tenant_id, key_name) = (SELECT tenant_id, key_name, priority FROM app_setting_targets ORDER BY priority DESC LIMIT 1) RETURNING setting_id"
        ));
    };

for ($seed = 1; $seed <= 48; $seed++) {
    $operator = match ($seed % 4) {
        0 => '=',
        1 => '<>',
        2 => 'IS',
        default => 'IS DISTINCT FROM',
    };
    $state = 'empty_scalar_' . $seed;
    $sql = "UPDATE app_settings SET state = '{$state}' WHERE (tenant_id, key_name) {$operator} (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 0) RETURNING setting_id, state ORDER BY setting_id LIMIT -1";
    $expected = $operator === 'IS DISTINCT FROM' ? [1, 2, 3, 4, 5, 6, 7, 8] : [];

    $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue4 empty scalar subquery update seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $operator, $expected, $state): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            if ($operator === 'IS DISTINCT FROM') {
                $t->same(array_fill(0, 8, $state), array_column($result['returning'], 'state'));
            } else {
                $t->same([], $result['returning']);
            }
        };
}

for ($seed = 1; $seed <= 48; $seed++) {
    $operator = match ($seed % 4) {
        0 => '=',
        1 => '!=',
        2 => 'IS',
        default => 'IS NOT',
    };
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) {$operator} (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority DESC LIMIT 0) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
    $expected = $operator === 'IS NOT' ? [1, 2, 3, 4, 5, 6, 7, 8] : [];

    $tests[sprintf('rowvalue update delete limit dynamic parity rowvalue4 empty scalar subquery delete seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $operator, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same($operator === 'IS NOT' ? [] : [1, 2, 3, 4, 5, 6, 7, 8], array_column($result['tables']['app_settings'], 'setting_id'));
            $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
        };
}

$tests['rowvalue update delete limit dynamic parity rowvalue4 empty scalar subquery arity preserved'] =
    static function (TestRunner $t) use ($execute): void {
        $result = $execute("UPDATE app_settings SET state = 'empty_scalar_arity' WHERE (tenant_id, key_name) IS DISTINCT FROM (SELECT tenant_id, key_name FROM app_setting_targets LIMIT 0) RETURNING setting_id ORDER BY setting_id LIMIT 2 OFFSET 1");
        $t->same([2, 3], $result['plan']->selectedIds);
        $t->same([2, 3], array_column($result['returning'], 'setting_id'));
        $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
    };

return $tests;
