# yield-sqlite-planner-stat4-json-covering-order-current-next55

Adds matched STAT4 current/next evidence to `SQLiteSelectExpressionIndexPlan`
for JSON expression covering indexes. The bounded planner now reports both the
full STAT4 cursor and the samples that match the current equality, range,
BETWEEN, or IN constraint.

Behavior covered:

- `jsonb_extract(option_value, '$.plugin.channel')` covering expression indexes
  with partial `autoload = 'yes'` proof.
- `->>` JSON text operator indexes remain a separate function family from
  `jsonb_extract`.
- Expression `ORDER BY` followed by covering tail columns is satisfied only
  when the expression and tail order match the index.
- Matched STAT4 current/next evidence is exposed for point, IN, BETWEEN, and
  open/closed range predicates.
- Invalid STAT4 payloads and invalid covering-column lists throw bounded
  planner errors.

Application smoke:

- `examples/application-planner-stat4-json-covering-order-current-next55.php`
  previews a copied `wp_options` plugin-channel index plan with matched keys
  `beta,delta,stable,theta`, covering payload columns, partial-index proof, and
  expression ordering.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4JsonCoveringOrderCurrentNext55Test.php
php -l lanes/libsqlite/examples/application-planner-stat4-json-covering-order-current-next55.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4JsonCoveringOrderCurrentNext55Test.php
php lanes/libsqlite/examples/application-planner-stat4-json-covering-order-current-next55.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses the
existing native PHP `SQLiteSelectExpressionIndexPlan`, `SQLiteCreateIndex`, and
JSON path/index parsing helpers.

Non-overlap: this does not repeat accepted STAT4 partial-covering order,
STAT4 skip-scan covering, JSON path SELECT execution, JSON table source/cursor
or hidden/visible constraints, SQL expression ORDER BY text execution, VFS/WAL
writer/sync/rollback clusters, B-tree page/root/overflow clusters, or Unicode
GLOB behavior. The new behavior is narrower planner evidence for matched STAT4
samples on JSON expression covering indexes.
