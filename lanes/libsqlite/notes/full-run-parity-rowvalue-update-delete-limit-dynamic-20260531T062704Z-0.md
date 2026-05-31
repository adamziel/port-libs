# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T062704Z-0`

Base accepted HEAD: `68a3731675769814ce7d56857d9182ac7f8b3613`

Upstream sources cited by the focused parity file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue7.test`

Behavior added:

- `SQLiteUpdateDeleteReturningSql` now evaluates SQLite `octet_length()` and
  `hex()` constant expressions in UPDATE/DELETE LIMIT and OFFSET clauses.
- The dynamic row-value parity test now covers outer UPDATE windows using
  `octet_length()` and row-value DELETE subquery windows combining `hex()` and
  `octet_length()`, including malformed arity, NULL, and non-integer hex text
  rejection.

Focused evidence:

- Before this slice, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php` passed `2 test files, 11349 assertions, 0 failures`.
- After this slice, the same command passed `2 test files, 11685 assertions, 0 failures`.
- Focused assertion movement is `+336`.

Dependency closure:

- No new support component is needed. This extends the existing native PHP
  constant-expression evaluator used by the row-value UPDATE/DELETE LIMIT
  executor.

Non-overlap:

- This does not add generated numbered helper classes, WordPress-specific APIs,
  metadata-only rows, or duplicate accepted dynamic LIMIT arithmetic, CAST,
  CASE, min/max, length, printf/format, quote/typeof, unicode, or row-value
  scalar-subquery coverage. It adds a distinct upstream `func.test` byte/hex
  scalar-function family.
