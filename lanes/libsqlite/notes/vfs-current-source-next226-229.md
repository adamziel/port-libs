# VFS current-source next226-229

Behavior slice: `vfs-current-source-reuse-lease-publish-next226-229`

This prepares the follow-on VFS current-source layer after the ready
`next218-221` reuse/publish handoff. The slice keeps the existing ready snapshot
and publish receipt fencing, then records a reuse lease before allowing a
follow-on publish. The lease pins the snapshot token, data-version, publish
receipt count, and publish receipt digest observed during reuse.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next206-209`.
- Records ready `vfs-current-source-ready-next214-217` as the immediate
  predecessor.
- Reuses `vfs-current-source-reuse-publish-next218-221` publication fencing.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat next206-209 snapshot/reuse mechanics, ready next214-217
hydration, dirty flush, checkpoint creation, or next218-221 receipt digest
fencing. The new surface is reuse lease validation between a reused ready
current-source snapshot and its follow-on publish.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-next226-229.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next226-229.php --self-test`
- `git diff --check`
