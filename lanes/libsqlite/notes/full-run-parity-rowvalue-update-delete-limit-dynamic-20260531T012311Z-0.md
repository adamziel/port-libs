# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T012311Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now treats integer `ORDER BY` terms inside
  row-value `IN (SELECT ...)` subqueries as SELECT result-column ordinals.
- Ordinal ordering is applied before LIMIT/OFFSET and before the tuple match
  decides which UPDATE/DELETE rows qualify.
- Out-of-range ordinal terms are rejected instead of being silently treated as
  constants.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  ordered LIMIT/OFFSET selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 656 to 738
  focused TestRunner PASS cases.
- Assertion count grows from 1903 to 2129, a +226 assertion delta.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 2129 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard file is absent in this worktree
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple subquery evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This slice extends the existing row-value/update-delete-limit dynamic parity
  file with SELECT-subquery `ORDER BY` ordinal behavior only. It does not
  repeat prior negative offset, scalar function, cast, boolean, CASE,
  arithmetic LIMIT/OFFSET, grouped SELECT, JSON table, WAL/VFS, B-tree,
  PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite evidence
  surfaces.
