# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T022014Z-0

## Behavior

- Fixed `SQLiteUpdateDeleteReturningSql` `length()` evaluation in UPDATE/DELETE
  LIMIT and row-value subquery ORDER BY expressions to count UTF-8 text
  characters instead of bytes, matching SQLite text `length()` behavior.
- Added row-value UPDATE/DELETE LIMIT dynamic parity coverage for Unicode
  `length()` in outer LIMIT/OFFSET expressions, row-value IN subquery
  LIMIT/OFFSET expressions, and row-value subquery ORDER BY expressions.

## Evidence

- Before the fix, parsing
  `DELETE FROM app_settings RETURNING setting_id LIMIT length('éé') OFFSET length('é')`
  produced `limit=4` and `offset=2`; after the fix it produces SQLite-parity
  character counts.
- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- Result: `1 test files, 4692 assertions, 0 failures`.
- Focused PASS-line movement in this test file is +146 cases over the prior
  1363-case dynamic parity file, for `1509` PASS lines.

## Non-Overlap

This extends the current row-value/update-delete-limit dynamic parity file with
Unicode character-count `length()` behavior only. It does not repeat accepted
negative offset, arithmetic, quoted integral, unary plus, cast, exponent,
hexadecimal, boolean literal, bitwise, CASE, scalar-function, ordinal, NULLS
placement, grouped SELECT, scalar SELECT, predicate-valued LIMIT/OFFSET, JSON
table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
metadata-only suite evidence surfaces.

## Dependency Closure

No new support component is needed. The fix uses a local UTF-8 text-unit helper
inside the existing UPDATE/DELETE RETURNING parser/executor path, with a byte
fallback for invalid text.
