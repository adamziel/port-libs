# yield-sqlite-trigger-upsert-returning-recursive-current-next51

2026-05-27 isolated slice `yield-sqlite-trigger-upsert-returning-recursive-current-next51`.

## Behavior

Adds RETURNING projection support to `SQLiteRecursiveUpsertConflictYieldPlan` for
recursive trigger-generated UPSERT rows. The bounded executor now records
statement-current RETURNING rows for changed top-level and recursive trigger
UPSERTs, while skipped conflicts continue to yield no RETURNING row. Projection
coverage includes default full-row RETURNING, `*`, `new.*`, `excluded.*`,
`old.*` for updates, event/depth/source-trigger metadata, callables, and
malformed projection guards.

The Application smoke models a copied `wp_options` import where an update trigger
and insert trigger recursively UPSERT child option rows and reports the current
RETURNING stream for statement and trigger rows without requiring `ext/sqlite`.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentNext51Test.php
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines, 1 test files, 57 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-current-next51.php --self-test
application-trigger-upsert-returning-recursive-current-next51 self-test passed

php -l lanes/libsqlite/src/SQLiteRecursiveUpsertConflictYieldPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRecursiveUpsertConflictYieldPlan.php

php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentNext51Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentNext51Test.php

php -l lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-current-next51.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-current-next51.php

git diff --check -- lanes/libsqlite
clean
```

## Dashboard Delta

- `phpPass`: `18565 -> 18622` (+57 verified focused PASS lines).
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior
  growth, not a new upstream inventory mapping.

## Non-Overlap

This avoids accepted batch23 UPSERT trigger/FK yield behavior, next26 RETURNING
row-image coverage, next27 recursive savepoint UPSERT behavior, next30
recursive UPSERT conflict yield behavior, next32 trigger RETURNING secondary
UNIQUE conflict coverage, and batch48 view-trigger RETURNING savepoint coverage.
The new surface is the uncovered intersection: RETURNING projection rows for
recursive trigger-generated UPSERTs, including skipped conflict behavior and
source-trigger/depth metadata.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
row-array recursive UPSERT trigger executor and copied Application option
fixtures; no shell-out, network, upstream binary, or `ext/sqlite` dependency is
introduced.
