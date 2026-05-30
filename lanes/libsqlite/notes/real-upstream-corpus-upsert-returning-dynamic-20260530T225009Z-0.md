# real-upstream-corpus-upsert-returning-dynamic-20260530T225009Z-0

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T225009Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-21.0` and `returning1-21.1`: writable `sqlite_schema` and `sqlite_temp_schema` `DEFAULT VALUES RETURNING schema.name`.
  - `returning1-22.1`: `RETURNING` subquery name-resolution rejection for `sqlite_master.name` when the name is only an inner alias.
  - `returning1-23.1` and `returning1-23.2`: recursive trigger inserts populate generated rows while the `RETURNING` stream exposes only the top-level statement row.
  - `returning1-24.1` through `returning1-24.3`: FTS5-style virtual table insertion continues to return the inserted virtual row after another connection has changed the ordinary schema.

Changed files:

- `lanes/libsqlite/tests/SQLiteRealUpstreamReturningSchemaVirtualDynamicTest.php`

Focused coverage:

- Adds `1081` focused assertions from real upstream `returning1.test` sections 21-24.
- The batch is intentionally separate from earlier accepted UPSERT/RETURNING coverage for `upsert4.test`, `upsert5.test`, `returning1-1.0` through `returning1-20.3`, row-value RETURNING, trigger/view RETURNING, recursive UPSERT, and UPDATE/DELETE RETURNING window/savepoint helpers.
- The tests use generic schema/application names only and do not add domain-specific libsqlite APIs.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningSchemaVirtualDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamReturningSchemaVirtualDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningSchemaVirtualDynamicTest.php`
  - `1 test files, 1081 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This batch models existing native PHP row-array/catalog/trigger/virtual-table RETURNING behavior and does not require a new support-library gate.

Integrator note:

- Root harness was not run from this isolated micro-slice.
- No example smoke was added; these are upstream engine semantics around internal schema tables, recursive trigger visibility, and virtual-table `RETURNING`, not a separate application workflow.
