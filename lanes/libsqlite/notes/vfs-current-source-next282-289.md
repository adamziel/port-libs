# VFS current-source next282-289

Behavior slice: `vfs-current-source-snapshot-reuse-publish-next282-289`

This prepares the follow-on VFS current-source layer after the merged
`next274-281` chain. The slice captures a fresh clean current-source snapshot
after the `shared-cache-next281` publication, records the matching reuse
acknowledgement, claims the snapshot for reuse, then publishes the bounded
`shared-cache-next289` receipt.

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

This does not touch prior `next274-281` files or repeat next206-209
capture/reuse, next214-217 readiness, next218-221 publish fencing, next226-229
lease validation, next234-237 acknowledgement gating, next266-273 publish
claiming, or next274-281 pre-acknowledged claim publishing. The new surface is
the post-next281 snapshot capture plus the matching reuse/publish handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next282-289.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next282-289.php --self-test`
- `git diff --check`
