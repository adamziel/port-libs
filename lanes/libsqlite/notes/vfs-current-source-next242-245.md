# VFS current-source next242-245

Behavior slice: `vfs-current-source-snapshot-reuse-publish-next242-245`

This prepares the follow-on VFS current-source layer after ready `next234-237`.
The slice keeps the acknowledged reuse-publish receipt from `next234-237`,
validates that the reusable current-source snapshot still matches the live
handle/path/owner/data-version and publish receipt digest, then records a
bounded reuse claim before the snapshot can be published for the next handoff.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next206-209`.
- Records ready `vfs-current-source-ready-next214-217`.
- Reuses `vfs-current-source-reuse-publish-next218-221`.
- Builds through `vfs-current-source-reuse-lease-publish-next226-229`.
- Builds directly on `vfs-current-source-reuse-ack-publish-next234-237`.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not touch active `next238-241` files or repeat next206-209
capture/reuse, next214-217 readiness, next218-221 publish fencing, next226-229
lease validation, or next234-237 acknowledgement gating. The new surface is the
post-ack reuse claim required before another current-source snapshot publication.

Validation:

- `composer dump-autoload`
- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext242245Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext242245Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next242-245.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext234237Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext242245Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next242-245.php --self-test`
- `git diff --check`
