# SQLite planner STAT4 skip-scan partial covering current/next50

2026-05-27 isolated slice `yield-sqlite-planner-stat4-skipscan-partial-covering-current-next50`.

Behavior covered:

- Extends `SQLiteSkipScanCoveringStat4Plan` so STAT4 skip-scan samples can carry tuple skipped-prefix values, matching multi-column skip-scan loops such as `(blog_scope, autoload)` before a constrained `status, option_name` suffix.
- Adds stable current/next STAT4 sample keys for tuple prefixes instead of PHP array-to-string warnings.
- Marks proved partial-index plans with `partialPredicateImplied` and includes `PARTIAL` in the query-plan detail string.
- Preserves covering-index decisions over proved partial indexes so copied `wp_options` option-value reads avoid table lookup while non-covering alternatives still report deferred lookup columns.
- Covers partial WHERE proof, missing proof rejection, bounded range/BETWEEN estimates, no-STAT4 fallback estimates, malformed STAT4 sample guards, tuple-prefix loop estimates, and deterministic ranking.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4SkipScanPartialCoveringCurrentNext50Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 73 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-planner-stat4-skipscan-partial-covering-current-next50.php
{
    "scenario": "application-planner-stat4-skipscan-partial-covering-current-next50",
    "selectedIndex": "idx_blog_autoload_status_name_value_partial_stat4",
    "usesSkipScan": true,
    "skippedColumns": [
        "blog_scope",
        "autoload"
    ],
    "partialPredicateImplied": true,
    "covering": true,
    "estimatedRows": 72
}
```

Expected dashboard movement: `phpPass` +73, from 18565 to 18638, with `phpFail` unchanged at 0. `benchmarkDenominator.mapped` is unchanged because this adds focused PHP planner behavior over existing upstream planner inventory rather than a newly hydrated upstream Tcl unit.

Non-overlap: this avoids accepted batch48 STAT4 skip-scan covering estimates by narrowing to tuple skipped-prefix STAT4 samples combined with proved partial covering indexes. It also avoids accepted partial-index WHERE implication, STAT4 partial-covering ORDER, expression-index range-cost, expression ORDER BY, SELECT SQL text, JSON table, WAL/VFS, and B-tree clusters.

Dependency closure: no new support component is needed. This reuses existing native PHP CREATE INDEX parsing, partial predicate proof, multicolumn range/skip-scan planning, and STAT4 skip-scan covering planner surfaces.
