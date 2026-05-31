# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T034335Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates constant `instr()`,
  `replace()`, and `char()` expressions in UPDATE/DELETE `LIMIT` and
  `OFFSET` contexts when the result is losslessly coercible to an integer.
- Focused dynamic cases cover outer UPDATE windows, row-value DELETE
  `IN (SELECT ...)` tuple subqueries, character-code LIMIT windows, source
  order RETURNING, and malformed NULL/arity/non-integer scalar arguments.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression coercion and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- Baseline focused file before this slice:
  `1 test files, 6710 assertions, 0 failures`.
- After this slice:
  `1 test files, 7140 assertions, 0 failures`.
- Added 122 focused TestRunner PASS cases / 430 assertions.
- Mapped upstream denominator coverage is unchanged at `1589 / 1589`; this is
  already-mapped PHP behavior growth.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before: `1 test files, 6710 assertions, 0 failures`
  - after: `1 test files, 7140 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with constant string-position/replacement/character scalar LIMIT/OFFSET
  functions only. It does not repeat accepted arithmetic, cast, predicate,
  NULL ordering, Unicode length, concat, round/sign, text trim/substr,
  DISTINCT/compound tuple-source, JSON, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, or suite-evidence surfaces.
