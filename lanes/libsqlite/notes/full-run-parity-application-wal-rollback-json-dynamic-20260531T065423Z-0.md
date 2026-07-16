# full-run-parity-application-wal-rollback-json-dynamic-20260531T065423Z-0

Scope: app-WAL rollback JSON dynamic parity follow-up on accepted base
`598504695c988ec41a0063207004e700089f5af7`.

## Behavior

- Added `SQLiteJsonImportRollbackWalPlan::dynamicSuccessfulMaterializedWalScenarios()`.
- The new cluster covers direct successful JSON import batches that materialize
  current frames into WAL bytes without going through the retry-after-rollback
  path.
- Dynamic cases cover clean WAL headers and preexisting prefix frames, both 512
  and 1024 byte pages, JSON text and JSONB catalog rows, inserted audit rows,
  chained checksummed WAL frames, final commit-frame page counts, and the
  unapplied rollback preview retained by the savepoint stack.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 5180 assertions, 0 failures`

## Delta

- Adds 604 focused assertions to the existing app-WAL rollback JSON dynamic
  parity file.
- Mapped coverage remains `1589 / 1589`; this is behavior-backed PHP coverage,
  not a denominator change.

## Non-Overlap

This does not repeat accepted app-WAL rollback, preexisting rollback,
inserted-setting rollback, duplicate inserted-setting rollback, missing/partial
tail, frame/header checksum rejection, retry materialization, pager WAL
readonly SHM refresh, rollback-journal commit/apply, VFS writer/sync/lock,
JSON table source/cursor/constraint, B-tree page relocation/freeblock,
row-value dynamic parity, or source-neutral cleanup clusters. The new behavior
is direct success-path WAL frame materialization for application JSON imports.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON
mutation, JSONB encoding, savepoint image/WAL bookkeeping, WAL checksum, and
app-WAL byte materialization primitives.
