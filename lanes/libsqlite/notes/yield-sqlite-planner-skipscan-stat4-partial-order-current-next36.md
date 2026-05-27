# yield-sqlite-planner-skipscan-stat4-partial-order-current-next36

## Scope

Adds a bounded planner evidence helper for the SQLite skip-scan path where:

- the usable index is partial and the query terms must imply the partial-index WHERE predicate;
- the leading index column is skipped and each distinct prefix gets its own current/next loop;
- `sqlite_stat4`-style prefix/suffix samples estimate per-loop row counts; and
- `ORDER BY` on the suffix column is only partially satisfied across skip-scan loops, requiring right-part/block sort evidence.

This is intentionally separate from the accepted current-next28 skip-scan materialization slice and does not repeat row filtering, LIMIT/OFFSET, or partial predicate proof as the primary behavior.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSkipScanStat4PartialOrderCurrentNext36Test.php
```

Verified dashboard movement: `phpPass +52` from the new focused test cases. Mapped upstream denominator is unchanged because this is a focused bounded planner port rather than newly mapped upstream inventory.

## Dependency closure

No new support component is required. The slice reuses existing native PHP `SQLiteIndexPredicate` and `SQLiteIndexSkipScanPlan` primitives.

## Non-overlap

Avoids accepted batch23/28 surfaces: partial-index WHERE implication planning, existing skip-scan BETWEEN row materialization, expression-index range cost, SQL expression `ORDER BY`, and parser-level SELECT text execution. The new behavior is STAT4-informed skip-scan prefix costing plus partial ORDER BY current/next evidence.
