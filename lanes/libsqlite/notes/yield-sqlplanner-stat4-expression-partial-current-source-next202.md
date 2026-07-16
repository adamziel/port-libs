# sqlplanner-stat4-expression-partial-current-source-next202

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` composes
the accepted current-source STAT4 expression partial-index chain through next196
and adds a prepared-vs-current partial predicate definition fingerprint. This
keeps a prepared `lower(option_name)` partial index plan from being reused when
the current source has the same index name and compatible rows but the partial
`WHERE` definition changed.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext202Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next202.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext202Test.php`
- Result: `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next202.php --self-test`
- Result: `application-sqlplanner-stat4-expression-partial-current-source-next202 self-test passed`

Non-overlap: avoids accepted next196 duplicate peer order, next192
covering-column admission, next191 payload expression-key rechecks, next189
row-level payload partial predicate checks, expression ORDER BY, range-cost
ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only
guards partial-index predicate definition drift between the prepared and current
sources.

Dependency closure: no new support component is needed; this reuses the native
current-source STAT4 expression partial-index planner fixtures and adds a
lane-local predicate-definition fingerprint check.

Next task: wire the same definition fingerprint into the broader SELECT planner
cache/reprepare path once the current-source STAT4 partial-expression chain is
integrated.
