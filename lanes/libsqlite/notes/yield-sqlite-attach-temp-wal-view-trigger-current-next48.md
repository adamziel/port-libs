# yield-sqlite-attach-temp-wal-view-trigger-current-next48

## Behavior

Adds `SQLiteAttachTempWalViewTriggerPlan`, a bounded current/next planner for
triggers that resolve through attached schemas and TEMP shadowing while writes
are routed to the correct journal family:

- main or attached schemas with WAL metadata use
  `SQLiteWalAppendPlan::checkpointAppendCurrentNext()`.
- TEMP writes are kept out of WAL and marked as temp rollback-journal work.
- attached/main writes without WAL metadata remain explicit rollback-journal
  work instead of being silently counted as WAL writes.

The Application smoke uses copied `wp_options`/`wp_option_audit` style triggers:
a TEMP trigger on `main.wp_options` writes one TEMP audit row and one qualified
main audit row, proving the TEMP shadow route does not hide the main WAL append.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalViewTriggerCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

## Non-Overlap

This avoids accepted `SQLiteAttachTempViewTriggerYieldPlan` body-yield coverage,
accepted WAL append/checkpoint/savepoint byte materialization, accepted VFS
writer/rollback/sync application, and accepted JSON table/SELECT SQL clusters.
The new behavior is the schema/journal routing layer that composes trigger
resolution with WAL current/next visibility for main/attached writes while
leaving TEMP writes on rollback-journal routing.

## Dependency Closure

No new support component is needed. The slice reuses the existing attached
schema catalog, trigger yield planner, WAL parser, and WAL append current/next
planner.
