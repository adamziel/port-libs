# WAL Hot-Journal Savepoint Checkpoint Current Source Next204

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan`, a
generation-ticket fence for the WAL/hot-journal/savepoint/checkpoint path. It
runs after the accepted next203 WAL/page-digest lease check and retains a
page-cache lease only when the lease also carries the current checkpoint
generation, schema cookie, page count, database digest, and non-dirty/non-closed
state.

Stale checkpoint generation, stale reader epoch, schema-cookie mismatch,
page-count mismatch, stale database digest, dirty leases, and closed leases are
routed to reopen.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Test.php

Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

PHP lint:

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next204.php
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next204.php
```

The smoke returned status
`wal-hot-journal-savepoint-checkpoint-current-source-next204` with one admitted
current-generation `wp_options` lease and one stale-generation lease routed to
reopen.

## Dashboard Delta

Expected libsqlite `phpPass`: `99392 -> 99447` (`+55`) on acceptance, from the
focused PASS-line count in this slice. Mapped upstream coverage remains
`621 / 1589`; this slice does not claim a new manifest-backed upstream row.

## Non-Overlap

This slice fences current-source page-cache reuse with checkpoint generation
tickets after next203 page-digest leases. It does not repeat next203 WAL/page
digest lease checks, next202 file-handle receipts, next196 sidecar publication,
VFS savepoint rollback, rollback-journal apply, WAL byte truncation, or
accepted WAL hot-journal checkpoint reader-separation behavior.

## Dependency Closure

No new support component is needed. The slice reuses next203 checkpoint
page-cache lease metadata plus lane-local checkpoint generation, schema-cookie,
page-count, and database-digest receipts.
