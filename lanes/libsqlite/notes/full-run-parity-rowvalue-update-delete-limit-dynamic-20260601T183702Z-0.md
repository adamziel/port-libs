# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T183702Z-0

Lane: `libsqlite`
Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T183702Z-0`
Base accepted HEAD: `2898695b0aef4ffbe3958dc2b27b0878671c7500`

## Behavior

Added row-value SELECT RHS aggregate tuple parity for `UPDATE` / `DELETE`
selection and RETURNING expressions. `SQLiteUpdateDeleteReturningSql` now
recognizes simple row-value SELECT arms whose projection list is entirely
`min()`, `max()`, or `count()` aggregates and returns SQLite's single aggregate
result tuple before the existing DISTINCT, compound, and LIMIT tuple handling.

This fixes the previously red aggregate tuple case:

```text
InvalidArgumentException: SQLite UPDATE/DELETE literal is not supported: max(tenant_id)
```

## Upstream Anchors

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
  `rowvalue4-2.2.11` and `rowvalue4-2.2.12` cover row-value comparisons
  against `(SELECT max(...), max(...))` and `(SELECT max(...), min(...))`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
  and `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
  anchor UPDATE/DELETE execution surfaces.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  anchors ordered LIMIT/OFFSET execution windows used around the row-value
  predicates.

## Focused Coverage

Added `SQLiteRowValueUpdateDeleteLimitAggregateTupleDynamicTest.php` with 80
focused PASS cases / 485 assertions:

- 32 UPDATE cases using aggregate tuple RHS predicates with ordered LIMIT/OFFSET
  windows.
- 32 DELETE cases using aggregate tuple RHS predicates with ordered LIMIT/OFFSET
  windows.
- 4 compound row-value subquery cases using aggregate SELECT arms across
  `UNION ALL`, `UNION`, `INTERSECT`, and `EXCEPT`.
- 8 RETURNING expression cases covering equality, `IN`, `IS`, `IS NOT`,
  empty aggregate tuple NULL behavior, and compound aggregate RHS matching.
- 3 malformed guard cases for single-column aggregate tuple RHS and invalid
  aggregate arguments.

## Verification

Red repro before implementation:

```bash
php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql; $tables=["app_settings"=>[["setting_id"=>1,"tenant_id"=>1,"key_name"=>"alpha","state"=>"queued"],["setting_id"=>2,"tenant_id"=>2,"key_name"=>"beta","state"=>"queued"]],"app_setting_targets"=>[["tenant_id"=>2,"key_name"=>"beta"]]]; try { $r=SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET state = '\''done'\'' WHERE (tenant_id, key_name) = (SELECT max(tenant_id), max(key_name) FROM app_setting_targets) RETURNING setting_id, state", $tables, "setting_id", [["tenant_id","key_name"]]); var_export([$r["plan"]->selectedIds, $r["returning"]]); } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage().PHP_EOL; }'
```

Result:

```text
InvalidArgumentException: SQLite UPDATE/DELETE literal is not supported: max(tenant_id)
```

Focused tests after implementation:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitAggregateTupleDynamicTest.php
```

Result:

```text
1 test files, 485 assertions, 0 failures
```

Family/guard regression check:

```bash
php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLiteRowValueUpdateDeleteLimit*Test.php' -print | sort) lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
```

Result:

```text
16 test files, 25564 assertions, 0 failures
```

## Delta

- `phpPass`: `6186144 -> 6186224` (`+80`) from the new focused PASS cases.
- `phpFail`: unchanged at `16`.
- Mapped coverage: unchanged at `1589 / 1589`.
- Root harness: not run; this was an isolated micro-slice.

## Non-Overlap

This patch does not repeat previous dynamic LIMIT/OFFSET scalar expression
clusters for arithmetic, casts, CASE/iif, date/time, timediff, random,
randomblob, unhex, LIKE/GLOB, JSON/JSONB scalar or mutation functions,
COLLATE postfix, NULL-safe distinct predicates, BETWEEN precedence, comma-form
LIMIT parsing, bind parameters, source-neutral cleanup, or suite-evidence
metadata. It is limited to aggregate row-value SELECT tuple RHS handling in the
generic UPDATE/DELETE executor.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
row-value tuple subquery parser, expression evaluator, collation helpers,
compound SELECT tuple handling, and TestRunner harness.
