# VDBE affinity/collation sorter current-source next108

## Behavior

Adds `SQLiteVdbeAffinityCollationSorterSourcePlan`, a bounded current-source
comparison helper for VDBE sorter output. It compares current and next sorted
row streams using the existing VDBE affinity, collation, descending, and
explicit NULL-placement comparator, then reports ordered row ids, inserted and
deleted ids, moved ids, stable-tie ids, per-row trace summaries, and support
dependencies.

The focused Application smoke models copied `wp_options` sort output where a
changed option priority, an inserted plugin option, and a deleted cache option
must invalidate/re-yield the sorter stream under NOCASE/RTRIM collations,
numeric affinity, descending site-id tie-breaks, and NULLS LAST behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAffinityCollationSorterCurrentSourceNext108Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 61 assertions, 0 failures`
  - 61 focused PASS lines.
- `php lanes/libsqlite/examples/application-vdbe-affinity-collation-sorter-current-source-next108.php`
  - emits valid JSON with current/next sorter order, inserted/deleted/moved ids,
    stable ties, changed flag, and dependency tags.

## Non-overlap

This slice does not repeat accepted VDBE DISTINCT/collation sorter behavior,
aggregate ORDER BY, window sorter frames, Unicode GLOB ranges, UTF-16 guards,
JSON table sources/constraints, VFS writer/lock/sync work, B-tree overflow/
page-move/root-collapse work, or WAL checkpoint/savepoint byte truncation. It
is limited to current-vs-next VDBE sorter source yield diagnostics over
affinity/collation-sorted streams.

## Dependency Closure

No new support component is required. The implementation reuses existing native
PHP `SQLiteVdbeSorterYieldCursor`, `SQLiteVdbeSortCompare`, and
`SQLiteAffinityComparison` behavior.
