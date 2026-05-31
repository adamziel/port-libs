# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T102159Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Added dynamic LIMIT/OFFSET expression support for `LIKE`, `NOT LIKE`, `GLOB`,
  `NOT GLOB`, and `LIKE ... ESCAPE ...` predicates.
- Covered the expressions in outer UPDATE ORDER BY LIMIT windows and row-value
  DELETE tuple-source subqueries.
- Preserved SQLite row-selection behavior, source-order RETURNING rows,
  row-value tuple matching, and generic `app_settings` fixture names.
- Covered malformed NULL pattern, empty/wide LIKE ESCAPE, and invalid GLOB
  ESCAPE rejection.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` for
  boolean expression and NULL result behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/like.test` for
  LIKE/GLOB/ESCAPE semantics.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  row-value tuple behavior in the surrounding dynamic parity file.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 15558 to
  16206 assertions.
- The added behavior contributes 108 focused TestRunner PASS cases and 648
  focused assertions.

Red-first evidence:

- Before the predicate dispatch fix, the new LIKE/GLOB cases produced 104
  failures because quoted predicate expressions such as
  `'abcde' LIKE 'abc%'` were consumed as string literals before predicate
  parsing.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before edit: `1 test files, 15558 assertions, 0 failures`
  - after edit: `1 test files, 16206 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php`
  - after edit: `4 test files, 16910 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - after edit: `1 test files, 3 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-source evaluator,
  LIMIT/OFFSET constant-expression evaluator, and existing
  `SQLiteDatabase::likeMatches()` / `SQLiteDatabase::globMatches()` helpers.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with LIKE/GLOB predicate LIMIT/OFFSET expressions only. It does not repeat
  prior negative offset, arithmetic, casts, booleans, CASE, coalesce/nullif,
  ordinal tuple sources, NULLS placement, length/unicode/concat/round/sign,
  quote/typeof, octet_length/hex, func7 math, JSON table, WAL/VFS, B-tree,
  PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite evidence
  surfaces.
