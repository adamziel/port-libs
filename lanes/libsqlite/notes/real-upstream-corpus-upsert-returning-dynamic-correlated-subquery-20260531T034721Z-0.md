# real-upstream-corpus-upsert-returning-dynamic-correlated-subquery-20260531T034721Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-20.1`: `DELETE ... RETURNING` subqueries referencing the modified table are correlated and recomputed after each filtered delete.
  - `returning1-20.2`: full-table delete returns recomputed aggregate subquery values through the final empty-table `NULL` state.
  - `returning1-20.3`: correlated RETURNING subqueries may also reference the deleted outer row while recomputing against the post-delete table image.

## Behavior Ported

- Added `SQLiteRealUpstreamUpsertReturningDynamicCorrelatedSubqueryTest.php` with 1000 deterministic generic application seeds.
- Each seed checks filtered delete, full delete, empty-table aggregate `NULL` behavior, and outer-row offset correlation against the real upstream `returning1.test` scenarios.
- This is a test-only behavior corpus admission. The current native row-array RETURNING model already preserves the needed per-row post-change recomputation semantics.

## Non-Overlap

- Does not repeat accepted UPSERT conflict-arm priority, `upsert4`, `upsert5`, target-admission/target-first, scope matrix, insert-select, trigger/FK, row-value, or UPDATE/DELETE LIMIT RETURNING batches.
- This slice owns only `returning1.test` section 20 correlated RETURNING subquery behavior using generic application rows.

## Verification

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorrelatedSubqueryTest.php`
- Lint:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorrelatedSubqueryTest.php`
- Diff check:
  - `git diff --check -- lanes/libsqlite`

## Dependency Closure

- No new support component is needed. The slice reuses existing native PHP row-array DELETE/RETURNING modeling and deterministic aggregate recomputation.
