# Attach VACUUM temp schema current next36

This slice adds bounded native PHP planning for parser-level `VACUUM` statements against the current attached-schema catalog:

- Bare `VACUUM` and `VACUUM main` target the main database image.
- `VACUUM site` and quoted attached schema names target the selected attached database and use its isolated file name, page size, page count, and auto-vacuum header state.
- `VACUUM ... INTO ...` plans durable write/sync/directory-sync operations through the existing vacuum-into image planner.
- `VACUUM temp` is rejected because SQLite's connection-local temp schema is not vacuumed as an attached persistent database.
- VACUUM plans preserve the `temp` database-list entry and do not invalidate the attached-schema cache generation.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachVacuumTempSchemaCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-attach-vacuum-temp-schema-current-next36.php
schema=site source=/srv/wp-content/cache/site.sqlite target=/tmp/site-vacuum.sqlite page_size=4096 temp_preserved=yes cache_invalidated=no ops=write,sync,sync_directory,vacuum_rewrite
```

Status delta:

- `phpPass`: `12903 -> 12965` (`+62` verified PASS lines from the focused TestRunner file).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior over already mapped ATTACH/VACUUM/schema primitives, not a new hydrated upstream inventory unit.

Non-overlap:

This does not repeat accepted ATTACH/DETACH lifecycle execution, attach temp collation view resolution, schema-cache invalidation, auto-vacuum pointer-map application, VACUUM page-size/autovacuum header rewriting, VACUUM INTO file-image planning, VFS writer/sync/lock application, WAL checkpoint/savepoint behavior, B-tree page relocation/root-collapse/overflow release, JSON table planner work, Unicode GLOB, or SELECT SQL text clusters. The new behavior is the parser-level selection and rejection rules for VACUUM over main/attached/temp schemas while preserving current TEMP schema state.

Dependency closure:

No new support component is needed. The implementation reuses the lane-local attached schema catalog plus existing VACUUM rewrite and VACUUM INTO planners.
