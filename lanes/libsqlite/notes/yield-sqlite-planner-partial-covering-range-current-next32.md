# SQLite planner partial covering range current-next32

## Behavior

This slice extends `SQLiteMultiColumnRangePlan` so multicolumn range planning can use partial covering indexes when the current WHERE terms prove the partial predicate.

The bounded planner now reports:

- proved partial-index admission for point, IN-list, BETWEEN, and open-ended range constraints;
- covering status for requested output columns;
- the current range column used as the B-tree interval after equality-prefix columns;
- later range predicates preserved as residual filters instead of being treated as a second seek interval;
- cost preference for proved partial covering plans without hiding the full-index fallback.

## Focused evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialCoveringRangeCurrentNext32Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
40 PASS lines
1 test files, 61 assertions, 0 failures
```

Example smoke:

```bash
php lanes/libsqlite/examples/application-partial-covering-range-plan.php
```

## Non-overlap

This does not repeat accepted expression-index range-cost ranking, expression ORDER BY, partial-index WHERE implication planning, covering-index join planning, multicolumn range current-next25, JSON table planning, VFS/WAL/B-tree storage application, or Unicode GLOB behavior. The new surface is the combined partial + covering + current-range/next-residual planner decision for ordinary multicolumn indexes over copied `wp_options` predicates.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP `SQLiteCreateIndex`, `SQLiteIndexPredicate`, and `SQLiteMultiColumnRangePlan` parsing/planning helpers.
