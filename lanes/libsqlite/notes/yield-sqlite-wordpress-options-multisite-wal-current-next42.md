# yield-sqlite-wordpress-options-multisite-wal-current-next42

## Scope

Adds `SQLiteWordPressMultisiteOptionsWalPlan` for copied WordPress multisite option imports that span `wp_sitemeta` plus per-blog `wp_{blog_id}_options` tables in one committed WAL append transaction.

The slice is intentionally separate from accepted single-site `SQLiteWordPressOptionsWalImportPlan`, WAL append transaction persistence, WAL byte truncation, VFS file writer, VFS savepoint rollback, rollback-journal commit, checkpoint transaction, JSON table, B-tree page move, and SQL text executor clusters.

## Behavior

- Normalizes network and blog option rows into table-qualified keys so duplicate names like `siteurl` remain isolated per blog table.
- Preserves table-local `option_id` values for updates and allocates inserted IDs from the matching table only.
- Materializes one WAL commit containing rewritten option pages plus one autoload index page per multisite table.
- Reports current-reader visibility before the commit and next-reader visibility after the appended commit.
- Applies the generated WAL append through `SQLiteVfsFileWriter::applyWalAppendTransactions()` for a WordPress smoke path.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWordPressOptionsMultisiteWalCurrentNext42Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
68 PASS lines
1 test files, 79 assertions, 0 failures
```

The lane-status `phpPass` value moves from `15189` to `15257`, an exact `+68` PASS-line delta from the focused test output. `benchmarkDenominator.mapped` is unchanged because this is focused PHP behavior coverage rather than newly mapped upstream inventory.

## Dependency Closure

No new support component is needed. The slice reuses existing native WAL parsing/checksum, WAL append planning, reader snapshots, and VFS file-handle append application.
