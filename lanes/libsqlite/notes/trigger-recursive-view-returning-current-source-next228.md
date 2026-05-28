# trigger-recursive-view-returning-current-source-next228

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at the current/next source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext228Plan`,
an additive snapshot acknowledgement layer after the accepted next224 current
returning source seal. Current recursive view-trigger `RETURNING` rows are
tagged with deterministic snapshot acknowledgements. Next-source rows remain
held until the current snapshot token, view source, trigger source, and all
current-row acknowledgements match.

WordPress path: `wordpress-trigger-recursive-view-returning-current-source-next228.php`
models a copied `wp_options` recursive import view where plugin DDL changes the
next view/trigger source while current `RETURNING` rows are still visible.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext228Test.php`
- Result: `1 test files, 95 assertions, 0 failures`
- PASS-line delta: `+95`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next228.php`
- Result: `wordpress-trigger-recursive-view-returning-current-source-next228 self-test passed`

Dashboard delta: `phpPass +95` from `110487` to `110582`. Mapped coverage is
unchanged at `628 / 1589`; this is current-source PHP behavior over already
mapped trigger/view/RETURNING inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view trigger, RETURNING, current-source epoch, and next224
source-seal machinery.

Non-overlap: avoids accepted next222 ticket handoff, next224 source seal,
row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers,
schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.
The new surface is specifically the final current-source snapshot
acknowledgement before next-source RETURNING rows are published.
