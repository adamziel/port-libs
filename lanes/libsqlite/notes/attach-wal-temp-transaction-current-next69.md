# ATTACH WAL Temp Transaction Current/Next 69

## Behavior

Adds `SQLiteAttachWalTempTransactionCurrentNext69Plan`, a bounded attach/temp/WAL transaction visibility planner for schema-cookie changes across main, temp, and attached databases. It models:

- committed WAL schema cookies and committed page-1 WAL frame cookies as the current baseline;
- current readers continuing on their pre-transaction schema-cookie snapshot;
- next readers seeing committed schema-cookie changes only after transaction commit;
- temp schema writes using temp rollback-journal routing rather than WAL routing;
- savepoint rollback restoring uncommitted temp/attached schema-cookie increments while preserving earlier outer writes;
- full transaction rollback restoring all post-transaction cookies and avoiding reprepare.

This is intentionally separate from accepted current-next67 prepared-statement lifecycle expiry and current-next52 schema-trigger/cache routing. The new slice focuses on transaction-level cookie visibility and rollback restoration rather than prepared statement step/reset actions or trigger-body routing.

## Focused Evidence

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext69Test.php`

Result:

`1 test files, 70 assertions, 0 failures`

`php lanes/libsqlite/examples/application-attach-wal-temp-transaction-current-next69.php`

Result:

```json
{
    "commit_status": "committed",
    "commit_reprepare_schemas": [
        "archive",
        "main",
        "temp"
    ],
    "main_cookie_after_commit": 42,
    "temp_cookie_after_savepoint_rollback": 6,
    "full_rollback_status": "rolled_back",
    "main_cookie_after_full_rollback": 41
}
```

## Dashboard Delta

Adds 70 focused PHP PASS lines. `phpPass` moves from 25516 to 25586. Mapped upstream denominator is unchanged because this adds runtime behavior coverage without a new upstream manifest inventory unit.

## Dependency Closure

No new support component is needed. The planner is lane-local PHP and reuses existing WAL/temp schema-cookie concepts without requiring external services, native extensions, or shared checkout changes.

## Next Task

Wire this transaction-level schema-cookie visibility into broader pager/VFS transaction application once the native executor begins applying attach/temp WAL schema writes directly.
