# VFS current-source next274-281

Behavior slice: `vfs-current-source-snapshot-reuse-publish-next274-281`

This prepares the follow-on VFS current-source layer after ready `next266-273`.
The slice keeps the acknowledged reuse-publish receipt from `next266-273`,
validates that the reusable current-source snapshot still matches the live
handle/path/owner/data-version and publish receipt digest, then records a
bounded reuse claim before the snapshot can be published for the next handoff.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next206-209`.
- Records ready `vfs-current-source-ready-next214-217`.
- Reuses `vfs-current-source-reuse-publish-next218-221`.
- Builds through `vfs-current-source-reuse-lease-publish-next226-229`.
- Builds directly on `vfs-current-source-reuse-ack-publish-next234-237`.
- Builds directly on `vfs-current-source-snapshot-reuse-publish-next242-245`.
- Builds directly on `vfs-current-source-snapshot-reuse-publish-next254-257`.
- Builds directly on `vfs-current-source-snapshot-reuse-publish-next258-265`.
- Builds directly on `vfs-current-source-snapshot-reuse-publish-next266-273`.
- Builds directly on `vfs-current-source-snapshot-reuse-publish-next274-281`.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not touch prior `next266-273` files or repeat next206-209
capture/reuse, next214-217 readiness, next218-221 publish fencing, next226-229
lease validation, next234-237 acknowledgement gating, or next266-273 publish
claiming. The new surface is the
post-ack reuse claim required before another current-source snapshot publication.

Validation:

- `composer dump-autoload`
- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next274-281.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next274-281.php --self-test`
- `git diff --check`
