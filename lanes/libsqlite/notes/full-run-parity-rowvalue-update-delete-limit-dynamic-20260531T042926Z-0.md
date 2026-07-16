# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T042926Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Adds upstream `rowvalue4.test` style ordered row-value `IN (SELECT ...)`
  tuple windows over ascending and descending tuple sources with explicit
  `LIMIT`/`OFFSET` boundaries.
- Adds upstream `rowvalue3.test` style NULL tuple-source behavior for
  `IN`/`NOT IN`, including UNKNOWN rows skipped by UPDATE/DELETE and concrete
  matches after an offset removes the NULL tuple.
- Keeps generic `app_settings` and `app_setting_targets` fixtures only.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
  cases `2.2.*.1` through `2.2.*.15` for ordered row-value tuple source
  windows and LIMIT/OFFSET boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue3.test`
  cases `3.*.1` through `3.*.4` for NULL-sensitive row-value `IN` truth.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelimit.test`
  for UPDATE/DELETE LIMIT selection semantics.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 7699 to
  7861 assertions.
- Adds 56 distinct TestRunner PASS cases and 162 focused assertions.
- Mapped upstream denominator coverage is unchanged; this is already-mapped
  PHP behavior growth over row-value and UPDATE/DELETE LIMIT parity.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 7861 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-source evaluator,
  LIMIT/OFFSET selection plan, and nullable row-value truth evaluator.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with ordered tuple-source windows and NULL tuple-source truth only. It does
  not repeat accepted DISTINCT/compound tuple sources, scalar LIMIT
  expressions, app-WAL, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, or metadata-only suite evidence surfaces.
