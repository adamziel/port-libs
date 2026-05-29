# VFS current-source next246-249

Behavior slice: `vfs-current-source-snapshot-reuse-publish-next246-249`

This prepares the follow-on VFS current-source layer after the ready
`next238-241` and reuse lease `next242-245` handoff. The slice captures a fresh
current-source snapshot after the `shared-cache-next245` publish receipt, reuses
that snapshot through a new lease, and publishes the next current-source receipt
only when the source metadata, publish count, and receipt digest still match.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next214-217`.
- Records ready `vfs-current-source-ready-next225` as the immediate
  predecessor.
- Reuses `vfs-current-source-reuse-publish-next226-229` publication fencing.
- Requires `vfs-current-source-ready-publish-next238-241`.
- Builds on `vfs-current-source-reuse-lease-publish-next242-245`.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat next214-217 snapshot/reuse mechanics, ready next222-225
hydration, dirty flush, checkpoint creation, or next226-229 receipt digest
fencing. It also avoids reintroducing next242-245 lease setup; the new surface is
the next snapshot/reuse/publish hop that proves the current-source receipt can be
reused again after `shared-cache-next245`.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next246-249.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next246-249.php --self-test`
- `git diff --check`
