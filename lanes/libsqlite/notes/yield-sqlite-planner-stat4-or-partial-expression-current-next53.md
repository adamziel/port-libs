# yield-sqlite-planner-stat4-or-partial-expression-current-next53

## Status

Implemented a bounded planner extension for STAT4-backed OR clauses over
partial covering expression indexes. `SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan()`
now reports per-arm STAT4 current/next evidence, aggregate STAT4 evidence, and
an `or-to-in-partial-expression` rewrite when all OR arms are point lookups on
the same partial expression index. Duplicate point values reuse the minimum
arm estimate instead of inflating the OR estimate.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4OrPartialExpressionCurrentNext53Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures
```

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerOrPartialCoveringCurrentNext34Test.php lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionOrderCurrentNext49Test.php lanes/libsqlite/tests/SQLitePlannerExpressionIndexCoveringCurrentNext33Test.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 261 assertions, 0 failures
```

## Application Smoke

`examples/application-stat4-or-partial-expression-current-next53.php` exercises a
copied `wp_options` plugin-option OR predicate over a partial
`lower(option_name)` expression index. The expected smoke output reports the
IN-style rewrite, deduped plugin values, STAT4 usage, and a 12-row estimate for
`plugin_alpha OR plugin_beta OR plugin_alpha`.

## Non-overlap

This avoids accepted batch49 STAT4 partial-expression ORDER planning and the
accepted expression-index range-cost work. The new surface is OR-arm STAT4
current/next evidence plus same-index point OR to IN rewrite for partial
covering expression indexes.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
CREATE INDEX parser, partial-index predicate prover, and STAT4 estimate support.
