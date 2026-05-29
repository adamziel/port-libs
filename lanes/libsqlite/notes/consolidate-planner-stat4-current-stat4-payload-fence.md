# sqlplanner STAT4 expression partial current-source next253

Adds a current-source STAT4 expression payload fence after accepted next250
partial-predicate validation. The planner now rejects cursor reuse when the
STAT4 expression payload for a yielded rowid has a stale expression key or stale
covered column image after a WordPress option import updates copied
`wp_options` rows.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentStat4PayloadFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-stat4-payload-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentStat4PayloadFenceTest.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-stat4-payload-fence.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `+66` focused PASS lines in the new lane-scoped
test file. Mapped coverage is unchanged; this is focused PHP behavior coverage
rather than a new manifest-backed upstream inventory row.

Dependency closure: no new support component needed. The slice reuses the
existing current-source STAT4 expression partial planner structures and adds a
bounded payload row-image proof.

Non-overlap: this extends accepted next250 partial-predicate rowid fencing with
current-source STAT4 expression payload row-image validation. It avoids next250
predicate implication, next247 boundary peers, expression ORDER BY,
range-cost ranking, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT,
and UTF clusters.
