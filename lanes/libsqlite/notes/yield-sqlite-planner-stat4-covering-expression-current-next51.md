# yield-sqlite-planner-stat4-covering-expression-current-next51

This slice extends `SQLiteSelectExpressionIndexPlan` so a STAT4-estimated
expression-index plan can also prove that projected expression payloads are
covered by the same index. The existing planner already estimated first
expression probes with STAT4; the new behavior is the covering decision for
additional indexed expressions such as `upper(option_name)`,
`length(option_name)`, and JSON operator expressions.

Focused behavior:

- records explicit `coveringExpressions` metadata on an index definition;
- treats the searched first expression as covered, while allowing additional
  indexed expressions to satisfy SELECT-list payloads without a table lookup;
- keeps STAT4 current/next estimates for equality, range, `BETWEEN`, `IN`,
  numeric casts, and JSON expression probes while evaluating expression
  coverage;
- distinguishes JSON expression operator kind and path, so `->>` and `->`
  payloads do not accidentally cover each other;
- rejects malformed covering-expression metadata and malformed requested
  expression operands.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionCurrentNext51Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionCurrentNext51Test.php
php -l lanes/libsqlite/examples/application-planner-stat4-covering-expression-current-next51.php
No syntax errors detected in lanes/libsqlite/examples/application-planner-stat4-covering-expression-current-next51.php
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionCurrentNext51Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 70 assertions, 0 failures
```

`grep -c '^PASS '` over the focused run output reported `57` new PASS lines.

```text
php lanes/libsqlite/examples/application-planner-stat4-covering-expression-current-next51.php --self-test
application-planner-stat4-covering-expression-current-next51 self-test passed
```

Non-overlap: this does not repeat batch48 STAT4 skip-scan covering loop
evidence, batch37 first-expression STAT4 estimates, expression-index range-cost
ranking, SQL expression `ORDER BY`, JSON hidden/visible constraints, or
parser-level JSON table source/cursor work. The new behavior is
expression-payload covering for STAT4 expression-index plans.

Dependency closure: no new support component is needed; this reuses the native
PHP expression-index planner, JSON path validation, and STAT4 sample evidence
already present in the libsqlite lane.
