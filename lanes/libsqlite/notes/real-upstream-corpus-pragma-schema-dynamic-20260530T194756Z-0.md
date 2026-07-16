# Real Upstream Corpus: PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T194756Z-0`

Accepted base: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - `schema3-1.1` through `schema3-1.22`
  - stale schema-cache refresh after another connection creates tables, views, indexes, triggers, adds columns, and drops/recreates schema objects.

Behavior ported:

- Added focused dynamic corpus coverage for schema-cache refresh across the upstream `schema3.test` multiclient DDL matrix.
- The coverage uses generic `schema3_*` application tables and verifies that `SQLiteSchemaDdlReparsePlan` invalidates stale prepared schema scans and refreshes PRAGMA table metadata before the second connection executes SELECT, UPDATE, DELETE, INSERT, CREATE INDEX, CREATE TRIGGER, DROP, or recreate statements.

Focused coverage:

- `SQLiteRealUpstreamPragmaSchemaDynamicSchema3Test.php`
  - 46 dynamic variants over 22 upstream `schema3.test` cases.
  - Focused PASS cases: 1013.
  - Behavior assertions: 8467.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3Test.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3Test.php` -> `1 test files, 8467 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaInvalidationDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `3 test files, 14470 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` -> no whitespace errors

Expected dashboard movement:

- Count as PASS-line growth only.
- `phpPass`: `469812 -> 470825` (`+1013` focused PASS cases).
- Mapped coverage unchanged at `1472 / 1589`.

Dependency closure:

- No new support component is needed. The slice reuses lane-local schema DDL reparse, schema-record catalog, ALTER TABLE ADD COLUMN, and PRAGMA schema catalog primitives.

Non-overlap:

- This does not repeat prior `pragma.test` table-info/default/type coverage, `pragma3.test` data-version coverage, `pragma4.test` table-valued PRAGMA coverage, or the earlier `schema.test` rollback-expired same-cookie invalidation batch.
- The new surface is specifically upstream `schema3.test` multiclient stale schema-cache refresh across create, alter, drop, and recreate DDL.
