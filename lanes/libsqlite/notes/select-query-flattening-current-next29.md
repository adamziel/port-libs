# SELECT query flattening current-next29

Status delta: added `SQLiteSelectFlatteningPlan`, a bounded native PHP planner
surface that classifies simple derived-table `FROM` subqueries as flattenable
or materialization-required before parser/executor dispatch. The planner records
the derived alias, projection alias map, merged inner/outer WHERE clause, and a
preview flattened SQL string for safe single-source cases.

Focused behavior:

- Flattenable single derived SELECT sources with inner/outer WHERE predicates,
  alias projection, inherited inner ORDER BY when the outer query is not order
  sensitive, outer ORDER BY/LIMIT, implicit subquery aliases, and `SELECT ALL`.
- Materialization blockers for inner DISTINCT, GROUP/HAVING/aggregate, LIMIT,
  compound SELECT, order-sensitive inner ORDER BY, window projections, outer
  DISTINCT, outer aggregate/GROUP BY, and outer JOIN sources.
- Application smoke covers copied `wp_options` style autoload filtering where
  `autoload = 'yes'` can be merged with an outer option-id range before native
  execution.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectQueryFlatteningCurrentNext29Test.php`
- `php lanes/libsqlite/examples/application-select-query-flattening-current-next29.php`
- `php -l lanes/libsqlite/src/SQLiteSelectFlatteningPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSelectQueryFlatteningCurrentNext29Test.php`
- `php -l lanes/libsqlite/examples/application-select-query-flattening-current-next29.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted derived-table materialization,
correlated derived subqueries, SELECT SQL text/JOIN/GROUP/subquery/comma-LIMIT
execution, expression ORDER BY, JSON table source/cursor/constraint work, WAL
or VFS writer/sync/lock clusters, B-tree page-move/root-collapse/overflow
clusters, Unicode GLOB, or batch23 metadata/planner/VDBE work. The new behavior
is the planner classification surface that decides when a derived SELECT query
can be flattened and why current SQLite-style blockers require materialization.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP SELECT parser conventions and adds a lane-local planner
helper only.
