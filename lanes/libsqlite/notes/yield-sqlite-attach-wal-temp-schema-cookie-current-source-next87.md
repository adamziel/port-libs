# ATTACH WAL Temp Schema Cookie Current Source Next87

## Scope

- Added `SQLiteAttachWalTempSchemaCookieSourcePlan` for current/next schema-cookie attribution across `main`, `temp`, and attached schemas.
- The slice covers committed WAL page-1 cookies, ignored uncommitted WAL page-1 tails, temp rollback-journal schema-cookie changes, attached WAL schema-cookie stability, and prepared statement reuse/reprepare decisions.
- Application smoke: copied `wp_options` and temp import staging statements report which schema-cookie source expires active readers, write statements, and qualified attached-schema readers.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaCookieCurrentSourceNext87Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures
```

New focused PASS lines: `+62`.

## Non-Overlap

This does not repeat accepted batch83-84 ATTACH temp/WAL schema-cookie cache invalidation or trigger-cache reprepare coverage. The new behavior is source attribution and statement action planning across mixed WAL page-1, temp rollback-journal, and uncommitted WAL-tail cookie sources.

## Dependency Closure

No new support component is needed. The patch reuses bounded native PHP schema/WAL/temp planning inputs and does not require ext/sqlite, live services, or new shared dependencies.
