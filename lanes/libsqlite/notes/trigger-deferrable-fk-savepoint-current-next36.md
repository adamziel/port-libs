# Trigger deferrable FK savepoint current-next36

Slice: `yield-sqlite-trigger-deferrable-fk-savepoint-current-next36`

This slice adds `SQLiteTriggerDeferrableFkSavepointPlan`, a bounded native PHP
planner for trigger-generated child/parent changes under nested SQLite
savepoints with deferrable foreign-key timing. It covers:

- deferred child checks surviving `RELEASE` until commit;
- `ROLLBACK TO` restoring table images, change counts, and deferred queues;
- initially immediate constraints deferred by a transaction-level
  `defer_foreign_keys`-style flag;
- `RESTRICT` parent deletes still reporting statement-time violations;
- trigger names and statement indexes preserved in current/next diagnostics.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferrableFkSavepointCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 65 assertions, 0 failures
```

Example verification:

```text
php lanes/libsqlite/examples/application-trigger-deferrable-fk-savepoint-current-next36.php --self-test
application-trigger-deferrable-fk-savepoint-current-next36 self-test passed
```

Status delta:

- `phpPass`: `12903` -> `12968` (`+65` focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused native behavior,
  not a new upstream inventory mapping.

Non-overlap:

This avoids accepted trigger/deferred-FK transaction-only planning,
recursive UPSERT conflict yield, recursive trigger savepoint UPSERT,
deferred FK check PRAGMA planning, WAL/savepoint byte truncation/recovery,
VFS savepoint rollback application, rollback-journal/VFS apply, SELECT/JSON/
B-tree/VFS/WAL batch30 clusters, and accepted trigger-depth-only diagnostics.
The new surface is deferrable foreign-key timing across nested savepoint
current/next state.

Dependency closure:

No new support component is needed. The slice reuses lane-local row-array
execution and native PHP savepoint snapshots; it does not require ext/sqlite,
upstream binaries, live services, or provider credentials.
