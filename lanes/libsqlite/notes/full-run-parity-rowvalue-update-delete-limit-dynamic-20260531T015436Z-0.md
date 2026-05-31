# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T015436Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts `length(...)` in UPDATE/DELETE
  `LIMIT` and `OFFSET` expressions, matching SQLite's general expression rule
  for UPDATE/DELETE LIMIT when the result is integer-valued.
- Focused coverage exercises outer UPDATE and DELETE ordered windows,
  comma-form LIMITs, row-value `IN (SELECT ...)` tuple sources, ordered
  subquery windows, and NULL rejection for `length(NULL)`.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
  sections `e_update-3.1` through `e_update-3.5` for UPDATE ORDER BY/LIMIT
  behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
  sections around `e_delete-3.*` for DELETE ORDER BY/LIMIT behavior and
  integer-valued LIMIT expression requirements.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` now reports `2957`
  assertions and `986` focused PASS lines.
- Compared with the lane's recorded targeted rowvalue baseline of `2693`
  assertions and `902` PASS lines, this adds `264` assertions and `84` PASS
  lines.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 2957 assertions, 0 failures`
  - `986` focused PASS lines

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator,
  expression evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with `length(...)` LIMIT/OFFSET expression semantics only. It does not repeat
  the accepted negative offset, arithmetic, quoted integral, unary,
  parenthesized negative, ORDER BY `length(...)`, cast, exponent/hex/boolean,
  CASE, coalesce/nullif/min/max, ordinal subquery, grouped SELECT, JSON table,
  WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
