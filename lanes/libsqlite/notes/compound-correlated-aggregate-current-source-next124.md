# Compound Correlated Aggregate Current Source Next124

- Implements composed aggregate projection rewriting for parser-level SELECT
  SQL so `sum(bytes) + outer.column`, `count(*) + outer.column`, and related
  expressions are evaluated from aggregate summary columns instead of trying
  to read the pre-aggregate source column after grouping.
- Preserves invariant columns during implicit aggregate summaries, and threads
  the qualified correlated outer row through empty aggregate subquery groups so
  scalar compound arms can still evaluate current-source references when the
  inner source has no matching rows.
- Adds focused coverage for scalar compound subqueries over copied
  `wp_options` / option-meta rows using `UNION`, `UNION ALL`, `INTERSECT`,
  `EXCEPT`, `HAVING`, `ORDER BY`, `LIMIT`, and `OFFSET` with composed
  aggregate expressions.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCorrelatedAggregateCurrentSourceNext124Test.php`
  - `1 test files, 59 assertions, 0 failures`
  - `28` PASS lines
- `php lanes/libsqlite/examples/application-compound-correlated-aggregate-current-source-next124.php --self-test`
  - `application-compound-correlated-aggregate-current-source-next124 self-test passed`

Dependency closure:

- No new support component is needed. This reuses the existing bounded native
  PHP SELECT parser/executor, grouped aggregate, compound SELECT, and
  Application row-array fixtures.

Non-overlap:

- Avoids accepted compound recursive affinity/name-resolution/order-limit
  slices, accepted grouped SELECT text, accepted scalar subquery predicates,
  and accepted JSON/WAL/B-tree/VFS current-source clusters. This patch is
  limited to composed aggregate expressions inside correlated scalar compound
  subqueries.
