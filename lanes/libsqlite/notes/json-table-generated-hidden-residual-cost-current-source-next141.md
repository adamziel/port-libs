# JSON table generated hidden residual cost current-source next141

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedHiddenResidualCostNext141()` for the current-source planner boundary where generated hidden constraints contain a mix of usable and unusable generated terms. Usable terms drive the generated hidden cost estimate; unusable generated terms are retained as residual predicates with explicit residual value tape, residual penalty, effective cost, and replan reasons.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenResidualCostCurrentSourceNext141Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 48 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-generated-hidden-residual-cost-current-source-next141.php --self-test
application-json-table-generated-hidden-residual-cost-current-source-next141 self-test passed
```

Non-overlap: this builds on accepted generated hidden cost behavior (`next136`) but does not repeat generated hidden filtering, JSON table hidden/visible constraint extraction, path generated ordering (`next137`), JSON table cursor/source wiring, or host/dynamic joins. The new behavior is specifically residual costing and value-tape tracking for unusable generated hidden predicates.

Dependency closure: no new support component is needed; this reuses native JSON table current-source planning, generated hidden cost filtering, and residual predicate costing.
