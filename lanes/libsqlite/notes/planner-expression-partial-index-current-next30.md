### 2026-05-27 planner expression partial index current-next30

This slice adds bounded expression-aware partial-index implication for
`SQLiteSelectExpressionIndexPlan`. `CREATE INDEX` partial `WHERE` predicates
can now parse and prove `lower(column)`, `upper(column)`, `length(column)`, and
`CAST(column AS INTEGER)` predicates against matching expression-index search
constraints.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionPartialIndexCurrentNext30Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 212 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-planner-expression-partial-index-current-next30.php --self-test
application-planner-expression-partial-index-current-next30 self-test passed
```

Behavior covered:

- expression partial `WHERE` predicates over `lower`, `upper`, `length`, and
  integer `CAST`;
- point, range, `BETWEEN`, and `IN` implication against expression predicates;
- AND/OR partial predicates where separate expression terms jointly prove the
  partial index window;
- ordinary-column proof still composing with expression partial proof for
  copied `wp_options` autoload indexes;
- rejection for broad ranges, all-NULL `IN` lists, unsupported expression
  predicates, wrong ordinary equality terms, and incompatible cast values.

Non-overlap:

This does not repeat accepted partial-index ordinary-column range implication,
partial-index WHERE proof, expression-index range-cost ranking, expression
`ORDER BY`, SELECT SQL text/subquery/GROUP BY dispatch, JSON table
source/cursor/constraint work, Unicode GLOB, B-tree page/freelist clusters, or
WAL/VFS transaction application. The new surface is expression predicates
inside the partial-index `WHERE` clause itself.

Dependency closure:

No new support component is needed. The implementation reuses the existing
lane-local `CREATE INDEX` parser, `SQLiteIndexPredicate`, and expression-index
planner.
