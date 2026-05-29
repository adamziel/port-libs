# VFS current-source next234-237

Behavior slice: `vfs-current-source-reuse-ack-publish-next234-237`

This prepares the follow-on VFS current-source layer after ready `next226-229`.
The slice preserves the ready snapshot, publish receipt digest, and reuse lease
guards, then requires a consumer acknowledgement of the reused publish receipt
before the current source can be republished for the next handoff.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next206-209`.
- Records ready `vfs-current-source-ready-next214-217`.
- Reuses `vfs-current-source-reuse-publish-next218-221`.
- Builds directly on `vfs-current-source-reuse-lease-publish-next226-229`.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat next206-209 capture/reuse, next214-217 readiness,
next218-221 publish fencing, or next226-229 lease validation. It also avoids
the parallel next230-233 ownership surface. The new surface is the reuse
acknowledgement gate between an already published reused snapshot and the next
current-source publication.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext234237Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext234237Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next234-237.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext226229Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext234237Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next234-237.php --self-test`
- `git diff --check`
