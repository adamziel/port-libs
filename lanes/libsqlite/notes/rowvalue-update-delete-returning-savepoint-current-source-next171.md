# Row-Value UPDATE/DELETE RETURNING Savepoint Current Source Next171

## Behavior

This slice extends the native PHP `SQLiteUpdateDeleteReturningSql` executor so
row-value `UPDATE` / `DELETE ... RETURNING` `WHERE` clauses can evaluate
top-level `OR` groups while preserving existing `AND` precedence and `BETWEEN`
handling.

The covered Application import shape is a copied `wp_options` cleanup savepoint:

- `UPDATE ... SET (status, option_value, bytes) = (...)` selects rows via
  `(blog_id, option_name) IN (...) OR row-value BETWEEN ... AND ... AND autoload = 'no'`.
- `DELETE ... RETURNING` then consumes the current source produced by that
  update using another `OR`-composed predicate.
- String literals containing `OR` are not split, and `NULL OR true` admits the
  true branch while unknown-only branches remain non-selected by `WHERE`.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext171Test.php
```

Result:

```text
1 test files, 41 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next171.php --self-test
```

Result:

```text
application-rowvalue-update-delete-returning-savepoint-current-source-next171 self-test passed
```

## Non-Overlap

This avoids accepted row-value next156/161/167 surfaces: it does not add another
conflict algorithm, retry, literal-clause parser, or rollback-to-savepoint
variant. The new behavior is specifically top-level `OR` grouping for current
source row-value predicates in UPDATE/DELETE RETURNING.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
row-value predicate evaluator, mutation planner, RETURNING projection, and
savepoint current-source modeling.
