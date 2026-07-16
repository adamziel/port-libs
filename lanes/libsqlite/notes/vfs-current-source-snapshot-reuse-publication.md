# VFS current-source snapshot reuse publication

Consolidation slice: `consolidate-final-numbered-methods-wal-vfs-fifty-seventh-pass`

The VFS current-source snapshot/reuse/publication implementation now uses the
stable private `runSnapshotReusePublication()` helper and descriptive
`*SnapshotReusePublication()` support helpers instead of the generated numbered
wrapper names. The direct test and Application smoke were renamed to stable
snapshot-reuse filenames while preserving the same assertions and self-test
coverage.

Dependency closure:

- Reuses `vfs-current-source-dirty-flush-checkpoint-next198-201`.
- Records ready `vfs-current-source-ready-next202-205` as the local predecessor.
- Adds no support component outside the libsqlite lane.

Non-overlap:

This does not repeat open/write/flush/checkpoint mechanics or the ready
publication layer. The preserved behavior is clean source snapshot reuse and
stale-reader fencing.

Validation:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceSnapshotReuseTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-current-source-snapshot-reuse.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceSnapshotReuseTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-snapshot-reuse.php --self-test`
- `git diff --check -- lanes/libsqlite`
