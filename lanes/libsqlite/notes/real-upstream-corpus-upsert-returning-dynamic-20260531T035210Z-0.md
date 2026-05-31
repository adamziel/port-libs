# Real upstream RETURNING writable schema dynamic

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T035210Z-0`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Sections:
  - `returning1-21.0`: writable `sqlite_schema` `DEFAULT VALUES RETURNING sqlite_schema.name`
  - `returning1-21.1`: writable `sqlite_temp_schema` `DEFAULT VALUES RETURNING sqlite_temp_schema.name`
  - `returning1-22.1`: temp-schema `RETURNING` subquery preserves `sqlite_master` alias name-resolution error

## Change

- Added `SQLiteReturningWritableSchemaPlan`, a bounded generic model for schema-table `DEFAULT VALUES RETURNING` row images and the temp-schema subquery alias error.
- Added `SQLiteRealUpstreamReturningWritableSchemaDynamicTest.php` with `857` focused TestRunner PASS cases and `3163` focused assertions.

## Non-overlap

This does not repeat existing UPSERT arm ordering, conflict-target, alias/default, trigger old-value, correlated-delete, broad `returning1-20.*`, JSON, B-tree, WAL/VFS, PRAGMA catalog, or metadata-only runner slices. Existing notes identified `returning1-21.*` and `returning1-22.*` as remaining writable-schema/name-resolution RETURNING coverage.

## Dependency closure

No new support component is needed. The slice reuses the lane-local schema row shape and adds a bounded native PHP helper for the cited RETURNING behavior.

## Verification

Local focused verification:

- `php -l lanes/libsqlite/src/SQLiteReturningWritableSchemaPlan.php`
  - passed
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningWritableSchemaDynamicTest.php`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningWritableSchemaDynamicTest.php`
  - `1 test files, 3163 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed
