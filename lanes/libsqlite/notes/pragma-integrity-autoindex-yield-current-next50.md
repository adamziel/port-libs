# PRAGMA integrity autoindex yield current next50

This slice adds bounded native PHP current/next pagination for
`sqlite_autoindex_*` roots discovered from `sqlite_schema` while reusing the
existing `PRAGMA integrity_check` parser guard.

- Added `SQLitePragmaIntegrityAutoindexYield` to stream automatic UNIQUE/PRIMARY
  KEY index root diagnostics with the default current/next50 page size.
- Covered 55 copied `wp_options` autoindex rows, tail pagination, explicit-index
  exclusion, null-SQL non-autoindex exclusion, malformed root pages, pointer-map
  type/parent mismatches, invalid roots, and guardrails for offsets/limits and
  unsupported PRAGMA SQL.
- Added `application-pragma-integrity-autoindex-yield.php` to smoke copied
  Application autoindex integrity preflight without ext/sqlite.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexYieldCurrentNext50Test.php
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines, 64 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +57, from 17920 to 17977. Mapped upstream
coverage is unchanged because this is focused PHP behavior coverage over an
already mapped PRAGMA integrity surface.

Dependency closure: no new support component is needed. This reuses the
lane-local SQLite database reader, schema-record parser, pointer-map reader,
index page assembler/parser conventions, and existing integrity PRAGMA parser.

Non-overlap: this avoids accepted PRAGMA pointer-map deep integrity, PRAGMA
index/freelist child pointer-map diagnostics, foreign-key/integrity yield,
schema PRAGMA cursor metadata, B-tree page move/root-collapse/overflow release,
JSON table source/cursor/constraint work, VFS writer/lock/sync/rollback paths,
WAL checkpoint/savepoint byte truncation, SELECT SQL text/JOIN/GROUP/subquery/
ORDER/LIMIT clusters, and Unicode GLOB behavior. It only adds current/next50
streaming diagnostics for automatic index roots.
