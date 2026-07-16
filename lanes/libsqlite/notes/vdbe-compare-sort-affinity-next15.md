# VDBE compare/sort affinity next15

## Scope

- Adds `SQLiteVdbeSortCompare` for VDBE-style record/key comparison where an affinity string is applied slot-by-slot before storage-class comparison.
- Covers affinity codes `A/B` none, `C` numeric, `D` integer, `E/F` real, and `G` text, plus per-slot `BINARY`, `NOCASE`, and `RTRIM` collations.
- Adds stable row-array sorting over copied `wp_options`-style rows with numeric priority coercion, BLOB priority bytes, DESC reversal, NULL ordering, and stable ties.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeCompareSortAffinityNext15Test.php
Focused test run: 1 selected test files (root lock skipped)
58 PASS lines, 58 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-vdbe-sort-affinity.php
orderedOptionIds: [4, 3, 1, 5, 2]
```

## Status Delta

- `phpPass`: `4362 -> 4420` from the verified 58 PASS-line focused test delta.
- `benchmarkDenominator.mapped`: unchanged; this is focused behavior coverage, not a new upstream inventory unit.
- Dependency closure: no new support component needed; this reuses existing scalar comparison, BLOB value, and row-array helpers.

## Non-overlap

This avoids accepted VFS file-writer/lock/sync, WAL byte truncation/checkpoint/rollback, JSON table cursor/source/constraint, B-tree page move/root collapse/overflow freelist, Unicode GLOB, expression ORDER BY, SELECT SQL subquery/grouped/comma-LIMIT, and rollback-journal commit clusters. The slice targets the named remaining VDBE compare/sort affinity current-source gap.
