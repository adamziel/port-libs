# VFS current-source next214-217

Behavior slice: `vfs-current-source-snapshot-reuse-publish-next214-217`

This follow-on starts after ready `vfs-current-source-ready-next210-213` and
adds publication-ticket reuse for already captured current-source snapshots.
The planner publishes a stable snapshot ticket, lets readers reuse the ticket
only while the source identity/data-version/durable-count still match, and
fences revoked, dirty, missing, or stale publication tickets.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next214-217.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next214-217.php --self-test`
- `git diff --check`

Non-overlap:

This avoids status/progress files and does not repeat snapshot
capture/reuse/publish mechanics from next206-209, ready publication from
next202-205, dirty flush/checkpoint behavior, or unrelated WAL, pager, B-tree,
JSON, planner, PRAGMA, and suite evidence work.

Dependency closure:

No new support component is needed; the slice reuses lane-local VFS current
source identity, snapshot, durable receipt, data-version, and reader handoff
metadata.
