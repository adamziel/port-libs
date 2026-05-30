# Attach Temp View Trigger Current Next17

This slice adds bounded native PHP resolution for triggers across attached
schema catalogs where temp, main, and attached databases contain shadowing
tables/views.

- New `SQLiteAttachTempViewTriggerResolution` resolves trigger records by
  SQLite search order, keeps non-temp triggers pinned to their current schema,
  lets temp triggers resolve unqualified targets through temp/main/attached
  order, and honors schema-qualified trigger targets such as `main.wp_options`.
- The focused test file adds 59 independent PASS cases covering temp trigger
  shadowing, main trigger current-schema pinning, attached trigger pinning,
  INSTEAD OF view-trigger column checks, pseudo-table references, body
  dependencies, unresolved column diagnostics, malformed triggers, and summary
  counts.
- The Application smoke previews copied `wp_options` temp staging, main
  `active_options`, and attached `site.active_options` triggers without
  requiring `ext/sqlite`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempViewTriggerCurrentNext17Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `5718 -> 5777` (+59 verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior
  coverage, not a new upstream inventory unit.

Non-overlap:

Avoids accepted attach/temp schema catalog PRAGMAs, generic view/trigger DDL,
view-trigger name resolution without attached schema state, JSON table source
and hidden/visible constraint clusters, SELECT SQL JOIN/GROUP/ORDER/subquery
clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint
byte truncation, B-tree page move/root-collapse/overflow release, and Unicode
GLOB work.

Dependency closure:

No new support component is needed. The slice reuses lane-local schema-record
and attached-schema catalog primitives.
