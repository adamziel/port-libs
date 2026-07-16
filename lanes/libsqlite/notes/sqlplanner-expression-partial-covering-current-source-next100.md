# SQL Planner Expression Partial Covering Current Source Next100

Adds index-definition fingerprinting to
`SQLiteStat4PartialCoveringCurrentSourcePlan`. Prepared partial covering plans
now reprepare when the current source has the same schema cookie, STAT4
generation, and projection list but the partial covering index root page, SQL
definition, or STAT4 payload changed.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionPartialCoveringCurrentSourceNext100Test.php`
  - `1 test files, 55 assertions, 0 failures`
  - 55 focused PASS lines.
- `php lanes/libsqlite/examples/application-planner-expression-partial-covering-current-source-next100.php --self-test`
  - `application-planner-expression-partial-covering-current-source-next100 self-test passed`

Application relevance: copied `wp_options` plugin scans can keep the same schema
cookie and STAT4 generation across import planning while the selected partial
covering index root/stat4 payload changes. The smoke proves the native PHP
planner switches to current source and avoids stale covering-index estimates.

Non-overlap: this avoids accepted expression ORDER BY, expression-index
range-cost ranking, STAT4 partial-covering current-source next90 sample
generation invalidation, STAT4 order-covering current-source next94, JSON
planner/table work, WAL/VFS/B-tree clusters, and Unicode LIKE/GLOB behavior.
The new behavior is only index-signature invalidation for existing STAT4
partial covering plans.

Dependency closure: no new support component is needed. This reuses the
existing CREATE INDEX parser, partial-index proof, multicolumn range planner,
and STAT4 current/next evidence helpers.
