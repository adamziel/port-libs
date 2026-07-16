# Full-run parity row-value update/delete LIMIT dynamic 20260531T112628Z-0

Status: pending lane handoff on launcher base `729105b48b26aa61ef0db4b008592ded7b7410d2`.

Behavior:
- Added SQLite introspection scalar functions to `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET expression evaluation: `sqlite_version()`, `sqlite_source_id()`, `sqlite_compileoption_get()`, and `sqlite_compileoption_used()`.
- Added focused row-value UPDATE/DELETE parity coverage for those expressions in top-level DML LIMIT/OFFSET and row-value subquery LIMIT/OFFSET selection.
- Preserves SQLite LIMIT integer coercion: direct nonintegral text/NULL returns from introspection functions are rejected, while numeric compile-option checks or numeric wrappers such as `length()`, `substr()`, and `instr()` are accepted.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ctime.test`
  - `ctime-1.4.*`: `sqlite_compileoption_used()` handles `SQLITE_` prefixes and bare option names.
  - `ctime-2.1.*` / `ctime-2.2.*`: compile-option function arity and NULL/empty/out-of-range behavior.
  - `ctime-2.3` / `ctime-2.5.*`: `sqlite_compileoption_get()` values round-trip through `sqlite_compileoption_used()`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test`
  - `func-11.1`: built-in `sqlite_version()` function behavior.

Red-first probe before the source change:
- `length(sqlite_version())` -> `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer`
- `sqlite_compileoption_used('ENABLE_FTS5')` -> same rejection
- `length(sqlite_compileoption_get(14))` -> same rejection
- `length(sqlite_source_id())` -> same rejection

Focused evidence:
- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 16852 assertions, 0 failures`
  - `4432` PASS lines
- After: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 17530 assertions, 0 failures`
  - `4546` PASS lines
- Delta: `+114` PASS lines and `+678` focused assertions.

Expected dashboard movement:
- `phpPass`: `2893069 -> 2893183` if the integrator admits the lane-status delta.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior-depth growth over already mapped upstream row-value/update/delete-limit inventory.

Non-overlap:
- Does not repeat row-value concat/concat_ws LIMIT/OFFSET, LIKE/GLOB predicates, date/time scalars, func7 math scalars, string/blob scalar wrappers, PRAGMA virtual-selects, VFS win32 URI/open, WAL rollback JSON, or atof2 formatting slices.

Dependency closure:
- No new support component is needed. The change reuses the existing generic `SQLiteCoreScalarFunction` introspection implementation and extends only the row-value UPDATE/DELETE LIMIT expression dispatcher.
