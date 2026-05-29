# VFS current-source next206-209

Behavior slice: `vfs-current-source-snapshot-reuse-next206-209`

This prepares the follow-on VFS current-source layer after ready next198-205.
The slice captures a clean checkpointed current-source snapshot, admits reuse
only while handle/path/owner/data-version/durable-count still match, and blocks
publication while dirty pages remain. It keeps throughput high by validating the
metadata fence directly instead of introducing sleeps, polling, or global state.

Dependency closure:

- Reuses `vfs-current-source-dirty-flush-checkpoint-next198-201`.
- Records ready `vfs-current-source-ready-next202-205` as the local predecessor.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat open/write/flush/checkpoint mechanics from next198-201 or
the ready next202-205 publication layer. The new surface is clean source
snapshot reuse and stale-reader fencing for next206-209.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next206-209.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next206-209.php --self-test`
- `git diff --check`
