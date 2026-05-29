# compound-select-window-recursive-limit-current-source-recursive-window-promotion-barrier

Status: focused PHP behavior growth for compound SELECT current-source handoff
where a recursive queue and window frame must both be acknowledged before a
next-source cursor is promoted.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`.
It layers on the accepted current-page-handoff current-page handoff and adds a promotion
barrier made from:

- current compound page acknowledgement token;
- recursive CTE queue trace token, including LIMIT/OFFSET skipped and emitted
  rows;
- per-arm window-frame metadata token.

WordPress path: `wordpress-compound-select-window-recursive-limit-recursive-window-promotion-barrier.php`
models copied `wp_options` preview rows where `plugin_prime` appears in the
next source but must stay hidden until the current recursive/windowed page and
its queue/window metadata are acknowledged.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitRecursiveWindowPromotionBarrierTest.php
php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-recursive-window-promotion-barrier.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitRecursiveWindowPromotionBarrierTest.php
php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-recursive-window-promotion-barrier.php
```

Focused result: `1 test files, 468 assertions, 0 failures` with 74 PASS lines.
Example result: JSON self-test emitted status
`compound-select-window-recursive-limit-current-source-recursive-window-promotion-barrier-ready`.

Expected dashboard movement: `phpPass +74`, from `116842` to `116916`.
`benchmarkDenominator.mapped` remains `639 / 1589`; this is current-source PHP
behavior over already mapped compound SELECT, recursive CTE LIMIT/OFFSET, and
window-function inventory.

Dependency closure: no new support component is needed. The patch reuses
lane-local `SQLiteSelectSql`, accepted union-except-dense-rank-limit/current-page-handoff compound recursive/window
handoff behavior, recursive CTE trace metadata, and window row-array planning.

Non-overlap: avoids accepted next226 sum/count EXCEPT+INTERSECT behavior,
union-except-dense-rank-limit dense-rank UNION/EXCEPT source tokens, current-page-handoff page-only current-source
handoff, JSON table source/cursor/constraint work, WAL/VFS/B-tree/storage
clusters, encoding/collation slices, planner range-cost work, and suite
evidence handoffs. The new surface is the combined page + recursive-trace +
window-frame promotion barrier before a next-source cursor is exposed.
