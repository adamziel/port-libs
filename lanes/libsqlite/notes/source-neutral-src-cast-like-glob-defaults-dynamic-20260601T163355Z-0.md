# Source-Neutral CAST/LIKE/GLOB Defaults Cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T163355Z-0`

Base accepted HEAD: `961d532798b4f10d7a9114bf6d87ff0b412e3bc9`

## Changes

- Neutralized `SQLiteTenantJsonWalSavepointPlan` public inputs and observable result keys from legacy site/network naming to generic tenant/aggregate naming.
- Updated the directly coupled tenant JSON WAL savepoint test and application example to use generic tenant settings fixtures and the renamed result keys.
- Extended source-neutral guards so the tenant JSON WAL savepoint planner is covered by the no-domain and tenant-savepoint source scans.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTenantJsonWalSavepointPlan.php && php -l lanes/libsqlite/tests/SQLiteTenantJsonWalSavepointCurrentNext47Test.php && php -l lanes/libsqlite/tests/SQLiteTenantSavepointWalSourceNeutralTest.php && php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php && php -l lanes/libsqlite/examples/application-tenant-json-wal-savepoint.php`
  - Result: all changed PHP files reported no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantJsonWalSavepointCurrentNext47Test.php`
  - Result: `1 test files, 102 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantSavepointWalSourceNeutralTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`
  - Result: `3 test files, 54 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-tenant-json-wal-savepoint.php --self-test`
  - Result: `application-tenant-json-wal-savepoint self-test passed`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

## Root Harness

Not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This cleanup reuses the existing tenant JSON WAL savepoint planner, savepoint stack, JSON validation, and WAL frame preview helpers.

## Non-Overlap

This source-neutral slice does not add upstream PASS rows or lane counters. It removes production-source site/network naming in the tenant JSON WAL savepoint planner without changing the accepted SQLite behavior clusters.
