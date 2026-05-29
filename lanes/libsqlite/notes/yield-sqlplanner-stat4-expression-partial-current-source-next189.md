# SQLite planner STAT4 expression partial current-source next189

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next189`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
layered on the accepted next185 sample-provenance fence. It admits a current
STAT4 partial expression-index window only after every selected row payload and
every current STAT4 sample rowid re-evaluates against the current row image:

- `lower(option_name)` still matches the expected expression key;
- partial predicate terms still hold for expression ranges, `autoload = 'yes'`,
  and `option_name IS NOT NULL`;
- payload drift, autoload drift, NULL-name drift, and unsupported predicate
  operators force `requires-current-source-reprepare`.

WordPress smoke: `wordpress-sqlplanner-stat4-expression-partial-current-source-next189.php`
models copied `wp_options` plugin-admin pagination after ANALYZE/source changes,
where a stale prepared partial expression index must not reuse STAT4 samples
whose current payload no longer belongs to the partial index.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Test.php`
  - `1 test files, 74 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next189.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-current-source-next189 self-test passed`

Dashboard delta: `+74` focused libsqlite PASS lines from the new next189 test.
Mapped upstream coverage remains unchanged; this composes already mapped STAT4,
partial-index, expression-index, and current-source planner evidence.

Dependency closure: no new support component is needed. The slice reuses
lane-local current-source STAT4 expression partial planners and adds a bounded
payload-level expression/partial-predicate recheck.

Non-overlap: avoids accepted next185 sample provenance, next186 IN windows,
next165 range admission, expression ORDER BY, range-cost ranking, JSON planner,
WAL/VFS, B-tree, PRAGMA, trigger, and compound SELECT clusters. The new surface
is payload-level partial predicate proof for current STAT4 sample rowids before
the partial expression-index plan is admitted.
