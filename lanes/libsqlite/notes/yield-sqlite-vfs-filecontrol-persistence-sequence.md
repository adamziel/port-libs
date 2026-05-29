# libsqlite VFS file-control persistence persistent file-control sequence

## Behavior

Adds a bounded VFS xFileControl persistence model for close/reopen boundaries.
The slice distinguishes database-persistent file controls (`persist_wal`,
`reserve_bytes`, and powersafe-overwrite state) from per-handle controls such as
`mmap_size`, `chunk_size`, `name_hint`, `write_hint`, `overwrite`, and sync
counters. Reopening a copied WordPress database handle reloads only the
persistent controls and clears transient handle hints.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsFileControlPersistenceSequenceTest.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-vfs-filecontrol-persistence-sequence.php`
- Syntax: `php -l lanes/libsqlite/src/SQLiteVfsFileControlPersistencePlan.php`,
  `php -l lanes/libsqlite/tests/SQLiteVfsFileControlPersistenceSequenceTest.php`,
  and `php -l lanes/libsqlite/examples/wordpress-vfs-filecontrol-persistence-sequence.php`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-overlap

This does not repeat batch68/69 VFS file-control state parsing/current-next
coverage or batch74 open/file-control locking. It adds the missing persistence
boundary across close/reopen for the persistent VFS file-control slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded VFS
capability and file-control state helpers.
