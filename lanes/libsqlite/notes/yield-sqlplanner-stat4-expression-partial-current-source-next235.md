# SQLite planner STAT4 expression partial current-source next235

Status: focused PHP behavior growth for a STAT4 partial expression-index planner
current-source fence.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
which extends the accepted next232 first-prefix STAT4 counter fence to
multi-prefix STAT4 vectors for a partial expression index. The selected
`lower(option_name), blog_id` sample vectors must match current partial-index
rows for `neq`, `nlt`, and `ndlt` before the current-source cursor program is
admitted.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Test.php`
- `1 test files, 73 assertions, 0 failures`

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next235.php --self-test`
- Expected output includes `stat4-expression-partial-current-source-next235-ready`.

Dashboard delta:

- `phpPass`: `116842 -> 116915` (`+73` focused PASS lines).
- Mapped upstream coverage unchanged at `639 / 1589`; this is focused
  behavior coverage, not a new manifest-backed upstream inventory row.

Dependency closure:

- No new support component is needed. The slice reuses lane-local STAT4
  expression partial planner metadata, current-source row materialization, and
  the accepted next232 counter fence, adding only vector-counter validation.

Non-overlap:

- Avoids accepted next232 first-prefix counter cardinalities, next231 page
  membership, next228 sample partial proof, expression ORDER BY, range-cost
  ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice is
  only multi-prefix STAT4 vector validation for partial expression indexes.
