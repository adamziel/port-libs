# Application JSON Import WAL Savepoint Current Next35

## Scope

- Adds `SQLiteJsonImportWalSavepointPlan`, a bounded native PHP planner for Application `wp_options` JSON import payloads staged through SQLite savepoints while tracking the current WAL frame.
- Covers text JSON, JSON subtype, and JSONB payloads; JSON path extraction; released, open, rolled-back, and aborting savepoints; unique `option_name` conflicts; dirty page to WAL frame mapping; and option import modes.
- Adds a Application smoke example that imports plugin settings from JSON, rolls back a malformed plugin payload, and reports the retained WAL current frame.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonImportWalSavepointCurrentNext35Test.php
Focused test run: 1 selected test files (root lock skipped)
56 PASS lines
1 test files, 144 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +56, from 12271 to 12327, with no mapped upstream denominator change.

## Non-overlap

This slice does not repeat accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal commit, JSON table cursor/source/visible/hidden constraint work, or batch29 Application import transaction error-yield coverage. The new behavior is the JSON-to-`wp_options` savepoint import planner with current WAL frame accounting for released and rolled-back JSON payload batches.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP JSON extraction/validity/JSONB helpers, `SQLiteImportTransactionPlan`, and `SQLiteSavepointStack`.

## Next

- Wire the plan into broader pager/VFS transaction application if a later slice needs actual WAL byte writes for these JSON import batches.
- Add upstream-oracle evidence only if a selected SQLite Tcl JSON/savepoint/WAL subset becomes hydrated and runnable for this exact behavior.
