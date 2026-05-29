# rowvalue-update-delete-returning-window-current-source-next298-301

This slice prepares the next298-301 after-ready metadata for row-value
`UPDATE`/`DELETE ... RETURNING` window current-source work. It consumes exactly
four ready candidate payloads for next294 through next297, validates their
after-ready state and retry window rows, then emits deterministic next298
receipt, next299 ledger, next300 handoff, and next301 seal hashes.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareNext298301.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext298301Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next298-301-after-ready.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext298301Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next298-301-after-ready.php --self-test`

Dependency closure: no new support component is needed; the slice is a
post-ready receipt layer over next294-297 row-value UPDATE/DELETE RETURNING
window current-source candidate payloads.

Non-overlap: avoids suite evidence, JSON table, WAL/VFS, planner, PRAGMA,
ATTACH, B-tree, and unrelated window slices. The narrow surface is after-ready
receipt preparation for the assigned row-value RETURNING window chain.
