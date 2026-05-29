# SQL Planner Partial Expression Skip-Scan Current Source Next139

This slice adds `SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan`,
a bounded current-source planner fence for stale prepared partial expression
skip-scan plans when the partial index predicate itself changed in the current
schema.

The planner reuses accepted expression skip-scan materialization, then records
prepared/current partial predicate signatures, rowid deltas, current rows
rejected by the changed predicate, and a cursor-tape predicate recheck opcode.
This prevents a prepared `WHERE kind = 'plugin'` partial expression index from
being reused after current schema tightens it to `WHERE kind = 'plugin' AND
option_name IS NOT NULL`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialExpressionSkipScanCurrentSourceNext139Test.php
1 test files, 63 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-planner-partial-expression-skipscan-current-source-next139.php
```

Non-overlap: avoids accepted next129 expression-key materialization, next132
expression covering, next137 STAT4 stale-source deltas, expression ORDER BY,
expression-index range-cost ranking, and ordinary non-skip-scan partial range
covering. The new behavior is the partial-predicate current-source fence for a
partial expression skip-scan cursor.

Dependency closure: no new support component is needed. The patch reuses native
PHP expression skip-scan materialization, partial predicate proof, STAT4 sample
metadata, and current-source planner fences.
