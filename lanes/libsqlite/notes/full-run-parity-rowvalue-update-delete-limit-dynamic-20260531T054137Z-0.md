# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T054137Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Fixes LIMIT/OFFSET expression parity for SQLite truth predicates:
  `IS TRUE`, `IS FALSE`, `IS NOT TRUE`, and `IS NOT FALSE`.
- Preserves SQLite truthiness for non-1 true values such as `2 IS TRUE` and
  numeric-prefix text such as `'2abc' IS TRUE`.
- Preserves NULL truth-predicate behavior for `NULL IS NOT TRUE` and
  `NULL IS NOT FALSE`.
- Adds dynamic UPDATE windows and row-value DELETE subquery windows over
  generic `app_settings` fixtures only.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression evaluation and integer coercion behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` and
  `rowvalue4.test` for row-value tuple subquery selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from `10369`
  to `10723` focused assertions in this worktree.
- Adds `103` focused TestRunner PASS cases to the existing dynamic parity
  file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - after: `1 test files, 10723 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - passed
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - passed
- `git diff --check -- lanes/libsqlite`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard file is not present in this worktree

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, LIMIT expression evaluator, row-value
  tuple subquery evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  only for LIMIT/OFFSET `IS TRUE` / `IS FALSE` truth-predicate behavior. It
  does not repeat accepted scalar function LIMIT expression, row-value
  `BETWEEN`, explicit tuple-list/VALUES RHS, SELECT assignment, JSON table,
  app-WAL, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
