# full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T224146Z-0

Slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T224146Z-0`

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`

Behavior implemented:
- Extended `SQLiteUpdateDeleteReturningSql` LIMIT parsing for UPDATE/DELETE RETURNING and row-value IN subqueries to accept constant integer expressions used by upstream SQLite update/delete-limit tests.
- Covered `5-1 OFFSET 2+1`, comma-form `1+1, 16/4`, quoted integral strings such as `'4'` and `'1.0'`, negative arithmetic such as `5*-1`, and malformed non-integral/NULL limits.

Upstream source cited:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelimit.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`

Focused coverage:
- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` now passes with 326 assertions.
- Focused PASS-line movement in this test file is +14 cases over the prior 117-case dynamic parity file.

Non-overlap:
- This slice does not repeat accepted comma-form LIMIT, top-level negative-offset clamp, row-value RETURNING savepoint/window metadata, SELECT expression ORDER BY, JSON table, WAL/VFS, B-tree, suite-evidence, or source-neutral cleanup surfaces.
- The change is limited to the shared UPDATE/DELETE RETURNING LIMIT parser and directly coupled dynamic row-value update/delete-limit tests.

Dependency closure:
- No new support component is needed. This reuses the existing lane-local SQL expression parsing, row-value IN subquery execution, and UPDATE/DELETE LIMIT selection helpers.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` -> 1 test files, 326 assertions, 0 failures.
