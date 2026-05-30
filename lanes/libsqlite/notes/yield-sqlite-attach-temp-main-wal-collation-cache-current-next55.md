# yield-sqlite-attach-temp-main-wal-collation-cache-current-next55

## Behavior

Adds `SQLiteAttachTempMainWalCollationCachePlan`, a bounded current/next planner
for ATTACH temp/main trigger programs that combines:

- existing trigger body yield routing across WAL, temp rollback, and rollback
  journal writes;
- committed page-1 WAL schema-cookie state for main/attached schema caches;
- per-schema registered collation caches used by prepared trigger programs.

The planner reports changed schema cookies, stable versus expired trigger
programs, operation route counts, required collations, missing collations, and
schema dependencies for main/temp/attached trigger bodies.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempMainWalCollationCacheCurrentNext55Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 75 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-attach-temp-main-wal-collation-cache-current-next55.php
```

Expected output includes `changed_schemas: ["main"]`,
`expired_triggers: ["main_autoloaded_update", "temp_main_bridge"]`, and WAL plus
temp rollback route counts for copied `wp_options` trigger routing.

## Status Delta

- `phpPass`: `20008 -> 20083` from the verified focused PASS-line delta.
- `benchmarkDenominator.mapped`: unchanged.

## Non-Overlap

This avoids accepted/queued attach51-54 surfaces by not adding another
schema-cache table-list, SQL-text extraction, schema-trigger, or trigger
collation-only variant. It specifically covers the combined current/next
decision where WAL schema-cookie changes and per-schema collation caches decide
whether prepared temp/main/attached trigger programs must be reprepared after
yielded trigger writes.

## Dependency Closure

No new support component is needed. The slice reuses lane-local
`SQLiteAttachedSchemaCatalog`, `SQLiteAttachTempWalViewTriggerPlan`,
`SQLiteAttachTempViewCollationPlan`, and `SQLiteWalAppendPlan`.
