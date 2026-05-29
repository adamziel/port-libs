# SQL Planner Partial Expression Skip-Scan Current Source Predicate Fence

This consolidation pass keeps
`SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan` as the canonical
class and renames its numbered production entry/helper methods to stable
predicate-fence names. The direct test and WordPress smoke now use stable file
names and call `materializeCurrentPredicateFence()`.

The planner behavior still reuses accepted expression skip-scan materialization, then records
prepared/current partial predicate signatures, rowid deltas, current rows
rejected by the changed predicate, and a cursor-tape predicate recheck opcode.
This prevents a prepared `WHERE kind = 'plugin'` partial expression index from
being reused after current schema tightens it to `WHERE kind = 'plugin' AND
option_name IS NOT NULL`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialExpressionSkipScanCurrentSourcePredicateFenceTest.php
1 test files, 63 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-planner-partial-expression-skipscan-current-source-predicate-fence.php
```

Non-overlap: avoids accepted next129 expression-key materialization, next132
expression covering, next137 STAT4 stale-source deltas, expression ORDER BY,
expression-index range-cost ranking, and ordinary non-skip-scan partial range
covering. The new behavior is the partial-predicate current-source fence for a
partial expression skip-scan cursor.

Observable metadata note: the accepted status value, dependency marker, fixture
index names, and non-overlap wording still preserve their historical `next139`
tokens because dependent assertions treat those as behavior metadata. No
numbered production helper symbol remains in this family.

Dependency closure: no new support component is needed. The patch reuses native
PHP expression skip-scan materialization, partial predicate proof, STAT4 sample
metadata, and current-source planner fences.
