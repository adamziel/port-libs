# SQLite planner STAT4 expression partial current-source next238

Status: focused PHP behavior growth for STAT4 partial expression-index planner
current-source reuse.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
It composes the accepted next235 vector-counter fence and adds a covering
payload fence: every current partial-index row must have a matching
`stat4ExpressionPayloads` row with the same expression key and covering column
values before the index-only current-source cursor is admitted.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Test.php`
- `1 test files, 70 assertions, 0 failures`

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next238.php --self-test`
- Expected output includes `stat4-expression-partial-current-source-next238-ready`.

Dashboard delta:

- `phpPass`: `119121 -> 119191` (`+70` focused PASS lines).
- Mapped upstream coverage unchanged at `642 / 1589`; this is focused planner
  behavior over already mapped expression-index/partial-index/STAT4 inventory.

Dependency closure:

- No new support component is needed. The slice reuses lane-local STAT4
  expression partial metadata, next235 vector-counter validation, and current
  row payload materialization.

Non-overlap:

- Avoids accepted next235 vector counters, next231 page membership, next228
  sample partial proof, expression ORDER BY, range-cost ranking, JSON, WAL,
  VFS, B-tree, trigger, and UTF clusters. This slice only checks covering
  payload staleness for current-source partial expression indexes.
