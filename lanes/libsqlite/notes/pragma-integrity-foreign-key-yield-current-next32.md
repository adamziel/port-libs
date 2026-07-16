# PRAGMA Integrity Foreign-Key Yield Current Next32

This slice adds a bounded native PHP stream for Application import preflights
that need both `PRAGMA integrity_check` diagnostics and
`PRAGMA foreign_key_check` rows without materializing an unbounded result page
in one UI step.

## Behavior

- `SQLitePragmaIntegrityForeignKeyYield::collect()` combines integrity errors
  first, then all-schema foreign-key violations using the existing SQLite
  schema order (`temp`, `main`, then attached schemas).
- `page(..., offset, 32)` returns stable `count`, `total`, `next_offset`, and
  `complete` metadata for current/next page iteration.
- Clean databases with clean foreign keys return an empty diagnostic stream;
  no synthetic `ok` row is emitted in the combined stream.
- The helper reuses the existing native PHP `SQLitePragmaIntegrityCheck` and
  `SQLitePragmaForeignKeyIntegrity` implementations; no ext/sqlite dependency
  is introduced.

## Verification

Focused commands from this isolated worktree:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyYieldCurrentNext32Test.php
php lanes/libsqlite/examples/application-pragma-integrity-foreign-key-yield-current-next32.php
php -l lanes/libsqlite/src/SQLitePragmaIntegrityForeignKeyYield.php
php -l lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyYieldCurrentNext32Test.php
php -l lanes/libsqlite/examples/application-pragma-integrity-foreign-key-yield-current-next32.php
git diff --check -- lanes/libsqlite
```

## Non-Overlap

This avoids accepted standalone `PRAGMA integrity_check`/`quick_check`, direct
`PRAGMA foreign_key_check`, schema-current foreign-key integrity, schema PRAGMA
catalogs, JSON table source/cursor/constraint work, SELECT SQL text/subquery/
GROUP/ORDER/LIMIT clusters, VFS writer/sync/lock/rollback clusters, WAL
checkpoint/savepoint byte truncation, B-tree page move/root-collapse/overflow
release, and Unicode GLOB work. The new behavior is only the bounded
current/next32 diagnostic stream composed from the existing PRAGMA engines.

## Dependency Closure

No new support component is needed. The slice reuses native PHP page/header,
integrity, and foreign-key PRAGMA helpers already present in the libsqlite
lane.
