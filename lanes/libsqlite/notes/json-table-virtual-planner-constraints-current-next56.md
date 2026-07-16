# JSON Table Virtual Planner Constraints Current Next 56

2026-05-27 isolated slice `virtual-table-planner-constraints-current-next56`.

## Behavior

- Added cursor-facing `filterArguments` to `SQLiteJsonTablePlan::xBestIndexPlan()` so every advertised positive `argvIndex` has the value SQLite-style `xFilter` would receive.
- Added `filterCurrentNext` pairs ordered by `argvIndex`, separate from the existing original-constraint-order `currentNext` metadata.
- `LIMIT` / `OFFSET` planner metadata remains hidden with argv index `0`, residual predicates remain outside the cursor argument tape, and visible pushed constraints keep their existing residual-evaluation behavior.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableVirtualPlannerConstraintsCurrentNext56Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-json-table-virtual-planner-constraints-current-next56.php
```

This reports copied Application `wp_options` JSON diagnostics with `idxStr`, `filterArguments`, argv-ordered current/next columns, and residual operators without requiring `ext/sqlite`.

## Non-Overlap

This does not repeat accepted JSON table cursor iteration, parser-level JSON table SELECT source wiring, hidden/visible constraint extraction, visible-column constraint pushdown, host joins, JSON source/cursor tests, or malformed JSONB planner diagnostics. The new surface is the virtual-table planner bridge from advertised xBestIndex constraint usage to the cursor-facing xFilter argument tape and argv-ordered current/next constraint metadata.

## Dependency Closure

No new support component is needed. This reuses existing native PHP JSON table planner/cursor primitives and adds the missing planner metadata needed by future cursor integration.
