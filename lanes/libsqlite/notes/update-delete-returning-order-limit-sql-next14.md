# UPDATE/DELETE RETURNING ORDER/LIMIT SQL text next14

## Behavior

Adds a bounded native PHP SQL text executor for copied `wp_options` maintenance
statements that combine `UPDATE` or `DELETE` with `RETURNING`, `ORDER BY`, and
`LIMIT`/`OFFSET`. The slice delegates final row selection and RETURNING row
materialization to the existing `SQLiteUpdateDeleteLimitPlan`, but adds the
parser-level bridge needed for SQL text statements.

Covered semantics:

- `DELETE FROM ... WHERE ... RETURNING ... ORDER BY ... LIMIT ...`
- `UPDATE ... SET ... WHERE ... RETURNING ... ORDER BY ... LIMIT ...`
- comma-form `LIMIT offset,count`
- old-row DELETE RETURNING output and new-row UPDATE RETURNING output
- source-order mutation output after ORDER/LIMIT row selection
- simple Application-shaped predicates and assignment expressions
- malformed SQL guard paths

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php`
  - `1 test files, 53 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-update-delete-returning-order-limit-sql.php`
  - passed and emitted copied `wp_options` delete/update RETURNING diagnostics

`phpPass` increases by the verified focused PASS-line delta: `3796 -> 3849`
(+53).

## Non-overlap

This does not repeat the accepted standalone `SQLiteUpdateDeleteLimitPlan`
ORDER/LIMIT helper or `SQLiteUpdateDeleteReturningCorpusTest` projection
corpus. The new surface is parser-level UPDATE/DELETE SQL text dispatch for the
combined `RETURNING` plus `ORDER BY` plus `LIMIT/OFFSET` shape.

## Dependency closure

No new support component is required. The slice reuses existing native PHP
row-array selection, RETURNING projection, LIKE/GLOB matching, and test harness
components.
