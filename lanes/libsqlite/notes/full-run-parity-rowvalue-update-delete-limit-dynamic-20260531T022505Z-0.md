# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T022505Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates top-level LIMIT/OFFSET string
  concatenation expressions before literal parsing and bitwise `|` parsing.
- This fixes SQLite parity for expressions such as `LIMIT '0' || '2'` and
  `OFFSET '0' || '1'`, where the concatenated numeric text is then accepted
  only if it losslessly evaluates to an integer.
- NULL, BLOB, nonintegral, and malformed concatenation LIMIT/OFFSET
  expressions remain rejected as datatype or expression errors.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` now passes with 4550
  assertions.
- Adds 100 focused TestRunner PASS cases over the current accepted row-value
  UPDATE/DELETE LIMIT dynamic parity file:
  - 48 UPDATE ordered-window concatenated LIMIT/OFFSET cases.
  - 48 DELETE row-value subquery concatenated LIMIT/OFFSET cases.
  - 4 malformed concatenation guard cases.

Verification:

- Red-first focused check before the precedence fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 4214 assertions, 96 failures`
- Focused passing check after the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 4550 assertions, 0 failures`
- Additional required checks are recorded in the final handoff.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  only with concatenated LIMIT/OFFSET expression behavior. It does not repeat
  prior negative offset, arithmetic, quoted integral, unary plus,
  parenthesized unary negative, computed ORDER BY length, INTEGER/REAL/NUMERIC
  casts, scalar functions, scalar SELECTs, predicate LIMITs, NULLS ordering,
  grouped SELECT, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, or metadata-only suite evidence surfaces.
