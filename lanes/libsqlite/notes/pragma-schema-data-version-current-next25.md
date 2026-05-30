# PRAGMA schema/data version current-next25

## Scope

- Added `SQLitePragmaSchemaDataVersion` for bounded `PRAGMA schema_version`
  and `PRAGMA data_version` current/next behavior.
- `schema_version` reads and assignment update the schema cookie preview.
- `data_version` reads the current change counter, ignores assignment as a
  read-only compatibility no-op, and advances only through explicit
  commit-style bumps.
- Attached schema state is isolated, and `SQLitePragmaSnapshot` can seed the
  model from a parsed database header.

## Application smoke

- Added `application-pragma-schema-data-version-current-next25.php` to preview
  schema-cookie writes and external writer data-version bumps for copied
  Application databases without requiring ext/sqlite.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaDataVersionCurrentNext25Test.php`:
  `1 test files, 64 assertions, 0 failures` with 64 PASS lines.
- Additional syntax, smoke, and diff checks are recorded in the lane final
  handoff.

## Non-overlap

This slice avoids batch23 PRAGMA function/module/collation metadata and earlier
locking/journal/schema-catalog PRAGMA work. It targets the separate
`schema_version` / `data_version` current and next-row compatibility behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing database
header parser and `SQLitePragmaSnapshot`; the new bounded state object is
native PHP lane-local code.
