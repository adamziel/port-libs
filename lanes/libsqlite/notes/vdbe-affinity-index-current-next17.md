# VDBE affinity index current/next17

## Scope

- Adds `SQLiteVdbeIndexCursor`, a bounded row-array model for VDBE index cursor current-key reads, rowid yield, `Next` advancement, and prefix equality seeks.
- Reuses `SQLiteVdbeSortCompare` so index keys apply SQLite affinity strings and per-slot collations before current row selection.
- Covers copied `wp_options` index-like records with numeric priority affinity, `NOCASE` option-name suffixes, `RTRIM`, `NULL`, BLOB storage-rank behavior, descending scans, and malformed cursor/probe guards.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeIndexCurrentNext17Test.php
Focused test run: 1 selected test files (root lock skipped)
40 PASS lines, 45 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-vdbe-index-current-next.php
{
    "scenario": "application-vdbe-index-current-next",
    "autoloadRowids": [1, 2, 4, 10],
    "autoloadNames": ["Plugin_A", "plugin_a", "plugin_blob", "Plugin_Z"],
    "nextRowids": [8, 7],
    "applicationUse": "Preview copied wp_options option_name index scans through VDBE-like current-key reads and Next advancement, applying SQLite affinity/collation before yielding rowids without ext/sqlite."
}
```

## Status Delta

- `phpPass`: add the exact verified 40 PASS-line delta for `SQLiteVdbeIndexCurrentNext17Test.php`.
- `benchmarkDenominator.mapped`: unchanged; this is focused VDBE behavior coverage, not a newly mapped upstream inventory unit.
- Dependency closure: no new support component needed; this reuses existing PHP affinity comparison, BLOB value, and VDBE sort/compare helpers.

## Non-overlap

This avoids accepted SELECT SQL text/JOIN/GROUP BY/subquery/expression ORDER BY, JSON table cursor/source/hidden/visible constraints, VFS writer/lock/sync/rollback/super-journal, WAL byte truncation/checkpoint, B-tree page move/root collapse/interior merge/overflow release, Unicode GLOB, and previous VDBE compare/sort-only affinity coverage. The new behavior is current-row and `Next` index cursor yield after affinity-aware key ordering.
