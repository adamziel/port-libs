# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T223522Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for SQLite
connection-status scalar functions.

Source truth and non-overlap:
- Upstream anchors:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/laststmtchanges.test`
  for `changes()` / `total_changes()` statement-counter behavior,
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/lastinsert.test` for
  `last_insert_rowid()`, `limit.test` for LIMIT/OFFSET expression admission,
  and `rowvalue4.test` for row-value tuple-source LIMIT selection.
- This does not repeat accepted arithmetic, bind parameter, CAST, collation,
  current time, DISTINCT, EXISTS, JSON mutation, LIKE/GLOB, random/blob,
  timediff, unistr, aggregate-tuple, or ordered row-value LIMIT clusters.

Red-first evidence:
- Before the source change,
  `SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT changes()')`
  failed with `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions
  must evaluate to an integer`.
- Fresh SQLite oracle check: `SELECT changes(), total_changes(),
  last_insert_rowid(), changes()+2, total_changes()+1,
  last_insert_rowid()+3;` returns `0|0|0|2|1|3`.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now admits `changes()`, `total_changes()`,
  and `last_insert_rowid()` in dynamic LIMIT/OFFSET expressions.
- The bounded row-array executor has no mutable connection state parameter, so
  the evaluator uses `SQLiteConnectionCounters::initial()` and preserves
  upstream fresh-connection zero defaults plus no-argument arity checks.

Focused test movement:
- Added 24 UPDATE cases where outer LIMIT/OFFSET expressions combine the three
  connection-status scalar functions with arithmetic and predicates.
- Added 24 DELETE cases where row-value tuple subquery comma-form LIMIT/OFFSET
  expressions use the same connection-status functions.
- Added 10 parser and arity guard cases.
- Added generic application example
  `application-rowvalue-limit-connection-status.php` with `--self-test`.
- PASS-line delta: +59 focused TestRunner PASS cases in
  `SQLiteRowValueUpdateDeleteLimitConnectionStatusDynamicTest.php`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitConnectionStatusDynamicTest.php`
  passed.
- `php -l lanes/libsqlite/examples/application-rowvalue-limit-connection-status.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitConnectionStatusDynamicTest.php`
  passed: `1 test files, 303 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-rowvalue-limit-connection-status.php --self-test`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimit*DynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `16 test files, 4167 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed. This reuses the existing
  `SQLiteConnectionCounters` support component and the existing row-value
  UPDATE/DELETE LIMIT evaluator.
