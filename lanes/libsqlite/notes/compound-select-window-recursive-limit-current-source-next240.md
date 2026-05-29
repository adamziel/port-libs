# compound-select-window-recursive-limit-current-source-next240

Status: focused PHP behavior growth for compound SELECT output where recursive
CTE rows and copied `wp_options` rows feed per-arm window values, then a final
compound `LIMIT/OFFSET` page suppresses already-produced current-source rows.

Behavior added:

- `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` reuses the
  accepted next237 compound recursive/window/dequeue execution.
- The new `compoundFinalPageSpilloverDrainNext240` surface materializes labels
  skipped by final `OFFSET` and truncated by final `LIMIT`.
- A spillover drain token and per-row acknowledgement set hold next-source row
  promotion until current-source compound rows outside the visible page are
  acknowledged.
- Cursor validation rejects stale spillover tokens, missing acknowledgements,
  and unexpected acknowledgement values.

WordPress path:

- `wordpress-compound-select-window-recursive-limit-current-source-next240.php`
  models copied `wp_options` import previews where a next-source plugin option
  enters the final compound page while current-source recursive/window rows are
  skipped or truncated by the current page boundary.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext240Test.php`
- Result: `1 test files, 416 assertions, 0 failures`
- PASS-line delta: `+76`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next240.php --self-test`
- Result: `wordpress-compound-select-window-recursive-limit-current-source-next240 self-test passed`

Expected dashboard movement: `phpPass +76` from the new focused test file,
`120636 -> 120712`. Mapped coverage remains `644 / 1589`; this is
current-source PHP behavior over already mapped compound SELECT, recursive CTE,
window, and LIMIT/OFFSET inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed; this reuses lane-local
`SQLiteSelectSql`, recursive CTE queue tracing, compound set operators,
rank/row_number window output, and final LIMIT/OFFSET result paging.

Non-overlap: avoids accepted next236 metric fences, next237 recursive dequeue
fences, next226/next228/next230/next233/next235 compound recursive/window
handoffs, JSON table source/cursor/constraint work, WAL/VFS, B-tree, planner,
trigger, PRAGMA, encoding, and suite evidence clusters. The narrower surface is
the final compound page spillover drain before next-source promotion.
