# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T040612Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates constant `unicode()` LIMIT
  and OFFSET expressions, matching SQLite's first-codepoint scalar behavior
  for ASCII, two-byte, three-byte, and four-byte UTF-8 text.
- Focused dynamic cases cover outer UPDATE windows and row-value DELETE
  `IN (SELECT ...)` tuple subqueries where `unicode(char(...))` or
  `unicode(text)-constant` determines the LIMIT/OFFSET before mutation.
- Malformed arity and empty/NULL codepoint results remain rejected by the
  existing integer LIMIT/OFFSET gate.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test`
  `func-30.*` for `unicode(X)` first-character codepoint behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression coercion and datatype mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- Baseline focused file before this slice:
  `1 test files, 7356 assertions, 0 failures`.
- After this slice:
  `1 test files, 7699 assertions, 0 failures`.
- Added 103 focused TestRunner PASS cases / 343 assertions.
- Mapped upstream denominator coverage is unchanged at `1589 / 1589`; this is
  already-mapped PHP behavior growth.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 7699 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with constant `unicode()` LIMIT/OFFSET scalar behavior only. It does not
  repeat accepted arithmetic, cast, predicate, NULL ordering, Unicode length,
  concat, round/sign, text trim/substr, string-position, character generation,
  DISTINCT/compound tuple-source, JSON, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, or suite-evidence surfaces.
