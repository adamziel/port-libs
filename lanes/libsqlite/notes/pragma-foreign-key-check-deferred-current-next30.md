# PRAGMA foreign_key_check deferred current next30

Slice: `yield-sqlite-pragma-foreign-key-check-deferred-current-next30`

This slice adds `SQLitePragmaForeignKeyCheckDeferredPlan`, a bounded native PHP
transaction-state wrapper around the existing `SQLitePragmaForeignKeyCheck`
row-array checker. It covers the upstream-visible behavior that
`PRAGMA foreign_key_check` reports the current database image even while
foreign-key violations are still deferred until COMMIT:

- transient child INSERT violations are visible before the parent repair;
- parent DELETE and child UPDATE violations are visible before rollback/repair;
- `PRAGMA foreign_key_check(table)` filtering still applies to the current
  deferred state;
- composite foreign-key repairs clear current violations before COMMIT;
- nested savepoint rollback removes only inner deferred violations;
- unrepaired deferred violations fail at COMMIT.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyCheckDeferredCurrentNext30Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures

php lanes/libsqlite/examples/application-pragma-foreign-key-check-deferred-current-next30.php --self-test
application-pragma-foreign-key-check-deferred-current-next30 self-test passed
```

Dashboard delta: `phpPass` increases by the verified `+52` focused PASS lines,
from `10028` to `10080`. No new mapped upstream denominator unit is claimed.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local row-array FK checker and native PHP test runner; it does
not require ext/sqlite, live services, shared caches, or a new dependency row.

Non-overlap: this does not repeat accepted static `PRAGMA foreign_key_check`
affinity/collation coverage, deferred cascade/action planners, trigger
deferred-FK planning, UPSERT trigger/FK yield behavior, or recent VFS/WAL/
B-tree/JSON/SELECT accepted clusters. The new behavior is specifically
current transaction-image visibility for `foreign_key_check` over deferred
constraint states.
