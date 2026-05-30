# Trigger Order Update/Delete Corpus Next8

- Session: `port-dev-sqlite-yield-trigord8`
- Base accepted HEAD: `94837175ee20d3f0848f865972c63a682044f7a9`
- Scope: bounded upstream-style UPDATE/DELETE trigger ordering for copied `wp_options` rows.

## Behavior Added

- Added `SQLiteUpdateDeleteTriggerOrderPlan` for focused UPDATE/DELETE trigger-order evidence.
- Added `SQLiteTriggerOrderUpdateDeleteCorpusTest.php` with 52 independent PASS cases covering:
  - UPDATE row visitation order with ORDER BY/LIMIT.
  - BEFORE UPDATE OLD/NEW value visibility.
  - UPDATE OF changed-column filtering.
  - same-timing trigger declaration order.
  - AFTER UPDATE WHEN filtering over NEW values.
  - DELETE row visitation order.
  - BEFORE/AFTER DELETE OLD-row visibility.
  - DELETE WHEN filtering and malformed-trigger guards.
- Added `application-trigger-order-update-delete.php` smoke for copied `wp_options` plugin-setting update and transient-delete audit behavior.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerOrderUpdateDeleteCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-trigger-order-update-delete.php
```

Passed and emitted update/delete audit JSON with remaining options.

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteTriggerOrderPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerOrderUpdateDeleteCorpusTest.php
php -l lanes/libsqlite/examples/application-trigger-order-update-delete.php
git diff --check -- lanes/libsqlite
```

All passed.

## Dashboard Delta

- `lane-status.json` `phpPass`: `2311 -> 2363` from the verified 52 PASS lines in the new focused test file.
- `benchmarkDenominator.mapped`: unchanged; this slice adds focused PHP corpus coverage without claiming a new upstream inventory row.

## Non-overlap

This does not repeat accepted INSERT trigger conflict inheritance, recursive INSERT trigger behavior, view/trigger DDL catalog parsing, UPDATE/DELETE RETURNING, UPDATE FROM, SELECT SQL execution, JSON table cursor/source/constraint work, WAL/VFS/B-tree accepted clusters, or batch5b ALTER trigger/view rewriting. The new behavior is the execution order and OLD/NEW audit semantics of UPDATE and DELETE triggers.

## Dependency Closure

No new support component is needed. The slice reuses native PHP row-array execution and trigger metadata conventions already present under `lanes/libsqlite`.
