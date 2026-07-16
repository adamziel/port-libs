# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T042005Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates constant `iif()` and `if()`
  LIMIT/OFFSET scalar expressions.
- Shared SQLite truth conversion now accepts numeric prefixes in text values,
  matching `e_expr.test` truthiness cases such as text beginning with `1`.
- Dynamic UPDATE windows and row-value DELETE tuple-source subqueries cover
  true, false, NULL, numeric, and text-prefix condition operands while
  preserving source-order RETURNING rows over generic `app_settings` fixtures.
- Malformed `iif()`/`if()` arity, NULL selected branches, and nonintegral
  selected branches remain rejected by the integer LIMIT/OFFSET gate.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  `e_expr-10.*` and truthiness/iif cases around lines 1240 and 1964-1998.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression coercion and datatype mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- Baseline focused file before this slice:
  `1 test files, 7699 assertions, 0 failures`.
- After this slice:
  `1 test files, 8039 assertions, 0 failures`.
- Added 100 focused TestRunner PASS cases / 340 assertions.
- Mapped upstream denominator coverage is unchanged at `1589 / 1589`; this is
  already-mapped PHP behavior growth.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 8039 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-source evaluator,
  LIMIT/OFFSET selection plan, and scalar truth conversion.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with `iif()`/`if()` LIMIT/OFFSET scalar behavior and numeric-prefix text
  truthiness only. It does not repeat accepted arithmetic, cast, predicate,
  scalar SELECT, NULL ordering, Unicode length, concat, round/sign, trim/substr,
  instr/replace/char/unicode, DISTINCT/compound tuple-source, JSON, WAL/VFS,
  B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or suite-evidence
  surfaces.
