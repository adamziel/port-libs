# real-upstream-corpus-upsert-returning-dynamic-20260601T073407Z-0

Base accepted HEAD: `0e6b89c861545d2e8159ac2fd07a33034e44e234`.

## Scope

Ported real upstream `returning1.test` bare literal projection behavior for
UPDATE/DELETE RETURNING. `SQLiteUpdateDeleteReturningSql` now accepts a bare
SQL literal RETURNING term without `AS` and exposes it under the literal text,
matching upstream statements such as `RETURNING rowid, b, '|'`, while other
non-column expressions still require an alias.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-2.1`: `UPDATE ... RETURNING rowid, b, '|'` emits the bare
    literal expression for every changed row.
  - `returning1-3.1`: `DELETE ... RETURNING rowid, *, '|'` emits the bare
    literal expression for every deleted row.

## Focused Growth

- Added `SQLiteRealUpstreamReturningBareExpressionDynamicTest.php`.
- New focused TestRunner PASS cases: `1001`.
- New focused assertions: `16006`.
- `lane-status.json` `phpPass` moved from `5672951` to `5673952` for this
  isolated focused corpus growth.
- Non-overlap: this does not repeat prior UPSERT arm ordering, SELECT-source
  UPSERT, count_changes, trigger/FK, writable schema, temp trigger, column-name
  dequote, correlated DELETE, or virtual-table RETURNING slices. It owns only
  bare, unaliased literal RETURNING projections in the UPDATE/DELETE executor.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningBareExpressionDynamicTest.php`
  - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningBareExpressionDynamicTest.php`
  - `1 test files, 16006 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningRowValueCurrentSourceNext125Test.php`
  - `1 test files, 52 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`.
- `git diff --check -- lanes/libsqlite`
  - passed.

## Dependency Closure

No new support component is needed. This reuses
`SQLiteUpdateDeleteReturningSql` projection parsing and row-array UPDATE/DELETE
RETURNING execution.
