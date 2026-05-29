# VFS current-source next218-221

Behavior slice: `vfs-current-source-reuse-publish-next218-221`

This prepares the follow-on VFS current-source layer after the ready
`next214-217` handoff. The slice captures a ready source only when the current
source has a ready receipt, an existing publish receipt, and no dirty pages. It
then admits reuse/publish only while the source handle, path, owner,
data-version, publish receipt count, and publish receipt digest still match the
captured ready snapshot.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next206-209`.
- Records ready `vfs-current-source-ready-next214-217` as the immediate
  predecessor.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat next206-209 snapshot/reuse mechanics, ready next214-217
hydration, dirty flush, or checkpoint creation. The new surface is publication
receipt fencing for a ready current-source snapshot after the accepted
next214-217 handoff.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next218-221.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next218-221.php --self-test`
- `git diff --check`
