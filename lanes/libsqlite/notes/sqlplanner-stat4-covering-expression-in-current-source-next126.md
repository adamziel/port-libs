# SQL Planner STAT4 Covering Expression IN Current Source Next126

This slice adds a bounded current-source planner wrapper for STAT4-backed
covering expression indexes used by `IN (...)` probes.

- Reprepares stale prepared plans when schema cookie, STAT4 generation, root
  page, index SQL, covering columns, or STAT4 sample signatures changed.
- Builds a multi-seek cursor tape for expression-index `IN` values and only
  materializes current rows whose expression keys are present in refreshed
  STAT4 samples.
- Keeps payload columns and expression projections on the covering index
  cursor, with `DeferredSeek` omitted when coverage is complete.
- Adds guarded fallback coverage for non-covering plans, missing STAT4 samples,
  missing current-source samples, invalid sources, and malformed predicates.

Application path: copied `wp_options` plugin setting lookups often probe a small
set of lower-cased option names. After `ANALYZE`, this lets the native PHP
planner discard stale expression-index samples and continue reading option
payloads from the current covering index cursor without ext/sqlite.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionInCurrentSourceNext126Test.php`
- `php -l lanes/libsqlite/examples/application-stat4-covering-expression-in-current-source-next126.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionInCurrentSourceNext126Test.php`
- `php lanes/libsqlite/examples/application-stat4-covering-expression-in-current-source-next126.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 66 assertions, 0 failures`.

Non-overlap: avoids accepted next122 bounded range covering expression STAT4,
next109/next117 JSON expression covering current-source streams, expression
ORDER BY, expression-index range-cost ranking, JSON table planner, WAL, VFS,
B-tree, encoding, and suite evidence clusters. This patch is limited to
multi-seek `IN` expression probes over refreshed current-source STAT4 samples.

Dependency closure: no new support component is needed; this reuses existing
native PHP expression-index parsing, STAT4 sample estimates, and covering
cursor diagnostics.
