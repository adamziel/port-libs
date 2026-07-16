# sqlplanner STAT4 expression partial current-source next250

Adds a current-source partial-predicate fence after accepted next247 STAT4
boundary-peer validation. The new planner proof rejects reuse when a prepared
partial expression index would yield rowids that no longer satisfy the current
partial-index WHERE terms after ANALYZE/schema refresh.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateFenceTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-partial-predicate-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateFenceTest.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-partial-predicate-fence.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `+59` focused PASS lines in the new lane-scoped
test file. Mapped coverage is unchanged; this is focused PHP behavior coverage
rather than a new manifest-backed upstream inventory row.

Dependency closure: no new support component needed. The slice reuses the
existing current-source STAT4 expression partial planner data structures.

Non-overlap: this extends accepted next247 boundary-peer validation with
current partial-index predicate rowid fencing. It avoids next246 duplicate
cardinality, next247 boundary peers, expression ORDER BY, range-cost ranking,
JSON, WAL, VFS, B-tree, trigger, PRAGMA, and UTF clusters.
