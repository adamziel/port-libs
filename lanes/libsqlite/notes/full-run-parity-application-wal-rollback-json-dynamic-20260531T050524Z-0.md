# full-run-parity-application-wal-rollback-json-dynamic-20260531T050524Z-0

Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`.

## Behavior

Extended `SQLiteTenantJsonWalSavepointPlan` with source-neutral aggregate
network rollback reporting and optional application for tenant/global JSON
imports in WAL mode. Per-tenant savepoints still preserve released tenant rows
for diagnostics, but callers can now request that any tenant or global import
failure truncates the aggregate network WAL publication back to the header.

The generic application example was updated from legacy option/autoload-shaped
rows to setting/key/load-policy rows and now exercises the network rollback
mode in its self-test.

## Evidence

Before this slice:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantJsonWalSavepointCurrentNext47Test.php
1 test files, 74 assertions, 0 failures
```

After this slice:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantJsonWalSavepointCurrentNext47Test.php
1 test files, 102 assertions, 0 failures

php lanes/libsqlite/examples/application-tenant-json-wal-savepoint.php --self-test
application-tenant-json-wal-savepoint self-test passed
```

Expected focused movement: `+28` assertions, from `2202926` to `2202954` pass
/ 0 fail in lane-local status.

## Non-Overlap

This avoids JSON table cursor/source/hidden/visible constraint work, WAL byte
truncation helpers, rollback-journal apply/commit, VFS file writer and lock
state work, pager WAL dynamic corpus rows, and the previous per-statement or
per-tenant JSON WAL savepoint paths. The added behavior is specifically the
aggregate network WAL publication rollback decision for already-isolated
tenant/global import failures.

## Dependency Closure

No new support component is needed. The slice reuses the accepted JSON import
WAL savepoint planner, JSON subtype/JSONB helpers, and savepoint WAL rollback
primitive.
