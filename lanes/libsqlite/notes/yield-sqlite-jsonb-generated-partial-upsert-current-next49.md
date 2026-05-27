# yield-sqlite-jsonb-generated-partial-upsert-current-next49

## Status delta

- Added `SQLiteJsonbGeneratedPartialUpsertPlan` for bounded UPSERT row routing over copied `wp_options` rows whose JSONB `option_value` feeds generated columns and partial indexes.
- The slice models conflict updates, stale `DO UPDATE WHERE` skips, inserts, JSONB `jsonb_set` mutation inputs from `excluded` and current rows, generated-column re-evaluation, and before/after partial-index entry diffs.
- Added focused PHP coverage with 8 PASS cases / 55 assertions.
- Expected dashboard movement: `phpPass` +8 (`17920 -> 17928`) and mapped upstream inventory `462 -> 463` for one new focused JSONB/generated-column/partial-index UPSERT behavior row.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbGeneratedPartialUpsertCurrentNext49Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS jsonb generated partial upsert current next49 table metadata
PASS jsonb generated partial upsert current next49 row routing
PASS jsonb generated partial upsert current next49 generated values after upsert
PASS jsonb generated partial upsert current next49 decodes updated JSONB payloads
PASS jsonb generated partial upsert current next49 partial index membership changes
PASS jsonb generated partial upsert current next49 slug and rank index images
PASS jsonb generated partial upsert current next49 logical index actions
PASS jsonb generated partial upsert current next49 validation errors

1 test files, 55 assertions, 0 failures
```

## Non-overlap

- Avoids accepted batch38 `SQLiteGeneratedJsonPathIndexPlan` JSONB generated-index B-tree update coverage by adding UPSERT conflict/insert/skip routing and partial-index membership diffs around generated columns.
- Avoids accepted batch23 partial-index WHERE implication planning; this slice uses generated-column partial index membership after row mutation rather than planner proof selection.
- Avoids accepted UPSERT trigger/FK and UPSERT RETURNING savepoint clusters; this slice does not model triggers, FKs, savepoints, or RETURNING.

## Dependency closure

No new support component is needed. The implementation reuses existing native PHP JSONB, JSON mutation/extract, generated-column dependency analysis, and index leaf image helpers.
