# SQLite planner STAT4 expression partial current-source next193

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next193`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
layered on the accepted next189 current payload/partial-predicate fence. It
admits a current STAT4 partial expression-index window only after rowid alias
constraints from `rowid`, `_rowid_`, or `oid` are rechecked against the current
source:

- rowid alias terms are stripped before delegating to the accepted next189
  expression/partial-predicate planner, then applied as a current-source fence;
- selected window rowids and current STAT4 sample rowids must satisfy `=`, `IN`,
  `BETWEEN`, `<`, `<=`, `>`, or `>=` alias constraints;
- missing current rows, unsupported rowid operators, invalid integer literals,
  and stale sample rowids force `requires-current-source-rowid-reprepare`.

Application smoke:
`application-sqlplanner-stat4-expression-partial-current-source-next193.php`
models copied `wp_options` plugin-admin pagination after ANALYZE/source changes,
where a stale prepared partial expression index must not reuse STAT4 samples
unless current `rowid` alias constraints still hold.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext193Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next193.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next193 self-test passed`

Dashboard delta: `+65` focused libsqlite PASS lines from the new next193 test.
Mapped upstream coverage remains unchanged; this composes already mapped STAT4,
partial-index, expression-index, rowid-alias, and current-source planner
evidence without claiming a new manifest-backed upstream row.

Dependency closure: no new support component is needed. The slice reuses
lane-local current-source STAT4 expression partial planners and adds a bounded
rowid-alias admission fence.

Non-overlap: avoids accepted next189 payload partial fences, next188 duplicate
peer fences, range-cost ranking, expression ORDER BY, JSON planner, WAL/VFS,
B-tree, PRAGMA, trigger, UTF, and compound SELECT clusters. The new surface is
rowid alias proof for current STAT4 sample/window rowids before the partial
expression-index plan is admitted.
