# VFS current-source next230-233

Behavior slice: `vfs-current-source-snapshot-reuse-publish-next230-233`

This prepares the follow-on VFS current-source layer after the ready
`next222-225` and reuse lease `next226-229` handoff. The slice captures a fresh
current-source snapshot after the `shared-cache-next229` publish receipt, reuses
that snapshot through a new lease, and publishes the next current-source receipt
only when the source metadata, publish count, and receipt digest still match.

Dependency closure:

- Reuses `vfs-current-source-snapshot-reuse-next206-209`.
- Records ready `vfs-current-source-ready-next214-217` as the immediate
  predecessor.
- Reuses `vfs-current-source-reuse-publish-next218-221` publication fencing.
- Requires `vfs-current-source-ready-publish-next222-225`.
- Builds on `vfs-current-source-reuse-lease-publish-next226-229`.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat next206-209 snapshot/reuse mechanics, ready next214-217
hydration, dirty flush, checkpoint creation, or next218-221 receipt digest
fencing. It also avoids reintroducing next226-229 lease setup; the new surface is
the next snapshot/reuse/publish hop that proves the current-source receipt can be
reused again after `shared-cache-next229`.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext230233Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext230233Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next230-233.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext206209Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext230233Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-current-source-next230-233.php --self-test`
- `git diff --check`
