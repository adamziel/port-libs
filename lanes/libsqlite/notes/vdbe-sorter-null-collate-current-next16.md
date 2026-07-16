# VDBE sorter NULL/COLLATE current-next next16

## Scope

- Extends `SQLiteVdbeSortCompare` with explicit per-key `NULLS FIRST` / `NULLS LAST` placement that is not reversed by descending sort terms.
- Adds `SQLiteVdbeSorterCursor` so callers can iterate sorted row-array output with SQLite-style `current()` and `next()` cursor steps.
- Covers Application-shaped `wp_options` import ordering where `autoload COLLATE NOCASE`, numeric priorities, stable rowids, and NULL option metadata determine the next yielded row.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterNullCollateCurrentNext16Test.php
```

The focused run passed locally with 35 PASS lines and 44 assertions.

```text
php lanes/libsqlite/examples/application-vdbe-sorter-null-collate-current-next.php
```

The smoke printed ordered option IDs `[4,3,6,8,2,1,7,5]`.

## Status Delta

- `phpPass`: `5433 -> 5468` from the verified 35 PASS-line focused test delta.
- `benchmarkDenominator.mapped`: unchanged; this is focused behavior coverage, not a newly mapped upstream inventory unit.
- Dependency closure: no new support component needed; this reuses VDBE sort comparison, row-array sorting, scalar affinity comparison, and a lane-local cursor wrapper.

## Non-overlap

This avoids accepted SELECT SQL expression ORDER BY, GROUP BY/HAVING, subquery, JSON table source/cursor/constraint, VFS writer/lock/sync/rollback, WAL byte/checkpoint, B-tree page-move/root-collapse/overflow, Unicode GLOB, and prior VDBE affinity-only sort coverage. The slice targets the narrower remaining VDBE sorter NULL placement, collation, and `current()`/`next()` cursor-yield behavior.
