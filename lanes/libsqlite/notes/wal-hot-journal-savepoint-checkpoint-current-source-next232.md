# WAL hot-journal savepoint checkpoint current-source next232

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a focused
post-publication reader-slot admission check for the WAL hot-journal,
savepoint, and checkpoint current-source chain.

The new planner starts from an admitted next229 reopened-handle plan and admits
reader slots only when they still match the published current source:

- source token and writer generation;
- checkpoint database digest and non-stale WAL digest;
- schema cookie and restarted WAL salt;
- checkpoint page membership;
- reset read-mark frame;
- no visible hot journal, open savepoint, or dirty cache;
- lock receipt present before serving reopened checkpoint pages.

This is intentionally a reader-slot current-source fence. It does not repeat
reset publication, reopened handle page-digest coverage, WAL byte truncation,
VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction
planning, or VFS file writing.

## WordPress smoke

`examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next232.php`
models a copied WordPress import reopening schema, `wp_options`, and autoload
index reader slots after hot-journal recovery and savepoint checkpoint
publication.

## Verification

Focused test command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext232Test.php
```

Result:

```text
1 test files, 66 assertions, 0 failures
```

Example smoke command:

```bash
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next232.php
```

Result:

```text
wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next232 self-test passed
```

## Dependency closure

No new support component is needed. The slice reuses next229 handle publication
metadata plus lane-local reader-slot generation, schema-cookie, WAL-salt,
page-coverage, and lock receipt data.
