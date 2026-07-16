# trigger-recursive-view-returning-current-source-next200

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` current-source handoff.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext200Plan`.
It composes the accepted next194 `SQLITE_DONE` source-cookie gate with a
current-source drain high-water barrier. The next-source recursive view
`RETURNING` rows are exposed only when the current source reports the expected
drain count, last current resume token, and generation epoch; stale partial
drain metadata keeps next-source rows quarantined even when the lower done gate
would otherwise admit them.

Application relevance: copied `wp_options` imports routed through recursive views
can finish previewing all current-source yielded `RETURNING` rows before a
reparsed next view/trigger source contributes rows to the same import cursor.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext200Test.php`
  - `1 test files, 80 assertions, 0 failures`
  - 80 PASS lines
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next200.php`
  - `application-trigger-recursive-view-returning-current-source-next200 self-test passed`

Expected dashboard movement: `phpPass +80` from the new focused test file.
Mapped upstream coverage remains unchanged; this is current-source PHP behavior
over already mapped trigger/view/RETURNING inventory.

Non-overlap: extends accepted next194 `SQLITE_DONE`/source-cookie gating with a
current drain-count and high-water resume-token admission barrier. It avoids
next194 done-gate repeats, next187 drain tickets, next184 checkpoint admission,
next170/next194 source-cookie behavior, row-value `RETURNING`, schema reparse,
deferred FK trigger behavior, WAL/pager/B-tree/JSON/PRAGMA/encoding clusters,
and suite evidence handoffs.

Dependency closure: no new support component is needed. The implementation
reuses native PHP recursive view trigger `RETURNING` rows, resume tokens, and
current-source handoff metadata.
